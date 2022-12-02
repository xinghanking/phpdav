<?php
class DavSession
{
    protected static $_obj = null;
    protected static $_db = null;

    /**
     * Sqlite_Db constructor.
     */
    protected function __construct()
    {
        if (!(self::$_db instanceof SQLite3)) {
            self::$_db = new SQLite3(':memory:');
            $sql = 'CREATE TABLE IF NOT EXISTS `dav_session`(`session_id` char(32) PRIMARY KEY NOT NULL,`session_info` text NOT NULL,`create_time` integer DEFAULT 0 NOT NULL COLLATE BINARY)';
            self::$_db->exec($sql);
            if (in_array(session_status(), [PHP_SESSION_NONE, PHP_SESSION_ACTIVE])) {
                session_commit();
            }
            session_set_save_handler([$this, 'open'], [$this, 'close'], [$this, 'read'], [$this, 'write'], [$this, 'destroy'], [$this, 'gc']);
            session_start();
        }
    }

    /**
     * 调用
     */
    public static function init()
    {
        if (!(self::$_obj instanceof self)) {
            self::$_obj = new self();
        }
        self::$_obj->start();
    }

    /**
     * 启动
     */
    public function start()
    {
        $sessionName = session_name();
        if (empty($_COOKIE[$sessionName])) {
            $_COOKIE[$sessionName] = md5(session_id() . getmypid() . microtime(true));
        }
        if ($_COOKIE[$sessionName] != session_id()) {
            session_commit();
            session_id($_COOKIE[$sessionName]);
            session_start();
        }
    }

    private function open()
    {
        return true;
    }

    private function close()
    {
        return true;
    }

    private function read($sessionId)
    {
        $lastTime = time() - session_cache_expire() * 60;
        $sql = "SELECT `session_info` FROM `dav_session` where `session_id`='" . $sessionId . "' AND `create_time`>" . $lastTime;
        $sessionInfo = self::$_db->querySingle($sql);
        return empty($sessionInfo) ? '' : $sessionInfo;
    }

    private function write($sessionId, $data)
    {
        $sql = "REPLACE INTO `dav_session`(`session_id`,`session_info`,`create_time`) values ('" . $sessionId . "','" . $data . "','" . time() . "')";
        return self::$_db->exec($sql);
    }

    private function destroy($sessionId)
    {
        $sql = "DELETE FROM `dav_session` WHERE `session_id`='" . $sessionId . "'";
        return self::$_db->exec($sql);
    }

    private function gc($maxTime)
    {
        $sql = 'DELETE FROM `dav_session` WHERE `create_time`<=' . (time() - $maxTime);
        return self::$_db->exec($sql);
    }

    /**
     * clear
     */
    public function __destruct()
    {
        return $this->gc(session_cache_expire() * 60);
    }
}
