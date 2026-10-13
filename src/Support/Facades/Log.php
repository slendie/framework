<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Support\Facade;

/**
 * @method static \Slendie\Log\Logger channel(?string $name = null)
 * @method static \Slendie\Log\Logger extend(string $driver, \Closure $factory)
 * @method static void emergency(string|\Stringable $message, array<string, mixed> $context = [])
 * @method static void alert(string|\Stringable $message, array<string, mixed> $context = [])
 * @method static void critical(string|\Stringable $message, array<string, mixed> $context = [])
 * @method static void error(string|\Stringable $message, array<string, mixed> $context = [])
 * @method static void warning(string|\Stringable $message, array<string, mixed> $context = [])
 * @method static void notice(string|\Stringable $message, array<string, mixed> $context = [])
 * @method static void info(string|\Stringable $message, array<string, mixed> $context = [])
 * @method static void debug(string|\Stringable $message, array<string, mixed> $context = [])
 * @method static void log(string $level, string|\Stringable $message, array<string, mixed> $context = [])
 *
 * @see \Slendie\Log\Logger
 */
class Log extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'log';
    }
}
