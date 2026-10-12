<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

use Closure;
use Slendie\Database\Query\Builder;
use Slendie\Validation\ValidationRule;
use Slendie\Validation\Validator;
use Slendie\Validation\ValidatorAwareRule;

/**
 * Base de `unique` e `exists`: tabela, coluna e condições extra.
 */
abstract class DatabaseRule implements ValidationRule, ValidatorAwareRule
{
    protected ?Validator $validator = null;

    /** @var list<Closure(Builder): mixed> */
    protected array $wheres = [];

    public function __construct(protected string $table, protected string $column = 'NULL')
    {
    }

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * `where('active', 1)`, `where('deleted_at', null)` (IS NULL) ou `where(fn (Builder $q) => ...)`.
     *
     * @param  Closure(Builder): mixed|string  $column
     */
    public function where(Closure|string $column, mixed $value = null): static
    {
        if ($column instanceof Closure) {
            $this->wheres[] = $column;
        } elseif ($value === null) {
            $this->wheres[] = static fn (Builder $query): mixed => $query->whereNull($column);
        } else {
            $this->wheres[] = static fn (Builder $query): mixed => $query->where($column, '=', $value);
        }

        return $this;
    }

    public function whereNot(string $column, mixed $value): static
    {
        $this->wheres[] = static fn (Builder $query): mixed => $query->where($column, '<>', $value);

        return $this;
    }

    public function whereNull(string $column): static
    {
        return $this->where($column, null);
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = static fn (Builder $query): mixed => $query->whereNull($column, 'and', true);

        return $this;
    }

    /** A coluna a comparar (por omissão, o nome do campo). */
    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    protected function resolveColumn(string $attribute): string
    {
        if ($this->column !== 'NULL') {
            return $this->column;
        }

        $last = str_contains($attribute, '.') ? substr($attribute, (int) strrpos($attribute, '.') + 1) : $attribute;

        return ctype_digit($last) ? $attribute : $last;
    }

    protected function validator(): Validator
    {
        return $this->validator ?? throw new \LogicException('A regra precisa de um Validator.');
    }
}
