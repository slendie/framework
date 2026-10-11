<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Container\Container;
use Slendie\Database\DatabaseManager;
use Slendie\Database\Schema\Builder;
use Slendie\Support\Facade;

/**
 * @method static void create(string $table, \Closure $callback)
 * @method static void table(string $table, \Closure $callback)
 * @method static void drop(string $table)
 * @method static void dropIfExists(string $table)
 * @method static void rename(string $from, string $to)
 * @method static void dropAllTables()
 * @method static bool hasTable(string $table)
 * @method static bool hasColumn(string $table, string $column)
 * @method static bool hasColumns(string $table, list<string> $columns)
 * @method static list<string> getColumnListing(string $table)
 * @method static list<array{name: string, type: string, nullable: bool, default: ?string}> getColumns(string $table)
 * @method static list<string> getTables()
 * @method static void enableForeignKeyConstraints()
 * @method static void disableForeignKeyConstraints()
 * @method static mixed withoutForeignKeyConstraints(\Closure $callback)
 * @method static \Slendie\Database\Connection getConnection()
 *
 * @see \Slendie\Database\Schema\Builder
 */
class Schema extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'db.schema';
    }

    /**
     * Schema de outra ligação: `Schema::connection('mysql')->hasTable('leads')`.
     */
    public static function connection(?string $name = null): Builder
    {
        $app = static::$app ?? Container::getInstance();

        /** @var DatabaseManager $manager */
        $manager = $app->make(DatabaseManager::class);

        return $manager->connection($name)->getSchemaBuilder();
    }
}
