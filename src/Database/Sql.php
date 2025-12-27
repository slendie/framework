<?php
namespace Slendie\Framework\Database;

class Sql
{
    private $table = null;
    private $alias = null;
    private $insert = null;
    private $select = null;
    private $update = null;
    private $delete = null;
    private $where = null;
    private $group = null;
    private $order = null;
    private $limit = null;
    private $offset = null;
    private $join = null;
    private $union = null;
    private $model = null;
    private $binds = [];
    private $is_opened = false;
    private $is_suppress_logic = false;

    public function __construct( $table = null, $alias = null )
    {
        $this->table( $table );
        $this->alias(  $alias );
    }

    public function table( $table )
    {
        $this->table = $table;
    }

    public function alias( $alias )
    {
        $this->alias = $alias;
    }

    public function model( $model )
    {
        $this->model = $model;
    }

    public function bind( $column, $value = null )
    {
        if ( is_array( $column ) ) {
            foreach( $column as $key => $value ) {
                $this->binds[$key] = $value;
            }
        } else {
            $this->binds[$column] = $value;
        }
        return $this;
    }

    private static function column( $column )
    {
        if ( strpos($column, '.') !== false ) {
            $parts = explode('.', $column);
            $final_column = "`{$parts[0]}`.`{$parts[1]}`";
        } else {
            if ( $column == '*' ) {
                $final_column = '*';
            } else {
                $final_column = "`{$column}`";
            }
        }

        return $final_column;
    }

    private static function value( $value )
    {
        if ( strpos( $value, '`') == 1 ) {
            $final_value = substr( $value, 1, -1 );
        } else {
            if ( is_numeric( $value ) ) {
                $final_value = $value;
            } else {
                $final_value = "'" . addslashes( $value ) . "'";
            }
        }

        return $final_value;
    }

    public function insert( $columns )
    {
        if ( !is_array( $columns ) ) {
            throw new \Exception( 'Insert must be an array' );
        }

        $this->insert = "INSERT INTO `{$this->table}`";
        $sets = '';
        $values = '';
        foreach( $columns as $column ) {
            if ( !empty( $sets ) ) {
                $sets .= ', ';
                $values .= ', ';
            }
            $sets .= self::column( $column );
            $values .= "?";
        }
        $this->insert .= " ({$sets}) VALUES ({$values})";
        return $this;
    }

    public function select( $select = ['*'] )
    {
        if ( !is_array( $select ) ) {
            throw new \Exception('Select must be an array');
        }

        $columns = '';
        foreach ( $select as $column ) {
            if ( !empty( $columns ) ) {
                $columns .= ', ';
            }
            $columns .= self::column( $column );
        }

        $this->select = "SELECT {$columns} FROM `{$this->table}`";

        if ( !is_null( $this->alias ) ) {
            $this->select .= " AS `{$this->alias}`";
        }

        return $this;
    }

    public function count()
    {
        $this->select = "SELECT COUNT(*) FROM `{$this->table}`";

        if ( !is_null( $this->alias ) ) {
            $this->select .= " AS `{$this->alias}`";
        }

        return $this;
    }

    public function update( $columns )
    {
        if ( !is_array( $columns ) ) {
            throw new \Exception('Update must be an array');
        }

        $this->update = "UPDATE `{$this->table}` SET ";
        $sets = '';
        foreach ( $columns as $column ) {
            if ( !empty( $sets ) ) {
                $sets .= ', ';
            }
            $sets .= self::column( $column) . " = ?";
        }
        $this->update .= $sets;

        return $this;
    }

    public function delete()
    {
        $this->delete = "DELETE FROM `{$this->table}`";
        return $this;
    }

    public function where( $column, $value = null, $operator = '=', $logic = 'AND' )
    {
        if ( empty( $this->where ) ) {
            $this->where = "WHERE ";
        } else {
            if ( !$this->is_suppress_logic ) {
                $this->where .= " {$logic} ";
            }
            $this->is_suppress_logic = false;
        }
        if ( is_null( $value ) ) {
            $this->where .= self::column( $column ) . " {$operator} :{$column}";
        } else {
            $this->where .= self::column( $column ) . " {$operator} " . self::value( $value );
        }

        return $this;
    }

