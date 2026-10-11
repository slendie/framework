<?php

declare(strict_types=1);

use Slendie\Database\Query\Builder;
use Slendie\Database\Query\Expression;
use Slendie\Tests\Fixtures\Database\DbHelpers as Db;
use Slendie\Tests\Fixtures\Database\GrammarCases;

dataset('sqlite select cases', GrammarCases::cases());
dataset('sqlite write cases', GrammarCases::writeCases());

it('SQLite: select', function (callable $build, string $sql, array $bindings): void {
    $builder = Db::builder('sqlite');
    $build($builder);

    expect($builder->toSql())->toBe($sql)
        ->and($builder->getBindings())->toBe($bindings);
})->with('sqlite select cases');

it('SQLite: escrita', function (callable $compile, string $sql): void {
    expect($compile(Db::builder('sqlite')))->toBe($sql);
})->with('sqlite write cases');

it('SQLite: whereLike acrescenta % se o valor não os tiver', function (): void {
    $b = Db::builder('sqlite', 'users')->whereLike('name', 'silva');

    expect($b->toSql())->toBe('select * from "users" where "name" like ?')
        ->and($b->getBindings())->toBe(['%silva%']);
});

it('SQLite: whereLike respeita padrões explícitos', function (): void {
    $b = Db::builder('sqlite', 'users')->whereLike('name', 'ana%')->orWhereLike('name', '_ui')->whereNotLike('email', '%@x.pt');

    expect($b->toSql())->toBe('select * from "users" where "name" like ? or "name" like ? and "email" not like ?')
        ->and($b->getBindings())->toBe(['ana%', '_ui', '%@x.pt']);
});

it('SQLite: whereLike sensível a maiúsculas usa GLOB', function (): void {
    $b = Db::builder('sqlite', 'users')->whereLike('name', 'An%', true);

    expect($b->toSql())->toBe('select * from "users" where "name" glob ?')
        ->and($b->getBindings())->toBe(['An*']);
});

it('SQLite: whereDate/Year/Month/Day', function (): void {
    $b = Db::builder('sqlite', 'users')
        ->whereDate('created_at', '2026-05-09')
        ->whereYear('created_at', 2026)
        ->whereMonth('created_at', '>=', 5)
        ->whereDay('created_at', new DateTimeImmutable('2026-05-09'));

    expect($b->toSql())->toBe('select * from "users" where date("created_at") = cast(? as text) and strftime(\'%Y\', "created_at") = cast(? as text) and strftime(\'%m\', "created_at") >= cast(? as text) and strftime(\'%d\', "created_at") = cast(? as text)')
        ->and($b->getBindings())->toBe(['2026-05-09', '2026', '05', '09']);
});

it('SQLite: offset sem limit usa limit -1', function (): void {
    expect(Db::builder('sqlite', 'users')->offset(5)->toSql())->toBe('select * from "users" limit -1 offset 5');
});

it('SQLite: inRandomOrder', function (): void {
    expect(Db::builder('sqlite', 'users')->inRandomOrder()->toSql())->toBe('select * from "users" order by RANDOM()');
});

it('SQLite: insertOrIgnore, truncate e insertGetId', function (): void {
    $connection = Db::offline('sqlite');

    $log = $connection->pretend(function ($c): void {
        $c->table('users')->insertOrIgnore(['a' => 1]);
        $c->table('users')->truncate();
        $c->table('users')->insertGetId(['name' => 'Ana']);
    });

    expect(array_column($log, 'sql'))->toBe([
        'insert or ignore into "users" ("a") values (?)',
        'delete from "users"',
        'delete from sqlite_sequence where name = ?',
        'insert into "users" ("name") values (?)',
    ])->and($log[2]['bindings'])->toBe(['users']);
});

it('SQLite: agregados, exists, update, delete e increment', function (): void {
    $connection = Db::offline('sqlite');

    $log = $connection->pretend(function ($c): void {
        $c->table('users')->where('a', 1)->orderBy('id')->count();
        $c->table('users')->sum('votes');
        $c->table('users')->avg('votes');
        $c->table('users')->min('votes');
        $c->table('users')->max('users.votes');
        $c->table('users')->distinct()->count('email');
        $c->table('users')->where('a', 1)->exists();
        $c->table('users')->where('id', 3)->update(['name' => 'X', 'age' => 3]);
        $c->table('users')->where('id', 3)->delete();
        $c->table('users')->delete(9);
        $c->table('users')->where('id', 3)->increment('votes', 2, ['name' => 'Y']);
        $c->table('users')->decrement('votes');
    });

    expect(array_column($log, 'sql'))->toBe([
        'select count(*) as "aggregate" from "users" where "a" = ?',
        'select sum("votes") as "aggregate" from "users"',
        'select avg("votes") as "aggregate" from "users"',
        'select min("votes") as "aggregate" from "users"',
        'select max("users"."votes") as "aggregate" from "users"',
        'select count(distinct "email") as "aggregate" from "users"',
        'select exists(select * from "users" where "a" = ?) as "exists"',
        'update "users" set "name" = ?, "age" = ? where "id" = ?',
        'delete from "users" where "id" = ?',
        'delete from "users" where "id" = ?',
        'update "users" set "votes" = "votes" + 2, "name" = ? where "id" = ?',
        'update "users" set "votes" = "votes" - 1',
    ])->and($log[7]['bindings'])->toBe(['X', 3, 3]);
});

it('SQLite: count com group by usa subquery', function (): void {
    $log = Db::offline('sqlite')->pretend(fn ($c) => $c->table('users')->where('a', 1)->groupBy('team')->count());

    expect($log[0]['sql'])->toBe('select count(*) as "aggregate" from (select * from "users" where "a" = ? group by "team") as "aggregate_table"');
});

it('SQLite: bindings são convertidos (bool, data, enum) e Expression não gera binding', function (): void {
    $log = Db::offline('sqlite')->pretend(function ($c): void {
        $c->table('users')->where('active', true)->where('at', new DateTimeImmutable('2026-01-02 03:04:05'))->where('s', Slendie\Tests\Fixtures\Database\Status::Open)->update(['x' => new Expression('1')]);
    });

    expect($log[0]['sql'])->toBe('update "users" set "x" = 1 where "active" = ? and "at" = ? and "s" = ?')
        ->and($log[0]['bindings'])->toBe([1, '2026-01-02 03:04:05', 'open']);
});

it('SQLite: toRawSql interpola só para debug', function (): void {
    $raw = Db::builder('sqlite', 'users')->where('name', "O'Brien")->where('n', 3)->where('x', true)->toRawSql();

    expect($raw)->toBe('select * from "users" where "name" = \'O\'\'Brien\' and "n" = 3 and "x" = 1');
});

it('SQLite: clone é profundo', function (): void {
    $a = Db::builder('sqlite', 'users')->where(fn (Builder $q) => $q->where('a', 1))->whereExists(fn (Builder $q) => $q->from('p'));
    $b = clone $a;
    $b->wheres[0]['query']->where('b', 2);
    $b->wheres[1]['query']->where('c', 3);

    expect($a->toSql())->toBe('select * from "users" where ("a" = ?) and exists (select * from "p")')
        ->and($b->toSql())->toBe('select * from "users" where ("a" = ? and "b" = ?) and exists (select * from "p" where "c" = ?)');
});
