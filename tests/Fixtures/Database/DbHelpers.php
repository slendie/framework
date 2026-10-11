<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Database;

use LogicException;
use PDO;
use Slendie\Database\Connection;
use Slendie\Database\Query\Builder;
use Slendie\Database\Query\Grammar;
use Slendie\Database\Query\Grammars\MySqlGrammar;
use Slendie\Database\Query\Grammars\PgsqlGrammar;
use Slendie\Database\Query\Grammars\SqliteGrammar;

/**
 * Ligações de teste: sem BD (só para gerar SQL) ou reais (SQLite em memória; MySQL/PostgreSQL via env).
 */
final class DbHelpers
{
    public static function grammar(string $driver): Grammar
    {
        return match ($driver) {
            'sqlite' => new SqliteGrammar(),
            'mysql' => new MySqlGrammar(),
            'pgsql' => new PgsqlGrammar(),
        };
    }

    /** Connection que nunca liga (o PDO é uma closure que falha). */
    public static function offline(string $driver = 'sqlite'): Connection
    {
        return new Connection(
            fn (): PDO => throw new LogicException('Teste sem base de dados.'),
            $driver,
            'test',
            [],
            self::grammar($driver),
        );
    }

    public static function builder(string $driver = 'sqlite', ?string $table = null): Builder
    {
        $builder = new Builder(self::offline($driver));

        return $table === null ? $builder : $builder->from($table);
    }

    /** @return list<string> */
    public static function drivers(): array
    {
        return ['sqlite', 'mysql', 'pgsql'];
    }

    public static function dsnVariable(string $driver): string
    {
        return 'DB_TEST_'.strtoupper($driver).'_DSN';
    }

    /**
     * Connection real, ou null se o driver não estiver disponível neste ambiente.
     */
    public static function live(string $driver): ?Connection
    {
        if ($driver === 'sqlite') {
            return new Connection(self::pdo('sqlite::memory:'), 'sqlite', ':memory:', [], new SqliteGrammar());
        }

        $dsn = getenv(self::dsnVariable($driver));

        if (! is_string($dsn) || $dsn === '' || ! in_array($driver, PDO::getAvailableDrivers(), true)) {
            return null;
        }

        $user = getenv('DB_TEST_'.strtoupper($driver).'_USER');
        $password = getenv('DB_TEST_'.strtoupper($driver).'_PASSWORD');

        return new Connection(
            self::pdo($dsn, is_string($user) ? $user : null, is_string($password) ? $password : null),
            $driver,
            'test',
            [],
            self::grammar($driver),
        );
    }

    public static function skipReason(string $driver): string
    {
        return 'Define '.self::dsnVariable($driver).' (e instala pdo_'.$driver.') para correr este teste.';
    }

    /**
     * Cria (do zero) as tabelas `leads` e `notes`.
     */
    public static function createTables(Connection $connection): void
    {
        $id = match ($connection->getDriverName()) {
            'sqlite' => 'integer primary key autoincrement',
            'mysql' => 'int auto_increment primary key',
            default => 'serial primary key',
        };

        $connection->statement('drop table if exists notes');
        $connection->statement('drop table if exists leads');
        $timestamp = $connection->getDriverName() === 'pgsql' ? 'timestamp' : 'datetime';

        $connection->statement("create table leads (id {$id}, name varchar(100) not null, email varchar(100) null unique, votes int not null default 0, status varchar(20) null, created_at {$timestamp} null)");
        $connection->statement("create table notes (id {$id}, lead_id int not null, body varchar(200) not null)");
    }

    public static function dropTables(Connection $connection): void
    {
        $connection->statement('drop table if exists notes');
        $connection->statement('drop table if exists leads');
    }

    private static function pdo(string $dsn, ?string $user = null, ?string $password = null): PDO
    {
        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }
}
