<?php

declare(strict_types=1);

namespace Slendie\Database;

use Slendie\Database\Migrations\MigrationCreator;
use Slendie\Database\Migrations\MigrationRepository;
use Slendie\Database\Migrations\Migrator;
use Slendie\Database\Pagination\AbstractPaginator;
use Slendie\Database\Schema\Builder as SchemaBuilder;
use Slendie\Foundation\Application;
use Slendie\Foundation\ServiceProvider;
use Slendie\Http\Request;
use Slendie\Model\Model;
use Slendie\Support\Config;
use Slendie\View\Factory;

/**
 * Regista o DatabaseManager (`db`), a Connection por omissão, o Schema (`db.schema`), o Migrator e liga a paginação ao pedido actual.
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

        // Não partilhado: o Schema:: segue sempre a ligação por omissão (que o Migrator pode trocar).
        $this->app->bind('db.schema', fn (Application $app): SchemaBuilder => $app->make(DatabaseManager::class)->connection()->getSchemaBuilder());

        $this->app->singleton(MigrationRepository::class, function (Application $app): MigrationRepository {
            $table = $app->make(Config::class)->get('database.migrations', 'migrations');

            return new MigrationRepository($app->make(DatabaseManager::class), is_string($table) && $table !== '' ? $table : 'migrations');
        });

        $this->app->singleton(Migrator::class, fn (Application $app): Migrator => new Migrator(
            $app->make(MigrationRepository::class),
            $app->make(DatabaseManager::class),
            [$app->databasePath('migrations')],
        ));

        $this->app->singleton(MigrationCreator::class, fn (): MigrationCreator => new MigrationCreator());
    }

    public function boot(): void
    {
        // Só quando as views forem usadas (e só se existirem): a paginação não depende do Blade.
        $this->callAfterResolving(Factory::class, function (Factory $views): void {
            $views->addNamespace('pagination', dirname(__DIR__, 2).'/resources/views/pagination');
        });

        $app = $this->app;

        Model::setConnectionResolver(static fn (): DatabaseManager => $app->make(DatabaseManager::class));

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
