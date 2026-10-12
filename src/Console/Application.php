<?php

declare(strict_types=1);

namespace Slendie\Console;

use Closure;
use InvalidArgumentException;
use Slendie\Console\Commands\HelpCommand;
use Slendie\Console\Commands\ListCommand;
use Slendie\Foundation\Application as FoundationApplication;
use Throwable;

/**
 * Aplicação de consola (`php slendie`): regista comandos, interpreta `argv` e executa.
 */
class Application
{
    /** @var array<string, Command> nome => comando */
    protected array $commands = [];

    /** @var array<string, string> alias => nome */
    protected array $aliases = [];

    protected ?Output $output = null;

    /** @var resource|null */
    protected $inputStream = null;

    /** false com `--no-interaction`; null = automático (terminal) */
    protected ?bool $interactive = null;

    /** O que $interactive volta a ser em cada `run()` sem `--no-interaction`. */
    protected ?bool $defaultInteractive = null;

    public function __construct(protected FoundationApplication $app)
    {
        $this->add(new ListCommand());
        $this->add(new HelpCommand());
    }

    // -------------------------------------------------------------------------
    // Registo
    // -------------------------------------------------------------------------

    /**
     * @param  Command|class-string<Command>  $command
     */
    public function add(Command|string $command): static
    {
        if (is_string($command)) {
            $resolved = $this->app->make($command);

            if (! $resolved instanceof Command) {
                throw new InvalidArgumentException("[{$command}] não é um comando (tem de estender ".Command::class.').');
            }

            $command = $resolved;
        }

        $command->setApp($this->app)->setConsole($this);

        $name = $command->getName();
        $this->commands[$name] = $command;

        foreach ($command->getAliases() as $alias) {
            $this->aliases[$alias] = $name;
        }

        return $this;
    }

    /**
     * @param  list<Command|class-string<Command>>  $commands
     */
    public function resolveCommands(array $commands): static
    {
        foreach ($commands as $command) {
            $this->add($command);
        }

        return $this;
    }

    /**
     * Comando por closure: `$console->command('inspire', fn () => $this->info('...'))->purpose('Uma frase')`.
     */
    public function command(string $signature, Closure $callback): ClosureCommand
    {
        $command = new ClosureCommand($signature, $callback);
        $this->add($command);

        return $command;
    }

