<?php

declare(strict_types=1);

use Slendie\Console\SignatureParser;

it('lê o nome e todas as formas de argumentos', function (): void {
    [$name, $definition] = SignatureParser::parse('mail:send {user} {cc?} {subject=Olá} {tags?*} ');

    expect($name)->toBe('mail:send');

    $arguments = $definition->arguments();

    expect(array_keys($arguments))->toBe(['user', 'cc', 'subject', 'tags'])
        ->and($arguments['user']->required)->toBeTrue()
        ->and($arguments['cc']->required)->toBeFalse()
        ->and($arguments['cc']->default)->toBeNull()
        ->and($arguments['subject']->required)->toBeFalse()
        ->and($arguments['subject']->default)->toBe('Olá')
        ->and($arguments['tags']->isArray)->toBeTrue()
        ->and($arguments['tags']->required)->toBeFalse();
});

it('argumentos: obrigatórios antes dos opcionais e array no fim', function (): void {
    [, $definition] = SignatureParser::parse('x {a} {b?} {c?*}');

    $arguments = $definition->arguments();

    expect($arguments['a']->required)->toBeTrue()
        ->and($arguments['b']->required)->toBeFalse()
        ->and($arguments['c']->isArray)->toBeTrue()
        ->and($arguments['c']->required)->toBeFalse();

    [, $definition] = SignatureParser::parse('x {list*}');

    expect($definition->argument('list')->required)->toBeTrue()
        ->and($definition->argument('list')->isArray)->toBeTrue();
});

it('lê todas as formas de opções', function (): void {
    [, $definition] = SignatureParser::parse('x {--flag} {--value=} {--level=info} {--O|opt} {--migration|-m} {--tag=*}');

    $options = $definition->options();

    expect($options['flag']->acceptsValue)->toBeFalse()
        ->and($options['flag']->default)->toBeFalse()
        ->and($options['value']->acceptsValue)->toBeTrue()
        ->and($options['value']->default)->toBeNull()
        ->and($options['level']->default)->toBe('info')
        ->and($options['opt']->shortcut)->toBe('O')
        ->and($options['migration']->shortcut)->toBe('m')
        ->and($options['tag']->isArray)->toBeTrue()
        ->and($options['tag']->default)->toBe([])
        ->and($definition->optionByShortcut('m')?->name)->toBe('migration');
});

it('lê descrições inline em argumentos e opções', function (): void {
    [, $definition] = SignatureParser::parse('x {name : Nome do utilizador} {--force : Não pede confirmação} {--level=info : Nível de log}');

    expect($definition->argument('name')->description)->toBe('Nome do utilizador')
        ->and($definition->option('force')->description)->toBe('Não pede confirmação')
        ->and($definition->option('level')->description)->toBe('Nível de log')
        ->and($definition->option('level')->default)->toBe('info');
});

it('aceita assinaturas em várias linhas e dois pontos no valor por omissão', function (): void {
    [$name, $definition] = SignatureParser::parse("make:command\n    {name}\n    {--command=app:name : Nome}\n");

    expect($name)->toBe('make:command')
        ->and($definition->option('command')->default)->toBe('app:name');
});

it('gera a linha de uso', function (): void {
    [, $definition] = SignatureParser::parse('x {a} {b?} {c?*} {--f}');

    expect($definition->synopsis('x'))->toBe('x [options] [--] <a> [<b>] [<c>...]');
});

it('recusa sintaxe inválida', function (string $signature, string $message): void {
    expect(fn () => SignatureParser::parse($signature))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'vazia' => ['', 'começar pelo nome'],
    'só chavetas' => ['{name}', 'começar pelo nome'],
    'nome inválido' => ['ma$ke {a}', 'Nome de comando inválido'],
    'texto solto' => ['x {a} lixo', 'fora de {chavetas}'],
    'token vazio' => ['x { }', 'vazio'],
    'argumento inválido' => ['x {1a}', 'Nome de argumento inválido'],
    'opção inválida' => ['x {--1a}', 'Nome de opção inválido'],
    'dois atalhos' => ['x {--a|b|c}', 'só é permitido um atalho'],
    'atalho comprido' => ['x {--ab|cd}', 'uma só letra'],
    'opção reservada' => ['x {--help}', 'reservada'],
    'atalho reservado' => ['x {--v|verb}', 'reservada'],
]);

it('recusa definições incoerentes', function (string $signature): void {
    expect(fn () => SignatureParser::parse($signature))->toThrow(LogicException::class);
})->with([
    'obrigatório depois de opcional' => ['x {a?} {b}'],
    'argumento depois de array' => ['x {a*} {b?}'],
    'argumento repetido' => ['x {a} {a}'],
    'opção repetida' => ['x {--a} {--a}'],
    'atalho repetido' => ['x {--a|x} {--b|x}'],
]);
