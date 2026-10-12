<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Console\Commands\Concerns\PreparesDatabase;
use Slendie\Database\DatabaseManager;
use Slendie\Database\Migrations\Migrator;
use Slendie\Foundation\Application;

final class MigrateCommand extends Command
{
    use PreparesDatabase;

    protected string $signature = 'migrate
        {--seed : Corre o DatabaseSeeder depois de migrar}
        {--step : Um batch por migração (permite reverter uma a uma)}
        {--pretend : Mostra o SQL sem o executar}
        {--force : Corre em produção sem pedir confirmação}';

    protected string $description = 'Corre as migrações pendentes';

    public function handle(Migrator $migrator, DatabaseManager $db, Application $app): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $pretend = $this->option('pretend') === true;

        if (! $pretend && ! $this->prepareDatabase($app, $db)) {
            return self::FAILURE;
        }

        $migrator->setOutput(fn (string $message) => $this->line($message));

        try {
            $ran = $migrator->run(['pretend' => $pretend, 'step' => $this->option('step') === true]);
        } finally {
            $migrator->setOutput(null);
        }

        if ($pretend) {
            foreach ($ran as $sql) {
                $this->line($sql);
            }

            return self::SUCCESS;
        }

        if ($this->option('seed') === true) {
            return $this->call('db:seed', ['--force' => true]);
        }

        return self::SUCCESS;
    }
}
