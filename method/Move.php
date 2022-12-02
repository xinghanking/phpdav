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
        $sourceBaseResource = Dav_Resource::getInstance();
        if (empty($sourceBaseResource) || $sourceBaseResource->status == Dav_Resource::STATUS_FAILED) {
            return ['code' => 503];
        }
        if ($sourceBaseResource->status == Dav_Resource::STATUS_DELETE) {
            return ['code' => 404];
        }
        if (isset($arrInput['Overwrite']) && $this->arrInput['Overwrite'] == 'F' && file_exists($this->arrInput['Destination']) && (!is_dir($this->arrInput['Destination']) || substr($this->arrInput['Destination'], -1) != DIRECTORY_SEPARATOR)) {
            return ['code' => 412];
        }
        $destResourceName = rtrim($this->arrInput['Destination'], '/');
        $objDestResource = Dav_Resource::getInstance($destResourceName, true);
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
                $childDest = $destResourceName . DIRECTORY_SEPARATOR . basename($path);
                if (true === $this->canMove($objSource, $childDest)) {
                    $arrResponse[] = $objSource->move($childDest);
                }
            }
            return $arrResponse;
        }
        $destResource = $destResourceName;
        if ($objDestResource->status == Dav_Resource::STATUS_NORMAL && $objDestResource->content_type == Dao_ResourceProp::MIME_TYPE_DIR && substr($this->arrInput['Destination'], -1) === '/') {
            $destResource = $destResourceName . DIRECTORY_SEPARATOR . basename($_REQUEST['HEADERS']['Resource']);
        }
        if (false === $this->canMove($sourceBaseResource, $destResource)) {
            return ['code' => 412];
        }
        $response = $sourceBaseResource->move($destResource);
        if ($response['code'] == 201) {
            $response['headers'] = ['Location: ' . Dav_Utils::href_encode($destResource)];
        }
        return $response;
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
        $isLocked = $objSourceResource->checkLocked();
        if ($isLocked && !(isset($_SESSION['user']) && !in_array($_SESSION['user'], $objSourceResource->locked_info['owner'])) && !in_array($objSourceResource->locked_info['locktoken'], $this->arrInput['Token'])) {
            throw new Exception(Dav_Status::$Msg[423], 423);
        }
        $objDestResource = Dav_Resource::getInstance($destResource, true);
        if ($objDestResource->status == Dav_Resource::STATUS_DELETE) {
            return true;
        }
        if ($objDestResource->status != Dav_Resource::STATUS_NORMAL) {
            return false;
        }
        if ($this->arrInput['Overwrite'] == 'F') {
            return false;
        }
        $isLocked = $objDestResource->checkLocked();
        if ($isLocked && !(isset($_SESSION['user']) && !in_array($_SESSION['user'], $objDestResource->locked_info['owner'])) && !in_array($objDestResource->lockedInfo['locktoken'], $this->arrInput['Token'])) {
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
        if (substr($destination, 0, 4) == 'http') {
            $destination = Dav_Utils::href_decode($destination);
        }
        $this->arrInput = [
            'Destination' => $destination,
            'Overwrite'   => empty($_REQUEST['HEADERS']['Overwrite']) ? null : $_REQUEST['HEADERS']['Overwrite'],
            'Token'       => Dav_Utils::getLockToken(),
        ];
    }
}
