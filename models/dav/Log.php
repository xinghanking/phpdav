<?php

/**
 * Class Dav_Log
 */
class Dav_Log
{
    /**
     * @param string $message
     */
    public static function debug($message)
    {
        $logDir = BASE_ROOT . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'phpdav' . DIRECTORY_SEPARATOR . 'debug';
        if (!is_dir($logDir)) {
            Dav_PhyOperation::createDir($logDir);
        }
        $logFile = $logDir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        file_put_contents($logFile, date('Y-m-d H:i:s', time()) . ' ' . $message, FILE_APPEND);
    }

    /**
     * @param \Exception $e
     * @param string $msg
     */
    public static function error(Exception $e, $msg = '')
    {
        $logDir = BASE_ROOT . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'phpdav';
        if (!is_dir($logDir)) {
            Dav_PhyOperation::createDir($logDir);
        }
        $msg = date('Y-m-d H:i:s', time()) . ' ' . $e->getFile() . ':' . $e->getLine() . '; Code: ' . $e->getCode() . '; Nsg: ' . $msg . ',' . $e->getMessage() . PHP_EOL . 'Trace: ' . $e->getTrace();
        error_log($msg, 3, $logDir . DIRECTORY_SEPARATOR . 'error.log');
    }
}