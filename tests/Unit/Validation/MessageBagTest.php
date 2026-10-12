<?php

declare(strict_types=1);

use Slendie\Validation\MessageBag;

function sampleBag(): MessageBag
{
    return new MessageBag([
        'name' => ['Obrigatório'],
        'email' => ['Inválido', 'Demasiado longo'],
        'items.0.qty' => ['Mínimo 2'],
        'items.1.qty' => 'Inteiro',
    ]);
}

it('add / merge não duplicam a mesma mensagem no mesmo campo', function (): void {
    $bag = new MessageBag();
    $bag->add('a', 'x')->add('a', 'x')->add('a', 'y')->add('b', 'x');

    expect($bag->get('a'))->toBe(['x', 'y'])
        ->and($bag->count())->toBe(3);

    $bag->merge(['a' => ['y', 'z'], 'c' => 'w'])->merge(new MessageBag(['d' => ['q']]));

    expect($bag->all())->toBe(['x', 'y', 'z', 'x', 'w', 'q']);
});

it('has / hasAny / first / get com formato', function (): void {
    $bag = sampleBag();

    expect($bag->has('name'))->toBeTrue()
        ->and($bag->has('nada'))->toBeFalse()
        ->and($bag->has(['name', 'email']))->toBeTrue()
        ->and($bag->has(['name', 'nada']))->toBeFalse()
        ->and($bag->hasAny(['nada', 'email']))->toBeTrue()
        ->and($bag->hasAny('nada'))->toBeFalse()
        ->and($bag->has(null))->toBeTrue()
        ->and((new MessageBag())->has(null))->toBeFalse()
        ->and($bag->first('email'))->toBe('Inválido')
        ->and($bag->first('nada'))->toBe('')
        ->and($bag->first())->toBe('Obrigatório')
        ->and($bag->first('name', '<li>:message</li>'))->toBe('<li>Obrigatório</li>')
        ->and($bag->get('email', '- :message'))->toBe(['- Inválido', '- Demasiado longo']);
});

it('wildcards em has/get', function (): void {
    $bag = sampleBag();

    expect($bag->has('items.*.qty'))->toBeTrue()
        ->and($bag->has('items.*.price'))->toBeFalse()
        ->and($bag->get('items.*.qty'))->toBe(['Mínimo 2', 'Inteiro'])
        ->and($bag->first('items.*.qty'))->toBe('Mínimo 2');
});

it('all / keys / count / isEmpty / isNotEmpty / any', function (): void {
    $bag = sampleBag();

    expect($bag->all())->toBe(['Obrigatório', 'Inválido', 'Demasiado longo', 'Mínimo 2', 'Inteiro'])
        ->and($bag->all('[:message]')[0])->toBe('[Obrigatório]')
        ->and($bag->keys())->toBe(['name', 'email', 'items.0.qty', 'items.1.qty'])
        ->and($bag->count())->toBe(5)
        ->and(count($bag))->toBe(5)
        ->and($bag->isEmpty())->toBeFalse()
        ->and($bag->isNotEmpty())->toBeTrue()
        ->and($bag->any())->toBeTrue()
        ->and((new MessageBag())->isEmpty())->toBeTrue()
        ->and((new MessageBag())->any())->toBeFalse();
});

it('toArray / getMessages / toJson / JsonSerializable / __toString', function (): void {
    $bag = new MessageBag(['a' => ['x']]);

    expect($bag->toArray())->toBe(['a' => ['x']])
        ->and($bag->getMessages())->toBe(['a' => ['x']])
        ->and($bag->toJson())->toBe('{"a":["x"]}')
        ->and(json_encode($bag))->toBe('{"a":["x"]}')
        ->and((string) $bag)->toBe('{"a":["x"]}');
});
