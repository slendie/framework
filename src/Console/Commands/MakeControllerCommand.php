<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\GeneratorCommand;

final class MakeControllerCommand extends GeneratorCommand
{
    protected string $signature = 'make:controller
        {name : Nome do controller (ex.: LeadController ou Admin/LeadController)}
        {--resource : Gera os 7 métodos de um resource}
        {--api : Gera só os métodos de uma API (sem create/edit)}
        {--invokable : Gera um controller com um único método __invoke}
        {--model= : Model associado (liga o type-hint nos métodos)}
        {--force : Substitui o ficheiro se já existir}';

    protected string $description = 'Cria um controller';

    protected string $type = 'Controller';

    protected function stub(): string
    {
        if ($this->option('invokable') === true) {
            return 'controller.invokable.stub';
        }

        if ($this->option('model') !== null) {
            return 'controller.model.stub';
        }

        return match (true) {
            $this->option('api') === true => 'controller.api.stub',
            $this->option('resource') === true => 'controller.resource.stub',
            default => 'controller.plain.stub',
        };
    }

    protected function defaultNamespace(): string
    {
        return rtrim($this->rootNamespace(), '\\').'\\Http\\Controllers';
    }

    protected function replacements(string $name): array
    {
        $model = $this->option('model');

        if (! is_string($model) || $model === '') {
            return [];
        }

        $qualified = $this->normalizeName($model);

        $root = $this->rootNamespace();

        // Como no Laravel: fora do namespace da aplicação o nome é relativo a App\Models.
        if (! str_starts_with($qualified.'\\', $root)) {
            $qualified = $root.'Models\\'.$qualified;
        }

        $base = substr($qualified, (int) strrpos($qualified, '\\') + 1);

        return [
            'model' => $base,
            'modelVariable' => lcfirst($base),
            'namespacedModel' => $qualified,
        ];
    }

    protected function buildClass(string $name): string
    {
        $contents = parent::buildClass($name);

        // --model --api: o stub do model traz os 7 métodos; numa API não há formulários.
        if ($this->option('model') !== null && $this->option('api') === true && $this->option('invokable') !== true) {
            $contents = (string) preg_replace('/\n    \/\*\*[^\n]*\*\/\n    public function (?:create|edit)\([^\n]*\)\n    \{\n[^\n]*\n    \}\n/', '', $contents);
        }

        return $contents;
    }
}
