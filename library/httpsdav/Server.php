<?php

class HttpsDav_Server
{
    /**
     * @var array 处理请求路由（左边key对应请求的方法，右边value为对应匹配的文件路径和类名关键词，虽然PHP的类名不区分大小写）
     */
    private $_route = [
        'OPTIONS' => 'Options',
        'PROPFIND' => 'PropFind',
        'PROPPATCH' => 'PropPatch',
        'LOCK' => 'Lock',
        'UNLOCK' => 'UnLock',
        'HEAD' => 'Head',
        'GET' => 'Get',
        'PUT' => 'Put',
        'MKCOL' => 'Mkcol',
        'DELETE' => 'Delete',
        'COPY' => 'Copy',
        'MOVE' => 'Move',
    ];
    private static $_objInstance = null; //保存单一实例
    private static $objPropNs;           //保存属性命名空间实例

    /**
     * 构造函数，初始化信息
     * Httpsdav_Server constructor.
     * @param string|null $requestName
     * @throws Exception
     */
    private function __construct()
    {
        HttpsDav_Request::init();
        $this->initDavInfo();
        $requestPath = DAV_ROOT . str_replace('/', DIRECTORY_SEPARATOR, urldecode(REQUEST_URI));
        $clientCharset = mb_check_encoding($requestPath);
        if (!empty($clientCharset) && $clientCharset != 'UTF-8') {
            $requestPath = mb_convert_encoding($requestPath, 'UTF-8', $clientCharset);
        }
        define('REQUEST_PATH', $requestPath);
        define('REQUEST_RESOURCE', rtrim(REQUEST_PATH, DIRECTORY_SEPARATOR . '*'));
        self::$objPropNs = Dao_PropNs::getInstance();
    }

    /**
     * 初始化httpsdav信息
     * @throws Exception
     */
    private function initDavInfo()
    {
        $mangePath = Dao_DavConf::getDavRoot(HttpsDav_Request::$_Headers['Host']);
        if (empty($mangePath)) {
            Dao_DavConf::setDavRoot(HttpsDav_Request::$_Headers['Host'], DEF_CLOUD_ROOT);
        }
        define('DAV_ROOT', $mangePath);
    }

    /**
     * 初始化并返回一个httpsdav_Server对象实例，启动HttpsDav服务
     * @return HttpsDav_Server
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
        spl_autoload_register(function () {
            include_once BASE_ROOT . DIRECTORY_SEPARATOR . 'handlers' . DIRECTORY_SEPARATOR . $this->_route[REQUEST_METHOD] . '.php';
        });
        $className = 'Handler_' . $this->_route[REQUEST_METHOD];
        $message = 'Request Headers:' . PHP_EOL . print_r(HttpsDav_Request::$_Headers, true) . PHP_EOL;
        $msg = 'Request Resource: ' . REQUEST_RESOURCE . PHP_EOL . print_r($_SERVER, true) . PHP_EOL . $message;
        file_put_contents(BASE_ROOT . '/logs/access.log', $msg, FILE_APPEND);
        $objHandler = new $className();
        $arrResponse = $objHandler->execute();
        if (isset($arrResponse['code']) && isset(HttpsDav_StatusCode::$message[$arrResponse['code']])) {
            self::response_message($arrResponse);
        }
        fastcgi_finish_request();
        //$path = '/home/work/phpdav/logs/debug/' . $this->_route[REQUEST_METHOD] .'log';
        //$log = print_r(['server' => $_SERVER,'headers' => HttpsDav_Request::$_Headers, 'body' => HttpsDav_Request::getInputContent(), 'response' => $arrResponse], true);
        //file_put_contents($path, $log, FILE_APPEND);
    }

    /**
     * 根据命名空间uri获取命名空间id
     * @param string $uri
     * @return mixed
     */
    public static function getNsIdByUri($uri)
    {
        return self::$objPropNs->getNsIdByUri($uri);
    }

    /**
     * 根据命名空间id获取命名空间信息
     * @param int $id
     * @return mixed
     */
    public static function getNsInfoById($id)
    {
        return self::$objPropNs->getNsInfoById($id);
    }

    /**
     * 将路径转化为链接
     * @param string $path
     * @return string
     */
    public static function href_encode($path)
    {
        if ($path == DAV_ROOT) {
            return '/';
        }
        $path = substr($path, strlen(DAV_ROOT));
        $arrPath = explode(DIRECTORY_SEPARATOR, $path);
        foreach ($arrPath as $k => $v) {
            $arrPath[$k] = rawurlencode($v);
        }
        $href = implode('/', $arrPath);
        return $href;
    }

