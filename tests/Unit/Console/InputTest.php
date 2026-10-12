<?php

declare(strict_types=1);

use Slendie\Console\Input;
use Slendie\Console\InvalidInputException;
use Slendie\Console\SignatureParser;

/**
 * @param  list<string>  $tokens
 */
function bound(string $signature, array $tokens): Input
{
    [$name, $definition] = SignatureParser::parse($signature);

    return Input::fromTokens($tokens)->bind($definition, $name);
}

it('argumentos posicionais, opcionais e valores por omissão', function (): void {
    $input = bound('x {a} {b?} {c=padrão}', ['um']);

    expect($input->arguments())->toBe(['a' => 'um', 'b' => null, 'c' => 'padrão'])
        ->and($input->argument('a'))->toBe('um')
        ->and($input->hasArgument('b'))->toBeTrue()
        ->and($input->hasArgument('z'))->toBeFalse();

    expect(bound('x {a} {b?}', ['um', 'dois'])->argument('b'))->toBe('dois');
});

it('argumentos array apanham o resto', function (): void {
    $input = bound('x {first} {rest*}', ['a', 'b', 'c', 'd']);

    expect($input->argument('first'))->toBe('a')
        ->and($input->argument('rest'))->toBe(['b', 'c', 'd']);

    expect(bound('x {rest?*}', [])->argument('rest'))->toBe([]);
});

it('opções booleanas, --opt=valor, --opt valor e valor por omissão', function (): void {
    $signature = 'x {--flag} {--name=} {--level=info} {--other=}';

    $input = bound($signature, ['--flag', '--name=Ana', '--other', 'Rui']);

    expect($input->option('flag'))->toBeTrue()
        ->and($input->option('name'))->toBe('Ana')
        ->and($input->option('other'))->toBe('Rui')
        ->and($input->option('level'))->toBe('info');

    // Sem nada: flags a false, valores ao default.
    $empty = bound($signature, []);

    expect($empty->option('flag'))->toBeFalse()
        ->and($empty->option('name'))->toBeNull()
        ->and($empty->options())->toBe(['flag' => false, 'name' => null, 'level' => 'info', 'other' => null]);

    // `--level` sem valor usa o default quando ele existe.
    expect(bound($signature, ['--level'])->option('level'))->toBe('info');
});

it('atalhos: -m, -Ovalor, -O valor, -O=valor e grupos como -mcr', function (): void {
    $signature = 'make:model {name} {--migration|-m} {--controller|-c} {--resource|-r} {--O|out=}';

    $group = bound($signature, ['Lead', '-mcr']);

    expect($group->option('migration'))->toBeTrue()
        ->and($group->option('controller'))->toBeTrue()
        ->and($group->option('resource'))->toBeTrue()
        ->and($group->argument('name'))->toBe('Lead');

    expect(bound($signature, ['Lead', '-Ofile.txt'])->option('out'))->toBe('file.txt')
        ->and(bound($signature, ['Lead', '-O', 'file.txt'])->option('out'))->toBe('file.txt')
        ->and(bound($signature, ['Lead', '-O=file.txt'])->option('out'))->toBe('file.txt')
        ->and(bound($signature, ['Lead', '-mOfile.txt'])->option('migration'))->toBeTrue()
        ->and(bound($signature, ['-m', 'Lead'])->argument('name'))->toBe('Lead');
});

it('opções array acumulam', function (): void {
    $input = bound('x {--tag=*}', ['--tag=a', '--tag', 'b', '--tag=c']);

    expect($input->option('tag'))->toBe(['a', 'b', 'c'])
        ->and(bound('x {--tag=*}', [])->option('tag'))->toBe([]);
});

it('-- termina as opções; hífen sozinho e números negativos são argumentos', function (): void {
    $input = bound('x {--flag} {a} {b}', ['--flag', '--', '--not-an-option', '-']);

    expect($input->option('flag'))->toBeTrue()
        ->and($input->argument('a'))->toBe('--not-an-option')
        ->and($input->argument('b'))->toBe('-')
        ->and(bound('x {n}', ['-5'])->argument('n'))->toBe('-5');
});

