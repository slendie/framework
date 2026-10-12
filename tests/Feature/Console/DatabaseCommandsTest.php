<?php

declare(strict_types=1);

use Slendie\Database\DatabaseManager;
use Slendie\Tests\Fixtures\Console\ConsoleProject;
use Slendie\Tests\Fixtures\Database\MigrationHelpers as M;

require_once dirname(__DIR__, 4).'/skeleton/database/seeders/DatabaseSeeder.php';
require_once dirname(__DIR__, 4).'/skeleton/database/seeders/LeadSeeder.php';

afterEach(function (): void {
    ConsoleProject::cleanup();
});

/** Projecto com as migrações `users` (batch 1) pronta e a `posts` escrita à parte. */
function migratable(string $database = 'file'): ConsoleProject
{
    $project = ConsoleProject::make(database: $database);

    M::createTable($project->path('database/migrations'), '2026_01_01_000000_create_users_table', 'users');

    return $project;
}

function tables(ConsoleProject $project): array
{
    /** @var DatabaseManager $db */
    $db = $project->app->make(DatabaseManager::class);

    return $db->connection()->getSchemaBuilder()->getTables();
}

it('migrate cria o ficheiro SQLite (sem perguntar com --force) e corre as pendentes', function (): void {
    $project = migratable();

    expect($project->has('database/database.sqlite'))->toBeFalse();

    [$code, $output] = $project->artisan('migrate --force');

    expect($code)->toBe(0)
        ->and($output)->toContain('SQLite database [')
        ->and($output)->toContain('created successfully')
        ->and($output)->toContain('Migrating: 2026_01_01_000000_create_users_table')
        ->and($output)->toMatch('/Migrated:  2026_01_01_000000_create_users_table \(\d+\.\d+ms\)/')
        ->and($project->has('database/database.sqlite'))->toBeTrue()
        ->and(tables($project))->toContain('users', 'migrations');
});

it('migrate pergunta antes de criar o ficheiro e respeita um "não"', function (): void {
    $project = migratable();

    $stream = fopen('php://memory', 'w+');
    fwrite($stream, "n\n");
    rewind($stream);
    $project->console->setInputStream($stream)->setInteractive(null);

    [$code, $output] = $project->artisan('migrate');

    expect($code)->toBe(1)
        ->and($output)->toContain('does not exist. Create it?')
        ->and($output)->toContain('Database file not created.')
        ->and($project->has('database/database.sqlite'))->toBeFalse();
});

it('migrate sem terminal cria o ficheiro (a pergunta tem "sim" por omissão)', function (): void {
    $project = migratable();

    [$code] = $project->artisan('migrate');

    expect($code)->toBe(0)->and($project->has('database/database.sqlite'))->toBeTrue();
});

it('migrate de novo não tem nada para fazer; em :memory: não cria ficheiros', function (): void {
    $project = migratable('memory');

    $project->artisan('migrate');
    [$code, $output] = $project->artisan('migrate');

    expect($code)->toBe(0)
        ->and($output)->toContain('Nothing to migrate.')
        ->and($output)->not->toContain('SQLite database')
        ->and(glob($project->path('database/*.sqlite')))->toBeEmpty();
});

it('migrate --pretend imprime o SQL e não altera nada', function (): void {
    $project = migratable('memory');

    [$code, $output] = $project->artisan('migrate --pretend');

    expect($code)->toBe(0)
        ->and($output)->toContain('create table "users"')
        ->and(tables($project))->not->toContain('users');
});

it('migrate --step grava um batch por migração', function (): void {
    $project = migratable('memory');
    M::createTable($project->path('database/migrations'), '2026_01_02_000000_create_posts_table', 'posts');

    $project->artisan('migrate --step');
    [, $status] = $project->artisan('migrate:status');

    expect($status)->toMatch('/create_users_table\s+\│?\s*1\s/u')
        ->and($status)->toMatch('/create_posts_table\s+\│?\s*2\s/u');
});

