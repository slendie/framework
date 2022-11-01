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
            $sets .= "`{$column}`";
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
            if ( $column != '*' ) {
                $columns .= "`{$column}`";
            } else {
                $columns .= $column;
            }
        }

        $this->select = "SELECT {$columns} FROM `{$this->table}`";

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
            $sets .= "`{$column}` = ?";
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
            $this->where .= "`{$column}` {$operator} :{$column}";
        } else {
            if ( is_numeric( $value ) ) {
                $this->where .= "`{$column}` {$operator} {$value}";
            } else {
                $this->where .= "`{$column}` {$operator} '{$value}'";
            }
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
                $groupmant .= "`{$column}`, ";
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
                $ordermant .= "`{$column}` {$direction}, ";
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
        $this->union = " UNION {$sql}";
        return $this;
    }

    public function join( $table, $on, $join = 'JOIN', $alias = null, $operator = '=' )
    {
        $this->join = "{$join} `{$table}`";
        if ( !is_null( $alias ) ) {
            $this->join .= " AS `{$alias}`";
        }
        foreach( $on as $key => $value ) {
            $this->join .= " ON `{$key}` {$operator} `{$value}`";
        }
        return $this;
    }

    public function get()
    {
        $sql = '';

        /* STATEMENT */

        if ( !is_null( $this->insert ) ) {
            $sql .= "{$this->insert} ";

        } elseif ( !is_null( $this->select ) ) {
            $sql .= "{$this->select} ";

        } elseif ( !is_null( $this->update ) ) {
            $sql .= "{$this->update} ";

        } elseif ( !is_null( $this->delete ) ) {
            $sql .= "{$this->delete} ";

        }

        /* UNION */

        if ( !is_null( $this->union ) ) {
            $sql .= "{$this->union} ";
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

        return trim( $sql );
    }
}