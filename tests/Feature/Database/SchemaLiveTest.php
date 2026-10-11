<?php

declare(strict_types=1);

use Slendie\Database\Connection;
use Slendie\Database\QueryException;
use Slendie\Database\Schema\Blueprint;
use Slendie\Tests\Fixtures\Database\DbHelpers as Db;

/*
| SQLite :memory: corre sempre; MySQL e PostgreSQL só com DB_TEST_MYSQL_DSN / DB_TEST_PGSQL_DSN definidos
| (a base de dados é recriada: usa uma só para testes).
*/

dataset('schema drivers', Db::drivers());

const SCHEMA_TABLES = ['schema_notes', 'schema_leads', 'schema_users', 'schema_renamed'];

function schemaDb(string $driver): Connection
{
    $connection = Db::live($driver);

    if ($connection === null) {
        test()->markTestSkipped(Db::skipReason($driver));
    }

    $schema = $connection->getSchemaBuilder();
    $schema->withoutForeignKeyConstraints(function () use ($schema): void {
        foreach (SCHEMA_TABLES as $table) {
            $schema->dropIfExists($table);
        }
    });

    if ($driver === 'sqlite') {
        $schema->enableForeignKeyConstraints();
    }

    return $connection;
}

function schemaCleanup(Connection $connection): void
{
    $schema = $connection->getSchemaBuilder();
    $schema->withoutForeignKeyConstraints(function () use ($schema): void {
        foreach (SCHEMA_TABLES as $table) {
            $schema->dropIfExists($table);
        }
    });
}

it('create + hasTable / hasColumn / hasColumns / getColumnListing / getTables', function (string $driver): void {
    $db = schemaDb($driver);
    $schema = $db->getSchemaBuilder();

    expect($schema->hasTable('schema_leads'))->toBeFalse();

    $schema->create('schema_leads', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 120);
        $table->string('email')->index();
        $table->string('phone', 30)->nullable();
        $table->text('message')->nullable();
        $table->string('status', 20)->default('new')->index();
        $table->boolean('active')->default(true);
        $table->decimal('amount', 8, 2)->default(0);
        $table->json('meta')->nullable();
        $table->timestamps();
    });

    expect($schema->hasTable('schema_leads'))->toBeTrue()
        ->and($schema->hasColumn('schema_leads', 'email'))->toBeTrue()
        ->and($schema->hasColumn('schema_leads', 'EMAIL'))->toBeTrue()
        ->and($schema->hasColumn('schema_leads', 'nope'))->toBeFalse()
        ->and($schema->hasColumns('schema_leads', ['name', 'phone']))->toBeTrue()
        ->and($schema->hasColumns('schema_leads', ['name', 'nope']))->toBeFalse()
        ->and($schema->getColumnListing('schema_leads'))->toBe(['id', 'name', 'email', 'phone', 'message', 'status', 'active', 'amount', 'meta', 'created_at', 'updated_at'])
        ->and($schema->getTables())->toContain('schema_leads');

    $columns = array_column($schema->getColumns('schema_leads'), null, 'name');

    expect($columns['name']['nullable'])->toBeFalse()
        ->and($columns['phone']['nullable'])->toBeTrue()
        ->and($columns['status']['default'])->toContain('new');

    // o default de bool e o de string funcionam na prática
    $db->table('schema_leads')->insert(['name' => 'Ana', 'email' => 'ana@x.pt']);
    $row = $db->table('schema_leads')->first();

    expect($row)->toMatchArray(['name' => 'Ana', 'status' => 'new'])
        ->and((int) $row['active'])->toBe(1);

    schemaCleanup($db);
})->with('schema drivers');

it('table() adiciona colunas, renomeia e remove', function (string $driver): void {
    $db = schemaDb($driver);
    $schema = $db->getSchemaBuilder();

    $schema->create('schema_leads', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('msg')->nullable();
        $table->string('junk')->nullable();
    });

    $schema->table('schema_leads', function (Blueprint $table): void {
        $table->string('source', 50)->nullable();
        $table->integer('score')->default(0);
        $table->renameColumn('msg', 'message');
        $table->dropColumn('junk');
    });

    expect($schema->getColumnListing('schema_leads'))->toBe(['id', 'name', 'message', 'source', 'score']);

    schemaCleanup($db);
})->with('schema drivers');

