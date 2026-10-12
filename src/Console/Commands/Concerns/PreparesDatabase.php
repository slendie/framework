<?php

declare(strict_types=1);

namespace Slendie\Console\Commands\Concerns;

use Slendie\Database\DatabaseManager;
use Slendie\Foundation\Application;

/**
 * Antes de migrar com SQLite garante que o ficheiro da base de dados existe
 * (pergunta, ou cria logo com `--force`). Exige a opção `--force` na assinatura.
 *
 * @phpstan-require-extends \Slendie\Console\Command
 */
trait PreparesDatabase
{
    protected function prepareDatabase(Application $app, DatabaseManager $db): bool
    {
        $config = $db->getConfig();
        $connections = is_array($config['connections'] ?? null) ? $config['connections'] : [];
        $connection = $connections[$db->getDefaultConnection()] ?? null;

        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'sqlite') {
            return true;
        }

        $database = is_scalar($connection['database'] ?? null) ? (string) $connection['database'] : '';

        if ($database === '' || $database === ':memory:' || str_contains($database, 'mode=memory')) {
            return true;
        }

        $file = preg_match('#^([A-Za-z]:[\\\\/]|/|\\\\\\\\)#', $database) === 1
            ? $database
            : $app->databasePath($database);

        if (is_file($file)) {
            return true;
        }

        if ($this->option('force') !== true && ! $this->confirm("The SQLite database [{$file}] does not exist. Create it?", true)) {
            $this->error('Database file not created.');

            return false;
        }

        $directory = dirname($file);

        if ((! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) || file_put_contents($file, '') === false) {
            $this->error("Could not create the SQLite database [{$file}].");

            return false;
        }

        $this->badge('INFO', "SQLite database [{$file}] created successfully.");

        return true;
    }
}
