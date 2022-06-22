<?php
namespace Slendie\Framework\Database;

use PDO;

class Sql
{
    protected $tables = [];
    protected $table_index = -1;

    protected $statement = '';
    protected $where = '';
    protected $group = '';
    protected $order = '';
    protected $having = '';
    protected $limit = '';
    protected $offset = '';

    protected $bindables = [];
    protected $is_prepare_mode = false;
    protected $opened = 0;
    protected $open_waiting = 0;

    public function __construct( $table )
    {
        $this->table( $table );
    }

    public function table( $table )
    {
        if ( is_array( $table ) ) {
            foreach( $table as $name => $alias ) {
                $this->table_index++;

                $this->tables[ $this->table_index ] = 
                [
                    'table' => $name,
                    'as'    => $alias
                ];
            }
        } else {
            $this->table_index++;

            $this->tables[ $this->table_index ] = 
            [
                'table' => $table,
                'as'    => $table
            ];
        }
        return $this;
    }

    /* *** Auxiliary functions *** */
    
    /**
     * WHERE condition
     */
    private function buildWhere( $column, $value, $operand, $logical = 'AND', $negate = false)
    {
        if ( empty( $this->where ) ) {
            $this->where = "WHERE ";
        } else {
            $this->where .= "{$logical} ";
        }

        while( $this->open_waiting > 0 ) {
            $this->where .= "( ";
            $this->open_waiting--;
        }

        if ( $negate ) {
            $this->where .= " NOT (";
        }

        $this->where .= self::encapsulate( $column );
        $this->where .= " {$operand} ";

        if ( $this->is_prepare_mode ) {
            $this->where .= "? ";
            $this->bind( $column, $value );
        } else {
            $this->where .= self::sanitize( $value );
        }

        if ( $negate ) {
            $this->where .= ") ";
        }

        $this->where .= " ";
    }

    private function selectColumn( $column ) 
    {
        if ( preg_match( '/\./', $column ) ) {
            return self::encapsulate( $column );
        } else {
            return self::encapsulate( $this->currentAlias() . ".{$column}" );
        }
    }

    private function getFrom()
    {
        $from = "FROM " . self::encapsulate( $this->currentTable() ) . " ";
        if ( $this->currentTable() != $this->currentAlias() ) {
            $from .= "AS " . self::encapsulate( $this->currentAlias() ) . " ";
        }
        return $from;
    }

    private function currentTable()
    {
        return $this->tables[ $this->table_index ]['table'];
    }

    private function currentAlias()
    {
        return $this->tables[ $this->table_index ]['as'];
    }

    public function where( $column, $value, $operand = '=' )
    {
        $this->buildWhere( $column, $value, $operand, 'AND');
        return $this;
    }

    private static function encapsulate( $object ) 
    {
        if ( preg_match('/\./', $object) ) {
            $parts = explode('.', $object);
            return "`{$parts[0]}`.`{$parts[1]}`";
        } else {
            return "`{$object}`";
        }
    }

    private static function sanitize( $value )
    {
        if ( is_string($value) && !empty($value) && !is_numeric($value) ) {
            return "'" . htmlentities($value, ENT_QUOTES) . "'";

        } else if ( is_bool($value) || ( is_numeric($value) && ( $value == '0' || $value == '1' ) ) ) {
            return $value ? 1 : 0;

        } else if ( !empty( $value ) && !is_null( $value ) ) {
            return $value;

        } else if ( empty( $value ) ) {
            return "''";
            
        } else {
            return "NULL";
        }
    }

    private static function getType( $value )
    {
        if ( is_string($value) && !empty($value) && !is_numeric($value) ) {
            return PDO::PARAM_STR;

        } else if ( is_bool($value) || ( is_numeric($value) && ( $value == '0' || $value == '1' ) ) ) {
            return PDO::PARAM_BOOL;

        } else if ( !empty( $value ) && !is_null( $value ) ) {
            if ( is_int( $value ) ) {
                return PDO::PARAM_INT;
            } else {
                return PDO::PARAM_STR;
            }
        } else if ( empty( $value ) ) {
            return PDO::PARAM_STR;
            
        } else {
            return PDO::PARAM_NULL;
        }
    }

    private function bind( $column, $value )
    {
        $this->bindables[] = [
            $column => $value
        ];
    }

    /**
     * Build a SELECT statement
     */
    public function select( $args = '*' )
    {
        if ( is_array( $args ) ) {
            $columns = implode(', ', array_map( array($this, 'selectColumn') , $args ) );
        } else {
            $columns = $args;
        }
        $this->statement = "SELECT {$columns} " . $this->getFrom();
        return $this;
    }

