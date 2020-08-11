<?php
$listen_port = 8080;
$_SERVER['LANG'] = 'UTF-8';
$pid_path = null;
$net_disks = [
    'default' => [
        'path'      => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'share_disk',
        'is_auth'   => false,
        'user_list' => [
            'phpdav' => 'phpdav'
        ]
    ],
    '192.168.1.102' => [
        'path'      => '/home/web',
        'is_auth' => false,
        'user_list' => [
            'phpdav' => 'phpdav'
        ]
    ]
];