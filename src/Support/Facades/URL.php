<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Support\Facade;

/**
 * @method static string to(string $path, array<mixed> $extra = [], ?bool $secure = null)
 * @method static string route(string $name, array<mixed> $parameters = [], bool $absolute = true)
 * @method static string asset(string $path, ?bool $secure = null)
 * @method static string current()
 * @method static string full()
 * @method static string previous(string|false $fallback = false)
 *
 * @see \Slendie\Routing\UrlGenerator
 */
class URL extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'url';
    }
}
