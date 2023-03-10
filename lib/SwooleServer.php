<?php

use Swoole\Process;
use Swoole\Coroutine;
use Swoole\Coroutine\Server\Connection;

$protocol = [
    'enable_coroutine'    => true,
    'open_eof_check'      => true,
    'open_http2_protocol' => true
];
if ($is_ssl && !empty($local_cert) && file_exists($local_cert)) {
    $protocol['open_ssl'] = true;
    $protocol['ssl_cert_file'] = $local_cert;
    if (!empty($local_pk)) {
        $protocol['ssl_key_file'] = $local_pk;
    }
    if (!empty($passphrase)) {
        $protocol['ssl_dhparam'] = $passphrase;
    }
    if (!empty($verify_peer) && !empty($cafile)) {
        $protocol['ssl_verify_peer'] = true;
        $protocol['ssl_allow_self_signed'] = true;
        $protocol['ssl_client_cert_file'] = $cafile;
    }
}
define('SWOOLE_HOST', $listen_ip);
define('SWOOLE_PORT', $port);
define('SWOOLE_PROTOCOL', $protocol);
defined('PROCESS_NUM') || define('PROCESS_NUM', !empty($process_num) && is_numeric($process_num) ? intval($process_num) : 2);

class SwooleServer
{
    private static $instance;
    private static $conn;
    private static $_body;

    private function __construct()
    {
        $objDavSession = DavSession::init();
        //多进程管理模块
        $pool = new Process\Pool(PROCESS_NUM);
        //让每个OnWorkerStart回调都自动创建一个协程
        $pool->set(SWOOLE_PROTOCOL);
        $pool->on('workerStart', function ($pool, $id) {
            //每个进程都监听设定端口
            $server = new Swoole\Coroutine\Server(SWOOLE_HOST, SWOOLE_PORT, false, true);

            //收到15信号关闭服务
            Process::signal(SIGTERM, function () use ($server) {
                $server->shutdown();
            });

            //接收到新的连接请求 并自动创建一个协程
            $server->handle(function (Connection $conn) {
                $data = $conn->recv(1);
                if ($data === '' || $data === false) {
                    $errCode = swoole_last_error();
                    $errMsg = socket_strerror($errCode);
                    echo "errCode: {$errCode}, errMsg: {$errMsg}\n";
                    $conn->close();
                }
                self::$conn = &$conn;
                $r = $data;
                $data = explode("\r\n\r\n", $data, 2);
                $headers = $this->getHeaders($data[0]);
                self::$_body = $data[1];
                if (is_array($headers)) {
                    try {
                        DavSession::init();
                        Dav_Utils::getDavSet();
                        Dav_Utils::auth();
                        $handler = 'Method_' . Dav_Utils::$_Methods[$headers['Method']];
                        $handler = new $handler();
                        $msg = $handler->execute();
                        $code = $msg['code'];
                    } catch (Exception $e) {
                        $code = $e->getCode();
                        if (!isset(Dav_Status::$Msg[$code])) {
                            $code = 503;
                        }
                        $msg = ['header' => [Dav_Status::$Msg[$code]]];
                        if ($code == 401) {
                            $msg['header'][] = 'Date: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT';
                            $msg['header'][] = 'WWW-Authenticate: Basic realm="login WebDav site"';
                            $msg['header'][] = 'Content-Type: text/html; charset=utf-8';
                            $msg['header'][] = 'Content-Length: 0';
                        } elseif ($code >= 500) {
                            Dav_Log::error($e);
                        }
                    }
                    if (isset($msg['header'])) {
                        $this->preResponseHeader($msg['header']);
                        $msg = implode("\r\n", $msg['header']) . "\r\n\r\n" . (isset($msg['body']) ? $msg['body'] : '');
                        $conn->send($msg);/*if($code>0 || true){Dav_Log::debug($r . "\n\n\n\n" . $msg . "\n\n\n\n\n\n\n\n");}*/
                    }
                }
                unset($_COOKIE);
                //发送数据
                $conn->close();
            });
            //开始监听端口
            $server->start();
        });
        $pool->start();
    }

    /**
     * 启动webdav
     *
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
     * 获取接收报文headers部分
     *
     * @return array|bool|mixed
     */
    private function getHeaders($headers)
    {
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
        $_REQUEST['COOKIE'] = isset($_COOKIE) ? $_COOKIE : [];
        return $_REQUEST['HEADERS'];
    }

    /**
     * 设置cookie
     *
     * @param string $cookieInfo
     * @return bool
     */
    private function acceptSetCookie($cookieInfo)
    {
        $cookieInfo = explode(';', $cookieInfo, 2);
        $cookieValue = explode('=', $cookieInfo[0], 2);
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
     *
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
     *
     * @return bool
     */
    private function auth()
    {
        $davInfo = empty($_SERVER['NET_DISKS'][$_REQUEST['DAV_HOST']]) ? $_SERVER['NET_DISKS']['default'] :
            $_SERVER['NET_DISKS'][$_REQUEST['DAV_HOST']];
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
            $cookie = [];
            foreach ($_COOKIE as $k => $v) {
                if (empty($_REQUEST['COOKIE'][$k])) {
                    $responseHeaders[] = 'Set-Cookie: ' . $k . '=' . rawurlencode($v);
                } else {
                    $cookie[] = $k . '=' . rawurlencode($v) . ';';
                }
            }
            if (!empty($cookie)) {
                $responseHeaders[] = 'Cookie: ' . implode(' ', $cookie);
            }
        }
        $responseHeaders[] = 'User-Agent: phpdav/3.0';
        $responseHeaders = array_unique($responseHeaders);
        unset($_COOKIE);
    }

    /**
     * 发送返回的报文
     *
     * @param array $msg
     */
    public function responseMsg($msg)
    {
        $msg = implode("\r\n", $msg['header']) . "\r\n\r\n" . (isset($msg['body']) ? $msg['body'] : '');
        self::$conn->send($msg);
    }

    /**
     * 发送返回报文header部分
     *
     * @param array $responseHeaders
     */
    public static function response_headers($responseHeaders)
    {
        $responseHeaders = implode("\r\n", $responseHeaders) . "\r\n\r\n";
        self::$conn->send($responseHeaders);
    }

    /**
     * 发送返回报文body部分
     *
     * @param string $body
     * @return false|int
     */
    public static function response_body($body)
    {
        self::$conn->send($body);
    }

    /**
     * 获取接收报文body部分
     *
     * @return false|string
     */
    public static function get_body()
    {
        while (strlen(self::$_body) < $_REQUEST['HEADERS']['Content-Length']) {
            $data = self::$conn->recv(1);
            if (strlen($data) > 0) {
                self::$_body .= $data;
            } else {
                break;
            }
        }
        return self::$_body;
    }

    /**
     * 保存上传的文件
     *
     * @param string $path
     * @return bool
     */
    public static function save_data($path)
    {
        file_put_contents($path, '');
        if (isset($_REQUEST['HEADERS']['Content-Length']) && $_REQUEST['HEADERS']['Content-Length'] == 0) {
            return 0;
        }
        $len = strlen(self::$_body);
        if ($len > 0) {
            file_put_contents($path, self::$_body, FILE_APPEND);
        }
        while ($len < $_REQUEST['HEADERS']['Content-Length']) {
            $data = self::$conn->recv(3);
            $n = file_put_contents($path, $data, FILE_APPEND);
            if ($n <= 0) {
                return false;
            }
            $len += $n;
        }
        return $len;
    }
}
