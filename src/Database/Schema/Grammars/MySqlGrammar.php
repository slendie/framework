<?php

declare(strict_types=1);

namespace Slendie\Database\Schema\Grammars;

use InvalidArgumentException;
use Slendie\Database\Connection;
use Slendie\Database\Schema\Blueprint;
use Slendie\Database\Schema\ColumnDefinition;
use Slendie\Database\Schema\ColumnType;
use Slendie\Database\Schema\Command;

/**
 * DDL do MySQL / MariaDB. O DDL não é transaccional: cada instrução faz commit implícito.
 */
class MySqlGrammar extends Grammar
{
    public function supportsSchemaTransactions(): bool
    {
        return false;
    }

    protected function quote(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    public function quoteString(string $value): string
    {
        // Sem NO_BACKSLASH_ESCAPES, a barra invertida também escapa.
        return "'".str_replace(['\\', "'"], ['\\\\', "''"], $value)."'";
    }

    // -------------------------------------------------------------------------
    // Tabelas e colunas
    // -------------------------------------------------------------------------

    protected function compileCreateOptions(Blueprint $blueprint, Connection $connection): string
    {
        $config = $connection->getConfig();

        $engine = $blueprint->engine ?? $this->option($config['engine'] ?? null, 'InnoDB');
        $charset = $blueprint->charset ?? $this->option($config['charset'] ?? null, 'utf8mb4');
        $collation = $blueprint->collation ?? $this->option($config['collation'] ?? null, 'utf8mb4_unicode_ci');

        return ' engine='.$this->assertOption($engine)
            .' default charset='.$this->assertOption($charset)
            .' collate='.$this->assertOption($collation);
    }

    public function compileChange(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        $columns = array_map(fn (string $column): string => 'modify '.$column, $this->getColumns($blueprint, true));

        return 'alter table '.$this->wrapTable($blueprint).' '.implode(', ', $columns);
    }

    public function compileComment(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' comment = '.$this->quoteString((string) $command->value);
    }

    private function option(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function assertOption(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
            throw new InvalidArgumentException("Opção de tabela inválida [{$value}] (engine, charset ou collation).");
        }

        return $value;
    }

    // -------------------------------------------------------------------------
    // Índices e chaves
    // -------------------------------------------------------------------------

    public function compileUnique(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' add unique '.$this->wrap((string) $command->index)
            .' ('.$this->columnize($command->columns).')';
    }

    public function compileIndex(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' add index '.$this->wrap((string) $command->index)
            .' ('.$this->columnize($command->columns).')';
    }

    public function compileDropPrimary(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop primary key';
    }

    public function compileDropUnique(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return $this->compileDropIndex($blueprint, $command, $connection);
    }

    public function compileDropIndex(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop index '.$this->wrap((string) $command->index);
    }

    public function compileDropForeign(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop foreign key '.$this->wrap((string) $command->index);
    }

    // -------------------------------------------------------------------------
    // Introspecção e restrições
    // -------------------------------------------------------------------------

    public function compileTableExists(string $table): string
    {
        static::assertIdentifier($table);

        return 'select count(*) from information_schema.tables where table_schema = database() and table_name = '.$this->quoteString($table);
    }

    public function compileGetTables(): string
    {
        return "select table_name as name from information_schema.tables where table_schema = database() and table_type = 'BASE TABLE' order by table_name";
    }

    public function compileGetColumns(string $table): string
    {
        static::assertIdentifier($table);

        return 'select column_name as name, column_type as type, is_nullable as nullable, column_default as `default`'
            .' from information_schema.columns where table_schema = database() and table_name = '.$this->quoteString($table)
            .' order by ordinal_position';
    }

    public function compileEnableForeignKeyConstraints(): string
    {
        return 'set foreign_key_checks = 1';
    }

    public function compileDisableForeignKeyConstraints(): string
    {
        return 'set foreign_key_checks = 0';
    }

    public function compileDropAllTables(array $tables): array
    {
        if ($tables === []) {
            return [];
        }

        return ['drop table '.implode(', ', array_map($this->quote(...), $tables))];
    }

    // -------------------------------------------------------------------------
    // Modifiers
    // -------------------------------------------------------------------------

    protected function modifyUnsigned(Blueprint $blueprint, ColumnDefinition $column): string
    {
        $numeric = $column->type->isIncrements() || in_array($column->type, [
            ColumnType::Integer, ColumnType::BigInteger, ColumnType::SmallInteger, ColumnType::TinyInteger,
            ColumnType::Float, ColumnType::Double, ColumnType::Decimal,
        ], true);

        return $column->unsigned && $numeric ? ' unsigned' : '';
    }

    protected function modifyCharset(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->charset !== null ? ' character set '.$this->assertOption($column->charset) : '';
    }

    protected function modifyCollate(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->collation !== null ? ' collate '.$this->assertOption($column->collation) : '';
    }

    protected function currentTimestamp(ColumnDefinition $column): string
    {
        return $column->precision !== null && $column->precision > 0 ? 'current_timestamp('.$column->precision.')' : 'current_timestamp';
    }

    protected function modifyOnUpdate(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->useCurrentOnUpdate ? ' on update '.$this->currentTimestamp($column) : '';
    }

    protected function modifyIncrement(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->autoIncrement ? ' auto_increment primary key' : '';
    }

    protected function modifyComment(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->comment !== null ? ' comment '.$this->quoteString($column->comment) : '';
    }

    protected function modifyAfter(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->after !== null ? ' after '.$this->wrap($column->after) : '';
    }

    protected function modifyFirst(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return $column->first ? ' first' : '';
    }

    // -------------------------------------------------------------------------
    // Tipos
    // -------------------------------------------------------------------------

    protected function typeIncrements(ColumnDefinition $column): string
    {
        return 'int';
    }

    protected function typeBigIncrements(ColumnDefinition $column): string
    {
        return 'bigint';
    }

    protected function typeSmallIncrements(ColumnDefinition $column): string
    {
        return 'smallint';
    }

    protected function typeUuid(ColumnDefinition $column): string
    {
        return 'char(36)';
    }

    protected function typeMediumText(ColumnDefinition $column): string
    {
        return 'mediumtext';
    }

    protected function typeLongText(ColumnDefinition $column): string
    {
        return 'longtext';
    }

    protected function typeInteger(ColumnDefinition $column): string
    {
        return 'int';
    }

    protected function typeTinyInteger(ColumnDefinition $column): string
    {
        return 'tinyint';
    }

    protected function typeFloat(ColumnDefinition $column): string
    {
        return ($column->precision ?? 53) <= 24 ? 'float' : 'double';
    }

    protected function typeDouble(ColumnDefinition $column): string
    {
        return 'double';
    }

    protected function typeBoolean(ColumnDefinition $column): string
    {
        return 'tinyint(1)';
    }

    protected function typeDateTime(ColumnDefinition $column): string
    {
        return 'datetime'.$this->precision($column);
    }

    protected function typeDateTimeTz(ColumnDefinition $column): string
    {
        return $this->typeDateTime($column);
    }

    protected function typeTimestamp(ColumnDefinition $column): string
    {
        return 'timestamp'.$this->precision($column);
    }

    protected function typeTimestampTz(ColumnDefinition $column): string
    {
        return $this->typeTimestamp($column);
    }

    protected function typeTime(ColumnDefinition $column): string
    {
        return 'time'.$this->precision($column);
    }

    protected function typeYear(ColumnDefinition $column): string
    {
        return 'year';
    }

    protected function typeJson(ColumnDefinition $column): string
    {
        return 'json';
    }

    protected function typeJsonb(ColumnDefinition $column): string
    {
        return 'json';
    }

    protected function typeBinary(ColumnDefinition $column): string
    {
        return 'blob';
    }

    protected function typeEnum(ColumnDefinition $column): string
    {
        if ($column->allowed === []) {
            throw new InvalidArgumentException("A coluna enum [{$column->name}] precisa de pelo menos um valor.");
        }

        return 'enum('.$this->quoteStrings($column->allowed).')';
    }

    protected function typeIpAddress(ColumnDefinition $column): string
    {
        return 'varchar(45)';
    }

    protected function typeMacAddress(ColumnDefinition $column): string
    {
        return 'varchar(17)';
    }

    private function precision(ColumnDefinition $column): string
    {
        return $column->precision !== null && $column->precision > 0 ? '('.$column->precision.')' : '';
    }
}
