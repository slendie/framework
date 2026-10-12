<?php

declare(strict_types=1);

use Slendie\Console\Application as Console;
use Slendie\Console\BufferedOutput;
use Slendie\Console\Command;
use Slendie\Console\CommandNotFoundException;
use Slendie\Support\Facades\Console as ConsoleFacade;
use Slendie\Tests\Fixtures\Console\CommandProvider;
use Slendie\Tests\Fixtures\Console\ConsoleProject;

afterEach(fn () => ConsoleProject::cleanup());

/** @param  array<string, mixed>  $config */
function appConfig(array $config = []): string
{
    return "<?php\n\nreturn ".var_export([
        'name' => 'Teste', 'env' => 'local', 'debug' => false, 'url' => 'http://localhost',
        'key' => null, 'timezone' => 'UTC', 'locale' => 'pt', 'providers' => [],
        ...$config,
    ], true).";\n";
}

function withProvider(): ConsoleProject
{
    return ConsoleProject::make(['config/app.php' => appConfig(['providers' => [CommandProvider::class]])]);
}

it('a aplicação de consola é um singleton resolvido antes do bootstrap', function (): void {
    $project = ConsoleProject::make();

    expect($project->app->make(Console::class))->toBe($project->console)
        ->and($project->app->hasBeenBootstrapped())->toBeFalse()
        ->and($project->app->runningInConsole())->toBeTrue();

    $project->artisan('env');

    expect($project->app->hasBeenBootstrapped())->toBeTrue()
        ->and($project->app->make(Console::class))->toBe($project->console);
});

it('sem argumentos lista os comandos por grupos', function (): void {
    [$code, $output] = ConsoleProject::make()->artisan('');

    expect($code)->toBe(0)
        ->and($output)->toContain('Slendie Framework 4.0.0-dev')
        ->and($output)->toContain('Comandos disponíveis')
        ->and($output)->toMatch('/\bmigrate:status\s+Mostra o estado de cada migração/')
        ->and($output)->toMatch('/\bmake:controller\b/')
        ->and($output)->toMatch('/\broute:list\b/')
        ->and($output)->toMatch('/\bkey:generate\b/')
        ->and($output)->toMatch('/\bvendor:publish\b/')
        ->and($output)->toMatch('/\bview:clear\b/')
        ->and($output)->toMatch('/\bserve\b/')
        ->and($output)->toMatch('/\bdb:seed\b/')
        ->and($output)->not->toContain('<info>');
});

it('list filtra por grupo e falha num grupo inexistente', function (): void {
    $project = ConsoleProject::make();

    [$code, $output] = $project->artisan('list make');

    expect($code)->toBe(0)
        ->and($output)->toContain('make:model')
        ->and($output)->not->toContain('migrate:status');

    [$code, $output] = $project->artisan('list nada');

    expect($code)->toBe(1)
        ->and($output)->toContain('Não há comandos no grupo "nada"');
});

it('help mostra descrição, uso, argumentos e opções com defaults', function (): void {
    $project = withProvider();

    [$code, $output] = $project->artisan('help greet');

    expect($code)->toBe(0)
        ->and($output)->toContain('Cumprimenta alguém')
        ->and($output)->toContain('greet [options] [--] <name>')
        ->and($output)->toMatch('/name\s+Quem/')
        ->and($output)->toMatch('/--shout\s+Em maiúsculas/')
        ->and($output)->toMatch('/--times=TIMES\s+Repetições \[por omissão: 1\]/');

    [, $same] = $project->artisan('greet --help');
    [, $short] = $project->artisan('greet -h');

    expect($same)->toBe($output)->and($short)->toBe($output);
});

it('help de um comando inexistente falha com sugestão', function (): void {
    [$code, $output] = ConsoleProject::make()->artisan('help migrat');

    expect($code)->toBe(1)->and($output)->toContain('O comando "migrat" não existe');
});

it('--version mostra a versão', function (): void {
    [$code, $output] = ConsoleProject::make()->artisan('--version');

    expect($code)->toBe(0)->and($output)->toContain('Slendie Framework 4.0.0-dev');
});

it('comando desconhecido sugere os parecidos e devolve 1', function (string $typed, array $suggested): void {
    [$code, $output] = ConsoleProject::make()->artisan($typed);

    expect($code)->toBe(1)->and($output)->toContain("O comando \"{$typed}\" não existe");

    foreach ($suggested as $name) {
        expect($output)->toContain('Quis dizer')->and($output)->toContain("    {$name}");
    }
})->with([
    'erro de escrita' => ['migrate:stats', ['migrate:status']],
    'letra a mais' => ['migraate', ['migrate']],
    'prefixo' => ['make:con', ['make:controller']],
    'troca' => ['make:contrller', ['make:controller']],
]);

it('comando desconhecido sem parecidos não sugere nada', function (): void {
    [$code, $output] = ConsoleProject::make()->artisan('zzzzzzzzzzzz');

    expect($code)->toBe(1)
        ->and($output)->toContain('não existe')
        ->and($output)->not->toContain('Quis dizer');
});

