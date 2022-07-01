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
            // throw new \Exception('Atributo ' . $key . ' inexistente na tabela ' . $this->getTable() );
            // HERE
            $this->_data[ $key ] = '';
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
     * Get a copy of current record
     */
    private function copy()
    {
        $class = get_called_class();
        $model = new $class;

        if ( isset( $this->{$this->_id} ) ) {
            $model = $class::find( $this->{$this->_id} );
        }

        return $model;
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

    private function setMeta()
    {
        /* Extract Meta info from table */
        $this->_sql = new Sql( $this->getTable() );
        $select = $this->_sql->select('1')->limit(1)->get();

        try {
            $statement = self::$_dbh->query( $select );
            $columns_count = $statement->columnCount();
        } catch (\Exception $e) {
            die('Table ' . $this->getTable() . ' does not exists.' );
            return false;
        }
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

        return true;
    }

    public function getMeta()
    {
        return $this->_meta;
    }

    public function getColumnName( $column )
    {
        return $this->getTable() . "." . $column;
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

    public function id()
    {
        return $this->{$this->_id};
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
        try {
            return self::$_dbh->fetchAll( $sql, get_called_class() );
        } catch (\Exception $e) {
            debug_print_backtrace();
            dd( $sql );
        }
    }

    public static function fetch( $sql )
    {
        try {
            return self::$_dbh->fetch( $sql, get_called_class() );
        } catch (\Exception $e) {
            debug_print_backtrace();
            dd( $sql );
        }
    }

    public static function fetchAssoc( $sql )
    {
        try {
            return self::$_dbh->fetch( $sql );
        } catch (\Exception $e) {
            debug_print_backtrace();
            dd( $sql );
        }
    }

    public static function all( $columns = '*' )
    {
        self::connect();

        $class = get_called_class();
        $model = new $class;

        $sql = new Sql( $model->getTable() );
        $select = $sql->select( $columns )->get();

        return self::fetchAll( $select );
    }

    public static function find( $id, $key = 'id' )
    {
        self::connect();

        $class = get_called_class();
        $model = new $class;

        $sql = new Sql( $model->getTable() );
        $select = $sql->select()->where( $key, $id )->get();

        return self::fetch( $select );
    }

    public function exec( $sql, $data )
    {
        $dbh = self::prepare( $sql );
        return $dbh->execute( $data );
    }

    /**
     * Find a child relationship, one-to-one
     */
    public function hasOne( $model, $related_column = '' )
    {
        $current = $this->copy();
        
        if ( empty( $related_column ) ) {
            $related_column = self::columnRelated( $current->getTable() );
        }

        $this->_sql = new Sql( $model->getTable() );
        $select = $this->_sql->select()->where( $related_column, $current->id() )->get();

        // return self::fetch( $select );
        $class = get_class( $model );
        return $class::fetch( $select );
    }

    /**
     * Find a parent relationship, one-to-one
     */
    public function belongsToOne( $model, $related_column = '' )
    {
        $current = $this->copy();
        
        if ( empty( $related_column ) ) {
            $related_column = self::columnRelated( $model->getTable() );
        }

        $this->_sql = new Sql( $model->getTable() );
        $select = $this->_sql->select()->where( $model->getId() , $current->{$related_column} )->get();

        // return self::fetch( $select );
        $class = get_class( $model );
        return $class::fetch( $select );
    }

    /**
     * Find a child relationship, one-to-many
     */
    public function hasMany( $model, $related_column = '', $order = '' )
    {
        $current = $this->copy();
        
        if ( empty( $related_column ) ) {
            $related_column = self::columnRelated( $current->getTable() );
        }

        $this->_sql = new Sql( $model->getTable() );
        $sql = $this->_sql->select()->where( $related_column, $current->id() );

        if ( empty( $order ) ) {
            $select = $this->_sql->get();
        } else {
            $select = $this->_sql->order( $order )->get();
        }
        

        // return self::fetchAll( $select );
        $class = get_class( $model );
        return $class::fetchAll( $select );
    }

    /**
     * Find a parent relationship, one-to-many
     */
    public function belongsToMany( $model, $related_column = '', $order = '' )
    {
        $current = $this->copy();
        
        if ( empty( $related_column ) ) {
            $related_column = self::columnRelated( $model->getTable() );
        }

        $this->_sql = new Sql( $model->getTable() );
        $sql = $this->_sql->select()->where( $model->getId() , $current->{$related_column} );

        if ( empty( $order ) ) {
            $select = $this->_sql->get();
        } else {
            $select = $this->_sql->order( $order )->get();
        }

        // return self::fetchAll( $select );
        $class = get_class( $model );
        return $class::fetchAll( $select );
    }

    public function manyToMany( $model, $related_column = NULL, $model_related_column = NULL, $link_table = NULL, $order = '' )
    {
        $current = $this->copy();
        $model_class = get_class( $model );
        $model = new $model_class;

        $this_table = $current->getTable();
        $model_table = $model->getTable();

        if ( is_null( $link_table ) ) {
            if ( $this_table < $model_table ) {
                $table = $this_table . '_' . $model_table;
            } else {
                $table = $model_table . '_' . $this_table;
            }
        } else {
            $table = $link_table;
        }

        if ( empty( $related_column ) ) {
            $related_column = self::columnRelated( $current->getTable() );
        }

        if ( empty( $model_related_column ) ) {
            $model_related_column = $model::columnRelated( $model->getTable() );
        }

        $columnsRaw = "`" . $model->getTable() . "`.*";
        $this->_sql = new Sql( $model->getTable() );
        $sql = $this->_sql->select( $columnsRaw )->join( $table, [ $model->getColumnName('id') => $model_related_column ] )->join( $this_table, [ $this->getColumnName('id') => $related_column ])->where( $this->getColumnName('id'), $this->id );

        if ( empty( $order ) ) {
            $select = $this->_sql->get();
        } else {
            $select = $this->_sql->order( $order )->get();
        }

        return $model->fetchAll( $select );
    }

    public static function prepare( $sql )
    {
        try {
            return self::$_dbh->prepare( $sql );
        } catch (\Exception $e) {
            debug_print_backtrace();
            dd( $sql );
        }
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

        $this->_sql = new Sql( $this->getTable() );
        $this->_sql->setPrepareMode();
        $insert = $this->_sql->insert( $data )->get();
       
        return $this->exec( $insert, $this->_sql->values() );
    }

    public function update( $data )
    {
        if ( $this->log_timestamp ) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $this->_sql = new Sql( $this->getTable() );
        $this->_sql->setPrepareMode();
        $update = $this->_sql->update( $data )->where( $this->_id, $this->_data[ $this->_id ] )->get();

        return $this->exec( $update, $this->_sql->values() );
    }

    public function delete()
    {
        if ( $this->soft_deletes ) {
            $data = [
                'deleted_at'    => date('Y-m-d H:i:s'),
            ];
            return $this->update( $data );
        } else {
            $this->_sql = new Sql( $this->getTable() );
            $this->_sql->setPrepareMode();
            $delete = $this->_sql->delete()->where( $this->_id, $this->_data[ $this->_id ] )->get();

            return $this->exec( $delete, $this->_sql->values() );
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

    public static function where( $column, $value )
    {
        $class = get_called_class();
        $model = new $class;

        $model->_sql = new Sql( $model->getTable() );
        $model->_sql->select()->where( $column, $value );

        return $model;
    }

    public function lastSql()
    {
        return $this->_sql->get();
    }
}