<?php

declare(strict_types=1);

namespace Slendie\Database\Schema\Grammars;

use BackedEnum;
use InvalidArgumentException;
use LogicException;
use Slendie\Database\Connection;
use Slendie\Database\Query\Expression;
use Slendie\Database\Schema\Blueprint;
use Slendie\Database\Schema\ColumnDefinition;
use Slendie\Database\Schema\Command;
use Slendie\Database\Schema\ForeignKeyDefinition;

/**
 * Transforma um Blueprint em DDL. Cada driver sobrepõe tipos, modifiers e as operações em que diverge.
 *
 * Tabelas, colunas e índices passam por wrap(), que só aceita `[A-Za-z_][A-Za-z0-9_]*`; textos livres
 * (defaults, comentários, valores de enum) são sempre citados como literais.
 */
abstract class Grammar
{
    /** @var list<string> */
    protected const MODIFIERS = ['Unsigned', 'Charset', 'Collate', 'Nullable', 'Default', 'OnUpdate', 'Increment', 'Comment', 'After', 'First'];

    // -------------------------------------------------------------------------
    // Capacidades
    // -------------------------------------------------------------------------

    /** O DDL pode ser desfeito por rollback (MySQL faz commit implícito). */
    abstract public function supportsSchemaTransactions(): bool;

    /** Os comentários de coluna são instruções separadas (PostgreSQL). */
    public function compilesColumnCommentsSeparately(): bool
    {
        return false;
    }

    // -------------------------------------------------------------------------
    // Identificadores e literais
    // -------------------------------------------------------------------------

