<?php

declare(strict_types=1);

namespace Slendie\Foundation;

use Closure;
use LogicException;
use Slendie\Support\Config;

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
    // Helpers de registo (os que dependem de peças futuras lançam LogicException)
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
     * @param  array<string, mixed>  $attributes
     */
    protected function loadRoutesFrom(string $path, array $attributes = ['middleware' => 'web']): void
    {
        $this->notYetAvailable(__FUNCTION__, 'core-04-09');
    }

    /**
     * @param  string|list<string>  $path
     */
    protected function loadViewsFrom(string|array $path, string $namespace): void
    {
        $this->notYetAvailable(__FUNCTION__, 'core-04-09');
    }

    /**
     * @param  string|list<string>  $paths
     */
    protected function loadMigrationsFrom(string|array $paths): void
    {
        $this->notYetAvailable(__FUNCTION__, 'core-04-09');
    }

    protected function loadTranslationsFrom(string $path, string $namespace): void
    {
        $this->notYetAvailable(__FUNCTION__, 'core-04-09');
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
        }
    }

    /**
     * @return array<string, string>
     */
    public static function pathsToPublish(?string $provider = null, ?string $group = null): array
    {
        if ($provider !== null && $group !== null) {
            $providers = static::$publishGroups[$group] ?? [];

            return in_array($provider, $providers, true) ? (static::$publishes[$provider] ?? []) : [];
        }

        if ($provider !== null) {
            return static::$publishes[$provider] ?? [];
        }

        if ($group !== null) {
            $paths = [];
            foreach (static::$publishGroups[$group] ?? [] as $class) {
                $paths = [...$paths, ...(static::$publishes[$class] ?? [])];
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
    }

    /**
     * @param  string|list<string>  $commands
     */
    protected function commands(string|array $commands): void
    {
        $this->notYetAvailable(__FUNCTION__, 'core-04-07');
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

    private function notYetAvailable(string $method, string $piece): never
    {
        throw new LogicException("ServiceProvider::{$method}() fica disponível na peça {$piece}.");
    }
}
