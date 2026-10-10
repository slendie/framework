<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Providers;

use Slendie\Foundation\ServiceProvider;

final class ConfigListedProvider extends ServiceProvider
{
    public function register(): void
    {
        FixtureServiceProvider::$log[] = 'config-listed:register';
    }

    public function boot(): void
    {
        FixtureServiceProvider::$log[] = 'config-listed:boot';
    }
}
