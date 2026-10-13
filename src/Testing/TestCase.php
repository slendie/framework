<?php

declare(strict_types=1);

namespace Slendie\Testing;

use Closure;
use LogicException;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;
use Slendie\Container\Container;
use Slendie\Database\DatabaseManager;
use Slendie\Foundation\Application;
use Slendie\Foundation\Bootstrap\HandleExceptions;
use Slendie\Foundation\Exceptions\Handler;
use Slendie\Model\Model;
use Slendie\Support\Env;
use Slendie\Support\Facade;
use Slendie\Testing\Concerns\InteractsWithAuthentication;
use Slendie\Testing\Concerns\InteractsWithConsole;
use Slendie\Testing\Concerns\InteractsWithContainer;
use Slendie\Testing\Concerns\InteractsWithDatabase;
use Slendie\Testing\Concerns\InteractsWithExceptionHandling;
use Slendie\Testing\Concerns\InteractsWithSession;
use Slendie\Testing\Concerns\MakesHttpRequests;
use Slendie\Testing\Fakes\RecordingHandler;
use Throwable;

/**
 * Base dos testes de uma aplicação Slendie (o Pest usa-a com `uses(Tests\TestCase::class)`).
 *
 * Cada teste arranca uma Application nova a partir de `bootstrap/app.php` com `APP_ENV=testing`, sessão em memória e
 * CSRF desligado, e deita tudo fora no fim.
 */
abstract class TestCase extends BaseTestCase
{
    use InteractsWithAuthentication;
    use InteractsWithConsole;
    use InteractsWithContainer;
    use InteractsWithDatabase;
    use InteractsWithExceptionHandling;
    use InteractsWithSession;
    use MakesHttpRequests;

    protected Application $app;

    protected bool $applicationReady = false;

    /** @var list<Closure> */
    protected array $afterApplicationCreatedCallbacks = [];

    /** @var list<Closure> */
    protected array $beforeApplicationDestroyedCallbacks = [];

    /** @var list<string> ligações que sobrevivem ao teste (partilhadas pelo RefreshDatabase) */
    protected array $preservedConnections = [];

    /**
     * Cria a aplicação. Por defeito carrega `bootstrap/app.php` do projecto (procurado a partir do ficheiro da
     * TestCase subindo as pastas); sobreponha para usar outro caminho.
     */
    protected function createApplication(): Application
    {
        $file = $this->applicationBootstrapFile();

        /** @var mixed $app */
        $app = require $file;

        if (! $app instanceof Application) {
            throw new LogicException("[{$file}] tem de devolver uma Slendie\\Foundation\\Application (termina com ->create()).");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Container::setInstance(null);
        Facade::setFacadeApplication(null);
        Facade::clearResolvedInstances();
        Env::flush();
        Env::set('APP_ENV', 'testing');

        $this->app = $this->createApplication();

        // Não há browser a ecoar o token CSRF: fica desligado até um teste chamar withCsrf().
        $this->app->instance('testing.csrf.disabled', true);
        $this->app->bootstrap();

        $this->app->instance(Handler::class, new RecordingHandler($this->app, $this->app->make(Handler::class)));
        $this->sessionHandler();

        $this->setUpTraits();

        $this->applicationReady = true;

        foreach ($this->afterApplicationCreatedCallbacks as $callback) {
            $callback();
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            foreach ($this->beforeApplicationDestroyedCallbacks as $callback) {
                try {
                    $callback();
                } catch (Throwable) {
                    // A limpeza continua: um callback partido não pode deixar a aplicação pendurada.
                }
            }

            $this->closeConnections();

            if ($this->app->hasBeenBootstrapped()) {
                HandleExceptions::flush();
            }

            $this->app->flush();
        }

        Model::clearBootedModels();
        Model::setConnectionResolver(null);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Env::flush();

        $_SESSION = [];
        $this->applicationReady = false;
        $this->afterApplicationCreatedCallbacks = [];
        $this->beforeApplicationDestroyedCallbacks = [];

        parent::tearDown();
    }

    /** Corre `$callback` logo depois de a aplicação estar pronta (depois dos traits). */
    protected function afterApplicationCreated(Closure $callback): void
    {
        if ($this->applicationReady) {
            $callback();

            return;
        }

        $this->afterApplicationCreatedCallbacks[] = $callback;
    }

    /** Corre `$callback` antes de a aplicação ser destruída. */
    protected function beforeApplicationDestroyed(Closure $callback): void
    {
        $this->beforeApplicationDestroyedCallbacks[] = $callback;
    }

    /**
     * Activa os traits de teste usados pela classe (RefreshDatabase, DatabaseTransactions...).
     *
     * @return array<string, string> traits usados
     */
    protected function setUpTraits(): array
    {
        $uses = $this->classUsesRecursive(static::class);

        // Cada trait de teste expõe um método de arranque; a ordem é a da execução.
        $hooks = [
            RefreshDatabase::class => 'refreshDatabase',
            DatabaseMigrations::class => 'runDatabaseMigrations',
            DatabaseTransactions::class => 'beginDatabaseTransaction',
            WithoutMiddleware::class => 'disableMiddlewareForAllTests',
        ];

        foreach ($hooks as $trait => $method) {
            if (isset($uses[$trait]) && method_exists($this, $method)) {
                $this->{$method}();
            }
        }

        return $uses;
    }

    /** Guarda a ligação para o próximo teste (não é desligada no tearDown). */
    protected function preserveConnection(string $name): void
    {
        $this->preservedConnections[] = $name;
    }

    protected function closeConnections(): void
    {
        if (! $this->app->resolved(DatabaseManager::class)) {
            return;
        }

        $db = $this->app->make(DatabaseManager::class);

        foreach (array_keys($db->getConnections()) as $name) {
            if (! in_array((string) $name, $this->preservedConnections, true)) {
                $db->purge((string) $name);
            }
        }
    }

    /**
     * Procura `bootstrap/app.php` a partir da pasta da classe de teste (e das suas classes pai).
     */
    protected function applicationBootstrapFile(): string
    {
        $class = new ReflectionClass($this);

        while ($class !== false) {
            $file = $class->getFileName();

            if ($file !== false && ! str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', __DIR__))) {
                $directory = dirname($file);

                for ($level = 0; $level < 6; $level++) {
                    if (is_file($directory.'/bootstrap/app.php')) {
                        return $directory.'/bootstrap/app.php';
                    }

                    $directory = dirname($directory);
                }
            }

            $class = $class->getParentClass();
        }

        $cwd = getcwd();

        if ($cwd !== false && is_file($cwd.'/bootstrap/app.php')) {
            return $cwd.'/bootstrap/app.php';
        }

        throw new LogicException('Não encontrei bootstrap/app.php: sobreponha createApplication() na sua TestCase.');
    }

    /**
     * Traits da classe, das classes pai e dos próprios traits.
     *
     * @return array<string, string>
     */
    protected function classUsesRecursive(string $class): array
    {
        $traits = [];

        do {
            $traits = [...$traits, ...class_uses($class) ?: []];
        } while (($class = get_parent_class($class)) !== false);

        foreach ($traits as $trait) {
            $traits = [...$traits, ...$this->traitUsesRecursive($trait)];
        }

        return array_combine($traits, $traits);
    }

    /**
     * @return array<string, string>
     */
    private function traitUsesRecursive(string $trait): array
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $nested) {
            $traits = [...$traits, ...$this->traitUsesRecursive($nested)];
        }

        return $traits;
    }
}