    public function orWhere( $column, $value, $operator = '=' )
    {
        return $this->where( $column, $value, $operator, 'OR' );
    }

    public function open($logic = 'AND')
    {
        $this->is_opened = true;
        $this->is_suppress_logic = true;

        if ( empty( $this->where ) ) {
            $this->where = "WHERE ";
        } else {
            $this->where .= " {$logic} ";
        }
        $this->where .= "(";
        return $this;
    }

    public function orOpen()
    {
        return $this->open('OR');
    }

    public function close()
    {
        $this->is_opened = false;
        $this->is_suppress_logic = false;

        $this->where .= ")";
        return $this;
    }

    public function group( $group )
    {
        if ( is_array( $group ) ) {
            $groupmant = '';
            foreach( $group as $column ) {
                $groupmant .= self::column( $column ) . ", ";
            }
        } else {
            $groupmant = "`{$group}`";
        }
        $this->group = "GROUP BY {$groupmant}";
        return $this;
    }

    public function order( $order, $direction = 'ASC' )
    {
        if ( is_array( $order ) ) {
            $ordermant = '';
            foreach( $order as $column ) {
                $ordermant .= self::column( $column ) . " {$direction}, ";
            }
        } else {
            $ordermant = "`{$order}` $direction";
        }
        $this->order = "ORDER BY {$ordermant}";
        return $this;
    }

    public function limit( $limit )
    {
        $this->limit = "LIMIT {$limit}";
        return $this;
    }

    public function offset( $offset )
    {
        $this->offset = "OFFSET {$offset}";
        return $this;
    }

    public function union( $sql )
    {
        $this->union = "UNION {$sql}";
        return $this;
    }

    public function join( $table, $on, $join = 'JOIN', $alias = null )
    {
        $this->join = "{$join} `{$table}`";
        if ( !is_null( $alias ) ) {
            $this->join .= " AS `{$alias}`";
        }
        $on_condition = '';
        foreach( $on as $joined ) {
            if ( count( $joined ) == 2 ) {
                list($column1, $column2) = $joined;
                $operator  = '=';
            } else {
                list($column1, $column2, $operator) = $joined;
            }

            if ( empty( $on_condition ) ) {
                $on_condition = " ON ";
            } else {
                $on_condition .= " AND ";
            }
            $on_condition .= self::column( $column1 ) . " {$operator} " . self::column( $column2 );
        }
        $this->join .= $on_condition;
        return $this;
    }

    public function get()
    {
        $sql = '';

        /* STATEMENT */
        if ( empty( $this->insert ) && empty( $this->select ) && empty( $this->update ) && empty( $this->delete ) ) {
            $this->select();
        }

        if ( !is_null( $this->insert ) ) {
            $sql .= "{$this->insert} ";

        } elseif ( !is_null( $this->select ) ) {
            $sql .= "{$this->select} ";

        } elseif ( !is_null( $this->update ) ) {
            $sql .= "{$this->update} ";

        } elseif ( !is_null( $this->delete ) ) {
            $sql .= "{$this->delete} ";

        }

        /* JOIN */

        if ( !is_null( $this->join ) ) {
            $sql .= "{$this->join} ";
        }

        /* CONDITIONS */

        if ( !is_null( $this->where ) ) {
            $sql .= "{$this->where} ";

        }

        /* GROUPING */

        if ( !is_null( $this->group ) ) {
            $sql .= "{$this->group} ";
        }

        /* ORDERING */

        if ( !is_null( $this->order ) ) {
            $sql .= "{$this->order} ";
        }

        /* LIMIT AND OFFSET */

        if ( !is_null( $this->limit ) ) {
            $sql .= "{$this->limit} ";
        }

        if ( !is_null( $this->offset ) ) {
            $sql .= "{$this->offset} ";
        }

        /* UNION */

        if ( !is_null( $this->union ) ) {
            $sql .= "{$this->union} ";
        }

        if ( is_null( $this->model ) ) {
            return trim( $sql );
        } else {
            $this->model->setSql( $this );
            return $this->model->get();
        }
    }

    public function __call( $name, $arguments )
    {
        if ( is_null( $this->model ) ) {
            throw new \Exception( "Call to undefined method " . __CLASS__ . "::{$name}()" );
        }

        return $this->model->$name( ...$arguments );
    }
}