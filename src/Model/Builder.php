<?php

declare(strict_types=1);

namespace Slendie\Model;

use BadMethodCallException;
use Closure;
use Generator;
use LogicException;
use Slendie\Database\Pagination\Paginator;
use Slendie\Database\Pagination\SimplePaginator;
use Slendie\Database\Query\Builder as QueryBuilder;
use Slendie\Support\Str;

/**
 * Envolve o Query Builder e devolve models em vez de arrays. Os métodos de condição
 * (`where*`, `orderBy`, `join`, …) são reencaminhados para o Query Builder.
 *
 * Os closures de `where(fn ($q) => …)` recebem o Query Builder base, não este Builder.
 *
 * @method $this where(Closure|string|array<string, mixed> $column, mixed $operator = null, mixed $value = null)
 * @method $this orWhere(Closure|string|array<string, mixed> $column, mixed $operator = null, mixed $value = null)
 * @method $this whereIn(string $column, array<array-key, mixed> $values)
 * @method $this whereNotIn(string $column, array<array-key, mixed> $values)
 * @method $this whereNull(string $column)
 * @method $this whereNotNull(string $column)
 * @method $this whereLike(string $column, string $value, bool $caseSensitive = false)
 * @method $this whereBetween(string $column, array<array-key, mixed> $values)
 * @method $this orderBy(string $column, string $direction = 'asc')
 * @method $this orderByDesc(string $column)
 * @method $this latest(string $column = 'created_at')
 * @method $this oldest(string $column = 'created_at')
 * @method $this limit(int $value)
 * @method $this offset(int $value)
 * @method $this take(int $value)
 * @method $this skip(int $value)
 * @method $this select(string ...$columns)
 * @method $this groupBy(string ...$groups)
 * @method $this join(string $table, Closure|string $first, ?string $operator = null, ?string $second = null)
 * @method $this leftJoin(string $table, Closure|string $first, ?string $operator = null, ?string $second = null)
 * @method int count(string $column = '*')
 * @method int|float|string sum(string $column)
 * @method int|float|string|null avg(string $column)
 * @method mixed min(string $column)
 * @method mixed max(string $column)
 * @method bool exists()
 * @method bool doesntExist()
 * @method string toSql()
 * @method list<mixed> getBindings()
 */
class Builder
{
    /** Métodos reencaminhados com os global scopes aplicados; devolvem o resultado do Query Builder. */
    private const array PASSTHRU_SCOPED = [
        'aggregate', 'count', 'sum', 'avg', 'min', 'max', 'exists', 'doesntExist',
        'toSql', 'getBindings', 'getRawBindings', 'toRawSql', 'dump', 'dd',
    ];

    /** Métodos reencaminhados tal como estão (não dependem de scopes). */
    private const array PASSTHRU = ['insert', 'insertGetId', 'insertOrIgnore', 'truncate', 'getGrammar'];

    protected Model $model;

    /** @var list<string> */
    protected array $removedScopes = [];

    public function __construct(protected QueryBuilder $query)
    {
    }

    public function __clone(): void
    {
        $this->query = clone $this->query;
    }

    public function setModel(Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * O Query Builder subjacente (sem global scopes aplicados).
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function withoutGlobalScope(string $name): static
    {
        $this->removedScopes[] = $name;

        return $this;
    }

    /**
     * @param  list<string>|null  $names  null = todos
     */
    public function withoutGlobalScopes(?array $names = null): static
    {
        $this->removedScopes = [...$this->removedScopes, ...($names ?? array_keys($this->model->getGlobalScopes()))];

        return $this;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_diff(array_keys($this->model->getGlobalScopes()), $this->removedScopes));
    }

