<?php

declare(strict_types=1);

namespace Slendie\Database\Schema;

use Closure;
use Slendie\Database\Connection;
use Slendie\Database\Schema\Grammars\Grammar;

/**
 * Descreve as alterações a uma tabela (colunas + comandos) e compila-as para SQL com a grammar do driver.
 */
class Blueprint
{
    /** @var list<ColumnDefinition> */
    protected array $columns = [];

    /** @var list<Command> */
    protected array $commands = [];

    public bool $temporary = false;

    public ?string $engine = null;

    public ?string $charset = null;

    public ?string $collation = null;

    protected bool $impliedCommandsAdded = false;

    public function __construct(protected string $table, ?Closure $callback = null)
    {
        if ($callback !== null) {
            $callback($this);
        }
    }

    // -------------------------------------------------------------------------
    // Compilação
    // -------------------------------------------------------------------------

    /**
     * Executa as instruções geradas.
     */
    public function build(Connection $connection): void
    {
        foreach ($this->toSql($connection) as $statement) {
            $connection->statement($statement);
        }
    }

    /**
     * @return list<string>
     */
    public function toSql(Connection $connection): array
    {
        $grammar = $connection->getSchemaGrammar();

        $this->addImpliedCommands($grammar);

        $statements = [];

        foreach ($this->commands as $command) {
            $method = 'compile'.ucfirst($command->name);

            if (! method_exists($grammar, $method)) {
                continue;
            }

            /** @var string|list<string>|null $sql */
            $sql = $grammar->$method($this, $command, $connection);

            foreach ((array) $sql as $statement) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    protected function addImpliedCommands(Grammar $grammar): void
    {
        if ($this->impliedCommandsAdded) {
            return;
        }

        $this->impliedCommandsAdded = true;

        $prepend = [];

        if (! $this->creating()) {
            $added = array_filter($this->columns, fn (ColumnDefinition $column): bool => ! $column->change);
            $changed = array_filter($this->columns, fn (ColumnDefinition $column): bool => $column->change);

            if ($added !== []) {
                $prepend[] = new Command('add');
            }

            if ($changed !== []) {
                $prepend[] = new Command('change');
            }
        }

        $this->commands = [...$prepend, ...$this->commands];

        foreach ($this->columns as $column) {
            if ($column->primary && ! $column->autoIncrement) {
                $this->primary($column->name);
            }

            if ($column->unique !== false) {
                $this->unique($column->name, is_string($column->unique) ? $column->unique : null);
            }

            if ($column->index !== false) {
                $this->index($column->name, is_string($column->index) ? $column->index : null);
            }

            if ($column->comment !== null && $grammar->compilesColumnCommentsSeparately()) {
                $this->commands[] = new Command('columnComment', [$column->name], value: $column->comment);
            }
        }
    }

    public function creating(): bool
    {
        foreach ($this->commands as $command) {
            if ($command->name === 'create') {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Operações sobre a tabela
    // -------------------------------------------------------------------------

    public function create(): Command
    {
        return $this->addCommand(new Command('create'));
    }

    public function drop(): Command
    {
        return $this->addCommand(new Command('drop'));
    }

    public function dropIfExists(): Command
    {
        return $this->addCommand(new Command('dropIfExists'));
    }

    public function rename(string $to): Command
    {
        return $this->addCommand(new Command('rename', to: $to));
    }

    /** Comentário da tabela (MySQL e PostgreSQL; ignorado em SQLite). */
    public function comment(string $comment): Command
    {
        return $this->addCommand(new Command('comment', value: $comment));
    }

    public function temporary(): static
    {
        $this->temporary = true;

        return $this;
    }

    /** Só MySQL. */
    public function engine(string $engine): static
    {
        $this->engine = $engine;

        return $this;
    }

    /** Só MySQL. */
    public function charset(string $charset): static
    {
        $this->charset = $charset;

        return $this;
    }

    /** Só MySQL. */
    public function collation(string $collation): static
    {
        $this->collation = $collation;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Colunas
    // -------------------------------------------------------------------------

    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column);
    }

    public function increments(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Increments, $column)->unsigned()->autoIncrement();
    }

    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::BigIncrements, $column)->unsigned()->autoIncrement();
    }

    public function smallIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::SmallIncrements, $column)->unsigned()->autoIncrement();
    }

    public function uuid(string $column = 'uuid'): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Uuid, $column);
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        $definition = $this->addColumn(ColumnType::String, $column);
        $definition->length = $length;

        return $definition;
    }

    public function char(string $column, int $length = 255): ColumnDefinition
    {
        $definition = $this->addColumn(ColumnType::Char, $column);
        $definition->length = $length;

        return $definition;
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Text, $column);
    }

    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::MediumText, $column);
    }

    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::LongText, $column);
    }

    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Integer, $column)->autoIncrement($autoIncrement)->unsigned($unsigned);
    }

    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn(ColumnType::BigInteger, $column)->autoIncrement($autoIncrement)->unsigned($unsigned);
    }

    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn(ColumnType::SmallInteger, $column)->autoIncrement($autoIncrement)->unsigned($unsigned);
    }

    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn(ColumnType::TinyInteger, $column)->autoIncrement($autoIncrement)->unsigned($unsigned);
    }

    public function unsignedInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->integer($column, $autoIncrement, true);
    }

    public function unsignedBigInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    public function unsignedSmallInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    public function unsignedTinyInteger(string $column, bool $autoIncrement = false): ColumnDefinition
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    public function float(string $column, int $precision = 53): ColumnDefinition
    {
        $definition = $this->addColumn(ColumnType::Float, $column);
        $definition->precision = $precision;

        return $definition;
    }

    public function double(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Double, $column);
    }

    public function decimal(string $column, int $total = 8, int $places = 2): ColumnDefinition
    {
        $definition = $this->addColumn(ColumnType::Decimal, $column);
        $definition->total = $total;
        $definition->places = $places;

        return $definition;
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Boolean, $column);
    }

    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Date, $column);
    }

    public function dateTime(string $column, int $precision = 0): ColumnDefinition
    {
        $definition = $this->addColumn(ColumnType::DateTime, $column);
        $definition->precision = $precision;

        return $definition;
    }

    public function dateTimeTz(string $column, int $precision = 0): ColumnDefinition
    {
        $definition = $this->addColumn(ColumnType::DateTimeTz, $column);
        $definition->precision = $precision;

        return $definition;
    }

    public function timestamp(string $column, int $precision = 0): ColumnDefinition
    {
        $definition = $this->addColumn(ColumnType::Timestamp, $column);
        $definition->precision = $precision;

        return $definition;
    }

    public function timestampTz(string $column, int $precision = 0): ColumnDefinition
    {
        $definition = $this->addColumn(ColumnType::TimestampTz, $column);
        $definition->precision = $precision;

        return $definition;
    }

    public function time(string $column, int $precision = 0): ColumnDefinition
    {
        $definition = $this->addColumn(ColumnType::Time, $column);
        $definition->precision = $precision;

        return $definition;
    }

    public function year(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Year, $column);
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Json, $column);
    }

    /** `jsonb` em PostgreSQL; `json` (ou texto) nos outros drivers. */
    public function jsonb(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Jsonb, $column);
    }

    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn(ColumnType::Binary, $column);
    }

    /**
     * `ENUM(...)` em MySQL; texto com `CHECK (col IN (...))` nos outros drivers.
     *
     * @param  list<string>  $allowed
     */
    public function enum(string $column, array $allowed): ColumnDefinition
    {
        $definition = $this->addColumn(ColumnType::Enum, $column);
        $definition->allowed = array_values(array_map(strval(...), $allowed));

        return $definition;
    }

    public function ipAddress(string $column = 'ip_address'): ColumnDefinition
    {
        return $this->addColumn(ColumnType::IpAddress, $column);
    }

    public function macAddress(string $column = 'mac_address'): ColumnDefinition
    {
        return $this->addColumn(ColumnType::MacAddress, $column);
    }

    /** `unsignedBigInteger` pronto a ser usado em `->constrained()`. */
    public function foreignId(string $column): ColumnDefinition
    {
        return $this->unsignedBigInteger($column);
    }

    public function foreignUuid(string $column): ColumnDefinition
    {
        return $this->uuid($column);
    }

    /** `created_at` e `updated_at` (nullable). */
    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    public function timestampsTz(int $precision = 0): void
    {
        $this->timestampTz('created_at', $precision)->nullable();
        $this->timestampTz('updated_at', $precision)->nullable();
    }

    public function softDeletes(string $column = 'deleted_at', int $precision = 0): ColumnDefinition
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    public function rememberToken(): ColumnDefinition
    {
        return $this->string('remember_token', 100)->nullable();
    }

    // -------------------------------------------------------------------------
    // Índices e chaves
    // -------------------------------------------------------------------------

    /**
     * @param  string|list<string>  $columns
     */
    public function primary(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->indexCommand('primary', $columns, $name);
    }

    /**
     * @param  string|list<string>  $columns
     */
    public function unique(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->indexCommand('unique', $columns, $name);
    }

    /**
     * @param  string|list<string>  $columns
     */
    public function index(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->indexCommand('index', $columns, $name);
    }

    /**
     * @param  string|list<string>  $columns
     */
    public function foreign(string|array $columns, ?string $name = null): ForeignKeyDefinition
    {
        $columns = array_values((array) $columns);
        $command = new ForeignKeyDefinition('foreign', $columns, $name ?? $this->createIndexName('foreign', $columns));
        $this->commands[] = $command;

        return $command;
    }

    /**
     * @param  string|list<string>  $columns
     */
    public function dropColumn(string|array $columns): Command
    {
        return $this->addCommand(new Command('dropColumn', array_values((array) $columns)));
    }

    public function renameColumn(string $from, string $to): Command
    {
        return $this->addCommand(new Command('renameColumn', from: $from, to: $to));
    }

    /**
     * @param  string|list<string>|null  $index  nome do índice ou colunas (deduz o nome)
     */
    public function dropPrimary(string|array|null $index = null): Command
    {
        return $this->dropIndexCommand('dropPrimary', 'primary', $index);
    }

    /**
     * @param  string|list<string>  $index  nome do índice ou colunas (deduz o nome)
     */
    public function dropUnique(string|array $index): Command
    {
        return $this->dropIndexCommand('dropUnique', 'unique', $index);
    }

    /**
     * @param  string|list<string>  $index  nome do índice ou colunas (deduz o nome)
     */
    public function dropIndex(string|array $index): Command
    {
        return $this->dropIndexCommand('dropIndex', 'index', $index);
    }

    /**
     * @param  string|list<string>  $index  nome da chave ou colunas (deduz o nome)
     */
    public function dropForeign(string|array $index): Command
    {
        return $this->dropIndexCommand('dropForeign', 'foreign', $index);
    }

    public function dropTimestamps(): Command
    {
        return $this->dropColumn(['created_at', 'updated_at']);
    }

    public function dropSoftDeletes(string $column = 'deleted_at'): Command
    {
        return $this->dropColumn($column);
    }

    // -------------------------------------------------------------------------
    // Acessores
    // -------------------------------------------------------------------------

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return list<ColumnDefinition>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<Command>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @param  list<string>  $columns
     */
    public function createIndexName(string $type, array $columns): string
    {
        $name = strtolower($this->table.'_'.implode('_', $columns).'_'.$type);
        $name = str_replace(['-', '.'], '_', $name);

        // Limite de 64 (MySQL) / 63 (PostgreSQL) caracteres: encurta com um hash estável.
        return strlen($name) > 60 ? substr($name, 0, 51).'_'.substr(md5($name), 0, 8) : $name;
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    protected function addColumn(ColumnType $type, string $name): ColumnDefinition
    {
        return $this->columns[] = new ColumnDefinition($name, $type, $this);
    }

    /**
     * @template T of Command
     *
     * @param  T  $command
     * @return T
     */
    protected function addCommand(Command $command): Command
    {
        $this->commands[] = $command;

        return $command;
    }

    /**
     * @param  string|list<string>  $columns
     */
    protected function indexCommand(string $type, string|array $columns, ?string $name): IndexDefinition
    {
        $columns = array_values((array) $columns);
        $command = new IndexDefinition($type, $columns, $name ?? $this->createIndexName($type, $columns));
        $this->commands[] = $command;

        return $command;
    }

    /**
     * @param  string|list<string>|null  $index
     */
    protected function dropIndexCommand(string $command, string $type, string|array|null $index): Command
    {
        $name = match (true) {
            is_array($index) => $this->createIndexName($type, array_values($index)),
            $index === null => null,
            default => $index,
        };

        return $this->addCommand(new Command($command, is_array($index) ? array_values($index) : [], $name));
    }
}
