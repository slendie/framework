<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\Database\DbHelpers as Db;
use Slendie\Tests\Fixtures\Database\GrammarCases;

dataset('pgsql select cases', GrammarCases::cases());
dataset('pgsql write cases', GrammarCases::writeCases());

it('PostgreSQL: select', function (callable $build, string $sql, array $bindings): void {
    $builder = Db::builder('pgsql');
    $build($builder);

    expect($builder->toSql())->toBe($sql)
        ->and($builder->getBindings())->toBe($bindings);
})->with('pgsql select cases');

it('PostgreSQL: escrita', function (callable $compile, string $sql): void {
    expect($compile(Db::builder('pgsql')))->toBe($sql);
})->with('pgsql write cases');

it('PostgreSQL: whereLike usa ILIKE quando insensível', function (): void {
    $b = Db::builder('pgsql', 'users')->whereLike('name', 'silva')->whereLike('email', 'A%', true)->whereNotLike('x', 'y%');

    expect($b->toSql())->toBe('select * from "users" where "name" ilike ? and "email" like ? and "x" not ilike ?')
        ->and($b->getBindings())->toBe(['%silva%', 'A%', 'y%']);
});

it('PostgreSQL: o caso do brief gera SQL válido', function (): void {
    $b = Db::builder('pgsql', 'leads')->whereLike('name', 'silva')->orderBy('id', 'desc')->forPage(1, 20);

    expect($b->toSql())->toBe('select * from "leads" where "name" ilike ? order by "id" desc limit 20 offset 0');
});

it('PostgreSQL: whereDate/Year/Month/Day', function (): void {
    $b = Db::builder('pgsql', 'users')
        ->whereDate('created_at', '2026-05-09')
        ->whereYear('created_at', 2026)
        ->whereMonth('created_at', '>=', 5)
        ->whereDay('created_at', new DateTimeImmutable('2026-05-09'));

    expect($b->toSql())->toBe('select * from "users" where "created_at"::date = ? and extract(YEAR from "created_at") = ? and extract(MONTH from "created_at") >= ? and extract(DAY from "created_at") = ?')
        ->and($b->getBindings())->toBe(['2026-05-09', 2026, 5, 9]);
});

it('PostgreSQL: offset sem limit é válido', function (): void {
    expect(Db::builder('pgsql', 'users')->offset(5)->toSql())->toBe('select * from "users" offset 5');
});

it('PostgreSQL: inRandomOrder', function (): void {
    expect(Db::builder('pgsql', 'users')->inRandomOrder()->toSql())->toBe('select * from "users" order by RANDOM()');
});

it('PostgreSQL: insertOrIgnore, truncate e insertGetId com RETURNING', function (): void {
    $log = Db::offline('pgsql')->pretend(function ($c): void {
        $c->table('users')->insertOrIgnore(['a' => 1]);
        $c->table('users')->truncate();
        $c->table('users')->insertGetId(['name' => 'Ana']);
        $c->table('users')->insertGetId(['name' => 'Ana'], 'user_id');
    });

    expect(array_column($log, 'sql'))->toBe([
        'insert into "users" ("a") values (?) on conflict do nothing',
        'truncate "users" restart identity cascade',
        'insert into "users" ("name") values (?) returning "id"',
        'insert into "users" ("name") values (?) returning "user_id"',
    ]);
});
