<?php

declare(strict_types=1);

namespace Slendie\Testing\Concerns;

use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use Slendie\Database\Connection;
use Slendie\Database\DatabaseManager;
use Slendie\Database\Query\Builder;
use Slendie\Database\Seeders\Seeder;
use Slendie\Model\Model;

/**
 * Asserções sobre o estado da base de dados e `seed()`.
 *
 * @phpstan-require-extends \Slendie\Testing\TestCase
 */
trait InteractsWithDatabase
{
    /**
     * @param  array<string, mixed>  $data  colunas => valores (null = IS NULL)
     */
    protected function assertDatabaseHas(string $table, array $data, ?string $connection = null): static
    {
        Assert::assertTrue(
            $this->matching($table, $data, $connection)->exists(),
            $this->databaseFailure("Failed asserting that a row in the table [{$table}] matches the attributes", $table, $data, $connection),
        );

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function assertDatabaseMissing(string $table, array $data, ?string $connection = null): static
    {
        Assert::assertFalse(
            $this->matching($table, $data, $connection)->exists(),
            $this->databaseFailure("Failed asserting that no row in the table [{$table}] matches the attributes", $table, $data, $connection),
        );

        return $this;
    }

    protected function assertDatabaseCount(string $table, int $count, ?string $connection = null): static
    {
        $actual = $this->db($connection)->table($table)->count();

        Assert::assertSame($count, $actual, "Failed asserting that the table [{$table}] has {$count} rows (it has {$actual}).");

        return $this;
    }

    protected function assertDatabaseEmpty(string $table, ?string $connection = null): static
    {
        return $this->assertDatabaseCount($table, 0, $connection);
    }

    protected function assertModelExists(Model $model): static
    {
        return $this->assertDatabaseHas($model->getTable(), [$model->getKeyName() => $model->getKey()], $model->getConnectionName());
    }

    protected function assertModelMissing(Model $model): static
    {
        return $this->assertDatabaseMissing($model->getTable(), [$model->getKeyName() => $model->getKey()], $model->getConnectionName());
    }

    /**
     * Linha com `deleted_at` preenchido. Aceita um model ou uma tabela + colunas.
     *
     * @param  Model|string  $table
     * @param  array<string, mixed>  $data
     */
    protected function assertSoftDeleted(Model|string $table, array $data = [], ?string $connection = null, string $deletedAtColumn = 'deleted_at'): static
    {
        [$table, $data, $connection, $column] = $this->softDeleteTarget($table, $data, $connection, $deletedAtColumn);

        Assert::assertTrue(
            $this->matching($table, $data, $connection)->whereNotNull($column)->exists(),
            $this->databaseFailure("Failed asserting that a soft-deleted row in the table [{$table}] matches the attributes", $table, $data, $connection),
        );

        return $this;
    }

    /**
     * @param  Model|string  $table
     * @param  array<string, mixed>  $data
     */
    protected function assertNotSoftDeleted(Model|string $table, array $data = [], ?string $connection = null, string $deletedAtColumn = 'deleted_at'): static
    {
        [$table, $data, $connection, $column] = $this->softDeleteTarget($table, $data, $connection, $deletedAtColumn);

        Assert::assertTrue(
            $this->matching($table, $data, $connection)->whereNull($column)->exists(),
            $this->databaseFailure("Failed asserting that a row (not soft-deleted) in the table [{$table}] matches the attributes", $table, $data, $connection),
        );

        return $this;
    }

    /**
     * Corre seeders: sem argumento, o `Database\Seeders\DatabaseSeeder`.
     *
     * @param  string|null  $class  nome completo de uma classe que estenda Seeder
     */
    protected function seed(?string $class = null): static
    {
        $class ??= 'Database\\Seeders\\DatabaseSeeder';

        if (! class_exists($class) || ! is_subclass_of($class, Seeder::class)) {
            throw new InvalidArgumentException("[{$class}] não é um seeder (classe inexistente ou não estende ".Seeder::class.').');
        }

        $seeder = $this->app->make($class);
        $seeder->setContainer($this->app);
        $seeder();

        return $this;
    }

    // -------------------------------------------------------------------------

    protected function db(?string $connection = null): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection($connection);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function matching(string $table, array $data, ?string $connection): Builder
    {
        $query = $this->db($connection)->table($table);

        foreach ($data as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, '=', $value);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function databaseFailure(string $message, string $table, array $data, ?string $connection): string
    {
        $connectionObject = $this->db($connection);
        $total = $connectionObject->table($table)->count();
        $sample = $connectionObject->table($table)->limit(3)->get();

        return $message.': '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)
            .($total === 0
                ? '. The table is empty.'
                : ". Found {$total} row(s)".($total > 3 ? ', first 3' : '').': '.json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR).'.');
    }

    /**
     * @param  Model|string  $table
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: array<string, mixed>, 2: ?string, 3: string}
     */
    private function softDeleteTarget(Model|string $table, array $data, ?string $connection, string $column): array
    {
        if ($table instanceof Model) {
            $model = $table;

            if (method_exists($model, 'getDeletedAtColumn')) {
                $column = (string) $model->getDeletedAtColumn();
            }

            return [$model->getTable(), [$model->getKeyName() => $model->getKey()], $model->getConnectionName(), $column];
        }

        return [$table, $data, $connection, $column];
    }
}
