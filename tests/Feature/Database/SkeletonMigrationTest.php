<?php

declare(strict_types=1);

use Slendie\Container\Container;
use Slendie\Database\Connection;
use Slendie\Database\DatabaseManager;
use Slendie\Database\Migrations\MigrationRepository;
use Slendie\Database\Migrations\Migrator;
use Slendie\Foundation\Application;
use Slendie\Support\Facade;
use Slendie\Tests\Fixtures\Database\DbHelpers as Db;

/*
| A migração `create_leads_table` do skeleton corre e reverte nos três drivers
| (MySQL/PostgreSQL só com DB_TEST_*_DSN; neles as tabelas `leads` e `migrations` são recriadas).
*/

dataset('skeleton drivers', Db::drivers());

function skeletonPath(string $path = ''): string
{
    return dirname(__DIR__, 4).'/skeleton/database'.($path === '' ? '' : '/'.$path);
}

/** Migrator do skeleton sobre a ligação de teste, com as tabelas do teste removidas. */
function skeletonMigrator(string $driver): array
{
    $connection = Db::live($driver);

    if ($connection === null) {
        test()->markTestSkipped(Db::skipReason($driver));
    }

    $schema = $connection->getSchemaBuilder();
    $schema->dropIfExists('leads');
    $schema->dropIfExists('migrations');

    // As migrações do skeleton usam a facade Schema, que resolve `db.schema` no container.
    $app = new Application(fixturePath('http'));
    $app->bind('db.schema', fn () => $connection->getSchemaBuilder());
    Facade::setFacadeApplication($app);

    $manager = new class ($app, [], null, $connection) extends DatabaseManager {
        public function __construct(Application $app, array $config, $factory, private readonly Connection $fixed)
        {
            parent::__construct($app, $config, $factory);
        }

        public function connection(?string $name = null): Connection
        {
            return $this->fixed;
        }
    };

    $migrator = new Migrator(new MigrationRepository($manager, 'migrations'), $manager, [skeletonPath('migrations')]);

    return [$connection, $migrator, $manager];
}

it('create_leads_table corre e reverte', function (string $driver): void {
    [$db, $migrator] = skeletonMigrator($driver);
    $schema = $db->getSchemaBuilder();

    expect($migrator->run())->toBe(['2026_01_01_000000_create_leads_table'])
        ->and($schema->getColumnListing('leads'))->toBe(['id', 'name', 'email', 'phone', 'message', 'source', 'status', 'ip', 'user_agent', 'created_at', 'updated_at']);

    $db->table('leads')->insert(['name' => 'Ana', 'email' => 'ana@x.pt']);
    $lead = $db->table('leads')->first();

    expect($lead)->toMatchArray(['name' => 'Ana', 'status' => 'new', 'phone' => null]);

    $migrator->rollback();

    expect($schema->hasTable('leads'))->toBeFalse();

    $schema->dropIfExists('migrations');
})->with('skeleton drivers');

it('o DatabaseSeeder do skeleton insere 3 leads', function (): void {
    foreach (['DatabaseSeeder', 'LeadSeeder'] as $seeder) {
        require_once skeletonPath("seeders/{$seeder}.php");
    }

    [$db, $migrator] = skeletonMigrator('sqlite');
    $migrator->run();

    $container = new Container();
    $container->instance(Connection::class, $db);

    (new Database\Seeders\DatabaseSeeder($container))->__invoke();

    expect($db->table('leads')->count())->toBe(3)
        ->and($db->table('leads')->orderBy('id')->pluck('email'))->toBe(['ana@example.com', 'rui@example.com', 'marta@example.com'])
        ->and($db->table('leads')->where('status', 'new')->count())->toBe(2);
});
