<?php
namespace Slendie\Framework\Database;

use Slendie\Framework\Environment\Environment;

use PDO;
use PDOException;

class Connection
{
    private static $connection;
    private static $data;

    /**
     * Singleton
     */
    private function __construct() {}

    public function __clone() {}

    public function __wakeup() {}

    /**
     * Make a connection string and generate PDO object
     */
    private static function make( array $data ): PDO
    {
        $driver     = $data['db_driver'] ?? "mysql";
        $server     = $data['db_server'] ?? "localhost";
        $port       = $data['db_port'] ?? NULL;
        $user       = $data['db_user'] ?? NULL;
        $pass       = $data['db_password'] ?? NULL;
        $dbname     = $data['db_dbname'] ?? NULL;

        if ( !is_null($driver) ) {
            switch( strtoupper($driver) ) {
                case 'MYSQL':
                    $port = $port ?? 3306;
                    $dsn = "mysql:host={$server};port={$port};dbname={$dbname}";
                    return new PDO($dsn, $user, $pass);
                    break;

                case 'MSSQL':
                    $port = $port ?? 1433;
                    $dsn = "mssql:host={$server},{$port};dbname={$dbname}";
                    return new PDO($dsn, $user, $pass);
                    break;

                case 'PGSQL':
                    $port = $port ?? 5432;
                    $dsn = "pgsql:dbname={$dbname}; user={$user}; password={$pass}, host={$server};port={$port}";
                    return new PDO($dsn);
                    break;

                case 'SQLITE':
                    $dbname = env('base_dir') . $dbname;
                    $dsn = "sqlite:{$dbname}";
                    return new PDO($dsn);
                    break;

                case 'OCI8':
                    $dsn = "oci:dbname={$dbname}";
                    return new PDO($dsn, $user, $pass);
                    break;

                case 'FIREBIRD':
                    $dsn = "firebird:dbname={$dbname}";
                    return new PDO($dsn, $user, $pass);
                    break;

                default:
                    throw new \Exception(sprintf('Database driver %s is not supported.', $driver));
                    break;
            }
        } else {
            throw new \Exception('Database environment data is missed.');
        }
    }

    public static function getInstance(): PDO
    {
        if ( is_null(self::$connection) ) {
            self::$data = env('database');
            self::$connection = self::make( self::$data );
            self::$connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            // self::$connection->exec("set names utf8");
        }
        return self::$connection;
    }
}