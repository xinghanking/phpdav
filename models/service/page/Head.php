<?php

/**
 * @name Service_Page_Get
 * @desc get page service, 和action对应，支持断点下载
 * @author 刘重量(13439694341@qq.com)
 */
class Service_Page_Head
{
    const MAX_CONTENT_LENGTH = 1073747824;
    const MAX_ONCE_READ_LENGTH = 10485760;
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
                header('Location:' . $href);
                return ['code' => 0];
            }
            $eTag = '"' . $this->objResource->etag . '"';
            if (isset($arrInput['If-Match']) && !in_array($eTag, $arrInput['If-Match'])) {
                return ['code' => 416];
            }
            $lastModified = strtotime($this->objResource->getlastmodified);
            $href = Httpsdav_Server::href_encode($this->objResource->path);
            if (true === $this->objResource->noChanged) {
                $arrResponse = ['code' => $this->objResource->noChanged ? 304 : 200];
            }
            $arrResponse['headers'] = [
                'ETag: ' . $eTag,
                'Last-Modified:' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
                'Accept-Ranges: bytes',
                'Content-Type: ' . $this->objResource->getcontenttype,
            ];
            return $arrResponse;
        } catch (Exception $e) {
            Bd_Log::warning($e->getMessage(), $e->getCode());
            $errorCode = $e->getCode();
            $responseCode = isset(Httpsdav_StatusCode::$message[$errorCode]) ? $errorCode : 500;
            return ['code' => $responseCode];
        }
        return $arrMessage;
    }
}
