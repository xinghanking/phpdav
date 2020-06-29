<?php
$server_name = '0.0.0.0'; //主机名（ip或域名）
$port = 8080; //端口号
$dir_path = ''; //要管理的文件目录路径
$username = ''; // 登录用户名
$password = ''; // 登录密码
$ssl_cert = ''; //ssl服务器端证书
$ssl_key = '';  //ssl服务器端证书key
$ssl_pass = ''; //证书秘钥
$cafile = ''; //验证远端证书所用到的CA证书路径。
$local_socket = $server_name . ':' . $port;
if (empty($ssl_cert)) {
    $local_socket .= 'tcp://' . $local_socket;
    $socket = stream_socket_server($local_socket, $err_no, $err_str, STREAM_SERVER_BIND, );
} else {
    $local_socket .= 'tls://' . $local_socket;
    $context = [
        'local_cert' => $ssl_cert,
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ];
    if (!empty($ssl_key)) {
        $context['local_pk'] = $ssl_key;
    }
    if (!empty($ssl_pass)) {
        $context['passphrase'] = $ssl_pass;
    }
    if (!empty($cafile)) {
        $context['cafile'] = $cafile;
    }
    $context = stream_context_create(['ssl' => $context]);
    $socket = stream_socket_server($local_socket, $err_no, $err_str, STREAM_SERVER_BIND, $context);
}

class phpDav_cgi
{
    private static $socket = null;
    private static $objInstance = null;

    private function __construct()
    {
        if (!empty($local_socket)) {

        }
    }
}

