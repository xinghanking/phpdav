<?php

/**
 * Class Method_Options
 */
class Method_Options extends Dav_Method
{
    /**
     * 执行客户端通过OPTIONS方法发来的请求任务并返回数组格式化结果
     * @return array
     */
    public function handler()
    {
        $response = [
            'code'    => 200,
            'headers' => [
                'Accept-Charset: utf-8',
                'DAV: 1,2,3',
                'MS-Author-Via: DAV',
                'Allow: OPTIONS,GET,HEAD,DELETE,PROPFIND,PROPPATCH,COPY,MKCOL,MOVE,PUT,LOCK,UNLOCK',
                'Content-Length: 0',
                'Date: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT'
            ]
        ];
        return $response;
    }

    /**
     * 获取并数组格式化客户端发来的请求数据
     * @return mixed|void
     */
    protected function getArrInput()
    {
        //HttpsDav_Log::debug(json_encode(HttpsDav_Request::$_Headers, JSON_UNESCAPED_UNICODE));
    }
}
