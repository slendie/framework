<?php

declare(strict_types=1);

use Slendie\Database\DatabaseServiceProvider;
use Slendie\Database\Migrations\MigrationCreator;
use Slendie\Database\Migrations\MigrationRepository;
use Slendie\Database\Migrations\Migrator;
use Slendie\Database\QueryException;
use Slendie\Foundation\Application;
use Slendie\Support\Config;
use Slendie\Support\Facades\DB;
use Slendie\Support\Facades\Schema;
use Slendie\Tests\Fixtures\Database\MigrationHelpers as M;

afterEach(fn () => M::cleanup());

/** Três migrações ordenadas: leads, notes, tags. */
function threeMigrations(): array
{
    $dir = M::dir();
    M::createTable($dir, '2026_01_01_000001_create_leads_table', 'leads');
    M::createTable($dir, '2026_01_01_000002_create_notes_table', 'notes');
    M::createTable($dir, '2026_01_01_000003_create_tags_table', 'tags');

    return [$dir, M::migrator([$dir])];
}

// -----------------------------------------------------------------------------
// run
// -----------------------------------------------------------------------------

it('run cria o repositório e corre as migrações por ordem de nome', function (): void {
    [$dir, $migrator] = threeMigrations();
    $schema = $migrator->getRepository()->getConnection()->getSchemaBuilder();

    expect($schema->hasTable('migrations'))->toBeFalse();

    $ran = $migrator->run();

    expect($ran)->toBe(['2026_01_01_000001_create_leads_table', '2026_01_01_000002_create_notes_table', '2026_01_01_000003_create_tags_table'])
        ->and($schema->hasTable('migrations'))->toBeTrue()
        ->and($schema->hasTable('leads') && $schema->hasTable('notes') && $schema->hasTable('tags'))->toBeTrue()
        ->and($migrator->getRepository()->getRan())->toBe($ran)
        ->and($migrator->getRepository()->getLastBatchNumber())->toBe(1);
});

it('uma segunda run não repete migrações; novas ficam no batch seguinte', function (): void {
    [$dir, $migrator] = threeMigrations();
    $migrator->run();

    expect($migrator->run())->toBe([]);

    M::createTable($dir, '2026_01_01_000004_create_extras_table', 'extras');

    expect($migrator->run())->toBe(['2026_01_01_000004_create_extras_table'])
        ->and($migrator->getRepository()->getBatches())->toBe([
            '2026_01_01_000001_create_leads_table' => 1,
            '2026_01_01_000002_create_notes_table' => 1,
            '2026_01_01_000003_create_tags_table' => 1,
            '2026_01_01_000004_create_extras_table' => 2,
        ]);
});

it('run com step regista um batch por migração', function (): void {
    [, $migrator] = threeMigrations();
    $migrator->run(['step' => true]);

    expect(array_values($migrator->getRepository()->getBatches()))->toBe([1, 2, 3]);
});

// -----------------------------------------------------------------------------
// rollback / reset / fresh
// -----------------------------------------------------------------------------

it('rollback chama down() do último batch e remove os registos', function (): void {
    [$dir, $migrator] = threeMigrations();
    $migrator->run();
    M::createTable($dir, '2026_01_01_000004_create_extras_table', 'extras');
    M::createTable($dir, '2026_01_01_000005_create_more_table', 'more');
    $migrator->run();

    $schema = $migrator->getRepository()->getConnection()->getSchemaBuilder();

    expect($migrator->rollback())->toBe(['2026_01_01_000005_create_more_table', '2026_01_01_000004_create_extras_table'])
        ->and($schema->hasTable('extras') || $schema->hasTable('more'))->toBeFalse()
        ->and($schema->hasTable('leads'))->toBeTrue()
        ->and($migrator->getRepository()->getLastBatchNumber())->toBe(1)
        ->and($migrator->rollback())->toBe(['2026_01_01_000003_create_tags_table', '2026_01_01_000002_create_notes_table', '2026_01_01_000001_create_leads_table'])
        ->and($schema->hasTable('leads'))->toBeFalse()
        ->and($migrator->rollback())->toBe([]);
});

