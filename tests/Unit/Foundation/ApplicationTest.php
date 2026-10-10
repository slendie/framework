<?php

declare(strict_types=1);

use Slendie\Container\Container;
use Slendie\Foundation\Application;
use Slendie\Foundation\Bootstrap\BootProviders;
use Slendie\Foundation\Bootstrap\LoadConfiguration;
use Slendie\Foundation\Bootstrap\LoadEnvironment;
use Slendie\Foundation\Bootstrap\RegisterProviders;
use Slendie\Foundation\ServiceProvider;
use Slendie\Support\Config;
use Slendie\Support\Env;
use Slendie\Support\Facade;
use Slendie\Tests\Fixtures\Clock;
use Slendie\Tests\Fixtures\GreeterContract;
use Slendie\Tests\Fixtures\Providers\DeferredProvider;
use Slendie\Tests\Fixtures\Providers\FixtureServiceProvider;

const BOOTSTRAPPERS_SEM_EXCEPTIONS = [
    LoadEnvironment::class,
    LoadConfiguration::class,
    RegisterProviders::class,
    BootProviders::class,
];

beforeEach(function (): void {
    FixtureServiceProvider::$log = [];
    DeferredProvider::$registered = 0;
    $this->app = new Application(fixturePath('app'));
});

it('regista-se como instância global e no container', function (): void {
    expect(Container::getInstance())->toBe($this->app)
        ->and(Facade::getFacadeApplication())->toBe($this->app)
        ->and($this->app->make('app'))->toBe($this->app)
        ->and($this->app->make(Application::class))->toBe($this->app)
        ->and($this->app->make(Container::class))->toBe($this->app)
        ->and(app())->toBe($this->app)
        ->and($this->app->version())->toBe(Application::VERSION);
});

it('calcula os caminhos', function (): void {
    $base = fixturePath('app');
    $ds = DIRECTORY_SEPARATOR;

    expect($this->app->basePath())->toBe($base)
        ->and($this->app->basePath('x/y'))->toBe($base.$ds.'x/y')
        ->and($this->app->appPath())->toBe($base.$ds.'app')
        ->and($this->app->configPath('app.php'))->toBe($base.$ds.'config'.$ds.'app.php')
        ->and($this->app->databasePath())->toBe($base.$ds.'database')
        ->and($this->app->publicPath())->toBe($base.$ds.'public')
        ->and($this->app->resourcePath('views'))->toBe($base.$ds.'resources'.$ds.'views')
        ->and($this->app->storagePath('logs'))->toBe($base.$ds.'storage'.$ds.'logs')
        ->and($this->app->bootstrapPath())->toBe($base.$ds.'bootstrap')
        ->and($this->app->langPath())->toBe($base.$ds.'lang')
        ->and(base_path())->toBe($base)
        ->and(storage_path('x'))->toBe($base.$ds.'storage'.$ds.'x');

    $this->app->useStoragePath('/tmp/custom/');
    expect($this->app->storagePath('a'))->toBe('/tmp/custom'.$ds.'a');
});

it('faz bootstrap: env, config, providers e boot pela ordem certa', function (): void {
    $this->app->bootstrapWith(BOOTSTRAPPERS_SEM_EXCEPTIONS);

    expect($this->app->hasBeenBootstrapped())->toBeTrue()
        ->and($this->app->isBooted())->toBeTrue()
        ->and(config('app.name'))->toBe('Fixture App')
        ->and(config('app.timezone'))->toBe('Europe/Lisbon')
        ->and(date_default_timezone_get())->toBe('Europe/Lisbon')
        ->and(config('services.mailer.host'))->toBe('smtp.fixture.test')
        ->and($this->app->make('config'))->toBeInstanceOf(Config::class)
        ->and($this->app->environment())->toBe('testing')
        ->and($this->app->environment('testing', 'local'))->toBeTrue()
        ->and($this->app->environment('prod*'))->toBeFalse()
        ->and($this->app->runningUnitTests())->toBeTrue()
        ->and($this->app->isDebug())->toBeTrue()
        ->and($this->app->isProduction())->toBeFalse()
        ->and($this->app->runningInConsole())->toBeTrue()
        ->and(FixtureServiceProvider::$log)->toBe(['register', 'config-listed:register', 'boot:Fixture App', 'config-listed:boot'])
        ->and($this->app->providerIsLoaded(FixtureServiceProvider::class))->toBeTrue()
        ->and($this->app->make(GreeterContract::class)->greet('x'))->toBe('Olá, x!');
});

