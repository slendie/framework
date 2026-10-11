<?php

declare(strict_types=1);

namespace Slendie\Model;

use Closure;

/**
 * Registo simples de eventos por classe de model (sem observers em classe).
 *
 * @internal
 */
final class ModelEvents
{
    public const array NAMES = ['creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored'];

    /** @var array<class-string, array<string, list<Closure>>> */
    private static array $listeners = [];

    /**
     * @param  class-string  $class
     */
    public static function listen(string $class, string $event, Closure $callback): void
    {
        self::$listeners[$class][$event][] = $callback;
    }

    /**
     * Devolve false se algum listener devolver false (cancela os eventos `*ing`).
     *
     * @param  class-string  $class
     */
    public static function fire(string $class, string $event, Model $model): bool
    {
        // Os listeners registados num pai também correm nas subclasses.
        foreach ([...array_reverse(array_values(class_parents($class) ?: [])), $class] as $name) {
            foreach (self::$listeners[$name][$event] ?? [] as $listener) {
                if ($listener($model) === false) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function flush(): void
    {
        self::$listeners = [];
    }
}
