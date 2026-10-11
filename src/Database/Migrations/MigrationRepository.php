<?php

declare(strict_types=1);

namespace Slendie\Database\Migrations;

use Slendie\Database\Connection;
use Slendie\Database\DatabaseManager;
use Slendie\Database\Query\Builder as QueryBuilder;
use Slendie\Database\Schema\Blueprint;

/**
 * A tabela `migrations`: que migrações já correram e em que batch.
 */
class MigrationRepository
{
    protected ?string $source = null;

    public function __construct(protected DatabaseManager $manager, protected string $table = 'migrations')
    {
    }

    /** Escolhe a ligação (null = por omissão). */
    public function setSource(?string $name): void
    {
        $this->source = $name;
    }

    public function getConnection(): Connection
    {
        return $this->manager->connection($this->source);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function createRepository(): void
    {
        $this->getConnection()->getSchemaBuilder()->create($this->table, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('migration')->unique();
            $table->integer('batch');
        });
    }

    public function repositoryExists(): bool
    {
        return $this->getConnection()->getSchemaBuilder()->hasTable($this->table);
    }

    public function deleteRepository(): void
    {
        $this->getConnection()->getSchemaBuilder()->dropIfExists($this->table);
    }

    /**
     * Nomes das migrações executadas, pela ordem em que correram.
     *
     * @return list<string>
     */
    public function getRan(): array
    {
        return array_values(array_map(strval(...), $this->query()->orderBy('batch')->orderBy('migration')->pluck('migration')));
    }

    /**
     * @return array<string, int> migração => batch
     */
    public function getBatches(): array
    {
        $batches = [];

        foreach ($this->query()->orderBy('batch')->orderBy('migration')->get(['migration', 'batch']) as $row) {
            $batches[(string) $row['migration']] = (int) $row['batch'];
        }

        return $batches;
    }

    /**
     * As migrações dos últimos `$steps` batches, da mais recente para a mais antiga.
     *
     * @return list<array{migration: string, batch: int}>
     */
    public function getMigrations(int $steps): array
    {
        $batches = $this->query()->distinct()->orderBy('batch', 'desc')->limit(max(1, $steps))->pluck('batch');

        if ($batches === []) {
            return [];
        }

        return $this->rows($this->query()->whereIn('batch', array_values($batches)));
    }

    /**
     * @return list<array{migration: string, batch: int}>
     */
    public function getMigrationsByBatch(int $batch): array
    {
        return $this->rows($this->query()->where('batch', $batch));
    }

    /**
     * As migrações do último batch.
     *
     * @return list<array{migration: string, batch: int}>
     */
    public function getLast(): array
    {
        return $this->getMigrationsByBatch($this->getLastBatchNumber());
    }

    public function log(string $migration, int $batch): void
    {
        $this->query()->insert(['migration' => $migration, 'batch' => $batch]);
    }

    public function delete(string $migration): void
    {
        $this->query()->where('migration', $migration)->delete();
    }

    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    public function getLastBatchNumber(): int
    {
        $max = $this->query()->max('batch');

        return is_numeric($max) ? (int) $max : 0;
    }

    protected function query(): QueryBuilder
    {
        return $this->getConnection()->table($this->table);
    }

    /**
     * @return list<array{migration: string, batch: int}>
     */
    protected function rows(QueryBuilder $query): array
    {
        $rows = [];

        foreach ($query->orderBy('batch', 'desc')->orderBy('migration', 'desc')->get(['migration', 'batch']) as $row) {
            $rows[] = ['migration' => (string) $row['migration'], 'batch' => (int) $row['batch']];
        }

        return $rows;
    }
}
