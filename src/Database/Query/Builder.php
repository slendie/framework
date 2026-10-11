<?php

declare(strict_types=1);

namespace Slendie\Database\Query;

use Closure;
use Generator;
use InvalidArgumentException;
use Slendie\Database\Connection;
use Slendie\Database\Pagination\Paginator;
use Slendie\Database\Pagination\SimplePaginator;
use Slendie\Database\QueryException;
use Slendie\Database\RecordNotFoundException;

/**
 * Query Builder fluente. Valores vão sempre em bindings (prepared statements); nomes de tabelas e
 * colunas são validados por regex; SQL cru só através de Expression/`*Raw()`.
 *
 * As propriedades públicas descrevem o estado e são lidas pela Grammar; não as alteres à mão.
 */
class Builder
{
    public string|Expression|null $from = null;

    /** @var list<string|Expression> vazio = `*` */
    public array $columns = [];

    public bool $distinct = false;

    /** @var array{function: string, columns: list<string|Expression>}|null */
    public ?array $aggregate = null;

    /** @var list<JoinClause> */
    public array $joins = [];

    /** @var list<array<string, mixed>> */
    public array $wheres = [];

    /** @var list<string|Expression> */
    public array $groups = [];

    /** @var list<array<string, mixed>> */
    public array $havings = [];

    /** @var list<array<string, mixed>> */
    public array $orders = [];

    public ?int $limit = null;

    public ?int $offset = null;

    /** @var array{select: list<mixed>, join: list<mixed>, where: list<mixed>, having: list<mixed>, order: list<mixed>} */
    protected array $bindings = ['select' => [], 'join' => [], 'where' => [], 'having' => [], 'order' => []];

    public function __construct(public readonly Connection $connection)
    {
    }

    public function getGrammar(): Grammar
    {
        return $this->connection->getGrammar();
    }

    public function newQuery(): self
    {
        return new self($this->connection);
    }

    public function __clone(): void
    {
        $this->joins = array_map(fn (JoinClause $join): JoinClause => clone $join, $this->joins);

        $this->wheres = array_map(function (array $where): array {
            if (isset($where['query']) && $where['query'] instanceof self) {
                $where['query'] = clone $where['query'];
            }

            return $where;
        }, $this->wheres);
    }

    // -------------------------------------------------------------------------
    // Select / from
    // -------------------------------------------------------------------------

    public function from(string|Expression $table, ?string $as = null): static
    {
        if (is_string($table)) {
            Grammar::assertIdentifier($table);
            $this->from = $as !== null ? $table.' as '.Grammar::assertIdentifier($as) : $table;
        } else {
            $this->from = $table;
        }

        return $this;
    }

    /**
     * @param  string|Expression|array<array-key, string|Expression>  ...$columns
     */
    public function select(string|Expression|array ...$columns): static
    {
        $this->columns = [];
        $this->bindings['select'] = [];

        return $this->addSelect(...$columns);
    }

