<?php


class Dav_Request
{
    public static $_Method = [
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
        'MOVE'      => 'Move'
    ];
    public static $_Headers = [
        'Host'                => 'HTTP_HOST',
        'User-Agent'          => 'HTTP_USER_AGENT',
        'Content-Type'        => 'HTTP_CONTENT_TYPE',
        'Content-Length'      => 'HTTP_CONTENT_LENGTH',
        'Depth'               => 'HTTP_DEPTH',
        'Expect'              => 'HTTP_EXPECT',
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
    private static $_objXml;
    private static $_object;

    /**
     * 初始化这个保存并格式化请求数据存储的对象
     */
    public static function getHeaders()
    {
        foreach (self::$_Headers as $field => $vkey) {
            if (isset($_SERVER[$vkey]) && $_SERVER[$vkey] !== '') {
                self::$_Headers[$field] = trim($_SERVER[$vkey]);
            } else {
                unset(self::$_Headers[$field]);
            }
        }
        if (empty(self::$_Headers['Content-Type']) && !empty($_SERVER['CONTENT-TYPE'])) {
            self::$_Headers['Content-Type'] = $_SERVER['CONTENT-TYPE'];
        }
        if (!empty(self::$_Headers['Content-Type'])) {
            self::$_Headers['Content-Type'] = strtok(self::$_Headers['Content-Type'], ';');
        }
        $uri = empty($_SERVER['REQUEST_URI']) ? '/' : trim($_SERVER['REQUEST_URI']);
        if ($uri{0} != '/') {
            $uri = '/' . $uri;
        }
        if (empty(self::$_Headers['Destination']) && !empty($_SERVER['DESTINATION'])) {
            self::$_Headers['Destination'] = trim($_SERVER['DESTINATION']);
        }
        self::$_Headers['Method'] = strtoupper($_SERVER['REQUEST_METHOD']);
        self::$_Headers['Uri'] = $uri;
        self::$_Headers['Href'] = rtrim($uri, '*');
    }

    /**
     * 获取请求headers中的lock token 信息
     * @return array
     */
    public static function getLockToken()
    {
        $arrLockTokenList = [];
        if (isset(self::$_Headers['If'])) {
            preg_match_all('/<(.*)>/i', self::$_Headers['If'], $matches);
            if (!empty($matches[1])) {
                $arrLockTokenList = $matches[1];
            }
        }
        if (isset(self::$_Headers['Lock-Token'])) {
            preg_match_all('/<(.*)>/i', self::$_Headers['Lock-Token'], $matches);
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
            if (isset(self::$_Headers[$field])) {
                if ('W/' == strtoupper(substr(self::$_Headers[$field], 0, 2))) {
                    $eTagList[] = [
                        'is_w' => true,
                        'list' => explode(',', strtoupper(substr(self::$_Headers[$field], 2)))
                    ];
                } elseif (self::$_Headers[$field]{0} == '"') {
                    $eTagList[] = [
                        'is_w' => false,
                        'list' => explode(',', self::$_Headers[$field])
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
            if (isset(self::$_Headers[$field])) {
                $lastModified = strtotime(self::$_Headers[$field]);
                if ($lastModified > 0) {
                    $modifiedTimeList[] = $lastModified;
                }
            }
        }
        return empty($modifiedTimeList) ? null : min($modifiedTimeList);
    }

    /**
     * 获取原始输入数据主体
     * @param int $start 偏移量
     * @param int $length 一次获取的最大内容长度 在php7.3.3版本，length传null在shell下会被当成传0字长不能取到全部字符串
     * @return false|string
     */
    public static function getInputContent($start = 0, $length = MAX_READ_LENGTH)
    {
        if ($length <= 0) {
            $requestBody = file_get_contents('php://input', false, null, $start);
        } else {
            $requestBody = file_get_contents('php://input', false, null, $start, $length);
        }
        return $requestBody;
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
     * @return DOMElement|null
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
}