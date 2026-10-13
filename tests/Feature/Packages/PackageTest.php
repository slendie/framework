<?php

declare(strict_types=1);

use Slendie\Foundation\Bootstrap\RegisterProviders;
use Slendie\Foundation\PackageDiscovery;
use Slendie\Foundation\ServiceProvider;
use Slendie\Http\Request;
use Slendie\Support\Config;
use Slendie\Support\Facades\Blade;
use Slendie\Tests\Fixtures\Console\ConsoleProject;
use Slendie\Tests\Fixtures\Providers\DeferredProvider;
use Slendie\View\Compiler\BladeCompiler;

require_once __DIR__.'/../../Fixtures/packages/acme-blog/src/AcmeServiceProvider.php';
require_once __DIR__.'/../../Fixtures/packages/acme-blog/src/AcmeMiddleware.php';
require_once __DIR__.'/../../Fixtures/packages/acme-blog/src/AcmeGroupMiddleware.php';
require_once __DIR__.'/../../Fixtures/packages/acme-blog/src/HelloCommand.php';

const ACME_DIR = __DIR__.'/../../Fixtures/packages/acme-blog';

afterEach(function (): void {
    ConsoleProject::cleanup();
    ServiceProvider::flushPublishes();
    BladeCompiler::flushConditions();
});

/**
 * Um projecto cujo `vendor/composer/installed.json` declara o package acme/blog (como depois de `composer require`).
 *
 * @param  array<string, string>  $files
 */
function projectWithAcme(array $files = []): ConsoleProject
{
    $installed = json_encode(['packages' => [json_decode((string) file_get_contents(ACME_DIR.'/composer.json'), true)]]);

    return ConsoleProject::make([
        'vendor/composer/installed.json' => (string) $installed,
        'routes/web.php' => "<?php\n",
        ...$files,
    ]);
}

function acmeGet(ConsoleProject $project, string $uri): Slendie\Http\Response
{
    return $project->app->handle(Request::create($uri));
}

// -----------------------------------------------------------------------------
// PackageDiscovery
// -----------------------------------------------------------------------------

it('PackageDiscovery lê installed.json (Composer 2) e ignora packages sem extra.slendie', function (): void {
    $project = ConsoleProject::make();
    $project->put('vendor/composer/installed.json', (string) json_encode(['packages' => [
        ['name' => 'acme/blog', 'extra' => ['slendie' => ['providers' => ['Acme\\Blog\\AcmeServiceProvider'], 'aliases' => ['Blog' => '\\Acme\\Blog\\Facade']]]],
        ['name' => 'plain/lib', 'version' => '1.0.0'],
        ['name' => 'other/lib', 'extra' => ['branch-alias' => ['dev-main' => '1.x-dev']]],
        ['name' => 'two/providers', 'extra' => ['slendie' => ['providers' => ['A\\P1', 'A\\P2']]]],
    ]]));

    $manifest = (new PackageDiscovery())->discover($project->path('vendor'));

    expect($manifest['providers'])->toBe(['Acme\\Blog\\AcmeServiceProvider', 'A\\P1', 'A\\P2'])
        ->and($manifest['aliases'])->toBe(['Blog' => 'Acme\\Blog\\Facade'])
        ->and(array_keys($manifest['packages']))->toBe(['acme/blog', 'two/providers'])
        ->and($manifest['dont-discover'])->toBe([]);
});

it('PackageDiscovery aceita o formato antigo do Composer 1 e a falta de installed.json', function (): void {
    $project = ConsoleProject::make();

    expect((new PackageDiscovery())->discover($project->path('vendor'))['providers'])->toBe([]);

    $project->put('vendor/composer/installed.json', (string) json_encode([
        ['name' => 'old/pkg', 'extra' => ['slendie' => ['providers' => ['Old\\Provider']]]],
    ]));

    expect((new PackageDiscovery())->discover($project->path('vendor'))['providers'])->toBe(['Old\\Provider']);
});