    /**
     * Build a JOIN statement
     */
    public function join( $table, $args, $join_type = 'INNER' )
    {
        $this->table( $table );
        $this->statement .= $join_type . " JOIN " . self::encapsulate( $this->currentTable() ) . " ";
        if ( $this->currentTable() != $this->currentAlias() ) {
            $this->statement .= "AS " . self::encapsulate( $this->currentAlias() ) . " ";
        }
        $this->statement .= "ON ";

        $first = true;
        foreach( $args as $column_1 => $column_2 ) {
            if ( !$first ) {
                $this->statement .= "AND ";
            }
            $first = false;
            $this->statement .= self::encapsulate( $column_1 ) . " = " . self::encapsulate( $column_2 ) . " ";
        }

        return $this;
    }

    /**
     * Build a INSERT statement. This build will be generate a prepare SQL statement.
     */
    public function insert( $data )
    {
        $this->statement = "INSERT INTO " . self::encapsulate( $this->currentTable() ) . " (" . 
                implode(', ', array_map( 'self::encapsulate', array_keys( $data ) ) ) . 
                ") VALUES (";

        if ( $this->is_prepare_mode ) {
            $values = implode(', ', array_map( function ($v) { 
                return '?'; 
            }, $data ) );
            foreach( $data as $column => $value ) {
                $this->bind( $column, $value );
            }
        } else {
            $values = implode(', ', array_map( function ($v) { return self::sanitize( $v ); }, $data ) );
        }
        $this->statement .= "{$values} ) ";
        return $this;
    }

    /**
     * Build a UPDATE statement. This build will be generate a prepare SQL statement.
     */
    public function update( $data )
    {
        $this->statement = "UPDATE " . self::encapsulate( $this->currentTable() ) . " SET ";
        $sets = [];
        foreach( $data as $column => $value ) {
            if ( $this->is_prepare_mode ) {
                $sets[] = self::encapsulate( $column ) . " = ?";
                $this->bind( $column, $value );
            } else {
                $sets[] = self::encapsulate( $column ) . " = " . self::sanitize( $value );
            }
        }
        $this->statement .= implode(', ', $sets) . " ";
        return $this;
    }
    
    public function updateRaw( $text )
    {
        $this->statement = "UPDATE " . self::encapsulate( $this->currentTable() ) . " SET ";
        $this->statement .= $text;
        return $this;
    }
    
    /**
     * Build a DELETE statement.
     */
    public function delete()
    {
        $this->statement = "DELETE FROM " . self::encapsulate( $this->currentTable() );
        return $this;
    }

    /**
     * Build a TRUNCATE statement.
     */
    public function truncate()
    {
        return "TRUNCATE TABLE " . self::encapsulate( $this->currentTable() ) . "; ";
    }
    


    public function whereOr( $column, $value, $operand = '=' )
    {
        $this->buildWhere( $column, $value, $operand, 'OR');
        return $this;
    }

    public function whereNot( $column, $value, $operand = '=' )
    {
        $this->buildWhere( $column, $value, $operand, 'AND', true);
        return $this;
    }

    public function whereOrNot( $column, $value, $operand = '=' )
    {
        $this->buildWhere( $column, $value, $operand, 'OR', true);
        return $this;
    }

    public function open()
    {
        // $this->where .= "( ";
        $this->opened++;
        $this->open_waiting++;
        return $this;
    }

    public function close()
    {
        if ( $this->opened == 0 ) {
            throw new \Exception('Não existem condições abertas para fechar.');
        }

        $this->where .= ") ";
        $this->opened--;
        return $this;
    }
 
    /**
     * Build GROUP BY
     */
    public function group( $args )
    {
        $this->group = "GROUP BY ";

        if ( is_array( $args ) ) {
            $columns = implode(', ', $args);
        } else {
            $columns = $args;
        }

        $this->group .= "{$columns} ";
        return $this;
    }

    /**
     * Build ORDER BY
     */
    public function order( $args, $order = 'ASC' )
    {
        if ( empty($this->order) ) {
            $this->order = "ORDER BY ";
        } else {
            $this->order .= ", ";
        }
        if ( is_array( $args ) ) {
            $first = true;
            foreach( $args as $column_name => $column_order ) {
                if ( empty( $column_order ) ) {
                    $column_order = $order;
                }
                if ( !$first ) {
                    $this->order .= ", ";
                }
                $first = false;
                $this->order .= "{$column_name} {$column_order} ";
            }
        } else {
            $this->order .= "{$args} {$order} ";
        }
        return $this;
    }

    /**
     * Build HAVING
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
     * Build OFFSET
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
     * Build LIMIT
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

    public function get()
    {
        $sql = '';
        $sql .= $this->statement;
        $sql .= $this->where;
        while ( $this->opened > 0 ) {
            $this->close();
        }
        $sql .= $this->order;
        $sql .= $this->group;
        $sql .= $this->having;
        $sql .= $this->limit;
        $sql .= $this->offset;
        $sql .= ";";

        return $sql;
    }

    public function values()
    {
        $values = [];
        foreach( $this->bindables as $i => $data ) {
            foreach( $data as $column => $value ) {
                $values[$i] = $value;
            }
        }
        return $values;
    }

    public function setPrepareMode( $status = true )
    {
        $this->is_prepare_mode = $status;
    }
}