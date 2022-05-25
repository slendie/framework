<?php
namespace Slendie\Framework\Database;

use Slendie\Framework\Environment\Env;
use Slendie\Tools\Str;

use \PDO;

class Model
{
    protected static $db = null;

    // TODO: rename $table to $_table. Rename all internal properties.
    protected $table = null;
    protected $_id = null;
    protected $statement = null;
    protected $data = [];
    protected $meta = [];
    protected $sql = null;

    /* Model Control - can be overriden in child class */
    protected $log_timestamp = null;
    protected $soft_deletes = null;
    protected $select_deleted = null;

    public function __construct()
    {
        /* Define table name */
        if ( is_null( $this->table ) ) {
            $table = self::tableName();
            $this->setTable( $table );
        } else {
            $table = $this->table;
        }

        /* Define ID column */
        if ( is_null( $this->_id ) ) {
            $this->_id = 'id';
        }

        /* Set the 'id' column */
        $this->sql = new Sql( $table );
        $this->sql->setId( $this->_id );

        /* Connect to the database */
        self::connect();

        /* Extract Meta info from table */
        $sql = new Sql( $table );
        $sqlText = $sql->select()->limit(1)->get();

        $statement = self::query( $sqlText );
        $columns_count = $statement->columnCount();

        for ( $i = 0; $i < $columns_count; $i++ ) {
            $this->meta[ $statement->getColumnMeta($i)['name'] ] = [
                'type'          => $statement->getColumnMeta($i)['pdo_type'],
                'native_type'   => $statement->getColumnMeta($i)['native_type'],
                'flags'         => $statement->getColumnMeta($i)['flags'],
                'len'           => $statement->getColumnMeta($i)['len'],
                'precision'     => $statement->getColumnMeta($i)['precision'],
            ];
        }

        /* Configure model */
        if ( is_null( $this->log_timestamp )) {
            $this->log_timestamp = false;
        }
        if ( is_null( $this->soft_deletes )) {
            $this->soft_deletes = false;
        }
        if ( is_null( $this->select_deleted )) {
            $this->select_deleted = false;
        }
    }

    /**
     * Set column value
     */
    public function __set( $key, $value ) 
    {
        $this->data[ $key ] = $value;
    }

    /**
     * Get column value
     */
    public function __get( $key )
    {
        if ( !array_key_exists( $key, $this->data ) ) {
            throw new \Exception('Atributo ' . $key . ' inexistente na tabela ' . $this->table);
        }

        switch ( $this->meta[ $key ][ 'native_type' ] ) {
            case 'integer':
                return (int) $this->data[ $key ];
                break;

        }

        return $this->data[ $key ];
    }

    /**
     * Check if column value exists
     */
    public function __isset( $key )
    {
        return isset( $this->data[ $key ] );
    }

    /**
     * Unset column value
     */
    public function __unset( $key ) 
    {
        if ( array_key_exists( $key, $this->data ) ) {
            unset( $this->data[ $key ] );
            return true;
        }
        return false;
    }

    /**
     * When clone, do not copy the ID.
     */
    private function ___clone()
    {
        if ( array_key_exists( $this->_id, $this->data ) ) {
            unset( $this->data[ $this->_id ] );
        }
    }


    /**
     * Get table name from class name.
     */
    private static function tableName()
    {
        $class = explode('\\', get_called_class());
        $class_name = strtolower( array_pop( $class ) );
        $table_name = Str::plural( $class_name );

        return $table_name;
    }

    /**
     * Set table name property (override)
     */
    public function setTable( $name )
    {
        $this->table = $name;
    }

    /**
     * Get table name from property.
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get table meta data.
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * Set column values
     */
    public function setData( array $data )
    {
        $this->data = $data;
    }

    /**
     * Return column values (as array)
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * Set column values (same as setData)
     */
    public function fromArray( array $array )
    {
        $this->setData( $array );
    }

    /**
     * Return column values as Json.
     */
    public function toJson()
    {
        return json_encode( $this->data );
    }

    /**
     * Set column values from Json.
     */
    public function fromJson( string $json )
    {
        $this->setData( json_decode( $json ) );
    }

    /**
     * Connect to the database
     */
    public static function connect()
    {
        if ( is_null( self::$db ) ) {
            $env = Env::getInstance();
            $database = $env->database;

            if ( is_null( $database ) || empty( $database ) ) {
                throw new \Exception('No options for database defined in .env file.');
            }

            self::$db = Database::getInstance( $database );
        }
    }

    /**
     * Execute an SQL statement and return the number of rows.
     */
    private static function exec( $sql )
    {
        self::connect();
        return Database::exec( $sql );
    }

    /**
     * Prepare and execute SQL statement without placeholders.
     */
    private static function query( $sql )
    {
        self::connect();
        return Database::query( $sql );
    }

