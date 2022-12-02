<?php

/**
 * Class Dav_Server
 */
class Dav_Server
{
    private static $_objInstance = null; //保存单一实例

    /**
     * 构造函数，初始化信息
     * Httpsdav_Server constructor.
     * @param string|null $requestName
     * @throws Exception
     */
    private function __construct()
    {
        Dav_Utils::getHeaders();
        Dav_Utils::getDavSet();
        Dav_Utils::auth();
    }

    /**
     * 初始化并返回一个httpsdav_Server对象实例，启动HttpsDav服务
     * @return Dav_Server
     * @throws Exception
     */
    public static function init()
    {
        if (!(self::$_objInstance instanceof self)) {
            self::$_objInstance = new self();
        }
        return self::$_objInstance;
    }

    public function start()
    {
        define('START_CLASS', __CLASS__);
        $className = 'Method_' . Dav_Utils::$_Methods[$_REQUEST['HEADERS']['Method']];
        $objHandler = new $className();
        $arrResponse = $objHandler->execute();
        if (isset($arrResponse['code']) && isset(Dav_Status::$Msg[$arrResponse['code']])) {
            self::response_message($arrResponse);
        }
        fastcgi_finish_request();
    }

    /**
     * 输出应答信息
     * @param array $data
     */
    public static function response_message(array $data)
    {
        foreach ($data['header'] as $field) {
            header($field);
        }
        if (isset($data['body']) && is_string($data['body'])) {
            file_put_contents('php://output', $data['body']);
        }
    }

    /**
     * 返回报文header部分
     * @param array $arrHeaders
     */
    public static function response_headers($arrHeaders)
    {
        foreach ($arrHeaders as $header) {
            header($header);
        }
    }

    /**
     * 输出报文body部分
     * @param string $body
     */
    public static function response_body($body)
    {
        return file_put_contents('php://output', $body);
    }

    /**
     * 获取报文body部分
     * @return false|string
     */
    public static function get_body()
    {
        return file_get_contents('php://input');
    }

    /**
     * 储存接收到的报文body部分到指定路径
     * @param string $path
     * @return false|int
     */
    public static function accept_data($path)
    {
        return file_put_contents($path, self::get_body(), FILE_APPEND);
    }

    /**
     * 保存上传的文件到指定路径
     * @param string $path
     * @return false|int
     */
    public static function save_data($path)
    {
        $inPut = fopen('php://input', 'r');
        $save = fopen($path, 'w+');
        $size = stream_copy_to_stream($inPut, $save);
        fclose($inPut);
        fclose($save);
        return $size;
    }
}
