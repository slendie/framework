<?php

declare(strict_types=1);

namespace Slendie\Database\Schema\Grammars;

use LogicException;
use Slendie\Database\Connection;
use Slendie\Database\Schema\Blueprint;
use Slendie\Database\Schema\ColumnDefinition;
use Slendie\Database\Schema\Command;

/**
 * DDL do SQLite. O ALTER TABLE do SQLite é limitado: ADD/DROP/RENAME COLUMN funcionam (≥ 3.35),
 * mas não é possível alterar colunas nem adicionar/remover chaves estrangeiras e primárias.
 */
class SqliteGrammar extends Grammar
{
    public function supportsSchemaTransactions(): bool
    {
        return true;
    }

    protected function quote(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    // -------------------------------------------------------------------------
    // Tabelas e colunas
    // -------------------------------------------------------------------------

    public function compileCreate(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        $definitions = $this->getColumns($blueprint);

        // O SQLite só aceita chaves primárias e estrangeiras na criação da tabela.
        foreach ($blueprint->getCommands() as $other) {
            if ($other->name === 'primary') {
                $definitions[] = 'primary key ('.$this->columnize($other->columns).')';
            } elseif ($other->name === 'foreign') {
                $definitions[] = $this->foreignKeySql($other);
            }
        }

        return $this->createVerb($blueprint).' '.$this->wrapTable($blueprint).' ('.implode(', ', $definitions).')';
    }

    /**
     * @return list<string>
     */
    public function compileAdd(Blueprint $blueprint, Command $command, Connection $connection): array
    {
        $table = $this->wrapTable($blueprint);

        return array_map(
            fn (string $column): string => 'alter table '.$table.' add column '.$column,
            $this->getColumns($blueprint),
        );
    }

    public function compileChange(Blueprint $blueprint, Command $command, Connection $connection): never
    {
        throw new LogicException('O SQLite não suporta alterar colunas; recrie a tabela (tabela ['.$blueprint->getTable().']).');
    }

    /**
     * @return list<string>
     */
    public function compileDropColumn(Blueprint $blueprint, Command $command, Connection $connection): array
    {
        $table = $this->wrapTable($blueprint);

        return array_map(
            fn (string $column): string => 'alter table '.$table.' drop column '.$this->wrap($column),
            $command->columns,
        );
    }

    // -------------------------------------------------------------------------
    // Índices e chaves
    // -------------------------------------------------------------------------

    public function compilePrimary(Blueprint $blueprint, Command $command, Connection $connection): ?string
    {
        if ($blueprint->creating()) {
            return null; // já incluída em compileCreate()
        }

        throw new LogicException('O SQLite não suporta adicionar uma chave primária a uma tabela existente; recrie a tabela.');
    }

    public function compileUnique(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'create unique index '.$this->wrap((string) $command->index).' on '.$this->wrapTable($blueprint)
            .' ('.$this->columnize($command->columns).')';
    }

    public function compileForeign(Blueprint $blueprint, Command $command, Connection $connection): ?string
    {
        if ($blueprint->creating()) {
            return null; // já incluída em compileCreate()
        }

        throw new LogicException('O SQLite não suporta adicionar uma chave estrangeira a uma tabela existente; recrie a tabela.');
    }

    public function compileDropPrimary(Blueprint $blueprint, Command $command, Connection $connection): never
    {
        throw new LogicException('O SQLite não suporta remover a chave primária; recrie a tabela.');
    }

    public function compileDropUnique(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'drop index '.$this->wrap((string) $command->index);
    }

    public function compileDropIndex(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'drop index '.$this->wrap((string) $command->index);
    }

    public function compileDropForeign(Blueprint $blueprint, Command $command, Connection $connection): never
    {
        throw new LogicException('O SQLite não suporta remover chaves estrangeiras; recrie a tabela.');
    }

    // -------------------------------------------------------------------------
    // Introspecção e restrições
    // -------------------------------------------------------------------------

    public function compileTableExists(string $table): string
    {
        static::assertIdentifier($table);

        return 'select count(*) from sqlite_master where type = '.$this->quoteString('table').' and name = '.$this->quoteString($table);
    }

    public function compileGetTables(): string
    {
        return "select name from sqlite_master where type = 'table' and name not like 'sqlite\\_%' escape '\\' order by name";
    }

    public function compileGetColumns(string $table): string
    {
        return 'pragma table_info('.$this->wrap($table).')';
    }

    public function processColumns(array $rows): array
    {
        return parent::processColumns(array_map(fn (array $row): array => [
            'name' => $row['name'] ?? '',
            'type' => $row['type'] ?? '',
            'nullable' => empty($row['notnull']),
            'default' => $row['dflt_value'] ?? null,
        ], $rows));
    }

    public function compileEnableForeignKeyConstraints(): string
    {
        return 'pragma foreign_keys = on';
    }

    public function compileDisableForeignKeyConstraints(): string
    {
        return 'pragma foreign_keys = off';
    }

    public function compileDropAllTables(array $tables): array
    {
        return array_map(fn (string $table): string => 'drop table '.$this->quote($table), $tables);
    }

    // -------------------------------------------------------------------------
    // Modifiers
    // -------------------------------------------------------------------------

    protected function modifyIncrement(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->autoIncrement ? ' primary key autoincrement' : '';
    }

    // -------------------------------------------------------------------------
    // Tipos
    // -------------------------------------------------------------------------

    protected function typeIncrements(ColumnDefinition $column): string
    {
        return 'integer';
    }

    protected function typeBigIncrements(ColumnDefinition $column): string
    {
        return 'integer';
    }

    protected function typeSmallIncrements(ColumnDefinition $column): string
    {
        return 'integer';
    }

    protected function typeUuid(ColumnDefinition $column): string
    {
        return 'text';
    }

    protected function typeChar(ColumnDefinition $column): string
    {
        return 'varchar('.($column->length ?? 255).')';
    }

    // Todos os inteiros são "integer": só assim o SQLite aceita AUTOINCREMENT.
    protected function typeBigInteger(ColumnDefinition $column): string
    {
        return 'integer';
    }

    protected function typeSmallInteger(ColumnDefinition $column): string
    {
        return 'integer';
    }

    protected function typeTinyInteger(ColumnDefinition $column): string
    {
        return 'integer';
    }

    protected function typeFloat(ColumnDefinition $column): string
    {
        return 'real';
    }

    protected function typeDouble(ColumnDefinition $column): string
    {
        return 'real';
    }

    protected function typeDecimal(ColumnDefinition $column): string
    {
        return 'numeric';
    }

    protected function typeBoolean(ColumnDefinition $column): string
    {
        return 'tinyint(1)';
    }

    protected function typeDateTime(ColumnDefinition $column): string
    {
        return 'datetime';
    }

    protected function typeDateTimeTz(ColumnDefinition $column): string
    {
        return 'datetime';
    }

    protected function typeTimestamp(ColumnDefinition $column): string
    {
        return 'datetime';
    }

    protected function typeTimestampTz(ColumnDefinition $column): string
    {
        return 'datetime';
    }

    protected function typeTime(ColumnDefinition $column): string
    {
        return 'time';
    }

    protected function typeJson(ColumnDefinition $column): string
    {
        return 'text';
    }

    protected function typeJsonb(ColumnDefinition $column): string
    {
        return 'text';
    }

    protected function typeBinary(ColumnDefinition $column): string
    {
        return 'blob';
    }

    protected function typeEnum(ColumnDefinition $column): string
    {
        return $this->enumAsCheck($column, 'text');
    }

    protected function typeIpAddress(ColumnDefinition $column): string
    {
        return 'varchar(45)';
    }

    protected function typeMacAddress(ColumnDefinition $column): string
    {
        return 'varchar(17)';
    }
}
