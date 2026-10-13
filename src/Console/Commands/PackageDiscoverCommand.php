<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Foundation\Application;
use Slendie\Foundation\PackageDiscovery;

final class PackageDiscoverCommand extends Command
{
    protected string $signature = 'package:discover';

    protected string $description = 'Descobre os packages Slendie instalados e guarda o resultado em bootstrap/cache/packages.php';

    public function handle(Application $app, PackageDiscovery $discovery): int
    {
        $manifest = $discovery->discover($app->basePath('vendor'));

        $discovery->write($app->bootstrapPath('cache/packages.php'), $manifest);

        foreach (array_keys($manifest['packages']) as $package) {
            $this->line("  <info>Discovered Package:</info> {$package}");
        }

        if ($manifest['packages'] === []) {
            $this->line('  <muted>No packages to discover.</muted>');
        }

        $this->info('Package manifest generated successfully.');

        return self::SUCCESS;
    }
}
