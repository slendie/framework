<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Console\Application;
use Slendie\Support\Facade;

/**
 * @method static \Slendie\Console\ClosureCommand command(string $signature, \Closure $callback)
 * @method static int call(string $command, array<array-key, mixed> $params = [], ?\Slendie\Console\Output $out = null)
 * @method static \Slendie\Console\Application add(\Slendie\Console\Command|string $command)
 * @method static array<string, \Slendie\Console\Command> all()
 * @method static bool has(string $name)
 * @method static \Slendie\Console\Output output()
 *
 * @see \Slendie\Console\Application
 */
class Console extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Application::class;
    }
}