    /**
     * Cópia do builder com os global scopes aplicados (os `where` existentes ficam agrupados).
     */
    public function applyScopes(): static
    {
        $scopes = array_diff_key($this->model->getGlobalScopes(), array_flip($this->removedScopes));

        if ($scopes === []) {
            return $this;
        }

        $builder = clone $this;
        $builder->query->groupWheres();

        foreach ($scopes as $scope) {
            $scope($builder, $this->model);
        }

        return $builder;
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    protected function callScope(string $scope, array $parameters): static
    {
        $this->model->{$scope}($this, ...$parameters);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Hidratação
    // -------------------------------------------------------------------------

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function hydrate(array $rows): Collection
    {
        return $this->model->newCollection(array_map(
            fn (array $row): Model => $this->model->newFromBuilder($row),
            $rows,
        ));
    }

    // -------------------------------------------------------------------------
    // Leitura
    // -------------------------------------------------------------------------

    /**
     * @param  list<string>|string  $columns
     */
    public function get(array|string $columns = ['*']): Collection
    {
        return $this->hydrate($this->applyScopes()->query->get($columns));
    }

    /** Sinónimo de get(). */
    public function fetchAll(): Collection
    {
        return $this->get();
    }

    /**
     * @param  list<string>|string  $columns
     */
    public function first(array|string $columns = ['*']): ?Model
    {
        $row = $this->applyScopes()->query->first($columns);

        return $row === null ? null : $this->model->newFromBuilder($row);
    }

    /** Sinónimo de first(). */
    public function fetch(): ?Model
    {
        return $this->first();
    }

    /**
     * @param  list<string>|string  $columns
     *
     * @throws ModelNotFoundException
     */
    public function firstOrFail(array|string $columns = ['*']): Model
    {
        return $this->first($columns) ?? throw (new ModelNotFoundException())->setModel($this->model::class);
    }

    /**
     * @param  Closure|string|array<string, mixed>  $column
     */
    public function firstWhere(Closure|string|array $column, mixed $operator = null, mixed $value = null): ?Model
    {
        return $this->where(...func_get_args())->first();
    }

    /**
     * @param  array<array-key, mixed>|int|string  $id
     * @param  list<string>|string  $columns
     */
    public function find(array|int|string $id, array|string $columns = ['*']): Model|Collection|null
    {
        if (is_array($id)) {
            return $this->findMany($id, $columns);
        }

        return $this->where($this->model->getQualifiedKeyName(), '=', $id)->first($columns);
    }

    /**
     * @param  array<array-key, mixed>|int|string  $id
     * @param  list<string>|string  $columns
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(array|int|string $id, array|string $columns = ['*']): Model|Collection
    {
        $result = $this->find($id, $columns);
        $ids = (array) $id;

        if ($result === null || ($result instanceof Collection && count($result) < count(array_unique($ids)))) {
            throw (new ModelNotFoundException())->setModel($this->model::class, $ids);
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @param  list<string>|string  $columns
     */
    public function findMany(array $ids, array|string $columns = ['*']): Collection
    {
        if ($ids === []) {
            return $this->model->newCollection();
        }

        return $this->whereIn($this->model->getQualifiedKeyName(), array_values($ids))->get($columns);
    }

    /**
     * Valores em bruto (sem casts).
     *
     * @return array<array-key, mixed>
     */
    public function pluck(string $column, ?string $key = null): array
    {
        return $this->applyScopes()->query->pluck($column, $key);
    }

    public function value(string $column): mixed
    {
        return $this->applyScopes()->query->value($column);
    }

    /**
     * Processa os resultados por lotes de models. Devolver `false` no callback pára.
     *
     * @param  Closure(Collection, int): mixed  $callback
     */
    public function chunk(int $size, Closure $callback): bool
    {
        return $this->applyScopes()->query->chunk(
            $size,
            fn (array $rows, int $page): mixed => $callback($this->hydrate($rows), $page),
        );
    }

    /**
     * @return Generator<int, Model>
     */
    public function cursor(): Generator
    {
        foreach ($this->applyScopes()->query->cursor() as $row) {
            yield $this->model->newFromBuilder($row);
        }
    }

    public function paginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): Paginator
    {
        $page = max(1, $page ?? Paginator::resolveCurrentPage($pageName));
        $query = $this->applyScopes()->query;

        $total = $query->count();
        $items = $total > 0 ? array_values($this->hydrate((clone $query)->forPage($page, $perPage)->get())->all()) : [];

        return new Paginator($items, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName, 'query' => Paginator::resolveQueryString()]);
    }

