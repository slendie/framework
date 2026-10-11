<?php

declare(strict_types=1);

namespace Slendie\Database\Migrations;

use Closure;
use RuntimeException;
use Slendie\Database\DatabaseManager;
use Slendie\Support\Str;

/**
 * Descobre, executa e reverte migrações, registando-as em batches na tabela `migrations`.
 *
 * Os comandos de consola são camadas finas sobre `run`, `rollback`, `reset`, `fresh` e `status`;
 * `setOutput()` recebe as mensagens (`Migrating: x`, `Migrated:  x (12.3ms)`) para o comando as imprimir.
 */
class Migrator
{
    /** @var array<string, mixed> ficheiro => valor devolvido pelo `require` (um ficheiro só pode ser incluído uma vez) */
    protected static array $requiredPathCache = [];

    protected ?string $connection = null;

    /** @var (Closure(string): void)|null */
    protected ?Closure $output = null;

    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        protected MigrationRepository $repository,
        protected DatabaseManager $db,
        protected array $paths = [],
    ) {
    }

    // -------------------------------------------------------------------------
    // Configuração
    // -------------------------------------------------------------------------

    /** Regista mais uma pasta de migrações (aplicação, packages). */
    public function path(string $path): static
    {
        $path = rtrim($path, '/\\');

        if (! in_array($path, $this->paths, true)) {
            $this->paths[] = $path;
        }

        return $this;
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /** Ligação em que correm (e onde fica o registo); null = a por omissão. */
    public function setConnection(?string $name): static
    {
        $this->connection = $name;
        $this->repository->setSource($name);

        return $this;
    }

    public function getRepository(): MigrationRepository
    {
        return $this->repository;
    }

    /**
     * @param  Closure(string): void  $writer
     */
    public function setOutput(Closure $writer): static
    {
        $this->output = $writer;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Execução
    // -------------------------------------------------------------------------

    /**
     * Corre as migrações pendentes num novo batch (com `step`, um batch por ficheiro).
     * Devolve os nomes corridos; com `pretend` devolve o SQL que correria, sem executar nada.
     *
     * @param  array{pretend?: bool, step?: bool}  $options
     * @return list<string>
     */
    public function run(array $options = []): array
    {
        $pretend = (bool) ($options['pretend'] ?? false);
        $step = (bool) ($options['step'] ?? false);

        $files = $this->getMigrationFiles();

        if (! $pretend && ! $this->repository->repositoryExists()) {
            $this->repository->createRepository();
        }

        $exists = $this->repository->repositoryExists();
        $pending = array_diff_key($files, array_flip($exists ? $this->repository->getRan() : []));

        if ($pending === []) {
            $this->note('Nothing to migrate.');

            return [];
        }

        $batch = $exists ? $this->repository->getNextBatchNumber() : 1;
        $result = [];

        foreach ($pending as $name => $file) {
            $migration = $this->resolve($file);

            if (! $migration->shouldRun()) {
                $this->note("Skipping:  {$name}");

                continue;
            }

            $this->note("Migrating: {$name}");
            $start = microtime(true);

            $sql = $this->execute($migration, 'up', $pretend);

            if (! $pretend) {
                $this->repository->log($name, $batch);
            }

            $this->note("Migrated:  {$name} (".$this->elapsed($start).'ms)');

            $result = $pretend ? [...$result, ...$sql] : [...$result, $name];

            if ($step) {
                $batch++;
            }
        }

        return $result;
    }

    /**
     * Reverte os últimos `step` batches (por omissão 1) ou um batch concreto, por ordem inversa.
     * Devolve os nomes revertidos (ou o SQL, com `pretend`).
     *
     * @param  array{step?: int, batch?: int|null, pretend?: bool}  $options
     * @return list<string>
     */
    public function rollback(array $options = []): array
    {
        if (! $this->repository->repositoryExists()) {
            $this->note('Nothing to rollback.');

            return [];
        }

        $batch = $options['batch'] ?? null;

        $records = $batch !== null
            ? $this->repository->getMigrationsByBatch($batch)
            : $this->repository->getMigrations($options['step'] ?? 1);

        return $this->rollbackRecords($records, (bool) ($options['pretend'] ?? false));
    }

    /**
     * Reverte todas as migrações executadas.
     *
     * @param  array{pretend?: bool}  $options
     * @return list<string>
     */
    public function reset(array $options = []): array
    {
        if (! $this->repository->repositoryExists()) {
            $this->note('Nothing to rollback.');

            return [];
        }

        $records = array_map(
            fn (string $migration): array => ['migration' => $migration, 'batch' => 0],
            array_reverse($this->repository->getRan()),
        );

        return $this->rollbackRecords($records, (bool) ($options['pretend'] ?? false));
    }

    /**
     * Apaga todas as tabelas (sem usar os `down()`) e corre tudo de novo.
     *
     * @param  array{step?: bool}  $options
     * @return list<string>
     */
    public function fresh(array $options = []): array
    {
        $this->repository->getConnection()->getSchemaBuilder()->dropAllTables();

        return $this->run($options);
    }

    /**
     * @return list<array{migration: string, ran: bool, batch: int|null}>
     */
    public function status(): array
    {
        $batches = $this->repository->repositoryExists() ? $this->repository->getBatches() : [];
        $status = [];

        foreach (array_keys($this->getMigrationFiles()) as $name) {
            $status[] = ['migration' => $name, 'ran' => isset($batches[$name]), 'batch' => $batches[$name] ?? null];
        }

        return $status;
    }

    // -------------------------------------------------------------------------
    // Ficheiros
    // -------------------------------------------------------------------------

    /**
     * `nome => caminho` de todos os `*.php` das pastas, ordenado pelo nome (prefixo `YYYY_MM_DD_HHMMSS_`).
     *
     * @return array<string, string>
     *
     * @throws RuntimeException se o mesmo nome existir em duas pastas
     */
    public function getMigrationFiles(): array
    {
        $files = [];

        foreach ($this->paths as $path) {
            foreach (glob($path.'/*.php') ?: [] as $file) {
                $name = basename($file, '.php');

                if (isset($files[$name]) && $files[$name] !== $file) {
                    throw new RuntimeException("A migração [{$name}] existe em duas pastas: [{$files[$name]}] e [{$file}].");
                }

                $files[$name] = $file;
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Carrega um ficheiro de migração: devolve uma instância (classe anónima) ou o nome da classe (nomeada).
     *
     * @throws RuntimeException
     */
    public function resolve(string $file): Migration
    {
        $path = realpath($file) ?: $file;

        $value = self::$requiredPathCache[$path] ??= (static fn (string $path): mixed => require $path)($path);

        if (is_object($value)) {
            if (! $value instanceof Migration) {
                throw new RuntimeException("O ficheiro [{$file}] devolve um objecto que não estende ".Migration::class.'.');
            }

            return (clone $value)->setDatabaseManager($this->db);
        }

        $class = Str::studly((string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($file, '.php')));

        if (! class_exists($class, false)) {
            throw new RuntimeException("O ficheiro [{$file}] não devolve uma migração nem declara a classe [{$class}].");
        }

        $migration = new $class();

        if (! $migration instanceof Migration) {
            throw new RuntimeException("A classe [{$class}] tem de estender ".Migration::class.'.');
        }

        return $migration->setDatabaseManager($this->db);
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    /**
     * @param  list<array{migration: string, batch: int}>  $records
     * @return list<string>
     */
    protected function rollbackRecords(array $records, bool $pretend): array
    {
        if ($records === []) {
            $this->note('Nothing to rollback.');

            return [];
        }

        $files = $this->getMigrationFiles();
        $result = [];

        foreach ($records as $record) {
            $name = $record['migration'];

            if (! isset($files[$name])) {
                $this->note("Not found:  {$name}");

                continue;
            }

            $this->note("Rolling back: {$name}");
            $start = microtime(true);

            $sql = $this->execute($this->resolve($files[$name]), 'down', $pretend);

            if (! $pretend) {
                $this->repository->delete($name);
            }

            $this->note("Rolled back:  {$name} (".$this->elapsed($start).'ms)');

            $result = $pretend ? [...$result, ...$sql] : [...$result, $name];
        }

        return $result;
    }

    /**
     * Corre `up()`/`down()` (numa transacção, se o driver o permitir) ou, com `pretend`, só recolhe o SQL.
     *
     * @param  'up'|'down'  $method
     * @return list<string>
     */
    protected function execute(Migration $migration, string $method, bool $pretend): array
    {
        $name = $migration->getConnection() ?? $this->connection;
        $previous = $this->db->getDefaultConnection();

        // A facade Schema e o DB resolvem a ligação por omissão: durante a migração é a dela.
        if ($name !== null) {
            $this->db->setDefaultConnection($name);
        }

        try {
            $connection = $this->db->connection($name);
            $callback = static function () use ($migration, $method): void {
                $migration->$method();
            };

            if ($pretend) {
                return array_map(static fn (array $query): string => $query['sql'], $connection->pretend($callback));
            }

            if ($migration->withinTransaction && $connection->getSchemaGrammar()->supportsSchemaTransactions()) {
                $connection->transaction($callback);
            } else {
                $callback();
            }

            return [];
        } finally {
            $this->db->setDefaultConnection($previous);
        }
    }

    protected function note(string $message): void
    {
        if ($this->output !== null) {
            ($this->output)($message);
        }
    }

    protected function elapsed(float $start): string
    {
        return number_format((microtime(true) - $start) * 1000, 1, '.', '');
    }
}