it('PackageDiscovery respeita dont-discover por nome e com *', function (): void {
    $installed = (string) json_encode(['packages' => [
        ['name' => 'a/one', 'extra' => ['slendie' => ['providers' => ['A\\One']]]],
        ['name' => 'b/two', 'extra' => ['slendie' => ['providers' => ['B\\Two']]]],
    ]]);

    $byName = ConsoleProject::make(['vendor/composer/installed.json' => $installed, 'composer.json' => (string) json_encode(['extra' => ['slendie' => ['dont-discover' => ['a/one']]]])]);
    $all = ConsoleProject::make(['vendor/composer/installed.json' => $installed, 'composer.json' => (string) json_encode(['extra' => ['slendie' => ['dont-discover' => ['*']]]])]);

    expect((new PackageDiscovery())->discover($byName->path('vendor'))['providers'])->toBe(['B\\Two'])
        ->and((new PackageDiscovery())->discover($all->path('vendor'))['providers'])->toBe([]);
});

it('PackageDiscovery escreve e lê o cache; sem cache descobre em runtime', function (): void {
    $project = projectWithAcme();
    $discovery = new PackageDiscovery();
    $cache = $project->path('bootstrap/cache/packages.php');

    $manifest = $discovery->discover($project->path('vendor'));
    $discovery->write($cache, $manifest);

    expect(is_file($cache))->toBeTrue()
        ->and($discovery->load($project->path('vendor'), $cache)['providers'])->toBe(['Acme\\Blog\\AcmeServiceProvider']);

    // O cache manda: apagar o installed.json não muda nada...
    unlink($project->path('vendor/composer/installed.json'));
    expect($discovery->load($project->path('vendor'), $cache)['providers'])->toBe(['Acme\\Blog\\AcmeServiceProvider']);

    // ...e sem cache volta a descobrir (agora, nada).
    $discovery->clear($cache);
    expect($discovery->load($project->path('vendor'), $cache)['providers'])->toBe([]);
});

it('package:discover escreve o cache e lista os packages; RegisterProviders usa-o', function (): void {
    $project = projectWithAcme();

    [$code, $output] = $project->artisan('package:discover');

    expect($code)->toBe(0)
        ->and($output)->toContain('Discovered Package: acme/blog')
        ->and($project->has('bootstrap/cache/packages.php'))->toBeTrue();

    // Com o cache, o installed.json já não é lido.
    unlink($project->path('vendor/composer/installed.json'));
    (new RegisterProviders())->bootstrap($project->app);

    expect($project->app->providerIsLoaded('Acme\\Blog\\AcmeServiceProvider'))->toBeTrue();
});

it('RegisterProviders junta framework, packages descobertos e bootstrap/providers.php', function (): void {
    $project = projectWithAcme(['bootstrap/providers.php' => "<?php\nreturn [Slendie\\Tests\\Fixtures\\Providers\\FixtureServiceProvider::class];\n"]);
    (new RegisterProviders())->bootstrap($project->app);

    expect($project->app->providerIsLoaded('Acme\\Blog\\AcmeServiceProvider'))->toBeTrue()
        ->and($project->app->providerIsLoaded(Slendie\Tests\Fixtures\Providers\FixtureServiceProvider::class))->toBeTrue()
        ->and($project->app->providerIsLoaded(Slendie\Routing\RoutingServiceProvider::class))->toBeTrue();
});

it('um package com dont-discover * no projecto não é registado', function (): void {
    $project = projectWithAcme(['composer.json' => (string) json_encode(['extra' => ['slendie' => ['dont-discover' => ['acme/blog']]]])]);
    (new RegisterProviders())->bootstrap($project->app);

    expect($project->app->providerIsLoaded('Acme\\Blog\\AcmeServiceProvider'))->toBeFalse();
});

