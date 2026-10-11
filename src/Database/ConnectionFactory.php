<?php

declare(strict_types=1);

namespace Slendie\Database;

use InvalidArgumentException;
use PDO;
use Slendie\Database\Query\Grammar;
use Slendie\Database\Query\Grammars\MySqlGrammar;
use Slendie\Database\Query\Grammars\PgsqlGrammar;
use Slendie\Database\Query\Grammars\SqliteGrammar;
use Slendie\Exceptions\SlendieException;

/**
 * Cria Connections (com PDO preguiçoso) a partir da configuração de `config/database.php`.
 */
class ConnectionFactory
{
    public function __construct(protected ?string $databasePath = null)
    {
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function make(array $config, ?string $name = null): Connection
    {
        $driver = $config['driver'] ?? null;

        if (! is_string($driver) || $driver === '') {
            throw new InvalidArgumentException('A ligação'.($name !== null ? " [{$name}]" : '').' não define o "driver".');
        }

        if ($name !== null) {
            $config['name'] = $name;
        }

        $database = $this->resolveDatabase($driver, $config);
        $config['database'] = $database;

        return new Connection(
            fn (): PDO => $this->createPdo($driver, $config),
            $driver,
            $database,
            $config,
            $this->createGrammar($driver),
        );
    }

    public function createGrammar(string $driver): Grammar
    {
        return match ($driver) {
            'sqlite' => new SqliteGrammar(),
            'mysql', 'mariadb' => new MySqlGrammar(),
            'pgsql' => new PgsqlGrammar(),
            default => throw new InvalidArgumentException("Driver de base de dados não suportado [{$driver}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function createPdo(string $driver, array $config): PDO
    {
        $pdoDriver = $driver === 'mariadb' ? 'mysql' : $driver;

        if (! in_array($pdoDriver, PDO::getAvailableDrivers(), true)) {
            throw new SlendieException("O driver PDO [{$pdoDriver}] não está instalado (extensão pdo_{$pdoDriver}).");
        }

        $options = $this->options($config);

        $pdo = match ($driver) {
            'sqlite' => $this->connectSqlite($config, $options),
            'mysql', 'mariadb' => $this->connectMySql($config, $options),
            'pgsql' => $this->connectPgsql($config, $options),
            default => throw new InvalidArgumentException("Driver de base de dados não suportado [{$driver}]."),
        };

        return $pdo;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function resolveDatabase(string $driver, array $config): string
    {
        $database = $config['database'] ?? '';
        $database = is_scalar($database) ? (string) $database : '';

        if ($driver !== 'sqlite' || $database === '' || $database === ':memory:' || str_contains($database, 'mode=memory')) {
            return $database === '' && $driver === 'sqlite' ? ':memory:' : $database;
        }

        if (preg_match('#^([A-Za-z]:[\\\\/]|/|\\\\\\\\)#', $database) === 1) {
            return $database;
        }

        $base = $this->databasePath ?? (getcwd() !== false ? getcwd().DIRECTORY_SEPARATOR.'database' : 'database');

        return rtrim($base, '/\\').DIRECTORY_SEPARATOR.ltrim($database, '/\\');
    }

    /**
     * Opções PDO comuns, que `options` da config pode sobrepor.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, mixed>
     */
    protected function options(array $config): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        $custom = $config['options'] ?? [];

        if (is_array($custom)) {
            foreach ($custom as $key => $value) {
                if (is_int($key)) {
                    $options[$key] = $value;
                }
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, mixed>  $options
     */
    protected function connectSqlite(array $config, array $options): PDO
    {
        $database = (string) $config['database'];

        if ($database !== ':memory:' && ! str_contains($database, 'mode=memory') && ! is_dir(dirname($database))) {
            throw new SlendieException('A pasta da base de dados SQLite não existe: ['.dirname($database).']. Cria-a ou ajusta DB_DATABASE.');
        }

        $pdo = new PDO('sqlite:'.$database, null, null, $options);

        if (($config['foreign_key_constraints'] ?? true) !== false) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        $timeout = $config['busy_timeout'] ?? null;

        if (is_numeric($timeout)) {
            $pdo->exec('PRAGMA busy_timeout = '.(int) $timeout);
        }

        $journal = $config['journal_mode'] ?? null;

        if (is_string($journal) && preg_match('/^(delete|truncate|persist|memory|wal|off)$/i', $journal) === 1) {
            $pdo->exec('PRAGMA journal_mode = '.strtolower($journal));
        }

        return $pdo;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, mixed>  $options
     */
    protected function connectMySql(array $config, array $options): PDO
    {
        $charset = $this->string($config, 'charset', 'utf8mb4');

        if ($this->string($config, 'unix_socket') !== '') {
            $dsn = 'mysql:unix_socket='.$this->string($config, 'unix_socket');
        } else {
            $dsn = 'mysql:host='.$this->string($config, 'host', '127.0.0.1').';port='.$this->string($config, 'port', '3306');
        }

        $dsn .= ';dbname='.$this->string($config, 'database').';charset='.$charset;

        $pdo = new PDO($dsn, $this->string($config, 'username'), $this->string($config, 'password'), $options);

        $names = 'SET NAMES '.$pdo->quote($charset);
        $collation = $this->string($config, 'collation');

        if ($collation !== '') {
            $names .= ' COLLATE '.$pdo->quote($collation);
        }

        $pdo->exec($names);

        $timezone = $this->string($config, 'timezone');

        if ($timezone !== '') {
            $pdo->exec('SET time_zone = '.$pdo->quote($timezone));
        }

        if (($config['strict'] ?? true) !== false) {
            $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }

        return $pdo;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, mixed>  $options
     */
    protected function connectPgsql(array $config, array $options): PDO
    {
        $dsn = 'pgsql:host='.$this->string($config, 'host', '127.0.0.1')
            .';port='.$this->string($config, 'port', '5432')
            .';dbname='.$this->string($config, 'database');

        if ($this->string($config, 'sslmode') !== '') {
            $dsn .= ';sslmode='.$this->string($config, 'sslmode');
        }

        $pdo = new PDO($dsn, $this->string($config, 'username'), $this->string($config, 'password'), $options);

        $pdo->exec('SET client_encoding TO '.$pdo->quote($this->string($config, 'charset', 'utf8')));

        $schema = $this->string($config, 'search_path') !== '' ? $this->string($config, 'search_path') : $this->string($config, 'schema');

        if ($schema !== '') {
            $schemas = array_map(fn (string $s): string => '"'.str_replace('"', '""', trim($s)).'"', explode(',', $schema));
            $pdo->exec('SET search_path TO '.implode(', ', $schemas));
        }

        $timezone = $this->string($config, 'timezone');

        if ($timezone !== '') {
            $pdo->exec('SET time zone '.$pdo->quote($timezone));
        }

        return $pdo;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function string(array $config, string $key, string $default = ''): string
    {
        $value = $config[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }
}
