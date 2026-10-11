<?php

declare(strict_types=1);

use Slendie\Database\Query\Expression;
use Slendie\Database\Schema\Blueprint;
use Slendie\Tests\Fixtures\Database\DbHelpers as Db;

/**
 * DDL gerado por driver, sem base de dados.
 *
 * @return list<string>
 */
function ddl(string $driver, Closure $define, bool $create = true, string $table = 'leads'): array
{
    $blueprint = new Blueprint($table);

    if ($create) {
        $blueprint->create();
    }

    $define($blueprint);

    return $blueprint->toSql(Db::offline($driver));
}

// -----------------------------------------------------------------------------
// create — todos os tipos de coluna
// -----------------------------------------------------------------------------

it('SQLite: create com todos os tipos', function (): void {
    $sql = ddl('sqlite', function (Blueprint $t): void {
        $t->id();
        $t->increments('inc');
        $t->smallIncrements('small_inc');
        $t->uuid();
        $t->string('s');
        $t->string('s20', 20);
        $t->char('c', 4);
        $t->text('t');
        $t->mediumText('mt');
        $t->longText('lt');
        $t->integer('i');
        $t->bigInteger('bi');
        $t->smallInteger('si');
        $t->tinyInteger('ti');
        $t->unsignedInteger('ui');
        $t->unsignedBigInteger('ubi');
        $t->unsignedTinyInteger('uti');
        $t->float('f');
        $t->double('d');
        $t->decimal('dec', 10, 3);
        $t->boolean('b');
        $t->date('dt');
        $t->dateTime('dtt');
        $t->timestamp('ts');
        $t->time('tm');
        $t->year('y');
        $t->json('j');
        $t->jsonb('jb');
        $t->binary('bin');
        $t->enum('e', ['a', 'b']);
        $t->ipAddress();
        $t->macAddress();
    });

    expect($sql)->toBe(['create table "leads" ('
        .'"id" integer primary key autoincrement, "inc" integer primary key autoincrement, "small_inc" integer primary key autoincrement, '
        .'"uuid" text not null, "s" varchar(255) not null, "s20" varchar(20) not null, "c" varchar(4) not null, "t" text not null, '
        .'"mt" text not null, "lt" text not null, "i" integer not null, "bi" integer not null, "si" integer not null, "ti" integer not null, '
        .'"ui" integer not null, "ubi" integer not null, "uti" integer not null, "f" real not null, "d" real not null, "dec" numeric not null, '
        .'"b" tinyint(1) not null, "dt" date not null, "dtt" datetime not null, "ts" datetime not null, "tm" time not null, "y" integer not null, '
        .'"j" text not null, "jb" text not null, "bin" blob not null, "e" text check ("e" in (\'a\', \'b\')) not null, '
        .'"ip_address" varchar(45) not null, "mac_address" varchar(17) not null)']);
});

it('MySQL: create com todos os tipos', function (): void {
    $sql = ddl('mysql', function (Blueprint $t): void {
        $t->id();
        $t->increments('inc');
        $t->smallIncrements('small_inc');
        $t->uuid();
        $t->string('s');
        $t->char('c', 4);
        $t->text('t');
        $t->mediumText('mt');
        $t->longText('lt');
        $t->integer('i');
        $t->bigInteger('bi');
        $t->smallInteger('si');
        $t->tinyInteger('ti');
        $t->unsignedInteger('ui');
        $t->unsignedBigInteger('ubi');
        $t->float('f');
        $t->float('f24', 24);
        $t->double('d');
        $t->decimal('dec', 10, 3);
        $t->boolean('b');
        $t->date('dt');
        $t->dateTime('dtt');
        $t->dateTime('dtt3', 3);
        $t->timestamp('ts');
        $t->time('tm');
        $t->year('y');
        $t->json('j');
        $t->jsonb('jb');
        $t->binary('bin');
        $t->enum('e', ['a', 'b']);
        $t->ipAddress();
        $t->macAddress();
    });

    expect($sql)->toBe(['create table `leads` ('
        .'`id` bigint unsigned auto_increment primary key, `inc` int unsigned auto_increment primary key, `small_inc` smallint unsigned auto_increment primary key, '
        .'`uuid` char(36) not null, `s` varchar(255) not null, `c` char(4) not null, `t` text not null, `mt` mediumtext not null, `lt` longtext not null, '
        .'`i` int not null, `bi` bigint not null, `si` smallint not null, `ti` tinyint not null, `ui` int unsigned not null, `ubi` bigint unsigned not null, '
        .'`f` double not null, `f24` float not null, `d` double not null, `dec` decimal(10, 3) not null, `b` tinyint(1) not null, `dt` date not null, '
        .'`dtt` datetime not null, `dtt3` datetime(3) not null, `ts` timestamp not null, `tm` time not null, `y` year not null, `j` json not null, '
        .'`jb` json not null, `bin` blob not null, `e` enum(\'a\', \'b\') not null, `ip_address` varchar(45) not null, `mac_address` varchar(17) not null'
        .') engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci']);
});

