<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Support\Facade;

/**
 * @method static void directive(string $name, callable $handler)
 * @method static array<string, callable> getCustomDirectives()
 * @method static string compileString(string $value)
 * @method static int clearCache()
 *
 * @see \Slendie\View\Compiler\BladeCompiler
 */
class Blade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'blade.compiler';
    }
}
