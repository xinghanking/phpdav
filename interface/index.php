<?php
try {
    require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'conf/prepend.php';
    $objDavServer = Dav_Server::init();
    try {
        $objDavServer->start();
    } catch (Exception $e) {
        Dav_Log::error($e);
        $code = $e->getCode();
        if (!isset(Dav_Status::$Msg[$code])) {
            $code = 503;
        }
        header(Dav_Status::$Msg[$code]);
    }
} catch (Exception $e) {
    $msg = $e->getFile() . ':' . $e->getLine() . ';  CODE: ' . $e->getCode() . '; Msg: ' . $e->getMessage() . PHP_EOL . 'Trace: ' . $e->getTraceAsString();
    error_log($msg);
    header('HTTP/1.1 503 Service Unavailable');
}