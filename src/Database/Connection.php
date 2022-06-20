<?php
namespace Slendie\Framework\Database;

use Slendie\Framework\Environment\Env;

use PDO;
use PDOException;

class Connection
{
    private static $connection;
    private static $options = [];

    /* Singleton */
    private function __construct() {
    }

    public static function setOptions( $options ) 
    {
        self::$options = $options;
    }

    public function __clone() {}

    public function __wakeup() {}

    public static function connect(): PDO|bool
    {
        if ( count( self::$options ) == 0 ) return false;

        $driver     = self::$options['driver'] ?? "mysql";
        $server     = self::$options['server'] ?? "localhost";
        $port       = self::$options['port'] ?? NULL;
        $user       = self::$options['user'] ?? NULL;
        $pass       = self::$options['password'] ?? NULL;
        $dbname     = self::$options['dbname'] ?? NULL;
        $root       = Env::getBase();

        if ( empty($driver) ) {
            throw new \Exception('Database driver is missing.');
        }
        if ( empty($dbname) ) {
            throw new \Exception('Database name is missing.');
        }

        switch ( strtoupper( $driver ) ) {
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
                $file = $root . $dbname;
                $dsn = "sqlite:{$file}";
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
    }

    public static function getConnection( $options = [] ): PDO
    {
        if ( is_null( $options ) ) {
            $options = self::$options;
        } elseif ( count( $options ) == 0 ) {
            $options = self::$options;
        }

        if ( is_null(self::$connection) ) {
            self::setOptions( $options );

            self::$connection = self::connect();
            // self::$connection->exec("set names utf8");

            if ( self::$connection ) {
                self::$connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            } else {
                throw new \Exception('Cannot connect to database.');
            }
            
        }
        return self::$connection;
    }

    public static function get( $attr )
    {
        if ( count( self::$options ) == 0 ) return null;

        if ( !array_key_exists( $attr, self::$options ) ) return null;
        
        return self::$options[ $attr ];
    }

}