it('rollback com step reverte vários batches e com batch reverte um concreto', function (): void {
    [, $migrator] = threeMigrations();
    $migrator->run(['step' => true]); // batches 1, 2, 3

    expect($migrator->rollback(['batch' => 2]))->toBe(['2026_01_01_000002_create_notes_table'])
        ->and($migrator->getRepository()->getRan())->toBe(['2026_01_01_000001_create_leads_table', '2026_01_01_000003_create_tags_table'])
        ->and($migrator->rollback(['step' => 2]))->toBe(['2026_01_01_000003_create_tags_table', '2026_01_01_000001_create_leads_table'])
        ->and($migrator->getRepository()->getRan())->toBe([]);
});

it('reset reverte tudo; sem repositório não faz nada', function (): void {
    [, $migrator] = threeMigrations();

    expect($migrator->reset())->toBe([]);

    $migrator->run(['step' => true]);

    expect($migrator->reset())->toBe(['2026_01_01_000003_create_tags_table', '2026_01_01_000002_create_notes_table', '2026_01_01_000001_create_leads_table'])
        ->and($migrator->getRepository()->getRan())->toBe([])
        ->and($migrator->getRepository()->getConnection()->getSchemaBuilder()->hasTable('leads'))->toBeFalse();
});

it('fresh apaga tudo sem usar down() e volta a correr as migrações', function (): void {
    [$dir, $migrator] = threeMigrations();
    $migrator->run();

    $schema = $migrator->getRepository()->getConnection()->getSchemaBuilder();
    $schema->create('orphan', fn ($table) => $table->id()); // tabela que nenhum down() conhece
    $migrator->getRepository()->getConnection()->table('leads')->insert(['name' => 'old']);

    expect($migrator->fresh())->toHaveCount(3)
        ->and($schema->hasTable('orphan'))->toBeFalse()
        ->and($migrator->getRepository()->getConnection()->table('leads')->count())->toBe(0)
        ->and($migrator->getRepository()->getLastBatchNumber())->toBe(1);
});

it('rollback ignora (com aviso) migrações cujo ficheiro desapareceu', function (): void {
    [$dir, $migrator] = threeMigrations();
    $migrator->run();
    unlink($dir.'/2026_01_01_000003_create_tags_table.php');

    $messages = [];
    $migrator->setOutput(function (string $message) use (&$messages): void {
        $messages[] = $message;
    });

    expect($migrator->rollback())->toBe(['2026_01_01_000002_create_notes_table', '2026_01_01_000001_create_leads_table'])
        ->and($messages)->toContain('Not found:  2026_01_01_000003_create_tags_table')
        ->and($migrator->getRepository()->getRan())->toBe(['2026_01_01_000003_create_tags_table']);
});

// -----------------------------------------------------------------------------
// status, ficheiros e paths
// -----------------------------------------------------------------------------

it('status lista cada migração com ran e batch', function (): void {
    [$dir, $migrator] = threeMigrations();

    expect($migrator->status())->toBe([
        ['migration' => '2026_01_01_000001_create_leads_table', 'ran' => false, 'batch' => null],
        ['migration' => '2026_01_01_000002_create_notes_table', 'ran' => false, 'batch' => null],
        ['migration' => '2026_01_01_000003_create_tags_table', 'ran' => false, 'batch' => null],
    ]);

    $migrator->run(['step' => true]);
    $migrator->rollback();

    expect(array_column($migrator->status(), 'batch', 'migration'))->toBe([
        '2026_01_01_000001_create_leads_table' => 1,
        '2026_01_01_000002_create_notes_table' => 2,
        '2026_01_01_000003_create_tags_table' => null,
    ]);
});

it('junta migrações de várias pastas por ordem de nome e ignora pastas inexistentes', function (): void {
    $app = M::dir();
    $package = M::dir();
    M::createTable($app, '2026_03_01_000000_create_b_table', 'b');
    M::createTable($package, '2026_02_01_000000_create_a_table', 'a');

    $migrator = M::migrator([$app]);
    $migrator->path($package)->path($package)->path($package.'/nao-existe');

    expect($migrator->paths())->toBe([$app, $package, $package.'/nao-existe'])
        ->and(array_keys($migrator->getMigrationFiles()))->toBe(['2026_02_01_000000_create_a_table', '2026_03_01_000000_create_b_table'])
        ->and($migrator->run())->toBe(['2026_02_01_000000_create_a_table', '2026_03_01_000000_create_b_table']);
});

