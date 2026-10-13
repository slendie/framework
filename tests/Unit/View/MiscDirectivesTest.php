<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\View\ViewHelpers as V;
use Slendie\View\CompileException;

it('@dd e @dump compilam para as funções homónimas', function (): void {
    expect(V::compile('@dd($x)'))->toContain('dd($x)')
        ->and(V::compile('@dump($x, $y)'))->toContain('dump($x, $y)');
});

it('@inject cria a variável a partir do container e valida os argumentos', function (): void {
    expect(V::compile("@inject('metrics', 'App\\Services\\Metrics')"))->toContain('$metrics = app(')
        ->and(fn () => V::compile('@inject($x)'))->toThrow(CompileException::class, '@inject')
        ->and(fn () => V::compile("@inject('1x', 'A')"))->toThrow(CompileException::class, '@inject');
});

it('@php inline compila e vazio falha', function (): void {
    expect(V::render('@php($x = 2){{ $x }}'))->toBe('2')
        ->and(fn () => V::compile('@php()'))->toThrow(CompileException::class);
});
