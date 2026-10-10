<?php

declare(strict_types=1);

namespace Slendie\Support;

use ArrayAccess;
use Closure;

final class Arr
{
    /**
     * @param  array<array-key, mixed>|ArrayAccess<array-key, mixed>  $array
     */
    public static function get(array|ArrayAccess $array, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $array;
        }

        if (self::exists($array, $key)) {
            return $array[$key];
        }

        if (is_int($key) || ! str_contains($key, '.')) {
            return $default;
        }

        foreach (explode('.', $key) as $segment) {
            if ((is_array($array) || $array instanceof ArrayAccess) && self::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * @param  array<array-key, mixed>  $array
     * @return array<array-key, mixed>
     */
    public static function set(array &$array, string|int|null $key, mixed $value): array
    {
        if ($key === null) {
            return $array = is_array($value) ? $value : [$value];
        }

        $keys = is_int($key) ? [$key] : explode('.', $key);
        $ref = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                break;
            }

            if (! isset($ref[$segment]) || ! is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref[end($keys)] = $value;

        return $array;
    }

    /**
     * @param  array<array-key, mixed>|ArrayAccess<array-key, mixed>  $array
     * @param  string|int|list<string|int>  $keys
     */
    public static function has(array|ArrayAccess $array, string|int|array $keys): bool
    {
        $keys = is_array($keys) ? $keys : [$keys];

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            if (self::exists($array, $key)) {
                continue;
            }

            if (is_int($key)) {
                return false;
            }

            $sub = $array;
            foreach (explode('.', $key) as $segment) {
                if ((is_array($sub) || $sub instanceof ArrayAccess) && self::exists($sub, $segment)) {
                    $sub = $sub[$segment];
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<array-key, mixed>  $array
     * @param  string|int|list<string|int>  $keys
     */
    public static function forget(array &$array, string|int|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            if (self::exists($array, $key)) {
                unset($array[$key]);

                continue;
            }

            if (is_int($key)) {
                continue;
            }

            $parts = explode('.', $key);
            $ref = &$array;

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (! isset($ref[$part]) || ! is_array($ref[$part])) {
                    continue 2;
                }

                $ref = &$ref[$part];
            }

            unset($ref[array_shift($parts)]);
        }
    }

    /**
     * @param  array<array-key, mixed>|ArrayAccess<array-key, mixed>  $array
     */
    public static function exists(array|ArrayAccess $array, string|int $key): bool
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        return array_key_exists($key, $array);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param  array<TKey, TValue>  $array
     * @param  list<TKey>|TKey  $keys
     * @return array<TKey, TValue>
     */
    public static function only(array $array, array|string|int $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param  array<TKey, TValue>  $array
     * @param  list<TKey>|TKey  $keys
     * @return array<TKey, TValue>
     */
    public static function except(array $array, array|string|int $keys): array
    {
        self::forget($array, (array) $keys);

        return $array;
    }

    /**
     * @template TValue
     * @param  iterable<array-key, TValue>  $array
     * @param  (Closure(TValue, array-key): bool)|null  $callback
     * @return TValue|mixed
     */
    public static function first(iterable $array, ?Closure $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            foreach ($array as $item) {
                return $item;
            }

            return $default;
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @template TValue
     * @param  array<array-key, TValue>  $array
     * @param  (Closure(TValue, array-key): bool)|null  $callback
     * @return TValue|mixed
     */
    public static function last(array $array, ?Closure $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return $array === [] ? $default : end($array);
        }

        return self::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * @param  iterable<array-key, mixed>  $array
     * @return list<mixed>
     */
    public static function flatten(iterable $array, int $depth = PHP_INT_MAX): array
    {
        $result = [];

        foreach ($array as $item) {
            if (! is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = [...$result, ...array_values($item)];
            } else {
                $result = [...$result, ...self::flatten($item, $depth - 1)];
            }
        }

        return $result;
    }

    /**
     * @param  iterable<array-key, mixed>  $array
     * @return array<array-key, mixed>
     */
    public static function pluck(iterable $array, string $value, ?string $key = null): array
    {
        $results = [];

        foreach ($array as $item) {
            $itemValue = self::dataGet($item, $value);

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = self::dataGet($item, $key);
                $results[is_int($itemKey) || is_string($itemKey) ? $itemKey : (string) $itemKey] = $itemValue;
            }
        }

        return $results;
    }

    /**
     * Acesso por dot notation a arrays e objectos.
     */
    public static function dataGet(mixed $target, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $target;
        }

        $segments = is_int($key) ? [$key] : explode('.', $key);

        foreach ($segments as $segment) {
            if (is_array($target) || $target instanceof ArrayAccess) {
                if (! self::exists($target, $segment)) {
                    return $default;
                }
                $target = $target[$segment];
            } elseif (is_object($target)) {
                if (! isset($target->{$segment})) {
                    return $default;
                }
                $target = $target->{$segment};
            } else {
                return $default;
            }
        }

        return $target;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    public static function isAssoc(array $array): bool
    {
        return ! array_is_list($array);
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    public static function isList(array $array): bool
    {
        return array_is_list($array);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @param  array<TKey, TValue>  $array
     * @param  Closure(TValue, TKey): bool  $callback
     * @return array<TKey, TValue>
     */
    public static function where(array $array, Closure $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @template TResult
     * @param  array<TKey, TValue>  $array
     * @param  Closure(TValue, TKey): TResult  $callback
     * @return array<TKey, TResult>
     */
    public static function map(array $array, Closure $callback): array
    {
        $keys = array_keys($array);
        $items = array_map($callback, $array, $keys);

        return array_combine($keys, $items);
    }

    /**
     * Junta as chaves de um array multidimensional em dot notation.
     *
     * @param  iterable<array-key, mixed>  $array
     * @return array<string, mixed>
     */
    public static function dot(iterable $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && $value !== []) {
                $results = [...$results, ...self::dot($value, $prepend.$key.'.')];
            } else {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Inverso de dot().
     *
     * @param  iterable<string, mixed>  $array
     * @return array<array-key, mixed>
     */
    public static function undot(iterable $array): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            self::set($results, $key, $value);
        }

        return $results;
    }
}
