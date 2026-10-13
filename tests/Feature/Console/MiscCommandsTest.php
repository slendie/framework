<?php

declare(strict_types=1);

use Slendie\Console\Commands\ServeCommand;
use Slendie\Foundation\ServiceProvider;
use Slendie\Tests\Fixtures\Console\CommandProvider;
use Slendie\Tests\Fixtures\Console\ConsoleProject;

afterEach(function (): void {
    ConsoleProject::cleanup();
    ServiceProvider::flushPublishes();
});

const ROUTES = <<<'PHP'
    <?php

    use Slendie\Support\Facades\Route;

    Route::get('/', fn () => 'home')->name('home');
    Route::get('/leads', [Slendie\Tests\Fixtures\Http\PostController::class, 'index'])->name('admin.leads.index')->middleware('auth');
    Route::post('/contacto', fn () => 'ok')->name('contact.store');
    Route::match(['put', 'patch'], '/leads/{lead}', fn () => 'x')->name('admin.leads.update');
    PHP;

it('route:list mostra Method | URI | Name | Action | Middleware', function (): void {
    [$code, $output] = ConsoleProject::make(['routes/web.php' => ROUTES])->artisan('route:list');

    expect($code)->toBe(0)
        ->and($output)->toContain('Method')
        ->and($output)->toContain('URI')
        ->and($output)->toContain('Name')
        ->and($output)->toContain('Action')
        ->and($output)->toContain('Middleware')
        ->and($output)->toMatch('/GET\s*│ \/\s+│ home\s+│ Closure\s+│ web\s+│/u')
        ->and($output)->toMatch('/admin\.leads\.index\s+│ Slendie\\\\Tests\\\\Fixtures\\\\Http\\\\PostController@index\s+│ web, auth\s+│/u')
        ->and($output)->toMatch('/PUT\|PATCH\s+│ \/leads\/\{lead\}/u')
        ->and($output)->toContain('4 routes');
});

it('route:list filtra por método, nome e caminho', function (): void {
    $project = ConsoleProject::make(['routes/web.php' => ROUTES]);

    [, $post] = $project->artisan('route:list --method=post');
    [, $name] = $project->artisan('route:list --name=admin.');
    [, $path] = $project->artisan('route:list --path=contacto');
    [, $none] = $project->artisan('route:list --path=nada-disto');

    expect($post)->toContain('/contacto')->not->toContain('home')->and($post)->toContain('1 route')
        ->and($name)->toContain('admin.leads.index')->toContain('admin.leads.update')->not->toContain('contact.store')
        ->and($path)->toContain('contact.store')->not->toContain('admin.leads')
        ->and($none)->toContain("doesn't have any routes matching");
});

it('route:list --json devolve JSON válido', function (): void {
    [$code, $output] = ConsoleProject::make(['routes/web.php' => ROUTES])->artisan('route:list --json --path=leads');

    $routes = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($code)->toBe(0)
        ->and($routes)->toHaveCount(2)
        ->and($routes[0])->toBe([
            'method' => 'GET',
            'uri' => '/leads',
            'name' => 'admin.leads.index',
            'action' => 'Slendie\\Tests\\Fixtures\\Http\\PostController@index',
            'middleware' => ['web', 'auth'],
        ])
        ->and($routes[1]['method'])->toBe('PUT|PATCH');
});

it('view:clear apaga só os .php compilados', function (): void {
    $project = ConsoleProject::make([
        'storage/framework/views/aaa.php' => '<?php',
        'storage/framework/views/bbb.php' => '<?php',
        'storage/framework/views/.gitkeep' => '',
    ]);

    [$code, $output] = $project->artisan('view:clear');

    expect($code)->toBe(0)
        ->and($output)->toContain('Compiled views cleared successfully.')
        ->and($project->has('storage/framework/views/aaa.php'))->toBeFalse()
        ->and($project->has('storage/framework/views/bbb.php'))->toBeFalse()
        ->and($project->has('storage/framework/views/.gitkeep'))->toBeTrue();

    [$code] = $project->artisan('view:clear');
    expect($code)->toBe(0); // pasta já vazia
});

