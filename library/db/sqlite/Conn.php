<?php

/**
 * Class Db_Sqlite_Conn
 */
class Db_Sqlite_Conn
{
    protected static $_obj;
    protected static $_db = null;
    protected $_tbl;

    /**
     * Sqlite_Db constructor.
     */
    protected function __construct()
    {
        if (!(self::$_db instanceof SQLite3)) {
            if (file_exists(SQLITE_DB_FILE)) {
                self::$_db = new SQLite3(SQLITE_DB_FILE);
            }
            else {
                self::$_db = new SQLite3(SQLITE_DB_FILE);
                $sql = file_get_contents(SQLITE_INIT_FILE);
                self::$_db->exec($sql);
            }
            self::$_db->busyTimeout(3000);
        }
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, array $arguments = null)
    {
        return call_user_func_array([self::$_db, $name], $arguments);
    }

    private function __clone(){}

    /**
     * @return Db_Sqlite_Conn
     */
    public static function getInstance()
    {
        if (!(self::$_obj instanceof self)) {
            self::$_obj = new self();
        }
        return self::$_obj;
    }

    /**
     * @param string $sql
     * @return array|mixed
     * @throws Exception
     */
    public function query($sql)
    {
        $objRes = self::$_db->query($sql);
        if (false === $objRes) {
            $mirSeconds = 0;
            $count = 0;
            while (false == $objRes && $count < 3) {
                $microSeconds = rand($mirSeconds, $count * 1000000);
                usleep($microSeconds);
                $objRes = self::$_db->query($sql);
                ++$count;
            }
        }
        if (false === $objRes) {
            throw new Exception('fail execute query sql，sql:' . $sql);
        }
        $arrRes = [];
        while ($row = $objRes->fetchArray(SQLITE3_ASSOC)) {
            $arrRes[] = $row;
        }
        return $arrRes;
    }

    /**
     * @param string $sql
     * @return array
     * @throws Exception
     */
    public function getRow($sql)
    {
        $row = self::$_db->querySingle($sql, true);
        if (false === $row) {
            $count = 0;
            $microSeconds = 0;
            while (false === $row && $count < 3) {
                $microSeconds = rand($microSeconds, $count * 1000000);
                usleep($microSeconds);
                $row = self::$_db->querySingle($sql, true);
                ++$count;
            }
            if (false === $row) {
                throw new Exception('执行sql语句失败，sql:' . $sql);
            }
        }
        return $row;
    }

    /**
     * @param string $sql
     * @return string
     * @throws Exception
     */
    public function getColumn($sql)
    {
        $column = self::$_db->querySingle($sql);
        if (false === $column) {
            $count = 0;
            $microSeconds = 0;
            while (false === $column && $count < 3) {
                $microSeconds = rand($microSeconds, $count * 1000000);
                usleep($microSeconds);
                $column = self::$_db->querySingle($sql);
                ++$count;
            }
            if (false === $column) {
                throw new Exception('执行sql语句失败，sql:' . $sql);
            }
        }
        return $column;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function beginTransaction()
    {
        return $this->exec('BEGIN TRANSACTION');
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function commit()
    {
        return $this->exec('COMMIT');
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function rollback()
    {
        return $this->exec('ROLLBACK');
    }

    /**
     * @return int
     */
    public function changes(){
        return self::$_db->changes();
    }

    /**
     * @return int
     */
    public function lastInsertRowID() {
        return self::$_db->lastInsertRowID();
    }

    /**
     * @param string $sql
     * @return bool
     * @throws Exception
     */
    public function exec($sql)
    {
        $res = self::$_db->exec($sql);
        if (false === $res) {
            $mirSeconds = 0;
            $count = 0;
            while (false == $res && $count < 3) {
                $microSeconds = rand($mirSeconds, $count * 1000000);
                usleep($microSeconds);
                $res = self::$_db->exec($sql);
                ++$count;
            }
            if (false === $res) {
                throw new Exception('执行sql语句失败，sql:' . $sql);
            }
        }
        return $res;
    }

    /**
     * @param string $string
     * @return string
     */
    public function escape($string)
    {
        return $string === null || $string === '' ? '' : sqlite3::escapeString($string);
    }
}