<?php

declare(strict_types=1);

namespace Slendie\Database\Schema;

use Closure;
use Slendie\Database\Connection;
use Slendie\Database\Schema\Grammars\Grammar;

/**
 * API de schema de uma ligação: `create`, `table`, `drop`, `hasTable`, `getColumns`...
 */
class Builder
{
    protected Grammar $grammar;

    /** @var (Closure(string, ?Closure): Blueprint)|null */
    protected ?Closure $resolver = null;

    public function __construct(protected Connection $connection)
    {
        $this->grammar = $connection->getSchemaGrammar();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Substitui a criação de Blueprints: `fn (string $table, ?Closure $callback): Blueprint`.
     *
     * @param  Closure(string, ?Closure): Blueprint  $resolver
     */
    public function blueprintResolver(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    // -------------------------------------------------------------------------
    // Tabelas
    // -------------------------------------------------------------------------

    /**
     * @param  Closure(Blueprint): void  $callback
     */
    public function create(string $table, Closure $callback): void
    {
        $blueprint = $this->createBlueprint($table);
        $blueprint->create();
        $callback($blueprint);

        $this->build($blueprint);
    }

    /**
     * Altera uma tabela existente.
     *
     * @param  Closure(Blueprint): void  $callback
     */
    public function table(string $table, Closure $callback): void
    {
        $this->build($this->createBlueprint($table, $callback));
    }

    public function drop(string $table): void
    {
        $blueprint = $this->createBlueprint($table);
        $blueprint->drop();

        $this->build($blueprint);
    }

    public function dropIfExists(string $table): void
    {
        $blueprint = $this->createBlueprint($table);
        $blueprint->dropIfExists();

        $this->build($blueprint);
    }

    public function rename(string $from, string $to): void
    {
        $blueprint = $this->createBlueprint($from);
        $blueprint->rename($to);

        $this->build($blueprint);
    }

    /**
     * Apaga todas as tabelas da base de dados (sem precisar dos `down()`).
     */
    public function dropAllTables(): void
    {
        $this->withoutForeignKeyConstraints(function (): void {
            foreach ($this->grammar->compileDropAllTables($this->getTables()) as $statement) {
                $this->connection->statement($statement);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Introspecção
    // -------------------------------------------------------------------------

    public function hasTable(string $table): bool
    {
        return (int) $this->connection->scalar($this->grammar->compileTableExists($table)) > 0;
    }

    public function hasColumn(string $table, string $column): bool
    {
        return in_array(strtolower($column), array_map(strtolower(...), $this->getColumnListing($table)), true);
    }

    /**
     * @param  list<string>  $columns
     */
    public function hasColumns(string $table, array $columns): bool
    {
        $existing = array_map(strtolower(...), $this->getColumnListing($table));

        foreach ($columns as $column) {
            if (! in_array(strtolower($column), $existing, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function getColumnListing(string $table): array
    {
        return array_column($this->getColumns($table), 'name');
    }

    /**
     * @return list<array{name: string, type: string, nullable: bool, default: ?string}>
     */
    public function getColumns(string $table): array
    {
        return $this->grammar->processColumns($this->connection->select($this->grammar->compileGetColumns($table)));
    }

    /**
     * @return list<string>
     */
    public function getTables(): array
    {
        $tables = [];

        foreach ($this->connection->select($this->grammar->compileGetTables()) as $row) {
            $name = reset($row);

            if (is_scalar($name)) {
                $tables[] = (string) $name;
            }
        }

        return $tables;
    }

    // -------------------------------------------------------------------------
    // Chaves estrangeiras
    // -------------------------------------------------------------------------

    public function enableForeignKeyConstraints(): void
    {
        $this->connection->statement($this->grammar->compileEnableForeignKeyConstraints());
    }

    public function disableForeignKeyConstraints(): void
    {
        $this->connection->statement($this->grammar->compileDisableForeignKeyConstraints());
    }

    /**
     * Corre o callback com as chaves estrangeiras desligadas (e volta a ligá-las no fim).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutForeignKeyConstraints(Closure $callback): mixed
    {
        $this->disableForeignKeyConstraints();

        try {
            return $callback();
        } finally {
            $this->enableForeignKeyConstraints();
        }
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    protected function createBlueprint(string $table, ?Closure $callback = null): Blueprint
    {
        if ($this->resolver !== null) {
            return ($this->resolver)($table, $callback);
        }

        return new Blueprint($table, $callback);
    }

    protected function build(Blueprint $blueprint): void
    {
        $blueprint->build($this->connection);
    }
}
