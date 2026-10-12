<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Database\Migrations\MigrationRepository;

final class MigrateInstallCommand extends Command
{
    protected string $signature = 'migrate:install';

    protected string $description = 'Cria a tabela onde as migrações ficam registadas';

    public function handle(MigrationRepository $repository): int
    {
        if ($repository->repositoryExists()) {
            $this->warn("Migration table [{$repository->getTable()}] already exists.");

            return self::SUCCESS;
        }

        $repository->createRepository();

        $this->badge('INFO', 'Migration table created successfully.');

        return self::SUCCESS;
    }
}
