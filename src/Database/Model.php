<?php
namespace Slendie\Framework\Database;

use Slendie\Tools\Str;
use Slendie\Framework\Environment\Env;
use \PDO;

class Model
{
    protected static $_dbh = null;
    protected $_table = null;
    protected $_id = null;
    protected $_meta = [];
    protected $_data = [];

    protected $_sql = null;

    protected $log_timestamp = false;
    protected $soft_deletes = false;

    public function __construct()
    {
        /* Define table name */
        if ( is_null( $this->_table ) ) {
            $table = self::tableName();
            $this->setTable( $table );
        } else {
            $table = $this->_table;
        }

        /* Define ID column */
        if ( is_null( $this->_id ) ) {
            $this->_id = 'id';
        }

        /* Connect to the database */
        self::connect();

        /* Get columns meta data */
        $this->setMeta();

        /* Configure model */
        if ( is_null( $this->log_timestamp )) {
            $this->log_timestamp = false;
        }
        if ( is_null( $this->soft_deletes )) {
            $this->soft_deletes = false;
        }
    }

    /**
     * Connect to the database
     */
    public static function connect()
    {
        if ( is_null( self::$_dbh ) ) {
            $env = Env::getInstance();
            $database = $env->database;

            if ( is_null( $database ) || empty( $database ) ) {
                throw new \Exception('No options for database defined in .env file.');
            }

            self::$_dbh = Database::getInstance( $database );
            self::$_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    /* *** Magic methods *** */
    /**
     * Set column value
     */
    public function __set( $key, $value ) 
    {
        $this->_data[ $key ] = $value;
    }

    /**
     * Get column value
     */
    public function __get( $key )
    {
        if ( !array_key_exists( $key, $this->_data ) ) {
            throw new \Exception('Atributo ' . $key . ' inexistente na tabela ' . $this->getTable() );
        }

        if ( array_key_exists( $key, $this->_meta ) ) {
            switch ( $this->_meta[ $key ][ 'native_type' ] ) {
                case 'integer':
                    return (int) $this->_data[ $key ];
                    break;
    
            }
        }

        return $this->_data[ $key ];
    }

    /**
     * Check if column value exists
     */
    public function __isset( $key )
    {
        return isset( $this->_data[ $key ] );
    }

    /**
     * Unset column value
     */
    public function __unset( $key ) 
    {
        if ( array_key_exists( $key, $this->_data ) ) {
            unset( $this->_data[ $key ] );
            return true;
        }
        return false;
    }

    /**
     * When clone, do not copy the ID.
     */
    private function ___clone()
    {
        if ( array_key_exists( $this->_id, $this->_data ) ) {
            unset( $this->_data[ $this->_id ] );
        }
    }

    /* *** Auxiliary methods *** */

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

    private function setMeta()
    {
        /* Extract Meta info from table */
        $sql = new Sql( $this->getTable() );
        $select = $sql->select()->limit(1)->get();

        $statement = self::$_dbh->query( $select );
        $columns_count = $statement->columnCount();
        $columns = [];

        for ( $i = 0; $i < $columns_count; $i++ ) {
            $columns[ $statement->getColumnMeta($i)['name'] ] = '';
            $this->_meta[ $statement->getColumnMeta($i)['name'] ] = [
                'type'          => $statement->getColumnMeta($i)['pdo_type'],
                'native_type'   => $statement->getColumnMeta($i)['native_type'],
                'flags'         => $statement->getColumnMeta($i)['flags'],
                'len'           => $statement->getColumnMeta($i)['len'],
                'precision'     => $statement->getColumnMeta($i)['precision'],
            ];
        }
    }

    public function getMeta()
    {
        return $this->_meta;
    }

    private static function columnRelated( $table )
    {
        // echo "{$table}: " . Str::singular( strtolower( $table ) ) . "_id" . PHP_EOL;
        return Str::singular( strtolower( $table ) ) . "_id";
    }

    /**
     * Set table name property (override)
     */
    public function setTable( $name )
    {
        $this->_table = $name;
    }

    /**
     * Get table name from property.
     */
    public function getTable()
    {
        return $this->_table;
    }

    public function getId()
    {
        return $this->_id;
    }

    /**
     * Setting and Getting Data
     */
    public function fromArray( $data )
    {
        $this->_data = $data;
    }

    public function toArray()
    {
        return $this->_data;
    }

    public function fromJson( string $json )
    {
        $this->fromArray( json_decode( $json ) );
    }

    public function toJson()
    {
        return json_encode( $this->toArray() );
    }

    /**
     * Frequent SQL functions
     */
    public static function fetchAll( $sql )
    {
        return self::$_dbh->fetchAll( $sql, get_called_class() );
    }

    public static function fetch( $sql )
    {
        return self::$_dbh->fetch( $sql, get_called_class() );
    }

    public static function fetchAssoc( $sql )
    {
        return self::$_dbh->fetch( $sql );
    }

    public static function all( $columns = '*' )
    {
        self::connect();

        $sql = new Sql( self::tableName() );
        $select = $sql->select( $columns )->get();

        return self::fetchAll( $select );
    }

    public static function find( $id, $key = 'id' )
    {
        self::connect();

        $sql = new Sql( self::tableName() );
        $select = $sql->select()->where( $key, $id )->get();

        return self::fetch( $select );
    }

    /**
     * Find a child relationship, one-to-one
     */
    public function hasOne( $model, $related_column = '' )
    {
        if ( empty( $related_column ) ) {
            $related_column = self::columnRelated( $this->getTable() );
        }

        $sql = new Sql( $model->getTable() );
        $select = $sql->select()->where( $related_column, $this->{$this->_id} )->get();

        // return self::fetch( $select );
        $class = get_class( $model );
        return $class::fetch( $select );
    }

    /**
     * Find a parent relationship, one-to-one
     */
    public function belongsToOne( $model, $related_column = '' )
    {
        if ( empty( $related_column ) ) {
            $related_column = self::columnRelated( $model->getTable() );
        }

        $sql = new Sql( $model->getTable() );
        $select = $sql->select()->where( $model->getId() , $this->{$related_column} )->get();

        // return self::fetch( $select );
        $class = get_class( $model );
        return $class::fetch( $select );
    }

    /**
     * Find a child relationship, one-to-many
     */
    public function hasMany( $model, $related_column = '' )
    {
        if ( empty( $related_column ) ) {
            $related_column = self::columnRelated( $this->getTable() );
        }

        $sql = new Sql( $model->getTable() );
        $select = $sql->select()->where( $related_column, $this->{$this->_id} )->get();

        // return self::fetchAll( $select );
        $class = get_class( $model );
        return $class::fetchAll( $select );
    }

    /**
     * Find a parent relationship, one-to-many
     */
    public function belongsToMany( $model, $related_column )
    {
        if ( empty( $related_column ) ) {
            $related_column = self::columnRelated( $model->getTable() );
        }

        $sql = new Sql( $model->getTable() );
        $select = $sql->select()->where( $model->getId() , $this->{$related_column} )->get();

        // return self::fetchAll( $select );
        $class = get_class( $model );
        return $class::fetchAll( $select );
    }

    public static function prepare( $sql )
    {
        return self::$_dbh->prepare( $sql );
    }

    public static function count()
    {
        $class = get_called_class();
        $model = new $class;
        $sql = new Sql( $model->getTable() );
        $select = $sql->select('COUNT(*) as n_rows')->get();

        $row = $model->fetch( $select );

        if ( $row ) {
            return $row->n_rows;
        }
        return false;
    }

    public function insert( $data )
    {
        if ( $this->log_timestamp ) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $sql = new Sql( $this->getTable() );
        $sql->setPrepareMode();
        $insert = $sql->insert( $data )->get();
       
        $dbh = self::prepare( $insert );
        $res = $dbh->execute( $sql->values() );
    }

    public function update( $data )
    {
        if ( $this->log_timestamp ) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $sql = new Sql( $this->getTable() );
        $sql->setPrepareMode();
        $update = $sql->update( $data )->where( $this->_id, $this->_data[ $this->_id ] )->get();

        $dbh = self::prepare( $update );
        return $dbh->execute( $sql->values() );
    }

    public function delete()
    {
        if ( $this->soft_deletes ) {
            $data = [
                'deleted_at'    => date('Y-m-d H:i:s'),
            ];
            return $this->update( $data );
        } else {
            $sql = new Sql( $this->getTable() );
            $sql->setPrepareMode();
            $delete = $sql->delete()->where( $this->_id, $this->_data[ $this->_id ] )->get();

            $dbh = self::prepare( $delete );
            return $dbh->execute( $sql->values() );
        }
    }

    public function save()
    {
        $data = $this->toArray();

        if ( array_key_exists( $this->_id, $data ) ) {
            unset( $data[ $this->_id ] );
            return $this->update( $data );
        } else {
            return $this->insert( $data );
        }
    }

    public function lastInsertId()
    {
        return self::$_dbh->lastInsertId();
    }
}