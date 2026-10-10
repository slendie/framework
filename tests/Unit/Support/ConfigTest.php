<?php

declare(strict_types=1);

use Slendie\Support\Config;

it('get/set/has com dot notation e default', function (): void {
    $config = new Config(['app' => ['name' => 'X', 'nested' => ['a' => 1]]]);

    expect($config->get('app.name'))->toBe('X')
        ->and($config->get('app.nested.a'))->toBe(1)
        ->and($config->get('app.nope', 'def'))->toBe('def')
        ->and($config->has('app.nested.a'))->toBeTrue()
        ->and($config->has('app.zzz'))->toBeFalse();

    $config->set('app.nested.b', 2);
    $config->set(['db.host' => 'h', 'db.port' => 1]);

    expect($config->get('app.nested'))->toBe(['a' => 1, 'b' => 2])
        ->and($config->get('db'))->toBe(['host' => 'h', 'port' => 1]);
});

it('carrega uma pasta de ficheiros php', function (): void {
    putenv('APP_NAME=FromEnv');
    $config = new Config();
    $config->loadFromDirectory(fixturePath('app/config'));
    putenv('APP_NAME');

    expect($config->get('app.name'))->toBe('FromEnv')
        ->and($config->get('services.mailer.port'))->toBe(2525)
        ->and($config->get('app.nested.level.deep'))->toBe('value');
});

it('merge preenche só as chaves em falta (projecto vence)', function (): void {
    $config = new Config(['pkg' => ['shared' => 'project']]);
    $config->merge('pkg', ['shared' => 'package', 'only_pkg' => 1]);

    expect($config->get('pkg'))->toBe(['shared' => 'project', 'only_pkg' => 1]);
});

it('push, forget e ArrayAccess', function (): void {
    $config = new Config(['list' => ['a']]);
    $config->push('list', 'b');
    $config['x.y'] = 1;

    expect($config->get('list'))->toBe(['a', 'b'])
        ->and($config['x.y'])->toBe(1)
        ->and(isset($config['x.y']))->toBeTrue();

    unset($config['x.y']);
    $config->forget('list');

    expect($config->all())->toBe(['x' => []]);
});
