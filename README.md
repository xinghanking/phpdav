# phpdav
使用php开发实现webdav协议的项目
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
3. 设置不同的访问主机名（域名、IP）地址映射各自的服务器目录地址，可按照config.ini.php里的提示修改，注意要给你php的执行用户读写执行权限(rwx)