it('mergeConfigFrom deixa o projecto vencer', function (): void {
    $this->app->bootstrapWith(BOOTSTRAPPERS_SEM_EXCEPTIONS);
    config(['fixture.shared_key' => 'project']);
    $this->app->register(FixtureServiceProvider::class, force: true);

    expect(config('fixture.from_package'))->toBe('package-default')
        ->and(config('fixture.shared_key'))->toBe('project');
});

it('config helper lê, escreve e devolve o repositório', function (): void {
    $this->app->bootstrapWith([LoadEnvironment::class, LoadConfiguration::class]);
    config(['custom.key' => 1]);

    expect(config('custom.key'))->toBe(1)
        ->and(config('nope', 'd'))->toBe('d')
        ->and(config())->toBeInstanceOf(Config::class);
});

it('usa defaults de app.* quando não há config/app.php', function (): void {
    // phpunit.xml define APP_ENV=testing/APP_DEBUG=true; simula um servidor sem essas variáveis.
    Env::set('APP_ENV', 'production');
    Env::set('APP_DEBUG', false);

    $app = new Application(sys_get_temp_dir().'/slendie-sem-config-'.uniqid());
    $app->bootstrapWith([LoadEnvironment::class, LoadConfiguration::class]);

    expect(config('app.name'))->toBe('Slendie')
        ->and(config('app.env'))->toBe('production')
        ->and($app->environment())->toBe('production')
        ->and($app->isProduction())->toBeTrue()
        ->and($app->isDebug())->toBeFalse();
});

it('register aceita instância, evita duplicados e faz boot imediato se já arrancou', function (): void {
    $this->app->bootstrapWith(BOOTSTRAPPERS_SEM_EXCEPTIONS);
    FixtureServiceProvider::$log = [];

    $provider = new class ($this->app) extends ServiceProvider {
        public function register(): void
        {
            FixtureServiceProvider::$log[] = 'anon:register';
        }

        public function boot(): void
        {
            FixtureServiceProvider::$log[] = 'anon:boot';
        }
    };

    $this->app->register($provider);
    $this->app->register($provider);

    expect(FixtureServiceProvider::$log)->toBe(['anon:register', 'anon:boot'])
        ->and($this->app->getProvider($provider::class))->toBe($provider)
        ->and($this->app->getLoadedProviders())->toHaveCount(3 + count($this->app->getFrameworkProviders()));
});

it('register recusa classes que não são providers', function (): void {
    $this->app->register(Clock::class);
})->throws(LogicException::class, 'não é um ServiceProvider');

it('booting e booted callbacks correm com DI; booted corre imediato se já arrancou', function (): void {
    $order = [];
    $this->app->booting(function () use (&$order): void {
        $order[] = 'booting';
    });
    $this->app->booted(function (Config $config) use (&$order): void {
        $order[] = 'booted:'.$config->get('app.env');
    });

    $this->app->bootstrapWith(BOOTSTRAPPERS_SEM_EXCEPTIONS);
    $this->app->booted(function () use (&$order): void {
        $order[] = 'late';
    });

    expect($order)->toBe(['booting', 'booted:testing', 'late']);
});