it('PostgreSQL: create com todos os tipos', function (): void {
    $sql = ddl('pgsql', function (Blueprint $t): void {
        $t->id();
        $t->increments('inc');
        $t->smallIncrements('small_inc');
        $t->uuid();
        $t->string('s');
        $t->char('c', 4);
        $t->text('t');
        $t->mediumText('mt');
        $t->integer('i');
        $t->bigInteger('bi');
        $t->smallInteger('si');
        $t->tinyInteger('ti');
        $t->unsignedBigInteger('ubi');
        $t->integer('serial', true);
        $t->float('f');
        $t->float('f24', 24);
        $t->double('d');
        $t->decimal('dec', 10, 3);
        $t->boolean('b');
        $t->date('dt');
        $t->dateTime('dtt');
        $t->timestampTz('tstz', 3);
        $t->time('tm');
        $t->year('y');
        $t->json('j');
        $t->jsonb('jb');
        $t->binary('bin');
        $t->enum('e', ['a', 'b']);
        $t->ipAddress();
        $t->macAddress();
    });

    expect($sql)->toBe(['create table "leads" ('
        .'"id" bigserial primary key, "inc" serial primary key, "small_inc" smallserial primary key, "uuid" uuid not null, "s" varchar(255) not null, '
        .'"c" char(4) not null, "t" text not null, "mt" text not null, "i" integer not null, "bi" bigint not null, "si" smallint not null, "ti" smallint not null, '
        .'"ubi" bigint not null, "serial" serial primary key, "f" double precision not null, "f24" real not null, "d" double precision not null, '
        .'"dec" decimal(10, 3) not null, "b" boolean not null, "dt" date not null, "dtt" timestamp(0) without time zone not null, '
        .'"tstz" timestamp(3) with time zone not null, "tm" time(0) without time zone not null, "y" integer not null, "j" json not null, "jb" jsonb not null, '
        .'"bin" bytea not null, "e" varchar(255) check ("e" in (\'a\', \'b\')) not null, "ip_address" inet not null, "mac_address" macaddr not null)']);
});

// -----------------------------------------------------------------------------
// Modifiers, defaults e timestamps
// -----------------------------------------------------------------------------

it('defaults: bool, string (com aspas), número, Expression e null', function (string $driver, string $true, string $false): void {
    $sql = ddl($driver, function (Blueprint $t): void {
        $t->boolean('on')->default(true);
        $t->boolean('off')->default(false);
        $t->string('name')->default("O'Neil");
        $t->integer('n')->default(0);
        $t->decimal('price')->default(1.5);
        $t->timestamp('at')->default(new Expression('CURRENT_TIMESTAMP'));
        $t->string('note')->nullable()->default(null);
    })[0];

    expect($sql)
        ->toContain("default {$true}, ")
        ->toContain("default {$false}, ")
        ->toContain("default 'O''Neil'")
        ->toContain('default 0, ')
        ->toContain('default 1.5, ')
        ->toContain('default CURRENT_TIMESTAMP, ')
        ->toContain(' null default null');
})->with([
    'sqlite' => ['sqlite', '1', '0'],
    'mysql' => ['mysql', '1', '0'],
    'pgsql' => ['pgsql', 'true', 'false'],
]);

it('MySQL escapa a barra invertida nos defaults e comentários', function (): void {
    $sql = ddl('mysql', fn (Blueprint $t) => $t->string('path')->default('C:\\tmp')->comment("a'b"))[0];

    expect($sql)->toContain("default 'C:\\\\tmp' comment 'a''b'");
});

