#!/usr/bin/env php
<?php
set_time_limit(0);
try {
    include_once __DIR__ . DIRECTORY_SEPARATOR . 'auto_prepend.php';
    include_once BASE_ROOT . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . '.dav_info.php';
    include_once BASE_ROOT . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'DavSession.php';
    $opts = ['http' => ['Server' => 'phpdav']];
    if ($is_ssl && !empty($local_cert) && file_exists($local_cert)) {
        $opts = [
            'ssl' => [
                'verify_depth' => 0,
                'local_cert'   => $local_cert,
            ]
        ];
        if (!empty($local_pk)) {
            $opts['ssl']['local_pk'] = $local_pk;
        }
        if (!empty($passphrase)) {
            $opts['ssl']['passphrase'] = $passphrase;
        }
        if (!empty($verify_peer) && !empty($cafile)) {
            $opts['ssl']['verify_peer'] = true;
            $opts['ssl']['cafile'] = $cafile;
        }
    }
    $_SERVER['DAV']['socket'] = $listen_address;
    $_SERVER['DAV']['context'] = stream_context_create($opts);
    define('PROCESS_NUM', !empty($process_num) && is_numeric($process_num) ? intval($process_num) : 1);
    session_start();

    class PhpDavServer
    {
        private $sock = null;
        private static $conn = null;
        private static $instance = null;

        /**
         * PhpDavServer constructor.
         * @throws Exception
         */
        private function __construct()
        {
            $this->conn();
            if (!$this->sock) {
                return false;
            }
            $this->run();

            $process_num = intval(PROCESS_NUM);
            if (!function_exists('pcntl_fork')) {
                $process_num = 0;
            } else {
                $fail_num = 0;
                for ($i = 0; $i < $process_num; ++$i) {
                    $pid = @pcntl_fork();
                    if ($pid == 0) {
                        $this->run();
                        break;
                    }
                    if ($pid < 0) {
                        ++$fail_num;
                    }
                }
                $process_num -= $fail_num;
            }
            if ($process_num == 0) {
                $this->run();
            }
        }

        /**
         * @return bool
         */
        private function conn()
        {
            if (!$this->sock) {
                $this->sock = @stream_socket_server($_SERVER['DAV']['socket'],$error_no,$error_msg,STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $_SERVER['DAV']['context']);
            }
            if ($this->sock) {
                self::$conn = @stream_socket_accept($this->sock, -1);
                if (self::$conn) {
                    return true;
                }
            }
            throw new Exception($error_msg, $error_no);
        }

        /**
         * 启动webdav
         * @throws Exception
         */
        public static function start()
        {
            define('START_CLASS', __CLASS__);
            if (!(self::$instance instanceof self)) {
                self::$instance = new self();
            }
        }

        /**
         * 运行webdav
         * @throws Exception
         */
        private function run()
        {
            while ($this->conn()) {
                $headers = $this->getHeaders();
                if (is_array($headers)) {
                    try {
                        DavSession::init();
                        Dav_Utils::getDavSet();
                        $this->auth();
                        $handler = 'Method_' . Dav_Utils::$_Methods[$headers['Method']];
                        $handler = new $handler();
                        $msg = $handler->execute();
                    } catch (Exception $e) {
                        $code = $e->getCode();
                        if (!isset(Dav_Status::$Msg[$code])) {
                            $code = 503;
                        }
                        $msg = ['header'=> [Dav_Status::$Msg[$code]]];
                        if ($code == 401) {
                            $msg['header'][]='Date: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT';
                            $msg['header'][]='WWW-Authenticate: Basic realm="login WebDav site"';
                            $msg['header'][]='Content-Length: 0';
                        } else {
                            Dav_Log::error($e);
                        }
                    }
                    if (isset($msg['header'])) {
                        $this->preResponseHeader($msg['header']);
                        $this->responseMsg($msg);
                    }
                }
                @fclose(self::$conn);
                unset($_COOKIE);
            }
        }

        /**
         * 获取接收报文headers部分
         * @return array|bool|mixed
         */
        private function getHeaders()
        {
            $headers = trim(stream_get_line(self::$conn, 8192, "\r\n\r\n"));
            if (empty($headers)) {
                return false;
            }
            $headers = explode("\r\n", $headers);
            $msg = array_shift($headers);
            $msg = preg_split('/\s+/', $msg, 3);
            $requireMethod = strtoupper($msg[0]);
            if (!isset(Dav_Utils::$_Methods[$requireMethod])) {
                return false;
            }
            $length = count($msg);
            if (strtoupper(strtok($msg[$length - 1], '/')) != 'HTTP') {
                return false;
            }
            $_SERVER['REQUEST_TIME'] = time();
            $_REQUEST['HEADERS'] = ['Method' => $requireMethod];
            $_REQUEST['HEADERS']['Uri'] = ($msg[1] == '*' || $length == 2) ? '/' : rtrim($msg[1], '*');
            if (!empty($headers)) {
                foreach ($headers as $msg) {
                    $msg = explode(':', trim($msg), 2);
                    $headerName = ucwords(trim($msg[0]));
                    $headerValue = trim($msg[1]);
                    $_REQUEST['HEADERS'][$headerName] = $headerValue;
                    if (strcasecmp($headerName, 'Content-Type') == 0) {
                        $values = explode(';', $headerValue);
                        $_REQUEST['HEADERS'][$headerName] = trim($values[0]);
                        if (count($values) == 2) {
                            $charsetMsg = explode('=', $values[1]);
                            if (strtolower(trim($charsetMsg[0])) == 'charset') {
                                $_REQUEST['HEADERS']['Charset'] = strtoupper(trim($charsetMsg[1]));
                            }
                        }
                    } elseif (strcasecmp($headerName, 'SetCookie') == 0) {
                        $this->acceptSetCookie($headerValue);
                    } elseif (strcasecmp($headerName, 'Cookie') == 0) {
                        $this->initCookie($headerValue);
                    }
                }
            }
            $_REQUEST['DAV_HOST'] = strtok($_REQUEST['HEADERS']['Host'], ':');
            return $_REQUEST['HEADERS'];
        }

        /**
         * 设置cookie
         * @param string $cookieInfo
         * @return bool
         */
        private function acceptSetCookie($cookieInfo)
        {
            $cookieInfo = explode(';', $cookieInfo, 2);
            $cookieValue = explode('=', $cookieInfo[0]);
            if (count($cookieValue) < 2) {
                return false;
            }
            $cName = urldecode(trim($cookieValue[0]));
            $cValue = urldecode(trim($cookieValue[1]));
            $expire = 0;
            $path = '/';
            $domain = $_REQUEST['HEADERS']['Host'];
            if (!empty($cookieInfo[1])) {
                $cookieInfo = explode(';', $cookieInfo[1]);
                foreach ($cookieInfo as $info) {
                    $info = trim($info);
                    $info = explode('=', $info);
                    if (count($info) == 2) {
                        $iName = trim($info[0]);
                        $iValue = trim($info[1]);
                        if (strcasecmp($iName, 'Expires') == 0 && $expire == 0) {
                            $expire = strtotime($iValue);
                        } elseif (strcasecmp($iName, 'Max-Age') == 0 && is_numeric($iValue)) {
                            $expire = time() + intval($iValue);
                        } elseif (strcasecmp($iName, 'Domain') == 0) {
                            $domain = $iValue;
                        } elseif (strcasecmp($iName, 'Path') == 0) {
                            $path = $iValue;
                        }
                    }
                }
            }
            if ($domain == $_REQUEST['HEADERS']['Host'] && strcmp($_REQUEST['HEADERS']['Uri'], $path) >= 0 && $expire >= $_SERVER['REQUEST_TIME']) {
                $_COOKIE[$cName] = $cValue;
            }
        }

        /**
         * 设置返回的cookie值
         * @param string $cookInfo
         */
        private function initCookie($cookInfo)
        {
            $cookInfo = explode(';', $cookInfo);
            foreach ($cookInfo as $info) {
                $info = explode('=', $info);
                $name = urldecode(trim($info[0]));
                $value = urldecode(trim($info[1]));
                $_COOKIE[$name] = urldecode($value);
            }
        }

        /**
         * 身份验证
         * @return bool
         */
        private function auth()
        {
            $davInfo = empty($_SERVER['NET_DISKS'][$_REQUEST['DAV_HOST']]) ? $_SERVER['NET_DISKS']['default'] : $_SERVER['NET_DISKS'][$_REQUEST['DAV_HOST']];
            if (empty($davInfo ['is_auth']) || !empty($_SESSION['auth'])) {
                return true;
            }

            if (isset($_REQUEST['HEADERS']['Authorization'])) {
                $authInfo = preg_split('/\s+/', $_REQUEST['HEADERS']['Authorization']);
                $authInfo = base64_decode(trim($authInfo[1]));
                $authInfo = explode(':', $authInfo);
                if (isset($davInfo['user_list'][$authInfo[0]]) && $davInfo['user_list'][$authInfo[0]] == $authInfo[1]) {
                    $_SESSION['auth'] = true;
                    return true;
                }
                throw new Exception(Dav_Status::$Msg['403'], 403);
            }
            throw new Exception(Dav_Status::$Msg['401'], 401);
        }

        /**
         * 处理发送前的header
         * @param array $responseHeaders
         */
        private function preResponseHeader(&$responseHeaders)
        {
            if (!empty($_COOKIE)) {
                foreach ($_COOKIE as $k => $v) {
                    $responseHeaders[] = 'Set-Cookie: ' . $k . '=' . rawurlencode($v);
                }
            }
            $responseHeaders[] = 'User-Agent: phpdav/1.1';
            $responseHeaders = array_unique($responseHeaders);
            unset($_COOKIE);
        }

        /**
         * 发送返回的报文
         * @param array $msg
         */
        public function responseMsg($msg)
        {
            $msg = implode("\r\n", $msg['header']) . "\r\n\r\n" . (isset($msg['body']) ? $msg['body'] : '');
            fputs(self::$conn, $msg);
        }

        /**
         * 发送返回报文header部分
         * @param array $responseHeaders
         */
        public static function response_headers($responseHeaders)
        {
            $responseHeaders = implode("\r\n", $responseHeaders) . "\r\n\r\n";
            fputs(self::$conn, $responseHeaders);
        }

        /**
         * 发送返回报文body部分
         * @param string $body
         * @return false|int
         */
        public static function response_body($body)
        {
            return fputs(self::$conn, $body);
        }

        /**
         * 获取接收报文body部分
         * @return false|string
         */
        public static function get_body()
        {
            $body = fread(self::$conn, $_REQUEST['HEADERS']['Content-Length']);
            return $body;
        }

        /**
         * 保存上传的文件
         * @param string $path
         * @return bool
         */
        public static function save_data($path)
        {
            file_put_contents($path, '');
            if (isset($_REQUEST['HEADERS']['Content-Length']) && $_REQUEST['HEADERS']['Content-Length'] == 0) {
                return 0;
            }
            $len = 0;
            while ($len < $_REQUEST['HEADERS']['Content-Length']) {
                $data = fread(self::$conn, 8192);
                $n = file_put_contents($path, $data, FILE_APPEND);
                if ($n <= 0) {
                    return false;
                }
                $len += $n;
            }
            return $len;
        }

        /**
         *储存接收报文body部分数据
         * @param string $path
         * @return bool
         */
        public static function accept_data($path)
        {
            if (isset($_REQUEST['HEADERS']['Content-Length']) && $_REQUEST['HEADERS']['Content-Length'] == 0) {
                file_put_contents($path, '', FILE_APPEND);
                return 0;
            }
            $len = 0;
            while ($len < $_REQUEST['HEADERS']['Content-Length']) {
                $data = fread(self::$conn, 8192);
                $n = file_put_contents($path, $data, FILE_APPEND);
                if ($n <= 0) {
                    return false;
                }
                $len += $n;
            }
            return $len;
        }
    }
    PhpDavServer::start();
} catch (Exception $e) {
    $msg='fatal error in ' . $e->getFile() . ':' . $e->getLine() . '. error code: ' . $e->getCode() . ', msg:' . $e->getMessage();
    file_put_contents('php://stderr', $msg);
}