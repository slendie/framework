<?php

declare(strict_types=1);

namespace Slendie\Database;

use Slendie\Database\Pagination\AbstractPaginator;
use Slendie\Foundation\Application;
use Slendie\Foundation\ServiceProvider;
use Slendie\Http\Request;
use Slendie\Support\Config;
use Slendie\View\Factory;

/**
 * Regista o DatabaseManager (`db`), a Connection por omissão e liga a paginação ao pedido actual.
 */
class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/database.php', 'database');

        $this->app->singleton(ConnectionFactory::class, fn (Application $app): ConnectionFactory => new ConnectionFactory($app->databasePath()));

        $this->app->singleton(DatabaseManager::class, function (Application $app): DatabaseManager {
            $config = $app->make(Config::class)->get('database', []);

            /** @var array<string, mixed> $config */
            $config = is_array($config) ? $config : [];

            return new DatabaseManager($app, $config, $app->make(ConnectionFactory::class));
        });
        $this->app->alias(DatabaseManager::class, 'db');

        $this->app->bind(Connection::class, fn (Application $app): Connection => $app->make(DatabaseManager::class)->connection());
    }

    public function boot(): void
    {
        // Só quando as views forem usadas (e só se existirem): a paginação não depende do Blade.
        $this->callAfterResolving(Factory::class, function (Factory $views): void {
            $views->addNamespace('pagination', dirname(__DIR__, 2).'/resources/views/pagination');
        });

        $app = $this->app;

        AbstractPaginator::currentPageResolver(function (string $pageName) use ($app): int {
            $page = $app->bound('request') ? $this->request($app)->query($pageName, 1) : ($_GET[$pageName] ?? 1);

            return is_numeric($page) ? max(1, (int) $page) : 1;
        });

        AbstractPaginator::currentPathResolver(fn (): string => $app->bound('request') ? $this->request($app)->path() : '/');

        AbstractPaginator::queryStringResolver(function () use ($app): array {
            if (! $app->bound('request')) {
                return [];
            }

            $query = $this->request($app)->query();

            /** @var array<string, mixed> */
            return is_array($query) ? $query : [];
        });
    }

    protected function request(Application $app): Request
    {
        /** @var Request */
        return $app->make('request');
    }
}
