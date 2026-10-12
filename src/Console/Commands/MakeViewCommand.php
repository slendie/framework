<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use InvalidArgumentException;
use Slendie\Console\GeneratorCommand;

final class MakeViewCommand extends GeneratorCommand
{
    protected string $signature = 'make:view {name : Nome da view (ex.: contacto ou admin.leads.index)} {--force : Substitui o ficheiro se já existir}';

    protected string $description = 'Cria uma view Blade';

    protected string $type = 'View';

    protected function stub(): string
    {
        return 'view.stub';
    }

    /** `admin.leads.index` e `admin/leads/index` dão `admin/leads/index`. */
    protected function qualifyName(string $name): string
    {
        $segments = preg_split('#[./\\\\]#', $name) ?: [];

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $segment) !== 1) {
                throw new InvalidArgumentException("Nome de view inválido [{$name}]: usa letras, números, - e _ (e . ou / para sub-pastas).");
            }
        }

        return implode('/', $segments);
    }

    protected function getPath(string $name): string
    {
        return $this->appInstance()->resourcePath('views'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name).'.blade.php');
    }

    protected function buildClass(string $name): string
    {
        return $this->replaceMarkers($this->stubContents(), ['view' => str_replace('/', '.', $name)]);
    }
}
