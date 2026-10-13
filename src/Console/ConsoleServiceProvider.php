<?php

declare(strict_types=1);

namespace Slendie\Console;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Slendie\Foundation\ServiceProvider;
use SplFileInfo;

/**
 * Regista os comandos da framework, descobre os de `app/Console/Commands`, carrega `routes/console.php`
 * e torna os stubs publicáveis (`vendor:publish --tag=slendie-stubs`).
 *
 * A `Console\Application` é um singleton registado pela própria `Foundation\Application`
 * (o script `slendie` resolve-a antes do bootstrap).
 */
class ConsoleServiceProvider extends ServiceProvider
{
    /** @var list<class-string<Command>> */
    public const array COMMANDS = [
        Commands\AboutCommand::class,
        Commands\AiContextCommand::class,
        Commands\DbSeedCommand::class,
        Commands\DbWipeCommand::class,
        Commands\EnvCommand::class,
        Commands\KeyGenerateCommand::class,
        Commands\MakeCommandCommand::class,
        Commands\MakeControllerCommand::class,
        Commands\MakeMiddlewareCommand::class,
        Commands\MakeMigrationCommand::class,
        Commands\MakeModelCommand::class,
        Commands\MakeProviderCommand::class,
        Commands\MakeRequestCommand::class,
        Commands\MakeSeederCommand::class,
        Commands\MakeViewCommand::class,
        Commands\MigrateCommand::class,
        Commands\MigrateFreshCommand::class,
        Commands\MigrateInstallCommand::class,
        Commands\MigrateRefreshCommand::class,
        Commands\MigrateResetCommand::class,
        Commands\MigrateRollbackCommand::class,
        Commands\MigrateStatusCommand::class,
        Commands\PackageDiscoverCommand::class,
        Commands\RouteListCommand::class,
        Commands\ServeCommand::class,
        Commands\VendorPublishCommand::class,
        Commands\ViewClearCommand::class,
    ];

    public function boot(Application $console): void
    {
        $this->publishes([dirname(__DIR__, 2).'/stubs' => $this->app->basePath('stubs')], 'slendie-stubs');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $console->resolveCommands(self::COMMANDS);

        $this->discoverCommands($console);
        $this->loadConsoleRoutes();
    }

    /** Todas as classes `Command` de `app/Console/Commands` (recursivo), sem precisarem de registo. */
    protected function discoverCommands(Application $console): void
    {
        $directory = $this->app->appPath('Console'.DIRECTORY_SEPARATOR.'Commands');

        if (! is_dir($directory)) {
            return;
        }

        $namespace = AppNamespace::resolve($this->app->basePath()).'Console\\Commands\\';
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        foreach ($files as $path) {
            $relative = substr($path, strlen(rtrim($directory, '/\\')) + 1, -4);
            $class = $namespace.str_replace(['/', DIRECTORY_SEPARATOR], '\\', $relative);

            if (class_exists($class) && is_subclass_of($class, Command::class) && (new ReflectionClass($class))->isInstantiable()) {
                $console->add($class);
            }
        }
    }

    protected function loadConsoleRoutes(): void
    {
        $file = $this->app->basePath('routes'.DIRECTORY_SEPARATOR.'console.php');

        if (is_file($file)) {
            (static function (string $file): void {
                require $file;
            })($file);
        }
    }
}
