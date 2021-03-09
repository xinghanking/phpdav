<?php
class DavSession
{
    protected static $_obj = null;
    protected static $_db = null;
    private $cacheTime;

    /**
     * Sqlite_Db constructor.
     */
    protected function __construct()
    {
        if (!(self::$_db instanceof SQLite3)) {
            self::$_db = new SQLite3(':memory:');
            $sql = 'CREATE TABLE IF NOT EXISTS `dav_session`(`session_id` char(32) PRIMARY KEY NOT NULL,`session_info` text NOT NULL,`create_time` integer DEFAULT 0 NOT NULL COLLATE BINARY)';
            self::$_db->exec($sql);
            $this->cacheTime = 60 * session_cache_expire();
        }
    }

    /**
     * 调用
     */
    public static function init(){
        if(!(self::$_obj instanceof self)){
            self::$_obj = new self();
        }
        self::$_obj->start();
    }

    /**
     * 启动
     */
    public function start() {
        $sessionName = session_name();
        $sessionId = session_id();
        if (empty($_COOKIE[$sessionName])){
            $_COOKIE[$sessionName] = md5($sessionId . getmypid() . microtime(true));
            session_id($_COOKIE[$sessionName]);
            $_SESSION = [];
        } elseif($_COOKIE[$sessionName] != $sessionId){
            $this->save();
            session_id($_COOKIE[$sessionName]);
            $lastTime = time() - $this->cacheTime;
            $sql = "SELECT `session_info` FROM `dav_session` where `session_id`='" . $_COOKIE[$sessionName] . "' AND `create_time`>" . $lastTime;
            $sessionInfo = self::$_db->querySingle($sql);
            $_SESSION = empty($sessionInfo) ? [] : json_decode($sessionInfo, true);
        }
    }

    /**
     *保存
     */
    public function save(){
        $sessionInfo = json_encode($_SESSION);
        $sql = "REPLACE INTO `dav_session`(`session_id`,`session_info`,`create_time`) values ('" . session_id() . "','" . $sessionInfo . "','" . time() . "')";
        self::$_db->exec($sql);
    }

    /**
     * clear
     */
    public function __destruct(){
        $sql = 'DELETE FROM `dav_session` WHERE `create_time`<=' . (time() - $this->cacheTime);
        self::$_db->exec($sql);
    }
}