it('timestamps() são nullable; useCurrent usa o default do driver', function (): void {
    expect(ddl('sqlite', fn (Blueprint $t) => $t->timestamps())[0])->toBe('create table "leads" ("created_at" datetime null, "updated_at" datetime null)')
        ->and(ddl('mysql', fn (Blueprint $t) => $t->timestamps(3))[0])->toStartWith('create table `leads` (`created_at` timestamp(3) null, `updated_at` timestamp(3) null)')
        ->and(ddl('pgsql', fn (Blueprint $t) => $t->timestamp('at')->useCurrent())[0])->toBe('create table "leads" ("at" timestamp(0) without time zone not null default current_timestamp)')
        ->and(ddl('mysql', fn (Blueprint $t) => $t->timestamp('at', 3)->useCurrent()->useCurrentOnUpdate())[0])
        ->toStartWith('create table `leads` (`at` timestamp(3) not null default current_timestamp(3) on update current_timestamp(3))')
        ->and(ddl('sqlite', fn (Blueprint $t) => $t->softDeletes())[0])->toBe('create table "leads" ("deleted_at" datetime null)')
        ->and(ddl('sqlite', fn (Blueprint $t) => $t->rememberToken())[0])->toBe('create table "leads" ("remember_token" varchar(100) null)');
});

it('MySQL: modifiers charset, collate, comment, after e first', function (): void {
    $sql = ddl('mysql', function (Blueprint $t): void {
        $t->string('a')->charset('latin1')->collation('latin1_swedish_ci');
        $t->string('b')->after('a')->comment('hi');
        $t->string('c')->first();
    }, create: false)[0];

    expect($sql)->toBe('alter table `leads` add column `a` varchar(255) character set latin1 collate latin1_swedish_ci not null, '
        .'add column `b` varchar(255) not null comment \'hi\' after `a`, add column `c` varchar(255) not null first');
});

it('MySQL: engine, charset e collation da tabela e da ligação', function (): void {
    $sql = ddl('mysql', function (Blueprint $t): void {
        $t->engine('MyISAM')->charset('latin1')->collation('latin1_swedish_ci');
        $t->id();
    })[0];

    expect($sql)->toEndWith(') engine=MyISAM default charset=latin1 collate=latin1_swedish_ci');
});

it('rejeita identificadores e opções perigosos', function (): void {
    expect(fn () => ddl('sqlite', fn (Blueprint $t) => $t->string('a"; drop table x; --')))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ddl('mysql', fn (Blueprint $t) => $t->id(), table: 'a`b'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ddl('mysql', fn (Blueprint $t) => $t->engine('x; drop table y')))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ddl('pgsql', fn (Blueprint $t) => $t->string('a')->default([1])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ddl('sqlite', fn (Blueprint $t) => $t->enum('e', [])))->toThrow(InvalidArgumentException::class);
});

// -----------------------------------------------------------------------------
// Índices e chaves estrangeiras
// -----------------------------------------------------------------------------

it('índices, únicos e primária composta na criação', function (string $driver, array $expected): void {
    $sql = ddl($driver, function (Blueprint $t): void {
        $t->string('email')->unique();
        $t->string('status')->index();
        $t->string('slug')->unique('my_slug');
        $t->integer('a');
        $t->integer('b');
        $t->primary(['a', 'b']);
        $t->index(['status', 'a']);
        $t->unique(['a', 'b'], 'ab_uniq');
    });

    expect($sql)->toBe($expected);
})->with([
    'sqlite' => ['sqlite', [
        'create table "leads" ("email" varchar(255) not null, "status" varchar(255) not null, "slug" varchar(255) not null, "a" integer not null, "b" integer not null, primary key ("a", "b"))',
        'create index "leads_status_a_index" on "leads" ("status", "a")',
        'create unique index "ab_uniq" on "leads" ("a", "b")',
        'create unique index "leads_email_unique" on "leads" ("email")',
        'create index "leads_status_index" on "leads" ("status")',
        'create unique index "my_slug" on "leads" ("slug")',
    ]],
    'mysql' => ['mysql', [
        'create table `leads` (`email` varchar(255) not null, `status` varchar(255) not null, `slug` varchar(255) not null, `a` int not null, `b` int not null) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci',
        'alter table `leads` add primary key (`a`, `b`)',
        'alter table `leads` add index `leads_status_a_index` (`status`, `a`)',
        'alter table `leads` add unique `ab_uniq` (`a`, `b`)',
        'alter table `leads` add unique `leads_email_unique` (`email`)',
        'alter table `leads` add index `leads_status_index` (`status`)',
        'alter table `leads` add unique `my_slug` (`slug`)',
    ]],
    'pgsql' => ['pgsql', [
        'create table "leads" ("email" varchar(255) not null, "status" varchar(255) not null, "slug" varchar(255) not null, "a" integer not null, "b" integer not null)',
        'alter table "leads" add primary key ("a", "b")',
        'create index "leads_status_a_index" on "leads" ("status", "a")',
        'alter table "leads" add constraint "ab_uniq" unique ("a", "b")',
        'alter table "leads" add constraint "leads_email_unique" unique ("email")',
        'create index "leads_status_index" on "leads" ("status")',
        'alter table "leads" add constraint "my_slug" unique ("slug")',
    ]],
]);