    /**
     * @throws InvalidArgumentException
     */
    public static function assertIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("Identificador SQL inválido [{$identifier}]. Usa só letras, números e _.");
        }

        return $identifier;
    }

    public function wrap(string $name): string
    {
        return $this->quote(static::assertIdentifier($name));
    }

    public function wrapTable(string|Blueprint $table): string
    {
        return $this->wrap($table instanceof Blueprint ? $table->getTable() : $table);
    }

    /**
     * @param  list<string>  $columns
     */
    public function columnize(array $columns): string
    {
        return implode(', ', array_map($this->wrap(...), $columns));
    }

    protected function quote(string $name): string
    {
        return '"'.$name.'"';
    }

    public function quoteString(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    /**
     * @param  list<string>  $values
     */
    protected function quoteStrings(array $values): string
    {
        return implode(', ', array_map($this->quoteString(...), $values));
    }

    public function getDefaultValue(mixed $value): string
    {
        return match (true) {
            $value instanceof Expression => (string) $value->getValue(),
            $value instanceof BackedEnum => $this->getDefaultValue($value->value),
            $value === null => 'null',
            is_bool($value) => $this->booleanLiteral($value),
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => $this->quoteString($value),
            default => throw new InvalidArgumentException('O default de uma coluna tem de ser escalar, enum ou Expression.'),
        };
    }

    protected function booleanLiteral(bool $value): string
    {
        return $value ? '1' : '0';
    }

    // -------------------------------------------------------------------------
    // Tabelas
    // -------------------------------------------------------------------------

    public function compileCreate(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return $this->createVerb($blueprint).' '.$this->wrapTable($blueprint)
            .' ('.implode(', ', $this->getColumns($blueprint)).')'
            .$this->compileCreateOptions($blueprint, $connection);
    }

    protected function createVerb(Blueprint $blueprint): string
    {
        return $blueprint->temporary ? 'create temporary table' : 'create table';
    }

    protected function compileCreateOptions(Blueprint $blueprint, Connection $connection): string
    {
        return '';
    }

    public function compileDrop(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    public function compileDropIfExists(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'drop table if exists '.$this->wrapTable($blueprint);
    }

    public function compileRename(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' rename to '.$this->wrapTable((string) $command->to);
    }

    /**
     * Comentário da tabela; por omissão ignorado.
     *
     * @return string|list<string>|null
     */
    public function compileComment(Blueprint $blueprint, Command $command, Connection $connection): string|array|null
    {
        return null;
    }

    // -------------------------------------------------------------------------
    // Colunas
    // -------------------------------------------------------------------------

    /**
     * @return string|list<string>
     */
    public function compileAdd(Blueprint $blueprint, Command $command, Connection $connection): string|array
    {
        $columns = array_map(fn (string $column): string => 'add column '.$column, $this->getColumns($blueprint));

        return 'alter table '.$this->wrapTable($blueprint).' '.implode(', ', $columns);
    }

    /**
     * @return string|list<string>
     */
    abstract public function compileChange(Blueprint $blueprint, Command $command, Connection $connection): string|array;

    /**
     * @return string|list<string>
     */
    public function compileDropColumn(Blueprint $blueprint, Command $command, Connection $connection): string|array
    {
        $columns = array_map(fn (string $column): string => 'drop column '.$this->wrap($column), $command->columns);

        return 'alter table '.$this->wrapTable($blueprint).' '.implode(', ', $columns);
    }

    public function compileRenameColumn(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' rename column '.$this->wrap((string) $command->from).' to '.$this->wrap((string) $command->to);
    }

    public function compileColumnComment(Blueprint $blueprint, Command $command, Connection $connection): ?string
    {
        return null;
    }

    /**
     * Definições `"coluna" tipo modifiers` das colunas a criar (ou, com $change, a alterar).
     *
     * @return list<string>
     */
    public function getColumns(Blueprint $blueprint, bool $change = false): array
    {
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            if ($column->change === $change) {
                $columns[] = $this->addModifiers($this->wrap($column->name).' '.$this->getType($column), $blueprint, $column);
            }
        }

        return $columns;
    }

    public function getType(ColumnDefinition $column): string
    {
        $method = 'type'.ucfirst($column->type->value);

        /** @var string */
        return $this->$method($column);
    }

    public function addModifiers(string $sql, Blueprint $blueprint, ColumnDefinition $column): string
    {
        foreach (self::MODIFIERS as $modifier) {
            $method = 'modify'.$modifier;
            $sql .= $this->$method($blueprint, $column);
        }

        return $sql;
    }

    protected function modifyUnsigned(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return '';
    }

    protected function modifyCharset(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return '';
    }

    protected function modifyCollate(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return '';
    }

    protected function modifyNullable(Blueprint $blueprint, ColumnDefinition $column): string
    {
        if ($column->autoIncrement) {
            return '';
        }

        return $column->nullable ? ' null' : ' not null';
    }

    protected function modifyDefault(Blueprint $blueprint, ColumnDefinition $column): string
    {
        if ($column->useCurrent) {
            return ' default '.$this->currentTimestamp($column);
        }

        return $column->hasDefault ? ' default '.$this->getDefaultValue($column->default) : '';
    }

    protected function currentTimestamp(ColumnDefinition $column): string
    {
        return 'current_timestamp';
    }

    protected function modifyOnUpdate(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return '';
    }

    protected function modifyIncrement(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return '';
    }

    protected function modifyComment(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return '';
    }

    protected function modifyAfter(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return '';
    }

    protected function modifyFirst(Blueprint $blueprint, ColumnDefinition $column): string
    {
        return '';
    }

    // -------------------------------------------------------------------------
    // Tipos
    // -------------------------------------------------------------------------

    abstract protected function typeIncrements(ColumnDefinition $column): string;

    abstract protected function typeBigIncrements(ColumnDefinition $column): string;

    abstract protected function typeSmallIncrements(ColumnDefinition $column): string;

    abstract protected function typeUuid(ColumnDefinition $column): string;

    protected function typeString(ColumnDefinition $column): string
    {
        return 'varchar('.($column->length ?? 255).')';
    }

    protected function typeChar(ColumnDefinition $column): string
    {
        return 'char('.($column->length ?? 255).')';
    }

    protected function typeText(ColumnDefinition $column): string
    {
        return 'text';
    }

    protected function typeMediumText(ColumnDefinition $column): string
    {
        return 'text';
    }

    protected function typeLongText(ColumnDefinition $column): string
    {
        return 'text';
    }

    protected function typeInteger(ColumnDefinition $column): string
    {
        return 'integer';
    }

    protected function typeBigInteger(ColumnDefinition $column): string
    {
        return 'bigint';
    }

    protected function typeSmallInteger(ColumnDefinition $column): string
    {
        return 'smallint';
    }

    protected function typeTinyInteger(ColumnDefinition $column): string
    {
        return 'smallint';
    }

    abstract protected function typeFloat(ColumnDefinition $column): string;

    abstract protected function typeDouble(ColumnDefinition $column): string;

    protected function typeDecimal(ColumnDefinition $column): string
    {
        return 'decimal('.($column->total ?? 8).', '.($column->places ?? 2).')';
    }

    protected function typeBoolean(ColumnDefinition $column): string
    {
        return 'boolean';
    }

    protected function typeDate(ColumnDefinition $column): string
    {
        return 'date';
    }

    abstract protected function typeDateTime(ColumnDefinition $column): string;

    abstract protected function typeDateTimeTz(ColumnDefinition $column): string;

    abstract protected function typeTimestamp(ColumnDefinition $column): string;

    abstract protected function typeTimestampTz(ColumnDefinition $column): string;

    abstract protected function typeTime(ColumnDefinition $column): string;

    protected function typeYear(ColumnDefinition $column): string
    {
        return 'integer';
    }

    abstract protected function typeJson(ColumnDefinition $column): string;

    abstract protected function typeJsonb(ColumnDefinition $column): string;

    abstract protected function typeBinary(ColumnDefinition $column): string;

    abstract protected function typeEnum(ColumnDefinition $column): string;

    abstract protected function typeIpAddress(ColumnDefinition $column): string;

    abstract protected function typeMacAddress(ColumnDefinition $column): string;

    /** `varchar(255) check ("col" in ('a', 'b'))` para drivers sem ENUM. */
    protected function enumAsCheck(ColumnDefinition $column, string $type): string
    {
        if ($column->allowed === []) {
            throw new InvalidArgumentException("A coluna enum [{$column->name}] precisa de pelo menos um valor.");
        }

        return $type.' check ('.$this->wrap($column->name).' in ('.$this->quoteStrings($column->allowed).'))';
    }

    // -------------------------------------------------------------------------
    // Índices e chaves
    // -------------------------------------------------------------------------

    public function compilePrimary(Blueprint $blueprint, Command $command, Connection $connection): ?string
    {
        return 'alter table '.$this->wrapTable($blueprint).' add primary key ('.$this->columnize($command->columns).')';
    }

    public function compileUnique(Blueprint $blueprint, Command $command, Connection $connection): ?string
    {
        return 'alter table '.$this->wrapTable($blueprint).' add constraint '.$this->wrap((string) $command->index)
            .' unique ('.$this->columnize($command->columns).')';
    }

    public function compileIndex(Blueprint $blueprint, Command $command, Connection $connection): string
    {
        return 'create index '.$this->wrap((string) $command->index).' on '.$this->wrapTable($blueprint)
            .' ('.$this->columnize($command->columns).')';
    }

    public function compileForeign(Blueprint $blueprint, Command $command, Connection $connection): ?string
    {
        return 'alter table '.$this->wrapTable($blueprint).' add constraint '.$this->wrap((string) $command->index)
            .' '.$this->foreignKeySql($command);
    }

    abstract public function compileDropPrimary(Blueprint $blueprint, Command $command, Connection $connection): string;

    abstract public function compileDropUnique(Blueprint $blueprint, Command $command, Connection $connection): string;

    abstract public function compileDropIndex(Blueprint $blueprint, Command $command, Connection $connection): string;

    abstract public function compileDropForeign(Blueprint $blueprint, Command $command, Connection $connection): string;

    /** `foreign key (a) references t (b) on delete x on update y` */
    protected function foreignKeySql(Command $command): string
    {
        if (! $command instanceof ForeignKeyDefinition || $command->on === null || $command->references === []) {
            throw new LogicException('A chave estrangeira ['.($command->index ?? '').'] precisa de references() e on().');
        }

        $sql = 'foreign key ('.$this->columnize($command->columns).') references '
            .$this->wrapTable($command->on).' ('.$this->columnize($command->references).')';

        if ($command->onDelete !== null) {
            $sql .= ' on delete '.$command->onDelete;
        }

        if ($command->onUpdate !== null) {
            $sql .= ' on update '.$command->onUpdate;
        }

        return $sql;
    }

    // -------------------------------------------------------------------------
    // Introspecção e restrições (usadas pelo Schema\Builder)
    // -------------------------------------------------------------------------

    abstract public function compileTableExists(string $table): string;

    abstract public function compileGetTables(): string;

    /** SQL que devolve linhas com as chaves name, type, nullable, default. */
    abstract public function compileGetColumns(string $table): string;

    /**
     * Normaliza as linhas de compileGetColumns().
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{name: string, type: string, nullable: bool, default: ?string}>
     */
    public function processColumns(array $rows): array
    {
        $columns = [];

        foreach ($rows as $row) {
            $nullable = $row['nullable'] ?? false;
            $type = $row['type'] ?? '';
            $name = $row['name'] ?? '';
            $default = $row['default'] ?? null;

            $columns[] = [
                'name' => is_scalar($name) ? (string) $name : '',
                'type' => is_scalar($type) ? strtolower((string) $type) : '',
                'nullable' => $nullable === true || $nullable === 1 || $nullable === '1' || (is_string($nullable) && strtolower($nullable) === 'yes'),
                'default' => is_scalar($default) ? (string) $default : null,
            ];
        }

        return $columns;
    }

    abstract public function compileEnableForeignKeyConstraints(): string;

    abstract public function compileDisableForeignKeyConstraints(): string;

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    abstract public function compileDropAllTables(array $tables): array;
}
