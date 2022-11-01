<?php

namespace Slendie\Framework\Database;

use \PDO;
use Slendie\Framework\Database\Connection;

class Database 
{
    private static $instance;
    public $conn = null;

    private function __construct() { 
    }

    public static function getInstance()
    {
        if ( is_null(self::$instance) ) {
            self::$instance = new Database();
            self::$instance->connect();
        }
        return self::$instance;
    }

    public function connect()
    {
        if ( is_null( $this->conn ) ) {
            $this->conn = Connection::getInstance();
            $this->conn->setOptions( env('DATABASE') );
            $this->conn->connect();
        }
    }

    #region Prepared SQL
    public function selectAllPreparedSql( $sql, $class = '', $values = [])
    {
        $sttm = $this->conn->prepare( $sql );
        $sttm->execute( $values );

        if ( empty( $class) ) {
            return $sttm->fetchAll( PDO::FETCH_ASSOC );
        } else {
            return $sttm->fetchAll( PDO::FETCH_CLASS, $class );
        }
    }

    public function selectPreparedSql( $sql, $class = '', $values = [] )
    {
        $sttm = $this->conn->prepare( $sql );
        $sttm->execute( $values );

        if ( empty( $class) ) {
            return $sttm->fetch( PDO::FETCH_ASSOC );
        } else {
            return $sttm->fetch( PDO::FETCH_CLASS, $class );
        }
    }

    public function execPreparedSql( $sql, $values = [] )
    {
        $sttm = $this->conn->prepare( $sql );
        return $sttm->execute( $values );
    }

    public function __call( $method, $arguments )
    {
        return call_user_func_array( [$this->conn, $method], $arguments );
    }
    #endregion

}