it('chaves estrangeiras com acções', function (string $driver, array $expected): void {
    $sql = ddl($driver, function (Blueprint $t): void {
        $t->foreignId('user_id')->constrained()->cascadeOnDelete()->restrictOnUpdate();
        $t->foreignId('owner_id')->nullable()->constrained('users', 'uid')->nullOnDelete();
        $t->foreignUuid('team_uuid')->constrained('teams');
        $t->integer('x');
        $t->foreign('x')->references('id')->on('xs')->onDelete('no action')->onUpdate('cascade');
    });

    expect($sql)->toBe($expected);
})->with([
    'sqlite' => ['sqlite', [
        'create table "leads" ("user_id" integer not null, "owner_id" integer null, "team_uuid" text not null, "x" integer not null, '
        .'foreign key ("user_id") references "users" ("id") on delete cascade on update restrict, '
        .'foreign key ("owner_id") references "users" ("uid") on delete set null, '
        .'foreign key ("team_uuid") references "teams" ("id"), '
        .'foreign key ("x") references "xs" ("id") on delete no action on update cascade)',
    ]],
    'mysql' => ['mysql', [
        'create table `leads` (`user_id` bigint unsigned not null, `owner_id` bigint unsigned null, `team_uuid` char(36) not null, `x` int not null) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci',
        'alter table `leads` add constraint `leads_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade on update restrict',
        'alter table `leads` add constraint `leads_owner_id_foreign` foreign key (`owner_id`) references `users` (`uid`) on delete set null',
        'alter table `leads` add constraint `leads_team_uuid_foreign` foreign key (`team_uuid`) references `teams` (`id`)',
        'alter table `leads` add constraint `leads_x_foreign` foreign key (`x`) references `xs` (`id`) on delete no action on update cascade',
    ]],
    'pgsql' => ['pgsql', [
        'create table "leads" ("user_id" bigint not null, "owner_id" bigint null, "team_uuid" uuid not null, "x" integer not null)',
        'alter table "leads" add constraint "leads_user_id_foreign" foreign key ("user_id") references "users" ("id") on delete cascade on update restrict',
        'alter table "leads" add constraint "leads_owner_id_foreign" foreign key ("owner_id") references "users" ("uid") on delete set null',
        'alter table "leads" add constraint "leads_team_uuid_foreign" foreign key ("team_uuid") references "teams" ("id")',
        'alter table "leads" add constraint "leads_x_foreign" foreign key ("x") references "xs" ("id") on delete no action on update cascade',
    ]],
]);

it('uma chave estrangeira incompleta ou com acção inválida lança excepção', function (): void {
    expect(fn () => ddl('mysql', fn (Blueprint $t) => $t->foreign('x')))->toThrow(LogicException::class, 'references() e on()')
        ->and(fn () => (new Blueprint('t'))->foreign('x')->onDelete('explode'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new Blueprint('t'))->string('x')->constrained())->not->toThrow(LogicException::class);
});

// -----------------------------------------------------------------------------
// drop / rename / alter
// -----------------------------------------------------------------------------

it('drop, dropIfExists e rename', function (string $driver, string $q): void {
    $mk = fn (string $method, ...$args): array => ddl($driver, fn (Blueprint $t) => $t->$method(...$args), create: false);

    expect($mk('drop'))->toBe(["drop table {$q}leads{$q}"])
        ->and($mk('dropIfExists'))->toBe(["drop table if exists {$q}leads{$q}"])
        ->and($mk('rename', 'contacts'))->toBe(["alter table {$q}leads{$q} rename to {$q}contacts{$q}"]);
})->with([
    'sqlite' => ['sqlite', '"'],
    'mysql' => ['mysql', '`'],
    'pgsql' => ['pgsql', '"'],
]);

