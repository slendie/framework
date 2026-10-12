<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\Console\ConsoleProject;

afterEach(fn () => ConsoleProject::cleanup());

/** O ficheiro gerado tem de ser PHP válido. */
function lint(ConsoleProject $project, string $path): void
{
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($project->path($path)).' 2>&1', $output, $code);

    expect($code)->toBe(0, implode("\n", $output));
}

it('make:controller plain', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('make:controller HomeController');

    expect($code)->toBe(0)
        ->and($output)->toContain('INFO')
        ->and($output)->toContain('Controller [app/Http/Controllers/HomeController.php] created successfully.');

    $source = $project->read('app/Http/Controllers/HomeController.php');

    expect($source)
        ->toContain('namespace App\\Http\\Controllers;')
        ->toContain('use Slendie\\Http\\Controller;')
        ->toContain('final class HomeController extends Controller')
        ->not->toContain('{{');

    lint($project, 'app/Http/Controllers/HomeController.php');
});

it('make:controller --resource, --api e --invokable', function (): void {
    $project = ConsoleProject::make();

    $project->artisan('make:controller PostController --resource');
    $project->artisan('make:controller ApiPostController --api');
    $project->artisan('make:controller ShowPost --invokable');

    $resource = $project->read('app/Http/Controllers/PostController.php');
    $api = $project->read('app/Http/Controllers/ApiPostController.php');
    $invokable = $project->read('app/Http/Controllers/ShowPost.php');

    foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'] as $method) {
        expect($resource)->toContain("public function {$method}(");
    }

    expect($api)->toContain('public function index(')
        ->toContain('public function destroy(')
        ->not->toContain('public function create(')
        ->not->toContain('public function edit(')
        ->and($invokable)->toContain('public function __invoke(Request $request)')
        ->not->toContain('public function index(');

    foreach (['PostController', 'ApiPostController', 'ShowPost'] as $name) {
        lint($project, "app/Http/Controllers/{$name}.php");
    }
});

it('make:controller --model liga o type-hint e o import (com sub-pastas)', function (): void {
    $project = ConsoleProject::make();

    $project->artisan('make:controller Admin/LeadController --model=Lead');

    $source = $project->read('app/Http/Controllers/Admin/LeadController.php');

    expect($source)
        ->toContain('namespace App\\Http\\Controllers\\Admin;')
        ->toContain('use App\\Models\\Lead;')
        ->toContain('public function show(Lead $lead)')
        ->toContain('public function update(Request $request, Lead $lead)')
        ->toContain('public function create()');

    lint($project, 'app/Http/Controllers/Admin/LeadController.php');

    // Model já qualificado e --api (sem create/edit).
    $project->artisan('make:controller Api/Lead2Controller --api --model=App\\Domain\\Contact');

    $api = $project->read('app/Http/Controllers/Api/Lead2Controller.php');

    expect($api)->toContain('use App\\Domain\\Contact;')
        ->toContain('public function show(Contact $contact)')
        ->not->toContain('public function create(')
        ->not->toContain('public function edit(');

    lint($project, 'app/Http/Controllers/Api/Lead2Controller.php');
});

it('os make:* recusam ficheiros existentes sem --force e substituem com --force', function (): void {
    $project = ConsoleProject::make();
    $project->artisan('make:controller HomeController');
    $project->put('app/Http/Controllers/HomeController.php', '<?php // editado');

    [$code, $output] = $project->artisan('make:controller HomeController');

    expect($code)->toBe(1)
        ->and($output)->toContain('Controller already exists: [app/Http/Controllers/HomeController.php]')
        ->and($project->read('app/Http/Controllers/HomeController.php'))->toBe('<?php // editado');

    [$code] = $project->artisan('make:controller HomeController --force');

    expect($code)->toBe(0)
        ->and($project->read('app/Http/Controllers/HomeController.php'))->toContain('final class HomeController');
});

it('recusa nomes inválidos ou que fogem da pasta', function (string $name): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('make:controller '.$name);

    expect($code)->toBe(1)->and($output)->toContain('Nome inválido');
    expect(glob($project->path('app/Http/Controllers/*')))->toBeEmpty();
})->with(['../Evil', '..\\Evil', 'Foo/../Bar', '1Invalid', 'has-dash', 'com espaço' => 'a.b']);

