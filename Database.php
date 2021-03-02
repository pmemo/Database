<?php

class Database
{
    protected static $pdo = null;
    private static $options = [
        'errorReporting' => false
    ];

    private $table = '';
    private $data = [];
    private $dataBinds = [];
    private $columns = [];
    private $joins = [];
    public $conditions = [];
    public $conditionBinds = [];
    private $orders = [];
    private $groupBy = [];
    private $having = [];
    private $limit = null;
    private $offset = null;

    public function __construct($table = '')
    {
        if ($table) {
            $this->table = $table;
        }
    }

    public static function connect($dsn, $user = '', $pass = '') {
        self::$pdo = new PDO($dsn, $user, $pass);
        self::$pdo ->setAttribute(PDO::ATTR_ERRMODE, self::$options['errorReporting'] ? PDO::ERRMODE_EXCEPTION : PDO::ERRMODE_SILENT);
    }

    public static function getPDO() {
        return self::$pdo;
    }

    public static function config($name, $value)
    {
        self::$options[$name] = $value;
    }

    public function set($column, $value = null)
    {
        $data = is_array($column) ? $column : [$column => $value];
        $this->data += $data;
        return $this;
    }

    public function save($column = null, $value = null)
    {
        if ($column) {
            $this->set($column, $value);
        }
    
        if (self::$options['validation']) {
            if ($this->validate()) {
                $st = $this->_build();
                return self::$pdo->lastInsertId() ? self::$pdo->lastInsertId() : -1;
            }
        } else {
            $st = $this->_build();
            return self::$pdo->lastInsertId();
        }

        return;
    }

    public function select($columns)
    {
        $columns = !is_array($columns) ? [$columns] : $columns;

        foreach ($columns as $alias => $column) {
            if (!is_numeric($alias)) {
                $column .= " AS $alias";
            }

            array_push($this->columns, $column);
        }
        return $this;
    }

    public function delete($column = null, $value = null)
    {
        if ($column !== null) {
            $this->where($column, $value);
        }
        $st = $this->_build(['delete' => true]);
        return $st->rowCount();
    }

    public function count()
    {
        $st = $this->_build(array('count' => true));
        return $st->fetchColumn();
    }

