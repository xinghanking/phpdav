<?php

/**
 * Class Method_Move
 */
class Method_Move extends Dav_Method
{
    /**
     * @return array|mixed
     */
    protected function handler()
    {
        try {
            $sourceBaseResource = Dav_Resource::getInstance($_REQUEST['HEADERS']['Resource']);
            if (empty($sourceBaseResource) || $sourceBaseResource->status == Dav_Resource::STATUS_FAILED) {
                return ['code' => 503];
            }
            if ($sourceBaseResource->status == Dav_Resource::STATUS_DELETE) {
                return ['code' => 404];
            }
            $isLocked = $sourceBaseResource->checkLocked();
            if ($isLocked && !in_array($sourceBaseResource->lockedInfo['locktoken'], $this->arrInput['Token'])) {
                return ['code' => 423];
            }
            if (isset($arrInput['overwrite']) && $this->arrInput['Overwrite'] == 'F') {
                return ['code' => 412];
            }
            $destResource = rtrim($this->arrInput['Destination'], '/');
            $objDestResource = Dav_Resource::getInstance($destResource);
            if ($objDestResource->status == Dav_Resource::STATUS_FAILED) {
                return ['code' => 503];
            }
            if (substr($_REQUEST['HEADERS']['Path'], -1) == '*') {
                if ($objDestResource->status == Dav_Resource::STATUS_NORMAL && $objDestResource->content_type != Dao_ResourceProp::MIME_TYPE_DIR) {
                    return ['code' => 412];
                }
                $sourceList = $sourceBaseResource->getChildren();
                if (empty($sourceList)) {
                    return ['code' => 404];
                }
                $arrResponse = [];
                foreach ($sourceList as $path => $objSource) {
                    $childDest = $destResource . DIRECTORY_SEPARATOR . basename($path);
                    if (true === $this->canMove($objSource, $childDest)) {
                        $arrResponse[] = $objSource->move($childDest);
                    }
                }
                return $arrResponse;
            }
            if ($objDestResource->status == Dav_Resource::STATUS_NORMAL && $objDestResource->content_type == Dao_ResourceProp::MIME_TYPE_DIR) {
                $destResource = $destResource . DIRECTORY_SEPARATOR . basename($_REQUEST['HEADERS']['Resource']);
            }
            if (false === $this->canMove($sourceBaseResource, $destResource)) {
                return ['code' => 412];
            }
            $response = $sourceBaseResource->move($destResource);
            if ($response['code'] == 201) {
                $response['headers'] = ['Location: ' . Dav_Utils::href_encode($destResource)];
            }
            return $response;
        } catch (Exception $e) {
            $code = $e->getCode();
            $msg = $e->getMessage();
            if (!isset(Dav_Status::$Msg[$code]) || Dav_Status::$Msg[$code] != $msg) {
                $code = 503;
            }
            return ['code' => $code];
        }
    }

    /**
     * 检查是否允许被移动到目标资源地址
     * @param \Dav_Resource $objSourceResource 源地址
     * @param string $destResource 目标地址
     * @return bool
     * @throws Exception
     */
    private function canMove($objSourceResource, $destResource)
    {
        $objDestResource = Dav_Resource::getInstance($destResource);
        if ($objDestResource->status == Dav_Resource::STATUS_DELETE) {
            return true;
        }
        if ($objDestResource->status != Dav_Resource::STATUS_NORMAL) {
            return false;
        }
        if ($this->arrInput['Overwrite'] == 'F') {
            return false;
        }
        if ($objSourceResource->content_type != $objDestResource->content_type) {
            return false;
        }
        if ($objDestResource->content_type == Dao_ResourceProp::MIME_TYPE_DIR) {
            $children = $objDestResource->getChildren();
            if (count($children) > 0) {
                return false;
            }
        }
        $isLocked = $objDestResource->checkLocked();
        if ($isLocked && !in_array($objDestResource->lockedInfo['locktoken'], $this->arrInput['Token'])) {
            return false;
        }
        return true;
    }

    /**
     * 获取格式化的输入数据
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
        $this->arrInput = [
            'Destination' => $destination,
            'Overwrite'   => empty($_REQUEST['HEADERS']['Overwrite']) ? null : $_REQUEST['HEADERS']['Overwrite'],
            'Token'       => Dav_Utils::getLockToken(),
        ];
    }
}
