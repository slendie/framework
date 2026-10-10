<?php

declare(strict_types=1);

use Slendie\Support\Env;

it('faz parse de todos os formatos de linha', function (): void {
    $parsed = Env::parse(<<<'ENV'
        # comentário
        PLAIN=valor
        SPACED="com espaços"   # comentário após aspas
        SINGLE='literal ${NAO} \n'
        ESCAPED="linha1\nlinha2 \"citado\""
        export EXPORTED=1
        EMPTY=
        INLINE=valor # comentário inline
        BOOL_T=true
        BOOL_F=(false)
        NULLISH=null
        EMPTYLIT=empty
        NUM=42
        REF=${PLAIN}-${SPACED}
        QUOTED_REF="x ${PLAIN} y"
        invalid line without equals
        ENV);

    expect($parsed)->toMatchArray([
        'PLAIN' => 'valor',
        'SPACED' => 'com espaços',
        'SINGLE' => 'literal ${NAO} \n',
        'ESCAPED' => "linha1\nlinha2 \"citado\"",
        'EXPORTED' => '1',
        'EMPTY' => '',
        'INLINE' => 'valor',
        'BOOL_T' => true,
        'BOOL_F' => false,
        'NULLISH' => null,
        'EMPTYLIT' => '',
        'NUM' => '42',
        'REF' => 'valor-com espaços',
        'QUOTED_REF' => 'x valor y',
    ])->and($parsed)->not->toHaveKey('invalid');
});

it('carrega um ficheiro e expõe via get/has/all, $_ENV e getenv', function (): void {
    Env::load(fixturePath('app/.env'));

    expect(Env::get('APP_NAME'))->toBe('Fixture App')
        ->and(Env::get('FIXTURE_FLAG'))->toBeTrue()
        ->and(Env::get('FIXTURE_URL'))->toBe('http://Fixture App.test')
        ->and(Env::has('APP_ENV'))->toBeTrue()
        ->and(Env::get('INEXISTENTE', 'd'))->toBe('d')
        ->and($_ENV['APP_NAME'])->toBe('Fixture App')
        ->and(getenv('APP_NAME'))->toBe('Fixture App')
        ->and(getenv('FIXTURE_FLAG'))->toBe('true')
        ->and(Env::all())->toHaveKey('APP_TIMEZONE');
});

it('não lança quando o ficheiro não existe', function (): void {
    Env::load('/caminho/que/nao/existe/.env');

    expect(Env::all())->toBe([]);
});

it('não sobrepõe valores existentes excepto com overwrite', function (): void {
    Env::set('APP_NAME', 'Do Servidor');
    Env::load(fixturePath('app/.env'));

    expect(Env::get('APP_NAME'))->toBe('Do Servidor');

    Env::load(fixturePath('app/.env'), overwrite: true);

    expect(Env::get('APP_NAME'))->toBe('Fixture App');
});

it('set, forget e flush gerem o estado', function (): void {
    Env::set('X', 'y');
    expect(Env::has('X'))->toBeTrue();

    Env::forget('X');
    expect(Env::has('X'))->toBeFalse()->and(getenv('X'))->toBeFalse();

    Env::set('A', 1);
    Env::flush();
    expect(Env::all())->toBe([])->and(Env::has('A'))->toBeFalse();
});
