<?php

declare(strict_types=1);

namespace Slendie\Console;

use LogicException;

/** Argumentos e opções aceites por um comando. */
final class InputDefinition
{
    /** @var array<string, InputArgument> */
    private array $arguments = [];

    /** @var array<string, InputOption> */
    private array $options = [];

    /** @var array<string, string> atalho => nome */
    private array $shortcuts = [];

    public function addArgument(InputArgument $argument): static
    {
        if (isset($this->arguments[$argument->name])) {
            throw new LogicException("O argumento [{$argument->name}] já foi definido.");
        }

        $last = $this->arguments === [] ? null : end($this->arguments);

        if ($last !== null && $last->isArray) {
            throw new LogicException('Nenhum argumento pode vir depois de um argumento array.');
        }

        if ($argument->required && $last !== null && ! $last->required) {
            throw new LogicException("O argumento obrigatório [{$argument->name}] não pode vir depois de um opcional.");
        }

        $this->arguments[$argument->name] = $argument;

        return $this;
    }

    public function addOption(InputOption $option): static
    {
        if (isset($this->options[$option->name])) {
            throw new LogicException("A opção [--{$option->name}] já foi definida.");
        }

        if ($option->shortcut !== null) {
            if (isset($this->shortcuts[$option->shortcut])) {
                throw new LogicException("O atalho [-{$option->shortcut}] já está em uso por [--{$this->shortcuts[$option->shortcut]}].");
            }

            $this->shortcuts[$option->shortcut] = $option->name;
        }

        $this->options[$option->name] = $option;

        return $this;
    }

    /** @return array<string, InputArgument> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /** @return array<string, InputOption> */
    public function options(): array
    {
        return $this->options;
    }

    public function argument(string $name): ?InputArgument
    {
        return $this->arguments[$name] ?? null;
    }

    public function option(string $name): ?InputOption
    {
        return $this->options[$name] ?? null;
    }

    public function optionByShortcut(string $shortcut): ?InputOption
    {
        return isset($this->shortcuts[$shortcut]) ? $this->options[$this->shortcuts[$shortcut]] : null;
    }

    /** Linha de uso: `migrate [options] [--] [<name>]`. */
    public function synopsis(string $commandName): string
    {
        $parts = [$commandName];

        if ($this->options !== []) {
            $parts[] = '[options]';
        }

        if ($this->arguments !== []) {
            $parts[] = '[--]';
        }

        foreach ($this->arguments as $argument) {
            $label = '<'.$argument->name.'>'.($argument->isArray ? '...' : '');
            $parts[] = $argument->required ? $label : '['.$label.']';
        }

        return implode(' ', $parts);
    }
}
