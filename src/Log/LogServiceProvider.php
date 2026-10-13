<?php

declare(strict_types=1);

namespace Slendie\Log;

use Slendie\Foundation\Application;
use Slendie\Foundation\ServiceProvider;
use Slendie\Support\Config;

/**
 * Regista o Logger (`log`), configurado por `config/logging.php`.
 */
class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/logging.php', 'logging');

        $this->app->singleton(Logger::class, function (Application $app): Logger {
            $config = $app->make(Config::class)->get('logging', []);

            /** @var array{default?: string, channels?: array<string, array<string, mixed>>} $config */
            $config = is_array($config) ? $config : [];

            return new Logger($app->storagePath(), static fn (): string => (string) $app->environment(), $config);
        });
        $this->app->alias(Logger::class, 'log');
    }

    public function boot(): void
    {
        $this->publishes([dirname(__DIR__, 2).'/config/logging.php' => $this->app->configPath('logging.php')], 'slendie-logging');
    }
}
