<?php

class Dav_Utils
{
    /**
     * @var array 处理请求路由（左边key对应请求的方法，右边value为对应匹配的文件路径和类名关键词，虽然PHP的类名不区分大小写）
     */
    public static $_Methods = [
        'OPTIONS'   => 'Options',
        'PROPFIND'  => 'PropFind',
        'PROPPATCH' => 'PropPatch',
        'LOCK'      => 'Lock',
        'UNLOCK'    => 'UnLock',
        'HEAD'      => 'Head',
        'GET'       => 'Get',
        'PUT'       => 'Put',
        'MKCOL'     => 'Mkcol',
        'DELETE'    => 'Delete',
        'COPY'      => 'Copy',
        'MOVE'      => 'Move',
    ];
    /**
     * @var array header对应预定义变量
     */
    public static $_Headers = [
        'Host'                => 'HTTP_HOST',
        'User-Agent'          => 'HTTP_USER_AGENT',
        'Content-Type'        => 'HTTP_CONTENT_TYPE',
        'Content-Length'      => 'HTTP_CONTENT_LENGTH',
        'Depth'               => 'HTTP_DEPTH',
        'Expect'              => 'HTTP_EXPECT',
        'Authorization'       => 'HTTP_AUTHORIZATION',
        'If-None-Match'       => 'HTTP_IF_NONE_MATCH',
        'If-Match'            => 'HTTP_IF_MATCH',
        'If-Range'            => 'HTTP_IF_RANGE',
        'Last-Modified'       => 'HTTP_LAST_MODIFIED',
        'If-Modified-Since'   => 'HTTP_IF_MODIFIED_SINCE',
        'If-Unmodified-Since' => 'HTTP_IF_UNMODIFIED_SINCE',
        'Range'               => 'HTTP_RANGE',
        'Timeout'             => 'HTTP_TIMEOUT',
        'If'                  => 'HTTP_IF',
        'Lock-Token'          => 'HTTP_LOCK_TOKEN',
        'Overwrite'           => 'HTTP_OVERWRITE',
        'Destination'         => 'HTTP_DESTINATION',
        'Request-Id'          => 'REQUEST_ID',
        'Request-Body-File'   => 'REQUEST_BODY_FILE',
        'Redirect-Status'     => 'REDIRECT_STATUS',
    ];

    public static $_Body;
    public static $_objXml;

    public static function getHeaders()
    {
        foreach (self::$_Headers as $field => $vkey) {
            if (isset($_SERVER[$vkey]) && $_SERVER[$vkey] !== '') {
                $_REQUEST['HEADERS'][$field] = trim($_SERVER[$vkey]);
            }
        }
        if (empty($_REQUEST['HEADERS']['Content-Type']) && !empty($_SERVER['CONTENT-TYPE'])) {
            $_REQUEST['HEADERS']['Content-Type'] = $_SERVER['CONTENT-TYPE'];
        }
        if (!empty($_REQUEST['HEADERS']['Content-Type'])) {
            $_REQUEST['HEADERS']['Content-Type'] = strtok($_REQUEST['HEADERS']['Content-Type'], ';');
        }
        $uri = empty($_SERVER['REQUEST_URI']) ? '/' : trim($_SERVER['REQUEST_URI']);
        if (substr($uri, 0, 1) != '/') {
            $uri = '/' . $uri;
        }
        $_REQUEST['HEADERS']['Uri'] = $uri;
        if (empty($_REQUEST['HEADERS']['Destination']) && !empty($_SERVER['DESTINATION'])) {
            $_REQUEST['HEADERS']['Destination'] = trim($_SERVER['DESTINATION']);
        }
        $_REQUEST['HEADERS']['Method'] = strtoupper($_SERVER['REQUEST_METHOD']);
        $_REQUEST['DAV_HOST'] = strtok($_SERVER['HTTP_HOST'], ':');
    }

