<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\Database\DbHelpers as Db;
use Slendie\Tests\Fixtures\Database\GrammarCases;

/**
 * Os casos comuns usam `"x"`; no MySQL os identificadores levam backticks.
 *
 * @param  array<string, array<int, mixed>>  $cases
 * @return array<string, array<int, mixed>>
 */
function mysqlCases(array $cases, int $sqlIndex): array
{
    return array_map(function (array $case) use ($sqlIndex): array {
        $case[$sqlIndex] = str_replace('"', '`', $case[$sqlIndex]);

        return $case;
    }, $cases);
}

dataset('mysql select cases', mysqlCases(GrammarCases::cases(), 1));
dataset('mysql write cases', mysqlCases(GrammarCases::writeCases(), 1));

it('MySQL: select', function (callable $build, string $sql, array $bindings): void {
    $builder = Db::builder('mysql');
    $build($builder);

    expect($builder->toSql())->toBe($sql)
        ->and($builder->getBindings())->toBe($bindings);
})->with('mysql select cases');

it('MySQL: escrita', function (callable $compile, string $sql): void {
    expect($compile(Db::builder('mysql')))->toBe($sql);
})->with('mysql write cases');

it('MySQL: backticks dentro do nome são duplicados mas nunca chegam lá (regex)', function (): void {
    expect(fn () => Db::builder('mysql', 'users')->select('a`b')->toSql())->toThrow(InvalidArgumentException::class);
});

it('MySQL: whereLike', function (): void {
    $b = Db::builder('mysql', 'users')->whereLike('name', 'silva')->whereLike('email', 'A%', true)->whereNotLike('x', 'y%', true);

    expect($b->toSql())->toBe('select * from `users` where `name` like ? and `email` like binary ? and `x` not like binary ?')
        ->and($b->getBindings())->toBe(['%silva%', 'A%', 'y%']);
});

it('MySQL: whereDate/Year/Month/Day', function (): void {
    $b = Db::builder('mysql', 'users')
        ->whereDate('created_at', '2026-05-09')
        ->whereYear('created_at', 2026)
        ->whereMonth('created_at', '>=', 5)
        ->whereDay('created_at', new DateTimeImmutable('2026-05-09'));

    expect($b->toSql())->toBe('select * from `users` where DATE(`created_at`) = ? and YEAR(`created_at`) = ? and MONTH(`created_at`) >= ? and DAY(`created_at`) = ?')
        ->and($b->getBindings())->toBe(['2026-05-09', 2026, 5, 9]);
});

it('MySQL: offset sem limit', function (): void {
    expect(Db::builder('mysql', 'users')->offset(5)->toSql())->toBe('select * from `users` limit 18446744073709551615 offset 5');
});

it('MySQL: inRandomOrder', function (): void {
    expect(Db::builder('mysql', 'users')->inRandomOrder()->toSql())->toBe('select * from `users` order by RAND()');
});

it('MySQL: insertOrIgnore, truncate e insertGetId', function (): void {
    $log = Db::offline('mysql')->pretend(function ($c): void {
        $c->table('users')->insertOrIgnore([['a' => 1], ['a' => 2]]);
        $c->table('users')->truncate();
        $c->table('users')->insertGetId(['name' => 'Ana']);
    });

    expect(array_column($log, 'sql'))->toBe([
        'insert ignore into `users` (`a`) values (?), (?)',
        'truncate table `users`',
        'insert into `users` (`name`) values (?)',
    ]);
});

it('MySQL: agregados, update com increment e exists', function (): void {
    $log = Db::offline('mysql')->pretend(function ($c): void {
        $c->table('users')->where('a', 1)->count();
        $c->table('users')->where('id', 3)->increment('votes', 2);
        $c->table('users')->where('a', 1)->exists();
    });

    expect(array_column($log, 'sql'))->toBe([
        'select count(*) as `aggregate` from `users` where `a` = ?',
        'update `users` set `votes` = `votes` + 2 where `id` = ?',
        'select exists(select * from `users` where `a` = ?) as `exists`',
    ]);
});
