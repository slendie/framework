<?php

declare(strict_types=1);

use Slendie\Foundation\ServiceProvider;
use Slendie\Tests\Fixtures\Console\ConsoleProject;

afterEach(function (): void {
    ConsoleProject::cleanup();
    ServiceProvider::flushPublishes();
});

function aiProject(): ConsoleProject
{
    $project = ConsoleProject::make([
        'routes/web.php' => <<<'PHP'
            <?php

            use Slendie\Support\Facades\Route;

            Route::get('/', fn () => 'home')->name('home');
            Route::post('/contacto', fn () => 'ok')->name('contact.store')->middleware('csrf');
            PHP,
        'config/app.php' => "<?php\nreturn ['name' => 'Teste', 'env' => 'local', 'debug' => false, 'url' => 'http://localhost', 'key' => 'base64:SEGREDO', 'timezone' => 'UTC', 'locale' => 'pt', 'providers' => []];\n",
        'config/services.php' => "<?php\nreturn ['mail' => ['password' => 'hunter2', 'host' => 'smtp.test', 'api_key' => 'abc'], 'stripe' => ['SECRET_TOKEN' => 'zzz', 'enabled' => true]];\n",
    ]);

    $project->artisan('migrate');
    $project->app->make('db.schema')->create('leads', static function (Slendie\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('email');
        $table->text('note')->nullable();
    });

    return $project;
}

it('ai:context --json emite um único objecto com routes, schema, config e info', function (): void {
    [$code, $output] = aiProject()->artisan('ai:context --json');

    $data = json_decode($output, true);

    expect($code)->toBe(0)
        ->and(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($data)->toHaveKeys(['routes', 'schema', 'config', 'info']);
});

it('routes: como route:list', function (): void {
    [, $output] = aiProject()->artisan('ai:context routes --json');
    $data = json_decode($output, true);

    expect(array_keys($data))->toBe(['routes'])
        ->and($data['routes'])->toHaveCount(2)
        ->and($data['routes'][0])->toMatchArray(['method' => 'GET', 'uri' => '/', 'name' => 'home'])
        ->and($data['routes'][1]['method'])->toBe('POST')
        ->and($data['routes'][1]['middleware'])->toContain('csrf');
});

it('schema: tabelas e colunas', function (): void {
    [, $output] = aiProject()->artisan('ai:context schema --json');
    $schema = json_decode($output, true)['schema'];

    $columns = array_column($schema['leads'], null, 'name');

    expect($schema)->toHaveKeys(['leads', 'migrations'])
        ->and(array_keys($columns))->toBe(['id', 'email', 'note'])
        ->and($columns['note']['nullable'])->toBeTrue()
        ->and($columns['email']['nullable'])->toBeFalse();
});

it('config: sanitiza *KEY*, *PASSWORD*, *SECRET* e *TOKEN* e mantém o resto', function (): void {
    [, $output] = aiProject()->artisan('ai:context config --json');
    $config = json_decode($output, true)['config'];

    expect($config['app']['key'])->toBe('********')
        ->and($config['services']['mail']['password'])->toBe('********')
        ->and($config['services']['mail']['api_key'])->toBe('********')
        ->and($config['services']['stripe']['SECRET_TOKEN'])->toBe('********')
        ->and($config['services']['mail']['host'])->toBe('smtp.test')
        ->and($config['services']['stripe']['enabled'])->toBeTrue()
        ->and($config['database']['connections']['sqlite']['foreign_key_constraints'])->toBeTrue()
        ->and($output)->not->toContain('hunter2')->not->toContain('SEGREDO');
});

it('info: versões, ambiente, providers, packages, middlewares, directivas e comandos', function (): void {
    $project = aiProject();
    $project->app->make(Slendie\View\Compiler\BladeCompiler::class)->directive('hello', fn () => '');

    [, $output] = $project->artisan('ai:context info --json');
    $info = json_decode($output, true)['info'];

    expect($info['slendie'])->toBe(Slendie\Foundation\Application::VERSION)
        ->and($info['php'])->toBe(PHP_VERSION)
        ->and($info['environment'])->toBe('local')
        ->and($info['debug'])->toBeFalse()
        ->and($info['database_driver'])->toBe('sqlite')
        ->and($info['providers'])->toContain(Slendie\Log\LogServiceProvider::class)
        ->and($info['packages'])->toBe([])
        ->and($info['middleware']['global'])->toContain(Slendie\Http\Middleware\TrimStrings::class)
        ->and($info['middleware']['groups'])->toHaveKeys(['web', 'api'])
        ->and($info['middleware']['aliases'])->toHaveKey('csrf')
        ->and($info['blade_directives'])->toContain('hello')
        ->and(array_column($info['commands'], 'name'))->toContain('ai:context', 'package:discover', 'route:list');
});

it('texto: cada secção tem o seu título e conteúdo legível', function (): void {
    [$code, $output] = aiProject()->artisan('ai:context');

    expect($code)->toBe(0)
        ->and($output)->toContain('# routes')->toContain('# schema')->toContain('# config')->toContain('# info')
        ->and($output)->toMatch('/GET\s+\/\s+Closure \[home\]/')
        ->and($output)->toContain('leads')
        ->and($output)->toMatch('/email\s+varchar|email\s+string|email\s+text/i')
        ->and($output)->toContain('app.key = ********')
        ->and($output)->not->toContain('hunter2');
});

it('secção desconhecida falha com mensagem', function (): void {
    [$code, $output] = aiProject()->artisan('ai:context nada');

    expect($code)->toBe(1)->and($output)->toContain('Unknown section [nada]');
});

it('sem base de dados acessível o schema fica vazio em vez de falhar', function (): void {
    $project = ConsoleProject::make([
        'config/database.php' => "<?php\nreturn ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => '/nao/existe/x.sqlite']]];\n",
    ]);

    [$code, $output] = $project->artisan('ai:context schema --json');

    expect($code)->toBe(0)->and(json_decode($output, true))->toBe(['schema' => []]);
});
