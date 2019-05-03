<?php
session_start();
$cloud_root = null;
$db_conn = 'sqlite';
$collect_view = 'collect.view.php';
define('BASE_ROOT', dirname(__DIR__));
define('DEF_CLOUD_ROOT', BASE_ROOT . DIRECTORY_SEPARATOR . 'mycloud');
if (!empty($cloud_root)){
    define('DAV_ROOT', $cloud_root);
}
define('DB_CONN', $db_conn);
define('NS_DAV_URI', 'DAV:');
define('NS_DAV_ID', 0);
define('TEMPLATE_COLLECT_VIEW', __DIR__ . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . $collect_view);
define('MAX_READ_LENGTH', 8388608);
spl_autoload_register(function ($class) {
    $classRootList = [
        BASE_ROOT . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR,
        BASE_ROOT . DIRECTORY_SEPARATOR . 'models'  . DIRECTORY_SEPARATOR,
    ];
    foreach ($classRootList as $root) {
        $classFile = $root . $class . '.php';
        if (is_file($classFile)) {
            include_once $classFile;
            return true;
        } else {
            $pathInfo = str_replace('_', DIRECTORY_SEPARATOR, $classFile);
            $path = dirname($pathInfo);
            if (false === is_dir($root . $path)) {
                $path = strtolower($path);
            }
            $path = $path . DIRECTORY_SEPARATOR;
            $fileName = basename($pathInfo);
            if (false === is_file($path . $fileName)) {
                $fileName = strtolower($fileName);
            }
            $classFile = $path . $fileName;
            if (is_file($classFile)) {
                include_once $classFile;
                return true;
            }
        }
    }
    return false;
}, true, true);
