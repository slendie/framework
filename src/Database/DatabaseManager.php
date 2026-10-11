<?php

declare(strict_types=1);

namespace Slendie\Database;

use Closure;
use InvalidArgumentException;
use Slendie\Foundation\Application;

/**
 * Gere as ligações nomeadas de `config/database.php` (criadas preguiçosamente, uma por nome)
 * e delega os métodos de Connection para a ligação por omissão.
 *
 * @mixin Connection
 */
class DatabaseManager
{
    /** @var array<string, Connection> */
    protected array $connections = [];

    /** @var array<string, Closure(array<string, mixed>, string): Connection> */
    protected array $extensions = [];

    /**
     * @param  array<string, mixed>  $config  `default` e `connections.{name}`
     */
    public function __construct(
        protected Application $app,
        protected array $config,
        protected ?ConnectionFactory $factory = null,
    ) {
        $this->factory ??= new ConnectionFactory($this->app->databasePath());
    }

    public function connection(?string $name = null): Connection
    {
        $name ??= $this->getDefaultConnection();

        return $this->connections[$name] ??= $this->makeConnection($name);
    }

    /** Esquece a ligação (a próxima chamada cria outra). */
    public function purge(?string $name = null): void
    {
        $name ??= $this->getDefaultConnection();

        if (isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
        }

        unset($this->connections[$name]);
    }

    public function reconnect(?string $name = null): Connection
    {
        $this->purge($name);

        return $this->connection($name);
    }

    public function getDefaultConnection(): string
    {
        $default = $this->config['default'] ?? 'sqlite';

        return is_string($default) && $default !== '' ? $default : 'sqlite';
    }

    public function setDefaultConnection(string $name): void
    {
        $this->config['default'] = $name;
    }

    /**
     * Regista um driver novo (packages): o resolver recebe `(array $config, string $name)` e devolve uma Connection.
     *
     * @param  Closure(array<string, mixed>, string): Connection  $resolver
     */
    public function extend(string $driver, Closure $resolver): void
    {
        $this->extensions[$driver] = $resolver;
    }

    /**
     * @return array<string, Connection>
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    protected function makeConnection(string $name): Connection
    {
        $connections = $this->config['connections'] ?? [];

        if (! is_array($connections) || ! isset($connections[$name]) || ! is_array($connections[$name])) {
            throw new InvalidArgumentException("A ligação de base de dados [{$name}] não está definida em config/database.php.");
        }

        /** @var array<string, mixed> $config */
        $config = $connections[$name];
        $driver = $config['driver'] ?? null;

        if (is_string($driver) && isset($this->extensions[$driver])) {
            return ($this->extensions[$driver])($config, $name);
        }

        return $this->factory()->make($config, $name);
    }

    protected function factory(): ConnectionFactory
    {
        return $this->factory ?? new ConnectionFactory();
    }

    /**
     * @param  array<int|string, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }
}
