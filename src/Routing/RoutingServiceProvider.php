<?php

declare(strict_types=1);

namespace Slendie\Routing;

use Slendie\Foundation\ServiceProvider;
use Slendie\Http\Request;
use Slendie\Http\ResponseFactory;

/**
 * Regista Router, UrlGenerator & co. e carrega routes/web.php (grupo web) e routes/api.php (prefixo /api, grupo api).
 */
class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/cors.php', 'cors');

        // RouteCollection, Router e Kernel já são singletons desde o construtor da Application.

        $this->app->singleton(UrlGenerator::class);
        $this->app->alias(UrlGenerator::class, 'url');

        $this->app->singleton(Redirector::class);
        $this->app->alias(Redirector::class, 'redirect');

        $this->app->singleton(ResponseFactory::class);
        $this->app->alias(ResponseFactory::class, 'response');

        // Quem pedir um Request recebe o do pedido em curso.
        $this->app->bind(Request::class, fn () => $this->app->make('request'));
    }

    public function boot(Router $router): void
    {
        $paths = $this->app->bound('routes.files') ? $this->app->make('routes.files') : [];
        $paths = is_array($paths) ? $paths : [];

        $web = is_string($paths['web'] ?? null) ? $paths['web'] : $this->app->basePath('routes/web.php');
        $api = is_string($paths['api'] ?? null) ? $paths['api'] : $this->app->basePath('routes/api.php');

        if (is_file($web)) {
            $router->group(['middleware' => ['web']], $web);
        }

        if (is_file($api)) {
            $router->group(['prefix' => 'api', 'middleware' => ['api']], $api);
        }
    }
}