it('providers adiados só registam quando o serviço é pedido', function (): void {
    $this->app->register(DeferredProvider::class);

    expect(DeferredProvider::$registered)->toBe(0)
        ->and($this->app->isDeferredService('fixture.clock'))->toBeTrue()
        ->and($this->app->bound('fixture.clock'))->toBeTrue();

    $clock = $this->app->make('fixture.clock');

    expect($clock)->toBeInstanceOf(Clock::class)
        ->and(DeferredProvider::$registered)->toBe(1)
        ->and($this->app->isDeferredService('fixture.clock'))->toBeFalse()
        ->and($this->app->make('fixture.clock'))->toBe($clock);
});

it('terminating callbacks correm em terminate()', function (): void {
    $ran = false;
    $this->app->terminating(function () use (&$ran): void {
        $ran = true;
    });
    $this->app->terminate();

    expect($ran)->toBeTrue();
});

it('bootstrap() só corre uma vez', function (): void {
    $this->app->bootstrapWith([LoadEnvironment::class]);
    $this->app->bootstrap();

    expect($this->app->isBooted())->toBeFalse();
});

it('bootstrapper inválido lança', function (): void {
    $this->app->bootstrapWith([Clock::class]);
})->throws(LogicException::class, 'bootstrap(Application)');

it('loadEnvironmentFrom muda o ficheiro .env', function (): void {
    $this->app->loadEnvironmentFrom('.env.custom');

    expect($this->app->environmentFile())->toBe('.env.custom')
        ->and($this->app->environmentFilePath())->toBe(fixturePath('app').DIRECTORY_SEPARATOR.'.env.custom');
});

it('facade resolve pelo container e cacheia singletons', function (): void {
    $this->app->singleton('fixture.clock', Clock::class);

    $facade = new class () extends Facade {
        protected static function getFacadeAccessor(): string
        {
            return 'fixture.clock';
        }
    };

    expect($facade::now())->toBe('2026-10-10')
        ->and($facade::getFacadeRoot())->toBe($this->app->make('fixture.clock'));

    $facade::swap(new class () {
        public function now(): string
        {
            return 'swapped';
        }
    });

    expect($facade::now())->toBe('swapped');
});

it('facade sem serviço registado explica o problema', function (): void {
    $facade = new class () extends Facade {
        protected static function getFacadeAccessor(): string
        {
            return 'servico.inexistente';
        }
    };

    $facade::qualquer();
})->throws(RuntimeException::class, 'não encontrou [servico.inexistente]');

it('helpers diversos', function (): void {
    expect(e('<a href="x">\'&'))->toBe('&lt;a href=&quot;x&quot;&gt;&#039;&amp;')
        ->and(e(null))->toBe('')
        ->and(e(true))->toBe('1')
        ->and(e(3.5))->toBe('3.5')
        ->and(value(fn () => 'x'))->toBe('x')
        ->and(value('y'))->toBe('y')
        ->and(value(fn (int $n) => $n * 2, 21))->toBe(42)
        ->and(tap(new Clock(), fn (Clock $c) => null))->toBeInstanceOf(Clock::class)
        ->and(class_basename(Application::class))->toBe('Application')
        ->and(blank(''))->toBeTrue()
        ->and(blank('  '))->toBeTrue()
        ->and(blank(null))->toBeTrue()
        ->and(blank([]))->toBeTrue()
        ->and(blank(0))->toBeFalse()
        ->and(blank(false))->toBeFalse()
        ->and(filled('x'))->toBeTrue()
        ->and(env('NAO_EXISTE', 'd'))->toBe('d');

    $attempts = 0;
    $result = retry(3, function (int $attempt) use (&$attempts): string {
        $attempts = $attempt;
        if ($attempt < 3) {
            throw new RuntimeException('falha');
        }

        return 'ok';
    });

    expect($result)->toBe('ok')->and($attempts)->toBe(3);
});

it('e() recusa arrays', function (): void {
    e([1]);
})->throws(InvalidArgumentException::class);