it('usa o stub do projecto quando existe', function (): void {
    $project = ConsoleProject::make(['stubs/middleware.stub' => "<?php\n// meu stub para {{ class }} em {{ namespace }} ({{rootNamespace}})\n"]);

    $project->artisan('make:middleware Auditoria');

    expect($project->read('app/Http/Middleware/Auditoria.php'))
        ->toBe("<?php\n// meu stub para Auditoria em App\\Http\\Middleware (App\\)\n");
});

it('o namespace raiz vem do composer.json (PSR-4 de app/)', function (): void {
    $project = ConsoleProject::make(['composer.json' => json_encode(['autoload' => ['psr-4' => ['Acme\\' => 'app/', 'Database\\Seeders\\' => 'database/seeders/']]])]);

    $project->artisan('make:controller Site/HomeController');
    $project->artisan('make:model Post');

    expect($project->read('app/Http/Controllers/Site/HomeController.php'))->toContain('namespace Acme\\Http\\Controllers\\Site;')
        ->and($project->read('app/Models/Post.php'))->toContain('namespace Acme\\Models;');

    // Um nome já qualificado com o namespace raiz não é duplicado.
    $project->artisan('make:middleware Acme\\Http\\Middleware\\Custom');

    expect($project->has('app/Http/Middleware/Custom.php'))->toBeTrue();
});

it('make:model cria só o model e a classe é final com $fillable', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('make:model Lead');

    expect($code)->toBe(0)
        ->and($output)->toContain('Model [app/Models/Lead.php] created successfully.')
        ->and($project->read('app/Models/Lead.php'))
        ->toContain('namespace App\\Models;')
        ->toContain('final class Lead extends Model')
        ->toContain('protected array $fillable = [];')
        ->and(glob($project->path('database/migrations/*')))->toBeEmpty()
        ->and($project->has('app/Http/Controllers/LeadController.php'))->toBeFalse();

    lint($project, 'app/Models/Lead.php');
});

it('make:model -mcr cria model, migração e controller resource', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('make:model Lead -mcr');

    expect($code)->toBe(0)
        ->and($output)->toContain('Model [app/Models/Lead.php] created successfully.')
        ->and($output)->toMatch('#Migration \[database/migrations/\d{4}_\d{2}_\d{2}_\d{6}_create_leads_table\.php\] created successfully\.#')
        ->and($output)->toContain('Controller [app/Http/Controllers/LeadController.php] created successfully.');

    $migrations = glob($project->path('database/migrations/*_create_leads_table.php'));
    $controller = $project->read('app/Http/Controllers/LeadController.php');

    expect($migrations)->toHaveCount(1)
        ->and(file_get_contents($migrations[0]))->toContain("Schema::create('leads'")
        ->and($controller)->toContain('use App\\Models\\Lead;')
        ->toContain('public function show(Lead $lead)')
        ->toContain('public function create()');

    lint($project, 'app/Http/Controllers/LeadController.php');
});

it('make:model com formas longas, -c simples, -a e -s', function (): void {
    $project = ConsoleProject::make();

    $project->artisan('make:model Post --migration');

    expect(glob($project->path('database/migrations/*_create_posts_table.php')))->toHaveCount(1)
        ->and($project->has('app/Http/Controllers/PostController.php'))->toBeFalse();

    $project->artisan('make:model Tag -c');

    expect($project->read('app/Http/Controllers/TagController.php'))->not->toContain('public function index(');

    $project->artisan('make:model Category -a');

    expect($project->has('app/Models/Category.php'))->toBeTrue()
        ->and(glob($project->path('database/migrations/*_create_categories_table.php')))->toHaveCount(1)
        ->and($project->read('app/Http/Controllers/CategoryController.php'))->toContain('public function show(Category $category)')
        ->and($project->has('database/seeders/CategorySeeder.php'))->toBeTrue();

    $project->artisan('make:model Note -s');

    expect($project->has('database/seeders/NoteSeeder.php'))->toBeTrue();
});

it('make:model em sub-pasta usa a tabela do nome final', function (): void {
    $project = ConsoleProject::make();

    $project->artisan('make:model Admin/Person -mr');

    expect($project->has('app/Models/Admin/Person.php'))->toBeTrue()
        ->and(glob($project->path('database/migrations/*_create_people_table.php')))->toHaveCount(1)
        ->and($project->read('app/Http/Controllers/Admin/PersonController.php'))->toContain('use App\\Models\\Admin\\Person;');
});

