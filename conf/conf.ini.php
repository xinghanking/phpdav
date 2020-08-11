<?php
$listen_port = 8150; //绑定的端口号
$server_lang = null; //服务器系统字符编码，可在命令行下执行 echo $LANG 获取
$process_num = 5;    //开启进程数
$is_ssl = false;     //是否开启ssl，true:开启；false;不开启，若开启，webdav的访问地址将以https开始
/*
 * 以下设置项，在is_ssl=true时有效
 */
$ssl_info = [
    'local_cert' => '',  //ssl服务器证书地址
    'local_pk' => '',    //ssl秘钥文件地址
    'passphrase' => '',  //服务器证书密码
    'cafile' => ''       //ssl根证书，如采用双向ssl加密，设置此项
];
/*
 * webdav主机名（域名、IP）与目录的映射关系
 * 如只设一个，可把"default"修改为主机名即可，如不修改，任何指向的主机名如果没有找到设置将使用这里的默认设置
 */
$net_disks = [
    'default' => [ //默认或任何未设置主机名访问这里，可把key改为你设置的主机名（域名、IP）
        'path' => BASE_ROOT . DIRECTORY_SEPARATOR . 'share_disk', //访问地址（主机 名+端口号）的映射目录
        'is_auth' => false, //是否开启用户认证
        'user_list' => [ //左边用户名（key）,右边密码(value)
            'phpdav' => 'phpdav'
        ]
    ]
];
