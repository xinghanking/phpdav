<?php
/**
 * @name   Service_Page_Copy
 * @desc   执行webdav的Copy方法
 * @author 刘重量(13439694341@qq.com)
 */
class Service_Page_Copy
{
    /**
     * 执行Httpsdav的Copy方法
     * @param  array input
     * @return array
     * @throws Exception
     */
    public function execute(array $arrInput)
    {
        $objResource = Service_Data_Resource::getInstance(REQUEST_RESOURCE);
        if (empty($objResource) || $objResource->status == Service_Data_Resource::STATUS_FAILED) {
            return ['code' => 503];
        }
        if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
            return ['code' => 404];
        }
        $objDestination = Service_Data_Resource::getInstance($arrInput['destination']);
        if ($objDestination->status == Service_Data_Resource::STATUS_NORMAL) {
            $isLocked = $objDestination->checkLocked();
            if ($isLocked && !in_array($objDestination->locked_info['locktoken'], $arrInput['token'])) {
                return ['code' => 423];
            }
            if (isset($arrInput['overwrite']) && $arrInput['overwrite'] == 'F') {
                return ['code' => 412];
            }
        }
        $objResource->copy($arrInput['destination']);
        return ['code' => $objDestination->status == Service_Data_Resource::STATUS_NORMAL ? 204 : 201];
    }
}
