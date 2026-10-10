<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Providers;

use Slendie\Foundation\ServiceProvider;
use Slendie\Support\Config;
use Slendie\Tests\Fixtures\Greeter;
use Slendie\Tests\Fixtures\GreeterContract;

final class FixtureServiceProvider extends ServiceProvider
{
    /** @var list<string> */
    public static array $log = [];

    public function register(): void
    {
        self::$log[] = 'register';
        $this->app->singleton(GreeterContract::class, Greeter::class);
        $this->mergeConfigFrom(__DIR__.'/../config/fixture.php', 'fixture');
    }

    public function boot(Config $config): void
    {
        self::$log[] = 'boot:'.$config->get('app.name');
    }
}
