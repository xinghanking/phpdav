<?php

class Dav_Server
{
    private static $_objInstance = null; //保存单一实例
    private static $objPropNs;           //保存属性命名空间实例

    /**
     * 构造函数，初始化信息
     * Dav_Server constructor.
     * @param string|null $requestName
     * @throws Exception
     */
    private function __construct()
    {
        Dav_Request::getHeaders();
        $this->initDavInfo();
        $requestPath = DAV_ROOT . str_replace('/', DIRECTORY_SEPARATOR, urldecode(Dav_Request::$_Headers['Uri']));
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
        $mangePath = Dao_DavConf::getDavRoot(Dav_Request::$_Headers['Host']);
        if(false === defined('DAV_ROOT')){
            if (empty($mangePath)) {
                Dao_DavConf::setDavRoot(Dav_Request::$_Headers['Host'], DEF_CLOUD_ROOT);
                $mangePath = DEF_CLOUD_ROOT;
            }
            define('DAV_ROOT', $mangePath);
        }elseif($mangePath != DAV_ROOT) {
            Dao_DavConf::setDavRoot(Dav_Request::$_Headers['Host'], DAV_ROOT, true);
        }
    }

    /**
     * 初始化并返回一个Dav_Server对象实例，启动HttpsDav服务
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
        spl_autoload_register(function () {
            include_once BASE_ROOT . DIRECTORY_SEPARATOR . 'handlers' . DIRECTORY_SEPARATOR . Dav_Request::$_Method[Dav_Request::$_Headers['Method']] . '.php';
        });
        $className = 'Handler_' . Dav_Request::$_Method[Dav_Request::$_Headers['Method']];
        $objHandler = new $className();
        $arrResponse = $objHandler->execute();
        if (isset($arrResponse['code']) && isset(Dav_Status::$Msg[$arrResponse['code']])) {
            self::response_message($arrResponse);
        }
        fastcgi_finish_request();
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
        $headers[] = Dav_Status::$Msg[$data['code']];
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
        file_put_contents('/home/web/phpdav/re.log', print_r($_SERVER, true), FILE_APPEND);
        file_put_contents('/home/web/phpdav/re.log', print_r($headers, true) .PHP_EOL, FILE_APPEND);
        if (isset($data['body']) && is_string($data['body'])) {
            file_put_contents('/home/web/phpdav/re.log', $data['body'] . PHP_EOL, FILE_APPEND);
            file_put_contents('php://output', $data['body']);
        }
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
                    $arrValue[] = [$localName, self::xml_decode($data->item($i)), Dav_Server::getNsIdByUri($data->item($i)->namespaceURI)];
                }
            }
        }
        if (empty($arrValue)) {
            return empty($xmlData->textContent) ? '' : $xmlData->textContent;
        }
        return $arrValue;
    }
}
