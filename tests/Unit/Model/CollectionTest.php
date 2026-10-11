<?php

declare(strict_types=1);

use Slendie\Model\Collection;

function people(): Collection
{
    return new Collection([
        ['id' => 1, 'name' => 'Ana', 'age' => 30, 'team' => 'a'],
        ['id' => 2, 'name' => 'Rui', 'age' => 20, 'team' => 'b'],
        ['id' => 3, 'name' => 'Zé', 'age' => 40, 'team' => 'a'],
    ]);
}

it('all, toArray, toJson, count, isEmpty, isNotEmpty e iteração', function (): void {
    $c = new Collection([1, 2]);

    expect($c->all())->toBe([1, 2])
        ->and($c->toArray())->toBe([1, 2])
        ->and($c->toJson())->toBe('[1,2]')
        ->and((string) $c)->toBe('[1,2]')
        ->and(count($c))->toBe(2)
        ->and($c->isEmpty())->toBeFalse()
        ->and($c->isNotEmpty())->toBeTrue()
        ->and((new Collection())->isEmpty())->toBeTrue()
        ->and(iterator_to_array($c))->toBe([1, 2]);
});

it('map, filter, reject, each', function (): void {
    $c = new Collection([1, 2, 3, 4]);
    $seen = [];

    expect($c->map(fn (int $n): int => $n * 2)->all())->toBe([2, 4, 6, 8])
        ->and($c->filter(fn (int $n): bool => $n % 2 === 0)->values()->all())->toBe([2, 4])
        ->and((new Collection([0, 1, null, 2]))->filter()->values()->all())->toBe([1, 2])
        ->and($c->reject(fn (int $n): bool => $n > 2)->all())->toBe([1, 2]);

    $c->each(function (int $n) use (&$seen): bool {
        $seen[] = $n;

        return $n < 2;
    });

    expect($seen)->toBe([1, 2]);
});

it('first, last, get, has, pull, put, push, ArrayAccess', function (): void {
    $c = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);

    expect($c->first())->toBe(1)
        ->and($c->last())->toBe(3)
        ->and($c->first(fn (int $v): bool => $v > 1))->toBe(2)
        ->and($c->first(fn (): bool => false, 'x'))->toBe('x')
        ->and($c->get('b'))->toBe(2)
        ->and($c->get('z', 'd'))->toBe('d')
        ->and($c->get('z', fn (): string => 'lazy'))->toBe('lazy')
        ->and($c->has('a'))->toBeTrue()
        ->and($c['a'])->toBe(1)
        ->and(isset($c['z']))->toBeFalse();

    $c['d'] = 4;
    $c[] = 5;
    $c->put('e', 6)->push(7, 8);
    unset($c['a']);

    expect($c->pull('b'))->toBe(2)
        ->and($c->pull('nope', 'dflt'))->toBe('dflt')
        ->and($c->all())->toBe(['c' => 3, 'd' => 4, 0 => 5, 'e' => 6, 1 => 7, 2 => 8]);
});

it('pluck, keyBy, groupBy', function (): void {
    $c = people();

    expect($c->pluck('name')->all())->toBe(['Ana', 'Rui', 'Zé'])
        ->and($c->pluck('name', 'id')->all())->toBe([1 => 'Ana', 2 => 'Rui', 3 => 'Zé'])
        ->and($c->keyBy('id')->get(2)['name'])->toBe('Rui')
        ->and($c->keyBy(fn (array $p): string => strtolower($p['name']))->has('ana'))->toBeTrue()
        ->and($c->groupBy('team')->get('a'))->toBeInstanceOf(Collection::class)
        ->and($c->groupBy('team')->get('a')->count())->toBe(2)
        ->and($c->groupBy(fn (array $p): bool => $p['age'] > 25)->keys()->all())->toBe([1, 0]);
});