    /**
     * 预处理设置信息
     * @throws Exception
     */
    public static function getDavSet()
    {
        $documentUri = strtok($_REQUEST['HEADERS']['Uri'], '?');
        $requestPath = str_replace('/', DIRECTORY_SEPARATOR, urldecode($documentUri));
        $clientCharset = mb_check_encoding($requestPath);
        if (!empty($clientCharset) && $clientCharset != SERVER_LANG) {
            $requestPath = mb_convert_encoding($requestPath, SERVER_LANG, $clientCharset);
        }
        $_REQUEST['HEADERS']['Base-Name'] = basename($requestPath);
        $_REQUEST['DOCUMENT_ROOT'] = Dao_DavConf::getDavRoot($_REQUEST['HEADERS']['Host']);
        $host = $_REQUEST['DAV_HOST'];
        if (empty($_REQUEST['DOCUMENT_ROOT'])) {
            if (empty($_SERVER['NET_DISKS'][$host]['path'])) {
                if (empty($_SERVER['NET_DISKS']['default']['path'])) {
                    throw new Exception('Not Found', 404);
                }
                $_REQUEST['DOCUMENT_ROOT'] = $_SERVER['NET_DISKS']['default']['path'];
                $_SESSION['user_list'] = $_SERVER['NET_DISKS']['default']['user_list'];
            } else {
                $_REQUEST['DOCUMENT_ROOT'] = $_SERVER['NET_DISKS'][$host]['path'];
                $_SESSION['user_list'] = $_SERVER['NET_DISKS'][$host]['user_list'];
            }
            if (!file_exists($_REQUEST['DOCUMENT_ROOT'])) {
                Dav_PhyOperation::createDir($_REQUEST['DOCUMENT_ROOT']);
            }
            Dao_DavConf::setDavRoot($_REQUEST['HEADERS']['Host'], $_REQUEST['DOCUMENT_ROOT']);
        } elseif (!empty($_SERVER['NET_DISKS'][$host]['path']) && $_SERVER['DOCUMENT_ROOT'] != $_SERVER['NET_DISKS'][$host]['path']) {
            $_REQUEST['DOCUMENT_ROOT'] = $_SERVER['NET_DISKS'][$host]['path'];
            Dao_DavConf::setDavRoot($_REQUEST['HEADERS']['Host'], $_REQUEST['DOCUMENT_ROOT'], true);
        }
        $_REQUEST['HEADERS']['Path'] = $_REQUEST['DOCUMENT_ROOT'] . $requestPath;
        $_REQUEST['HEADERS']['Resource'] = rtrim($_REQUEST['HEADERS']['Path'], DIRECTORY_SEPARATOR . '*');
        self::$_Body = null;
        self::$_objXml = null;
    }

    /**
     * 获取请求headers中的lock token 信息
     * @return array
     */
    public static function getLockToken()
    {
        $arrLockTokenList = [];
        if (isset($_REQUEST['HEADERS']['If'])) {
            preg_match_all('/<(.*)>/i', $_REQUEST['HEADERS']['If'], $matches);
            if (!empty($matches[1])) {
                $arrLockTokenList = $matches[1];
            }
        }
        if (isset($_REQUEST['HEADERS']['Lock-Token'])) {
            preg_match_all('/<(.*)>/i', $_REQUEST['HEADERS']['Lock-Token'], $matches);
            if (!empty($matches[1])) {
                $arrLockTokenList = array_merge($arrLockTokenList, $matches[1]);
            }
        }
        if (isset($_SESSION['LOCK_TOKEN'])) {
            $arrLockTokenList = array_merge($arrLockTokenList, $_SESSION['LOCK_TOKEN']);
        }
        return $arrLockTokenList;
    }

    /**
     * 返回客户端发来请求headers中包含的etag信息的数组
     * @return array
     */
    public static function getETagList()
    {
        $fieldList = ['If-Range', 'If-Match', 'If-None-Match'];
        $eTagList = [];
        foreach ($fieldList as $field) {
            if (isset($_REQUEST['HEADERS'][$field])) {
                if ('W/' == strtoupper(substr($_REQUEST['HEADERS'][$field], 0, 2))) {
                    $eTagList[] = [
                        'is_w' => true,
                        'list' => explode(',', strtoupper(substr($_REQUEST['HEADERS'][$field], 2)))
                    ];
                } elseif (substr($_REQUEST['HEADERS'][$field], 0, 1) === '"') {
                    $eTagList[] = [
                        'is_w' => false,
                        'list' => explode(',', $_REQUEST['HEADERS'][$field])
                    ];
                }
            }
        }
        return $eTagList;
    }

    /**
     * 获取客户端发来请求headers中包含的上次文件最后修改时间信息
     * @return int
     */
    public static function getLastModified()
    {
        $fieldList = ['Last-Modified', 'If-Modified-Since', 'If-Unmodified-Since', 'If-Range'];
        $modifiedTimeList = [];
        foreach ($fieldList as $field) {
            if (isset($_REQUEST['HEADERS'][$field])) {
                $lastModified = strtotime($_REQUEST['HEADERS'][$field]);
                if ($lastModified > 0) {
                    $modifiedTimeList[] = $lastModified;
                }
            }
        }
        return empty($modifiedTimeList) ? null : min($modifiedTimeList);
    }

