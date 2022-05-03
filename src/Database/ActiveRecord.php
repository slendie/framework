<?php
namespace Slendie\Framework\Database;

use \PDO;

class ActiveRecord
{
    private static $connection;

    private $data = [];
    protected $table = NULL;
    protected $id_column = NULL;
    protected $sql = NULL;
    protected $log_timestamp;
    protected $soft_deletes;
    protected $select_deleted;

    public function __construct()
    {
        $this->sql = new Sql();

        if ( !is_bool( $this->log_timestamp )) {
            $this->log_timestamp = true;
        }
        if ( !is_bool( $this->soft_deletes )) {
            $this->soft_deletes = false;
        }
        if ( !is_bool( $this->select_deleted )) {
            $this->select_deleted = false;
        }

        if ( is_null( $this->table ) ) {
            $class = explode('\\', get_class($this));
            $this->table = strtolower( array_pop($class) );
        }
        $this->sql->setTable( $this->table );

        if ( is_null( $this->id_column ) ) {
            $this->id_column = 'id';
            $this->sql->setIdColumn( $this->id_column );
        }
    }

    /**
     * Set PDO connection
     * @param PDO $connection
     * @return void
     */
    public static function setConnection( PDO $connection )
    {
        self::$connection = $connection;
    }

    public function __set( $param, $value ) 
    {
        $this->data[$param] = $value;
    }

    public function __get( $param )
    {
        return $this->data[$param];
    }

    public function __isset( $param )
    {
        return isset( $this->data['param'] );
    }

    public function __unset( $param ) 
    {
        if ( isset( $param ) ) {
            unset( $this->data[$param] );
            return true;
        }
        return false;
    }
    private function ___clone()
    {
        if ( isset($this->data[ $this->id_column ])) {
            unset($this->data[ $this->id_column ]);
        }
    }

    public function toArray()
    {
        return $this->data;
    }

    public function fromArray( array $array ) 
    {
        $this->data = $array;
    }

    public function toJson()
    {
        return json_encode( $this->data );
    }

    public function fromJson( string $json )
    {
        $this->data = json_decode( $json );
    }

    public function save()
    {
        if ( $this->log_timestamp === true ) {
            $this->updated_at = date('Y-m-d H:i:s');

            if ( !array_key_exists( $this->id_column, $this->data ) ) {
                $this->created_at = date('Y-m-d H:i:s');
            }
        }
        $this->sql->setPairs( $this->data );
        $sql = $this->sql->save();
        $res = self::exec( $this->sql->save() );

        // If INSERT, update id
        if ( $this->wasInserted() ) {
            $conn = Connection::getInstance();
            $this->data[ $this->id_column ] = $conn->lastInsertId();
        }

        return $res;
    }

    public function wasInserted() 
    {
        // Check SQL if it was an Insert
        if ( substr( $this->sql->get(), 0, strlen('INSERT') ) == 'INSERT' ) {
            return true;
        } else {
            return false;
        }
    }

    public function softDeletes() 
    {
        return $this->soft_deletes;
    }

    public function select()
    {
        $this->sql = $this->sql->select();
        return $this;
    }

    public function customSelect( string $select )
    {
        $this->sql = $this->sql->customSelect( $select );
        return $this;
    }

    public function orderBy( $column, $direction = 'ASC')
    {
        $this->sql = $this->sql->orderBy( $column, $direction );
        return $this;
    }

    public function delete()
    {
        if ( isset( $this->data[ $this->id_column ] ) ) {
            if ( $this->soft_deletes === true ) {
                $this->deleted_at = date("Y-m-d H:i:s");
                $this->save();
            } else {
                $this->sql = $this->sql->where($this->id_column, $this->data[ $this->id_column ]);
                return self::exec( $this->sql->delete()->get() );
            }
        }
    }

    public function remove()
    {
        if ( isset( $this->data[ $this->id_column ] ) ) {
            $this->sql = $this->sql->where($this->id_column, $this->data[ $this->id_column ]);
            return self::exec( $this->sql->delete()->get() );
        }
    }

    public static function find( $id )
    {
        $sql = new Sql();

        $class = get_called_class();
        $id_column = (new $class())->id_column;
        $table = (new $class())->table;

        $sql->setTable( $table );
        $sql->setIdColumn( $id_column );

        // if ( $this->soft_deletes && !$this->select_deleted ) {
        //     $select_sql = $sql->where($id_column, $id)->where('deleted_at', NULL)->select()->get();
        // } else {
        //     $select_sql = $sql->where($id_column, $id)->select()->get();
        // }
        $select_sql = $sql->where($id_column, $id)->where('deleted_at', NULL)->select()->get();
        
        return self::fetchObject( $select_sql );
    }

