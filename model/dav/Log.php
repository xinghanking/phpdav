<?php

/**
 * Class HttpsDav_Log
 */
class Dav_Log
{

    /**
     * @param string $message
     */
    public static function debug($message)
    {
        if (!is_dir(LOG_DIR)) {
            Dav_PhyOperation::createDir(LOG_DIR);
        }
        $debugLogFile = LOG_DIR . 'debug.log';
        file_put_contents($debugLogFile, date('Y-m-d H:i:s', time()) . ' ' . $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * @param \Exception $e
     * @param string $msg
     */
    public static function error(Exception $e, $msg = '')
    {
        if (!is_dir(LOG_DIR)) {
            Dav_PhyOperation::createDir(LOG_DIR);
        }
        $msg = date('Y-m-d H:i:s', time()) . ' ' . $e->getFile() . ':' . $e->getLine() . '; Code: ' . $e->getCode() . '; Nsg: ' . $msg . ',' . $e->getMessage() . PHP_EOL . 'Trace: ' . print_r($e->getTrace(), true);
        error_log($msg, 3, LOG_DIR . 'error.log');
    }
}
