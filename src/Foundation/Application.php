<?php

declare(strict_types=1);

namespace Slendie\Foundation;

use Closure;
use LogicException;
use Slendie\Container\Container;
use Slendie\Foundation\Bootstrap\BootProviders;
use Slendie\Foundation\Bootstrap\HandleExceptions;
use Slendie\Foundation\Bootstrap\LoadConfiguration;
use Slendie\Foundation\Bootstrap\LoadEnvironment;
use Slendie\Foundation\Bootstrap\RegisterProviders;
use Slendie\Foundation\Configuration\ApplicationBuilder;
use Slendie\Http\Kernel;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Routing\RouteCollection;
use Slendie\Routing\Router;
use Slendie\Routing\RoutingServiceProvider;
use Slendie\Session\SessionServiceProvider;
use Slendie\Support\Config;
use Slendie\Support\Env;
use Slendie\Support\Facade;

class Application extends Container
{
    public const string VERSION = '4.0.0-dev';

    /** Bootstrappers por defeito, pela ordem. @var list<class-string> */
    public const array BOOTSTRAPPERS = [
        LoadEnvironment::class,
        LoadConfiguration::class,
        HandleExceptions::class,
        RegisterProviders::class,
        BootProviders::class,
    ];

    /**
     * Providers que a própria framework regista sempre.
     *
     * @var list<class-string<ServiceProvider>>
     */
    protected array $frameworkProviders = [
        RoutingServiceProvider::class,
        SessionServiceProvider::class,
        // Peças seguintes: View, Database, Console, ...
    ];

    protected string $basePath;

    protected ?string $environmentFile = null;

    protected bool $hasBeenBootstrapped = false;

    protected bool $booted = false;

    /** @var array<class-string<ServiceProvider>, ServiceProvider> */
    protected array $loadedProviders = [];

    /** @var array<string, class-string<ServiceProvider>> serviço => provider adiado */
    protected array $deferredServices = [];

    /** @var list<Closure> */
    protected array $bootingCallbacks = [];

    /** @var list<Closure> */
    protected array $bootedCallbacks = [];

    /** @var list<Closure> */
    protected array $terminatingCallbacks = [];

    /** @var array<string, string> */
    protected array $customPaths = [];

    public function __construct(string $basePath)
    {
        $this->setBasePath($basePath);
        $this->registerBaseBindings();
    }

    protected function registerBaseBindings(): void
    {
        static::setInstance($this);
        Facade::setFacadeApplication($this);

        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->instance(self::class, $this);

        if (static::class !== self::class) {
            $this->instance(static::class, $this);
        }

        // O Kernel (e o withMiddleware) é resolvido antes do bootstrap: o Router tem de ser o mesmo depois.
        $this->singleton(RouteCollection::class);
        $this->singleton(Router::class);
        $this->alias(Router::class, 'router');
        $this->singleton(Kernel::class);
    }

    public function version(): string
    {
        return static::VERSION;
    }

    // -------------------------------------------------------------------------
    // Caminhos
    // -------------------------------------------------------------------------

    public function setBasePath(string $basePath): static
    {
        $this->basePath = rtrim($basePath, '\/');

        return $this;
    }

    public function basePath(string $path = ''): string
    {
        return $this->joinPath($this->basePath, $path);
    }

    public function appPath(string $path = ''): string
    {
        return $this->joinPath($this->customPaths['app'] ?? $this->basePath('app'), $path);
    }

    public function configPath(string $path = ''): string
    {
        return $this->joinPath($this->customPaths['config'] ?? $this->basePath('config'), $path);
    }

    public function databasePath(string $path = ''): string
    {
        return $this->joinPath($this->customPaths['database'] ?? $this->basePath('database'), $path);
    }

    public function publicPath(string $path = ''): string
    {
        return $this->joinPath($this->customPaths['public'] ?? $this->basePath('public'), $path);
    }

    public function resourcePath(string $path = ''): string
    {
        return $this->joinPath($this->customPaths['resources'] ?? $this->basePath('resources'), $path);
    }

    public function storagePath(string $path = ''): string
    {
        return $this->joinPath($this->customPaths['storage'] ?? $this->basePath('storage'), $path);
    }

    public function bootstrapPath(string $path = ''): string
    {
        return $this->joinPath($this->customPaths['bootstrap'] ?? $this->basePath('bootstrap'), $path);
    }

    public function langPath(string $path = ''): string
    {
        return $this->joinPath($this->customPaths['lang'] ?? $this->basePath('lang'), $path);
    }

    public function useStoragePath(string $path): static
    {
        $this->customPaths['storage'] = rtrim($path, '\/');

        return $this;
    }

