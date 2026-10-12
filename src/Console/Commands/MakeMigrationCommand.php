<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use InvalidArgumentException;
use RuntimeException;
use Slendie\Console\Command;
use Slendie\Database\Migrations\MigrationCreator;
use Slendie\Foundation\Application;

final class MakeMigrationCommand extends Command
{
    protected string $signature = 'make:migration
        {name : Nome da migração (ex.: create_leads_table, add_phone_to_leads_table)}
        {--create= : Tabela a criar}
        {--table= : Tabela a alterar}
        {--path= : Pasta da migração (relativa à raiz do projecto)}';

    protected string $description = 'Cria uma migração';

    public function handle(MigrationCreator $creator, Application $app): int
    {
        $create = $this->option('create');
        $table = $this->option('table');

        if (is_string($create) && $create !== '') {
            [$table, $isCreate] = [$create, true];
        } elseif (is_string($table) && $table !== '') {
            $isCreate = false;
        } else {
            [$table, $isCreate] = [null, false];
        }

        $path = $this->option('path');
        $directory = is_string($path) && $path !== ''
            ? (preg_match('#^([A-Za-z]:[\\\\/]|/|\\\\\\\\)#', $path) === 1 ? $path : $app->basePath($path))
            : $app->databasePath('migrations');

        try {
            $file = $creator->create((string) $this->argument('name'), $directory, is_string($table) ? $table : null, $isCreate);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $base = rtrim($app->basePath(), '/\\').DIRECTORY_SEPARATOR;
        $relative = str_replace('\\', '/', str_starts_with($file, $base) ? substr($file, strlen($base)) : $file);

        $this->badge('INFO', "Migration [{$relative}] created successfully.");

        return self::SUCCESS;
    }
}
