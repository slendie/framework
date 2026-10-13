<?php

declare(strict_types=1);

namespace Slendie\Foundation;

use Closure;
use LogicException;
use Slendie\Console\Application as ConsoleApplication;
use Slendie\Console\Command;
use Slendie\Database\Migrations\Migrator;
use Slendie\Routing\Router;
use Slendie\Support\Config;
use Slendie\Validation\Translator;
use Slendie\View\Compiler\BladeCompiler;
use Slendie\View\Finder;

/**
 * Único ponto de extensão da framework: a aplicação e os packages registam serviços aqui.
 *
 * register(): apenas bindings no container (não usar outros serviços).
 * boot(): corre depois de todos os register(); pode usar qualquer serviço (com DI nos parâmetros).
 */
abstract class ServiceProvider
{
    /** Quando true, register() só corre quando um dos provides() é pedido ao container. */
    protected bool $defer = false;

    /** @var array<class-string<ServiceProvider>, array<string, string>> grupo => [origem => destino] por provider */
    protected static array $publishes = [];

    /** @var array<string, list<class-string<ServiceProvider>>> grupo => providers */
    protected static array $publishGroups = [];

    /** @var array<string, array<class-string<ServiceProvider>, array<string, string>>> grupo => provider => [origem => destino] */
    protected static array $groupPaths = [];

    /** @var array<string, true> origens publicadas como migrações (os timestamps renomeiam-se ao publicar) */
    protected static array $migrationPublishes = [];

    /** @var list<Closure> */
    protected array $bootingCallbacks = [];

    /** @var list<Closure> */
    protected array $bootedCallbacks = [];

    public function __construct(protected Application $app)
    {
    }

    /**
     * Apenas bindings no container. Sem parâmetros.
     */
    public function register(): void
    {
    }

    /*
     * boot() é opcional e NÃO está declarado aqui de propósito: assim as subclasses podem
     * declarar `public function boot(Router $router, Config $config): void` e receber
     * dependências por injecção. A Application chama-o via Container::call() se existir.
     */

    public function isDeferred(): bool
    {
        return $this->defer;
    }

    /**
     * Serviços fornecidos por um provider adiado.
     *
     * @return list<string>
     */
    public function provides(): array
    {
        return [];
    }

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    public function booting(Closure $callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }

    public function booted(Closure $callback): void
    {
        $this->bootedCallbacks[] = $callback;
    }

    public function callBootingCallbacks(): void
    {
        foreach ($this->bootingCallbacks as $callback) {
            $this->app->call($callback);
        }
    }

