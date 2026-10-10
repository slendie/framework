<?php

declare(strict_types=1);

use Slendie\Support\Arr;

it('get com dot notation, chaves inteiras e default', function (): void {
    $a = ['a' => ['b' => ['c' => 1]], 0 => 'zero', 'x.y' => 'literal'];

    expect(Arr::get($a, 'a.b.c'))->toBe(1)
        ->and(Arr::get($a, 'a.b'))->toBe(['c' => 1])
        ->and(Arr::get($a, 0))->toBe('zero')
        ->and(Arr::get($a, 'x.y'))->toBe('literal')
        ->and(Arr::get($a, 'a.z', 'd'))->toBe('d')
        ->and(Arr::get($a, null))->toBe($a);
});

it('set cria níveis intermédios', function (): void {
    $a = [];
    Arr::set($a, 'a.b.c', 1);
    Arr::set($a, 'a.d', 2);

    expect($a)->toBe(['a' => ['b' => ['c' => 1], 'd' => 2]]);
});

it('has e forget', function (): void {
    $a = ['a' => ['b' => 1, 'c' => 2], 'd' => 3];

    expect(Arr::has($a, 'a.b'))->toBeTrue()
        ->and(Arr::has($a, ['a.b', 'd']))->toBeTrue()
        ->and(Arr::has($a, ['a.b', 'nope']))->toBeFalse()
        ->and(Arr::has($a, []))->toBeFalse();

    Arr::forget($a, ['a.b', 'd', 'zz.yy']);

    expect($a)->toBe(['a' => ['c' => 2]]);
});

it('only, except, first, last', function (): void {
    $a = ['a' => 1, 'b' => 2, 'c' => 3];

    expect(Arr::only($a, ['a', 'c']))->toBe(['a' => 1, 'c' => 3])
        ->and(Arr::except($a, 'b'))->toBe(['a' => 1, 'c' => 3])
        ->and(Arr::first($a))->toBe(1)
        ->and(Arr::first($a, fn ($v) => $v > 1))->toBe(2)
        ->and(Arr::first([], null, 'd'))->toBe('d')
        ->and(Arr::last($a))->toBe(3)
        ->and(Arr::last($a, fn ($v) => $v < 3))->toBe(2);
});

it('flatten, pluck, dataGet, wrap', function (): void {
    $rows = [['id' => 1, 'u' => ['n' => 'A']], ['id' => 2, 'u' => ['n' => 'B']]];
    $obj = (object) ['p' => (object) ['q' => 'ok']];

    expect(Arr::flatten([1, [2, [3, [4]]]]))->toBe([1, 2, 3, 4])
        ->and(Arr::flatten([1, [2, [3]]], 1))->toBe([1, 2, [3]])
        ->and(Arr::pluck($rows, 'u.n'))->toBe(['A', 'B'])
        ->and(Arr::pluck($rows, 'u.n', 'id'))->toBe([1 => 'A', 2 => 'B'])
        ->and(Arr::dataGet($obj, 'p.q'))->toBe('ok')
        ->and(Arr::dataGet($obj, 'p.z', 'd'))->toBe('d')
        ->and(Arr::wrap(null))->toBe([])
        ->and(Arr::wrap('x'))->toBe(['x'])
        ->and(Arr::wrap([1]))->toBe([1]);
});

it('isAssoc, isList, where, map, dot, undot', function (): void {
    expect(Arr::isAssoc(['a' => 1]))->toBeTrue()
        ->and(Arr::isAssoc([1, 2]))->toBeFalse()
        ->and(Arr::isList([1, 2]))->toBeTrue()
        ->and(Arr::where([1, 2, 3, 4], fn ($v) => $v % 2 === 0))->toBe([1 => 2, 3 => 4])
        ->and(Arr::map(['a' => 1, 'b' => 2], fn ($v, $k) => "$k$v"))->toBe(['a' => 'a1', 'b' => 'b2'])
        ->and(Arr::dot(['a' => ['b' => 1, 'c' => ['d' => 2]], 'e' => []]))->toBe(['a.b' => 1, 'a.c.d' => 2, 'e' => []])
        ->and(Arr::undot(['a.b' => 1, 'a.c.d' => 2]))->toBe(['a' => ['b' => 1, 'c' => ['d' => 2]]]);
});