it('argumento obrigatório em falta lança com o modo de uso', function (): void {
    try {
        bound('make:controller {name} {--force}', []);
    } catch (InvalidInputException $e) {
        expect($e->getMessage())
            ->toContain('Argumentos em falta: "name"')
            ->toContain('Uso:')
            ->toContain('make:controller [options] [--] <name>');

        return;
    }

    throw new RuntimeException('Devia ter lançado.');
});

it('lista todos os argumentos em falta', function (): void {
    expect(fn () => bound('x {a} {b}', []))->toThrow(InvalidInputException::class, '"a", "b"');
});

it('opção desconhecida, atalho desconhecido, argumentos a mais', function (array $tokens, string $message): void {
    expect(fn () => bound('x {a} {--known}', $tokens))->toThrow(InvalidInputException::class, $message);
})->with([
    'opção longa' => [['v', '--nope'], 'A opção "--nope" não existe'],
    'atalho' => [['v', '-z'], 'A opção "-z" não existe'],
    'argumento a mais' => [['v', 'w'], 'Demasiados argumentos'],
    'flag com valor' => [['v', '--known=1'], 'não aceita valores'],
]);

it('opção com valor obrigatório sem valor lança', function (): void {
    expect(fn () => bound('x {--name=}', ['--name']))->toThrow(InvalidInputException::class, 'exige um valor');
    expect(fn () => bound('x {--name=} {--other}', ['--name', '--other']))->toThrow(InvalidInputException::class, 'exige um valor');
});

it('um comando sem argumentos recusa argumentos soltos', function (): void {
    expect(fn () => bound('x {--f}', ['solto']))->toThrow(InvalidInputException::class, 'não aceita argumentos');
});

it('fromArray: argumentos por nome, opções com -- e atalhos', function (): void {
    [$name, $definition] = SignatureParser::parse('x {name} {more*} {--force} {--model=} {--tag=*} {--migration|-m}');

    $input = Input::fromArray([
        'name' => 'Lead',
        'more' => ['a', 'b'],
        '--force' => true,
        '--model' => 'Lead',
        '--tag' => ['t1', 't2'],
        '-m' => true,
    ])->bind($definition, $name);

    expect($input->argument('name'))->toBe('Lead')
        ->and($input->argument('more'))->toBe(['a', 'b'])
        ->and($input->option('force'))->toBeTrue()
        ->and($input->option('model'))->toBe('Lead')
        ->and($input->option('tag'))->toBe(['t1', 't2'])
        ->and($input->option('migration'))->toBeTrue();
});

it('fromArray: chaves inteiras são posicionais, false/null desligam flags e valida', function (): void {
    [$name, $definition] = SignatureParser::parse('x {a} {b?} {--force} {--model=}');

    $input = Input::fromArray(['um', 'dois', '--force' => false, '--model' => null])->bind($definition, $name);

    expect($input->argument('a'))->toBe('um')
        ->and($input->argument('b'))->toBe('dois')
        ->and($input->option('force'))->toBeFalse()
        ->and($input->option('model'))->toBeNull();

    expect(fn () => Input::fromArray(['a' => 'x', '--nope' => 1])->bind($definition, $name))->toThrow(InvalidInputException::class, '"--nope"')
        ->and(fn () => Input::fromArray(['zzz' => 'x'])->bind($definition, $name))->toThrow(InvalidInputException::class, '"zzz"')
        ->and(fn () => Input::fromArray([])->bind($definition, $name))->toThrow(InvalidInputException::class, 'em falta');
});

it('lê linhas de um stream e interactividade', function (): void {
    $stream = fopen('php://memory', 'w+');
    fwrite($stream, "primeira\r\nsegunda\n");
    rewind($stream);

    $input = Input::fromTokens([])->setStream($stream);

    expect($input->isInteractive())->toBeTrue()
        ->and($input->readLine())->toBe('primeira')
        ->and($input->readLine())->toBe('segunda')
        ->and($input->readLine())->toBeNull();

    expect($input->setInteractive(false)->isInteractive())->toBeFalse();
});