it('o mesmo nome de ficheiro em duas pastas lança RuntimeException', function (): void {
    $one = M::dir();
    $two = M::dir();
    M::createTable($one, '2026_01_01_000000_create_x_table', 'x');
    M::createTable($two, '2026_01_01_000000_create_x_table', 'x');

    expect(fn () => M::migrator([$one, $two])->run())->toThrow(RuntimeException::class, 'existe em duas pastas');
});

// -----------------------------------------------------------------------------
// resolve: classes anónimas e nomeadas
// -----------------------------------------------------------------------------

it('aceita classes nomeadas (CreateWidgetsTable) e anónimas', function (): void {
    $dir = M::dir();
    M::write($dir, '2026_01_01_000001_create_widgets_table', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Slendie\Database\Migrations\Migration;
        use Slendie\Database\Schema\Blueprint;

        class CreateWidgetsTable extends Migration
        {
            public function up(): void
            {
                $this->schema()->create('widgets', fn (Blueprint $table) => $table->id());
            }

            public function down(): void
            {
                $this->schema()->dropIfExists('widgets');
            }
        }
        PHP);
    M::createTable($dir, '2026_01_01_000002_create_gadgets_table', 'gadgets');

    $migrator = M::migrator([$dir]);
    $schema = $migrator->getRepository()->getConnection()->getSchemaBuilder();

    $migrator->run();

    expect($schema->hasTable('widgets') && $schema->hasTable('gadgets'))->toBeTrue();

    $migrator->rollback();

    expect($schema->hasTable('widgets') || $schema->hasTable('gadgets'))->toBeFalse();

    // volta a correr: o ficheiro com a classe nomeada já foi incluído, mas continua a resolver
    expect($migrator->run())->toHaveCount(2)
        ->and($schema->hasTable('widgets'))->toBeTrue();
});

it('um ficheiro que não é migração lança RuntimeException', function (): void {
    $dir = M::dir();
    M::write($dir, '2026_01_01_000001_create_nothing_table', "<?php\n\nreturn new stdClass();\n");
    M::write($dir, '2026_01_01_000002_create_empty_table', "<?php\n\n// nada\n");

    $migrator = M::migrator([$dir]);

    expect(fn () => $migrator->resolve($dir.'/2026_01_01_000001_create_nothing_table.php'))->toThrow(RuntimeException::class, 'não estende')
        ->and(fn () => $migrator->resolve($dir.'/2026_01_01_000002_create_empty_table.php'))->toThrow(RuntimeException::class, 'CreateEmptyTable');
});

// -----------------------------------------------------------------------------
// Transacções, falhas, pretend e shouldRun
// -----------------------------------------------------------------------------

it('uma migração que falha a meio faz rollback da transacção e não é registada', function (): void {
    $dir = M::dir();
    M::createTable($dir, '2026_01_01_000001_create_ok_table', 'ok');
    M::write($dir, '2026_01_01_000002_create_broken_table', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Slendie\Database\Migrations\Migration;
        use Slendie\Database\Schema\Blueprint;

        return new class extends Migration
        {
            public function up(): void
            {
                $this->schema()->create('half', fn (Blueprint $table) => $table->id());
                throw new RuntimeException('a meio');
            }

            public function down(): void
            {
            }
        };
        PHP);

    $migrator = M::migrator([$dir]);
    $schema = $migrator->getRepository()->getConnection()->getSchemaBuilder();

    expect(fn () => $migrator->run())->toThrow(RuntimeException::class, 'a meio')
        ->and($schema->hasTable('half'))->toBeFalse()                         // DDL desfeito
        ->and($schema->hasTable('ok'))->toBeTrue()                            // a anterior ficou
        ->and($migrator->getRepository()->getRan())->toBe(['2026_01_01_000001_create_ok_table'])
        ->and($migrator->getRepository()->getConnection()->transactionLevel())->toBe(0);
});

it('$withinTransaction = false deixa o DDL fora da transacção', function (): void {
    $dir = M::dir();
    M::write($dir, '2026_01_01_000001_create_loose_table', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Slendie\Database\Migrations\Migration;
        use Slendie\Database\Schema\Blueprint;

        return new class extends Migration
        {
            public bool $withinTransaction = false;

            public function up(): void
            {
                $this->schema()->create('loose', fn (Blueprint $table) => $table->id());
                throw new RuntimeException('depois do DDL');
            }

            public function down(): void
            {
            }
        };
        PHP);

    $migrator = M::migrator([$dir]);

    expect(fn () => $migrator->run())->toThrow(RuntimeException::class)
        ->and($migrator->getRepository()->getConnection()->getSchemaBuilder()->hasTable('loose'))->toBeTrue()
        ->and($migrator->getRepository()->getRan())->toBe([]);
});

