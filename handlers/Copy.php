<?php
/**
 * @name Handler_Copy
 * @desc copy method
 * @author 刘重量(13439694341@qq.com)
 */
class Handler_Copy extends Dav_BaseHander
{

    /**
     * @return array|mixed
     */
    protected function handler()
    {
        $arrResponse = ['code' => 503];
        try {
            $objResource = Dav_Resource::getInstance(REQUEST_RESOURCE);
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
                if($objResource->content_type == Dao_ResourceProp::MIME_TYPE_DIR || $objDestResource->content_type != Dao_ResourceProp::MIME_TYPE_DIR){
                    return ['code' => 424];
                }
                $isLocked = $objDestResource->checkLocked();
                if ($isLocked && !in_array($objDestResource->lockedInfo['locktoken'], $this->arrInput['Token'])) {
                    return ['code' => 423];
                }
                if (isset($this->arrInput['Overwrite']) && $this->arrInput['Overwrite'] == 'F') {
                    return ['code' => 412];
                }
            }
            $res = $objResource->copy($this->arrInput['Destination']);
            if($res) {
                $arrResponse = ['code' => 200];
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            $msg = $e->getMessage();
            if (!isset(Dav_Status::$Msg[$code]) || Dav_Status::$Msg[$code] != $msg) {
                $code = 503;
            }
            $arrResponse = ['code' => $code];
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
        if (empty(Dav_Request::$_Headers['Destination'])) {
            throw new Exception(Dav_Status::$Msg['412'], 412);
        }
        $destination = rtrim(Dav_Server::href_decode(Dav_Request::$_Headers['Destination']), '*');
        if (empty($destination)) {
            throw new Exception(Dav_Status::$Msg['412'], 412);
        }
        $arrInput = [
            'Destination' => $destination,
            'Overwrite'   => empty(Dav_Request::$_Headers['Overwrite']) ? null : Dav_Request::$_Headers['Overwrite'],
            'Token'       => Dav_Request::getLockToken(),
        ];
        return $arrInput;
    }
}