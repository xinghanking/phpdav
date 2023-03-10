# phpdav
使用php开发实现webdav协议的项目

#更新说明
phpdav3.0增加对event、swoole扩展的支持
如果安装了event扩展，使用方法不变；
如果安装了Swoole扩展，可在安装目中执行
   bin/phpswooldav
启动webdav站点，使用方法同 bin/phpdav

phpdav2.0可不依赖nginx

php版本至少php5.4以上

以下非必须：
nginx版本建议nginx1.11.0以上

#配置参考
本项目可不用nginx, 使用方法：
1. 下载后，直接在程序安装目录中执行
    bin/phpdav start
    即可使用phpdav建立一个webdav站点
2. 使用nginx、php-fpm按照conf/np.conf里的提示，设置nginx、php-fpm执行程序的地址
    bin/php-fpm start
    bin/nginx start
    同样可以建立一个webdav站点
