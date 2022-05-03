<?php
namespace Slendie\Framework\Database;

class SQL 
{
    protected $table = "";
    protected $id_column = NULL;
    protected $pairs = [];
    protected $wheres = [];
    protected $whereRaw = "";
    protected $orderBy = "";
    protected $sql = "";
    protected $limit = 0;
    protected $offset = 0;

    public function __construct()
    {
    }

    public function setTable( $table )
    {
        $this->table = $table;
    }

    public function setIdColumn( $id_column )
    {
        $this->id_column = $id_column;
    }

    public function setPairs( $pairs )
    {
        $this->pairs = $pairs;
    }

    public function get()
    {
        return $this->sql;
    }
    
    public function update()
    {
        $sets = [];
        $newPairs = $this->convertPairs( $this->pairs );
        foreach( $newPairs as $key => $value ) {
            if ( $key !== $this->id_column ) {
                $sets[] = "{$key} = {$value}";
            } else {
                $this->where( $key, $value );
            }
        }
        $this->sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " " . $this->getWhere() . ";";
        return $this;
    }

    public function insert()
    {
        $newPairs = $this->convertPairs( $this->pairs );
        $this->sql = "INSERT INTO {$this->table} (" . implode(', ', array_keys( $newPairs )) . ") VALUES (" . implode(', ', array_values( $newPairs )) . ");";
        return $this;
    }

    public function select()
    {
        foreach( $this->wheres as $i => $where ) {
            $this->wheres[$i]['right'] = $this->format($where['right']);
        }

        $this->sql = "SELECT * FROM {$this->table} " . $this->getWhere();
        if ( $this->orderBy != "" ) {
            $this->sql .= " ORDER BY " . $this->orderBy;
        }
        $this->sql .= ( $this->limit > 0 ) ? " LIMIT {$this->limit}" : "";
        $this->sql .= ( $this->offset > 0 ) ? " OFFSET {$this->offset}" : "";
        $this->sql .= ";";
        
        return $this;
    }

    public function customSelect( string $select )
    {
        foreach( $this->wheres as $i => $where ) {
            $this->wheres[$i]['right'] = $this->format($where['right']);
        }

        $this->sql = "SELECT " . $select . " FROM {$this->table} " . $this->getWhere();
        if ( $this->orderBy != "" ) {
            $this->sql .= " ORDER BY " . $this->orderBy;
        }
        $this->sql .= ( $this->limit > 0 ) ? " LIMIT {$this->limit}" : "";
        $this->sql .= ( $this->offset > 0 ) ? " OFFSET {$this->offset}" : "";
        $this->sql .= ";";
        
        return $this;
    }

    public function delete()
    {
        foreach( $this->wheres as $i => $where ) {
            $this->wheres[$i]['right'] = $this->format($where['right']);
        }

        $this->sql = "DELETE FROM {$this->table} " . $this->getWhere() . ";";
        return $this;
    }
    
    public function save()
    {
        if ( array_key_exists( $this->id_column, $this->pairs )) {
            return $this->update()->get();
        } else {
            return $this->insert()->get();
        }
    }

    public function count( string $column_name = '*' )
    {
        $this->sql = "SELECT COUNT( $column_name ) AS num_rows FROM {$this->table} " . $this->getWhere();

        return $this;
    }

    public function getWhere()
    {
        $where = "";
        if ( !empty($this->whereRaw) )
        {
            $where .= " " . $this->whereRaw;
        }

        if ( count( $this->wheres ) == 1 ) {
            $this->wheres[0]['open'] = "";
            $this->wheres[0]['close'] = "";
        }

        $count = 0;
        foreach( $this->wheres as $where_cond ) {
            if ( $count > 0 && empty($where_cond['oper']) ) {
                $where_cond['oper'] = 'AND';
            }
            if ( $where_cond['comp'] == '=' && is_null($where_cond['right']) ) {
                $where .= " " . trim($where_cond['oper'] . " " . $where_cond['open'] . $where_cond['left'] . " IS NULL " . $where_cond['close']);
            } elseif ( $where_cond['comp'] == '<>' && is_null($where_cond['right']) ) {
                $where .= " " . trim($where_cond['oper'] . " " . $where_cond['open'] . $where_cond['left'] . " IS NOT NULL " . $where_cond['close']);
            } else {
                $where .= " " . trim($where_cond['oper'] . " " . $where_cond['open'] . $where_cond['left'] . " " . $where_cond['comp'] . " " . $where_cond['right'] . " " . $where_cond['close']);
            }
            $count++;
        }
        if ( !empty($where) ) {
            $where = "WHERE " . trim($where);
        }

        return $where;
    }

    public function where($left, $right, $oper = "AND", $comp = "=", $open = "(", $close = ")")
    {
        $where = [];
        $where['open'] = $open;
        $where['close'] = $close;
        if ( count($this->wheres) > 0  || !empty($this->whereRaw) ) {
            $where['oper'] = $oper;
        } else {
            $where['oper'] = "";
        }
        $where['left'] = $left;
        $where['comp'] = $comp;
        // $where['right'] = $this->encapsulate($right);
        $where['right'] = $right;
        $this->wheres[] = $where;
        return $this;
    }

    public function whereRaw( $where_clause )
    {
        $this->whereRaw = $where_clause;
    }

    public function whereAnd($left, $right)
    {
        $this->where($left, $right, "AND", "=");
        return $this;
    }

    public function whereOr($left, $right)
    {
        $this->where($left, $right, "OR", "=");
        return $this;
    }

    public function whereLike($left, $right, $oper = "AND")
    {
        $this->where($left, $right, $oper, "LIKE");
        return $this;
    }

    public function whereNot($left, $right)
    {
        $this->where($left, $right, "AND", "<>");
        return $this;
    }

    public function orderBy( $column, $direction = 'ASC' )
    {
        if ( $this->orderBy != "" ) {
            $this->orderBy .= ", ";
        }
        $this->orderBy .= $column . " " . $direction;
        return $this;
    }

    public function limit( $limit )
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset( $offset )
    {
        $this->offset = $offset;
        return $this;
    }

    private function format( $value )
    {
        if ( is_string($value) && !empty($value) ) {
            return "'" . addslashes($value) . "'";
            // return addslashes($value);

        } else if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';

        } else if ($value !== '') {
            return $value;

        } else {
            return "NULL";

        }
    }

    private function encapsulate( $value )
    {
        if ( is_numeric($value) ) {
            return $value;

        } elseif ( is_string($value) ) {
            return "'" . addslashes($value) . "'";

        // if (is_string($value) && !empty($value)) {
        //     return "'" . $value . "'";

        // } elseif (is_string($value) && empty($value)) {
        //     return "NULL";
    
        } else  {
            return $value;

        }
    }

    private function convertPairs( $sets )
    {
        $newPairs = [];
        foreach ( $sets as $key => $value ) {
            if ( is_scalar($value) ) {
                $newPairs[$key] = $this->format($value);
            // } else {
            //     $newPairs[$key] = $value;
            }
        }
        return $newPairs;
    }
}