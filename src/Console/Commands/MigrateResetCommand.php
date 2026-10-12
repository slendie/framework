<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Database\Migrations\Migrator;

final class MigrateResetCommand extends Command
{
    protected string $signature = 'migrate:reset
        {--pretend : Mostra o SQL sem o executar}
        {--force : Corre em produção sem pedir confirmação}';

    protected string $description = 'Reverte todas as migrações';

    public function handle(Migrator $migrator): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $pretend = $this->option('pretend') === true;
        $migrator->setOutput(fn (string $message) => $this->line($message));

        try {
            $rolled = $migrator->reset(['pretend' => $pretend]);
        } finally {
            $migrator->setOutput(null);
        }

        if ($pretend) {
            foreach ($rolled as $sql) {
                $this->line($sql);
            }
        }

        return self::SUCCESS;
    }
}
