<?php

declare(strict_types=1);

use Slendie\Database\Query\Builder;
use Slendie\Database\Query\Expression;
use Slendie\Database\Query\Grammar;
use Slendie\Tests\Fixtures\Database\DbHelpers as Db;

dataset('identificadores inválidos', [
    'espaço' => 'a b',
    'ponto e vírgula' => 'id; drop table users',
    'comentário' => 'id --',
    'aspas' => 'na"me',
    'plica' => "na'me",
    'backtick' => 'na`me',
    'parênteses' => 'count(*)',
    'três partes' => 'a.b.c',
    'começa por dígito' => '1abc',
    'vazio' => '',
    'ponto solto' => 'a.',
    'alias inválido' => 'a as b c',
    'unicode' => 'nãme',
]);

it('rejeita identificadores inválidos em select, from, where, orderBy, groupBy, join e whereIn', function (string $bad): void {
    $attempts = [
        fn () => Db::builder()->from('users')->select($bad)->toSql(),
        fn () => Db::builder()->from($bad),
        fn () => Db::builder('sqlite', 'users')->where($bad, 1),
        fn () => Db::builder('sqlite', 'users')->orderBy($bad),
        fn () => Db::builder('sqlite', 'users')->groupBy($bad),
        fn () => Db::builder('sqlite', 'users')->whereIn($bad, [1]),
        fn () => Db::builder('sqlite', 'users')->whereNull($bad),
        fn () => Db::builder('sqlite', 'users')->whereColumn('a', $bad),
        fn () => Db::builder('sqlite', 'users')->join($bad, 'a', 'b')->toSql(),
        fn () => Db::builder('sqlite', 'users')->join('p', $bad, 'b'),
        fn () => Db::builder('sqlite', 'users')->whereLike($bad, 'x'),
        fn () => Db::builder('sqlite', 'users')->increment($bad),
    ];

    foreach ($attempts as $i => $attempt) {
        expect($attempt)->toThrow(InvalidArgumentException::class);
    }
})->with('identificadores inválidos');

it('aceita identificadores válidos', function (): void {
    foreach (['id', '_x', 'users.id', 'Users.Name', 'a1_b2', 'name as n', 'users.name AS n', '*', 'users.*'] as $name) {
        expect(Grammar::assertIdentifier($name))->toBe($name);
    }
});

it('Expression passa tal como está (SQL cru explícito)', function (): void {
    $b = Db::builder('sqlite', 'users')->select(new Expression('count(*) as c'))->groupBy(new Expression('lower(name)'))->orderBy(new Expression('c'), 'desc');

    expect($b->toSql())->toBe('select count(*) as c from "users" group by lower(name) order by c desc');
});

it('rejeita operadores fora da lista fechada', function (): void {
    foreach (['regexp', '= 1 or 1 =', '; drop', '&&', '', 'like binary', '<=>'] as $op) {
        expect(fn () => Db::builder('sqlite', 'users')->where('a', $op, 1))->toThrow(InvalidArgumentException::class);
    }

    expect(fn () => Db::builder('sqlite', 'users')->whereColumn('a', 'regexp', 'b'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->having('a', 'regexp', 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->whereDate('a', 'like', 1))->toThrow(InvalidArgumentException::class);
});

it('aceita todos os operadores da lista, com qualquer capitalização', function (): void {
    foreach (['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'LIKE', 'not like', 'Not  Like', 'ilike'] as $op) {
        $b = Db::builder('sqlite', 'users')->where('a', $op, 'x');

        expect($b->toSql())->toStartWith('select * from "users" where "a" ');
    }
});

it('rejeita direcções de ordenação inválidas', function (): void {
    expect(fn () => Db::builder('sqlite', 'users')->orderBy('id', 'sideways'))->toThrow(InvalidArgumentException::class, 'Direcção')
        ->and(fn () => Db::builder('sqlite', 'users')->orderBy('id', 'asc; drop table users'))->toThrow(InvalidArgumentException::class);
});

it('input do utilizador nunca é concatenado no SQL: vai sempre em bindings', function (): void {
    $evil = "x' OR '1'='1'; DROP TABLE users; --";

    $b = Db::builder('sqlite', 'users')
        ->where('name', $evil)
        ->whereLike('email', $evil)
        ->whereIn('id', [$evil])
        ->whereBetween('n', [$evil, $evil])
        ->whereDate('d', $evil)
        ->having('x', '>', $evil);

    expect($b->toSql())->not->toContain('DROP')->not->toContain('OR ')
        ->and($b->getBindings())->toBe([$evil, '%'.$evil.'%', $evil, $evil, $evil, $evil, $evil]);
});

it('valida argumentos estruturais', function (): void {
    expect(fn () => Db::builder('sqlite', 'users')->whereBetween('a', [1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->join('p', 'a', 'b', 'x', 'diagonal'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->aggregate('count; --', ['*']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->where('a', 'in', 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->where('a', '>', null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->where('a', '>', [1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->chunk(0, fn () => null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->insert([['a' => 1], ['b' => 2]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->join('p', 'a', 'b')->update(['a' => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Db::builder('sqlite', 'users')->join('p', 'a', 'b')->delete())->toThrow(InvalidArgumentException::class);
});

it('limit e offset negativos ficam a 0 e são sempre inteiros no SQL', function (): void {
    $b = Db::builder('sqlite', 'users')->limit(-5)->offset(-1);

    expect($b->toSql())->toBe('select * from "users" limit 0 offset 0');
});

it('insert normaliza a ordem das colunas', function (): void {
    $log = Db::offline()->pretend(fn ($c) => $c->table('users')->insert([['b' => 2, 'a' => 1], ['a' => 3, 'b' => 4]]));

    expect($log[0]['sql'])->toBe('insert into "users" ("a", "b") values (?, ?), (?, ?)')
        ->and($log[0]['bindings'])->toBe([1, 2, 3, 4]);
});

it('insert vazio não faz nada', function (): void {
    expect(Db::offline()->pretend(fn ($c) => $c->table('users')->insert([])))->toBe([]);
});

it('Grammar::interpolate escapa valores e deixa ? sem binding', function (): void {
    expect(Grammar::interpolate('a = ? and b = ? and c = ?', ["it's", null]))->toBe("a = 'it''s' and b = NULL and c = ?");
});

it('o Builder é independente do driver quando offline', function (): void {
    expect(Db::builder('mysql'))->toBeInstanceOf(Builder::class);
});
