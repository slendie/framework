<?php

declare(strict_types=1);

namespace Slendie\Testing\Concerns;

use RuntimeException;
use Slendie\Console\Application as ConsoleApplication;
use Slendie\Console\BufferedOutput;
use Slendie\Database\Connection;
use Slendie\Database\DatabaseManager;

/**
 * Peças comuns aos traits RefreshDatabase, DatabaseTransactions e DatabaseMigrations.
 *
 * @phpstan-require-extends \Slendie\Testing\TestCase
 */
trait ManagesTestDatabase
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function runConsoleOrFail(string $command, array $parameters = []): void
    {
        $output = new BufferedOutput();
        $console = $this->app->make(ConsoleApplication::class);
        $console->setInteractive(false);

        $code = $console->call($command, $parameters, $output);

        if ($code !== 0) {
            throw new RuntimeException("O comando [{$command}] falhou (código {$code}):\n".$output->fetch());
        }
    }

    /**
     * Abre uma transacção em cada ligação indicada e desfá-la no fim do teste.
     */
    protected function beginDatabaseTransaction(): void
    {
        $db = $this->app->make(DatabaseManager::class);

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $db->connection($name);
            $connection->beginTransaction();

            $this->beforeApplicationDestroyed(static function () use ($connection): void {
                while ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
            });
        }
    }

    /**
     * Ligações a envolver numa transacção (`null` = a por omissão). Sobreponha com
     * `protected array $connectionsToTransact = ['sqlite', 'outra'];`.
     *
     * @return list<string|null>
     */
    protected function connectionsToTransact(): array
    {
        if (property_exists($this, 'connectionsToTransact') && is_array($this->connectionsToTransact)) {
            /** @var list<string|null> */
            return $this->connectionsToTransact;
        }

        return [null];
    }

    /** Identifica uma ligação pela sua configuração (duas apps com a mesma BD partilham o resultado das migrações). */
    protected function connectionKey(DatabaseManager $db, ?string $name = null): string
    {
        $name ??= $db->getDefaultConnection();
        $config = $db->getConfig();
        $connections = is_array($config['connections'] ?? null) ? $config['connections'] : [];

        // O semeador faz parte da identidade: duas configurações de semente não podem partilhar a mesma base migrada.
        $seeding = (property_exists($this, 'seed') ? var_export($this->seed, true) : '').(property_exists($this, 'seeder') ? (string) $this->seeder : '');

        return $name.'|'.$this->app->basePath().'|'.json_encode($connections[$name] ?? [], JSON_PARTIAL_OUTPUT_ON_ERROR).'|'.$seeding;
    }

    protected function defaultConnection(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection();
    }
}
