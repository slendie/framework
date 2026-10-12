<?php

declare(strict_types=1);

use Slendie\Console\BufferedOutput;
use Slendie\Console\Output;

it('com ANSI aplica as etiquetas de estilo; sem ANSI remove-as', function (): void {
    $on = new BufferedOutput(decorated: true);
    $on->writeln('<info>ok</info> <comment>aviso</comment> <error>erro</error> <question>?</question>');

    expect($on->fetch())->toBe("\e[32mok\e[0m \e[33maviso\e[0m \e[37;41merro\e[0m \e[36m?\e[0m".PHP_EOL);

    $off = new BufferedOutput(decorated: false);
    $off->writeln('<info>ok</info> e <comment>aviso</comment>');

    expect($off->fetch())->toBe('ok e aviso'.PHP_EOL);
});

it('etiquetas desconhecidas e texto sem fecho ficam como estão', function (): void {
    $out = new BufferedOutput(decorated: true);
    $out->writeln('<nope>x</nope> <b> 1 < 2 e <info>sem fecho');

    expect($out->fetch())->toBe('<nope>x</nope> <b> 1 < 2 e <info>sem fecho'.PHP_EOL);
});

it('strip e width ignoram estilos e ANSI e medem com mb_strwidth', function (): void {
    $out = new BufferedOutput();

    expect($out->strip("<info>ação</info> \e[31mx\e[0m"))->toBe('ação x')
        ->and($out->width('<info>ação</info>'))->toBe(4)
        ->and($out->width('日本'))->toBe(4);
});

it('verbosidade filtra o stdout mas não o stderr', function (): void {
    $out = new BufferedOutput(verbosity: Output::VERBOSITY_NORMAL);

    $out->writeln('normal');
    $out->writeln('verboso', Output::VERBOSITY_VERBOSE);
    $out->errorln('erro');

    expect($out->fetch())->toBe('normal'.PHP_EOL.'erro'.PHP_EOL);

    $out->setVerbosity(Output::VERBOSITY_VERBOSE);
    $out->writeln('verboso', Output::VERBOSITY_VERBOSE);
    $out->writeln('muito', Output::VERBOSITY_VERY_VERBOSE);

    expect($out->fetch())->toBe('verboso'.PHP_EOL)
        ->and($out->isVerbose())->toBeTrue()
        ->and($out->isVeryVerbose())->toBeFalse();

    $out->setVerbosity(Output::VERBOSITY_QUIET);
    $out->writeln('calado');
    $out->errorln('mas o erro vê-se');

    expect($out->isQuiet())->toBeTrue()
        ->and($out->fetch())->toBe('mas o erro vê-se'.PHP_EOL);

    expect($out->setVerbosity(99)->getVerbosity())->toBe(Output::VERBOSITY_VERY_VERBOSE);
});

it('table() alinha colunas, também com acentos e caracteres largos', function (): void {
    $out = new BufferedOutput();
    $out->table(['Nome', 'Cidade'], [['José', 'São Paulo'], ['日本', 'Tóquio'], ['Ana']]);

    $lines = explode(PHP_EOL, trim($out->fetch()));

    expect($lines)->toHaveCount(7)
        ->and($lines[0])->toBe('┌──────┬───────────┐')
        ->and($lines[1])->toBe('│ Nome │ Cidade    │')
        ->and($lines[2])->toBe('├──────┼───────────┤')
        ->and($lines[3])->toBe('│ José │ São Paulo │')
        ->and($lines[4])->toBe('│ 日本 │ Tóquio    │')
        ->and($lines[5])->toBe('│ Ana  │           │')
        ->and($lines[6])->toBe('└──────┴───────────┘');

    expect(array_unique(array_map(fn (string $l): int => mb_strwidth($l), $lines)))->toBe([0 => 20]);
});

it('table() ASCII e células com estilo medem só o texto visível', function (): void {
    $out = new BufferedOutput();
    $out->setUnicode(false);
    $out->table(['A', 'Estado'], [['x', '<info>Ran</info>'], ['y', '<comment>Pending</comment>']]);

    expect($out->fetch())->toBe(implode(PHP_EOL, [
        '+---+---------+',
        '| A | Estado  |',
        '+---+---------+',
        '| x | Ran     |',
        '| y | Pending |',
        '+---+---------+',
    ]).PHP_EOL);
});

it('table() sem linhas desenha só o cabeçalho', function (): void {
    $out = new BufferedOutput();
    $out->setUnicode(false);
    $out->table(['A'], []);

    expect($out->fetch())->toBe("+---+\n| A |\n+---+\n+---+\n" === '' ? '' : implode(PHP_EOL, ['+---+', '| A |', '+---+', '+---+']).PHP_EOL);
});

it('detectAnsi respeita NO_COLOR e streams que não são terminais', function (): void {
    $memory = fopen('php://memory', 'w+');

    expect(Output::detectAnsi($memory))->toBeFalse()
        ->and(Output::detectAnsi(null))->toBeFalse();

    putenv('NO_COLOR=1');

    try {
        expect(Output::detectAnsi(STDOUT))->toBeFalse();
    } finally {
        putenv('NO_COLOR');
    }
});

it('terminalWidth lê COLUMNS dentro de limites', function (): void {
    $out = new BufferedOutput();

    putenv('COLUMNS=100');
    expect($out->terminalWidth())->toBe(100);
    putenv('COLUMNS=10');
    expect($out->terminalWidth())->toBe(40);
    putenv('COLUMNS=999');
    expect($out->terminalWidth())->toBe(120);
    putenv('COLUMNS=abc');
    expect($out->terminalWidth())->toBe(80);
    putenv('COLUMNS');
});
