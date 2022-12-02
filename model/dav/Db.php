<?php

/**
 * Class HttpsDav_Db
 */
abstract class Dav_Db
{
    protected static $_obj;
    protected static $_db = null;
    protected $_tbl = '';
    protected $_sql = [
        'SELECT'   => '*',
        'FROM'     => '',
        'WHERE'    => '',
        'ORDER BY' => '',
        'LIMIT'    => '',
    ];

    /**
     * Sqlite_Db constructor.
     * @throws Exception
     */
    protected function __construct()
    {
        self::conn();
        $this->_sql['FROM'] = &$this->_tbl;
        $this->init();
    }

    /**
     * Sqlite_Db constructor.
     * @throws Exception
     */
    private static function conn()
    {
        if (!(self::$_db instanceof Db_Sqlite_Conn)) {
            self::$_db = Db_Sqlite_Conn::getInstance();
        }
    }

    /**
     * @return mixed
     */
    abstract protected function init();

    /**
     * @return mixed
     * @throws Exception
     */
    public static function getInstance()
    {
        if (!(static::$_obj instanceof static)) {
            static::$_obj = new static();
        }
        return static::$_obj;
    }

    /**
     * @param string|array $fields
     * @param array $conditions
     * @return array
     * @throws Exception
     */
    public function select($fields, $conditions = [])
    {
        $this->_sql['SELECT'] = is_string($fields) ? $fields : implode(',', $fields);
        if (!empty($conditions)) {
            $this->getConditions($conditions);
        }
        $sql = $this->getSql();
        return $this->query($sql);
    }

    /**
     * @param array $conditions
     */
    public function getConditions(array $conditions)
    {
        $conds = array_intersect_key($conditions, $this->_sql);
        if (empty($conds)) {
            $this->_sql['WHERE'] = $conditions;
        } else {
            $this->_sql = array_merge($this->_sql, $conditions);
        }
        if (!empty($this->_sql['WHERE'])) {
            $this->_sql['WHERE'] = self::getWhere($this->_sql['WHERE']);
        }
    }

    /**
     * @param string|array $conditions
     * @param string $andOr
     * @return string
     */
    public static function getWhere($conditions, $andOr = 'AND')
    {
        if (is_string($conditions)) {
            return $conditions;
        }
        if (count($conditions) == 1 && isset($conditions[0])) {
            return self::getWhere($conditions[0], $andOr);
        }
        if (isset($conditions[0]) && is_array($conditions[0]) && isset($conditions[1]) && is_string($conditions[1])) {
            $op = strtoupper(trim($conditions[1]));
            if (in_array($op, ['AND', 'OR'])) {
                return self::getWhere($conditions[0], $op);
            }
        }
        foreach ($conditions as $k => $v) {
            if (!is_numeric($v) && !is_array($v)) {
                $v = trim($v);
            }
            if (is_numeric($k)) {
                $conditions[$k] = is_string($v) ? $v : self::getWhere($v);
            } else {
                if (is_numeric($v)) {
                    $conditions[$k] = $k . $v;
                } else {
                    if (is_array($v)) {
                        self::escapeData($v);
                        $conditions[$k] = $k . ' (' . implode(',', $v) . ')';
                    } else {
                        $conditions[$k] = $k . "'" . self::$_db->escape($v) . "'";
                    }
                }
            }
        }
        return '(' . implode(') ' . $andOr . ' (', $conditions) . ')';
    }

    /**
     * @return array|string
     */
    public function getSql()
    {
        $sql = [];
        if (!empty($this->_sql['WHERE']) && is_array($this->_sql['WHERE'])) {
            $this->_sql['WHERE'] = self::getWhere($this > $this->_sql['WHERE']);
        }
        foreach ($this->_sql as $k => $v) {
            if (!empty($v)) {
                $sql[] = $k . ' ' . (is_array($v) ? implode(',', $v) : $v);
            }
        }
        return implode(' ', $sql);
    }

