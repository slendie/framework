<?php

namespace Slendie\Framework\Database;

use Slendie\Framework\Database\Database;
use Slendie\Framework\Database\Sql;

class Model
{
    protected static $db = null;
    protected $table;
    protected $alias;
    protected $attributes = [];
    protected $fillable = [];
    protected $hidden = [];
    protected $guarded = [];
    protected $casts = [];
    protected $dates = [];
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $primaryKey = 'id';
    protected $sql = null;

    public function __construct() {
        if ( is_null( self::$db ) ) {
            self::$db = Database::getInstance();
        }
    }

    public function getTable()
    {
        return $this->table;
    }

    public function setTable($table)
    {
        $this->table = $table;
    }

    public function setPrimaryKey($primaryKey)
    {
        $this->primaryKey = $primaryKey;
    }

    public function getSql()
    {
        $this->sql->model( null );  // remove model from sql, avoid infinite loop
        return $this->sql->get();
    }

    public function setSql( $sql)
    {
        $this->sql = $sql;
    }

    public function setModel( $model )
    {
        $this->sql->model( $model );
    }

/*    public static function find( $primaryKey )
    {
        $obj = new static();
        $obj->sql = new Sql( $obj->getTable() );
        $obj->sql->select()->where( $obj->primaryKey, $primaryKey );
        $obj->sql->model( $obj );
        return $obj->sql->get();
    }*/

    public static function find($primaryKey)
    {
        $model = new static();
        $model->sql = new Sql( $model->getTable() );

        $select = $model->sql->select()->where( $model->primaryKey )->get();

        return self::$db->selectPreparedSql( $select, get_class($model), [':' . $model->primaryKey => $primaryKey] );
    }

    public static function findOrFail($primaryKey)
    {
        $model = new static();
        $model->sql = new Sql( $model->getTable() );

        $row = $model->find($primaryKey);
        if ( !$row ) {
            throw new \Exception("Record {$primaryKey} not found on table {$model->getTable()}");
        }
        return $row;
    }

    public static function all()
    {
        $model = new Static();
        $model->sql = new Sql( $model->getTable() );

        $select = $model->sql->select()->get();

        return self::$db->selectAllPreparedSql( $select, get_class( $model ) );
    }

    public function get()
    {
        $select = $this->getSql();
        $query = self::$db->query( $select );
        return $query->fetchAll( \PDO::FETCH_CLASS, get_class($this) );
    }

    public function first()
    {
        $this->sql->limit(1);
        $select = $this->getSql();
        $query = self::$db->query( $select );
        $query->setFetchMode(\PDO::FETCH_CLASS, get_class($this) );
        return $query->fetch( \PDO::FETCH_CLASS, \PDO::FETCH_ORI_NEXT );
    }

    public static function __callStatic( $name, $arguments )
    {
        $obj = new static();
        $obj->sql = new Sql( $obj->getTable() );
        $obj->sql->$name( ...$arguments );
        $obj->sql->model( $obj );
        return $obj->sql;
    }

    public function __call( $name, $arguments )
    {
        $caller = get_called_class();

        /* Prevent infinite loop */
        if ( $caller == 'Slendie\Framework\Database\Sql' ) {
            throw new \Exception('Call to undefined method ' . __CLASS__ . $name . '()');
        }

        if ( is_null( $this->sql ) ) {
            $this->sql = new Sql( $this->getTable() );
        }
        $this->sql->$name( ...$arguments );
        $this->sql->model( $this);
        return $this->sql;
    }

/*    public function fetch()
    {
        $this->sth->setFetchMode( \PDO::FETCH_CLASS, get_class($this) );
        return $this->sth->fetch();
    }
*/
/*    public function __get( $name )
    {
        return $this->db->getAttribute( $name );
    }
*/
/*    public static function __callStatic( $name, $arguments )
    {
        $model = new static();
        return $model->$name( ...$arguments );
    }

    public function __call( $name, $arguments )
    {
        if ( is_null( $this->sql ) ) {
            $this->sql = new Sql($this->table);
        }
        $this->sql->$name( ...$arguments );
        return $this;
    }*/

}