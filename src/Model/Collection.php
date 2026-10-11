<?php

declare(strict_types=1);

namespace Slendie\Model;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Slendie\Contracts\Arrayable;
use Slendie\Support\Arr;
use Stringable;
use Traversable;

/**
 * Colecção fluente de models (ou de qualquer valor). Os métodos de transformação (`map()`, `filter()`, …)
 * devolvem uma nova colecção; `push()`, `put()` e `pull()` alteram esta.
 *
 * @implements ArrayAccess<array-key, mixed>
 * @implements IteratorAggregate<array-key, mixed>
 *
 * @phpstan-consistent-constructor
 */
class Collection implements Arrayable, ArrayAccess, Countable, IteratorAggregate, JsonSerializable, Stringable
{
    /** @var array<array-key, mixed> */
    protected array $items;

    /**
     * @param  array<array-key, mixed>  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (mixed $item): mixed => $item instanceof Arrayable ? $item->toArray() : $item,
            $this->items,
        );
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->jsonSerialize(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn (mixed $item): mixed => $item instanceof JsonSerializable ? $item->jsonSerialize() : ($item instanceof Arrayable ? $item->toArray() : $item),
            $this->items,
        );
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    // -------------------------------------------------------------------------
    // Iteração, contagem
    // -------------------------------------------------------------------------

    /**
     * @return Traversable<array-key, mixed>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    // -------------------------------------------------------------------------
    // Transformação
    // -------------------------------------------------------------------------

    /**
     * @param  Closure(mixed, array-key): mixed  $callback
     */
    public function map(Closure $callback): static
    {
        $result = [];

        foreach ($this->items as $key => $item) {
            $result[$key] = $callback($item, $key);
        }

        return new static($result);
    }