it('sortBy, sortByDesc, sortKeys, reverse, values, keys', function (): void {
    $c = people();

    expect($c->sortBy('age')->pluck('name')->all())->toBe(['Rui', 'Ana', 'Zé'])
        ->and($c->sortByDesc('age')->pluck('name')->all())->toBe(['Zé', 'Ana', 'Rui'])
        ->and($c->sortBy(fn (array $p): string => $p['name'], true)->first()['name'])->toBe('Zé')
        ->and((new Collection(['b' => 1, 'a' => 2]))->sortKeys()->keys()->all())->toBe(['a', 'b'])
        ->and((new Collection(['b' => 1, 'a' => 2]))->sortKeys(true)->keys()->all())->toBe(['b', 'a'])
        ->and((new Collection([1, 2, 3]))->reverse()->values()->all())->toBe([3, 2, 1]);
});

it('sum, avg, min, max', function (): void {
    $c = people();

    expect($c->sum('age'))->toBe(90)
        ->and($c->avg('age'))->toBe(30)
        ->and($c->min('age'))->toBe(20)
        ->and($c->max('age'))->toBe(40)
        ->and($c->sum(fn (array $p): int => $p['age'] * 2))->toBe(180)
        ->and((new Collection([1, 2, 3]))->sum())->toBe(6)
        ->and((new Collection())->avg())->toBeNull()
        ->and((new Collection())->min())->toBeNull()
        ->and((new Collection())->max('x'))->toBeNull();
});

it('contains, where, whereIn, unique', function (): void {
    $c = people();

    expect($c->contains(2))->toBeFalse()
        ->and((new Collection([1, 2]))->contains(2))->toBeTrue()
        ->and($c->contains(fn (array $p): bool => $p['age'] === 20))->toBeTrue()
        ->and($c->contains('name', 'Ana'))->toBeTrue()
        ->and($c->contains('name', 'Nobody'))->toBeFalse()
        ->and($c->where('team', 'a')->count())->toBe(2)
        ->and($c->where('age', '>', 25)->pluck('name')->values()->all())->toBe(['Ana', 'Zé'])
        ->and($c->where('age', '<=', 20)->count())->toBe(1)
        ->and($c->where('age', '!=', 20)->count())->toBe(2)
        ->and($c->whereIn('id', [1, 3])->count())->toBe(2)
        ->and((new Collection([1, 1, '1', 2]))->unique()->values()->all())->toBe([1, '1', 2])
        ->and($c->unique('team')->count())->toBe(2)
        ->and($c->unique(fn (array $p): bool => $p['age'] > 25)->count())->toBe(2);

    $c->where('age', '??', 1);
})->throws(InvalidArgumentException::class);

it('take, slice, chunk, merge, implode, only, except', function (): void {
    $c = new Collection([1, 2, 3, 4, 5]);

    expect($c->take(2)->all())->toBe([1, 2])
        ->and($c->take(-2)->values()->all())->toBe([4, 5])
        ->and($c->slice(1, 2)->values()->all())->toBe([2, 3])
        ->and($c->chunk(2)->count())->toBe(3)
        ->and($c->chunk(2)->first())->toBeInstanceOf(Collection::class)
        ->and($c->chunk(0)->count())->toBe(0)
        ->and($c->merge([6])->count())->toBe(6)
        ->and($c->merge(new Collection([7]))->last())->toBe(7)
        ->and($c->implode('-'))->toBe('1-2-3-4-5')
        ->and(people()->implode(',', 'name'))->toBe('Ana,Rui,Zé')
        ->and((new Collection(['a' => 1, 'b' => 2, 'c' => 3]))->only(['a', 'c'])->all())->toBe(['a' => 1, 'c' => 3])
        ->and((new Collection(['a' => 1, 'b' => 2]))->except('a')->all())->toBe(['b' => 2]);
});

it('random e shuffle', function (): void {
    $c = new Collection([1, 2, 3]);

    expect($c->random())->toBeIn([1, 2, 3])
        ->and($c->random(2))->toHaveCount(2)
        ->and($c->random(10))->toHaveCount(3)
        ->and($c->shuffle()->count())->toBe(3)
        ->and((new Collection())->random())->toBeNull()
        ->and((new Collection())->random(2)->count())->toBe(0);
});