    /**
     * 将链接转为资源路径
     * @param string $href
     * @return string|null
     */
    public static function href_decode($href)
    {
        if (0 === strpos($href, 'http')) {
            if (0 === strpos($href, 'http://' . $_SERVER['HTTP_HOST'])) {
                $href = substr($href, strlen('http://' . $_SERVER['HTTP_HOST']));
            } elseif (0 === strpos($href, 'https://' . $_SERVER['HTTP_HOST'])) {
                $href = substr($href, strlen('https://' . $_SERVER['HTTP_HOST']));
            } else {
                return null;
            }
        }
        if (0 !== strpos($href, '/')) {
            return null;
        }
        $path = DAV_ROOT . urldecode($href);
        return $path;
    }

    /**
     * 输出应答信息
     * @param array $data
     */
    public static function response_message(array $data)
    {
        $headers = (!empty($data['headers']) && is_array($data['headers'])) ? $data['headers'] : [];
        $headers[] = Httpsdav_StatusCode::$message[$data['code']];
        if (!empty($data['body']) && is_array($data['body'])) {
            $xmlDoc = new DOMDocument('1.0', 'UTF-8');
            $xmlDoc->formatOutput = true;
            $element = self::xml_encode($xmlDoc, $data['body']);
            $xmlDoc->appendChild($element);
            $data['body'] = trim($xmlDoc->saveXML());
            $headers[] = 'Content-Type: application/xml; charset=UTF-8';
            $headers[] = 'Content-Length: ' . strlen($data['body']);
        }
        foreach ($headers as $header) {
            header($header);
        }
        if (isset($data['body']) && is_string($data['body'])) {
            file_put_contents('php://output', $data['body']);
        }
        $msg = ['Server'=>$_SERVER, 'Request' => ['Headers' => HttpsDav_Request::$_Headers], 'Response' => ['Headers'=>$headers]];
        if(REQUEST_METHOD != 'PUT'){
            $msg['Request']['Body'] =HttpsDav_Request::getInputContent();
        }
        if(REQUEST_METHOD != 'GET' && isset($data['body'])){
            $msg['Response']['Body'] = $data['body'];
        }
        file_put_contents(BASE_ROOT . '/logs/webdav/access.log', print_r($msg, true), FILE_APPEND);
        //$message = $_SERVER['REQUEST_METHOD'] . PHP_EOL . print_r($_SERVER, true) . PHP_EOL . HttpsDav_Request::getInputContent() . PHP_EOL . 'reaponse:' . PHP_EOL . print_r(headers_list(), true) . print_r($data, true) . PHP_EOL;
        //HttpsDav_Log::debug($message);
    }

    /**
     * 将代码运行的数组返回结果转化成xml格式
     * @param DOMDocument $xmlDoc
     * @param array $data
     * @return DOMElement
     */
    public static function xml_encode(DOMDocument &$xmlDoc, array $data)
    {
        $nsId = isset($data[2]) && is_numeric($data[2]) ? intval($data[2]) : NS_DAV_ID;
        $nsInfo = self::getNsInfoById($nsId);
        $nsUri = $nsInfo['uri'];
        $nsPrefix = $nsInfo['prefix'];
        $qualifiedName = $nsPrefix . ':' . $data[0];
        if (isset($data[1]) && is_array($data[1]) && !empty($data[1])) {
            $element = $xmlDoc->createElementNS($nsUri, $qualifiedName);
            foreach ($data[1] as $node) {
                $value = self::xml_encode($xmlDoc, $node);
                $element->appendChild($value);
            }
        } else {
            $element = $xmlDoc->createElementNS($nsUri, $qualifiedName, !isset($data[1]) || is_array($data[1]) ? null : strval($data[1]));
        }
        return $element;
    }

    /**
     * 将xml对象包含的元素转成成数组格式
     * @param DOMNode $xmlData
     * @return array|string
     */
    public static function xml_decode(DOMNode $xmlData)
    {
        $arrValue = [];
        $data = $xmlData->childNodes;
        if ($data->length > 0) {
            for ($i = 0; $i < $data->length; ++$i) {
                if (!empty($data->item($i)->localName)) {
                    $localName = trim($data->item($i)->localName);
                    $arrValue[] = [$localName, self::xml_decode($data->item($i)), Httpsdav_Server::getNsIdByUri($data->item($i)->namespaceURI)];
                }
            }
        }
        if (empty($arrValue)) {
            return empty($xmlData->textContent) ? '' : $xmlData->textContent;
        }
        return $arrValue;
    }
}