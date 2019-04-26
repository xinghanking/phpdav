<?php
/**
 * @name   Service_Page_PropPatch
 * @desc   执行webdav的PropPatch方法
 * @author 刘重量(13439694341@qq.com)
 */
class Service_Page_PropPatch
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
                return ['code' => 503];
            }
            if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
                return ['code' => 404];
            }
            $isLocked = $objResource->checkLocked();
            if ($isLocked && !in_array($objResource->opaquelocktoken, $arrInput['opaquelocktoken'])) {
                return ['code' => 403];
            }
            $res = $objResource->propPatch($arrInput);
            return ['code' => $res ? 200 : 503];
        } catch (Exception $e) {
            Bd_Log::fatal('资源：' . REQUEST_RESOURCE . '执行解锁失败，错误码：' . $e->getCode() . ', 错误信息：' . $e->getMessage());
            return ['code' => 500];
        }
    }
}