it('find() lança CommandNotFoundException com as alternativas', function (): void {
    $project = ConsoleProject::make();
    $project->artisan('env');
    $console = $project->console;

    try {
        $console->find('migrate:stats');
    } catch (CommandNotFoundException $e) {
        expect($e->alternatives())->toContain('migrate:status');

        return;
    }

    throw new RuntimeException('Devia ter lançado.');
});

it('argumentos inválidos devolvem 2 com o modo de uso', function (): void {
    [$code, $output] = ConsoleProject::make()->artisan('make:controller');

    expect($code)->toBe(2)
        ->and($output)->toContain('Argumentos em falta: "name"')
        ->and($output)->toContain('make:controller [options] [--] <name>');

    [$code, $output] = ConsoleProject::make()->artisan('make:controller X --nao-existe');

    expect($code)->toBe(2)->and($output)->toContain('A opção "--nao-existe" não existe');
});

it('has/all/find e aliases', function (): void {
    $project = withProvider();
    $project->artisan('env');

    $console = $project->console;

    expect($console->has('greet'))->toBeTrue()
        ->and($console->has('hello'))->toBeTrue()
        ->and($console->has('nope'))->toBeFalse()
        ->and($console->find('hello'))->toBe($console->find('greet'))
        ->and(array_keys($console->all()))->toContain('greet', 'ask', 'list', 'help', 'migrate')
        ->and($console->all())->not->toHaveKey('hello');

    [$code, $output] = $project->artisan('hello Ana --shout');

    expect($code)->toBe(0)->and($output)->toBe('OLÁ, ANA!'."\n");
});

it('comandos de providers (ServiceProvider::commands) recebem DI no handle()', function (): void {
    $project = withProvider();

    [$code, $output] = $project->artisan('greet Ana --times=2');

    expect($code)->toBe(0)->and($output)->toBe("Olá, Ana!\nOlá, Ana!\n");
});

it('uma opção -q silencia o stdout, -v mostra detalhes das excepções', function (): void {
    $project = ConsoleProject::make(['routes/console.php' => <<<'PHP'
        <?php
        use Slendie\Support\Facades\Console;
        Console::command('boom', function () { $this->line('antes'); throw new RuntimeException('rebentou'); });
        Console::command('chatty', function () { $this->line('normal'); $this->line('detalhe', null, 2); });
        PHP]);

    [$code, $output] = $project->artisan('boom');

    expect($code)->toBe(1)
        ->and($output)->toContain('antes')
        ->and($output)->toContain('ERROR')
        ->and($output)->toContain('rebentou')
        ->and($output)->toContain('Usa -v')
        ->and($output)->not->toContain('RuntimeException em');

    [, $verbose] = $project->artisan('boom -v');

    expect($verbose)->toContain('RuntimeException em')->and($verbose)->toContain('#0');

    [, $quiet] = $project->artisan('chatty -q');
    [, $normal] = $project->artisan('chatty');
    [, $detail] = $project->artisan('chatty -v');

    expect($quiet)->toBe('')
        ->and($normal)->toBe('normal'."\n")
        ->and($detail)->toBe('normal'."\n".'detalhe'."\n");
});

it('--ansi e --no-ansi controlam as cores', function (): void {
    $project = ConsoleProject::make();

    [, $plain] = $project->artisan('env --no-ansi');
    [, $colour] = $project->artisan('env --ansi');

    expect($plain)->toBe('Current application environment: local'."\n")
        ->and($colour)->toContain("\e[32mlocal\e[0m");
});

it('o código de saída do handle() é o do comando; void/null dão 0', function (): void {
    $project = ConsoleProject::make(['routes/console.php' => <<<'PHP'
        <?php
        use Slendie\Support\Facades\Console;
        Console::command('three', fn () => 3);
        Console::command('nothing', function () { $this->line('x'); });
        Console::command('fail', fn () => Slendie\Console\Command::FAILURE);
        PHP]);

    expect($project->artisan('three')[0])->toBe(3)
        ->and($project->artisan('nothing')[0])->toBe(0)
        ->and($project->artisan('fail')[0])->toBe(1);
});

it('comandos por closure em routes/console.php: argumentos, opções e DI como parâmetros', function (): void {
    $project = ConsoleProject::make([
        'config/app.php' => appConfig(['providers' => [CommandProvider::class]]),
        'routes/console.php' => <<<'PHP'
            <?php
            use Slendie\Support\Facades\Console;
            use Slendie\Tests\Fixtures\GreeterContract;

            Console::command('hello:closure {name} {--loud}', function (string $name, bool $loud, GreeterContract $greeter) {
                $text = $greeter->greet($name);
                $this->info($loud ? mb_strtoupper($text) : $text);
                $this->line('args='.json_encode($this->arguments()));
            })->purpose('Cumprimenta por closure');
            PHP,
    ]);

    [$code, $output] = $project->artisan('hello:closure Ana --loud');

    expect($code)->toBe(0)
        ->and($output)->toContain('OLÁ, ANA!')
        ->and($output)->toContain('args={"name":"Ana"}');

    [, $list] = $project->artisan('list');

    expect($list)->toMatch('/hello:closure\s+Cumprimenta por closure/');
});

