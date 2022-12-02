<?php

/**
 * @name Method_Copy
 * @desc copy method
 * @author 刘重量(13439694341@qq.com)
 */
class Method_Copy extends Dav_Method
{

    /**
     * @return array
     * @throws Exception
     */
    protected function handler()
    {
        $arrResponse = ['code' => 503];
        $objResource = Dav_Resource::getInstance($_REQUEST['HEADERS']['Resource']);
        if (empty($objResource) || $objResource->status == Dav_Resource::STATUS_FAILED) {
            return ['code' => 503];
        }
        if ($objResource->status == Dav_Resource::STATUS_DELETE) {
            return ['code' => 404];
        }
        $isLocked = $objResource->checkLocked();
        if ($isLocked && !in_array($objResource->lockedInfo['locktoken'], $this->arrInput['Token'])) {
            return ['code' => 423];
        }
        if (isset($arrInput['overwrite']) && $this->arrInput['Overwrite'] == 'F') {
            return ['code' => 412];
        }
        $baseDestResource = rtrim($this->arrInput['Destination'], '/');
        $objDestResource = Dav_Resource::getInstance($baseDestResource);
        if ($objDestResource->status == Dav_Resource::STATUS_NORMAL) {
            if ($objResource->content_type == Dao_ResourceProp::MIME_TYPE_DIR || $objDestResource->content_type != Dao_ResourceProp::MIME_TYPE_DIR) {
                return ['code' => 424];
            }
            $isLocked = $objDestResource->checkLocked();
            if ($isLocked && !(isset($_SESSSION['user']) && !in_array($_SESSSION['user'], $objResource->locked_info['owner'])) && !in_array($objDestResource->lockedInfo['locktoken'], $this->arrInput['Token'])) {
                return ['code' => 423];
            }
            if (isset($this->arrInput['Overwrite']) && $this->arrInput['Overwrite'] == 'F') {
                return ['code' => 412];
            }
        }
        $res = $objResource->copy($this->arrInput['Destination']);
        if ($res) {
            $arrResponse = ['code' => 200];
        }
        return $arrResponse;
    }

    /**
     * 获取格式化的输入数据
     * @return array
     * @throws Exception
     */
    protected function getArrInput()
    {
        if (empty($_REQUEST['HEADERS']['Destination'])) {
            throw new Exception(Dav_Status::$Msg['412'], 412);
        }
        $destination = rtrim(Dav_Utils::href_decode($_REQUEST['HEADERS']['Destination']), '*');
        if (empty($destination)) {
            throw new Exception(Dav_Status::$Msg['412'], 412);
        }
        return [
            'Destination' => $destination,
            'Overwrite'   => empty($_REQUEST['HEADERS']['Overwrite']) ? null : $_REQUEST['HEADERS']['Overwrite'],
            'Token'       => Dav_Utils::getLockToken(),
        ];
    }
}
