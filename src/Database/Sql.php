<?php
namespace Slendie\Framework\Database;

class Sql
{
    protected $mode = '';
    protected $table = '';
    protected $id = '';
    protected $tables = [];
    protected $sql = '';
    protected $where = '';
    protected $order = '';
    protected $group = '';
    protected $having = '';
    protected $offset = '';
    protected $limit = '';
    protected $data = [];

    public function __construct( $table ) 
    {
        $this->table = $table;
        $this->id = 'id';
    }

    /**
     * Reset all data.
     */
    public function reset()
    {
        $this->mode = '';
        $this->sql = '';
        $this->where = '';
        $this->order = '';
        $this->group = '';
        $this->having = '';
        $this->offset = '';
        $this->limit = '';
        $this->data = [];
    }

    /**
     * Build a SELECT statement
     */
    public function select($args = null)
    {
        $this->reset();
        $this->mode = "SELECT";

        $columns = '';
        if ( is_null($args) ) {
            $columns = '*';
        } else {
            if ( is_array($args) ) {
                $columns = implode(', ', array_map( 'self::encapsulate', $args ) );
            } else {
                $columns = $args;
            }
        }

        $this->sql = "SELECT {$columns} FROM " . self::encapsulate( $this->table ) . " ";
        return $this;
    }

    /**
     * Add a JOIN statement
     */
    public function join( $other_table, $array_on )
    {
        if ( $this->mode != "SELECT" ) {
            throw new \Exception("Necessário selcionar select() antes do join().");
            return false;
        }

        $this->sql .= " INNER JOIN " . self::encapsulate( $other_table ) . " ON ";
        
        $first = true;
        foreach( $array_on as $key1 => $key2 ) {
            if ( !$first ) {
                $this->sql .= "AND ";
            }
            $first = false;
            $this->sql .= self::encapsulate( $key1 ) . " = " . self::encapsulate( $key2 ) . " ";
        }
        return $this;
    }

    /**
     * Build the WHERE condition
     */
    public function where( $column, $value, $op = "=", $join = "AND" )
    {
        if ( empty( $this->where ) ) {
            $this->where .= "WHERE ";
        } else {
            $this->where .= "{$join} ";
        }

        $this->where .= self::encapsulate( $column ) . " ";
        if ( !is_null( $value ) ) {
            $this->where .= $op;
            $this->where .= " " . self::sanitize( $value );
        } else {
            if ( $op == '!=' || $op == 'IS NOT' ) {
                $this->where .= "IS NOT NULL";
            } else {
                $this->where .= "IS NULL";
            }
        }
        $this->where .= " ";

        return $this;
    }

    /**
     * Build the ORDER BY clause
     */
    public function order( $column, $order = 'ASC' ) 
    {
        if ( empty( $this->order ) ) {
            $this->order .= "ORDER BY ";
        } else {
            $this->order .= ", ";
        }
        $this->order .= self::encapsulate( $column ) . " {$order} ";

        return $this;
    }

    /**
     * Build the GROUP BY clause
     */
    public function group( $column )
    {
        if ( empty( $this->group ) ) {
            $this->group .= "GROUP BY ";
        } else {
            $this->group .= ", ";
        }
        $this->group .= self::encapsulate( $column ) . " ";

        return $this;
    }

    /**
     * Build the HAVING clause
     */
    public function having( $cond )
    {
        if ( empty( $this->having ) ) {
            $this->having .= "HAVING ";
        } else {
            $this->having .= "AND ";
        }
        $this->having .= "{$cond} ";

        return $this;
    }

    /**
     * Build the OFFSET clause
     */
    public function offset( $offset )
    {
        if ( empty( $this->offset ) ) {
            $this->offset .= "OFFSET {$offset} ";
        } else {
            throw new \Exception('Não pode definir mais do que 1 offset.');
        }

        return $this;
    }

    /**
     * Build the LIMIT clause
     */
    public function limit( $limit )
    {
        if ( empty( $this->limit ) ) {
            $this->limit .= "LIMIT {$limit} ";
        } else {
            throw new \Exception('Não pode definir mais do que 1 limit.');
        }

        return $this;
    }

    /**
     * Set data. It is used on build SQL statements.
     */
    public function setData( $array )
    {
        $this->data = $array;
    }

    /**
     * Set the ID column
     */
    public function setId( $id ) 
    {
        $this->id = $id;
    }

    /**
     * Build a INSERT statement. This build will be generate a prepare SQL statement.
     */
    public function insert()
    {
        $this->mode = "INSERT";

        $this->sql = "INSERT INTO " . self::encapsulate( $this->table ) . " (" . 
                implode(', ', array_map( 'self::encapsulate', array_keys( $this->data ) ) ) . 
                ") VALUES (" . 
                implode(', ', array_map( function ($v) { return ':' . $v; }, array_keys( $this->data ) ) ) . ") ";
        return $this;
    }

    /**
     * Build a UPDATE statement. This build will be generate a prepare SQL statement.
     */
    public function update()
    {
        $this->mode = "UPDATE";

        $this->sql = "UPDATE " . self::encapsulate( $this->table ) . " SET ";
        $sets = [];
        foreach( $this->data as $column => $value ) {
            if ( $column != $this->id ) {
                // $sets[] = self::encapsulate( $column ) . " = " . self::sanitize( $value );
                $sets[] = self::encapsulate( $column ) . " = :" . $column;
            }
        }
        $this->sql .= implode(', ', $sets) . " ";
        // echo "Sql::update->sql: {$this->sql}" . PHP_EOL;
        return $this;
    }
    
    /**
     * Build a DELETE statement.
     */
    public function delete()
    {
        $this->reset();
        $this->mode = "DELETE";

        $this->sql = "DELETE FROM " . self::encapsulate( $this->table ) . " ";
        return $this;
    }

    /**
     * Build a TRUNCATE statement.
     */
    public function truncate()
    {
        return "TRUNCATE TABLE " . self::encapsulate( $this->table ) . "; ";
    }
    
    /**
     * Check if there is an ID.
     * If so, then build an UPDATE statement.
     * If not, then build an INSERT statement.
     */
    public function save()
    {
        if ( array_key_exists( $this->id, $this->data ) ) {
            return $this->update()->get();
        } else {
            return $this->insert()->get();
        }
    }

    /**
     * Get mode property
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Get is like a 'run' to build SQL statement.
     */
    public function get()
    {
        $sql = $this->sql;
        if ( !empty( $this->where ) ) {
            $sql .= $this->where;
        }
        if ( !empty( $this->order ) ) {
            $sql .= $this->order;
        }
        if ( !empty( $this->group ) ) {
            $sql .= $this->group;
        }
        if ( !empty( $this->offset ) ) {
            $sql .= $this->offset;
        }
        if ( !empty( $this->limit ) ) {
            $sql .= $this->limit;
        }
        $sql .= ";";
        return $sql;
    }

    /**
     * Encapsulate item names, like table name and columns.
     */
    public static function encapsulate( $item )
    {
        return "`{$item}`";
    }

    /**
     * Sanitize data.
     */
    public static function sanitize( $value )
    {
        if ( is_string($value) && !empty($value) ) {
            return "'" . addslashes($value) . "'";

        } else if (is_bool($value)) {
            // return $value ? 'TRUE' : 'FALSE';
            return $value ? 1 : 0;

        } else if ( !empty( $value ) && !is_null( $value ) ) {
            return $value;

        } else {
            return "NULL";

        }
    }

}