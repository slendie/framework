<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\Console\CommandProvider;
use Slendie\Tests\Fixtures\Console\ConsoleProject;

afterEach(fn () => ConsoleProject::cleanup());

/** Projecto com o AskCommand e uma entrada simulada em memória. */
function interactive(string $answers): ConsoleProject
{
    $project = ConsoleProject::make(['config/app.php' => appConfigWith(CommandProvider::class)]);

    $stream = fopen('php://memory', 'w+');
    fwrite($stream, $answers);
    rewind($stream);

    $project->console->setInputStream($stream)->setInteractive(null);

    return $project;
}

function appConfigWith(string $provider): string
{
    return "<?php\n\nreturn ".var_export([
        'name' => 'Teste', 'env' => 'local', 'debug' => false, 'url' => 'http://localhost',
        'key' => null, 'timezone' => 'UTC', 'locale' => 'pt', 'providers' => [$provider],
    ], true).";\n";
}

it('ask, secret, confirm e choice lêem da entrada indicada', function (): void {
    [$code, $output] = interactive("Ana\nsegredo\nno\ncsv\na\n")->artisan('ask');

    expect($code)->toBe(0)
        ->and($output)->toContain('Nome? [anónimo]:')
        ->and($output)->toContain('Password?:')
        ->and($output)->toContain('Certeza? (yes/no) [yes]:')
        ->and($output)->toContain('Formato?')
        ->and($output)->toContain('[0] pdf')
        ->and($output)->toContain('[a] alto')
        ->and($output)->toContain('name=Ana pass=segredo sure=false format=csv level=alto');
});

it('respostas vazias dão os valores por omissão', function (): void {
    [, $output] = interactive("\n\n\n\n\n")->artisan('ask');

    expect($output)->toContain('name=anónimo pass=null sure=true format=pdf level=baixo');
});

it('o fim da entrada também dá os valores por omissão', function (): void {
    [, $output] = interactive('')->artisan('ask');

    expect($output)->toContain('name=anónimo pass=null sure=true format=pdf level=baixo');
});

it('confirm aceita variantes e repete perguntas inválidas', function (string $answer, bool $expected): void {
    $project = interactive("x\n{$answer}\n");

    $project->console->command('sure', fn () => $this->line('result='.var_export($this->confirm('Certeza?', false), true)));

    [, $output] = $project->artisan('sure');

    expect($output)->toContain('result='.var_export($expected, true));
})->with([
    ['y', true], ['YES', true], ['s', true], ['sim', true],
    ['n', false], ['No', false], ['não', false], ['nao', false],
]);

it('choice aceita o índice, o valor e repete respostas inválidas', function (): void {
    $project = interactive("nada\n1\n");
    $project->console->command('pick', fn () => $this->line('picked='.$this->choice('Qual?', ['a', 'b', 'c'])));

    [, $output] = $project->artisan('pick');

    expect($output)->toContain('Opção inválida [nada]')
        ->and($output)->toContain('picked=b');
});

it('--no-interaction devolve os valores por omissão sem perguntar', function (): void {
    $project = interactive("Ana\nsegredo\nno\ncsv\na\n");

    [$code, $output] = $project->artisan('ask --no-interaction');

    expect($code)->toBe(0)
        ->and($output)->not->toContain('Nome?')
        ->and($output)->toContain('name=anónimo pass=null sure=true format=pdf level=baixo');

    [, $short] = $project->artisan('ask -n');

    expect($short)->toContain('name=anónimo');
});

it('sem terminal (STDIN não é TTY) os comandos não perguntam', function (): void {
    $project = ConsoleProject::make(['config/app.php' => appConfigWith(CommandProvider::class)]);

    [, $output] = $project->artisan('ask');

    expect($output)->not->toContain('Nome?')
        ->and($output)->toContain('name=anónimo pass=null sure=true format=pdf level=baixo');
});

it('task() mostra DONE/FAIL com a duração e devolve o resultado', function (): void {
    $project = ConsoleProject::make(['routes/console.php' => <<<'PHP'
        <?php
        use Slendie\Support\Facades\Console;

        Console::command('tasks', function () {
            $a = $this->task('Primeira', fn () => true);
            $b = $this->task('Segunda', fn () => false);
            $this->line('a='.var_export($a, true).' b='.var_export($b, true));

            try {
                $this->task('Terceira', function () { throw new RuntimeException('falhou'); });
            } catch (RuntimeException $e) {
                $this->line('apanhada: '.$e->getMessage());
            }
        });
        PHP]);

    [$code, $output] = $project->artisan('tasks');

    expect($code)->toBe(0)
        ->and($output)->toMatch('/^  Primeira \.{3,} DONE \(\d+ms\)$/m')
        ->and($output)->toMatch('/^  Segunda \.{3,} FAIL \(\d+ms\)$/m')
        ->and($output)->toMatch('/^  Terceira \.{3,} FAIL \(\d+ms\)$/m')
        ->and($output)->toContain('a=true b=false')
        ->and($output)->toContain('apanhada: falhou');
});

it('os estilos info/comment/question/warn vão para o stdout e error para o stderr', function (): void {
    $project = ConsoleProject::make(['routes/console.php' => <<<'PHP'
        <?php
        use Slendie\Support\Facades\Console;

        Console::command('styles', function () {
            $this->info('i');
            $this->comment('c');
            $this->question('q');
            $this->warn('w');
            $this->error('e');
            $this->newLine(2);
            $this->line('fim');
        });
        PHP]);

    [, $plain] = $project->artisan('styles --no-ansi');
    [, $colour] = $project->artisan('styles --ansi');

    expect($plain)->toBe("i\nc\nq\nw\ne\n\n\nfim\n")
        ->and($colour)->toContain("\e[32mi\e[0m")
        ->and($colour)->toContain("\e[33mc\e[0m")
        ->and($colour)->toContain("\e[36mq\e[0m")
        ->and($colour)->toContain("\e[37;41me\e[0m");
});

it('table() disponível nos comandos', function (): void {
    $project = ConsoleProject::make(['routes/console.php' => <<<'PHP'
        <?php
        use Slendie\Support\Facades\Console;

        Console::command('tbl', fn () => $this->table(['A', 'B'], [[1, 'x'], [22, 'yy']]));
        PHP]);

    [, $output] = $project->artisan('tbl');

    expect($output)->toContain('│ A  │ B  │')
        ->and($output)->toContain('│ 22 │ yy │');
});

it('produção pede confirmação nos comandos destrutivos e --force dispensa-a', function (): void {
    $project = ConsoleProject::make(['config/app.php' => "<?php\n\nreturn ['env' => 'production', 'debug' => false, 'providers' => []];\n"]);

    // Sem terminal a confirmação responde "não": cancelado.
    [$code, $output] = $project->artisan('db:wipe');

    expect($code)->toBe(1)
        ->and($output)->toContain('Application In Production')
        ->and($output)->toContain('Command cancelled');

    [$code, $output] = $project->artisan('db:wipe --force');

    expect($code)->toBe(0)->and($output)->toContain('Dropped all tables successfully');
});

it('em produção responde-se "yes" à confirmação', function (): void {
    $project = ConsoleProject::make(['config/app.php' => "<?php\n\nreturn ['env' => 'production', 'debug' => false, 'providers' => []];\n"]);

    $stream = fopen('php://memory', 'w+');
    fwrite($stream, "yes\n");
    rewind($stream);
    $project->console->setInputStream($stream)->setInteractive(null);

    [$code, $output] = $project->artisan('db:wipe');

    expect($code)->toBe(0)->and($output)->toContain('Do you really wish to run this command?');
});
