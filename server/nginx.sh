#!/bin/sh

# Source function library.
. /etc/rc.d/init.d/functions

#获取PhpDav的工作目录
BASE_ROOT=$(readlink -f `dirname "$0"`/..)

#nginx程序地址
NGINX_EXEC="$BASE_ROOT/server/sbin/nginx"

#nginx运行目录
NGINX_ROOT="$BASE_ROOT/server/nginx"

#nginx配置
NGINX_CONF="$BASE_ROOT/conf/nginx/nginx.conf"

LOCK_UX="$BASE_ROOT/server/lock/nginx"

[ ! -x $NGINX_EXEC ] && echo "nginx exec file is not set or corrupted" && exit 5
[ ! -r $NGINX_CONF ] && echo "nginx config file is not set or can not read" && exit 6

start() {
    echo -n $"Starting nginx: "
    $NGINX_EXEC -p $NGINX_ROOT -c $NGINX_CONF
    retval=$?
    [ $retval -eq 0 ] && touch $LOCK_UX && echo  -e "[ \e[32m OK \e[0m ]" || echo  -e "[ \e[31m fail \e[0m ]"
    return $retval
}

stop() {
    echo -n $"Stopping nginx: "
    $NGINX_EXEC -p $NGINX_ROOT -c $NGINX_CONF -s stop
    retval=$?
    [ $retval -eq 0 ] && rm -f $LOCK_UX && echo  -e "[ \e[32m OK \e[0m ]" || echo  -e "[ \e[31m fail \e[0m ]"
    return $retval
}

reload() {
    echo -n $"Reloading nginx: "
    $NGINX_EXEC -p $NGINX_ROOT -c $NGINX_CONF -s reload
    RETVAL=$?
    return $retval
}

case "$1" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        stop
        sleep 1
        start
        ;;
    reload)
        reload
        ;;
    *)
        echo $"Usage: $0 {start|stop|restart|reload}"
        exit 2
esac