    public function useConfigPath(string $path): static
    {
        $this->customPaths['config'] = rtrim($path, '\/');

        return $this;
    }

    public function useDatabasePath(string $path): static
    {
        $this->customPaths['database'] = rtrim($path, '\/');

        return $this;
    }

    public function useLangPath(string $path): static
    {
        $this->customPaths['lang'] = rtrim($path, '\/');

        return $this;
    }

    protected function joinPath(string $base, string $path): string
    {
        $path = ltrim($path, '\/');

        return $path === '' ? $base : $base.DIRECTORY_SEPARATOR.$path;
    }

    // -------------------------------------------------------------------------
    // Ambiente
    // -------------------------------------------------------------------------

    public function environmentFile(): string
    {
        return $this->environmentFile ?? '.env';
    }

    public function loadEnvironmentFrom(string $file): static
    {
        $this->environmentFile = $file;

        return $this;
    }

    public function environmentFilePath(): string
    {
        return $this->basePath($this->environmentFile());
    }

    /**
     * Sem argumentos devolve o nome do ambiente; com argumentos verifica se corresponde a algum.
     */
    public function environment(string ...$environments): string|bool
    {
        $current = $this->resolveEnvironment();

        if ($environments === []) {
            return $current;
        }

        foreach ($environments as $env) {
            $pattern = str_replace('\*', '.*', preg_quote($env, '#'));

            if (preg_match('#^'.$pattern.'\z#u', $current) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function resolveEnvironment(): string
    {
        if ($this->bound(Config::class) && $this->resolved(Config::class)) {
            $fromConfig = $this->make(Config::class)->get('app.env');

            if (is_string($fromConfig) && $fromConfig !== '') {
                return $fromConfig;
            }
        }

        $env = Env::get('APP_ENV', 'production');

        return is_string($env) && $env !== '' ? $env : 'production';
    }

    public function isLocal(): bool
    {
        return $this->environment() === 'local';
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    public function isDebug(): bool
    {
        if ($this->bound(Config::class) && $this->resolved(Config::class)) {
            return (bool) $this->make(Config::class)->get('app.debug', false);
        }

        return filter_var(Env::get('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function runningInConsole(): bool
    {
        return in_array(PHP_SAPI, ['cli', 'phpdbg'], true);
    }

    public function runningUnitTests(): bool
    {
        return $this->environment() === 'testing';
    }

    public function getLocale(): string
    {
        $locale = $this->bound(Config::class) ? $this->make(Config::class)->get('app.locale', 'pt') : 'pt';

        return is_string($locale) ? $locale : 'pt';
    }

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    /**
     * @param  list<class-string>|null  $bootstrappers
     */
    public function bootstrapWith(?array $bootstrappers = null): void
    {
        $this->hasBeenBootstrapped = true;

        foreach ($bootstrappers ?? static::BOOTSTRAPPERS as $bootstrapper) {
            $instance = $this->make($bootstrapper);

            if (! is_object($instance) || ! method_exists($instance, 'bootstrap')) {
                throw new LogicException("O bootstrapper [{$bootstrapper}] precisa de um método bootstrap(Application).");
            }

            $instance->bootstrap($this);
        }
    }

    public function bootstrap(): void
    {
        if (! $this->hasBeenBootstrapped) {
            $this->bootstrapWith();
        }
    }

    public function hasBeenBootstrapped(): bool
    {
        return $this->hasBeenBootstrapped;
    }

    // -------------------------------------------------------------------------
    // Providers
    // -------------------------------------------------------------------------

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function getFrameworkProviders(): array
    {
        return $this->frameworkProviders;
    }

    /**
     * Regista um provider (classe ou instância). Providers adiados ficam à espera.
     *
     * @param  ServiceProvider|class-string<ServiceProvider>  $provider
     */
    public function register(ServiceProvider|string $provider, bool $force = false): ServiceProvider
    {
        $name = is_string($provider) ? ltrim($provider, '\\') : $provider::class;

        if (isset($this->loadedProviders[$name]) && ! $force) {
            return $this->loadedProviders[$name];
        }

        $instance = is_string($provider) ? $this->resolveProvider($provider) : $provider;
        $class = $instance::class;

        if ($instance->isDeferred() && ! $force) {
            foreach ($instance->provides() as $service) {
                $this->deferredServices[ltrim($service, '\\')] = $class;
            }

            return $instance;
        }

        $instance->register();
        $this->loadedProviders[$class] = $instance;

        if ($this->booted) {
            $this->bootProvider($instance);
        }

        return $instance;
    }

    /**
     * @param  class-string<ServiceProvider>  $provider
     */
    public function resolveProvider(string $provider): ServiceProvider
    {
        if (! is_subclass_of($provider, ServiceProvider::class)) {
            throw new LogicException("[{$provider}] não é um ServiceProvider.");
        }

        return new $provider($this);
    }

    /**
     * @param  class-string<ServiceProvider>  $provider
     */
    public function getProvider(string $provider): ?ServiceProvider
    {
        return $this->loadedProviders[ltrim($provider, '\\')] ?? null;
    }

    /**
     * @param  class-string<ServiceProvider>  $provider
     */
    public function providerIsLoaded(string $provider): bool
    {
        return isset($this->loadedProviders[ltrim($provider, '\\')]);
    }

    /** @return array<class-string<ServiceProvider>, ServiceProvider> */
    public function getLoadedProviders(): array
    {
        return $this->loadedProviders;
    }

    /** @return array<string, class-string<ServiceProvider>> */
    public function getDeferredServices(): array
    {
        return $this->deferredServices;
    }

    public function isDeferredService(string $service): bool
    {
        return isset($this->deferredServices[ltrim($service, '\\')]);
    }

    public function loadDeferredProvider(string $service): void
    {
        $service = ltrim($service, '\\');

        if (! isset($this->deferredServices[$service])) {
            return;
        }

        $provider = $this->deferredServices[$service];

        foreach ($this->deferredServices as $key => $class) {
            if ($class === $provider) {
                unset($this->deferredServices[$key]);
            }
        }

        $this->register($provider, force: true);
    }

    /**
     * @template T of object
     * @param  class-string<T>|string  $abstract
     * @param  array<string, mixed>  $parameters
     * @return ($abstract is class-string<T> ? T : mixed)
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->deferredServices[$abstract]) && ! isset($this->instances[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }

        return parent::make($abstract, $parameters);
    }

    public function bound(string $abstract): bool
    {
        return isset($this->deferredServices[ltrim($abstract, '\\')]) || parent::bound($abstract);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->bootingCallbacks as $callback) {
            $this->call($callback);
        }

        foreach ($this->loadedProviders as $provider) {
            $this->bootProvider($provider);
        }

        $this->booted = true;

        foreach ($this->bootedCallbacks as $callback) {
            $this->call($callback);
        }
    }

    protected function bootProvider(ServiceProvider $provider): void
    {
        $provider->callBootingCallbacks();

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }

        $provider->callBootedCallbacks();
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function booting(Closure $callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Regista um callback para depois do boot; se já arrancou, corre imediatamente.
     */
    public function booted(Closure $callback): void
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->booted) {
            $this->call($callback);
        }
    }

    // -------------------------------------------------------------------------
    // Pedido HTTP (peça 02) e término
    // -------------------------------------------------------------------------

    public function terminating(Closure $callback): static
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Sem argumentos corre apenas os callbacks de terminating(); com (Request, Response) delega primeiro no Kernel
     * (que chama terminate() nos middlewares e volta aqui sem argumentos).
     */
    public function terminate(?Request $request = null, ?Response $response = null): void
    {
        if ($request !== null && $response !== null) {
            $this->make(Kernel::class)->terminate($request, $response);

            return;
        }

        foreach ($this->terminatingCallbacks as $callback) {
            $this->call($callback);
        }
    }

    /**
     * Processa um pedido HTTP através do Kernel.
     */
    public function handle(Request $request): Response
    {
        /** @var Kernel $kernel */
        $kernel = $this->make(Kernel::class);

        return $kernel->handle($request);
    }

    // -------------------------------------------------------------------------
    // Configuração fluente (bootstrap/app.php)
    // -------------------------------------------------------------------------

    public static function configure(string $basePath): ApplicationBuilder
    {
        return new ApplicationBuilder(new self($basePath));
    }

    public function withRouting(?string $web = null, ?string $api = null): ApplicationBuilder
    {
        return (new ApplicationBuilder($this))->withRouting($web, $api);
    }

    public function withMiddleware(Closure $callback): ApplicationBuilder
    {
        return (new ApplicationBuilder($this))->withMiddleware($callback);
    }

    public function withExceptions(Closure $callback): ApplicationBuilder
    {
        return (new ApplicationBuilder($this))->withExceptions($callback);
    }

    public function flush(): void
    {
        parent::flush();

        $this->loadedProviders = [];
        $this->deferredServices = [];
        $this->bootingCallbacks = [];
        $this->bootedCallbacks = [];
        $this->terminatingCallbacks = [];
        $this->booted = false;
        $this->hasBeenBootstrapped = false;
    }
}
