<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\GeneratorCommand;

final class MakeRequestCommand extends GeneratorCommand
{
    protected string $signature = 'make:request {name : Nome do form request (ex.: StoreLeadRequest)} {--force : Substitui o ficheiro se já existir}';

    protected string $description = 'Cria um form request (validação e autorização)';

    protected string $type = 'Request';

    protected function stub(): string
    {
        return 'request.stub';
    }

    protected function defaultNamespace(): string
    {
        return rtrim($this->rootNamespace(), '\\').'\\Http\\Requests';
    }
}
