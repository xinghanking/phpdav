<?php
try {
    require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'conf/config.ini.php';
    $objHttpsDavServer = HttpsDav_Server::init();
    try {
        $objHttpsDavServer->start();
    } catch (Exception $e) {
        HttpsDav_Log::error($e);
        $code = $e->getCode();
        if (!isset(HttpsDav_StatusCode::$message[$code])) {
            $code = 503;
        }
        header(HttpsDav_StatusCode::$message[$code]);
    }
} catch (Exception $e) {
    $msg = $e->getFile() . ':' . $e->getLine() . ';  CODE: ' . $e->getCode() . '; Msg: ' . $e->getMessage() . PHP_EOL . 'Trace: ' . $e->getTraceAsString();
    error_log($msg);
    header('HTTP/1.1 503 Service Unavailable');
}