    public static function findFirst( string $filter = '' )
    {
        return self::all( $filter, 1 );
    }

    public function where( $column, $value ) 
    {
        $select_sql = $this->sql->where( $column, $value );
        return $this;
    }

    public function whereNot( $column, $value ) 
    {
        $select_sql = $this->sql->whereNot( $column, $value );
        return $this;
    }

    public function whereRaw( $filter )
    {
        $this->sql->whereRaw( $filter );
        return $this;
    }

    public function get()
    {
        if ( $this->soft_deletes === true ) {
            if ( $this->select_deleted === false ) {
                $this->sql = $this->sql->where('deleted_at', NULL);
            }
        }
        
        $select_sql = $this->sql->select()->get();
        return self::fetchAll( $select_sql );
    }

    public static function customAll( string $select, string $filter = '', int $limit = 0, int $offset = 0 )
    {
        $sql = new Sql();

        $class = get_called_class();
        $table = (new $class())->table;

        $sql->setTable( $table );

        if ( !empty($filter) ) {
            $sql->whereRaw( $filter );
        }

        // if ( $this->soft_deletes && !$this->select_deleted ) {
        //     $select_sql = $sql->where('deleted_at', NULL)->limit( $limit )->offset( $offset )->customSelect( $select )->get();
        // } else {
        //     $select_sql = $sql->limit( $limit )->offset( $offset )->customSelect( $select )->get();
        // }
        $select_sql = $sql->where('deleted_at', NULL)->limit( $limit )->offset( $offset )->customSelect( $select )->get();
        
        return self::fetchAll( $select_sql );
    }

    public static function all( string $filter = '', int $limit = 0, int $offset = 0 )
    {
        $sql = new Sql();

        $class = get_called_class();
        $table = (new $class())->table;

        $sql->setTable( $table );

        if ( !empty($filter) ) {
            $sql->whereRaw( $filter );
        }

        // if ( $this->soft_deletes && !$this->select_deleted ) {
        //     $select_sql = $sql->where('deleted_at', NULL)->limit( $limit )->offset( $offset )->select()->get();
        // } else {
        //     $select_sql = $sql->limit( $limit )->offset( $offset )->select()->get();
        // }
        $select_sql = $sql->where('deleted_at', NULL)->limit( $limit )->offset( $offset )->select()->get();
        
        return self::fetchAll( $select_sql );
    }

    public static function count( string $column_name = '*', string $filter = '' )
    {
        $sql = new Sql();

        $class = get_called_class();
        $table = (new $class())->table;

        $sql->setTable( $table );

        if ( !empty($filter) ) {
            $sql->whereRaw( $filter );
        }
        $select_sql = $sql->count()->get();
        $res = self::fetch( $select_sql );
        return (int) $res['num_rows'];
    }

    public static function fetchObject( $sql )
    {
        $res = self::query( $sql );

        if ( $res ) {
            $obj = $res->fetchObject( get_called_class() );
        }

        return $obj;   
    }

    public static function fetchAll( $sql )
    {
        $res = self::query( $sql );

        if ( $res ) {
            $obj = $res->fetchAll( PDO::FETCH_CLASS, get_called_class() );
        }

        return $obj;   
    }

    public static function exec( $sql )
    {
        $conn = Connection::getInstance();
        if ( $conn ) {
            // dd(['Database::ActiveRecord', $sql]);
            return $conn->exec( $sql );
        } else {
            throw new \Exception('Error on connecting to database.');
        }
    }

    public static function query( $sql )
    {
        $conn = Connection::getInstance();
        if ( $conn ) {
            return $conn->query( $sql );
        } else {
            throw new \Exception('Error on connecting to database.');
        }
    }

    public static function fetch( $sql )
    {
        $conn = Connection::getInstance();
        if ( $conn ) {
            $p = $conn->prepare( $sql );
            $p->execute();
            
            $q = $p->fetch( PDO::FETCH_ASSOC );
            return $q;
        }
    }

    public static function lastInsertId()
    {
        $conn = Connection::getInstance();
        return $conn->lastInsertId();
    }
}