it('rename e drop / dropIfExists', function (string $driver): void {
    $db = schemaDb($driver);
    $schema = $db->getSchemaBuilder();

    $schema->create('schema_leads', fn (Blueprint $table) => $table->id());
    $schema->rename('schema_leads', 'schema_renamed');

    expect($schema->hasTable('schema_leads'))->toBeFalse()
        ->and($schema->hasTable('schema_renamed'))->toBeTrue();

    $schema->drop('schema_renamed');
    $schema->dropIfExists('schema_renamed');

    expect($schema->hasTable('schema_renamed'))->toBeFalse()
        ->and(fn () => $schema->drop('schema_renamed'))->toThrow(QueryException::class);

    schemaCleanup($db);
})->with('schema drivers');

it('índices únicos são aplicados', function (string $driver): void {
    $db = schemaDb($driver);
    $schema = $db->getSchemaBuilder();

    $schema->create('schema_leads', function (Blueprint $table): void {
        $table->id();
        $table->string('email')->unique();
        $table->string('a');
        $table->string('b');
        $table->unique(['a', 'b']);
    });

    $db->table('schema_leads')->insert(['email' => 'a@x.pt', 'a' => '1', 'b' => '1']);

    expect(fn () => $db->table('schema_leads')->insert(['email' => 'a@x.pt', 'a' => '2', 'b' => '2']))->toThrow(QueryException::class)
        ->and(fn () => $db->table('schema_leads')->insert(['email' => 'b@x.pt', 'a' => '1', 'b' => '1']))->toThrow(QueryException::class);

    $schema->table('schema_leads', fn (Blueprint $table) => $table->dropUnique(['a', 'b']));
    $db->table('schema_leads')->insert(['email' => 'b@x.pt', 'a' => '1', 'b' => '1']);

    expect($db->table('schema_leads')->count())->toBe(2);

    schemaCleanup($db);
})->with('schema drivers');

it('chaves estrangeiras: cascade, set null e restrict são aplicados', function (string $driver): void {
    $db = schemaDb($driver);
    $schema = $db->getSchemaBuilder();

    $schema->create('schema_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $schema->create('schema_leads', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->nullable()->constrained('schema_users')->nullOnDelete();
    });

    $schema->create('schema_notes', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained('schema_users')->cascadeOnDelete();
    });

    $db->table('schema_users')->insert([['name' => 'a'], ['name' => 'b']]);
    $db->table('schema_leads')->insert(['user_id' => 1]);
    $db->table('schema_notes')->insert([['user_id' => 1], ['user_id' => 2]]);

    expect(fn () => $db->table('schema_notes')->insert(['user_id' => 99]))->toThrow(QueryException::class);

    $db->table('schema_users')->where('id', 1)->delete();

    expect($db->table('schema_notes')->count())->toBe(1)                       // cascata
        ->and($db->table('schema_leads')->first())->toMatchArray(['user_id' => null]); // set null

    schemaCleanup($db);
})->with('schema drivers');

it('withoutForeignKeyConstraints permite apagar a tabela referenciada (SQLite e MySQL)', function (string $driver): void {
    if ($driver === 'pgsql') {
        test()->markTestSkipped('No PostgreSQL, SET CONSTRAINTS só afecta restrições DEFERRABLE.');
    }

    $db = schemaDb($driver);
    $schema = $db->getSchemaBuilder();

    $schema->create('schema_users', fn (Blueprint $table) => $table->id());
    $schema->create('schema_notes', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained('schema_users');
    });

    $db->table('schema_users')->insert(['id' => 1]);
    $db->table('schema_notes')->insert(['user_id' => 1]);

    expect(fn () => $db->table('schema_users')->where('id', 1)->delete())->toThrow(QueryException::class);

    $schema->withoutForeignKeyConstraints(fn () => $db->table('schema_users')->where('id', 1)->delete());

    expect($db->table('schema_users')->count())->toBe(0)
        ->and(fn () => $db->table('schema_notes')->insert(['user_id' => 7]))->toThrow(QueryException::class);

    schemaCleanup($db);
})->with('schema drivers');

