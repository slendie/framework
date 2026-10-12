<?php

declare(strict_types=1);

namespace Slendie\Console;

/**
 * Entrada de um comando: `argv` (ou um array de parâmetros, para `call()`) validado contra uma `InputDefinition`.
 *
 * Aceita `--opt`, `--opt=valor`, `--opt valor` (só em opções com valor), `-O`, `-Ovalor`, atalhos agrupados (`-mcr`),
 * opções repetidas (arrays) e `--` para terminar as opções.
 */
final class Input
{
    /** @var list<string> */
    private array $tokens = [];

    /** @var array<array-key, mixed>|null */
    private ?array $parameters = null;

    /** @var array<string, mixed> */
    private array $arguments = [];

    /** @var array<string, mixed> */
    private array $options = [];

    private ?bool $interactive = null;

    /** @var resource|null */
    private $stream = null;

    private bool $explicitStream = false;

    /**
     * @param  list<string>  $tokens  argv sem o nome do script nem do comando
     */
    public static function fromTokens(array $tokens): self
    {
        $input = new self();
        $input->tokens = array_values($tokens);

        return $input;
    }

    /**
     * Parâmetros nomeados, como em `call()`: `['name' => 'Lead', '--force' => true, '--tag' => ['a', 'b'], '-m' => true]`.
     * Chaves inteiras são argumentos posicionais.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    public static function fromArray(array $parameters): self
    {
        $input = new self();
        $input->parameters = $parameters;

        return $input;
    }

    // -------------------------------------------------------------------------
    // Interacção
    // -------------------------------------------------------------------------

    public function setInteractive(?bool $interactive): static
    {
        $this->interactive = $interactive;

        return $this;
    }

    /** Interactivo se não foi desligado e a entrada é um terminal (ou um stream indicado explicitamente). */
    public function isInteractive(): bool
    {
        if ($this->interactive !== null) {
            return $this->interactive;
        }

        if ($this->explicitStream) {
            return true;
        }

        $stream = $this->stream();

        return $stream !== null && stream_isatty($stream);
    }

    /**
     * @param  resource|null  $stream
     */
    public function setStream($stream): static
    {
        $this->stream = $stream;
        $this->explicitStream = $stream !== null;

        return $this;
    }

    /** @return resource|null */
    public function stream()
    {
        if ($this->stream === null) {
            $this->stream = defined('STDIN') ? STDIN : (@fopen('php://stdin', 'r') ?: null);
        }

        return $this->stream;
    }

    /** Lê uma linha (sem o fim de linha); null no fim da entrada. */
    public function readLine(): ?string
    {
        $stream = $this->stream();

        if ($stream === null) {
            return null;
        }

        $line = fgets($stream);

        return $line === false ? null : rtrim($line, "\r\n");
    }

    // -------------------------------------------------------------------------
    // Ligação à definição
    // -------------------------------------------------------------------------

