<?php

declare(strict_types=1);

namespace Slendie\Foundation\Bootstrap;

use Slendie\Foundation\Application;
use Slendie\Support\Config;
use Slendie\Support\Env;

/**
 * Carrega config/*.php para o repositório Config e aplica definições globais (timezone).
 */
final class LoadConfiguration
{
    public function bootstrap(Application $app): void
    {
        $config = new Config();
        $config->loadFromDirectory($app->configPath());

        // Garante chaves mínimas de app.* mesmo sem config/app.php.
        $defaults = [
            'app.name' => Env::get('APP_NAME', 'Slendie'),
            'app.env' => Env::get('APP_ENV', 'production'),
            'app.debug' => filter_var(Env::get('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
            'app.url' => Env::get('APP_URL', 'http://localhost'),
            'app.timezone' => Env::get('APP_TIMEZONE', 'UTC'),
            'app.locale' => Env::get('APP_LOCALE', 'pt'),
            'app.fallback_locale' => 'en',
            'app.key' => Env::get('APP_KEY'),
            'app.providers' => [],
        ];

        foreach ($defaults as $key => $value) {
            if (! $config->has($key)) {
                $config->set($key, $value);
            }
        }

        $app->instance(Config::class, $config);
        $app->alias(Config::class, 'config');

        $timezone = $config->get('app.timezone', 'UTC');
        date_default_timezone_set(is_string($timezone) && $timezone !== '' ? $timezone : 'UTC');

        mb_internal_encoding('UTF-8');
    }
}
