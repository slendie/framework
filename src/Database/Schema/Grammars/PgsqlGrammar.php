<?php

declare(strict_types=1);

namespace Slendie\Database\Schema\Grammars;

use Slendie\Database\Connection;
use Slendie\Database\Schema\Blueprint;
use Slendie\Database\Schema\ColumnDefinition;
use Slendie\Database\Schema\Command;

/**
 * DDL do PostgreSQL. `unsigned` é ignorado e os comentários de coluna são instruções separadas.
 */
class PgsqlGrammar extends Grammar
{
    public function supportsSchemaTransactions(): bool
    {
        return true;
    }

    public function compilesColumnCommentsSeparately(): bool
    {
        return true;
    }

    protected function quote(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    protected function booleanLiteral(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    // -------------------------------------------------------------------------
    // Tabelas e colunas
    // -------------------------------------------------------------------------

    /**
     * Redefine a coluna: tipo, default e nulidade (um default não indicado é removido).
     */
    public function compileChange(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        $actions = [];

        foreach ($blueprint->getColumns() as $column) {
            if (! $column->change) {
                continue;
            }

            $name = $this->wrap($column->name);
            $actions[] = 'alter column '.$name.' type '.$this->getType($column);

            if ($column->useCurrent) {
                $actions[] = 'alter column '.$name.' set default '.$this->currentTimestamp($column);
            } elseif ($column->hasDefault) {
                $actions[] = 'alter column '.$name.' set default '.$this->getDefaultValue($column->default);
            } else {
                $actions[] = 'alter column '.$name.' drop default';
            }

            $actions[] = 'alter column '.$name.($column->nullable ? ' drop not null' : ' set not null');
        }

        return 'alter table '.$this->wrapTable($blueprint).' '.implode(', ', $actions);
    }

    public function compileComment(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'comment on table '.$this->wrapTable($blueprint).' is '.$this->quoteString((string) $command->value);
    }

    public function compileColumnComment(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'comment on column '.$this->wrapTable($blueprint).'.'.$this->wrap($command->columns[0] ?? '')
            .' is '.$this->quoteString((string) $command->value);
    }

    // -------------------------------------------------------------------------
    // Índices e chaves
    // -------------------------------------------------------------------------

    public function compileDropPrimary(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop constraint '.$this->wrap($command->index ?? $blueprint->getTable().'_pkey');
    }

    public function compileDropUnique(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop constraint '.$this->wrap((string) $command->index);
    }

    public function compileDropIndex(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'drop index '.$this->wrap((string) $command->index);
    }

    public function compileDropForeign(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop constraint '.$this->wrap((string) $command->index);
    }

    // -------------------------------------------------------------------------
    // Introspecção e restrições
    // -------------------------------------------------------------------------

    public function compileTableExists(string $table): string
    {
        static::assertIdentifier($table);

        return 'select count(*) from information_schema.tables where table_schema = current_schema() and table_name = '.$this->quoteString($table);
    }

    public function compileGetTables(): string
    {
        return "select table_name as name from information_schema.tables where table_schema = current_schema() and table_type = 'BASE TABLE' order by table_name";
    }

    public function compileGetColumns(string $table): string
    {
        static::assertIdentifier($table);

        return 'select column_name as name, data_type as type, is_nullable as nullable, column_default as "default"'
            .' from information_schema.columns where table_schema = current_schema() and table_name = '.$this->quoteString($table)
            .' order by ordinal_position';
    }

    /** Só tem efeito sobre restrições DEFERRABLE e dentro de uma transacção. */
    public function compileEnableForeignKeyConstraints(): string
    {
        return 'set constraints all immediate';
    }

    public function compileDisableForeignKeyConstraints(): string
    {
        return 'set constraints all deferred';
    }

    public function compileDropAllTables(array $tables): array
    {
        if ($tables === []) {
            return [];
        }

        return ['drop table '.implode(', ', array_map($this->quote(...), $tables)).' cascade'];
    }

    // -------------------------------------------------------------------------
    // Modifiers
    // -------------------------------------------------------------------------

    protected function modifyIncrement(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->autoIncrement ? ' primary key' : '';
    }

    // -------------------------------------------------------------------------
    // Tipos
    // -------------------------------------------------------------------------

    protected function typeIncrements(ColumnDefinition $column): string
    {
        return 'serial';
    }

    protected function typeBigIncrements(ColumnDefinition $column): string
    {
        return 'bigserial';
    }

    protected function typeSmallIncrements(ColumnDefinition $column): string
    {
        return 'smallserial';
    }

    protected function typeUuid(ColumnDefinition $column): string
    {
        return 'uuid';
    }

    protected function typeInteger(ColumnDefinition $column): string
    {
        return $column->autoIncrement ? 'serial' : 'integer';
    }

    protected function typeBigInteger(ColumnDefinition $column): string
    {
        return $column->autoIncrement ? 'bigserial' : 'bigint';
    }

    protected function typeSmallInteger(ColumnDefinition $column): string
    {
        return $column->autoIncrement ? 'smallserial' : 'smallint';
    }

    protected function typeTinyInteger(ColumnDefinition $column): string
    {
        return $column->autoIncrement ? 'smallserial' : 'smallint';
    }

    protected function typeFloat(ColumnDefinition $column): string
    {
        return ($column->precision ?? 53) <= 24 ? 'real' : 'double precision';
    }

    protected function typeDouble(ColumnDefinition $column): string
    {
        return 'double precision';
    }

    protected function typeDateTime(ColumnDefinition $column): string
    {
        return 'timestamp('.($column->precision ?? 0).') without time zone';
    }

    protected function typeDateTimeTz(ColumnDefinition $column): string
    {
        return 'timestamp('.($column->precision ?? 0).') with time zone';
    }

    protected function typeTimestamp(ColumnDefinition $column): string
    {
        return $this->typeDateTime($column);
    }

    protected function typeTimestampTz(ColumnDefinition $column): string
    {
        return $this->typeDateTimeTz($column);
    }

    protected function typeTime(ColumnDefinition $column): string
    {
        return 'time('.($column->precision ?? 0).') without time zone';
    }

    protected function typeJson(ColumnDefinition $column): string
    {
        return 'json';
    }

    protected function typeJsonb(ColumnDefinition $column): string
    {
        return 'jsonb';
    }

    protected function typeBinary(ColumnDefinition $column): string
    {
        return 'bytea';
    }

    protected function typeEnum(ColumnDefinition $column): string
    {
        return $this->enumAsCheck($column, 'varchar(255)');
    }

    protected function typeIpAddress(ColumnDefinition $column): string
    {
        return 'inet';
    }

    protected function typeMacAddress(ColumnDefinition $column): string
    {
        return 'macaddr';
    }
}
