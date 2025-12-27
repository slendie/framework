<?php
namespace Slendie\Framework\Database;

use PDO;
use PDOException;

class Connection
{
    #region Attributes
    private static $instance = null;
    private $conn = null;
    private $options = [];
    #endregion

    /* Singleton */
    private function __construct() {
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Connection();
        }
        return self::$instance;
    }

    public function setOptions( $options ) 
    {
        $this->options = $options;
    }

    public function __clone() {}

    public function __wakeup() {}

    public function connect()
    {
        if ( is_null( $this->conn ) ) {
            $this->conn = $this->getPdoConnection();

            if ( $this->conn ) {
                $this->conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            } else {
                throw new \Exception('Cannot connect to database.');
                die();
            }
        }
    }

    public function getPdoConnection()
    {
        if ( count( $this->options ) == 0 ) return false;

        $driver     = $this->options['DRIVER'] ?? "mysql";
        $server     = $this->options['SERVER'] ?? "localhost";
        $port       = $this->options['PORT'] ?? NULL;
        $user       = $this->options['USER'] ?? NULL;
        $pass       = $this->options['PASSWORD'] ?? NULL;
        $dbname     = $this->options['DBNAME'] ?? NULL;
        $root       = SITE_FOLDER;

        if ( empty($driver) ) {
            throw new \Exception('Database driver is missing.');
            die();
        }
        if ( empty($dbname) ) {
            throw new \Exception('Database name is missing.');
            die();
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
                try {
                    return new PDO($dsn);
                } catch (PDOException $e) {
                    throw new \Exception($e->getMessage() . "\nSQLite File: " . $file);
                    die();
                }
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

    #region PDO Methods
    public function __call( $method, $arguments )
    {
        return call_user_func_array( [$this->conn, $method], $arguments );
    }
    #endregion
}