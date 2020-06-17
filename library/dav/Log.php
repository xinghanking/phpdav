<?php

/**
 * Class Dav_Log
 */
class Dav_Log {
    const LOG_DIR = BASE_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'phpdav';
    const DEBUG_LOG_DIR  = self::LOG_DIR . DIRECTORY_SEPARATOR .'debug';
    const ACCESS_LOG_DIR = self::LOG_DIR . DIRECTORY_SEPARATOR . 'access';
    const ERROR_LOG_DIR  = self::LOG_DIR . DIRECTORY_SEPARATOR . 'error.log';

    /**
     * @param string $message
     */
    public static function debug($message){
        if (!file_exists(self::DEBUG_LOG_DIR . DIRECTORY_SEPARATOR)){
            mkdir(self::DEBUG_LOG_DIR);
        }
        $debugLogFile = self::DEBUG_LOG_DIR . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        file_put_contents($debugLogFile, date('Y-m-d H:i:s', time()) . ' ' . $message, FILE_APPEND);
    }

    public static function access(){
        $accessLogFile = self::ACCESS_LOG_DIR . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        $msg = date('Y-m-d H:i:s', time()) . ' ';
        file_put_contents($accessLogFile, $msg, FILE_APPEND);
    }

    /**
     * @param \Exception $e
     * @param  string $msg
     */
    public static function error(Exception $e, $msg = ''){
        $msg = date('Y-m-d H:i:s', time()) . ' ' . $e->getFile() . ':' . $e->getLine() . '; Code: ' . $e->getCode() .'; Nsg: ' . $msg . ',' . $e->getMessage() . PHP_EOL . 'Trace: ' . $e->getTrace();
        error_log($msg, 3, self::ERROR_LOG_DIR);
    }
}
