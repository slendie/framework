<?php

declare(strict_types=1);

namespace Slendie\Database\Migrations;

use InvalidArgumentException;
use RuntimeException;
use Slendie\Support\Str;

/**
 * Gera ficheiros de migração a partir dos stubs (`migration.create|update|blank.stub`).
 */
class MigrationCreator
{
    public function __construct(protected ?string $stubPath = null)
    {
    }

    /**
     * Cria `{Y_m_d_His}_{nome_em_snake}.php` em $path e devolve o caminho completo.
     *
     * Sem `$table`, tenta inferi-la do nome: `create_leads_table` → `leads` (create),
     * `add_phone_to_leads_table` → `leads` (update).
     *
     * @throws InvalidArgumentException nome ou tabela inválidos, ou migração duplicada
     */
    public function create(string $name, string $path, ?string $table = null, bool $create = false): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Nome de migração inválido [{$name}]. Usa só letras, números e _.");
        }

        $name = Str::snake($name);

        $this->ensureUnique($name, $path);

        if ($table === null) {
            [$table, $create] = $this->guessTable($name);
        }

        if ($table !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
            throw new InvalidArgumentException("Nome de tabela inválido [{$table}].");
        }

        $stub = str_replace('{{ table }}', (string) $table, $this->getStub($table, $create));

        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new RuntimeException("Não foi possível criar a pasta [{$path}].");
        }

        $file = rtrim($path, '/\\').DIRECTORY_SEPARATOR.$this->datePrefix().'_'.$name.'.php';

        if (file_put_contents($file, $stub) === false) {
            throw new RuntimeException("Não foi possível escrever a migração [{$file}].");
        }

        return $file;
    }

    /**
     * @return array{0: ?string, 1: bool} tabela e se é para criar
     */
    public function guessTable(string $name): array
    {
        if (preg_match('/^create_(.+)_table$/', $name, $m) === 1) {
            return [$m[1], true];
        }

        if (preg_match('/_(?:to|from|in)_(.+)_table$/', $name, $m) === 1
            || preg_match('/^(?:update|alter|modify)_(.+)_table$/', $name, $m) === 1) {
            return [$m[1], false];
        }

        return [null, false];
    }

    protected function getStub(?string $table, bool $create): string
    {
        $stub = $table === null ? 'blank' : ($create ? 'create' : 'update');
        $file = rtrim($this->stubPath ?? dirname(__DIR__, 3).'/stubs', '/\\')."/migration.{$stub}.stub";

        $contents = is_file($file) ? file_get_contents($file) : false;

        if ($contents === false) {
            throw new RuntimeException("Stub de migração não encontrado: [{$file}].");
        }

        return $contents;
    }

    protected function ensureUnique(string $name, string $path): void
    {
        foreach (glob(rtrim($path, '/\\').'/*_'.$name.'.php') ?: [] as $existing) {
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_'.preg_quote($name, '/').'\.php$/', basename($existing)) === 1) {
                throw new InvalidArgumentException("Já existe uma migração com o nome [{$name}]: ".basename($existing).'.');
            }
        }
    }

    protected function datePrefix(): string
    {
        return date('Y_m_d_His');
    }
}