it('pretend devolve o SQL sem executar nem registar nada', function (): void {
    [, $migrator] = threeMigrations();
    $schema = $migrator->getRepository()->getConnection()->getSchemaBuilder();

    $sql = $migrator->run(['pretend' => true]);

    expect($sql)->toBe([
        'create table "leads" ("id" integer primary key autoincrement, "name" varchar(255) null)',
        'create table "notes" ("id" integer primary key autoincrement, "name" varchar(255) null)',
        'create table "tags" ("id" integer primary key autoincrement, "name" varchar(255) null)',
    ])
        ->and($schema->hasTable('leads'))->toBeFalse()
        ->and($schema->hasTable('migrations'))->toBeFalse();

    $migrator->run();

    expect($migrator->rollback(['pretend' => true]))->toBe(['drop table if exists "tags"', 'drop table if exists "notes"', 'drop table if exists "leads"'])
        ->and($schema->hasTable('leads'))->toBeTrue()
        ->and($migrator->getRepository()->getRan())->toHaveCount(3);
});

it('shouldRun() = false salta a migração sem a registar', function (): void {
    $dir = M::dir();
    M::createTable($dir, '2026_01_01_000001_create_always_table', 'always');
    M::createTable($dir, '2026_01_01_000002_create_never_table', 'never', 'public function shouldRun(): bool { return false; }');

    $migrator = M::migrator([$dir]);
    $schema = $migrator->getRepository()->getConnection()->getSchemaBuilder();

    expect($migrator->run())->toBe(['2026_01_01_000001_create_always_table'])
        ->and($schema->hasTable('never'))->toBeFalse()
        ->and($migrator->status()[1])->toBe(['migration' => '2026_01_01_000002_create_never_table', 'ran' => false, 'batch' => null]);
});

it('mensagens de saída: Migrating / Migrated / Rolling back / Nothing to migrate', function (): void {
    [, $migrator] = threeMigrations();
    $messages = [];
    $migrator->setOutput(function (string $message) use (&$messages): void {
        $messages[] = $message;
    });

    $migrator->run();
    $migrator->run();
    $migrator->rollback();

    expect($messages[0])->toBe('Migrating: 2026_01_01_000001_create_leads_table')
        ->and($messages[1])->toMatch('/^Migrated:  2026_01_01_000001_create_leads_table \(\d+\.\dms\)$/')
        ->and($messages)->toContain('Nothing to migrate.')
        ->and($messages)->toContain('Rolling back: 2026_01_01_000003_create_tags_table');
});

// -----------------------------------------------------------------------------
// Ligações
// -----------------------------------------------------------------------------

it('uma migração com $connection corre nessa ligação, mesmo via DB/Schema', function (): void {
    $dir = M::dir();
    M::write($dir, '2026_01_01_000001_create_elsewhere_table', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Slendie\Database\Migrations\Migration;
        use Slendie\Database\Schema\Blueprint;

        return new class extends Migration
        {
            protected ?string $connection = 'other';

            public function up(): void
            {
                $this->schema()->create('elsewhere', fn (Blueprint $table) => $table->id());
            }

            public function down(): void
            {
                $this->schema()->dropIfExists('elsewhere');
            }
        };
        PHP);

    $manager = M::manager();
    $migrator = M::migrator([$dir], $manager);

    $migrator->run();

    expect($manager->connection('other')->getSchemaBuilder()->hasTable('elsewhere'))->toBeTrue()
        ->and($manager->connection('main')->getSchemaBuilder()->hasTable('elsewhere'))->toBeFalse()
        ->and($manager->getDefaultConnection())->toBe('main');

    $migrator->rollback();

    expect($manager->connection('other')->getSchemaBuilder()->hasTable('elsewhere'))->toBeFalse();
});

