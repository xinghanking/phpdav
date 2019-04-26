<?php
/**
 * @name   Service_Page_Put
 * @desc   执行webdav的Put方法
 * @author 刘重量(13439694341@qq.com)
 */
class Service_Page_Put
{
    /**
     * 执行Httpsdav的Put方法
     * @param  array input
     * @return array result
     */
    public function execute(array $arrInput)
    {
        $objResource = Service_Data_Resource::getInstance(REQUEST_RESOURCE);
        if (empty($objResource) || $objResource->status == Service_Data_Resource::STATUS_FAILED) {
            return ['code' => 500];
        }
        if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
            $res = file_put_contents(REQUEST_RESOURCE, $arrInput['data'] ?? '');
            if (false === $res) {
                return ['code' => 403];
            }
            return ['code' => Httpsdav_Request::$_Headers['Redirect-Status'] ?? (empty($arrInput['data']) ? 201 : 200)];
        }
        $isLocked = $objResource->checkLocked();
        if ($isLocked && !in_array($objResource->opaquelocktoken, $arrInput['token'])) {
            return ['code' => 403];
        }
        $res = file_put_contents(REQUEST_RESOURCE, $arrInput['data'] ?? '');
        if (false === $res) {
            return ['code' => 403];
        }
        return ['code' => Httpsdav_Request::$_Headers['Redirect-Status'] ?? 200];
    }
}
