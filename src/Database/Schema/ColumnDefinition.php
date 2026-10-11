<?php

declare(strict_types=1);

namespace Slendie\Database\Schema;

use LogicException;
use Slendie\Support\Str;

/**
 * Coluna fluente devolvida por cada método de tipo do Blueprint: `$table->string('email')->nullable()->index()`.
 */
class ColumnDefinition
{
    public ?int $length = null;

    public ?int $precision = null;

    public ?int $total = null;

    public ?int $places = null;

    /** @var list<string> */
    public array $allowed = [];

    public bool $nullable = false;

    public bool $unsigned = false;

    public bool $autoIncrement = false;

    public bool $primary = false;

    public bool|string $unique = false;

    public bool|string $index = false;

    public bool $hasDefault = false;

    public mixed $default = null;

    public bool $useCurrent = false;

    public bool $useCurrentOnUpdate = false;

    public bool $first = false;

    public bool $change = false;

    public ?string $comment = null;

    public ?string $after = null;

    public ?string $charset = null;

    public ?string $collation = null;

    public function __construct(
        public string $name,
        public ColumnType $type,
        protected ?Blueprint $blueprint = null,
    ) {
    }

    public function nullable(bool $value = true): static
    {
        $this->nullable = $value;

        return $this;
    }

    /** Aceita valores simples ou uma Expression (`DB::raw('CURRENT_TIMESTAMP')`). */
    public function default(mixed $value): static
    {
        $this->hasDefault = true;
        $this->default = $value;

        return $this;
    }

    public function useCurrent(): static
    {
        $this->useCurrent = true;

        return $this;
    }

    /** Só MySQL. */
    public function useCurrentOnUpdate(): static
    {
        $this->useCurrentOnUpdate = true;

        return $this;
    }

    public function unsigned(bool $value = true): static
    {
        $this->unsigned = $value;

        return $this;
    }

    public function autoIncrement(bool $value = true): static
    {
        $this->autoIncrement = $value;

        return $this;
    }

    public function primary(bool $value = true): static
    {
        $this->primary = $value;

        return $this;
    }

    public function unique(bool|string $name = true): static
    {
        $this->unique = $name;

        return $this;
    }

    public function index(bool|string $name = true): static
    {
        $this->index = $name;

        return $this;
    }

    public function comment(string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    /** Só MySQL. */
    public function after(string $column): static
    {
        $this->after = $column;

        return $this;
    }

    /** Só MySQL. */
    public function first(): static
    {
        $this->first = true;

        return $this;
    }

    /** Altera uma coluna existente (não suportado em SQLite). */
    public function change(): static
    {
        $this->change = true;

        return $this;
    }

    public function charset(string $charset): static
    {
        $this->charset = $charset;

        return $this;
    }

    public function collation(string $collation): static
    {
        $this->collation = $collation;

        return $this;
    }

    /**
     * Chave estrangeira para `$table.$column` (por omissão deduz a tabela de `user_id` → `users`).
     */
    public function constrained(?string $table = null, string $column = 'id', ?string $indexName = null): ForeignKeyDefinition
    {
        if ($this->blueprint === null) {
            throw new LogicException('constrained() só pode ser usado numa coluna criada por um Blueprint.');
        }

        $table ??= Str::plural((string) preg_replace('/_'.preg_quote($column, '/').'$/', '', $this->name));

        return $this->blueprint->foreign($this->name, $indexName)->references($column)->on($table);
    }
}