it('migrate:status mostra Ran/Pending e --pending devolve 1 se houver pendentes', function (): void {
    $project = migratable('memory');

    [$code, $output] = $project->artisan('migrate:status');

    expect($code)->toBe(0)
        ->and($output)->toContain('Migration')
        ->and($output)->toContain('Batch')
        ->and($output)->toContain('Status')
        ->and($output)->toContain('2026_01_01_000000_create_users_table')
        ->and($output)->toContain('Pending');

    [$code, $output] = $project->artisan('migrate:status --pending');

    expect($code)->toBe(1)->and($output)->toContain('create_users_table')->and($output)->toContain('Pending');

    $project->artisan('migrate');

    [$code, $output] = $project->artisan('migrate:status');

    expect($code)->toBe(0)->and($output)->toContain('Ran')->and($output)->not->toContain('Pending');

    [$code, $output] = $project->artisan('migrate:status --pending');

    expect($code)->toBe(0)->and($output)->toContain('No pending migrations.');
});

it('migrate:status sem migrações avisa', function (): void {
    [$code, $output] = ConsoleProject::make(database: 'memory')->artisan('migrate:status');

    expect($code)->toBe(0)->and($output)->toContain('No migrations found.');
});

it('migrate:rollback reverte o último batch, --step N batches e --batch um concreto', function (): void {
    $project = migratable('memory');
    $dir = $project->path('database/migrations');

    $project->artisan('migrate');
    M::createTable($dir, '2026_01_02_000000_create_posts_table', 'posts');
    $project->artisan('migrate');
    M::createTable($dir, '2026_01_03_000000_create_tags_table', 'tags');
    $project->artisan('migrate');

    expect(tables($project))->toContain('users', 'posts', 'tags');

    [$code, $output] = $project->artisan('migrate:rollback');

    expect($code)->toBe(0)
        ->and($output)->toContain('Rolling back: 2026_01_03_000000_create_tags_table')
        ->and($output)->toContain('Rolled back:  2026_01_03_000000_create_tags_table')
        ->and(tables($project))->not->toContain('tags')->toContain('posts', 'users');

    $project->artisan('migrate:rollback --batch=1');

    expect(tables($project))->not->toContain('users')->toContain('posts');

    $project->artisan('migrate');
    $project->artisan('migrate:rollback --step=2');

    expect(tables($project))->not->toContain('users', 'posts', 'tags');
});

it('migrate:rollback --pretend e validações', function (): void {
    $project = migratable('memory');
    $project->artisan('migrate');

    [$code, $output] = $project->artisan('migrate:rollback --pretend');

    expect($code)->toBe(0)
        ->and($output)->toContain('drop table if exists "users"')
        ->and(tables($project))->toContain('users');

    expect($project->artisan('migrate:rollback --step=0')[0])->toBe(2)
        ->and($project->artisan('migrate:rollback --step=abc')[0])->toBe(2)
        ->and($project->artisan('migrate:rollback --batch=x')[0])->toBe(2);
});

it('migrate:rollback sem nada migrado avisa', function (): void {
    [$code, $output] = ConsoleProject::make(database: 'memory')->artisan('migrate:rollback');

    expect($code)->toBe(0)->and($output)->toContain('Nothing to rollback.');
});

it('migrate:reset reverte tudo e migrate:install cria o repositório', function (): void {
    $project = migratable('memory');

    [$code, $output] = $project->artisan('migrate:install');

    expect($code)->toBe(0)
        ->and($output)->toContain('Migration table created successfully.')
        ->and(tables($project))->toContain('migrations');

    [, $again] = $project->artisan('migrate:install');

    expect($again)->toContain('already exists');

    $project->artisan('migrate');
    M::createTable($project->path('database/migrations'), '2026_01_02_000000_create_posts_table', 'posts');
    $project->artisan('migrate');

    [$code, $output] = $project->artisan('migrate:reset');

    expect($code)->toBe(0)
        ->and($output)->toContain('Rolled back:  2026_01_02_000000_create_posts_table')
        ->and(tables($project))->not->toContain('users', 'posts');

    [, $pretend] = $project->artisan('migrate:reset --pretend');

    expect($pretend)->not->toContain('Rolled back');
});

it('migrate:refresh reverte tudo e corre de novo; --seed popula', function (): void {
    $project = migratable('memory');
    $project->artisan('migrate');

    [$code, $output] = $project->artisan('migrate:refresh');

    expect($code)->toBe(0)
        ->and($output)->toContain('Rolled back:  2026_01_01_000000_create_users_table')
        ->and($output)->toContain('Migrated:  2026_01_01_000000_create_users_table')
        ->and(tables($project))->toContain('users');
});

