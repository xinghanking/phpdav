#!/bin/bash
#php-fpm 启动脚本

set -e
set -E
trap 'echo "Fail unexpectedly on ${BASH_SOURCE[0]}:$LINENO!" >&2' ERR

#获取PHPDAV的工作目录
BASE_ROOT=$(readlink -f `dirname "$0"`/../..)

# php-fpm路径
PROC_FILE="$BASE_ROOT/server/sbin/phpdav_php-fpm";

[ -x $PROC_FILE ] || PROC_FILE="$BASE_ROOT/server/bin/phpdav_php-fpm"
if [ ! -x $PROC_FILE ]; then
    echo 'No executable php-fpm file was found';
    exit 1;
fi

# 配置文件路径
CONFIGFILE="$BASE_ROOT/conf/php/php-fpm.conf"
PHP_INI="$BASE_ROOT/conf/php/php.ini"

[ ! -r $CONFIGFILE ] && echo "failed to read $CONFIGFILE" && exit 1
if [ $PROC_FILE -t -p $BASE_ROOT -y $CONFIGFILE >/dev/null 2>&1 ]; then
     $? -gt 0  && $PROC_FILE -t -p $BASE_ROOT -y $CONFIGFILE
fi

# PID文件路径(在php-fpm.conf设置)
PID_FILE="$BASE_ROOT/server/run/php-fpm.pid"

PROC_NAME='phpdav_php-fpm'
LOCK_UX="$BASE_ROOT/server/lock/$PROC_NAME"

DAV_USER_CONF="$BASE_ROOT/conf/php/davs/user.conf"
CURRENT_USER=`whoami`

pid=-1
if [ -r $PID_FILE ]; then
    pid=`cat $PID_FILE`
    if [ $(ps -p $pid|wc -l) -le 1 ]; then
        pid=-1
    fi
fi

rh_status() {
    rm -fr $LOCK_UX
    [ $pid -gt 0 ] && touch $LOCK_UX
    return $?
}

rh_start() {
    touch $LOCK_UX && echo -n "Starting $PROC_NAME ...    "
    if [ $? -eq 0 ] ; then
        if [ $CURRENT_USER = "root" ]; then
            sed -i 's/;user =/user =/g' $DAV_USER_CONF
            sed -i 's/;group =/group =/g' $DAV_USER_CONF
        fi
        $PROC_FILE -p $BASE_ROOT -c $PHP_INI -y $CONFIGFILE -D && echo -e "[ \e[32m OK \e[0m ]"
        retval=$?
        if [ $retval -ne 0 ]; then
            echo -e "[ \e[31m fail \e[0m ]"
        fi
        if [ $CURRENT_USER = "root" ]; then
            sed -i 's/user =/;user =/g' $DAV_USER_CONF
            sed -i 's/group =/;group =/g' $DAV_USER_CONF
        fi
    fi
}

rh_stop() {
    echo -n "Stopping $PROC_NAME ...    "
    if [ -r $PIDFILE ]; then
        if [ $pid -le 0 ]; then
            echo -e "wrong pid from $PIDFILE"
            return 1
        fi
        kill $pid || kill -9 $pid && rm -f $LOCK_UX
        retval=$?
        if [  $retval -eq 0 ]; then
            echo -e "[ \e[32m OK \e[0m ]"
            return 0
        fi
        echo -e " [ \e[31m fail \e[0m ]"
    else
        echo -e " The saved PID file could not be found. Maybe the program did not start."
        return 0
    fi
    return 1
}

case "$1" in
    status)
        rh_status
        ;;
    start)
        rh_status && echo "php-fpm has been is already running" && exit 1
        rh_start
        ;;
    stop)
        rh_status && rh_stop
        ;;
    restart)
        echo "Restarting $PROC_NAME"
        rh_status && rh_stop
        sleep 1
        rh_start
        ;;
    *)
         echo "Usage: $SCRIPTNAME {start|stop|restart}" >&2
         exit 3
        ;;
esac
exit 0