it('view:clear respeita config("view.compiled")', function (): void {
    $project = ConsoleProject::make([
        'config/view.php' => "<?php\nreturn ['compiled' => ".var_export(sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie_compiled_x', true)."];\n",
    ]);

    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie_compiled_x';
    @mkdir($dir);
    file_put_contents($dir.DIRECTORY_SEPARATOR.'c.php', '<?php');

    $project->artisan('view:clear');

    expect(file_exists($dir.DIRECTORY_SEPARATOR.'c.php'))->toBeFalse();
    @rmdir($dir);
});

it('key:generate --show só mostra uma chave base64 de 32 bytes', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('key:generate --show');

    expect($code)->toBe(0)
        ->and(trim($output))->toStartWith('base64:')
        ->and(strlen((string) base64_decode(substr(trim($output), 7), true)))->toBe(32)
        ->and($project->read('.env'))->toBe("APP_NAME=Teste\nAPP_KEY=\n");
});

it('key:generate escreve APP_KEY no .env e recusa sobrepor sem --force', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('key:generate');

    expect($code)->toBe(0)->and($output)->toContain('Application key set successfully.');

    $env = $project->read('.env');

    expect($env)->toMatch('/^APP_NAME=Teste\nAPP_KEY=base64:[A-Za-z0-9+\/]{43}=\n$/')
        ->and(config('app.key'))->toStartWith('base64:');

    [$code, $output] = $project->artisan('key:generate');

    expect($code)->toBe(1)
        ->and($output)->toContain('already set')
        ->and($project->read('.env'))->toBe($env);

    [$code] = $project->artisan('key:generate --force');

    expect($code)->toBe(0)->and($project->read('.env'))->not->toBe($env)->toContain('APP_NAME=Teste');
});

it('key:generate cria o .env ou acrescenta a linha em falta', function (): void {
    $project = ConsoleProject::make();
    unlink($project->path('.env'));

    $project->artisan('key:generate');

    expect($project->read('.env'))->toMatch('/^APP_KEY=base64:.{44}\n$/');

    $other = ConsoleProject::make(['.env' => 'APP_NAME=Sem fim de linha']);

    $other->artisan('key:generate');

    expect($other->read('.env'))->toMatch('/^APP_NAME=Sem fim de linha\nAPP_KEY=base64:.{44}\n$/');

    $quoted = ConsoleProject::make(['.env' => "APP_KEY=\"\"\nOUTRA=1\n"]);
    $quoted->artisan('key:generate');

    expect($quoted->read('.env'))->toMatch('/^APP_KEY=base64:.{44}\nOUTRA=1\n$/');
});

it('vendor:publish sem argumentos lista as tags disponíveis', function (): void {
    $project = ConsoleProject::make(['config/app.php' => appConfig(['providers' => [CommandProvider::class]])]);

    [$code, $output] = $project->artisan('vendor:publish');

    expect($code)->toBe(0)
        ->and($output)->toContain('Tags disponíveis')
        ->and($output)->toMatch('/blog-config\s*│ 2/u')
        ->and($output)->toMatch('/slendie-stubs\s*│ \d+/u')
        ->and($output)->toContain('vendor:publish --tag=');
});

it('vendor:publish --tag copia ficheiros e pastas, sem substituir sem --force', function (): void {
    $project = ConsoleProject::make(['config/app.php' => appConfig(['providers' => [CommandProvider::class]])]);

    [$code, $output] = $project->artisan('vendor:publish --tag=blog-config');

    expect($code)->toBe(0)
        ->and($output)->toContain('COPIED')
        ->and($project->read('config/blog.php'))->toContain("'posts_per_page' => 10")
        ->and($project->read('resources/views/vendor/blog/index.blade.php'))->toBe("blog index\n")
        ->and($project->read('resources/views/vendor/blog/partials/item.blade.php'))->toBe("blog item\n");

    $project->put('config/blog.php', '<?php return ["editado" => true];');

    [, $skipped] = $project->artisan('vendor:publish --tag=blog-config');

    expect($skipped)->toContain('SKIPPED')->toContain('use --force')
        ->and($project->read('config/blog.php'))->toBe('<?php return ["editado" => true];');

    $project->artisan('vendor:publish --tag=blog-config --force');

    expect($project->read('config/blog.php'))->toContain("'posts_per_page'");
});

it('vendor:publish --provider e --all', function (): void {
    $project = ConsoleProject::make(['config/app.php' => appConfig(['providers' => [CommandProvider::class]])]);

    $project->artisan('vendor:publish --provider=Slendie\\Tests\\Fixtures\\Console\\CommandProvider');

    expect($project->has('config/blog.php'))->toBeTrue();

    unlink($project->path('config/blog.php'));

    $project->artisan('vendor:publish --all');

    expect($project->has('config/blog.php'))->toBeTrue()
        ->and($project->has('stubs/model.stub'))->toBeTrue();
});

