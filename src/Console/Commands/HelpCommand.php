<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Console\InputArgument;
use Slendie\Console\InputOption;

final class HelpCommand extends Command
{
    protected string $signature = 'help {command_name=help : O comando}';

    protected string $description = 'Mostra a ajuda de um comando';

    public function handle(): int
    {
        $name = $this->argument('command_name');
        $command = $this->console()->find(is_string($name) ? $name : 'help');
        $definition = $command->getDefinition();

        if ($command->getDescription() !== '') {
            $this->comment('Descrição:');
            $this->line('  '.$command->getDescription());
            $this->newLine();
        }

        $this->comment('Uso:');
        $this->line('  '.$definition->synopsis($command->getName()));

        foreach ($command->getAliases() as $alias) {
            $this->line('  '.$alias);
        }

        $this->newLine();

        $arguments = $definition->arguments();

        if ($arguments !== []) {
            $this->comment('Argumentos:');
            $width = max(array_map('strlen', array_keys($arguments))) + 2;

            foreach ($arguments as $argument) {
                $this->line('  <info>'.str_pad($argument->name, $width).'</info>'.$this->describeArgument($argument));
            }

            $this->newLine();
        }

        $options = $definition->options();

        if ($options !== []) {
            $this->comment('Opções:');
            $labels = array_map(fn (InputOption $option): string => $this->label($option), $options);
            $width = max(array_map('strlen', $labels)) + 2;

            foreach ($options as $name => $option) {
                $this->line('  <info>'.str_pad($labels[$name], $width).'</info>'.$this->describeOption($option));
            }

            $this->newLine();
        }

        $this->comment('Opções globais:');
        $this->line('  -h, --help, -q, -v/-vv, -n (--no-interaction), --ansi, --no-ansi');

        return self::SUCCESS;
    }

    private function label(InputOption $option): string
    {
        $label = ($option->shortcut !== null ? '-'.$option->shortcut.', ' : '    ').'--'.$option->name;

        return $option->acceptsValue ? $label.'='.strtoupper($option->name) : $label;
    }

    private function describeArgument(InputArgument $argument): string
    {
        return trim($argument->description.$this->defaultSuffix($argument->default, $argument->required));
    }

    private function describeOption(InputOption $option): string
    {
        $suffix = $option->isArray ? ' (pode repetir-se)' : $this->defaultSuffix($option->acceptsValue ? $option->default : null, false);

        return trim($option->description.$suffix);
    }

    private function defaultSuffix(mixed $default, bool $required): string
    {
        if ($required) {
            return '';
        }

        return is_string($default) && $default !== '' ? " [por omissão: {$default}]" : '';
    }
}