it('make:model para se o model já existe; se a migração já existe segue com o resto', function (): void {
    $project = ConsoleProject::make();
    $project->artisan('make:model Lead');

    [$code, $output] = $project->artisan('make:model Lead -mc');

    expect($code)->toBe(1)
        ->and($output)->toContain('Model already exists')
        ->and(glob($project->path('database/migrations/*')))->toBeEmpty();

    $project->artisan('make:migration create_posts_table');

    [$code, $output] = $project->artisan('make:model Post -mc');

    expect($code)->toBe(1)
        ->and($output)->toContain('Já existe uma migração com o nome [create_posts_table]')
        ->and($project->has('app/Http/Controllers/PostController.php'))->toBeTrue();
});

it('make:migration infere a tabela e o modo pelo nome', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('make:migration create_leads_table');

    expect($code)->toBe(0)->and($output)->toContain('Migration [database/migrations/');

    $create = glob($project->path('database/migrations/*_create_leads_table.php'))[0];

    expect(file_get_contents($create))->toContain("Schema::create('leads'");

    $project->artisan('make:migration add_phone_to_leads_table');

    $update = glob($project->path('database/migrations/*_add_phone_to_leads_table.php'))[0];

    expect(file_get_contents($update))->toContain("Schema::table('leads'");

    $project->artisan('make:migration do_something');

    $blank = glob($project->path('database/migrations/*_do_something.php'))[0];

    expect(file_get_contents($blank))->not->toContain('Schema::create')->not->toContain('{{');
});

it('make:migration --create, --table e --path', function (): void {
    $project = ConsoleProject::make();

    $project->artisan('make:migration build_things --create=things');
    $project->artisan('make:migration tweak_things --table=things');
    $project->artisan('make:migration other_place --create=others --path=extra/migrations');

    expect(file_get_contents(glob($project->path('database/migrations/*_build_things.php'))[0]))->toContain("Schema::create('things'")
        ->and(file_get_contents(glob($project->path('database/migrations/*_tweak_things.php'))[0]))->toContain("Schema::table('things'")
        ->and(glob($project->path('extra/migrations/*_other_place.php')))->toHaveCount(1);
});

it('make:migration recusa nomes inválidos e duplicados', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('make:migration "bad name"');

    expect($code)->toBe(1)->and($output)->toContain('Nome de migração inválido');

    $project->artisan('make:migration create_leads_table');
    [$code, $output] = $project->artisan('make:migration create_leads_table');

    expect($code)->toBe(1)->and($output)->toContain('Já existe uma migração');
});

it('make:middleware, make:command, make:seeder e make:view', function (): void {
    $project = ConsoleProject::make();

    $project->artisan('make:middleware EnsureAdmin');
    $project->artisan('make:command SendReport --command=report:send');
    $project->artisan('make:command Plain');
    $project->artisan('make:seeder LeadSeeder');
    $project->artisan('make:seeder Admin/UserSeeder');

    expect($project->read('app/Http/Middleware/EnsureAdmin.php'))
        ->toContain('final class EnsureAdmin')
        ->toContain('public function handle(Request $request, Closure $next): Response');

    expect($project->read('app/Console/Commands/SendReport.php'))
        ->toContain("protected string \$signature = 'report:send';")
        ->toContain('extends Command')
        ->and($project->read('app/Console/Commands/Plain.php'))->toContain("'app:name'");

    expect($project->read('database/seeders/LeadSeeder.php'))
        ->toContain('namespace Database\\Seeders;')
        ->toContain('class LeadSeeder extends Seeder')
        ->and($project->read('database/seeders/Admin/UserSeeder.php'))->toContain('class UserSeeder extends Seeder');

    foreach (['app/Http/Middleware/EnsureAdmin.php', 'app/Console/Commands/SendReport.php', 'database/seeders/LeadSeeder.php'] as $file) {
        lint($project, $file);
    }

    [$code, $output] = $project->artisan('make:view admin.leads.index');

    expect($code)->toBe(0)
        ->and($output)->toContain('View [resources/views/admin/leads/index.blade.php] created successfully.')
        ->and($project->read('resources/views/admin/leads/index.blade.php'))
        ->toContain("@extends('layouts.app')")
        ->toContain("@section('content')")
        ->toContain('admin.leads.index');

    $project->artisan('make:view contacto');

    expect($project->has('resources/views/contacto.blade.php'))->toBeTrue();

    [$code, $output] = $project->artisan('make:view ../fora');

    expect($code)->toBe(1)->and($output)->toContain('Nome de view inválido');
});

