<?php

/**
 * @name Service_Page_Get
 * @desc get page service, 和action对应，支持断点下载
 * @author 刘重量(13439694341@qq.com)
 */
class Service_Page_Get
{
    const MAX_CONTENT_LENGTH = 10485760;
    private $objResource;

    public function __construct()
    {
        $this->objResource = Service_Data_Resource::getInstance();
    }

    /**
     * set注入
     * @param property
     * @param value
     * @return null
     *
     **/
    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    /**
     *
     * @param array input
     * @return array result
     **/
    public function execute($arrInput)
    {
        Bd_Log::debug('Get Page service called');
        $arrMessage = ['code' => 200];
        try {
            //服务器获取不到资源，返回服务器原因的状态值
            if (!isset($this->objResource->status)) {
                return ['code' => 500];
            }
            //资源不存在
            if ($this->objResource->status == Service_Data_Resource::STATUS_DELETE) {
                return ['code' => 404];
            }
            //请求的是一个资源目录
            if ($this->objResource->is_collection == Service_Data_Resource::BOOL_YES) {
                $arrChildren = $this->objResource->getChildren();
                if (empty($arrChildren)) {
                    return ['code' => 204];
                }
                $locationPath = key($arrChildren);
                $href = Httpsdav_Server::href_encode($locationPath);
                return [
                    'code'         => 200,
                    'Content-Type' => 'text/html',
                    'body'         => '<!DOCTYPE html><html><head><meta charset="utf-8"><title>使用说明</title></head><body><h4>使用说明</h4><ul><li>window 连接说明文档<br/><a href="http://wiki.baidu.com/pages/viewpage.action?pageId=729038654"> http://wiki.baidu.com/pages/viewpage.action?pageId=729038654</a></li><li>mac 连接说明文档<br/><a href="http://wiki.baidu.com/pages/viewpage.action?pageId=729038682"> http://wiki.baidu.com/pages/viewpage.action?pageId=729038682</a></li></ul></body></html>',
                ];
            }
            $eTag = '"' . $this->objResource->etag . '"';
            if (isset($arrInput['If-Match']) && !in_array($eTag, $arrInput['If-Match'])) {
                return ['code' => 416];
            }
            $lastModified = strtotime($this->objResource->getlastmodified);
            $href = Httpsdav_Server::href_encode($this->objResource->path);
            $arrResponse = [
                'headers' => [
                    'Content-Location: ' . $href,
                    'ETag: ' . $eTag,
                    'Last-Modified:' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
                    'Accept-Ranges: bytes',
                    'Content-Length: ' . $this->objResource->getcontentlength,
                    'Content-Type:' . $this->objResource->getcontenttype,
                ],
            ];
            if (true === $this->objResource->noChanged) {
                $arrResponse['code'] = 304;
                return $arrResponse;
            }
            $fileSize = $this->objResource->getcontentlength;
            if (0 == $fileSize) {
                $arrResponse['code'] = 200;
                return $arrResponse;
            }
            if (empty($arrInput['Range'])) {
                return $this->outMaxContent();
            }
            return $this->getResourceContent($arrInput['Range']);
        } catch (Exception $e) {
            Bd_Log::warning($e->getMessage(), $e->getCode());
            $arrMessage['code'] = $e->getCode();
        }
        return $arrMessage;
    }

    private function getResourceContent(array $range)
    {
        $lastRang = array_shift($range);
        if ($lastRang[0] >= $this->objResource->getcontentlength) {
            return ['code' => 416];
        }
        $maxPos = min($lastRang[0] + self::MAX_CONTENT_LENGTH, $this->objResource->getcontentlength);
        $rangeList = [];
        if ($lastRang[1] == -1 || $lastRang[1] > $maxPos) {
            $lastRang[1] = $maxPos;
        }elseif(!empty($range)){
            $contentLength = $lastRang[1] - $lastRang[0];
            if ($lastRang[1] < $this->objResource->getcontentlength && $contentLength < self::MAX_CONTENT_LENGTH) {
                foreach ($range as $ran) {
                    $ran[1] = min($ran[1], $this->objResource->getcontentlength, $ran[0] + self::MAX_CONTENT_LENGTH - $contentLength);
                    if ($ran[0] >= $ran[1]) {
                        break;
                    }
                    if ($ran[0] > $lastRang[1]) {
                        $rangList[] = $lastRang;
                        $lastRang = $ran;
                        $contentLength += $ran[1] - $ran[0];
                    } elseif ($ran[1] > $lastRang[1]) {
                        $contentLength += $ran[1] - $lastRang[1];
                        $lastRang[1] = $ran[1];
                    }
                }
            }
        }
        $rangeList[] = $lastRang;
        $responseMessage = [
            'code'    => 200,
            'headers' => [
                'Content-Type:' . $this->objResource->getcontenttype,
                'ETag:' . $this->objResource->etag,
                'Last-Modified:' . gmdate('D, d M Y H:i:s', strtotime($this->objResource->getlastmodified)) . ' GMT',
            ],
        ];
        if ($rangeList == [[0, $this->objResource->getcontentlength]]) {
            $responseMessage['body'] = file_get_contents($this->objResource->path);
            return $responseMessage;
        }
        $responseMessage['code'] = 206;
        header(Httpsdav_StatusCode::$message[206]);
        if (1 == count($rangeList)) {
            $range = current($rangeList);
            $length = $range[1] - $range[0];
            $responseMessage['headers'][] = 'Content-Range: bytes ' . $range[0] . '-' . $range[1] . '/' . $this->objResource->getcontentlength;
            $responseMessage['headers'][] = 'Content-Length: ' . ($range[1] - $range[0]);
            $responseMessage['body'] = file_get_contents($this->objResource->path, null, null, $range[0], $length);
        } else {
            $boundary = md5($this->objResource->path);
            $responseMessage['headers'][] = 'Content-Type: multipart/byteranges; boundary=' . $boundary;
            $responseMessage['body'] = '';
            foreach ($rangeList as $range) {
                $responseMessage['body'] .= '--' . $boundary . "\r\n" . 'Content-Type: ' . $this->objResource->getcontenttype . "\r\n" . 'Content-Range: bytes ' . $range[0] . '-' . $range[1] . '/' . $this->objResource->getcontentlength . "\r\n" . file_get_contents($this->objResource->path, null, null, $range[0], $range[1] - $range[0]);
            }
        }
        return $responseMessage;
    }

    private function outMaxContent(){
        header('HTTP/1.1 200 OK');
        header('Content-Type:' . $this->objResource->getcontenttype);
        header('ETag:' . $this->objResource->etag);
        header('Last-Modified:' . gmdate('D, d M Y H:i:s', strtotime($this->objResource->getlastmodified)) . ' GMT');
        header('Content-Length: ' . $this->objResource->getcontentlength);
        if($this->objResource->getcontentlength <= self::MAX_CONTENT_LENGTH) {
            $content = file_get_contents($this->objResource->path);
            echo $content;
        } else {
            $start = 0;
            while($start < $this->objResource->getcontentlength){
                $content = file_get_contents($this->objResource->path, null, null, $start, min(self::MAX_CONTENT_LENGTH, $this->objResource->getcontentlength-$start));
                echo $content;
                $start += self::MAX_CONTENT_LENGTH;
                ob_flush();
                flush();
            }
        }
        return ['code' => 0];
    }
}
