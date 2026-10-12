<?php

declare(strict_types=1);

namespace Slendie\Console;

use Closure;
use InvalidArgumentException;
use LogicException;
use Slendie\Container\Container;
use Slendie\Foundation\Application as FoundationApplication;
use Throwable;

/**
 * Base dos comandos da consola. Define `$signature`/`$description` e implementa `handle()`,
 * cujos parâmetros tipados são injectados pelo container:
 *
 *     protected string $signature = 'report:send {email : Destinatário} {--days=7}';
 *
 *     public function handle(LeadRepository $leads): int { ... return self::SUCCESS; }
 */
abstract class Command
{
    public const int SUCCESS = 0;

    public const int FAILURE = 1;

    public const int INVALID = 2;

    protected string $signature = '';

    protected string $description = '';

    protected bool $hidden = false;

    /** @var list<string> */
    protected array $aliases = [];

    protected ?Container $app = null;

    protected ?Application $console = null;

    protected ?Input $input = null;

    protected ?Output $output = null;

    private ?string $parsedName = null;

    private ?InputDefinition $parsedDefinition = null;

    // -------------------------------------------------------------------------
    // Metadados
    // -------------------------------------------------------------------------

    public function getName(): string
    {
        $this->parseSignature();

        return (string) $this->parsedName;
    }

