<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Support\Facade;

/**
 * @method static \Slendie\View\View make(string $view, array<string, mixed> $data = [])
 * @method static string render(string $view, array<string, mixed> $data = [])
 * @method static bool exists(string $view)
 * @method static mixed share(string|array<string, mixed> $key, mixed $value = null)
 * @method static array<string, mixed> getShared()
 * @method static void composer(string|array<int, string> $views, \Closure|string $callback)
 * @method static \Slendie\View\Factory addNamespace(string $namespace, string|array<int, string> $paths)
 * @method static \Slendie\View\Factory prependNamespace(string $namespace, string|array<int, string> $paths)
 * @method static \Slendie\View\Factory addLocation(string $path)
 *
 * @see \Slendie\View\Factory
 */
class View extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'view';
    }
}
