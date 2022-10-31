<?php
namespace Slendie\Framework\Database;

class Sql
{
    private static $model = null;
    private static $insert = null;
    private static $select = null;
    private static $update = null;
    private static $delete = null;
    private static $table = null;
    private static $where = null;
    private static $group = null;
    private static $order = null;
    private static $limit = null;
    private static $offset = null;
    private static $join = null;
    private static $values = null;

    public function __construct( $model = null )
    {
        static::$model = $model;
    }

    public static function insert()
    {
        if ( is_null( static::$model->insert ) ) {
            static::$model->insert = 'INSERT INTO';
        }
        return static::$model;
    }

    public static function select( $select = '*' )
    {
        if ( is_array( $select ) ) {
            static::$model->select = "SELECT " . implode( ',', $select ) . " FROM";
        } else {
            static::$model->select = "SELECT {$select} FROM";
        }
        
        return static::$model;
    }

    public static function update( $update )
    {
        if ( is_null( static::$model->update ) ) {
            static::$model->update = [];
        }

        if ( is_array( $update ) ) {
            foreach ( $update as $column => $value ) {
                static::$model->update[] = [
                    'column' => $column,
                    'value' => $value
                ];
            }
        }
        return static::$model;
    }

    public static function delete()
    {
        if ( is_null( static::$model->delete ) ) {
            static::$model->delete = "DELETE FROM";
        }
    }

    public static function table( $table )
    {
        static::$model->table = $table;
        return static::$model;
    }

    public static function where( $column, $value, $operator = '=' )
    {
        if ( is_null( static::$model->where ) ) {
            static::$model->where = [];
        }

        static::$model->where[] = [
            'column' => $column,
            'value' => $value,
            'operator' => $operator
        ];

        return static::$model;
    }

    public static function group( $group )
    {
        if ( is_null( static::$model->group ) ) {
            static::$model->group = [];
        }

        if ( is_array( $group ) ) {
            foreach( $group as $g ) {
                static::$model->group[] = $g;
            }
        } else {
            static::$model->group[] = $group;
        }

        return static::$model;
    }

    public static function order( $order, $direction = 'ASC' )
    {
        if ( is_null( static::$model->order ) ) {
            static::$model->order = [];
        }

        if ( is_array( $order ) ) {
            foreach( $order as $column => $direction ) {
                static::$model->order[] = [
                    'column' => $column,
                    'direction' => $direction
                ];
            }
        } else {
            static::$model->order[] = [
                'column' => $order,
                'direction' => $direction
            ];
        }

        return static::$model;
    }

    public static function limit( $limit )
    {
        static::$model->limit = $limit;
        return static::$model;
    }

    public static function offset( $offset )
    {
        static::$model->offset = $offset;
        return static::$model;
    }

    public static function join( $join )
    {
        if ( is_null( static::$model->join ) ) {
            static::$model->join = [];
        }
        static::$model->join[] = $join;
        return static::$model;
    }

    public static function values( $values )
    {
        if ( is_null( static::$model->values ) ) {
            static::$model->values = [];
        }

        if ( is_array( $values ) ) {
            foreach ( $values as $column => $value ) {
                static::$model->values[] = [
                    'column' => $column,
                    'value' => $value
                ];
            }
        }
    }

    public static function get()
    {
        $sql = '';

        /* STATEMENT */

        if ( !is_null( static::$model->insert ) ) {
            $sql .= "{static::$model->insert} {static::$model->table}";

        } else if ( !is_null( static::$model->select ) ) {
            $sql .= "{static::$model->select} {static::$model->table}";

        } else if ( !is_null( static::$model->update ) ) {
            $sql .= "UPDATE {static::$model->table} SET ";

            foreach ( static::$model->update as $updateItem ) {
                $sql .= "{$updateItem['column']} = '{$updateItem['value']}', ";
            }

            $sql = rtrim( $sql, ', ' );

        } else if ( !is_null( static::$model->delete ) ) {
            $sql .= "{static::$model->delete} {static::$model->table}";

        }

        /* CONDITIONS */

        if ( !is_null( static::$model->where ) ) {
            $where = '';
            foreach( static::$model->where as $whereItem ) {
                if ( empty( $where ) ) {
                    $where = "WHERE ";
                } else {
                    $where .= " AND ";
                }
                $where .= "{$whereItem['column']} {$whereItem['operator']} '{$whereItem['value']}'";
            }
            $sql .= " {$where}";
        }

        /* GROUPING */

        if ( !is_null( static::$model->group ) ) {
            $sql .= " GROUP BY " . implode( ',', static::$model->group );
        }

        /* ORDERING */

        if ( !is_null( static::$model->order ) ) {
            $order = '';
            foreach( static::$model->order as $orderItem ) {
                if ( empty( $order ) )  {
                    $order = "ORDER BY ";
                } else {
                    $order .= ", ";
                }
                $order .= "{$orderItem['column']} {$orderItem['direction']}";
            }

            $sql .= " {$order}";
        }

        /* LIMIT AND OFFSET */

        if ( !is_null( static::$model->limit ) ) {
            $sql .= " LIMIT {static::$model->limit}";
        }

        if ( !is_null( static::$model->offset ) ) {
            $sql .= " OFFSET {static::$model->offset}";
        }

        /* JOIN */

        if ( !is_null( static::$model->join ) ) {
            foreach( static::$model->join as $join ) {
                $sql .= " {$join}";
            }
        }

        /* VALUES */

        if ( !is_null( static::$model->insert ) && !is_null( static::$model->values ) ) {
            $columns = "";
            $values = "";
            foreach( static::$model->values as $valueItem ) {
                if ( empty( $columns ) ) {
                    $columns = "(";
                } else {
                    $columns = ", ";
                }
                if ( empty( $values ) ) {
                    $values = "(";
                } else {
                    $values = ", ";
                }
                $columns = "`{$valueItem['column']}`";
                $values = "'{$valueItem['value']}'";
            }
            $sql .= " {$columns}) VALUES {$values})";

        }
    }
}