    public function getDefinition(): InputDefinition
    {
        $this->parseSignature();

        return $this->parsedDefinition ?? new InputDefinition();
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /** @return list<string> */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function setApp(Container $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function setConsole(Application $console): static
    {
        $this->console = $console;

        return $this;
    }

    private function parseSignature(): void
    {
        if ($this->parsedName !== null) {
            return;
        }

        if (trim($this->signature) === '') {
            throw new LogicException('O comando ['.static::class.'] tem de definir a propriedade $signature.');
        }

        [$this->parsedName, $this->parsedDefinition] = SignatureParser::parse($this->signature);
    }

    // -------------------------------------------------------------------------
    // Execução
    // -------------------------------------------------------------------------

    /**
     * Liga a entrada e a saída e executa o comando; devolve o código de saída.
     *
     * @throws InvalidInputException
     */
    public function run(Input $input, Output $output): int
    {
        $this->input = $input->bind($this->getDefinition(), $this->getName());
        $this->output = $output;

        $result = $this->execute();

        return is_int($result) ? $result : self::SUCCESS;
    }

    /** Chama `handle()` com injecção de dependências. */
    protected function execute(): mixed
    {
        if (! method_exists($this, 'handle')) {
            throw new LogicException('O comando ['.static::class.'] tem de definir o método handle().');
        }

        return $this->container()->call([$this, 'handle']);
    }

    protected function container(): Container
    {
        return $this->app ?? Container::getInstance();
    }

    /**
     * Executa outro comando, partilhando a saída deste.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    public function call(string $command, array $parameters = []): int
    {
        return $this->console()->call($command, $parameters, $this->output);
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function callSilently(string $command, array $parameters = []): int
    {
        return $this->console()->call($command, $parameters, new BufferedOutput());
    }

    protected function console(): Application
    {
        return $this->console ?? throw new LogicException('O comando ainda não foi registado numa aplicação de consola.');
    }

    // -------------------------------------------------------------------------
    // Entrada
    // -------------------------------------------------------------------------

    protected function argument(string $name): mixed
    {
        if (! $this->getDefinition()->argument($name) instanceof InputArgument) {
            throw new InvalidArgumentException("O argumento [{$name}] não está definido na assinatura de [{$this->getName()}].");
        }

        return $this->input()->argument($name);
    }

    /** @return array<string, mixed> */
    protected function arguments(): array
    {
        return $this->input()->arguments();
    }

    protected function hasArgument(string $name): bool
    {
        return $this->input()->hasArgument($name);
    }

    protected function option(string $name): mixed
    {
        if (! $this->getDefinition()->option($name) instanceof InputOption) {
            throw new InvalidArgumentException("A opção [--{$name}] não está definida na assinatura de [{$this->getName()}].");
        }

        return $this->input()->option($name);
    }

    /** @return array<string, mixed> */
    protected function options(): array
    {
        return $this->input()->options();
    }

    protected function hasOption(string $name): bool
    {
        return $this->input()->hasOption($name);
    }

    protected function input(): Input
    {
        return $this->input ?? throw new LogicException('O comando ainda não foi executado.');
    }

    protected function output(): Output
    {
        return $this->output ?? throw new LogicException('O comando ainda não foi executado.');
    }

    // -------------------------------------------------------------------------
    // Saída
    // -------------------------------------------------------------------------

    protected function line(string $text, ?string $style = null, int $verbosity = Output::VERBOSITY_NORMAL): void
    {
        $this->output()->writeln($style === null ? $text : "<{$style}>{$text}</{$style}>", $verbosity);
    }

    protected function info(string $text): void
    {
        $this->line($text, 'info');
    }

    protected function comment(string $text): void
    {
        $this->line($text, 'comment');
    }

    protected function question(string $text): void
    {
        $this->line($text, 'question');
    }

    /** Branco sobre vermelho, em STDERR. */
    protected function error(string $text): void
    {
        $this->output()->errorln("<error>{$text}</error>");
    }

    protected function warn(string $text): void
    {
        $this->line($text, 'warn');
    }

    /** `  INFO  mensagem` (etiqueta a cores). */
    protected function badge(string $label, string $message, string $style = 'badge-info'): void
    {
        $this->output()->writeln("  <{$style}> {$label} </{$style}> {$message}");
    }

    protected function newLine(int $count = 1): void
    {
        $this->output()->write(str_repeat(PHP_EOL, max(1, $count)));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<array-key, mixed>>  $rows
     */
    protected function table(array $headers, array $rows): void
    {
        $this->output()->table($headers, $rows);
    }

    /**
     * Executa `$callback` mostrando `etiqueta ........ DONE (12ms)` ou `FAIL`.
     * Devolver `false` (ou lançar) marca como falhado; as excepções continuam a propagar-se.
     */
    protected function task(string $label, Closure $callback): bool
    {
        $start = microtime(true);
        $output = $this->output();

        $finish = function (bool $ok) use ($label, $start, $output): void {
            $status = $ok ? '<info>DONE</info>' : '<error>FAIL</error>';
            $elapsed = number_format((microtime(true) - $start) * 1000, 0, '.', '').'ms';
            $used = $output->width($label) + $output->width($status) + strlen($elapsed) + 6;
            $dots = str_repeat('.', max(3, $output->terminalWidth() - $used));

            $output->writeln("  {$label} <muted>{$dots}</muted> {$status} ({$elapsed})");
        };

        try {
            $result = $callback();
        } catch (Throwable $e) {
            $finish(false);

            throw $e;
        }

        $finish($result !== false);

        return $result !== false;
    }

    protected function isVerbose(): bool
    {
        return $this->output()->isVerbose();
    }

    protected function isVeryVerbose(): bool
    {
        return $this->output()->isVeryVerbose();
    }

    // -------------------------------------------------------------------------
    // Interacção (sem terminal ou com --no-interaction devolvem o valor por omissão)
    // -------------------------------------------------------------------------

    protected function ask(string $question, ?string $default = null): ?string
    {
        if (! $this->input()->isInteractive()) {
            return $default;
        }

        $this->output()->write("<question>{$question}</question>".($default !== null ? " [{$default}]" : '').': ');

        $answer = $this->input()->readLine();

        return $answer === null || $answer === '' ? $default : $answer;
    }

    /** Pede um valor sem ecoar (precisa de `stty`; sem ele avisa que a escrita ficará visível). */
    protected function secret(string $question): ?string
    {
        if (! $this->input()->isInteractive()) {
            return null;
        }

        $this->output()->write("<question>{$question}</question>: ");

        $stty = $this->sttyAvailable() && $this->input()->stream() !== null && stream_isatty($this->input()->stream());

        if ($stty) {
            shell_exec('stty -echo');
        } else {
            $this->output()->errorln(PHP_EOL.'<warn>Aviso: não foi possível ocultar a escrita neste terminal.</warn>');
        }

        try {
            $answer = $this->input()->readLine();
        } finally {
            if ($stty) {
                shell_exec('stty echo');
                $this->output()->writeln();
            }
        }

        return $answer === '' ? null : $answer;
    }

    protected function confirm(string $question, bool $default = false): bool
    {
        if (! $this->input()->isInteractive()) {
            return $default;
        }

        $hint = $default ? 'yes' : 'no';

        while (true) {
            $this->output()->write("<question>{$question}</question> (yes/no) [{$hint}]: ");

            $answer = $this->input()->readLine();

            if ($answer === null || trim($answer) === '') {
                return $default;
            }

            $answer = strtolower(trim($answer));

            if (in_array($answer, ['y', 'yes', 's', 'sim'], true)) {
                return true;
            }

            if (in_array($answer, ['n', 'no', 'nao', 'não'], true)) {
                return false;
            }
        }
    }

    /**
     * Escolha numa lista (por chave ou por valor). Devolve o valor escolhido.
     *
     * @param  array<array-key, string>  $choices
     */
    protected function choice(string $question, array $choices, string|int|null $default = null): string
    {
        $fallback = static function () use ($choices, $default): string {
            if ($default !== null && isset($choices[$default])) {
                return $choices[$default];
            }

            return $default !== null && in_array((string) $default, $choices, true) ? (string) $default : (string) (reset($choices) ?: '');
        };

        if (! $this->input()->isInteractive() || $choices === []) {
            return $fallback();
        }

        $this->output()->writeln("<question>{$question}</question>");

        foreach ($choices as $key => $label) {
            $this->output()->writeln("  [<comment>{$key}</comment>] {$label}");
        }

        while (true) {
            $this->output()->write('> ');

            $answer = $this->input()->readLine();

            if ($answer === null || trim($answer) === '') {
                return $fallback();
            }

            $answer = trim($answer);

            if (isset($choices[$answer])) {
                return $choices[$answer];
            }

            if (in_array($answer, $choices, true)) {
                return $answer;
            }

            $this->output()->errorln("<error>Opção inválida [{$answer}].</error>");
        }
    }

    /**
     * Em produção pede confirmação (ou `--force`) antes de comandos destrutivos.
     */
    protected function confirmToProceed(string $warning = 'Application In Production'): bool
    {
        $app = $this->container();

        if (! $app instanceof FoundationApplication || ! $app->isProduction()) {
            return true;
        }

        if ($this->getDefinition()->option('force') instanceof InputOption && $this->input()->option('force') === true) {
            return true;
        }

        $this->output()->writeln("  <badge-warn> WARN </badge-warn> {$warning}");

        if ($this->confirm('Do you really wish to run this command?', false)) {
            return true;
        }

        $this->comment('Command cancelled.');

        return false;
    }

    private function sttyAvailable(): bool
    {
        static $available = null;

        if ($available === null) {
            $probe = function_exists('shell_exec') ? @shell_exec('stty -g 2>&1') : null;
            $available = is_string($probe) && preg_match('/^[0-9a-f:]+\s*$/i', $probe) === 1;
        }

        return $available;
    }
}