    /**
     * @param mixed $sql
     * @return array|mixed
     * @throws Exception
     */
    public static function query($sql)
    {
        return self::$_db->query($sql);
    }

    /**
     * @param $conditions
     * @param string $andOr
     * @return $this
     */
    public function where($conditions, $andOr = 'AND')
    {
        $this->_sql['WHERE'] = self::getWhere($conditions, $andOr);
        return $this;
    }

    /**
     * @param string|array|null $fields
     * @return array|mixed
     * @throws Exception
     */
    public function getAll($fields = null)
    {
        if (!empty($fields)) {
            $this->_sql['SELECT'] = is_array($fields) ? implode($fields) : $fields;
        }
        $sql = $this->getSql();
        return $this->query($sql);
    }

    /**
     * @param array|null $fields
     * @param array $conditions
     * @return array
     * @throws Exception
     */
    public function getRow($fields = null, array $conditions = [])
    {
        if (!empty($fields)) {
            $this->_sql['SELECT'] = is_array($fields) ? implode(',', $fields) : $fields;
        }
        if (!empty($conditions)) {
            $this->getConditions($conditions);
        }
        $sql = $this->getSql();
        return self::$_db->getRow($sql);
    }

    /**
     * @param string|null $columnName
     * @param array $conditions
     * @return mixed
     * @throws Exception
     */
    public function getColumn($columnName = null, array $conditions = [])
    {
        if (!empty($columnName)) {
            $this->_sql['SELECT'] = $columnName;
        }
        if (!empty($conditions)) {
            $this->getConditions($conditions);
        }
        $sql = $this->getSql();
        return self::$_db->getColumn($sql);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function beginTransaction()
    {
        self::conn();    
        return self::$_db->beginTransaction();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function commit()
    {
        return self::$_db->commit();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public static function rollback()
    {
        return self::$_db->rollback();
    }

    /**
     * @param array $conditions
     * @return int
     * @throws Exception
     */
    public function delete(array $conditions)
    {
        $sql = 'DELETE FROM ' . $this->_tbl . ' WHERE ' . self::getWhere(($conditions));
        self::$_db->exec($sql);
        return self::$_db->changes();
    }

    /**
     * @param array $row
     * @param array $conditions
     * @return int
     * @throws Exception
     */
    public function update(array $row, array $conditions)
    {
        $arrData = [];
        self::escapeData($row);
        foreach ($row as $k => $v) {
            $arrData[] = $k . '=' . $v;
        }
        $sql = 'UPDATE ' . $this->_tbl . ' SET ' . implode(',', $arrData) . ' WHERE ' . self::getWhere($conditions);
        self::$_db->exec($sql);
        return self::$_db->changes();
    }

    /**
     * @param array $rows
     * @throws Exception
     */
    public function batchInsert(array $rows)
    {
        foreach ($rows as $row) {
            $this->insert($row);
        }
    }

    /**
     * @param array $row
     * @return int
     * @throws Exception
     */
    public function insert(array $row)
    {
        self::escapeData($row);
        $sql = 'INSERT INTO ' . $this->_tbl . '(' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_values($row)) . ')';
        self::$_db->exec($sql);
        return self::$_db->lastInsertRowID();
    }

    /**
     * @param array $row
     */
    private static function escapeData(array &$row)
    {
        foreach ($row as $k => $v) {
            if (is_array($v)) {
                self::escapeData($row[$k]);
            } elseif (!is_numeric($v)) {
                $row[$k] = "'" . self::$_db->escape($v) . "'";
            }
        }
    }

    /**
     * @param array $row
     * @return int
     * @throws Exception
     */
    public function replace(array $row)
    {
        self::escapeData($row);
        $sql = 'REPLACE INTO ' . $this->_tbl . '(' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_values($row)) . ')';
        self::$_db->exec($sql);
        return self::$_db->lastInsertRowID();
    }
}
