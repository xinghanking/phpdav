<?php
/**
 * @name   Service_Page_Move
 * @desc   执行webdav的Copy方法
 * @author 刘重量(13439694341@qq.com)
 */
class Service_Page_Move
{
    /**
     * 执行Httpsdav的move方法
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
            return ['code' => 404];
        }
        $objDestination = Service_Data_Resource::getInstance($arrInput['destination']);
        if ($objDestination->status == Service_Data_Resource::STATUS_NORMAL) {
            $isLocked = $objDestination->checkLocked();
            if ($isLocked && !in_array($objDestination->opaquelocktoken, $arrInput['token'])) {
                return ['code' => 423];
            }
        }
        if (isset($arrInput['overwrite']) && $arrInput['overwrite'] == 'F') {
            $sourceDir = dirname(REQUEST_RESOURCE);
            $destinonDir = dirname($arrInput['destination']);
            if($sourceDir != $destinonDir){
                file_put_contents('/tmp/vlog', '$sourceDir=' . $sourceDir . PHP_EOL . '$destinonDir=' . PHP_EOL);
                return ['code' => 412];
            }
        }
        $objResource->move($arrInput['destination']);
        return ['code' => $objDestination->status == Service_Data_Resource::STATUS_NORMAL ? 204 : 201];
    }
}