    public function toArray()
    {
        $st = $this->_build();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function oneArray($column = null, $value = null)
    {
        if ($column !== null) {
            $this->where($column, $value);
        }
        $st = $this->_build();
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function oneArrayValues($column = null, $value = null)
    {
        if ($column !== null) {
            $this->where($column, $value);
        }
        $st = $this->_build();
        return array_values($st->fetch(PDO::FETCH_ASSOC));
    }

    public function toJson()
    {
        $rows = $this->toArray();
        return json_encode($rows);
    }

    public function oneJson($column = null, $value = null)
    {
        if ($column !== null) {
            $this->where($column, $value);
        }
        $row = $this->oneArray();
        return json_encode($row);
    }

    # INNER JOIN
    public function join($table, $condition)
    {
        array_push($this->joins, "INNER JOIN $table ON $condition");
        return $this;
    }

    # LEFT OUTER JOIN
    public function leftJoin($table, $condition)
    {
        array_push($this->joins, "LEFT JOIN $table ON $condition");
        return $this;
    }

    public function group($columns)
    {
        if (is_array($columns)) {
            foreach ($columns as $column) {
                array_push($this->groupBy, "$column");
            }
        } else {
            array_push($this->groupBy, "$columns");
        }
        return $this;
    }

    public function having($function, $operator, $value = null, $ao = 'AND')
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        if (is_array($value)) {
            $qs = '(' . implode(',', array_fill(0, count($value), '?')) . ')';
            if (empty($this->having)) {
                array_push($this->having, "$function $operator $qs");
            } else {
                array_push($this->having, "$ao $function $operator $qs");
            }
            foreach ($value as $v) {
                array_push($this->conditionBinds, $v);
            }
        } else {
            if (empty($this->having)) {
                array_push($this->having, "$function $operator ?");
            } else {
                array_push($this->having, "$ao $function $operator ?");
            }
            array_push($this->conditionBinds, $value);
        }
        return $this;
    }

    public function orHaving($function, $operator, $value = null)
    {
        return $this->having($function, $operator, $value, 'OR');
    }

    public function asc($column)
    {
        array_push($this->orders, "$column ASC");
        return $this;
    }

    public function desc($column)
    {
        array_push($this->orders, "$column DESC");
        return $this;
    }

    public function limit($num)
    {
        $this->limit = " LIMIT $num";
        return $this;
    }

    public function offset($num)
    {
        $this->offset = " OFFSET $num";
        return $this;
    }

    # WHERE =
    public function where($column, $value)
    {
        $this->_where($column, '=', $value);
        return $this;
    }

    # WHERE <>
    public function whereNot($column, $value)
    {
        $this->_where($column, '<>', $value);
        return $this;
    }

    # WHERE >
    public function whereGt($column, $value)
    {
        $this->_where($column, '>', $value);
        return $this;
    }

    # WHERE >=
    public function whereGte($column, $value)
    {
        $this->_where($column, '>=', $value);
        return $this;
    }

    # WHERE <
    public function whereLt($column, $value)
    {
        $this->_where($column, '<', $value);
        return $this;
    }

    # WHERE <=
    public function whereLte($column, $value)
    {
        $this->_where($column, '<=', $value);
        return $this;
    }

    # WHERE LIKE
    public function whereLike($column, $value)
    {
        $this->_where($column, 'LIKE', $value);
        return $this;
    }

    # WHERE NOT LIKE
    public function whereNotLike($column, $value)
    {
        $this->_where($column, 'NOT LIKE', $value);
        return $this;
    }

    # WHERE IN
    public function whereIn($column, $values)
    {
        $this->_where($column, 'IN', $values);
        return $this;
    }

    # WHERE NOT IN
    public function whereNotIn($column, $values)
    {
        $this->_where($column, 'NOT IN', $values);
        return $this;
    }

    public function _where($column, $separator, $value)
    {
        if (is_array($value)) {
            $qs = '(' . implode(',', array_fill(0, count($value), '?')) . ')';
            array_push($this->conditions, "$column $separator $qs");
            foreach ($value as $v) {
                array_push($this->conditionBinds, $v);
            }
        } else {
            array_push($this->conditions, "$column $separator ?");
            array_push($this->conditionBinds, $value);
        }

        return $this;
    }

    protected function _build($params = [])
    {
        $sql = '';
        $sqlCondition = '';
        $sqlHaving = '';

        $conditions = implode(' AND ', $this->conditions);
        if ($conditions) {
            $sqlCondition .= " WHERE $conditions";
        }

        $having = implode(' ', $this->having);
        if ($having) {
            $sqlHaving .= " HAVING $having";
        }

        if ($this->data) {
            $insert = true;
            if ($this->conditions) {
                # UPDATE
                $insert = false;
                $columns = implode('=?,', array_keys($this->data)).'=?';
                $this->dataBinds = array_values($this->data);
                $sql = "UPDATE $this->table SET $columns";
                $sql .= $sqlCondition;
                $st = $this->_query($sql);
            }

            if ($insert) {
                # INSERT
                $columns = implode(',', array_keys($this->data));
                $this->dataBinds = array_values($this->data);
                $qs = implode(',', array_fill(0, count($this->data), '?'));
                $sql = "INSERT INTO $this->table($columns) VALUES($qs);";
                $this->conditionBinds = [];
                $st = $this->_query($sql);
            }
        } else {
            if (!empty($params['delete'])) {
                # DELETE
                $sql = "DELETE FROM $this->table";
                $sql .= $sqlCondition;
                $st = $this->_query($sql);
            } else {
                # SELECT
                $columns = implode(',', $this->columns);
                if (!$columns) {
                    $columns = '*';
                }

                if (!empty($params['count'])) {
                    $columns = "COUNT($columns) AS count";
                }

                $sql = "SELECT $columns FROM $this->table";
                $joins = implode(' ', $this->joins);
                if ($joins) {
                    $sql .= " $joins";
                }
                $order = '';
                if (count($this->orders) > 0) {
                    $order = ' ORDER BY ' . implode(',', $this->orders);
                }
                $group_by = '';
                if (count($this->groupBy) > 0) {
                    $group_by = ' GROUP BY ' . implode(',', $this->groupBy);
                }

                $sql .= $sqlCondition . $group_by . $order . $sqlHaving . $this->limit . $this->offset;
                $st = $this->_query($sql);
            }
        }
        return $st;
    }

    protected function _query($sql)
    {
        $binds = array_merge($this->dataBinds, $this->conditionBinds);
        $st = self::$pdo->prepare($sql);
        $st->execute($binds);
        return $st;
    }
}

function Database($table)
{
    return new Database($table);
}
