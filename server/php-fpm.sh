#!/bin/bash
#php-fpm 启动脚本

set -e
set -E
trap 'echo "Fail unexpectedly on ${BASH_SOURCE[0]}:$LINENO!" >&2' ERR

#获取PHPDAV的工作目录
BASE_ROOT=$(readlink -f `dirname "$0"`/..)

# php-fpm路径
PROC_FILE="$BASE_ROOT/server/sbin/php-fpm";

[ -x $PROC_FILE ] || PROC_FILE="$BASE_ROOT/server/bin/php-fpm"
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
PIDFILE=$BASE_ROOT/server/var/run/php-fpm.pid

#获取pid
pid=-1
if [[ -r $PIDFILE ]]; then
    pid=`cat $PIDFILE`
    if [ $(ps -p $pid|wc -l) -le 1 ]; then
        pid=-1
    fi
fi

PROC_NAME='php-fpm'
LOCK_UX="$BASE_ROOT/var/lock/$PROC_NAME"

rh_start() {
    if [ ! -e $LOCK_UX ]; then
        echo -n "Starting $PROC_NAME ...    "
        $PROC_FILE -p $BASE_ROOT -c $PHP_INI -y $CONFIGFILE && touch $LOCK_UX && echo -e "[ \e[32m OK \e[0m ]"
        retval=$?
        if [ $retval -ne 0 ]; then
            echo -e "[ \e[31m fail \e[0m ]"
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

rh_reload() {
    if [ $pid -gt 0 ]; then
        kill -HUP $pid
    else
        rh_start
    fi
    retval=$?
    [ $retval -eq 0 ] && echo -e "reload $PROC_NAME ...    [ \e[32m OK \e[0m ]" || echo -e " [ \e[31m fail \e[0m ]"
}

case "$1" in
    start)
        [ $pid -gt 0 ] && echo "php-fpm has been is already running" && exit 1
        rh_start
        ;;
    stop)
        rh_stop
        ;;
    reload)
        echo "Reloading $PROC_NAME configuration..."
        rh_reload
        ;;
    restart)
        echo "Restarting $PROC_NAME"
        rh_stop
        sleep 1
        rh_start
        ;;
    *)
         echo "Usage: $SCRIPTNAME {start|stop|restart|reload}" >&2
         exit 3
        ;;
esac
exit 0