it('o comando criado por make:command é descoberto e corre', function (): void {
    $name = 'Report'.bin2hex(random_bytes(3));
    $project = ConsoleProject::make();

    $project->artisan("make:command {$name} --command=report:run");
    require_once $project->path("app/Console/Commands/{$name}.php");

    $project->console->add("App\\Console\\Commands\\{$name}");

    expect($project->artisan('report:run'))->toBe([0, "Feito.\n"]);
});

it('make:provider cria o provider e cria/actualiza bootstrap/providers.php', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('make:provider AppServiceProvider');

    expect($code)->toBe(0)
        ->and($output)->toContain('Provider [app/Providers/AppServiceProvider.php] created successfully.')
        ->and($output)->toContain('Provider registered in [bootstrap/providers.php]')
        ->and($project->read('app/Providers/AppServiceProvider.php'))
        ->toContain('final class AppServiceProvider extends ServiceProvider')
        ->toContain('public function register(): void')
        ->and($project->read('bootstrap/providers.php'))->toContain('\\App\\Providers\\AppServiceProvider::class,');

    lint($project, 'app/Providers/AppServiceProvider.php');
    lint($project, 'bootstrap/providers.php');
});

it('make:provider acrescenta a arrays existentes sem os estragar', function (string $initial, array $expected): void {
    $project = ConsoleProject::make(['bootstrap/providers.php' => $initial]);

    $project->artisan('make:provider BlogServiceProvider');

    $providers = require $project->path('bootstrap/providers.php');

    expect($providers)->toBe($expected);
    lint($project, 'bootstrap/providers.php');
})->with([
    'vazio numa linha' => ["<?php\nreturn [];\n", ['App\\Providers\\BlogServiceProvider']],
    'vazio multi-linha' => ["<?php\nreturn [\n];\n", ['App\\Providers\\BlogServiceProvider']],
    'só comentário' => ["<?php\nreturn [\n    // Foo::class,\n];\n", ['App\\Providers\\BlogServiceProvider']],
    'sem vírgula final' => ["<?php\nreturn [\n    Slendie\Tests\Fixtures\Providers\DeferredProvider::class\n];\n", ['Slendie\Tests\Fixtures\Providers\DeferredProvider', 'App\\Providers\\BlogServiceProvider']],
    'com vírgula final' => ["<?php\nreturn [\n    Slendie\Tests\Fixtures\Providers\DeferredProvider::class,\n];\n", ['Slendie\Tests\Fixtures\Providers\DeferredProvider', 'App\\Providers\\BlogServiceProvider']],
    'com declare e docblock' => ["<?php\n\ndeclare(strict_types=1);\n\n/* providers */\nreturn [\n    Slendie\Tests\Fixtures\Providers\ConfigListedProvider::class,\n    Slendie\Tests\Fixtures\Providers\DeferredProvider::class,\n];\n", ['Slendie\Tests\Fixtures\Providers\ConfigListedProvider', 'Slendie\Tests\Fixtures\Providers\DeferredProvider', 'App\\Providers\\BlogServiceProvider']],
]);

it('make:provider não duplica um provider já registado e avisa se não consegue editar', function (): void {
    $project = ConsoleProject::make(['bootstrap/providers.php' => "<?php\nreturn [\n    \\App\\Providers\\BlogServiceProvider::class,\n];\n"]);

    $project->artisan('make:provider BlogServiceProvider --force');

    expect(substr_count($project->read('bootstrap/providers.php'), 'BlogServiceProvider::class'))->toBe(1);

    $weird = ConsoleProject::make([
        'bootstrap/providers.php' => "<?php\nreturn require __DIR__.'/lista.php';\n",
        'bootstrap/lista.php' => "<?php\nreturn [];\n",
    ]);

    [$code, $output] = $weird->artisan('make:provider Other');

    expect($code)->toBe(0)
        ->and($output)->toContain('Não foi possível editar bootstrap/providers.php')
        ->and($output)->toContain('\\App\\Providers\\Other::class')
        ->and($weird->has('app/Providers/Other.php'))->toBeTrue();
});
