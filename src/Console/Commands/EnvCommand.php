<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Foundation\Application;

final class EnvCommand extends Command
{
    protected string $signature = 'env';

    protected string $description = 'Mostra o ambiente actual (APP_ENV)';

    public function handle(Application $app): int
    {
        $this->line('Current application environment: <info>'.$app->environment().'</info>');

        return self::SUCCESS;
    }
}