    /** @return array<string, Command> */
    public function all(): array
    {
        return $this->commands;
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]) || isset($this->aliases[$name]);
    }

    /**
     * Procura um comando pelo nome exacto (ou alias).
     *
     * @throws CommandNotFoundException com sugestões quando não existe
     */
    public function find(string $name): Command
    {
        $name = $this->aliases[$name] ?? $name;

        if (isset($this->commands[$name])) {
            return $this->commands[$name];
        }

        $alternatives = $this->alternatives($name);
        $message = "O comando \"{$name}\" não existe.";

        if ($alternatives !== []) {
            $message .= PHP_EOL.PHP_EOL.'Quis dizer '.(count($alternatives) === 1 ? 'este' : 'um destes').'?'.PHP_EOL
                .implode(PHP_EOL, array_map(static fn (string $alternative): string => "    {$alternative}", $alternatives));
        }

        throw new CommandNotFoundException($message, $alternatives);
    }

    /**
     * Nomes parecidos: prefixo (`migrate:st`) ou poucas edições (`migrate:stats`).
     *
     * @return list<string>
     */
    protected function alternatives(string $name): array
    {
        $found = [];
        $limit = max(2, intdiv(strlen($name), 3));

        foreach (array_keys($this->commands) as $candidate) {
            if ($name !== '' && str_starts_with($candidate, $name)) {
                $found[$candidate] = 0;

                continue;
            }

            $distance = levenshtein($name, $candidate);

            if ($distance <= $limit) {
                $found[$candidate] = $distance;
            }
        }

        asort($found);

        return array_slice(array_map('strval', array_keys($found)), 0, 5);
    }

    // -------------------------------------------------------------------------
    // Execução
    // -------------------------------------------------------------------------

    public function output(): Output
    {
        return $this->output ??= new Output();
    }

    public function setOutput(Output $output): static
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Entrada alternativa a STDIN (testes): torna os comandos interactivos.
     *
     * @param  resource|null  $stream
     */
    public function setInputStream($stream): static
    {
        $this->inputStream = $stream;

        return $this;
    }

    /**
     * Força (true/false) ou deixa automático (null: só com terminal) o modo interactivo dos comandos.
     */
    public function setInteractive(?bool $interactive): static
    {
        $this->interactive = $this->defaultInteractive = $interactive;

        return $this;
    }

    public function version(): string
    {
        return $this->app->version();
    }

    /**
     * Interpreta `argv` (incluindo o nome do script), arranca a aplicação, corre o comando e devolve o código de saída.
     *
     * @param  list<string>|null  $argv
     */
    public function run(?array $argv = null): int
    {
        if ($argv === null) {
            $server = $_SERVER['argv'] ?? [];
            $argv = is_array($server) ? array_values(array_map('strval', $server)) : [];
        }

        array_shift($argv); // nome do script

        $flags = $this->extractGlobalOptions($argv);
        $output = $this->output();

        if ($flags['ansi'] !== null) {
            $output->setDecorated($flags['ansi']);
        }

        $output->setVerbosity($flags['verbosity']);
        $this->interactive = $flags['interaction'] ? $this->defaultInteractive : false;

        try {
            $this->app->bootstrap();

            if ($flags['version']) {
                $output->writeln('Slendie Framework <info>'.$this->version().'</info>');

                return Command::SUCCESS;
            }

            $tokens = $flags['tokens'];
            $name = null;

            foreach ($tokens as $index => $token) {
                if ($token !== '' && $token[0] !== '-') {
                    $name = $token;
                    unset($tokens[$index]);

                    break;
                }
            }

            $tokens = array_values($tokens);

            if ($name === null) {
                return $this->call('list', [], $output);
            }

            $command = $this->find($name);

            if ($flags['help']) {
                return $this->call('help', ['command_name' => $command->getName()], $output);
            }

            return $this->runCommand($command, Input::fromTokens($tokens), $output);
        } catch (CommandNotFoundException $e) {
            $output->errorln('  <badge-error> ERROR </badge-error> '.$this->indent($e->getMessage()));

            return Command::FAILURE;
        } catch (InvalidInputException $e) {
            $output->errorln('  <badge-error> ERROR </badge-error> '.$this->indent($e->getMessage()));

            return Command::INVALID;
        } catch (Throwable $e) {
            $this->renderException($e, $output);

            return Command::FAILURE;
        } finally {
            try {
                $this->app->terminate();
            } catch (Throwable) {
                // O término nunca pode esconder o resultado do comando.
            }
        }
    }

    /**
     * Uso programático (e `Command::call()`): corre um comando com parâmetros nomeados.
     *
     *     $console->call('make:model', ['name' => 'Lead', '--migration' => true]);
     *
     * @param  array<array-key, mixed>  $params
     */
    public function call(string $command, array $params = [], ?Output $out = null): int
    {
        $input = Input::fromArray($params);

        return $this->runCommand($this->find($command), $input, $out ?? $this->output());
    }

    protected function runCommand(Command $command, Input $input, Output $output): int
    {
        $input->setInteractive($this->interactive)->setStream($this->inputStream);

        return $command->setApp($this->app)->setConsole($this)->run($input, $output);
    }

    /**
     * Separa as opções globais (`-q -v -vv -n --ansi --no-ansi -h -V`) do resto.
     *
     * @param  list<string>  $argv
     * @return array{tokens: list<string>, verbosity: int, ansi: ?bool, interaction: bool, help: bool, version: bool}
     */
    protected function extractGlobalOptions(array $argv): array
    {
        $result = ['tokens' => [], 'verbosity' => Output::VERBOSITY_NORMAL, 'ansi' => null, 'interaction' => true, 'help' => false, 'version' => false];
        $verbose = null;

        foreach ($argv as $index => $token) {
            if ($token === '--') {
                $result['tokens'] = [...$result['tokens'], ...array_slice($argv, $index)];

                break;
            }

            match ($token) {
                '-q', '--quiet' => $result['verbosity'] = Output::VERBOSITY_QUIET,
                '-v', '--verbose' => $verbose = Output::VERBOSITY_VERBOSE,
                '-vv', '-vvv' => $verbose = Output::VERBOSITY_VERY_VERBOSE,
                '--ansi' => $result['ansi'] = true,
                '--no-ansi' => $result['ansi'] = false,
                '-n', '--no-interaction' => $result['interaction'] = false,
                '-h', '--help' => $result['help'] = true,
                '-V', '--version' => $result['version'] = true,
                default => $result['tokens'][] = $token,
            };
        }

        if ($verbose !== null && $result['verbosity'] !== Output::VERBOSITY_QUIET) {
            $result['verbosity'] = $verbose;
        }

        return $result;
    }

    /** Indenta as linhas não vazias da mensagem de erro. */
    protected function indent(string $message): string
    {
        return (string) preg_replace('/^(?=.)/m', '  ', $message);
    }

    protected function renderException(Throwable $e, Output $output): void
    {
        $output->errorln('');
        $output->errorln('  <badge-error> ERROR </badge-error> '.$e->getMessage());
        $output->errorln('');

        if ($this->app->isDebug() || $output->isVerbose()) {
            $output->errorln(sprintf('  %s em %s:%d', $e::class, $e->getFile(), $e->getLine()));
            $output->errorln('');
            $output->errorln($e->getTraceAsString());
        } else {
            $output->errorln('  Usa <comment>-v</comment> para ver mais detalhes.');
        }
    }
}