it('table(): add, dropColumn e renameColumn', function (string $driver, array $expected): void {
    $sql = ddl($driver, function (Blueprint $t): void {
        $t->string('source', 50)->nullable();
        $t->integer('score')->default(0);
        $t->dropColumn(['ip', 'user_agent']);
        $t->renameColumn('msg', 'message');
        $t->dropTimestamps();
    }, create: false);

    expect($sql)->toBe($expected);
})->with([
    'sqlite' => ['sqlite', [
        'alter table "leads" add column "source" varchar(50) null',
        'alter table "leads" add column "score" integer not null default 0',
        'alter table "leads" drop column "ip"',
        'alter table "leads" drop column "user_agent"',
        'alter table "leads" rename column "msg" to "message"',
        'alter table "leads" drop column "created_at"',
        'alter table "leads" drop column "updated_at"',
    ]],
    'mysql' => ['mysql', [
        'alter table `leads` add column `source` varchar(50) null, add column `score` int not null default 0',
        'alter table `leads` drop column `ip`, drop column `user_agent`',
        'alter table `leads` rename column `msg` to `message`',
        'alter table `leads` drop column `created_at`, drop column `updated_at`',
    ]],
    'pgsql' => ['pgsql', [
        'alter table "leads" add column "source" varchar(50) null, add column "score" integer not null default 0',
        'alter table "leads" drop column "ip", drop column "user_agent"',
        'alter table "leads" rename column "msg" to "message"',
        'alter table "leads" drop column "created_at", drop column "updated_at"',
    ]],
]);

it('dropIndex, dropUnique, dropPrimary e dropForeign', function (string $driver, array $expected): void {
    $sql = ddl($driver, function (Blueprint $t): void {
        $t->dropIndex(['status', 'a']);
        $t->dropIndex('custom_idx');
        $t->dropUnique(['email']);
        $t->dropForeign(['user_id']);
        $t->dropPrimary();
    }, create: false);

    expect($sql)->toBe($expected);
})->with([
    'mysql' => ['mysql', [
        'alter table `leads` drop index `leads_status_a_index`',
        'alter table `leads` drop index `custom_idx`',
        'alter table `leads` drop index `leads_email_unique`',
        'alter table `leads` drop foreign key `leads_user_id_foreign`',
        'alter table `leads` drop primary key',
    ]],
    'pgsql' => ['pgsql', [
        'drop index "leads_status_a_index"',
        'drop index "custom_idx"',
        'alter table "leads" drop constraint "leads_email_unique"',
        'alter table "leads" drop constraint "leads_user_id_foreign"',
        'alter table "leads" drop constraint "leads_pkey"',
    ]],
]);

it('SQLite: dropIndex e dropUnique usam DROP INDEX', function (): void {
    $sql = ddl('sqlite', function (Blueprint $t): void {
        $t->dropIndex(['status']);
        $t->dropUnique('leads_email_unique');
    }, create: false);

    expect($sql)->toBe(['drop index "leads_status_index"', 'drop index "leads_email_unique"']);
});

it('comentários da tabela e das colunas', function (): void {
    expect(ddl('mysql', fn (Blueprint $t) => $t->comment('Os leads'), create: false))->toBe(["alter table `leads` comment = 'Os leads'"])
        ->and(ddl('pgsql', function (Blueprint $t): void {
            $t->comment('Os leads');
            $t->string('name')->comment('Nome');
        }, create: false))->toBe([
            'alter table "leads" add column "name" varchar(255) not null',
            'comment on table "leads" is \'Os leads\'',
            'comment on column "leads"."name" is \'Nome\'',
        ])
        ->and(ddl('sqlite', fn (Blueprint $t) => $t->comment('ignorado'), create: false))->toBe([]);
});

it('temporary() cria tabelas temporárias', function (): void {
    expect(ddl('sqlite', function (Blueprint $t): void {
        $t->temporary();
        $t->id();
    })[0])->toStartWith('create temporary table "leads"');
});

// -----------------------------------------------------------------------------
// change()
// -----------------------------------------------------------------------------

it('MySQL: change() usa MODIFY com a definição completa', function (): void {
    $sql = ddl('mysql', function (Blueprint $t): void {
        $t->string('name', 200)->nullable()->change();
        $t->string('title')->after('name');
    }, create: false);

    expect($sql)->toBe([
        'alter table `leads` add column `title` varchar(255) not null after `name`',
        'alter table `leads` modify `name` varchar(200) null',
    ]);
});