it('vendor:publish com tag ou provider desconhecidos falha', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('vendor:publish --tag=nao-existe');
    expect($code)->toBe(1)->and($output)->toContain('Unable to locate publishable resources for tag [nao-existe]');

    [$code, $output] = $project->artisan('vendor:publish --provider=Nao\\Existe');
    expect($code)->toBe(1)->and($output)->toContain('has nothing to publish');
});

it('vendor:publish --tag=slendie-stubs copia os stubs e o make:* passa a usá-los', function (): void {
    $project = ConsoleProject::make();

    [$code] = $project->artisan('vendor:publish --tag=slendie-stubs');

    expect($code)->toBe(0)
        ->and($project->has('stubs/controller.resource.stub'))->toBeTrue()
        ->and($project->has('stubs/migration.create.stub'))->toBeTrue()
        ->and($project->has('stubs/view.stub'))->toBeTrue();

    $project->put('stubs/model.stub', "<?php\n// modelo personalizado {{ class }}\n");
    $project->artisan('make:model Custom');

    expect($project->read('app/Models/Custom.php'))->toBe("<?php\n// modelo personalizado Custom\n");
});

it('about mostra ambiente, base de dados, providers e packages', function (): void {
    [$code, $output] = ConsoleProject::make()->artisan('about');

    expect($code)->toBe(0)
        ->and($output)->toMatch('/Slendie\s+4\.0\.0\b/')
        ->and($output)->toMatch('/PHP\s+'.preg_quote(PHP_VERSION, '/').'/')
        ->and($output)->toMatch('/Ambiente\s+local/')
        ->and($output)->toMatch('/Debug\s+OFF/')
        ->and($output)->toMatch('/URL\s+http:\/\/localhost/')
        ->and($output)->toMatch('/Timezone\s+UTC/')
        ->and($output)->toMatch('/Ligação\s+sqlite/')
        ->and($output)->toContain('Slendie\\Database\\DatabaseServiceProvider')
        ->and($output)->toContain('Slendie\\Console\\ConsoleServiceProvider')
        ->and($output)->toMatch('/Descobertos\s+nenhum/');
});

it('env mostra o ambiente actual', function (): void {
    expect(ConsoleProject::make()->artisan('env'))->toBe([0, "Current application environment: local\n"]);
});

it('serve: monta o comando do servidor embutido', function (): void {
    $project = ConsoleProject::make();
    $project->artisan('env');

    /** @var ServeCommand $serve */
    $serve = $project->console->find('serve');

    expect($serve->buildCommand('127.0.0.1', 8000))->toBe([
        PHP_BINARY, '-S', '127.0.0.1:8000', '-t', $project->path('public'),
    ])->and($serve->buildCommand('0.0.0.0', 9001)[2])->toBe('0.0.0.0:9001');
});

it('serve: valida as opções e salta portas ocupadas sem lançar nada quando não há livres', function (): void {
    $project = ConsoleProject::make();

    expect($project->artisan('serve --port=abc')[0])->toBe(2)
        ->and($project->artisan('serve --port=70000')[0])->toBe(2)
        ->and($project->artisan('serve --tries=0x')[0])->toBe(2);

    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $port = (int) substr((string) stream_socket_get_name($server, false), strlen('127.0.0.1:'));

    try {
        [$code, $output] = $project->artisan("serve --port={$port} --tries=1");
    } finally {
        fclose($server);
    }

    expect($code)->toBe(1)->and($output)->toContain('Não há nenhuma porta livre em 127.0.0.1');
});

it('serve: arranca o processo do servidor e devolve o código de saída dele', function (): void {
    $project = ConsoleProject::make();
    $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $port = (int) substr((string) stream_socket_get_name($probe, false), strlen('127.0.0.1:'));
    fclose($probe);

    // O projecto não tem pasta public/: o `php -S` recusa-se a arrancar e sai logo com erro (não fica pendurado).
    [$code, $output] = $project->artisan("serve --port={$port}");

    expect($output)->toContain("Server running on [http://127.0.0.1:{$port}]")
        ->and($code)->not->toBe(0);
});
