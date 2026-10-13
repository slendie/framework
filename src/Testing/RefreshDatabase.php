<?php

declare(strict_types=1);

namespace Slendie\Testing;

use Slendie\Database\DatabaseManager;
use Slendie\Testing\Concerns\ManagesTestDatabase;

/**
 * Base de dados limpa em cada teste, de forma rápida: `migrate:fresh` só uma vez por processo (por ligação) e depois
 * cada teste corre dentro de uma transacção que é desfeita no fim. A ligação é partilhada entre testes, por isso
 * funciona também com SQLite em memória.
 *
 * Opções (declare-as na TestCase ou no teste):
 *   protected bool $seed = true;            // corre o DatabaseSeeder depois do migrate:fresh
 *   protected string $seeder = Foo::class;  // ou outro seeder
 *   protected array $connectionsToTransact = [null, 'outra'];
 */
trait RefreshDatabase
{
    use ManagesTestDatabase;

    protected function refreshDatabase(): void
    {
        $db = $this->app->make(DatabaseManager::class);
        $name = $db->getDefaultConnection();
        $key = $this->connectionKey($db, $name);
        $shared = DatabaseState::get($key);

        if ($shared !== null) {
            $db->setConnection($name, $shared);
        } else {
            $parameters = ['--force' => true];

            if (property_exists($this, 'seed') && $this->seed === true) {
                $parameters['--seed'] = true;
            }

            $this->runConsoleOrFail('migrate:fresh', $parameters);

            if (property_exists($this, 'seeder') && is_string($this->seeder) && $this->seeder !== '') {
                $this->seed($this->seeder);
            }

            DatabaseState::put($key, $db->connection($name));
        }

        $this->preserveConnection($name);
        $this->beginDatabaseTransaction();
    }
}
