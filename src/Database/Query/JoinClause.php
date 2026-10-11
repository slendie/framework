<?php

declare(strict_types=1);

namespace Slendie\Database\Query;

use Closure;
use InvalidArgumentException;

/**
 * Cláusula `join ... on ...`. Reutiliza os wheres do Builder: `on()` compara colunas,
 * `where()`/`orWhere()` comparam com valores (em bindings).
 */
class JoinClause extends Builder
{
    public function __construct(
        protected Builder $parentQuery,
        public readonly string $type,
        public readonly string|Expression $table,
    ) {
        parent::__construct($parentQuery->connection);

        if (is_string($table)) {
            Grammar::assertIdentifier($table);
        }
    }

    /**
     * `on('a.id', 'b.a_id')`, `on('a.id', '=', 'b.a_id')` ou `on(fn (JoinClause $j) => ...)` (grupo).
     */
    public function on(Closure|string|Expression $first, ?string $operator = null, string|Expression|null $second = null, string $boolean = 'and'): static
    {
        if ($first instanceof Closure) {
            return $this->addNestedWhere($first, $boolean);
        }

        if ($operator === null) {
            throw new InvalidArgumentException('on() requer pelo menos duas colunas.');
        }

        if ($second === null) {
            [$second, $operator] = [$operator, '='];
        }

        return $this->whereColumn($first, $operator, $second, $boolean);
    }

    /**
     * @param  Closure|string|Expression  $first
     */
    public function orOn(Closure|string|Expression $first, ?string $operator = null, string|Expression|null $second = null): static
    {
        return $this->on($first, $operator, $second, 'or');
    }

    public function newQuery(): self
    {
        return new self($this->parentQuery, $this->type, $this->table);
    }
}