    /**
     * 获取原始输入数据主体
     * @return false|string
     */
    public static function getInputContent()
    {
        if (isset($_REQUEST['HEADERS']['Content-Length']) && $_REQUEST['HEADERS']['Content-Length'] == 0) {
            return '';
        }
        if (is_null(self::$_Body)) {
            self::$_Body = call_user_func([START_CLASS, 'get_body']);
        }
        return self::$_Body;
    }

    /**
     * 储存接收到的报文body部分到指定路径
     * @param string $path
     * @return false|int
     */
    public static function accept_data($path)
    {
        return call_user_func([START_CLASS, 'accept_data'], $path);
    }

    /**
     * 保存上传的文件数据到指定路径
     * @param string $path
     * @return false|int
     */
    public static function save_data($path)
    {
        return call_user_func([START_CLASS, 'save_data'], $path);
    }

    /**
     * 获取格式化处理后xml对象的请求数据主体
     * @return DOMDocument|null
     */
    public static function getObjXml()
    {
        if (empty(self::$_objXml) || !(self::$_objXml instanceof DOMDocument)) {
            self::$_objXml = new DOMDocument();
            $requestBody = self::getInputContent();
            if (empty($requestBody)) {
                return null;
            }
            $requestBody = trim($requestBody);
            if (!empty($requestBody) || isset(self::$_Headers['Content-Type']) && in_array(self::$_Headers['Content-Type'], ['application/xml', 'text/xml'])) {
                $inputEncoding = mb_detect_encoding($requestBody);
                if (!empty($inputEncoding) && 'UTF-8' != $inputEncoding) {
                    $requestBody = mb_convert_encoding($requestBody, 'UTF-8', $inputEncoding);
                }
                self::$_objXml->loadXML($requestBody);
            }
        }
        return self::$_objXml;
    }

    /**
     * 获取对象格式的参数key所指定部分的请求数据
     * @param string $key 指定部分的路径标签
     * @return DOMDocument|DOMNodeList|null
     */
    public static function getObjElements($key)
    {
        $objReqData = self::getObjXml();
        if (empty($objReqData)) {
            return null;
        }
        $arrTags = explode('/', $key);
        $tag = array_shift($arrTags);
        $objReqData = $objReqData->getElementsByTagNameNS(NS_DAV_URI, $tag);
        if ($tag != $key) {
            while (count($arrTags) > 0 && $objReqData->length > 0) {
                $tag = array_shift($arrTags);
                $objReqData = $objReqData->item(0)->getElementsByTagNameNS(NS_DAV_URI, $tag);
                if (empty($objReqData) || $objReqData->length == 0) {
                    return null;
                }
            }
        }
        return $objReqData;
    }

    /**
     * 获取对象格式的参数key所指定部分的请求数据
     * @param string $key 指定部分的路径标签
     * @return DOMNode|null
     */
    public static function getDomElement($key)
    {
        $nodeList = self::getObjElements($key);
        if (empty($nodeList)) {
            return null;
        }
        return $nodeList->item(0);
    }

    /**
     * 获取数据列表格式的指定key标识的向httpsdav服务器请求消息的部分内容
     * @param string $key 指定部分的路径标签
     * @return array
     */
    public static function getElementList($key)
    {
        $arrData = [];
        $nodeData = self::getObjElements($key);
        if (!empty($nodeData)) {
            for ($i = 0; $i < $nodeData->length; ++$i) {
                $arrData[] = trim($nodeData->item($i)->nodeValue);
            }
        }
        return $arrData;
    }

    /**
     * 获取路径标签 key 标记的
     * @param string $key
     * @return string
     */
    public static function getValue($key)
    {
        $node = self::getDomElement($key);
        if (empty($node)) {
            return '';
        }
        return $node->textContent;
    }

    /**
     * 根据命名空间uri获取命名空间id
     * @param string $uri
     * @return mixed
     */
    public static function getNsIdByUri($uri)
    {
        return Dao_PropNs::getInstance()->getNsIdByUri($uri);
    }

    /**
     * 根据命名空间id获取命名空间信息
     * @param int $id
     * @return mixed
     */
    public static function getNsInfoById($id)
    {
        return Dao_PropNs::getInstance()->getNsInfoById($id);
    }

