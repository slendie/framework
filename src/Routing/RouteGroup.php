<?php

declare(strict_types=1);

namespace Slendie\Routing;

use Closure;

/**
 * Grupo pendente: acumula atributos (prefix, name, middleware, controller, where) e aplica-os
 * a cada Route criada dentro do grupo. Também faz o merge de atributos de grupos aninhados.
 *
 * @method Route get(string $uri, mixed $action)
 * @method Route post(string $uri, mixed $action)
 * @method Route put(string $uri, mixed $action)
 * @method Route patch(string $uri, mixed $action)
 * @method Route delete(string $uri, mixed $action)
 * @method Route any(string $uri, mixed $action)
 */
class RouteGroup
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(protected Router $router, protected array $attributes = [])
    {
    }

    public function prefix(string $prefix): static
    {
        $this->attributes = self::merge(['prefix' => $prefix], $this->attributes);

        return $this;
    }

    /**
     * @param  string|list<string>  ...$middleware
     */
    public function middleware(string|array ...$middleware): static
    {
        $this->attributes = self::merge(['middleware' => array_merge(...array_map(static fn (string|array $m): array => (array) $m, $middleware))], $this->attributes);

        return $this;
    }

    public function name(string $prefix): static
    {
        $this->attributes = self::merge(['as' => $prefix], $this->attributes);

        return $this;
    }

    /**
     * @param  class-string  $controller
     */
    public function controller(string $controller): static
    {
        $this->attributes['controller'] = $controller;

        return $this;
    }

    /**
     * @param  array<string, string>  $wheres
     */
    public function where(array $wheres): static
    {
        $this->attributes = self::merge(['where' => $wheres], $this->attributes);

        return $this;
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function group(Closure|string $routes): void
    {
        $this->router->group($this->attributes, $routes);
    }

    /**
     * Permite `Route::prefix('x')->get(...)`: regista a rota dentro do grupo e devolve-a.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->router->withAttributes($this->attributes, fn (): mixed => $this->router->{$method}(...$arguments));
    }

    /**
     * Junta os atributos de um grupo aos do grupo pai.
     *
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $old
     * @return array<string, mixed>
     */
    public static function merge(array $new, array $old): array
    {
        $merged = $old;

        if (isset($new['prefix'])) {
            $merged['prefix'] = trim(trim((string) ($old['prefix'] ?? ''), '/').'/'.trim((string) $new['prefix'], '/'), '/');
        }

        if (isset($new['as'])) {
            $merged['as'] = ($old['as'] ?? '').$new['as'];
        }

        if (isset($new['middleware'])) {
            $merged['middleware'] = array_values(array_unique([...(array) ($old['middleware'] ?? []), ...(array) $new['middleware']]));
        }

        if (isset($new['where'])) {
            $merged['where'] = [...(array) ($old['where'] ?? []), ...(array) $new['where']];
        }

        if (isset($new['controller'])) {
            $merged['controller'] = $new['controller'];
        }

        return $merged;
    }
}