it('migrate:fresh apaga tudo e corre de novo', function (): void {
    $project = migratable('memory');
    $project->artisan('migrate');

    /** @var DatabaseManager $db */
    $db = $project->app->make(DatabaseManager::class);
    $db->connection()->getSchemaBuilder()->create('orfa', function ($table): void {
        $table->id();
    });

    [$code, $output] = $project->artisan('migrate:fresh');

    expect($code)->toBe(0)
        ->and($output)->toContain('Dropping all tables.')
        ->and($output)->toContain('Migrated:  2026_01_01_000000_create_users_table')
        ->and(tables($project))->toContain('users')->not->toContain('orfa');
});

/** As migrações e seeders do skeleton (leads) dentro de um projecto temporário. */
function withSkeletonLeads(): ConsoleProject
{
    $project = ConsoleProject::make(database: 'memory');

    $project->put(
        'database/migrations/2026_01_01_000000_create_leads_table.php',
        (string) file_get_contents(dirname(__DIR__, 4).'/skeleton/database/migrations/2026_01_01_000000_create_leads_table.php'),
    );

    return $project;
}

it('migrate --seed corre o DatabaseSeeder', function (): void {
    $project = withSkeletonLeads();

    [$code, $output] = $project->artisan('migrate --seed');

    /** @var DatabaseManager $db */
    $db = $project->app->make(DatabaseManager::class);

    expect($code)->toBe(0)
        ->and($output)->toContain('Seeding: Database\\Seeders\\DatabaseSeeder')
        ->and($output)->toMatch('/DONE \(\d+ms\)/')
        ->and($db->connection()->table('leads')->count())->toBe(3);
});

it('migrate:fresh --seed e migrate:refresh --seed repõem os dados', function (): void {
    $project = withSkeletonLeads();
    $project->artisan('migrate --seed');

    /** @var DatabaseManager $db */
    $db = $project->app->make(DatabaseManager::class);

    $project->artisan('migrate:fresh --seed');
    expect($db->connection()->table('leads')->count())->toBe(3);

    $project->artisan('migrate:refresh --seed');
    expect($db->connection()->table('leads')->count())->toBe(3);

    $project->artisan('migrate:fresh');
    expect($db->connection()->table('leads')->count())->toBe(0);
});

it('db:seed --class corre um seeder concreto; classes inválidas falham', function (): void {
    $project = withSkeletonLeads();
    $project->artisan('migrate');

    /** @var DatabaseManager $db */
    $db = $project->app->make(DatabaseManager::class);

    [$code, $output] = $project->artisan('db:seed --class=Database\\Seeders\\LeadSeeder');

    expect($code)->toBe(0)
        ->and($output)->toContain('Seeding: Database\\Seeders\\LeadSeeder')
        ->and($db->connection()->table('leads')->count())->toBe(3);

    // Barra inicial tolerada.
    [$code] = $project->artisan('db:seed --class=\\Database\\Seeders\\LeadSeeder');
    expect($code)->toBe(0)->and($db->connection()->table('leads')->count())->toBe(6);

    [$code, $output] = $project->artisan('db:seed --class=Nao\\Existe');
    expect($code)->toBe(1)->and($output)->toContain('Seeder class [Nao\\Existe] does not exist.');

    [$code, $output] = $project->artisan('db:seed --class=stdClass');
    expect($code)->toBe(1)->and($output)->toContain('is not a seeder');
});

it('db:seed usa o DatabaseSeeder por omissão', function (): void {
    $project = withSkeletonLeads();
    $project->artisan('migrate');

    [$code, $output] = $project->artisan('db:seed');

    expect($code)->toBe(0)->and($output)->toContain('Database\\Seeders\\DatabaseSeeder');
});

it('db:wipe apaga todas as tabelas', function (): void {
    $project = migratable('memory');
    $project->artisan('migrate');

    [$code, $output] = $project->artisan('db:wipe');

    expect($code)->toBe(0)
        ->and($output)->toContain('Dropped all tables successfully.')
        ->and(tables($project))->toBe([]);
});

it('os comandos de migração usam as pastas registadas no Migrator (packages)', function (): void {
    $project = migratable('memory');
    $extra = $project->path('packages/blog/migrations');
    mkdir($extra, 0777, true);
    M::createTable($extra, '2026_02_01_000000_create_blog_posts_table', 'blog_posts');

    $project->artisan('env'); // arranca a aplicação
    $project->app->make(Slendie\Database\Migrations\Migrator::class)->path($extra);

    [, $output] = $project->artisan('migrate');

    expect($output)->toContain('create_blog_posts_table')
        ->and(tables($project))->toContain('users', 'blog_posts');
});
