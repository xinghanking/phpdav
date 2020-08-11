#!/usr/bin/env php
<?php
set_time_limit(0);
include_once __DIR__ . DIRECTORY_SEPARATOR . 'auto_prepend.php';
$listen_socket = 'tcp://0.0.0.0:' . $listen_port;
$opts = ['http' => ['Server' => 'phpdav']];
if ($is_ssl && file_exists($ssl_info['local_cert'])) {
    $listen_socket = 'ssl://0.0.0.0:' . $listen_port;
    $opts = ['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'local_cert'        => $ssl_info['local_cert'],
    ]];
    if (!empty($ssl_info['local_pk'])) {
        $opts['ssl']['local_pk'] = $ssl_info['local_pk'];
    }
    if (!empty($ssl_info['passphrase'])) {
        $opts['ssl']['passphrase'] = $ssl_info['passphrase'];
    }
    if (file_exists($ssl_info['cafile'])) {
        $opts['ssl']['cafile'] = $ssl_info['cafile'];
    }
}
$_SERVER['DAV']['socket'] = $listen_socket;
$_SERVER['DAV']['context'] = stream_context_create($opts);
define('PROCESS_NUM', $process_num);
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
        $this->sock = @stream_socket_server($_SERVER['DAV']['socket'], $error_no, $error_msg, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $_SERVER['DAV']['context']);
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
        while (self::$conn = @stream_socket_accept($this->sock, -1)) {
            $headers = $this->getHeaders();
            if (is_array($headers)) {
                Dav_Utils::getDavSet();
                $handler = 'Method_' . Dav_Utils::$_Methods[$headers['Method']];
                $handler = new $handler();
                $msg = $handler->execute();
                if (isset($msg['header'])) {
                    $this->preResponseHeader($msg['header']);
                    $this->responseMsg($msg);
                }
            }
            fclose(self::$conn);
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
        file_put_contents('/home/web/phpdav/log/debuga.log', $headers, FILE_APPEND);
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
        $_REQUEST['HEADERS'] = ['Method' => $requireMethod];
        $_REQUEST['HEADERS']['Uri'] = ($msg[1] == '*' || $length == 2) ? '/' : rtrim($msg[1], '*');
        if (!empty($headers)) {
            foreach ($headers as $msg) {
                $msg = explode(':', trim($msg));
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
        $cName = trim($cookieValue[0]);
        $cValue = urldecode(trim($cookieValue[1]));
        $expire = 0;
        $path = '';
        $domain = '';
        $secure = false;
        $httpOnly = false;
        if (!empty($cookieInfo[1])) {
            $cookieInfo = explode(';', $cookieInfo[1]);
            foreach ($cookieInfo as $info) {
                $info = trim($info);
                if (strcasecmp($info, 'Secure') == 0) {
                    $secure = true;
                } elseif (strcasecmp($info, 'HttpOnly') == 0) {
                    $httpOnly = true;
                } else {
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
        }
        setcookie($cName, $cValue, $expire, $path, $domain, $secure, $httpOnly);
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
            $name = trim($info[0]);
            $value = trim($info[1]);
            $_COOKIE[$name] = urldecode($value);
        }
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

try {
    PhpDavServer::start();
} catch (Exception $e) {
    echo 'fatal error in ' . $e->getFile() . ':' . $e->getLine() . '. error code: ' . $e->getCode() . ', msg:' . $e->getMessage();
}
