<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Support\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void put(string|array<mixed> $key, mixed $value = null)
 * @method static bool has(string $key)
 * @method static void forget(string $key)
 * @method static void flash(string $key, mixed $value = true)
 * @method static string token()
 * @method static bool regenerate(bool $destroy = false)
 * @method static bool invalidate()
 *
 * @see \Slendie\Session\Session
 */
class Session extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'session';
    }
}
