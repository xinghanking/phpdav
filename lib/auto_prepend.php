<?php

session_start();
$db_conn = 'sqlite';
$collect_view = 'collect.view.php';
define('BASE_ROOT', dirname(__DIR__));
require_once BASE_ROOT . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'conf.ini.php';
if (empty($_SERVER['LANG'])) {
    $_SERVER['LANG'] = getenv('LANG');
    if (empty($_SERVER['LANG'])) {
        $_SERVER['LANG'] = empty($server_lang) ? exec('echo $LANG') : $server_lang;
    }
}
if (empty($_SERVER['LANG'])) {
    $_SERVER['LANG'] = 'UTF-8';
} else {
    $_SERVER['LANG'] = explode('.', $_SERVER['LANG']);
    $_SERVER['LANG'] = $_SERVER['LANG'][count($_SERVER['LANG']) - 1];
}
$_SERVER['NET_DISKS'] = $net_disks;
define('LOG_DIR', BASE_ROOT . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR);
define('SQLITE_INIT_FILE', BASE_ROOT . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'sqlite' . DIRECTORY_SEPARATOR . 'phpdav.sql');
define('SQLITE_DB_PATH', BASE_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'sqlite');
define('PID_PATH', BASE_ROOT . DIRECTORY_SEPARATOR . 'run');
define('DB_CONN', $db_conn);
define('SERVER_LANG', $_SERVER['LANG']);
define('NS_DAV_URI', 'DAV:');
define('NS_DAV_ID', 0);
define('TEMPLATE_COLLECT_VIEW', BASE_ROOT . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . $collect_view);
define('MAX_READ_LENGTH', 8388608);
spl_autoload_register(
    function ($class) {
        if (substr($class, 0, 7) == 'Method_') {
            $fileName = BASE_ROOT . DIRECTORY_SEPARATOR . 'method' . DIRECTORY_SEPARATOR . substr($class, 7) . '.php';
            if (file_exists($fileName)) {
                include_once $fileName;
                return true;
            } else {
                return false;
            }
        }
        $root = BASE_ROOT . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR;
        $classFile = $root . $class . '.php';
        if (is_file($classFile)) {
            include_once $classFile;
            return true;
        } else {
            $pathInfo = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
            $classFile = $root . $pathInfo;
            if (false === is_file($classFile)) {
                $classFile = $root . strtolower(dirname($pathInfo)) . DIRECTORY_SEPARATOR . basename($pathInfo);
            }
            if (is_file($classFile)) {
                include_once $classFile;
                return true;
            }
        }
        return false;
    },
    true,
    true
);
