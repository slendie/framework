<?php

declare(strict_types=1);

namespace Slendie\Testing;

use Slendie\Database\Connection;

/**
 * Estado partilhado entre testes (e entre aplicações) do mesmo processo: as ligações já migradas.
 * Fica numa classe própria porque as propriedades estáticas de um trait seriam uma cópia por classe de teste.
 *
 * @internal
 */
final class DatabaseState
{
    /** @var array<string, Connection> */
    private static array $connections = [];

    public static function get(string $key): ?Connection
    {
        return self::$connections[$key] ?? null;
    }

    public static function put(string $key, Connection $connection): void
    {
        self::$connections[$key] = $connection;
    }

    public static function flush(): void
    {
        self::$connections = [];
    }
}
