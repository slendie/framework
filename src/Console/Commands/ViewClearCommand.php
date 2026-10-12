<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Console\Output;
use Slendie\Foundation\Application;
use Slendie\Support\Config;

final class ViewClearCommand extends Command
{
    protected string $signature = 'view:clear';

    protected string $description = 'Apaga as views Blade compiladas';

    public function handle(Application $app, Config $config): int
    {
        $compiled = $config->get('view.compiled');
        $directory = is_string($compiled) && $compiled !== '' ? $compiled : $app->storagePath('framework/views');

        $removed = 0;

        foreach (glob(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $removed++;
            }
        }

        $this->badge('INFO', 'Compiled views cleared successfully.');
        $this->line("{$removed} file".($removed === 1 ? '' : 's').' removed.', 'muted', Output::VERBOSITY_VERBOSE);

        return self::SUCCESS;
    }
}
