<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Support\Facade;

/**
 * @method static \Slendie\Database\Connection connection(?string $name = null)
 * @method static void purge(?string $name = null)
 * @method static \Slendie\Database\Connection reconnect(?string $name = null)
 * @method static string getDefaultConnection()
 * @method static void setDefaultConnection(string $name)
 * @method static void extend(string $driver, \Closure $resolver)
 * @method static \Slendie\Database\Query\Builder table(string $table, ?string $as = null)
 * @method static \Slendie\Database\Statement query(string $sql, array<array-key, mixed> $bindings = [])
 * @method static list<array<string, mixed>> select(string $sql, array<array-key, mixed> $bindings = [])
 * @method static array<string, mixed>|null selectOne(string $sql, array<array-key, mixed> $bindings = [])
 * @method static mixed scalar(string $sql, array<array-key, mixed> $bindings = [])
 * @method static bool insert(string $sql, array<array-key, mixed> $bindings = [])
 * @method static int update(string $sql, array<array-key, mixed> $bindings = [])
 * @method static int delete(string $sql, array<array-key, mixed> $bindings = [])
 * @method static bool statement(string $sql, array<array-key, mixed> $bindings = [])
 * @method static int affectingStatement(string $sql, array<array-key, mixed> $bindings = [])
 * @method static bool unprepared(string $sql)
 * @method static string|int lastInsertId(?string $sequence = null)
 * @method static mixed transaction(\Closure $callback, int $attempts = 1)
 * @method static void beginTransaction()
 * @method static void commit()
 * @method static void rollBack()
 * @method static int transactionLevel()
 * @method static \Slendie\Database\Query\Expression raw(string|int|float $value)
 * @method static void enableQueryLog()
 * @method static void disableQueryLog()
 * @method static list<array{sql: string, bindings: list<mixed>, time_ms: float}> getQueryLog()
 * @method static void flushQueryLog()
 * @method static void listen(\Closure $callback)
 * @method static list<array{sql: string, bindings: list<mixed>, time_ms: float}> pretend(\Closure $callback)
 *
 * @see \Slendie\Database\DatabaseManager
 * @see \Slendie\Database\Connection
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'db';
    }
}
