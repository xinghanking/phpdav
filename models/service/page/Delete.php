<?php

/**
 * @name Service_Page_Get
 * @desc get page service, 和action对应，支持断点下载
 * @author 刘重量(13439694341@qq.com)
 */
class Service_Page_Delete
{

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
    public function execute()
    {
        Bd_Log::debug('Delete Page service called');
        $arrMessage = ['code' => 204];
        try {
            //服务器获取不到资源，返回服务器原因的状态值
            if (!isset($this->objResource->status)) {
                return ['code' => 500];
            }
            //资源不存在
            if ($this->objResource->status == Service_Data_Resource::STATUS_DELETE) {
                return ['code' => 404];
            }
            $arrResponse = $this->objResource->remove();
            return $arrResponse;
        } catch (Exception $e) {
            Bd_Log::warning($e->getMessage(), $e->getCode());
            $errorCode = $e->getCode();
            $responseCode = isset(Httpsdav_StatusCode::$message[$errorCode]) ? $errorCode : 500;
            return ['code' => $responseCode];
        }
    }
}
