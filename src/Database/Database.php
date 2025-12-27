<?php

namespace Slendie\Framework\Database;

use \PDO;
use Slendie\Framework\Database\Connection;

class Database 
{
    private static $instance;
    protected $conn = null;
    protected $sth = null;
    protected $attributes = [];

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

    public function getColumnsMeta()
    {
        $this->attributes = [];

        $n_columns = $this->sth->columnCount();
        for( $i = 0; $i < $n_columns; $i++ ) {
            $column = $this->sth->getColumnMeta( $i );
            $this->attributes[ $column['name'] ] = $column;
        }

        return $this->attributes;
    }

    public function getMeta( $name )
    {
        if ( count( $this->attributes ) == 0 ) {
            $this->getColumnsMeta();
        }
        return $this->attributes[ $name ];
    }

    #region Prepared SQL
    public function selectAllPreparedSql( $sql, $class = '', $values = [])
    {
        $this->sth = $this->conn->prepare( $sql );
        $this->sth->execute( $values );

        if ( empty( $class) ) {
            return $this->sth->fetchAll( PDO::FETCH_ASSOC );
        } else {
            return $this->sth->fetchAll( PDO::FETCH_CLASS, $class );
        }
    }

    public function selectPreparedSql( $sql, $class = '', $values = [] )
    {
        $this->sth = $this->conn->prepare( $sql );
        $this->sth->execute( $values );

        if ( empty( $class) ) {
            return $this->sth->fetch( PDO::FETCH_ASSOC );
        } else {
            return $this->sth->fetch( PDO::FETCH_CLASS, $class );
        }
    }

    public function execPreparedSql( $sql, $values = [] )
    {
        $this->sth = $this->conn->prepare( $sql );
        return $this->sth->execute( $values );
    }

    public function __call( $method, $arguments )
    {
        return call_user_func_array( [$this->conn, $method], $arguments );
    }
    #endregion

}