it('PostgreSQL: change() usa ALTER COLUMN ... TYPE e redefine default e nulidade', function (): void {
    $sql = ddl('pgsql', function (Blueprint $t): void {
        $t->string('name', 200)->nullable()->change();
        $t->integer('score')->default(5)->change();
    }, create: false);

    expect($sql)->toBe(['alter table "leads" '
        .'alter column "name" type varchar(200), alter column "name" drop default, alter column "name" drop not null, '
        .'alter column "score" type integer, alter column "score" set default 5, alter column "score" set not null']);
});

it('SQLite: change(), dropForeign, dropPrimary e adicionar chaves lançam LogicException', function (): void {
    expect(fn () => ddl('sqlite', fn (Blueprint $t) => $t->string('name')->change(), create: false))
        ->toThrow(LogicException::class, 'não suporta alterar colunas')
        ->and(fn () => ddl('sqlite', fn (Blueprint $t) => $t->dropForeign(['user_id']), create: false))->toThrow(LogicException::class)
        ->and(fn () => ddl('sqlite', fn (Blueprint $t) => $t->dropPrimary(), create: false))->toThrow(LogicException::class)
        ->and(fn () => ddl('sqlite', fn (Blueprint $t) => $t->primary('id'), create: false))->toThrow(LogicException::class)
        ->and(fn () => ddl('sqlite', fn (Blueprint $t) => $t->foreign('x')->references('id')->on('xs'), create: false))->toThrow(LogicException::class);
});

// -----------------------------------------------------------------------------
// Nomes de índices
// -----------------------------------------------------------------------------

it('encurta nomes de índice demasiado longos', function (): void {
    $blueprint = new Blueprint('a_rather_long_table_name_for_testing');
    $name = $blueprint->createIndexName('unique', ['a_very_long_column_name_one', 'a_very_long_column_name_two']);

    expect(strlen($name))->toBeLessThanOrEqual(63)
        ->and($name)->toBe($blueprint->createIndexName('unique', ['a_very_long_column_name_one', 'a_very_long_column_name_two']))
        ->and($blueprint->createIndexName('index', ['A-b', 'x.y']))->toBe('a_rather_long_table_name_for_testing_a_b_x_y_index');
});

// -----------------------------------------------------------------------------
// Introspecção e restrições
// -----------------------------------------------------------------------------

it('SQL de introspecção e de chaves estrangeiras por driver', function (): void {
    $sqlite = Db::offline('sqlite')->getSchemaGrammar();
    $mysql = Db::offline('mysql')->getSchemaGrammar();
    $pgsql = Db::offline('pgsql')->getSchemaGrammar();

    expect($sqlite->compileTableExists('leads'))->toBe("select count(*) from sqlite_master where type = 'table' and name = 'leads'")
        ->and($sqlite->compileGetColumns('leads'))->toBe('pragma table_info("leads")')
        ->and($sqlite->compileEnableForeignKeyConstraints())->toBe('pragma foreign_keys = on')
        ->and($sqlite->compileDisableForeignKeyConstraints())->toBe('pragma foreign_keys = off')
        ->and($sqlite->compileDropAllTables(['a', 'b']))->toBe(['drop table "a"', 'drop table "b"'])
        ->and($mysql->compileTableExists('leads'))->toContain("table_schema = database() and table_name = 'leads'")
        ->and($mysql->compileEnableForeignKeyConstraints())->toBe('set foreign_key_checks = 1')
        ->and($mysql->compileDisableForeignKeyConstraints())->toBe('set foreign_key_checks = 0')
        ->and($mysql->compileDropAllTables(['a', 'b']))->toBe(['drop table `a`, `b`'])
        ->and($mysql->compileDropAllTables([]))->toBe([])
        ->and($pgsql->compileTableExists('leads'))->toContain("table_schema = current_schema() and table_name = 'leads'")
        ->and($pgsql->compileDisableForeignKeyConstraints())->toBe('set constraints all deferred')
        ->and($pgsql->compileEnableForeignKeyConstraints())->toBe('set constraints all immediate')
        ->and($pgsql->compileDropAllTables(['a', 'b']))->toBe(['drop table "a", "b" cascade'])
        ->and(fn () => $mysql->compileTableExists("x'; drop table y; --"))->toThrow(InvalidArgumentException::class);
});

it('o DDL transaccional só é suportado fora do MySQL', function (): void {
    expect(Db::offline('sqlite')->getSchemaGrammar()->supportsSchemaTransactions())->toBeTrue()
        ->and(Db::offline('pgsql')->getSchemaGrammar()->supportsSchemaTransactions())->toBeTrue()
        ->and(Db::offline('mysql')->getSchemaGrammar()->supportsSchemaTransactions())->toBeFalse();
});
