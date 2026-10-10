<?php

declare(strict_types=1);

namespace Slendie\Support;

use ArrayAccess;

/**
 * Repositório de configuração com acesso por dot notation (config('app.name')).
 *
 * @implements ArrayAccess<string, mixed>
 */
final class Config implements ArrayAccess
{
    /**
     * @param  array<string, mixed>  $items
     */
    public function __construct(private array $items = [])
    {
    }

    /**
     * Carrega todos os ficheiros *.php de uma pasta; o nome do ficheiro é a chave de topo.
     */
    public function loadFromDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $value = require $file;

            if (is_array($value)) {
                $this->items[$key] = $value;
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    /**
     * @param  string|array<string, mixed>  $key
     */
    public function set(string|array $key, mixed $value = null): void
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $k => $v) {
            Arr::set($this->items, $k, $v);
        }
    }

    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    public function forget(string $key): void
    {
        Arr::forget($this->items, $key);
    }

    /**
     * Adiciona valores a um array de configuração.
     */
    public function push(string $key, mixed $value): void
    {
        $array = $this->get($key, []);
        $array = is_array($array) ? $array : [$array];
        $array[] = $value;

        $this->set($key, $array);
    }

    /**
     * Preenche apenas as chaves em falta (usado por mergeConfigFrom de packages).
     *
     * @param  array<string, mixed>  $defaults
     */
    public function merge(string $key, array $defaults): void
    {
        $current = $this->get($key, []);
        $current = is_array($current) ? $current : [];

        $this->set($key, array_merge($defaults, $current));
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->forget((string) $offset);
    }
}