    /**
     * Execute a prepared query and return number of rows.
     */
    public static function execute( $prepare, $values )
    {
        self::connect();
        return Database::execute( $prepare, $values );
    }

    /**
     * Fetch a statement
     */
    public static function fetch( $statement )
    {
        self::connect();
        return Database::fetch( $statement, get_called_class() );
    }

    /**
     * Open a cursor and keep in its statement.
     */
    public function cursor( $sql )
    {
        $this->statement = Database::cursor( $sql );
    }

    /**
     * Return next record from its statement.
     */
    public function fetchNext()
    {
        if ( !is_null( $this->statement )  ) {
            // return Database::fetchNext( $this->statement );
            return $this->statement->fetchObject( get_called_class() );
        } 
        return false;
    }

    /**
     * Return all records from table.
     */
    public static function all()
    {
        self::connect();

        $sql = new Sql( self::tableName() );
        $select = $sql->select()->get();

        return Database::fetchAll( $select, get_called_class() );
    }

    /**
     * Find a record by ID.
     */
    public static function find( $id )
    {
        self::connect();

        $sql = new Sql( self::tableName() );
        $sqlSelect = $sql->select()->where( $this->_id, $id )->get();

        $statement = Database::query( $sqlText );
        return Database::fetch( $statement, get_called_class() );
    }

    /**
     * Build its SQL: SELECT
     */
    public function select( $columns = '*' )
    {
        $this->sql->select( $columns );
        return $this;
    }
 
    public function count()
    {
        return (int) $this->select( 'COUNT(*) as n_rows' )->get();
    }

    /**
     * Return from select()
     */
    public function get()
    {
        $select = $this->sql->get();

        $statement = self::query( $select );
        return self::fetch( $statement );
    }

    public static function lastInsertId()
    {
        $connection = Connection::getConnection();
        return $connection->lastInsertId();
    }

    /**
     * Save record to the database.
     * If has ID, than update.
     * Else, insert a new record.
     */
    public function save()
    {
        if ( true == $this->log_timestamp ) {
            $this->data[ 'updated_at' ] = date('Y-m-d H:i:s');
        }
        if ( isset( $this->{$this->_id} ) ) {
            return $this->update();
        } else {
            if ( true == $this->log_timestamp ) {
                $this->data[ 'created_at' ] = date('Y-m-d H:i:s');
            }
            return $this->insert();
        }
    }

    public function update()
    {
        $this->sql->setData( $this->data );
        $prepare = $this->sql->update()->where( $this->_id , $this->data[ $this->_id ])->get();

        $values = $this->data;
        unset( $values[ $this->_id ] );

        return Database::execute( $prepare, $values );
    }

    public function insert()
    {
        $this->sql->setData( $this->data );
        $prepare = $this->sql->insert()->get();

        $values = $this->data;

        $response = Database::execute( $prepare, $values );

        /* Update ID when insert */
        if ( $response ) {
            $this->data[ $this->_id ] = self::lastInsertId();
        }

        return $response;
    }
 
    public function delete()
    {
        if ( true == $this->soft_deletes ) {
            $this->data[ 'deleted_at' ] = date('Y-m-d H:i:s');
            return $this->save();
        } else {
            $this->sql->setData( $this->data );
            $prepare = $this->sql->delete()->where( $this->_id, $this->data[ $this->_id ] )->get();
    
            $values = $this->data;
    
            return Database::execute( $prepare, $values );
        }
    }

    public function remove()
    {
        $this->sql->setData( $this->data );
        $prepare = $this->sql->delete()->where( $this->_id, $this->data[ $this->_id ] )->get();

        $values = $this->data;

        return Database::execute( $prepare, $values );
    }

    public function truncate()
    {
        $db = Database::getInstance();
        $options = $db->getOptions();

        if ( $options['driver'] == 'sqlite' ) {
            $sql = new Sql( $this->table );
            $delete = $sql->delete()->get();
            $n_rows_truncated = Database::exec( $delete );

            $sql = new Sql('sqlite_sequence');
            $delete = $sql->delete()->where('name', $this->table)->get();
            $n_rows = Database::exec( $delete );
        } else {
            $truncate = $sql->truncate();
            $n_rows_truncated = Database::exec( $truncate );
        }
        return $n_rows_truncated;
    }

    /**
     * Build its SQL: where
     */
    public function where( $column, $value )
    {
        $this->sql->where( $column, $value );
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

    /**
     * Build its SQL: ORDER BY
     */
    public function order( $column, $direction = 'ASC')
    {
        $this->sql = $this->sql->order( $column, $direction );
        return $this;
    }

    public function first()
    {
        $this->sql = $this->sql->limit(1);
        return $this->get();
    }
}