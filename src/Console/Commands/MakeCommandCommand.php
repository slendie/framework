<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\GeneratorCommand;

final class MakeCommandCommand extends GeneratorCommand
{
    protected string $signature = 'make:command
        {name : Nome da classe do comando}
        {--command=app:name : Nome do comando na consola}
        {--force : Substitui o ficheiro se já existir}';

    protected string $description = 'Cria um comando de consola';

    protected string $type = 'Console command';

    protected function stub(): string
    {
        return 'command.stub';
    }

    protected function defaultNamespace(): string
    {
        return rtrim($this->rootNamespace(), '\\').'\\Console\\Commands';
    }

    protected function replacements(string $name): array
    {
        $command = $this->option('command');

        return ['command' => is_string($command) && $command !== '' ? $command : 'app:name'];
    }
}