// -----------------------------------------------------------------------------
// Capacidades do ServiceProvider (package acme/blog, ponta a ponta)
// -----------------------------------------------------------------------------

it('um request a /acme renderiza acme::index, com directivas e condições do package', function (): void {
    $project = projectWithAcme();

    $response = acmeGet($project, '/acme');
    $html = (string) $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and($html)->toContain('PACKAGE-VIEW MUNDO')
        ->and($html)->toContain('IN-ACME')->not->toContain('OUT-ACME')
        ->and($html)->toContain('ROLE-EDITOR')->not->toContain('ROLE-OTHER');
});

it('as views do projecto em resources/views/vendor/acme vencem as do package', function (): void {
    $project = projectWithAcme(['resources/views/vendor/acme/index.blade.php' => 'PROJECT-OVERRIDE {{ $who }}']);

    expect((string) acmeGet($project, '/acme')->getContent())->toBe('PROJECT-OVERRIDE mundo');
});

it('o middleware alias do package aplica-se e o grupo web recebe o middleware do package', function (): void {
    $project = projectWithAcme();

    $guarded = acmeGet($project, '/acme/guarded');
    $plain = acmeGet($project, '/acme');

    expect($guarded->getHeader('X-Acme'))->toBe('alias')
        ->and($plain->getHeader('X-Acme'))->toBeNull()
        ->and($plain->getHeader('X-Acme-Group'))->toBe('web')
        ->and($project->app->make(Slendie\Routing\Router::class)->getMiddleware())->toHaveKey('acme');
});

it('as traduções do package funcionam e o projecto sobrepõe em lang/vendor/acme', function (): void {
    $project = projectWithAcme();
    $project->app->bootstrap();

    expect(__('acme::m.hi'))->toBe('Olá do package');

    $override = projectWithAcme(['lang/vendor/acme/pt/m.php' => "<?php\nreturn ['hi' => 'Olá do projecto'];\n"]);
    $override->app->bootstrap();

    expect(__('acme::m.hi'))->toBe('Olá do projecto');
});

it('mergeConfigFrom: defaults do package com os valores do projecto por cima (merge raso)', function (): void {
    $plain = projectWithAcme();
    $plain->app->bootstrap();

    expect(config('acme.greeting'))->toBe('from-package')->and(config('acme.limit'))->toBe(10);

    $custom = projectWithAcme(['config/acme.php' => "<?php\nreturn ['greeting' => 'from-project', 'extra' => ['a' => 1]];\n"]);
    $custom->app->bootstrap();

    expect(config('acme.greeting'))->toBe('from-project')
        ->and(config('acme.limit'))->toBe(10)
        ->and(config('acme.extra'))->toBe(['a' => 1]);
});

it('as migrações do package correm com migrate', function (): void {
    $project = projectWithAcme();

    [$code, $output] = $project->artisan('migrate');

    expect($code)->toBe(0)
        ->and($output)->toContain('create_acme_posts_table')
        ->and($project->app->make('db.schema')->hasTable('acme_posts'))->toBeTrue();
});

it('o comando do package aparece em list e corre', function (): void {
    $project = projectWithAcme();

    [, $list] = $project->artisan('list');
    [$code, $output] = $project->artisan('acme:hello');

    expect($list)->toContain('acme:hello')
        ->and($code)->toBe(0)
        ->and($output)->toContain('Hello from Acme');
});

it('vendor:publish --tag=acme-config copia a config e respeita ficheiros existentes', function (): void {
    $project = projectWithAcme();

    [$code] = $project->artisan('vendor:publish --tag=acme-config');

    expect($code)->toBe(0)->and($project->has('config/acme.php'))->toBeTrue();

    $project->put('config/acme.php', 'EDITADO');
    [, $output] = $project->artisan('vendor:publish --tag=acme-config');

    expect($output)->toContain('SKIPPED')->and($project->read('config/acme.php'))->toBe('EDITADO');

    $project->artisan('vendor:publish --tag=acme-config --force');
    expect($project->read('config/acme.php'))->toContain('from-package');
});

