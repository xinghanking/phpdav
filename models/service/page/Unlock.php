<?php
/**
 * @name   Service_Page_Unlock
 * @desc   执行webdav的UNLOCK方法
 * @author 刘重量(13439694341@qq.com)
 */
class Service_Page_Unlock
{
    /**
     * 执行Httpsdav的lock方法
     * @param  array input
     * @return array result
     */
    public function execute(array $arrInput)
    {
        try {
            $objResource = Service_Data_Resource::getInstance(REQUEST_RESOURCE);
            if (empty($objResource) || $objResource->status == Service_Data_Resource::STATUS_FAILED) {
                return ['code' => 500];
            }
            if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
                return ['code' => 404];
            }
            $arrResult = $objResource->unlock($arrInput);
            return ['code' => $arrResult['code']];
        } catch (Exception $e) {
            Bd_Log::fatal('资源：' . REQUEST_RESOURCE . '执行解锁失败，错误码：' . $e->getCode() . ', 错误信息：' . $e->getMessage());
            return ['code' => 500];
        }
    }
}