    public function callBootedCallbacks(): void
    {
        foreach ($this->bootedCallbacks as $callback) {
            $this->app->call($callback);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers de registo
    // -------------------------------------------------------------------------

    /**
     * Junta a configuração do package por baixo da do projecto (o projecto vence).
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        $defaults = require $path;

        if (! is_array($defaults)) {
            throw new LogicException("O ficheiro de configuração [{$path}] deve devolver um array.");
        }

        /** @var Config $config */
        $config = $this->app->make(Config::class);

        /** @var array<string, mixed> $defaults */
        $config->merge($key, $defaults);
    }

    /**
     * Carrega um ficheiro de rotas (ou, com $path, as rotas de um package) dentro de um grupo.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function loadRoutesFrom(string $path, array $attributes = ['middleware' => 'web']): void
    {
        $this->app->booted(function () use ($path, $attributes): void {
            $this->router()->group($attributes, $path);
        });
    }

    /**
     * Regista as views de um package: `view('acme::index')`. Se o projecto tiver `resources/views/vendor/{namespace}`,
     * essa pasta tem prioridade (override).
     *
     * @param  string|list<string>  $path
     */
    protected function loadViewsFrom(string|array $path, string $namespace): void
    {
        $this->callAfterResolving(Finder::class, function (Finder $finder) use ($path, $namespace): void {
            $finder->addNamespace($namespace, $path);

            $override = $this->app->resourcePath('views/vendor/'.$namespace);

            if (is_dir($override)) {
                $finder->prependNamespace($namespace, $override);
            }
        });
    }

    /**
     * @param  string|list<string>  $paths
     */
    protected function loadMigrationsFrom(string|array $paths): void
    {
        $this->callAfterResolving(Migrator::class, static function (Migrator $migrator) use ($paths): void {
            foreach ((array) $paths as $path) {
                $migrator->path($path);
            }
        });
    }

    /**
     * Traduções de um package: `__('acme::mensagens.ola')`. O projecto sobrepõe em `lang/vendor/{namespace}`.
     */
    protected function loadTranslationsFrom(string $path, string $namespace): void
    {
        $this->callAfterResolving(Translator::class, static function (Translator $translator) use ($path, $namespace): void {
            $translator->addNamespace($namespace, $path);
        });
    }

    /**
     * Regista ficheiros publicáveis com `vendor:publish`.
     *
     * @param  array<string, string>  $paths  origem => destino
     * @param  string|list<string>|null  $groups
     */
    protected function publishes(array $paths, string|array|null $groups = null): void
    {
        $class = static::class;

        static::$publishes[$class] = [...(static::$publishes[$class] ?? []), ...$paths];

        foreach ((array) ($groups ?? []) as $group) {
            static::$publishGroups[$group][] = $class;
            static::$publishGroups[$group] = array_values(array_unique(static::$publishGroups[$group]));
            static::$groupPaths[$group][$class] = [...(static::$groupPaths[$group][$class] ?? []), ...$paths];
        }
    }

    /**
     * Como publishes(), mas para migrações: ao publicar, os timestamps dos ficheiros passam a ser "agora".
     *
     * @param  array<string, string>  $paths  origem => destino
     * @param  string|list<string>|null  $groups
     */
    protected function publishesMigrations(array $paths, string|array|null $groups = null): void
    {
        foreach (array_keys($paths) as $from) {
            static::$migrationPublishes[$from] = true;
        }

        $this->publishes($paths, $groups);
    }

    public static function isMigrationPublish(string $from): bool
    {
        return isset(static::$migrationPublishes[$from]);
    }

    /**
     * @return array<string, string>
     */
    public static function pathsToPublish(?string $provider = null, ?string $group = null): array
    {
        if ($provider !== null && $group !== null) {
            $providers = static::$publishGroups[$group] ?? [];

            return in_array($provider, $providers, true) ? (static::$groupPaths[$group][$provider] ?? []) : [];
        }

        if ($provider !== null) {
            return static::$publishes[$provider] ?? [];
        }

        if ($group !== null) {
            $paths = [];
            foreach (static::$publishGroups[$group] ?? [] as $class) {
                $paths = [...$paths, ...(static::$groupPaths[$group][$class] ?? [])];
            }

            return $paths;
        }

        return array_merge([], ...array_values(static::$publishes));
    }

    /** @return list<string> */
    public static function publishableGroups(): array
    {
        return array_keys(static::$publishGroups);
    }

    /** @return list<class-string<ServiceProvider>> */
    public static function publishableProviders(): array
    {
        return array_keys(static::$publishes);
    }

    public static function flushPublishes(): void
    {
        static::$publishes = [];
        static::$publishGroups = [];
        static::$groupPaths = [];
        static::$migrationPublishes = [];
    }

    /**
     * Regista comandos de consola (só quando corre em CLI): `php slendie` passa a conhecê-los.
     *
     * @param  class-string<Command>|list<class-string<Command>>  $commands
     */
    protected function commands(string|array $commands): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $commands = array_values((array) $commands);

        $this->callAfterResolving(ConsoleApplication::class, static function (ConsoleApplication $console) use ($commands): void {
            $console->resolveCommands($commands);
        });
    }

    /**
     * Regista um callback para quando $abstract for resolvido (ou imediatamente, se já o foi).
     */
    protected function callAfterResolving(string $abstract, Closure $callback): void
    {
        $this->app->resolving($abstract, $callback);

        if ($this->app->resolved($abstract)) {
            $callback($this->app->make($abstract), $this->app);
        }
    }

    protected function router(): Router
    {
        return $this->app->make(Router::class);
    }

    protected function blade(): BladeCompiler
    {
        return $this->app->make(BladeCompiler::class);
    }
}
