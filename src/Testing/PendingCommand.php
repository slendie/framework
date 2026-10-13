<?php

declare(strict_types=1);

namespace Slendie\Testing;

use PHPUnit\Framework\Assert;
use Slendie\Console\Application as ConsoleApplication;
use Slendie\Console\BufferedOutput;
use Throwable;

/**
 * Um comando de consola à espera de ser executado: acumulam-se as expectativas (saída, perguntas) e
 * corre-se no primeiro `assert*()` / `run()` — ou, no mínimo, quando o objecto é destruído.
 *
 *     $this->console('migrate', ['--force' => true])
 *         ->expectsOutputToContain('Nothing to migrate')
 *         ->assertSuccessful();
 */
class PendingCommand
{
    /** @var list<string> */
    protected array $answers = [];

    /** @var list<string> perguntas que têm de aparecer na saída, pela ordem em que foram registadas */
    protected array $questions = [];

    /** @var list<string> */
    protected array $expectedLines = [];

    /** @var list<string> */
    protected array $unexpectedLines = [];

    /** @var list<string> */
    protected array $expectedFragments = [];

    /** @var list<string> */
    protected array $unexpectedFragments = [];

    /** @var list<array{0: list<string>, 1: list<array<array-key, mixed>>}> */
    protected array $expectedTables = [];

    protected bool $hasRun = false;

    protected int $exitCode = 0;

    protected string $output = '';

    protected ?Throwable $exception = null;

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function __construct(
        protected ConsoleApplication $console,
        protected string $command,
        protected array $parameters = [],
    ) {
    }

    // -------------------------------------------------------------------------
    // Expectativas (antes de correr)
    // -------------------------------------------------------------------------

    /** Uma linha da saída (sem espaços nas pontas) tem de ser exactamente esta. */
    public function expectsOutput(string $output): static
    {
        $this->expectedLines[] = $output;

        return $this;
    }

    public function doesntExpectOutput(string $output): static
    {
        $this->unexpectedLines[] = $output;

        return $this;
    }

    public function expectsOutputToContain(string $fragment): static
    {
        $this->expectedFragments[] = $fragment;

        return $this;
    }

    public function doesntExpectOutputToContain(string $fragment): static
    {
        $this->unexpectedFragments[] = $fragment;

        return $this;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<array-key, mixed>>  $rows
     */
    public function expectsTable(array $headers, array $rows): static
    {
        $this->expectedTables[] = [$headers, $rows];

        return $this;
    }

    /** Responde à próxima pergunta (`ask`, `secret`...); a pergunta tem de aparecer na saída. */
    public function expectsQuestion(string $question, string $answer): static
    {
        $this->questions[] = $question;
        $this->answers[] = $answer;

        return $this;
    }

    public function expectsConfirmation(string $question, string $answer = 'no'): static
    {
        return $this->expectsQuestion($question, $answer);
    }

    /**
     * @param  array<array-key, string>  $outputChoices  as opções que a pergunta deve listar (opcional)
     */
    public function expectsChoice(string $question, string $answer, array $outputChoices = []): static
    {
        $this->expectsQuestion($question, $answer);

        foreach ($outputChoices as $choice) {
            $this->expectedFragments[] = $choice;
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Execução
    // -------------------------------------------------------------------------

    /** Corre o comando (uma só vez), verifica as expectativas e devolve o código de saída. */
    public function run(): int
    {
        if ($this->hasRun) {
            return $this->exitCode;
        }

        $this->hasRun = true;

        $output = new BufferedOutput();
        $stream = null;

        if ($this->answers !== []) {
            $stream = fopen('php://memory', 'w+');

            if ($stream !== false) {
                fwrite($stream, implode("\n", $this->answers)."\n");
                rewind($stream);
            }
        }

        $this->console->setInputStream($stream ?: null)->setInteractive($this->answers !== []);

        try {
            $this->exitCode = $this->console->call($this->command, $this->parameters, $output);
        } catch (Throwable $e) {
            $this->exception = $e;
            $this->exitCode = 1;
        } finally {
            $this->console->setInputStream(null)->setInteractive(false);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->output = str_replace("\r\n", "\n", $output->fetch());

        if ($this->exception !== null) {
            throw $this->exception;
        }

        $this->verifyExpectations();

        return $this->exitCode;
    }

    public function output(): string
    {
        $this->run();

        return $this->output;
    }

    public function assertExitCode(int $code): static
    {
        Assert::assertSame($code, $this->run(), $this->failure("Expected status code [{$code}] but received [{$this->exitCode}]."));

        return $this;
    }

    public function assertNotExitCode(int $code): static
    {
        Assert::assertNotSame($code, $this->run(), $this->failure("Unexpected status code [{$code}]."));

        return $this;
    }

    public function assertSuccessful(): static
    {
        return $this->assertExitCode(0);
    }

    public function assertOk(): static
    {
        return $this->assertExitCode(0);
    }

    public function assertFailed(): static
    {
        return $this->assertNotExitCode(0);
    }

    public function __destruct()
    {
        if (! $this->hasRun) {
            $this->run();
        }
    }

    // -------------------------------------------------------------------------

    protected function verifyExpectations(): void
    {
        $lines = array_map('trim', explode("\n", $this->output));

        foreach ($this->questions as $question) {
            Assert::assertStringContainsString($question, $this->output, $this->failure("Expected the question [{$question}] to be asked."));
        }

        foreach ($this->expectedLines as $line) {
            Assert::assertContains(trim($line), $lines, $this->failure("Expected the output line [{$line}]."));
        }

        foreach ($this->unexpectedLines as $line) {
            Assert::assertNotContains(trim($line), $lines, $this->failure("Unexpected output line [{$line}]."));
        }

        foreach ($this->expectedFragments as $fragment) {
            Assert::assertStringContainsString($fragment, $this->output, $this->failure("Expected the output to contain [{$fragment}]."));
        }

        foreach ($this->unexpectedFragments as $fragment) {
            Assert::assertStringNotContainsString($fragment, $this->output, $this->failure("Expected the output not to contain [{$fragment}]."));
        }

        foreach ($this->expectedTables as [$headers, $rows]) {
            $table = new BufferedOutput();
            $table->table($headers, $rows);

            Assert::assertStringContainsString(
                rtrim(str_replace("\r\n", "\n", $table->fetch())),
                $this->output,
                $this->failure('Expected the output to contain the given table.'),
            );
        }
    }

    protected function failure(string $message): string
    {
        return $message."\n\nOutput of [{$this->command}]:\n".($this->output === '' ? '(empty)' : $this->output);
    }
}
