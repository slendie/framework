<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Http;

use Closure;
use Slendie\Foundation\Application;
use Slendie\Foundation\Bootstrap\BootProviders;
use Slendie\Foundation\Bootstrap\LoadConfiguration;
use Slendie\Foundation\Bootstrap\LoadEnvironment;
use Slendie\Foundation\Bootstrap\RegisterProviders;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Routing\Router;

/**
 * Cria uma Application de teste (sem tocar em handlers globais do PHP) e envia pedidos através dela.
 */
final class HttpHelpers
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function app(array $config = []): Application
    {
        $app = new Application(fixturePath('http'));
        $app->useStoragePath(sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-test-'.bin2hex(random_bytes(6)));

        $app->bootstrapWith([LoadEnvironment::class, LoadConfiguration::class, RegisterProviders::class, BootProviders::class]);

        if ($config !== []) {
            config($config);
        }

        return $app;
    }

    /** Cria a excepção noutro ficheiro, para o excerto de código da página de debug não mostrar o teste. */
    public static function exception(string $message = 'x'): \RuntimeException
    {
        return new \RuntimeException($message);
    }

    public static function router(Application $app): Router
    {
        return $app->make(Router::class);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $server
     * @param  array<string, string>  $cookies
     */
    public static function send(Application $app, string $method, string $uri, array $parameters = [], array $server = [], array $cookies = []): Response
    {
        $request = Request::create($uri, $method, $parameters, $cookies, [], $server);

        return $app->handle($request);
    }

    public static function run(Application $app, Closure $routes): Router
    {
        $router = self::router($app);
        $routes($router);

        return $router;
    }
}
