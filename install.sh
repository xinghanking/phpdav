#!/bin/bash
#安装文件
#作者：刘重量 Email:13439694341@qq.com

#执行前初始化环境设置
set -e
set -E
trap 'echo "Fail unexpectedly on ${BASH_SOURCE[0]}:$LINENO!" >&2' ERR

OLD_LC_ALL=${LC_ALL:-""}                    #保存原来的语言编码
export LC_ALL="zh_CN.UTF-8"                 #设置界面显示语言及编码
PHPDAV_ROOT=$(readlink -f `dirname "$0"`)   #获取安装目录路径
CURRENT_USER=`whoami`                       #获取当前用户

#安装脚本退出函数
install_exit() {
    export LC_ALL="$OLD_LC_ALL"
    exit 0;
}

#安装脚本说明
install_help() {
    echo "安装脚本使用示例："
    echo './install.sh --nginx-path=/usr/local/nginx/sbin/nginx --php-fpm-path=/usr/local/php/sbin/php-fpm --user=phpdav --group=phpdav --share-dir-path=/home/phpdav/cloud'
    echo '说明：'
    echo '    --nginx-path       nginx程序路径地址
    --php-fpm-path     php-fpm程序路径地址
    --user             指定运行nginx和php程序的系统用户
    --group            指定运行nginx和php程序的系统用户组
    --share-dir-path   要映射管理的服务器目录'
    exit 0
}
NGINX_PATH=
PHP_FPM_PATH=
DAV_USER=
DAV_GROUP=
SHARE_DIR="$PHPDAV_ROOT/cloud"

if [ -z $1 ]; then
    install_help
fi

for parm in $*;
do
    val=${parm#*=}
    case "$parm" in
        --php-fpm-path=*)    PHP_FPM_PATH="$val" ;;
        --nginx-path=*)      NGINX_PATH="$val"   ;;
        --user=*)            DAV_USER="$val"     ;;
        --group=*)           DAV_GROUP="$val"    ;;
        --share-dir-path=*)  SHARE_DIR="$val"    ;;
        *)                   install_help        ;;
    esac
done;
if [ -z $PHP_FPM_PATH ]; then
    echo '请指定php-fpm地址'
    exit
fi
if [ -z $NGINX_PATH ]; then
    echo '请指定nginx地址'
    exit
fi
if [ ! -x $PHP_FPM_PATH ]; then
    echo '当前所用登录用户缺少对指定的php-fpm的执行权限，安装退出'
    install_exit
fi
n=`$PHP_FPM_PATH -v|grep fpm-fcgi|wc -l`
if [ $n -lt 1 ]; then
    echo '请输入正确的php-fpm路径地址'
    install_exit
fi
if [ ! -x $NGINX_PATH ]; then
    echo '当前所用登录用户缺少对指定的nginx的执行权限，安装退出'
    install_exit
fi
$NGINX_PATH -v >&/dev/null
if [ $? -gt 0 ]; then
    echo '请输入正确的nginx路径地址'
    install_exit
fi
if [ ! -d $SHARE_DIR ]; then
    mkdir -p $SHARE_DIR
fi
if [ -z $DAV_USER ]; then
    DIR_INFO=(`ls -l -d $SHARE_DIR`)
    DAV_USER=${DIR_INFO[2]}
    DAV_GROUP=${DIR_INFO[3]}
fi
if [ -z $DAV_GROUP ]; then
    DAV_GROUP="$DAV_USER"
fi
if [ id $DAV_USER >& /dev/null 2>&1 ]; then
    useradd -g $DAV_GROUP -s /sbin/nologin $DAV_USER
fi
if [ $DAV_USER != $CURRENT_USER ]; then
    sudo chown -R $DAV_USER:$DAV_GROUP $PHPDAV_ROOT
fi

echo 'NGINX_BIN="'$NGINX_PATH'"' > $PHPDAV_ROOT/conf/np.conf
echo 'PHP_FPM_BIN=”'$PHP_FPM_PATH'"' >> $PHPDAV_ROOT/conf/np.conf
echo 'DAV_USER="'$DAV_USER'"' >> $PHPDAV_ROOT/conf/np.conf
echo 'DAV_GROUP="'$DAV_GROUP'"' >> $PHPDAV_ROOT/conf/np.conf

echo '安装完成，你可以使用'
echo "bin/php-fpm start 命令启动php-fpm, bin/nginx start 启动nginx"
echo "然后就可以使用phpdav提供的webdav服务了"
echo "启动如遇端口号被占用，可修改$PHPDAV_ROOT/conf/nginx/nginx.conf 里nginx监听的端口号"
echo "如有其他问题，请联系作者：刘重量；手机：13439694341; 邮箱:13439694341@qq.com"
install_exit