it('enum aceita só os valores permitidos', function (string $driver): void {
    $db = schemaDb($driver);
    $schema = $db->getSchemaBuilder();

    $schema->create('schema_leads', function (Blueprint $table): void {
        $table->id();
        $table->enum('status', ['new', 'won', "it's"])->default('new');
    });

    $db->table('schema_leads')->insert([['status' => 'won'], ['status' => "it's"]]);

    expect($db->table('schema_leads')->pluck('status'))->toBe(['won', "it's"])
        ->and(fn () => $db->table('schema_leads')->insert(['status' => 'lost']))->toThrow(QueryException::class);

    schemaCleanup($db);
})->with('schema drivers');

it('dropAllTables apaga tudo, mesmo com chaves estrangeiras', function (string $driver): void {
    $db = schemaDb($driver);
    $schema = $db->getSchemaBuilder();

    $schema->create('schema_users', fn (Blueprint $table) => $table->id());
    $schema->create('schema_notes', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained('schema_users');
    });

    $before = $schema->getTables();
    $schema->dropAllTables();

    expect($before)->toContain('schema_users', 'schema_notes')
        ->and($schema->getTables())->toBe([])
        ->and($schema->hasTable('schema_users'))->toBeFalse();
})->with('schema drivers');

it('change() altera o tipo e a nulidade (MySQL e PostgreSQL)', function (string $driver): void {
    if ($driver === 'sqlite') {
        $schema = schemaDb($driver)->getSchemaBuilder();
        $schema->create('schema_leads', fn (Blueprint $table) => $table->string('name', 50));

        expect(fn () => $schema->table('schema_leads', fn (Blueprint $table) => $table->string('name', 200)->change()))
            ->toThrow(LogicException::class, 'não suporta alterar colunas');

        schemaCleanup($schema->getConnection());

        return;
    }

    $db = schemaDb($driver);
    $schema = $db->getSchemaBuilder();

    $schema->create('schema_leads', function (Blueprint $table): void {
        $table->id();
        $table->string('name', 50);
    });

    $schema->table('schema_leads', fn (Blueprint $table) => $table->string('name', 200)->nullable()->change());

    $db->table('schema_leads')->insert(['name' => str_repeat('x', 150)]);
    $db->table('schema_leads')->insert(['name' => null]);

    expect($db->table('schema_leads')->count())->toBe(2)
        ->and(array_column($schema->getColumns('schema_leads'), null, 'name')['name']['nullable'])->toBeTrue();

    schemaCleanup($db);
})->with('schema drivers');

it('SQLite: o modo pretend devolve o DDL sem o executar', function (): void {
    $db = Db::live('sqlite');
    $schema = $db->getSchemaBuilder();

    $queries = $db->pretend(fn () => $schema->create('schema_leads', fn (Blueprint $table) => $table->id()));

    expect(array_column($queries, 'sql'))->toBe(['create table "schema_leads" ("id" integer primary key autoincrement)'])
        ->and($schema->hasTable('schema_leads'))->toBeFalse();
});

it('o Blueprint pode ser criado por um resolver próprio', function (): void {
    $schema = Db::live('sqlite')->getSchemaBuilder();
    $made = [];

    $schema->blueprintResolver(function (string $table, ?Closure $callback) use (&$made): Blueprint {
        $made[] = $table;

        return new Blueprint($table, $callback);
    });

    $schema->create('schema_leads', fn (Blueprint $table) => $table->id());

    expect($made)->toBe(['schema_leads'])->and($schema->hasTable('schema_leads'))->toBeTrue();
});
