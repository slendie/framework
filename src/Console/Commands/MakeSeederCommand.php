<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\GeneratorCommand;

final class MakeSeederCommand extends GeneratorCommand
{
    private const string NAMESPACE = 'Database\\Seeders';

    protected string $signature = 'make:seeder {name : Nome do seeder} {--force : Substitui o ficheiro se já existir}';

    protected string $description = 'Cria um seeder';

    protected string $type = 'Seeder';

    protected function stub(): string
    {
        return 'seeder.stub';
    }

    protected function qualifyName(string $name): string
    {
        $name = $this->normalizeName($name);

        return str_starts_with($name, self::NAMESPACE.'\\') ? $name : self::NAMESPACE.'\\'.$name;
    }

    protected function getPath(string $name): string
    {
        $relative = substr($name, strlen(self::NAMESPACE) + 1);

        return $this->appInstance()->databasePath('seeders'.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $relative).'.php');
    }
}
