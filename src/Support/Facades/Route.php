<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Support\Facade;

/**
 * @method static \Slendie\Routing\Route get(string $uri, \Closure|array<mixed>|string $action)
 * @method static \Slendie\Routing\Route post(string $uri, \Closure|array<mixed>|string $action)
 * @method static \Slendie\Routing\Route put(string $uri, \Closure|array<mixed>|string $action)
 * @method static \Slendie\Routing\Route patch(string $uri, \Closure|array<mixed>|string $action)
 * @method static \Slendie\Routing\Route delete(string $uri, \Closure|array<mixed>|string $action)
 * @method static \Slendie\Routing\Route options(string $uri, \Closure|array<mixed>|string $action)
 * @method static \Slendie\Routing\Route any(string $uri, \Closure|array<mixed>|string $action)
 * @method static \Slendie\Routing\Route match(array<mixed> $methods, string $uri, \Closure|array<mixed>|string $action)
 * @method static \Slendie\Routing\Route view(string $uri, string $view, array<mixed> $data = [])
 * @method static \Slendie\Routing\Route redirect(string $uri, string $destination, int $status = 302)
 * @method static \Slendie\Routing\Route permanentRedirect(string $uri, string $destination)
 * @method static \Slendie\Routing\Route fallback(\Closure|array<mixed>|string $action)
 * @method static array<mixed> resource(string $name, string $controller, array<mixed> $options = [])
 * @method static array<mixed> apiResource(string $name, string $controller, array<mixed> $options = [])
 * @method static void group(array<mixed> $attributes, \Closure|string $routes)
 * @method static \Slendie\Routing\RouteGroup prefix(string $prefix)
 * @method static \Slendie\Routing\RouteGroup middleware(string|array<mixed> ...$middleware)
 * @method static \Slendie\Routing\RouteGroup name(string $prefix)
 * @method static \Slendie\Routing\RouteGroup controller(string $controller)
 * @method static \Slendie\Routing\Route|null current()
 * @method static string|null currentRouteName()
 * @method static bool has(string $name)
 * @method static bool is(string ...$patterns)
 *
 * @see \Slendie\Routing\Router
 */
class Route extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'router';
    }
}