it('setConnection() corre migrações e repositório noutra ligação', function (): void {
    $dir = M::dir();
    M::createTable($dir, '2026_01_01_000001_create_leads_table', 'leads');

    $manager = M::manager();
    $migrator = M::migrator([$dir], $manager)->setConnection('other');
    $migrator->run();

    expect($manager->connection('other')->getSchemaBuilder()->hasTable('migrations'))->toBeTrue()
        ->and($manager->connection('other')->getSchemaBuilder()->hasTable('leads'))->toBeTrue()
        ->and($manager->connection('main')->getSchemaBuilder()->hasTable('leads'))->toBeFalse()
        ->and($manager->getDefaultConnection())->toBe('main');
});

it('uma migração fora do Migrator não tem acesso à base de dados', function (): void {
    $migration = new class () extends Slendie\Database\Migrations\Migration {
        public function up(): void
        {
        }

        public function down(): void
        {
        }
    };

    expect(fn () => $migration->schema())->toThrow(LogicException::class);
});

// -----------------------------------------------------------------------------
// Repositório
// -----------------------------------------------------------------------------

it('MigrationRepository: batches, getLast, getMigrations e delete', function (): void {
    $repo = new MigrationRepository(M::manager(), 'my_migrations');

    expect($repo->repositoryExists())->toBeFalse();

    $repo->createRepository();

    expect($repo->repositoryExists())->toBeTrue()
        ->and($repo->getLastBatchNumber())->toBe(0)
        ->and($repo->getNextBatchNumber())->toBe(1)
        ->and($repo->getMigrations(1))->toBe([]);

    $repo->log('a', 1);
    $repo->log('b', 1);
    $repo->log('c', 2);
    $repo->log('d', 3);

    expect($repo->getRan())->toBe(['a', 'b', 'c', 'd'])
        ->and($repo->getLast())->toBe([['migration' => 'd', 'batch' => 3]])
        ->and(array_column($repo->getMigrations(2), 'migration'))->toBe(['d', 'c'])
        ->and(array_column($repo->getMigrations(10), 'migration'))->toBe(['d', 'c', 'b', 'a'])
        ->and(array_column($repo->getMigrationsByBatch(1), 'migration'))->toBe(['b', 'a'])
        ->and($repo->getNextBatchNumber())->toBe(4);

    $repo->delete('d');

    expect($repo->getLastBatchNumber())->toBe(2)
        ->and(fn () => $repo->log('a', 9))->toThrow(QueryException::class); // migration é única

    $repo->deleteRepository();

    expect($repo->repositoryExists())->toBeFalse();
});

// -----------------------------------------------------------------------------
// Provider e facade Schema
// -----------------------------------------------------------------------------

it('o DatabaseServiceProvider regista Migrator, repositório, creator e Schema; a facade Schema funciona numa migração', function (): void {
    $app = new Application(fixturePath('http'));
    $app->instance(Config::class, new Config(['database' => [
        'default' => 'main',
        'migrations' => 'schema_migrations',
        'connections' => ['main' => ['driver' => 'sqlite', 'database' => ':memory:']],
    ]]));
    (new DatabaseServiceProvider($app))->register();
    Slendie\Support\Facade::setFacadeApplication($app);

    $dir = M::dir();
    M::write($dir, '2026_01_01_000001_create_facade_table', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Slendie\Database\Migrations\Migration;
        use Slendie\Database\Schema\Blueprint;
        use Slendie\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('facade', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('facade');
            }
        };
        PHP);

    $migrator = $app->make(Migrator::class);
    $migrator->path($dir);

    expect($migrator)->toBe($app->make(Migrator::class))
        ->and($app->make(MigrationRepository::class)->getTable())->toBe('schema_migrations')
        ->and($app->make(MigrationCreator::class))->toBeInstanceOf(MigrationCreator::class)
        ->and($app->make('db.schema'))->toBeInstanceOf(Slendie\Database\Schema\Builder::class)
        ->and($migrator->paths())->toBe([$app->databasePath('migrations'), $dir]);

    $migrator->run();

    expect(Schema::hasTable('facade'))->toBeTrue()
        ->and(Schema::hasColumn('facade', 'name'))->toBeTrue()
        ->and(DB::table('facade')->count())->toBe(0)
        ->and(Schema::connection()->hasTable('schema_migrations'))->toBeTrue()
        ->and(Schema::connection('main'))->toBeInstanceOf(Slendie\Database\Schema\Builder::class);

    $migrator->rollback();

    expect(Schema::hasTable('facade'))->toBeFalse();
});