    /**
     * 将路径转化为链接
     * @param string $path
     * @return string
     */
    public static function href_encode($path)
    {
        if ($path == $_REQUEST['DOCUMENT_ROOT']) {
            return '/';
        }
        $path = substr($path, strlen($_REQUEST['DOCUMENT_ROOT']));
        if (SERVER_LANG != 'UTF-8') {
            $path = mb_convert_encoding($path, 'UTF-8', SERVER_LANG);
        }
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
            if (0 === strpos($href, 'http://' . $_REQUEST['HEADERS']['Host'])) {
                $href = substr($href, strlen('http://' . $_REQUEST['HEADERS']['Host']));
            } elseif (0 === strpos($href, 'https://' . $_REQUEST['HEADERS']['Host'])) {
                $href = substr($href, strlen('https://' . $_REQUEST['HEADERS']['Host']));
            } else {
                return null;
            }
        }
        if (0 !== strpos($href, '/')) {
            return null;
        }
        $path = urldecode($href);
        $clientLang = mb_check_encoding($path);
        if (SERVER_LANG != $clientLang) {
            $path = mb_convert_encoding($path, SERVER_LANG, $clientLang);
        }
        $path = $_REQUEST['DOCUMENT_ROOT'] . $path;
        return $path;
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
                    $arrValue[] = [$localName, self::xml_decode($data->item($i)), self::getNsIdByUri($data->item($i)->namespaceURI)];
                }
            }
        }
        if (empty($arrValue)) {
            return empty($xmlData->textContent) ? '' : $xmlData->textContent;
        }
        return $arrValue;
    }

    public static function auth()
    {
        if (empty($_SERVER['NET_DISKS'][$_REQUEST['DAV_HOST']]['is_auth']) || !empty($_SESSION['auth'])) {
            return true;
        }

        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            if (isset($_SERVER['NET_DISKS'][$_REQUEST['DAV_HOST']]['user_list'][$_SERVER['PHP_AUTH_USER']]) && $_SERVER['NET_DISKS'][$_REQUEST['DAV_HOST']]['user_list'][$_SERVER['PHP_AUTH_USER']] == $_SERVER['PHP_AUTH_PW']) {
                $_SESSION['auth'] = true;
                return true;
            }
            throw new Exception(Dav_Status::$Msg['403'], 403);
        }

        if (isset($_REQUEST['HEADERS']['Authorization'])) {
            $authInfo = preg_split('/\s+/', $_REQUEST['HEADERS']['Authorization']);
            $authInfo = base64_decode(trim($authInfo[1]));
            $authInfo = explode(':', $authInfo);
            if (isset($_SERVER['NET_DISKS'][$_REQUEST['DAV_HOST']]['user_list'][$authInfo[0]]) && $_SERVER['NET_DISKS'][$_REQUEST['DAV_HOST']]['user_list'][$authInfo[0]] == $authInfo[1]) {
                $_SESSION['auth'] = true;
                return true;
            }
            throw new Exception(Dav_Status::$Msg['403'], 403);
        }
        throw new Exception(Dav_Status::$Msg['401'], 401);
    }

    /**
     * 输出应答信息
     * @param array $data
     */
    public static function response_message(array $data)
    {
        $headers = [Dav_Status::$Msg[$data['code']]];
        if (!empty($data['headers'])) {
            $headers = array_merge($headers, $data['headers']);
        }
        if (!isset($data['body'])) {
            $headers[] = 'Content-Length: 0';
            $headers[] = 'User-Agent: phpdav/2.0(Unix)';
            $headers[] = 'Server: phpdav/2.0(Unix)';
        } elseif (is_array($data['body'])) {
            $xmlDoc = new DOMDocument('1.0', 'UTF-8');
            $xmlDoc->formatOutput = true;
            $element = self::xml_encode($xmlDoc, $data['body']);
            $xmlDoc->appendChild($element);
            $data['body'] = trim($xmlDoc->saveXML());
            $headers[] = 'Content-Type: application/xml; charset=UTF-8';
            $headers[] = 'Content-Length: ' . strlen($data['body']);
        }
        $headers = array_unique($headers);
        self::response_headers($headers);
        if (isset($data['body']) && is_string($data['body'])) {
            self::response_body($data['body']);
        }
    }

    /**
     *发送返回报文headers部分
     * @param array $headers
     */
    public static function response_headers($headers)
    {
        return call_user_func([START_CLASS, 'response_headers'], $headers);
    }

    /**
     * 发送返回报文body部分
     * @param string $body
     * @return mixed
     */
    public static function response_body($body)
    {
        return call_user_func([START_CLASS, 'response_body'], $body);
    }
}
