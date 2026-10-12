<?php

declare(strict_types=1);

namespace Slendie\Validation;

use ArrayAccess;
use Countable;
use Slendie\Contracts\Arrayable;
use Slendie\Support\Arr;

/**
 * Os dados validados (`$validator->safe()`, `$request->safe()`).
 *
 * @implements ArrayAccess<string, mixed>
 */
class ValidatedInput implements Arrayable, ArrayAccess, Countable
{
    /**
     * @param  array<string, mixed>  $input
     */
    final public function __construct(protected array $input)
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->input;
    }

    /**
     * @param  string|list<string>  ...$keys
     * @return array<string, mixed>
     */
    public function only(string|array ...$keys): array
    {
        return Arr::only($this->input, $this->flatten($keys));
    }

    /**
     * @param  string|list<string>  ...$keys
     * @return array<string, mixed>
     */
    public function except(string|array ...$keys): array
    {
        return Arr::except($this->input, $this->flatten($keys));
    }

    /**
     * @param  array<string, mixed>  $items
     */
    public function merge(array $items): static
    {
        return new static(array_replace($this->input, $items));
    }

    public function has(string $key): bool
    {
        return Arr::has($this->input, $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->input, $key, $default);
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->input;
    }

    public function count(): int
    {
        return count($this->input);
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? $this->get($offset) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($offset)) {
            $this->input[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset)) {
            unset($this->input[$offset]);
        }
    }

    /**
     * @param  array<array-key, string|list<string>>  $keys
     * @return list<string>
     */
    private function flatten(array $keys): array
    {
        $flat = [];

        array_walk_recursive($keys, static function (mixed $key) use (&$flat): void {
            $flat[] = (string) $key;
        });

        return $flat;
    }
}
