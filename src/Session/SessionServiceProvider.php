<?php

declare(strict_types=1);

namespace Slendie\Session;

use SessionHandlerInterface;
use Slendie\Foundation\ServiceProvider;
use Slendie\Support\Config;

/**
 * Lê config/session.php e regista o handler (por defeito, ficheiros). O Session em si é criado
 * por pedido pelo middleware StartSession.
 */
class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/session.php', 'session');

        $this->app->singleton('session.handler', function (): SessionHandlerInterface {
            /** @var Config $config */
            $config = $this->app->make(Config::class);

            $lifetime = $config->get('session.lifetime', 120);
            $driver = $config->get('session.driver', 'file');

            if ($driver === 'array') {
                return new ArraySessionHandler();
            }

            $files = $config->get('session.files');

            return new FileSessionHandler(
                is_string($files) && $files !== '' ? $files : $this->app->storagePath('framework/sessions'),
                is_numeric($lifetime) ? (int) $lifetime : 120,
            );
        });

        $this->app->alias('session.handler', SessionHandlerInterface::class);
    }
}