    public function simplePaginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): SimplePaginator
    {
        $page = max(1, $page ?? Paginator::resolveCurrentPage($pageName));
        $rows = (clone $this->applyScopes()->query)->offset(($page - 1) * $perPage)->limit($perPage + 1)->get();

        return new SimplePaginator(array_values($this->hydrate($rows)->all()), $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName, 'query' => Paginator::resolveQueryString()]);
    }

    /**
     * Eager loading está fora de âmbito na v4.0.
     *
     * @param  array<array-key, mixed>|string  $relations
     */
    public function with(array|string $relations): never
    {
        throw new LogicException('Eager loading fora de âmbito na v4.0; use ->load()');
    }

    public function when(mixed $condition, Closure $callback, ?Closure $default = null): static
    {
        if ($condition) {
            $callback($this, $condition);
        } elseif ($default !== null) {
            $default($this, $condition);
        }

        return $this;
    }

    public function unless(mixed $condition, Closure $callback, ?Closure $default = null): static
    {
        return $this->when(! $condition, $callback, $default);
    }

    // -------------------------------------------------------------------------
    // Criação
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function make(array $attributes = []): Model
    {
        return $this->model->newInstance($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes = []): Model
    {
        return tap($this->make($attributes), static fn (Model $model): bool => $model->save());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function forceCreate(array $attributes): Model
    {
        return tap($this->model->newInstance()->forceFill($attributes), static fn (Model $model): bool => $model->save());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function firstOrNew(array $attributes, array $values = []): Model
    {
        return $this->where($attributes)->first() ?? $this->make([...$attributes, ...$values]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        return $this->where($attributes)->first() ?? $this->create([...$attributes, ...$values]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $model = $this->firstOrNew($attributes);
        $model->fill($values)->save();

        return $model;
    }

    // -------------------------------------------------------------------------
    // Escrita em massa (não dispara eventos)
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        $updatedAt = $this->model->getUpdatedAtColumn();

        if ($this->model->usesTimestamps() && ! array_key_exists($updatedAt, $values)) {
            $values[$updatedAt] = $this->model->freshTimestampString();
        }

        return $this->applyScopes()->query->update($values);
    }

    /**
     * Apaga as linhas (marca `deleted_at` se o model usa SoftDeletes).
     */
    public function delete(): int
    {
        if ($this->model->usesSoftDeletes() && method_exists($this->model, 'getDeletedAtColumn')) {
            return $this->update([$this->model->getDeletedAtColumn() => $this->model->freshTimestampString()]);
        }

        return $this->applyScopes()->query->delete();
    }

    /**
     * Repõe em massa as linhas apagadas (só com SoftDeletes; use `onlyTrashed()` antes). Não dispara eventos.
     */
    public function restore(): int
    {
        if (! $this->model->usesSoftDeletes() || ! method_exists($this->model, 'getDeletedAtColumn')) {
            throw new LogicException('O model ['.$this->model::class.'] não usa SoftDeletes.');
        }

        return $this->withoutGlobalScope('softDeletes')->update([$this->model->getDeletedAtColumn() => null]);
    }

    public function forceDelete(): int
    {
        return $this->applyScopes()->query->delete();
    }

    public function increment(string $column, int|float $amount = 1): int
    {
        return $this->applyScopes()->query->increment($column, $amount);
    }

    public function decrement(string $column, int|float $amount = 1): int
    {
        return $this->applyScopes()->query->decrement($column, $amount);
    }

    // -------------------------------------------------------------------------
    // Reencaminhamento
    // -------------------------------------------------------------------------

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        $scope = 'scope'.Str::ucfirst($method);

        if (method_exists($this->model, $scope)) {
            return $this->callScope($scope, $parameters);
        }

        if (in_array($method, self::PASSTHRU_SCOPED, true)) {
            return $this->applyScopes()->query->{$method}(...$parameters);
        }

        if (in_array($method, self::PASSTHRU, true)) {
            return $this->query->{$method}(...$parameters);
        }

        if (! method_exists($this->query, $method)) {
            throw new BadMethodCallException('Método ['.$method.'] inexistente em '.static::class.'.');
        }

        $result = $this->query->{$method}(...$parameters);

        return $result === $this->query ? $this : $result;
    }
}
