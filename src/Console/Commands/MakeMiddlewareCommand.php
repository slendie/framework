<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\GeneratorCommand;

final class MakeMiddlewareCommand extends GeneratorCommand
{
    protected string $signature = 'make:middleware {name : Nome do middleware} {--force : Substitui o ficheiro se já existir}';

    protected string $description = 'Cria um middleware';

    protected string $type = 'Middleware';

    protected function stub(): string
    {
        return 'middleware.stub';
    }

    protected function defaultNamespace(): string
    {
        return rtrim($this->rootNamespace(), '\\').'\\Http\\Middleware';
    }
}