    /**
     * @param  string|Expression|array<array-key, string|Expression>  ...$columns
     */
    public function addSelect(string|Expression|array ...$columns): static
    {
        foreach ($this->flatten($columns) as $column) {
            $this->columns[] = $column instanceof Expression ? $column : Grammar::assertIdentifier($column);
        }

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    public function selectRaw(string $sql, array $bindings = []): static
    {
        $this->columns[] = new Expression($sql);
        $this->addBinding($bindings, 'select');

        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Joins
    // -------------------------------------------------------------------------

    public function join(string|Expression $table, Closure|string $first, ?string $operator = null, ?string $second = null, string $type = 'inner'): static
    {
        $type = strtolower($type);

        if (! in_array($type, ['inner', 'left', 'right', 'cross'], true)) {
            throw new InvalidArgumentException("Tipo de join inválido [{$type}].");
        }

        $join = new JoinClause($this, $type, $table);

        if ($first instanceof Closure) {
            $first($join);
        } else {
            $join->on($first, $operator, $second);
        }

        $this->joins[] = $join;
        $this->addBinding($join->getRawBindings()['where'], 'join');

        return $this;
    }

    public function leftJoin(string|Expression $table, Closure|string $first, ?string $operator = null, ?string $second = null): static
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string|Expression $table, Closure|string $first, ?string $operator = null, ?string $second = null): static
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function crossJoin(string|Expression $table): static
    {
        $this->joins[] = new JoinClause($this, 'cross', $table);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Where
    // -------------------------------------------------------------------------

    /**
     * `where('a', 1)`, `where('a', '>', 1)`, `where(['a' => 1, 'b' => 2])`, `where(fn ($q) => ...)`.
     *
     * @param  Closure|string|Expression|array<string, mixed>  $column
     */
    public function where(Closure|string|Expression|array $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if (is_array($column)) {
            return $this->addNestedWhere(function (self $query) use ($column): void {
                foreach ($column as $key => $val) {
                    $query->where($key, '=', $val);
                }
            }, $boolean);
        }

        if ($column instanceof Closure) {
            return $this->addNestedWhere($column, $boolean);
        }

        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        $column = $this->identifier($column);
        $operator = Grammar::assertOperator((string) $operator);

        if ($value === null) {
            return match ($operator) {
                '=', 'is' => $this->whereNull($column, $boolean),
                '<>', '!=', 'is not' => $this->whereNotNull($column, $boolean),
                default => throw new InvalidArgumentException("O operador [{$operator}] não aceita null."),
            };
        }

        if (is_array($value)) {
            return match ($operator) {
                'in' => $this->whereIn($column, $value, $boolean),
                'not in' => $this->whereIn($column, $value, $boolean, true),
                'between' => $this->whereBetween($column, array_values($value), $boolean),
                default => throw new InvalidArgumentException("O operador [{$operator}] não aceita arrays."),
            };
        }

        if (in_array($operator, ['in', 'not in', 'between', 'is', 'is not'], true)) {
            throw new InvalidArgumentException("O operador [{$operator}] requer um array ou null; usa whereIn()/whereBetween()/whereNull().");
        }

        $this->wheres[] = ['type' => 'Basic', 'column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => $boolean];

        if (! $value instanceof Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * @param  Closure|string|Expression|array<string, mixed>  $column
     */
    public function orWhere(Closure|string|Expression|array $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Nega o grupo: `whereNot('a', 1)` → `not ("a" = ?)`.
     *
     * @param  Closure|string|Expression|array<string, mixed>  $column
     */
    public function whereNot(Closure|string|Expression|array $column, mixed $operator = null, mixed $value = null): static
    {
        $args = array_slice(func_get_args(), 0, 3);

        return $this->addNestedWhere($column instanceof Closure ? $column : fn (self $query) => $query->where(...$args), 'and', true);
    }

    /**
     * @param  Closure|string|Expression|array<string, mixed>  $column
     */
    public function orWhereNot(Closure|string|Expression|array $column, mixed $operator = null, mixed $value = null): static
    {
        $args = array_slice(func_get_args(), 0, 3);

        return $this->addNestedWhere($column instanceof Closure ? $column : fn (self $query) => $query->where(...$args), 'or', true);
    }

    /**
     * `LIKE` (insensível a maiúsculas por defeito). Se o valor não contiver `%` nem `_`, é envolvido
     * em `%...%` automaticamente; passa o padrão completo (`'ana%'`) para controlar a pesquisa.
     */
    public function whereLike(string|Expression $column, string $value, bool $caseSensitive = false, string $boolean = 'and', bool $not = false): static
    {
        $column = $this->identifier($column);

        if (! str_contains($value, '%') && ! str_contains($value, '_')) {
            $value = '%'.$value.'%';
        }

        $this->wheres[] = ['type' => 'Like', 'column' => $column, 'caseSensitive' => $caseSensitive, 'not' => $not, 'boolean' => $boolean];
        $this->addBinding($this->getGrammar()->prepareLikeBinding($value, $caseSensitive), 'where');

        return $this;
    }

    public function orWhereLike(string|Expression $column, string $value, bool $caseSensitive = false): static
    {
        return $this->whereLike($column, $value, $caseSensitive, 'or');
    }

    public function whereNotLike(string|Expression $column, string $value, bool $caseSensitive = false, string $boolean = 'and'): static
    {
        return $this->whereLike($column, $value, $caseSensitive, $boolean, true);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function whereIn(string|Expression $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $values = array_values($values);

        $this->wheres[] = ['type' => 'In', 'column' => $this->identifier($column), 'values' => $values, 'not' => $not, 'boolean' => $boolean];
        $this->addBinding($this->getGrammar()->bindingsFor($values), 'where');

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function whereNotIn(string|Expression $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function orWhereIn(string|Expression $column, array $values): static
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNull(string|Expression $column, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = ['type' => 'Null', 'column' => $this->identifier($column), 'not' => $not, 'boolean' => $boolean];

        return $this;
    }

    public function whereNotNull(string|Expression $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNull(string|Expression $column): static
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * @param  array{0: mixed, 1: mixed}|list<mixed>  $values
     */
    public function whereBetween(string|Expression $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $values = array_values($values);

        if (count($values) !== 2) {
            throw new InvalidArgumentException('whereBetween() requer exactamente 2 valores.');
        }

        $this->wheres[] = ['type' => 'Between', 'column' => $this->identifier($column), 'not' => $not, 'boolean' => $boolean];
        $this->addBinding($values, 'where');

        return $this;
    }

    /**
     * @param  array{0: mixed, 1: mixed}|list<mixed>  $values
     */
    public function whereNotBetween(string|Expression $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function whereDate(string|Expression $column, mixed $operator, mixed $value = null, string $boolean = 'and'): static
    {
        return $this->addDateWhere('date', $column, func_num_args() === 2 ? '=' : $operator, func_num_args() === 2 ? $operator : $value, $boolean);
    }

    public function whereYear(string|Expression $column, mixed $operator, mixed $value = null, string $boolean = 'and'): static
    {
        return $this->addDateWhere('year', $column, func_num_args() === 2 ? '=' : $operator, func_num_args() === 2 ? $operator : $value, $boolean);
    }

    public function whereMonth(string|Expression $column, mixed $operator, mixed $value = null, string $boolean = 'and'): static
    {
        return $this->addDateWhere('month', $column, func_num_args() === 2 ? '=' : $operator, func_num_args() === 2 ? $operator : $value, $boolean);
    }

    public function whereDay(string|Expression $column, mixed $operator, mixed $value = null, string $boolean = 'and'): static
    {
        return $this->addDateWhere('day', $column, func_num_args() === 2 ? '=' : $operator, func_num_args() === 2 ? $operator : $value, $boolean);
    }

    protected function addDateWhere(string $function, string|Expression $column, mixed $operator, mixed $value, string $boolean): static
    {
        $operator = Grammar::assertOperator((string) $operator);

        if (! in_array($operator, ['=', '<', '>', '<=', '>=', '<>', '!='], true)) {
            throw new InvalidArgumentException("O operador [{$operator}] não é válido em whereDate/whereYear/whereMonth/whereDay.");
        }

        $this->wheres[] = ['type' => 'Date', 'function' => $function, 'column' => $this->identifier($column), 'operator' => $operator, 'boolean' => $boolean];
        $this->addBinding($this->getGrammar()->prepareDateBinding($function, $value), 'where');

        return $this;
    }

    public function whereColumn(string|Expression $first, string $operator, string|Expression|null $second = null, string $boolean = 'and'): static
    {
        if ($second === null) {
            [$second, $operator] = [$operator, '='];
        }

        $this->wheres[] = [
            'type' => 'Column',
            'first' => $this->identifier($first),
            'operator' => Grammar::assertOperator($operator),
            'second' => $this->identifier($second),
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        $this->wheres[] = ['type' => 'Raw', 'sql' => $sql, 'boolean' => $boolean];
        $this->addBinding($bindings, 'where');

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    public function orWhereRaw(string $sql, array $bindings = []): static
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    /**
     * @param  Closure(Builder): mixed  $callback  recebe um Builder para a subquery
     */
    public function whereExists(Closure $callback, string $boolean = 'and', bool $not = false): static
    {
        $query = $this->newQuery();
        $callback($query);

        $this->wheres[] = ['type' => 'Exists', 'query' => $query, 'not' => $not, 'boolean' => $boolean];
        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    /**
     * @param  Closure(Builder): mixed  $callback
     */
    public function whereNotExists(Closure $callback, string $boolean = 'and'): static
    {
        return $this->whereExists($callback, $boolean, true);
    }

    protected function addNestedWhere(Closure $callback, string $boolean, bool $not = false): static
    {
        $query = $this->newQuery();
        $callback($query);

        if ($query->wheres === []) {
            return $this;
        }

        $this->wheres[] = ['type' => 'Nested', 'query' => $query, 'not' => $not, 'boolean' => $boolean];
        $this->addBinding($query->getRawBindings()['where'], 'where');

        return $this;
    }

    /**
     * Aplica o callback só se a condição for verdadeira.
     *
     * @param  Closure(static, mixed): mixed  $callback
     * @param  (Closure(static, mixed): mixed)|null  $default
     */
    public function when(mixed $condition, Closure $callback, ?Closure $default = null): static
    {
        if ($condition) {
            $callback($this, $condition);
        } elseif ($default !== null) {
            $default($this, $condition);
        }

        return $this;
    }

    /**
     * @param  Closure(static, mixed): mixed  $callback
     * @param  (Closure(static, mixed): mixed)|null  $default
     */
    public function unless(mixed $condition, Closure $callback, ?Closure $default = null): static
    {
        return $this->when(! $condition, $callback, $default);
    }

    // -------------------------------------------------------------------------
    // Group / having / order / limit
    // -------------------------------------------------------------------------

    /**
     * @param  string|Expression|array<array-key, string|Expression>  ...$groups
     */
    public function groupBy(string|Expression|array ...$groups): static
    {
        foreach ($this->flatten($groups) as $group) {
            $this->groups[] = $this->identifier($group);
        }

        return $this;
    }

    public function having(string|Expression $column, mixed $operator, mixed $value = null, string $boolean = 'and'): static
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        $operator = Grammar::assertOperator((string) $operator);

        if (in_array($operator, ['in', 'not in', 'between', 'is', 'is not'], true)) {
            throw new InvalidArgumentException("O operador [{$operator}] não é suportado em having(); usa havingRaw().");
        }

        $this->havings[] = ['type' => 'Basic', 'column' => $this->identifier($column), 'operator' => $operator, 'value' => $value, 'boolean' => $boolean];

        if (! $value instanceof Expression) {
            $this->addBinding($value, 'having');
        }

        return $this;
    }

    public function orHaving(string|Expression $column, mixed $operator, mixed $value = null): static
    {
        return func_num_args() === 2
            ? $this->having($column, '=', $operator, 'or')
            : $this->having($column, $operator, $value, 'or');
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    public function havingRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        $this->havings[] = ['type' => 'Raw', 'sql' => $sql, 'boolean' => $boolean];
        $this->addBinding($bindings, 'having');

        return $this;
    }

    public function orderBy(string|Expression $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException("Direcção de ordenação inválida [{$direction}]. Usa 'asc' ou 'desc'.");
        }

        $this->orders[] = ['column' => $this->identifier($column), 'direction' => $direction];

        return $this;
    }

    public function orderByDesc(string|Expression $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function latest(string|Expression $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string|Expression $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    public function inRandomOrder(): static
    {
        return $this->orderByRaw($this->getGrammar()->compileRandom());
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    public function orderByRaw(string $sql, array $bindings = []): static
    {
        $this->orders[] = ['sql' => $sql];
        $this->addBinding($bindings, 'order');

        return $this;
    }

    /**
     * Remove as ordenações; opcionalmente define uma nova.
     */
    public function reorder(string|Expression|null $column = null, string $direction = 'asc'): static
    {
        $this->orders = [];
        $this->bindings['order'] = [];

        return $column === null ? $this : $this->orderBy($column, $direction);
    }

    public function limit(int $value): static
    {
        $this->limit = max(0, $value);

        return $this;
    }

    public function take(int $value): static
    {
        return $this->limit($value);
    }

    public function offset(int $value): static
    {
        $this->offset = max(0, $value);

        return $this;
    }

    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    public function forPage(int $page, int $perPage = 15): static
    {
        $page = max(1, $page);

        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    // -------------------------------------------------------------------------
    // Leitura
    // -------------------------------------------------------------------------

    /**
     * @param  string|Expression|array<array-key, string|Expression>  $columns
     * @return list<array<string, mixed>>
     */
    public function get(string|Expression|array $columns = ['*']): array
    {
        $query = clone $this;

        if ($query->columns === []) {
            $query->columns = array_values($this->flatten([$columns]));
        }

        return $this->connection->select($query->toSql(), $query->getBindings());
    }

    /**
     * Sinónimo de get().
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAll(): array
    {
        return $this->get();
    }

    /**
     * @param  string|Expression|array<array-key, string|Expression>  $columns
     * @return array<string, mixed>|null
     */
    public function first(string|Expression|array $columns = ['*']): ?array
    {
        return (clone $this)->limit(1)->get($columns)[0] ?? null;
    }

    /**
     * Sinónimo de first().
     *
     * @return array<string, mixed>|null
     */
    public function fetch(): ?array
    {
        return $this->first();
    }

    /**
     * @param  string|Expression|array<array-key, string|Expression>  $columns
     * @return array<string, mixed>
     *
     * @throws RecordNotFoundException
     */
    public function firstOrFail(string|Expression|array $columns = ['*']): array
    {
        return $this->first($columns) ?? throw new RecordNotFoundException(
            'Nenhum registo encontrado'.($this->from !== null ? ' em ['.$this->from.']' : '').'.',
            is_string($this->from) ? $this->from : null,
        );
    }

    /**
     * @param  string|Expression|array<array-key, string|Expression>  $columns
     * @return array<string, mixed>|null
     */
    public function find(int|string $id, string|Expression|array $columns = ['*']): ?array
    {
        return (clone $this)->where('id', '=', $id)->first($columns);
    }

    public function value(string|Expression $column): mixed
    {
        $row = $this->first([$column]);

        return $row === null ? null : (array_values($row)[0] ?? null);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function pluck(string|Expression $column, string|Expression|null $key = null): array
    {
        $rows = $this->get($key === null ? [$column] : [$column, $key]);
        $columnName = $this->resultName($column);
        $keyName = $key === null ? null : $this->resultName($key);

        $result = [];

        foreach ($rows as $row) {
            if ($keyName === null) {
                $result[] = $row[$columnName] ?? null;
            } else {
                /** @var array-key $k */
                $k = $row[$keyName];
                $result[$k] = $row[$columnName] ?? null;
            }
        }

        return $result;
    }

    public function count(string|Expression $column = '*'): int
    {
        return (int) $this->aggregate('count', [$column]);
    }

    public function sum(string|Expression $column): int|float|string
    {
        return $this->aggregate('sum', [$column]) ?? 0;
    }

    public function avg(string|Expression $column): int|float|string|null
    {
        return $this->aggregate('avg', [$column]);
    }

    public function min(string|Expression $column): mixed
    {
        return $this->aggregate('min', [$column]);
    }

    public function max(string|Expression $column): mixed
    {
        return $this->aggregate('max', [$column]);
    }

    /**
     * @param  list<string|Expression>  $columns
     */
    public function aggregate(string $function, array $columns = ['*']): mixed
    {
        $function = strtolower($function);

        if (! in_array($function, Grammar::AGGREGATES, true)) {
            throw new InvalidArgumentException("Função de agregação inválida [{$function}].");
        }

        $columns = array_map($this->identifier(...), $columns);
        $query = clone $this;

        if ($query->groups !== [] || $query->havings !== [] || $query->limit !== null || $query->offset !== null) {
            return $this->aggregateOverSubquery($query, $function, $columns);
        }

        $query->aggregate = ['function' => $function, 'columns' => $columns];
        $query->orders = [];
        $query->bindings['order'] = [];
        $query->bindings['select'] = [];
        $query->columns = [];

        $row = $this->connection->selectOne($query->toSql(), $query->getBindings());

        return $row['aggregate'] ?? null;
    }

    /**
     * @param  list<string|Expression>  $columns
     */
    protected function aggregateOverSubquery(self $inner, string $function, array $columns): mixed
    {
        if ($inner->columns === []) {
            $inner->columns = ['*'];
        }

        if ($inner->limit === null && $inner->offset === null) {
            $inner->orders = [];
            $inner->bindings['order'] = [];
        }

        $grammar = $this->getGrammar();
        $columns = array_map(
            fn (string|Expression $c): string|Expression => is_string($c) && str_contains($c, '.') && ! str_ends_with($c, '*') ? substr($c, (int) strrpos($c, '.') + 1) : $c,
            $columns,
        );

        $outer = $this->newQuery()->from(new Expression('('.$inner->toSql().') as '.$grammar->wrap('aggregate_table')));
        $outer->addBinding($inner->getBindings(), 'where');
        $outer->aggregate = ['function' => $function, 'columns' => $columns];

        $row = $this->connection->selectOne($outer->toSql(), $outer->getBindings());

        return $row['aggregate'] ?? null;
    }

    public function exists(): bool
    {
        $query = clone $this;
        $query->orders = [];
        $query->bindings['order'] = [];

        $row = $this->connection->selectOne($this->getGrammar()->compileExists($query), $query->getBindings());

        return (bool) ($row['exists'] ?? false);
    }

    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * Processa os resultados por lotes. Devolve false (e pára) se o callback devolver false.
     * Usa offset: não alteres as linhas que o filtro `where` selecciona durante o chunk.
     *
     * @param  Closure(list<array<string, mixed>>, int): mixed  $callback
     */
    public function chunk(int $size, Closure $callback): bool
    {
        if ($size < 1) {
            throw new InvalidArgumentException('O tamanho do chunk tem de ser >= 1.');
        }

        $page = 1;

        do {
            $rows = (clone $this)->forPage($page, $size)->get();

            if ($rows === []) {
                break;
            }

            if ($callback($rows, $page) === false) {
                return false;
            }

            $page++;
        } while (count($rows) === $size);

        return true;
    }

    /**
     * Itera linha a linha sem carregar tudo em memória.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function cursor(): Generator
    {
        $query = clone $this;

        if ($query->columns === []) {
            $query->columns = ['*'];
        }

        yield from $this->connection->cursor($query->toSql(), $query->getBindings());
    }

    public function paginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): Paginator
    {
        $page = max(1, $page ?? Paginator::resolveCurrentPage($pageName));

        $total = $this->count();
        $items = $total > 0 ? (clone $this)->forPage($page, $perPage)->get() : [];

        return new Paginator($items, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName, 'query' => Paginator::resolveQueryString()]);
    }

    /**
     * Paginação sem `COUNT(*)`: pede uma linha a mais para saber se há página seguinte.
     */
    public function simplePaginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): SimplePaginator
    {
        $page = max(1, $page ?? Paginator::resolveCurrentPage($pageName));

        $items = (clone $this)->offset(($page - 1) * $perPage)->limit($perPage + 1)->get();

        return new SimplePaginator($items, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName, 'query' => Paginator::resolveQueryString()]);
    }

    // -------------------------------------------------------------------------
    // Escrita
    // -------------------------------------------------------------------------

    /**
     * Insere uma linha (`['a' => 1]`) ou várias (`[['a' => 1], ['a' => 2]]`).
     *
     * @param  array<array-key, mixed>  $values
     */
    public function insert(array $values): bool
    {
        $rows = $this->normalizeRows($values);

        if ($rows === []) {
            return true;
        }

        $sql = $this->getGrammar()->compileInsert($this, $rows);

        return $this->connection->insert($sql, $this->insertBindings($rows));
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function insertOrIgnore(array $values): int
    {
        $rows = $this->normalizeRows($values);

        if ($rows === []) {
            return 0;
        }

        return $this->connection->affectingStatement($this->getGrammar()->compileInsertOrIgnore($this, $rows), $this->insertBindings($rows));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function insertGetId(array $values, ?string $sequence = null): int|string
    {
        $rows = $this->normalizeRows($values);
        $grammar = $this->getGrammar();
        $sql = $grammar->compileInsertGetId($this, $rows, $sequence);
        $bindings = $this->insertBindings($rows);

        if ($grammar->insertGetIdUsesReturning()) {
            $row = $this->connection->selectOne($sql, $bindings);
            $id = $row === null ? 0 : array_values($row)[0];
        } else {
            $this->connection->insert($sql, $bindings);
            $id = $this->connection->lastInsertId($sequence);
        }

        return is_int($id) || (is_numeric($id) && (string) (int) $id === (string) $id) ? (int) $id : (string) $id;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $this->assertNoJoins('update');

        $sql = $this->getGrammar()->compileUpdate($this, $values);
        $bindings = [...$this->getGrammar()->bindingsFor($values), ...$this->bindings['where']];

        return $this->connection->update($sql, $bindings);
    }

    /**
     * Actualiza a primeira linha que satisfaz `$attributes` ou insere uma nova.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        if (! (clone $this)->where($attributes)->exists()) {
            return $this->insert([...$attributes, ...$values]);
        }

        if ($values === []) {
            return true;
        }

        return (bool) (clone $this)->where($attributes)->limit(1)->update($values);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function increment(string $column, int|float $amount = 1, array $extra = []): int
    {
        return $this->adjust($column, '+', $amount, $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function decrement(string $column, int|float $amount = 1, array $extra = []): int
    {
        return $this->adjust($column, '-', $amount, $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function adjust(string $column, string $sign, int|float $amount, array $extra): int
    {
        $wrapped = $this->getGrammar()->wrap($this->identifier($column));

        return $this->update([$column => new Expression("{$wrapped} {$sign} {$amount}"), ...$extra]);
    }

    public function delete(int|string|null $id = null): int
    {
        $this->assertNoJoins('delete');

        if ($id !== null) {
            $this->where('id', '=', $id);
        }

        return $this->connection->delete($this->getGrammar()->compileDelete($this), $this->bindings['where']);
    }

    public function truncate(): void
    {
        foreach ($this->getGrammar()->compileTruncate($this) as $sql => $bindings) {
            try {
                $this->connection->statement($sql, $bindings);
            } catch (QueryException $e) {
                // O SQLite só tem sqlite_sequence depois de existir uma tabela AUTOINCREMENT.
                if (! str_contains($sql, 'sqlite_sequence')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<array<string, mixed>>
     */
    protected function normalizeRows(array $values): array
    {
        if ($values === []) {
            return [];
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $rows = [];

        foreach ($values as $row) {
            /** @var array<string, mixed> $row */
            ksort($row);
            $rows[] = $row;
        }

        $columns = array_keys($rows[0]);

        foreach ($rows as $row) {
            if (array_keys($row) !== $columns) {
                throw new InvalidArgumentException('Todas as linhas do insert() têm de ter as mesmas colunas.');
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<mixed>
     */
    protected function insertBindings(array $rows): array
    {
        $bindings = [];

        foreach ($rows as $row) {
            array_push($bindings, ...$this->getGrammar()->bindingsFor($row));
        }

        return $bindings;
    }

    // -------------------------------------------------------------------------
    // SQL / debug
    // -------------------------------------------------------------------------

    public function toSql(): string
    {
        return $this->getGrammar()->compileSelect($this);
    }

    /**
     * @return list<mixed>
     */
    public function getBindings(): array
    {
        return array_merge(...array_values($this->bindings));
    }

    /**
     * @return array{select: list<mixed>, join: list<mixed>, where: list<mixed>, having: list<mixed>, order: list<mixed>}
     */
    public function getRawBindings(): array
    {
        return $this->bindings;
    }

    /**
     * SQL com os valores interpolados. **Só para debug**; nunca executes o resultado.
     */
    public function toRawSql(): string
    {
        return Grammar::interpolate($this->toSql(), $this->connection->prepareBindings($this->getBindings()));
    }

    public function dump(): static
    {
        dump($this->toRawSql());

        return $this;
    }

    public function dd(): never
    {
        dd($this->toRawSql());
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    /**
     * @param  mixed|array<array-key, mixed>  $value
     */
    protected function addBinding(mixed $value, string $type): void
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Tipo de binding inválido [{$type}].");
        }

        if (is_array($value)) {
            $this->bindings[$type] = [...$this->bindings[$type], ...array_values($value)];
        } else {
            $this->bindings[$type][] = $value;
        }
    }

    protected function assertNoJoins(string $operation): void
    {
        if ($this->joins !== []) {
            throw new InvalidArgumentException("{$operation}() com joins não é suportado; usa whereIn()/whereExists().");
        }
    }

    /**
     * Valida um nome de tabela/coluna (Expression passa tal como está).
     */
    protected function identifier(string|Expression $name): string|Expression
    {
        return $name instanceof Expression ? $name : Grammar::assertIdentifier($name);
    }

    /**
     * Nome com que a coluna aparece no resultado: `users.name` → `name`; `x as y` → `y`.
     */
    protected function resultName(string|Expression $column): string
    {
        if ($column instanceof Expression) {
            return (string) $column->getValue();
        }

        if (preg_match('/\s+as\s+(\w+)$/i', $column, $m) === 1) {
            return $m[1];
        }

        return str_contains($column, '.') ? substr($column, (int) strrpos($column, '.') + 1) : $column;
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<string|Expression>
     */
    protected function flatten(array $items): array
    {
        $flat = [];

        array_walk_recursive($items, function (mixed $item) use (&$flat): void {
            if (is_string($item) || $item instanceof Expression) {
                $flat[] = $item;
            }
        });

        return $flat;
    }
}
