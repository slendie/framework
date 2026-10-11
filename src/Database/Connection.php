<?php

declare(strict_types=1);

namespace Slendie\Database;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Generator;
use PDO;
use PDOException;
use PDOStatement;
use Slendie\Database\Query\Builder;
use Slendie\Database\Query\Expression;
use Slendie\Database\Query\Grammar;
use Throwable;

/**
 * Uma ligação à base de dados: executa SQL com prepared statements, regista queries,
 * gere transacções (com savepoints) e cria Query Builders.
 */
class Connection
{
    protected ?PDO $pdo = null;

    /** @var PDO|Closure(): PDO */
    protected PDO|Closure $pdoResolver;

    protected int $transactions = 0;

    protected bool $loggingQueries = false;

    /** @var list<array{sql: string, bindings: array<array-key, mixed>, time_ms: float}> */
    protected array $queryLog = [];

    /** @var list<Closure(string, array<array-key, mixed>, float): void> */
    protected array $listeners = [];

    protected bool $pretending = false;

    /** @var list<array{sql: string, bindings: array<array-key, mixed>, time_ms: float}> */
    protected array $pretended = [];

    /**
     * @param  PDO|Closure(): PDO  $pdo  uma closure só liga à BD quando for precisa
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        PDO|Closure $pdo,
        protected string $driver,
        protected string $database,
        protected array $config,
        protected Grammar $grammar,
    ) {
        $this->pdoResolver = $pdo;

        if ($pdo instanceof PDO) {
            $this->pdo = $pdo;
        }
    }

    // -------------------------------------------------------------------------
    // Acessores
    // -------------------------------------------------------------------------

    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $resolver = $this->pdoResolver;
            $this->pdo = $resolver instanceof Closure ? $resolver() : $resolver;
        }

        return $this->pdo;
    }

    /** Fecha a ligação (a próxima query volta a ligar, se houver closure). */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->transactions = 0;
    }

    public function getDriverName(): string
    {
        return $this->driver;
    }

    public function getDatabaseName(): string
    {
        return $this->database;
    }

    public function getName(): ?string
    {
        $name = $this->config['name'] ?? null;

        return is_string($name) ? $name : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getGrammar(): Grammar
    {
        return $this->grammar;
    }

    public function raw(string|int|float $value): Expression
    {
        return new Expression($value);
    }

    public function table(string $table, ?string $as = null): Builder
    {
        return (new Builder($this))->from($table, $as);
    }

    // -------------------------------------------------------------------------
    // Execução
    // -------------------------------------------------------------------------

    /**
     * Prepara e executa; devolve um Statement para `fetch()/fetchAll()/fetchColumn()`.
     *
     * @param  array<array-key, mixed>  $bindings
     */
    public function query(string $sql, array $bindings = []): Statement
    {
        return $this->run($sql, $bindings, fn (string $sql, array $bindings): Statement => new Statement($this->execute($sql, $bindings)), new Statement());
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->fetchAll();
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        return $this->query($sql, $bindings)->fetch();
    }

    /**
     * Primeira coluna da primeira linha.
     *
     * @param  array<array-key, mixed>  $bindings
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        return $this->query($sql, $bindings)->fetchColumn();
    }

    /**
     * Linha a linha, sem carregar tudo em memória.
     *
     * @param  array<array-key, mixed>  $bindings
     * @return Generator<int, array<string, mixed>>
     */
    public function cursor(string $sql, array $bindings = []): Generator
    {
        $statement = $this->query($sql, $bindings);
        $i = 0;

        while (($row = $statement->fetch()) !== null) {
            yield $i++ => $row;
        }
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    public function insert(string $sql, array $bindings = []): bool
    {
        return $this->statement($sql, $bindings);
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     * @return int linhas afectadas
     */
    public function update(string $sql, array $bindings = []): int
    {
        return $this->affectingStatement($sql, $bindings);
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     * @return int linhas afectadas
     */
    public function delete(string $sql, array $bindings = []): int
    {
        return $this->affectingStatement($sql, $bindings);
    }

    /**
     * Executa uma instrução sem resultados (DDL, INSERT...).
     *
     * @param  array<array-key, mixed>  $bindings
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        $this->query($sql, $bindings);

        return true;
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    /**
     * Executa SQL sem prepared statement (p. ex. vários comandos DDL). Nunca com input do utilizador.
     */
    public function unprepared(string $sql): bool
    {
        return $this->run($sql, [], function (string $sql): bool {
            $this->getPdo()->exec($sql);

            return true;
        }, true);
    }

    public function lastInsertId(?string $sequence = null): string|int
    {
        if ($this->pretending) {
            return 0;
        }

        $id = $this->getPdo()->lastInsertId($sequence);

        return $id === false ? 0 : $id;
    }

    /**
     * @template T
     *
     * @param  array<array-key, mixed>  $bindings
     * @param  Closure(string, array<array-key, mixed>): T  $callback
     * @param  T  $pretendResult
     * @return T
     */
    protected function run(string $sql, array $bindings, Closure $callback, mixed $pretendResult): mixed
    {
        $bindings = $this->prepareBindings($bindings);
        $start = microtime(true);

        if ($this->pretending) {
            $this->pretended[] = ['sql' => $sql, 'bindings' => $bindings, 'time_ms' => 0.0];

            return $pretendResult;
        }

        try {
            $result = $callback($sql, $bindings);
        } catch (PDOException $e) {
            throw new QueryException($sql, $bindings, $e, $this->getName());
        }

        $this->logQuery($sql, $bindings, round((microtime(true) - $start) * 1000, 3));

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    protected function execute(string $sql, array $bindings): PDOStatement
    {
        $statement = $this->getPdo()->prepare($sql);
        $this->bindValues($statement, $bindings);
        $statement->execute();

        return $statement;
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    protected function bindValues(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            $type = match (true) {
                $value === null => PDO::PARAM_NULL,
                is_int($value) => PDO::PARAM_INT,
                is_resource($value) => PDO::PARAM_LOB,
                default => PDO::PARAM_STR,
            };

            $statement->bindValue(is_string($key) ? $key : $key + 1, is_float($value) ? (string) $value : $value, $type);
        }
    }

    /**
     * bool → int, DateTimeInterface → `Y-m-d H:i:s`, BackedEnum → value.
     *
     * @param  array<array-key, mixed>  $bindings
     * @return array<array-key, mixed>
     */
    public function prepareBindings(array $bindings): array
    {
        foreach ($bindings as $key => $value) {
            $bindings[$key] = match (true) {
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                $value instanceof BackedEnum => $value->value,
                is_bool($value) => (int) $value,
                default => $value,
            };
        }

        return $bindings;
    }

    // -------------------------------------------------------------------------
    // Log, listeners e pretend
    // -------------------------------------------------------------------------

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    protected function logQuery(string $sql, array $bindings, float $timeMs): void
    {
        if ($this->loggingQueries) {
            $this->queryLog[] = ['sql' => $sql, 'bindings' => $bindings, 'time_ms' => $timeMs];
        }

        foreach ($this->listeners as $listener) {
            $listener($sql, $bindings, $timeMs);
        }
    }

    public function enableQueryLog(): void
    {
        $this->loggingQueries = true;
    }

    public function disableQueryLog(): void
    {
        $this->loggingQueries = false;
    }

    public function logging(): bool
    {
        return $this->loggingQueries;
    }

    /**
     * @return list<array{sql: string, bindings: array<array-key, mixed>, time_ms: float}>
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function flushQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Chamado após cada query: `fn (string $sql, array $bindings, float $timeMs)`.
     *
     * @param  Closure(string, array<array-key, mixed>, float): void  $callback
     */
    public function listen(Closure $callback): void
    {
        $this->listeners[] = $callback;
    }

    /**
     * Executa o callback sem tocar na BD e devolve as queries que teriam corrido.
     *
     * @param  Closure(static): mixed  $callback
     * @return list<array{sql: string, bindings: array<array-key, mixed>, time_ms: float}>
     */
    public function pretend(Closure $callback): array
    {
        $previousPretending = $this->pretending;
        $previousPretended = $this->pretended;

        $this->pretending = true;
        $this->pretended = [];

        try {
            $callback($this);

            return $this->pretended;
        } finally {
            $this->pretending = $previousPretending;
            $this->pretended = $previousPretended;
        }
    }

    public function pretending(): bool
    {
        return $this->pretending;
    }

    // -------------------------------------------------------------------------
    // Transacções
    // -------------------------------------------------------------------------

    /**
     * Executa o callback numa transacção (commit no fim, rollback em qualquer Throwable).
     * Com `$attempts > 1` repete em caso de deadlock/bloqueio.
     *
     * @template T
     *
     * @param  Closure(static): T  $callback
     * @return T
     */
    public function transaction(Closure $callback, int $attempts = 1): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            $this->beginTransaction();

            try {
                $result = $callback($this);
                $this->commit();

                return $result;
            } catch (Throwable $e) {
                $this->rollBack();

                if ($attempt < $attempts && $this->causedByConcurrencyError($e)) {
                    continue;
                }

                throw $e;
            }
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactions === 0) {
            $this->pretending || $this->getPdo()->beginTransaction();
        } else {
            $this->statement('SAVEPOINT trans'.($this->transactions + 1));
        }

        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions === 0) {
            return;
        }

        if ($this->transactions === 1) {
            $this->pretending || $this->getPdo()->commit();
        }

        $this->transactions--;
    }

    public function rollBack(): void
    {
        if ($this->transactions === 0) {
            return;
        }

        if ($this->transactions === 1) {
            $this->pretending || ! $this->getPdo()->inTransaction() || $this->getPdo()->rollBack();
        } else {
            $this->statement('ROLLBACK TO SAVEPOINT trans'.$this->transactions);
        }

        $this->transactions--;
    }

    public function transactionLevel(): int
    {
        return $this->transactions;
    }

    protected function causedByConcurrencyError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        foreach (['deadlock', 'database is locked', 'could not serialize', 'lock wait timeout'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
