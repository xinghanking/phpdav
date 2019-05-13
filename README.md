# phpdav
使用php开发实现webdav协议的项目
php版本至少php5.6以上
nginx版本建议nginx1.11.0以上

#配置参考

修改conf/config.ini.php里
$cloud_root = null;
为你要映射的目录地址，注意要给你php-fpm的执行用户读写执行权限

nginx配置参考
    

    access_log                    /home/phpdav/phpdav/logs/nginx/access.log  main;
    charset                       utf-8;
    sendfile                      on;
    tcp_nodelay                   on;
    client_max_body_size          0;
    client_body_in_file_only      clean;
    client_body_in_single_buffer  on;
    location / {
        root                          /home/phpdav/phpdav/interface;
        rewrite                       .*  /index.php break;
        fastcgi_pass                  unix:/home/phpdav/phpdav/server/run/php-cgi.sock;
        fastcgi_keep_conn             on;
        fastcgi_limit_rate            0;
        fastcgi_request_buffering     on;
        fastcgi_cache_revalidate      on;
        fastcgi_pass_request_headers  on;
        fastcgi_force_ranges          on;
        fastcgi_connect_timeout       600s;
        fastcgi_read_timeout          600s;
        include                       fastcgi.conf;
    }

其中root配置项指向的代码文件目录中的interface目录
