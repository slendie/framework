<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\GeneratorCommand;
use Slendie\Support\Str;

final class MakeModelCommand extends GeneratorCommand
{
    protected string $signature = 'make:model
        {name : Nome do model (ex.: Lead)}
        {--migration|-m : Cria também a migração}
        {--controller|-c : Cria também um controller}
        {--resource|-r : O controller criado é um resource (ligado ao model)}
        {--all|-a : Cria migração, controller resource e seeder}
        {--seed|-s : Cria também um seeder}
        {--force : Substitui o ficheiro se já existir}';

    protected string $description = 'Cria um model (e, opcionalmente, migração, controller e seeder)';

    protected string $type = 'Model';

    protected function stub(): string
    {
        return 'model.stub';
    }

    protected function defaultNamespace(): string
    {
        return rtrim($this->rootNamespace(), '\\').'\\Models';
    }

    public function handle(): int
    {
        $status = parent::handle();

        if ($status !== self::SUCCESS) {
            return $status;
        }

        $name = $this->normalizeName((string) $this->argument('name'));
        $class = substr($name, (int) strrpos('\\'.$name, '\\'));
        $all = $this->option('all') === true;
        $force = $this->option('force') === true;

        // Um passo que falha (ex.: a migração já existe) não impede os restantes; o código de saída reflecte-o.
        $status = self::SUCCESS;

        if ($all || $this->option('migration') === true) {
            $table = Str::plural(Str::snake($class));
            $status = $this->call('make:migration', ['name' => "create_{$table}_table", '--create' => $table]);
        }

        if ($all || $this->option('controller') === true || $this->option('resource') === true) {
            $parameters = ['name' => $name.'Controller', '--force' => $force];

            if ($all || $this->option('resource') === true) {
                $parameters += ['--resource' => true, '--model' => $this->qualifyName($name)];
            }

            $status = max($status, $this->call('make:controller', $parameters));
        }

        if ($all || $this->option('seed') === true) {
            $status = max($status, $this->call('make:seeder', ['name' => $class.'Seeder', '--force' => $force]));
        }

        return $status;
    }
}