it('a facade Console regista comandos e chama-os', function (): void {
    $project = ConsoleProject::make();
    $project->artisan('env');

    ConsoleFacade::command('ping', fn () => $this->line('pong'));

    expect(ConsoleFacade::has('ping'))->toBeTrue()
        ->and($project->artisan('ping'))->toBe([0, 'pong'."\n"]);
});

it('descobre comandos em app/Console/Commands (inclui sub-pastas), sem registo', function (): void {
    $name = 'Ping'.bin2hex(random_bytes(3));
    $nested = 'Deep'.bin2hex(random_bytes(3));
    $plain = 'Plain'.bin2hex(random_bytes(3));

    $project = ConsoleProject::make([
        "app/Console/Commands/{$name}.php" => <<<PHP
            <?php
            namespace App\\Console\\Commands;
            use Slendie\\Console\\Command;
            final class {$name} extends Command
            {
                protected string \$signature = 'app:ping';
                protected string \$description = 'Descoberto';
                public function handle(): int { \$this->line('pong'); return self::SUCCESS; }
            }
            PHP,
        "app/Console/Commands/Sub/{$nested}.php" => <<<PHP
            <?php
            namespace App\\Console\\Commands\\Sub;
            use Slendie\\Console\\Command;
            final class {$nested} extends Command
            {
                protected string \$signature = 'app:deep';
                public function handle(): void { \$this->line('deep'); }
            }
            abstract class Hidden {}
            PHP,
        "app/Console/Commands/{$plain}.php" => "<?php\nnamespace App\\Console\\Commands;\nfinal class {$plain} {}\n",
    ]);

    require_once $project->path("app/Console/Commands/{$name}.php");
    require_once $project->path("app/Console/Commands/Sub/{$nested}.php");
    require_once $project->path("app/Console/Commands/{$plain}.php");

    expect($project->artisan('app:ping'))->toBe([0, 'pong'."\n"])
        ->and($project->artisan('app:deep'))->toBe([0, 'deep'."\n"]);

    [, $list] = $project->artisan('list');

    expect($list)->toMatch('/app:ping\s+Descoberto/');
})->skip(fn () => class_exists('App\\Console\\Commands\\NotACommand', false), 'o fixture NotACommand já foi carregado por outro teste');

it('call() corre um comando com parâmetros e devolve o código de saída', function (): void {
    $project = withProvider();
    $project->artisan('env');

    $buffer = new BufferedOutput();
    $code = $project->console->call('greet', ['name' => 'Rui', '--shout' => true, '--times' => 2], $buffer);

    expect($code)->toBe(0)->and(ConsoleProject::lf($buffer->fetch()))->toBe("OLÁ, RUI!\nOLÁ, RUI!\n");

    // Sem Output explícito usa o da aplicação.
    $project->console->call('greet', ['Rui']);

    expect(ConsoleProject::lf($project->out->fetch()))->toBe("Olá, Rui!\n");
});

it('call() propaga erros de parâmetros e comandos inexistentes', function (): void {
    $project = withProvider();
    $project->artisan('env');

    expect(fn () => $project->console->call('greet', []))->toThrow(Slendie\Console\InvalidInputException::class, 'Argumentos em falta')
        ->and(fn () => $project->console->call('greet', ['name' => 'x', '--nope' => true]))->toThrow(Slendie\Console\InvalidInputException::class)
        ->and(fn () => $project->console->call('nope'))->toThrow(CommandNotFoundException::class);
});

it('Command::call() e callSilently() entre comandos', function (): void {
    $project = ConsoleProject::make([
        'config/app.php' => appConfig(['providers' => [CommandProvider::class]]),
        'routes/console.php' => <<<'PHP'
            <?php
            use Slendie\Support\Facades\Console;

            Console::command('outer', function () {
                $loud = $this->call('greet', ['name' => 'A', '--shout' => true]);
                $silent = $this->callSilently('greet', ['name' => 'B']);
                $this->line("loud={$loud} silent={$silent}");
            });
            PHP,
    ]);

    [$code, $output] = $project->artisan('outer');

    expect($code)->toBe(0)->and($output)->toBe("OLÁ, A!\nloud=0 silent=0\n");
});

it('um comando sem $signature falha com mensagem clara', function (): void {
    $command = new class () extends Command {
        public function handle(): void
        {
        }
    };

    expect(fn () => $command->getName())->toThrow(LogicException::class, 'tem de definir a propriedade $signature');
});

it('um comando sem handle() falha com mensagem clara', function (): void {
    $project = ConsoleProject::make();
    $command = new class () extends Command {
        protected string $signature = 'sem:handle';
    };

    $project->console->add($command);

    [$code, $output] = $project->artisan('sem:handle');

    expect($code)->toBe(1)->and($output)->toContain('tem de definir o método handle()');
});

it('add() recusa classes que não são comandos', function (): void {
    $console = ConsoleProject::make()->console;

    expect(fn () => $console->add(stdClass::class))->toThrow(InvalidArgumentException::class, 'não é um comando');
});
