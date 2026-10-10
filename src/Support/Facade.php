<?php

declare(strict_types=1);

namespace Slendie\Support;

use RuntimeException;
use Slendie\Container\Container;

/**
 * Açúcar estático sobre serviços do container: Route::get(), Schema::create(), DB::table()...
 *
 * As subclasses implementam getFacadeAccessor() devolvendo a chave/classe registada no container.
 */
abstract class Facade
{
    protected static ?Container $app = null;

    /** @var array<string, mixed> */
    protected static array $resolvedInstances = [];

    abstract protected static function getFacadeAccessor(): string;

    public static function getFacadeRoot(): mixed
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    public static function setFacadeApplication(?Container $app): void
    {
        static::$app = $app;
    }

    public static function getFacadeApplication(): ?Container
    {
        return static::$app;
    }

    public static function clearResolvedInstance(string $name): void
    {
        unset(static::$resolvedInstances[$name]);
    }

    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstances = [];
    }

    /**
     * Substitui a instância resolvida (útil em testes).
     */
    public static function swap(mixed $instance): void
    {
        static::$resolvedInstances[static::getFacadeAccessor()] = $instance;

        static::$app?->instance(static::getFacadeAccessor(), $instance);
    }

    protected static function resolveFacadeInstance(string $name): mixed
    {
        if (isset(static::$resolvedInstances[$name])) {
            return static::$resolvedInstances[$name];
        }

        $app = static::$app ?? Container::getInstance();

        if (! $app->bound($name) && ! class_exists($name)) {
            throw new RuntimeException('A facade ['.static::class."] não encontrou [{$name}] no container. A aplicação arrancou?");
        }

        $instance = $app->make($name);

        if ($app->isShared($name)) {
            static::$resolvedInstances[$name] = $instance;
        }

        return $instance;
    }

    /**
     * @param  array<int, mixed>  $args
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();

        if (! is_object($instance)) {
            throw new RuntimeException('A facade ['.static::class.'] não resolveu para um objecto.');
        }

        return $instance->$method(...$args);
    }
}
