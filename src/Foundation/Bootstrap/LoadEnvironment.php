<?php

declare(strict_types=1);

namespace Slendie\Foundation\Bootstrap;

use Slendie\Foundation\Application;
use Slendie\Support\Env;

/**
 * Carrega {basePath}/.env e, se existir, .env.{APP_ENV} com prioridade.
 */
final class LoadEnvironment
{
    public function bootstrap(Application $app): void
    {
        $base = $app->environmentFilePath();

        // Primeiro o ficheiro específico do ambiente (se APP_ENV já vier do servidor).
        $env = Env::get('APP_ENV');

        if (is_string($env) && $env !== '' && is_file($base.'.'.$env)) {
            Env::load($base.'.'.$env);
        }

        Env::load($base);

        // Se o .env definiu APP_ENV e existir .env.{APP_ENV}, carrega-o por cima.
        $envAfter = Env::get('APP_ENV');

        if (is_string($envAfter) && $envAfter !== '' && $envAfter !== $env && is_file($base.'.'.$envAfter)) {
            Env::load($base.'.'.$envAfter, overwrite: true);
        }
    }
}
