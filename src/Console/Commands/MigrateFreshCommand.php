<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Console\Commands\Concerns\PreparesDatabase;
use Slendie\Database\DatabaseManager;
use Slendie\Database\Migrations\Migrator;
use Slendie\Foundation\Application;

final class MigrateFreshCommand extends Command
{
    use PreparesDatabase;

    protected string $signature = 'migrate:fresh
        {--seed : Corre o DatabaseSeeder no fim}
        {--force : Corre em produção sem pedir confirmação}';

    protected string $description = 'Apaga todas as tabelas e corre as migrações de novo';

    public function handle(Migrator $migrator, DatabaseManager $db, Application $app): int
    {
        if (! $this->confirmToProceed() || ! $this->prepareDatabase($app, $db)) {
            return self::FAILURE;
        }

        $this->badge('INFO', 'Dropping all tables.');

        $migrator->setOutput(fn (string $message) => $this->line($message));

        try {
            $migrator->fresh();
        } finally {
            $migrator->setOutput(null);
        }

        if ($this->option('seed') === true) {
            return $this->call('db:seed', ['--force' => true]);
        }

        return self::SUCCESS;
    }
}