    /**
     * @param  string  $commandName  usado na mensagem de uso dos erros
     *
     * @throws InvalidInputException
     */
    public function bind(InputDefinition $definition, string $commandName = ''): static
    {
        $this->arguments = [];
        $this->options = [];

        try {
            $positional = $this->parameters !== null
                ? $this->bindParameters($definition, $this->parameters)
                : $this->parseTokens($definition);

            $this->bindArguments($definition, $positional);
        } catch (InvalidInputException $e) {
            $usage = $definition->synopsis($commandName === '' ? 'command' : $commandName);

            throw new InvalidInputException($e->getMessage().PHP_EOL.PHP_EOL.'Uso:'.PHP_EOL.'  '.$usage, 0, $e);
        }

        foreach ($definition->options() as $name => $option) {
            if (! array_key_exists($name, $this->options)) {
                $this->options[$name] = $option->isArray ? [] : $option->default;
            }
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function argument(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }

    public function hasArgument(string $name): bool
    {
        return array_key_exists($name, $this->arguments);
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        return $this->options;
    }

    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    // -------------------------------------------------------------------------
    // Parsing
    // -------------------------------------------------------------------------

    /**
     * @return list<string> argumentos posicionais
     */
    private function parseTokens(InputDefinition $definition): array
    {
        $positional = [];
        $tokens = $this->tokens;
        $count = count($tokens);
        $onlyPositional = false;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($onlyPositional || $token === '-' || $token === '' || $token[0] !== '-') {
                $positional[] = $token;

                continue;
            }

            if ($token === '--') {
                $onlyPositional = true;

                continue;
            }

            if (str_starts_with($token, '--')) {
                $body = substr($token, 2);
                $eq = strpos($body, '=');
                $name = $eq === false ? $body : substr($body, 0, $eq);
                $option = $definition->option($name) ?? throw new InvalidInputException("A opção \"--{$name}\" não existe.");

                $this->setOption($option, $eq === false ? null : substr($body, $eq + 1), $tokens, $i);

                continue;
            }

            if (preg_match('/^-\d/', $token) === 1 && $definition->optionByShortcut($token[1]) === null) {
                $positional[] = $token;

                continue;
            }

            $this->parseShortcuts($definition, $token, $tokens, $i);
        }

        return $positional;
    }

    /**
     * `-m`, `-mcr` (booleanas agrupadas), `-Ovalor`, `-O=valor` ou `-O valor`.
     *
     * @param  list<string>  $tokens
     */
    private function parseShortcuts(InputDefinition $definition, string $token, array $tokens, int &$i): void
    {
        $length = strlen($token);

        for ($j = 1; $j < $length; $j++) {
            $letter = $token[$j];
            $option = $definition->optionByShortcut($letter) ?? throw new InvalidInputException("A opção \"-{$letter}\" não existe.");

            if (! $option->acceptsValue) {
                $this->setOption($option, null, $tokens, $i);

                continue;
            }

            $value = substr($token, $j + 1);
            $value = str_starts_with($value, '=') ? substr($value, 1) : $value;

            $this->setOption($option, $value === '' ? null : $value, $tokens, $i);

            return;
        }
    }

    /**
     * @param  list<string>  $tokens
     */
    private function setOption(InputOption $option, ?string $value, array $tokens, int &$i): void
    {
        if (! $option->acceptsValue) {
            if ($value !== null) {
                throw new InvalidInputException("A opção \"--{$option->name}\" não aceita valores.");
            }

            $this->options[$option->name] = true;

            return;
        }

        if ($value === null) {
            $next = $tokens[$i + 1] ?? null;

            if ($next !== null && ($next === '-' || $next === '' || $next[0] !== '-')) {
                $value = $next;
                $i++;
            } elseif ($option->default !== null && ! $option->isArray) {
                $this->options[$option->name] = $option->default;

                return;
            } else {
                throw new InvalidInputException("A opção \"--{$option->name}\" exige um valor.");
            }
        }

        if ($option->isArray) {
            $current = $this->options[$option->name] ?? [];
            $this->options[$option->name] = [...(is_array($current) ? $current : []), $value];
        } else {
            $this->options[$option->name] = $value;
        }
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     * @return list<string>
     */
    private function bindParameters(InputDefinition $definition, array $parameters): array
    {
        $positional = [];

        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                $positional[] = is_scalar($value) ? (string) $value : '';

                continue;
            }

            if (str_starts_with($key, '-')) {
                $option = str_starts_with($key, '--')
                    ? $definition->option(substr($key, 2))
                    : $definition->optionByShortcut(substr($key, 1));

                $this->assignParameterOption($option ?? throw new InvalidInputException("A opção \"{$key}\" não existe."), $value);

                continue;
            }

            $argument = $definition->argument($key) ?? throw new InvalidInputException("O argumento \"{$key}\" não existe.");
            $this->arguments[$key] = $argument->isArray ? array_values((array) $value) : $value;
        }

        return $positional;
    }

    private function assignParameterOption(InputOption $option, mixed $value): void
    {
        if (! $option->acceptsValue) {
            if ($value !== false && $value !== null) {
                $this->options[$option->name] = true;
            }

            return;
        }

        if ($option->isArray) {
            $this->options[$option->name] = array_values((array) $value);

            return;
        }

        if (is_array($value)) {
            throw new InvalidInputException("A opção \"--{$option->name}\" não aceita vários valores.");
        }

        $this->options[$option->name] = is_bool($value) ? ($value ? '1' : null) : $value;
    }

    /**
     * @param  list<string>  $positional
     */
    private function bindArguments(InputDefinition $definition, array $positional): void
    {
        $arguments = $definition->arguments();

        if ($positional !== [] && $arguments === []) {
            throw new InvalidInputException('Demasiados argumentos: este comando não aceita argumentos.');
        }

        $missing = [];

        foreach ($arguments as $name => $argument) {
            if (array_key_exists($name, $this->arguments)) {
                continue; // já indicado por nome (call())
            }

            if ($argument->isArray) {
                $this->arguments[$name] = $positional;
                $positional = [];

                if ($argument->required && $this->arguments[$name] === []) {
                    $missing[] = $name;
                }

                continue;
            }

            if ($positional !== []) {
                $this->arguments[$name] = array_shift($positional);
            } elseif ($argument->required) {
                $missing[] = $name;
            } else {
                $this->arguments[$name] = $argument->default;
            }
        }

        if ($missing !== []) {
            throw new InvalidInputException('Argumentos em falta: "'.implode('", "', $missing).'".');
        }

        if ($positional !== []) {
            throw new InvalidInputException('Demasiados argumentos; esperados: "'.implode('", "', array_keys($arguments)).'".');
        }
    }
}
