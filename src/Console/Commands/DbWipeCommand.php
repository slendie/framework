<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Database\Connection;

final class DbWipeCommand extends Command
{
    protected string $signature = 'db:wipe {--force : Corre em produção sem pedir confirmação}';

    protected string $description = 'Apaga todas as tabelas';

    public function handle(Connection $connection): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $connection->getSchemaBuilder()->dropAllTables();

        $this->badge('INFO', 'Dropped all tables successfully.');

        return self::SUCCESS;
    }
}