it('vendor:publish --tag=acme-views copia as views para resources/views/vendor/acme', function (): void {
    $project = projectWithAcme();

    $project->artisan('vendor:publish --tag=acme-views');

    expect($project->has('resources/views/vendor/acme/index.blade.php'))->toBeTrue();
});

it('publishesMigrations renomeia o timestamp para agora e não duplica ao republicar', function (): void {
    $project = projectWithAcme();

    $project->artisan('vendor:publish --tag=acme-migrations');

    $files = array_map('basename', glob($project->path('database/migrations/*.php')) ?: []);

    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('_create_acme_posts_table.php')
        ->and($files[0])->not->toStartWith('2024_01_01_000000')
        ->and($files[0])->toStartWith(date('Y_m_d_'));

    $project->artisan('vendor:publish --tag=acme-migrations');

    expect(glob($project->path('database/migrations/*.php')))->toHaveCount(1);
});

it('about lista os packages descobertos', function (): void {
    [, $output] = projectWithAcme()->artisan('about');

    expect($output)->toContain('acme/blog')->and($output)->toContain('Acme\\Blog\\AcmeServiceProvider');
});

// -----------------------------------------------------------------------------
// Providers adiados e Blade::if
// -----------------------------------------------------------------------------

it('um provider adiado só regista quando um dos seus serviços é resolvido', function (): void {
    DeferredProvider::$registered = 0;
    $project = ConsoleProject::make();
    $app = $project->app;

    $app->register(DeferredProvider::class);

    expect(DeferredProvider::$registered)->toBe(0)
        ->and($app->isDeferredService('fixture.clock'))->toBeTrue()
        ->and($app->providerIsLoaded(DeferredProvider::class))->toBeFalse();

    $app->make('fixture.clock');

    expect(DeferredProvider::$registered)->toBe(1)
        ->and($app->providerIsLoaded(DeferredProvider::class))->toBeTrue()
        ->and($app->getProvider(DeferredProvider::class))->toBeInstanceOf(DeferredProvider::class);

    $app->make('fixture.clock');
    expect(DeferredProvider::$registered)->toBe(1);
});

it('addDeferredServices declara serviços adiados à mão', function (): void {
    DeferredProvider::$registered = 0;
    $app = ConsoleProject::make()->app;

    $app->addDeferredServices(['fixture.clock' => DeferredProvider::class]);

    expect($app->isDeferredService('fixture.clock'))->toBeTrue()
        ->and($app->make('fixture.clock'))->toBeInstanceOf(Slendie\Tests\Fixtures\Clock::class)
        ->and(DeferredProvider::$registered)->toBe(1);
});

it('Blade::if cria @nome / @elsenome / @endnome e passa os argumentos', function (): void {
    $app = ConsoleProject::make()->app;
    $app->bootstrap();

    Blade::if('admin', static fn (): bool => false);
    Blade::if('role', static fn (string $name): bool => $name === 'editor');

    $compiled = Blade::compileString("@admin A @elseadmin B @endadmin @role('editor') C @endrole");

    expect($compiled)->toContain("BladeCompiler::check('admin')")
        ->and($compiled)->toContain("BladeCompiler::check('role', 'editor')")
        ->and($compiled)->toContain('elseif')
        ->and($compiled)->toContain('endif');

    expect(BladeCompiler::check('admin'))->toBeFalse()
        ->and(BladeCompiler::check('role', 'editor'))->toBeTrue()
        ->and(fn () => BladeCompiler::check('nope'))->toThrow(InvalidArgumentException::class);
});

it('o Config do projecto é o mesmo que o provider vê', function (): void {
    $project = projectWithAcme();
    $project->app->bootstrap();

    expect($project->app->make(Config::class)->get('acme.greeting'))->toBe('from-package');
});
