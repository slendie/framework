<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Database\Migrations\Migrator;

final class MigrateStatusCommand extends Command
{
    protected string $signature = 'migrate:status {--pending : Mostra só as pendentes (código de saída 1 se existirem)}';

    protected string $description = 'Mostra o estado de cada migração';

    public function handle(Migrator $migrator): int
    {
        $pendingOnly = $this->option('pending') === true;
        $status = $migrator->status();

        if ($status === []) {
            $this->warn('No migrations found.');

            return self::SUCCESS;
        }

        $rows = [];
        $pending = 0;

        foreach ($status as $migration) {
            if (! $migration['ran']) {
                $pending++;
            } elseif ($pendingOnly) {
                continue;
            }

            $rows[] = [
                $migration['migration'],
                $migration['batch'] === null ? '' : (string) $migration['batch'],
                $migration['ran'] ? '<info>Ran</info>' : '<comment>Pending</comment>',
            ];
        }

        if ($rows === []) {
            $this->info('No pending migrations.');

            return self::SUCCESS;
        }

        $this->table(['Migration', 'Batch', 'Status'], $rows);

        return $pendingOnly && $pending > 0 ? self::FAILURE : self::SUCCESS;
    }
}
