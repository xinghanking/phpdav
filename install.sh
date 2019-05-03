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

PHP_FPM_PATH=
PHP_PATH=
NGINX_PATH=
DAV_USER=
DAV_GROUP=
CLOUD_PATH="$PHPDAV_ROOT/cloud"

for parm in $*;
do
    val=${parm#*=}
    case "$parm" in
        --php-fpm-path=*)   PHP_FPM_PATH="$val" ;;
        --php-path=*)       PHP_PATH="$val"     ;;
        --nginx-path=*)     NGINX_PATH="$val"   ;;
        --user=*)           DAV_USER="$val"     ;;
        --group=*)          DAV_GROUP="$val"    ;;
        --data-dir-path=*)  CLOUD_PATH="$val"   ;;
    esac
done;
if [ -z $PHP_FPM_PATH ]; then
    PHP_FPM_PATH=`which php-fpm`
fi
if [ -z $PHP_PATH ]; then
    PHP_PATH=`which php`
fi
if [ -z $NGINX_PATH ]; then
    NGINX_PATH=`which nginx`
fi
if [ -d $PHP_FPM_PATH ]; then
    PHP_FPM_PATH="$PHP_FPM_PATH/sbin/php-fpm"
fi
if [ -d $PHP_PATH ]; then
    PHP_PATH="$PHP_PATH/bin/php"
fi
if [ -d $NGINX_PATH ]; then
    NGINX_PATH="$NGINX_PATH/sbin/nginx"
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
n=`$PHP_PATH -v|grep cli|wc -l`
if [ $n -lt 1 ]; then
    echo '请输入正确的php解释器路径地址'
    install_exit
fi
if [ ! -d $CLOUD_PATH ]; then
    mkdir -p $CLOUD_PATH
fi
if [ -z $DAV_USER ]; then
    DIR_INFO=(`ls -l -d $CLOUD_PATH`)
    DAV_USER=${DIR_INFO[2]}
    DAV_GROUP=${DIR_INFO[3]}
else
    if [ $DAV_USER = "root" ]; then
        echo "请指定非root用户执行"
        install_exit
    fi
fi
if [ $DAV_USER = "root" ]; then
    DAV_USER="phpdav"
    DAV_GROUP="phpdav"
fi
if [ -z $DAV_GROUP ]; then
    DAV_GROUP="$DAV_USER"
fi
if [ id $DAV_USER >& /dev/null 2>&1 ]; then
    useradd -g $DAV_GROUP -s /sbin/nologin $DAV_USER
fi
if [ $CURRENT_USER = "root" ]; then
    chown -R $DAV_USER:$DAV_GROUP $CLOUD_PATH
    chmod -R 700 $CLOUD_PATH
    chown -R $DAV_USER:$DAV_GROUP $PHPDAV_ROOT/interface
    chmod -R 700 $PHPDAV_ROOT/interface
fi
chmod -R 700 $PHPDAV_ROOT
rm -fr $PHPDAV_ROOT/server
mkdir -p $PHPDAV_ROOT/server
cp -r conf/template/php $PHPDAV_ROOT/server/php
mkdir -p $PHPDAV_ROOT/server/nginx/logs
mkdir -p $PHPDAV_ROOT/server/run
mkdir -p $PHPDAV_ROOT/server/lock
mkdir -p $PHPDAV_ROOT/server/sbin
echo ";user = $DAV_USER" > $PHPDAV_ROOT/conf/php/davs/user.conf
echo ";group = $DAV_GROUP" >> $PHPDAV_ROOT/conf/php/davs/user.conf
ln -s $PHP_FPM_PATH $PHPDAV_ROOT/server/sbin/phpdav_php-fpm
ln -s $NGINX_PATH $PHPDAV_ROOT/server/sbin/phpdav_nginx
SERVER_NAMES=`hostname -I`
mkdir -p $PHPDAV_ROOT/conf/nginx/davs
cp $PHPDAV_ROOT/conf/template/nginx/cloud.conf.tpl $PHPDAV_ROOT/conf/nginx/davs/cloud.conf
sed -i "s#{server_name}#$SERVER_NAMES#g" $PHPDAV_ROOT/conf/nginx/davs/cloud.conf
sed -i "s#{base_root}#$PHPDAV_ROOT#g" $PHPDAV_ROOT/conf/nginx/davs/cloud.conf
sed -i "s#cloud_root = null#cloud_root = '$CLOUD_PATH'#g" $PHPDAV_ROOT/conf/config.ini.php
sed -i "s:#!/usr/bin/php:$PHP_PATH:g" $PHPDAV_ROOT/bin/phpdav_admin
chown -R $DAVUSER:$DAV_GROUP $PHPDAV_ROOT
echo '安装完成，你可以使用'
echo "./phpdav start"
echo "命令启动phpdav,也可以把路径$PHPDAV_ROOT/bin/phpdav加到系统环境变量里方便使用"
echo "启动如遇端口号被占用，可修改$PHPDAV_ROOT/conf/nginx/davs/cloud.conf 第二行listen指令监听的端口号"
echo "如有其他问题，请联系作者：刘重量；手机：13439694341; 邮箱:13439694341@qq.com"
install_exit
