<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Database\Migrations\Migrator;

final class MigrateRollbackCommand extends Command
{
    protected string $signature = 'migrate:rollback
        {--step=1 : Número de batches a reverter}
        {--batch= : Reverte um batch concreto}
        {--pretend : Mostra o SQL sem o executar}
        {--force : Corre em produção sem pedir confirmação}';

    protected string $description = 'Reverte o último batch de migrações';

    public function handle(Migrator $migrator): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $step = $this->option('step');
        $batch = $this->option('batch');

        if (! is_string($step) || ! ctype_digit($step) || $step === '0' || ($batch !== null && (! is_string($batch) || ! ctype_digit($batch)))) {
            $this->error('As opções --step e --batch têm de ser inteiros positivos.');

            return self::INVALID;
        }

        $pretend = $this->option('pretend') === true;
        $migrator->setOutput(fn (string $message) => $this->line($message));

        try {
            $rolled = $migrator->rollback([
                'step' => (int) $step,
                'batch' => $batch === null ? null : (int) $batch,
                'pretend' => $pretend,
            ]);
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
