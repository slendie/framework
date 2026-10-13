<?php

declare(strict_types=1);

namespace Acme\Blog;

use Slendie\Console\Command;

final class HelloCommand extends Command
{
    protected string $signature = 'acme:hello';

    protected string $description = 'Diz olá a partir do package Acme';

    public function handle(): int
    {
        $this->info('Hello from Acme');

        return self::SUCCESS;
    }
}
