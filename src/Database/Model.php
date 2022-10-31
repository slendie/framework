<?php

namespace Slendie\Framework\Database;

use Slendie\Framework\Database\Database;
use Slendie\Framework\Database\Sql;

class Model
{
    private $db = null;
    private static $sql = null;
    protected static $table = NULL;
    protected $log_timestamp = true;
    protected $soft_deletes = true;

    public function __construct()
    {
        $this->db = Database::getInstance();
        static::$sql = new Sql();
        static::$sql->table( static::$table );
    }

    public static function first()
    {
        static::$sql->limit( 1 );
        $result = $this->db->query( static::$sql->get() );
        return $result->fetch();
    }

    public static function __callStatic( $method, $arguments )
    {
        if ( is_null( static::$sql ) ) {
            static::$sql = new Sql( new static() );
            static::$sql->table( static::$table );
        }
        return call_user_func_array([static::$sql, $method], $arguments);
    }
}