    /**
     * Sem callback remove os valores "falsy".
     *
     * @param  (Closure(mixed, array-key): bool)|null  $callback
     */
    public function filter(?Closure $callback = null): static
    {
        return new static($callback === null ? array_filter($this->items) : array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param  Closure(mixed, array-key): bool  $callback
     */
    public function reject(Closure $callback): static
    {
        return $this->filter(static fn (mixed $item, mixed $key): bool => ! $callback($item, $key));
    }

    /**
     * Percorre os itens; devolver `false` no callback pára a iteração.
     *
     * @param  Closure(mixed, array-key): mixed  $callback
     */
    public function each(Closure $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * @param  (Closure(mixed, array-key): bool)|null  $callback
     */
    public function first(?Closure $callback = null, mixed $default = null): mixed
    {
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * @param  (Closure(mixed, array-key): bool)|null  $callback
     */
    public function last(?Closure $callback = null, mixed $default = null): mixed
    {
        return Arr::last($this->items, $callback, $default);
    }

    public function pluck(string $value, ?string $indexBy = null): static
    {
        $result = [];

        foreach ($this->items as $item) {
            $v = Arr::dataGet($item, $value);

            if ($indexBy === null) {
                $result[] = $v;
            } else {
                $result[$this->toKey(Arr::dataGet($item, $indexBy))] = $v;
            }
        }

        return new static($result);
    }

    /**
     * @param  string|Closure(mixed, array-key): mixed  $key
     */
    public function keyBy(string|Closure $key): static
    {
        $result = [];

        foreach ($this->items as $k => $item) {
            $result[$this->toKey($key instanceof Closure ? $key($item, $k) : Arr::dataGet($item, $key))] = $item;
        }

        return new static($result);
    }

    /**
     * Devolve uma colecção de colecções, indexada pelo valor agrupado.
     *
     * @param  string|Closure(mixed, array-key): mixed  $key
     */
    public function groupBy(string|Closure $key): static
    {
        $groups = [];

        foreach ($this->items as $k => $item) {
            $groups[$this->toKey($key instanceof Closure ? $key($item, $k) : Arr::dataGet($item, $key))][] = $item;
        }

        return new static(array_map(static fn (array $items): static => new static($items), $groups));
    }

    /**
     * @param  string|Closure(mixed): mixed  $by
     */
    public function sortBy(string|Closure $by, bool $descending = false): static
    {
        $keys = array_map(
            static fn (mixed $item): mixed => $by instanceof Closure ? $by($item) : Arr::dataGet($item, $by),
            $this->items,
        );

        $descending ? arsort($keys) : asort($keys);

        $result = [];

        foreach (array_keys($keys) as $k) {
            $result[$k] = $this->items[$k];
        }

        return new static($result);
    }

    /**
     * @param  string|Closure(mixed): mixed  $by
     */
    public function sortByDesc(string|Closure $by): static
    {
        return $this->sortBy($by, true);
    }

    public function sortKeys(bool $descending = false): static
    {
        $items = $this->items;
        $descending ? krsort($items) : ksort($items);

        return new static($items);
    }

    public function reverse(): static
    {
        return new static(array_reverse($this->items, true));
    }

    public function values(): static
    {
        return new static(array_values($this->items));
    }

    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    // -------------------------------------------------------------------------
    // Agregados
    // -------------------------------------------------------------------------

    /**
     * @param  string|(Closure(mixed): mixed)|null  $by
     */
    public function sum(string|Closure|null $by = null): int|float
    {
        return array_sum($this->numbers($by));
    }

    /**
     * @param  string|(Closure(mixed): mixed)|null  $by
     */
    public function avg(string|Closure|null $by = null): int|float|null
    {
        $numbers = $this->numbers($by);

        return $numbers === [] ? null : array_sum($numbers) / count($numbers);
    }

    /**
     * @param  string|(Closure(mixed): mixed)|null  $by
     */
    public function min(string|Closure|null $by = null): mixed
    {
        $values = $this->mapForAggregate($by);

        return $values === [] ? null : min($values);
    }

    /**
     * @param  string|(Closure(mixed): mixed)|null  $by
     */
    public function max(string|Closure|null $by = null): mixed
    {
        $values = $this->mapForAggregate($by);

        return $values === [] ? null : max($values);
    }

    /**
     * @return list<int|float>
     */
    protected function numbers(string|Closure|null $by): array
    {
        return array_map(
            static fn (mixed $v): int|float => is_int($v) || is_float($v) ? $v : (is_numeric($v) ? $v + 0 : 0),
            $this->mapForAggregate($by),
        );
    }

    /**
     * @return list<mixed>
     */
    protected function mapForAggregate(string|Closure|null $by): array
    {
        return array_values(array_map(
            static fn (mixed $item): mixed => match (true) {
                $by === null => $item,
                $by instanceof Closure => $by($item),
                default => Arr::dataGet($item, $by),
            },
            $this->items,
        ));
    }

    private function toKey(mixed $value): string|int
    {
        return match (true) {
            is_int($value), is_string($value) => $value,
            is_bool($value) => (int) $value,
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => (string) json_encode($value),
        };
    }

    // -------------------------------------------------------------------------
    // Pesquisa
    // -------------------------------------------------------------------------

    /**
     * `contains($valor)`, `contains($model)`, `contains($closure)` ou `contains('chave', $valor)`.
     */
    public function contains(mixed $key, mixed $value = null): bool
    {
        if (func_num_args() === 2 && is_string($key)) {
            return $this->where($key, '=', $value)->isNotEmpty();
        }

        if ($key instanceof Closure) {
            foreach ($this->items as $k => $item) {
                if ($key($item, $k)) {
                    return true;
                }
            }

            return false;
        }

        if ($key instanceof Model) {
            foreach ($this->items as $item) {
                if ($item instanceof Model && $item->is($key)) {
                    return true;
                }
            }

            return false;
        }

        return in_array($key, $this->items, true);
    }

    /**
     * `where('status', 'active')` ou `where('votes', '>', 3)`.
     */
    public function where(string $key, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        return $this->filter(function (mixed $item) use ($key, $operator, $value): bool {
            $actual = Arr::dataGet($item, $key);

            return match ($operator) {
                '=', '==' => $actual == $value,
                '===' => $actual === $value,
                '!=', '<>' => $actual != $value,
                '!==' => $actual !== $value,
                '<' => $actual < $value,
                '<=' => $actual <= $value,
                '>' => $actual > $value,
                '>=' => $actual >= $value,
                default => throw new InvalidArgumentException('Operador ['.(is_scalar($operator) ? $operator : gettype($operator)).'] inválido em Collection::where().'),
            };
        });
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function whereIn(string $key, array $values): static
    {
        return $this->filter(static fn (mixed $item): bool => in_array(Arr::dataGet($item, $key), $values, false));
    }

    /**
     * @param  string|(Closure(mixed, array-key): mixed)|null  $by
     */
    public function unique(string|Closure|null $by = null): static
    {
        $seen = [];
        $result = [];

        foreach ($this->items as $k => $item) {
            $id = match (true) {
                $by === null => $item instanceof Model ? $item::class.':'.(is_scalar($item->getKey()) ? (string) $item->getKey() : '') : $item,
                $by instanceof Closure => $by($item, $k),
                default => Arr::dataGet($item, $by),
            };

            $hash = is_scalar($id) || $id === null ? gettype($id).':'.(string) $id : serialize($id);

            if (isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;
            $result[$k] = $item;
        }

        return new static($result);
    }

    public function take(int $limit): static
    {
        return $limit < 0
            ? new static(array_slice($this->items, $limit, null, true))
            : new static(array_slice($this->items, 0, $limit, true));
    }

    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Colecção de colecções com `$size` itens cada.
     */
    public function chunk(int $size): static
    {
        if ($size < 1) {
            return new static([]);
        }

        return new static(array_map(
            static fn (array $chunk): static => new static($chunk),
            array_chunk($this->items, $size, true),
        ));
    }

    /**
     * @param  iterable<array-key, mixed>  $items
     */
    public function merge(iterable $items): static
    {
        $other = $items instanceof self ? $items->all() : (is_array($items) ? $items : iterator_to_array($items));

        return new static(array_merge($this->items, $other));
    }

    // -------------------------------------------------------------------------
    // Mutação e acesso
    // -------------------------------------------------------------------------

    public function push(mixed ...$values): static
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }

        return $this;
    }

    public function put(string|int $key, mixed $value): static
    {
        $this->items[$key] = $value;

        return $this;
    }

    public function pull(string|int $key, mixed $default = null): mixed
    {
        $value = $this->items[$key] ?? $default;
        unset($this->items[$key]);

        return $value;
    }

    public function get(string|int $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? ($default instanceof Closure ? $default() : $default);
    }

    public function has(string|int $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function implode(string $glue, ?string $key = null): string
    {
        $values = $key === null ? $this->items : $this->pluck($key)->all();

        return implode($glue, array_map(static fn (mixed $v): string => is_scalar($v) || $v instanceof Stringable ? (string) $v : '', $values));
    }

    /**
     * @param  list<int|string>|string|int  $keys
     */
    public function only(array|string|int $keys): static
    {
        return new static(Arr::only($this->items, $keys));
    }

    /**
     * @param  list<int|string>|string|int  $keys
     */
    public function except(array|string|int $keys): static
    {
        return new static(Arr::except($this->items, $keys));
    }

    /**
     * Um item aleatório (`$number = 1`) ou uma colecção com `$number` itens.
     */
    public function random(int $number = 1): mixed
    {
        if ($this->items === []) {
            return $number === 1 ? null : new static([]);
        }

        if ($number === 1) {
            return $this->items[array_rand($this->items)];
        }

        $keys = (array) array_rand($this->items, min($number, count($this->items)));

        return new static(array_map(fn (string|int $k): mixed => $this->items[$k], $keys));
    }

    public function shuffle(): static
    {
        $items = array_values($this->items);
        shuffle($items);

        return new static($items);
    }

    // -------------------------------------------------------------------------
    // Específicos de models
    // -------------------------------------------------------------------------

    /**
     * Procura um model da colecção pela chave primária.
     */
    public function find(mixed $key, mixed $default = null): mixed
    {
        if ($key instanceof Model) {
            $key = $key->getKey();
        }

        foreach ($this->items as $item) {
            if ($item instanceof Model && $item->getKey() == $key) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * @return list<int|string>
     */
    public function modelKeys(): array
    {
        $keys = [];

        foreach ($this->items as $item) {
            if ($item instanceof Model && ($key = $item->getKey()) !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Carrega as relações em cada model (uma query por relação por model — sem eager batching).
     *
     * @param  string|list<string>  $relations
     */
    public function load(string|array $relations): static
    {
        foreach ($this->items as $item) {
            if ($item instanceof Model) {
                $item->load($relations);
            }
        }

        return $this;
    }

    /**
     * Volta a ler cada model da base de dados.
     */
    public function fresh(): static
    {
        return new static(array_map(
            static fn (mixed $item): mixed => $item instanceof Model ? $item->fresh() : $item,
            $this->items,
        ));
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
