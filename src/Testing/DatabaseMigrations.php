<?php

declare(strict_types=1);

namespace Slendie\Testing;

use Slendie\Testing\Concerns\ManagesTestDatabase;

/**
 * Migra antes e faz rollback real das migrações depois de cada teste. Lento: use-o só quando precisar de testar as
 * próprias migrações ou o DDL; prefira o RefreshDatabase.
 */
trait DatabaseMigrations
{
    use ManagesTestDatabase;

    protected function runDatabaseMigrations(): void
    {
        $this->runConsoleOrFail('migrate:fresh', ['--force' => true]);

        $this->beforeApplicationDestroyed(function (): void {
            $this->runConsoleOrFail('migrate:reset', ['--force' => true]);
        });
    }
}
