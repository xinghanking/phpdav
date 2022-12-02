<?php

/**
 * Class Method_Put
 */
class Method_Put extends Dav_Method
{
    const LIMIT_SIZE = 10485760;
    protected $arrInput = [];

    /**
     * 执行客户端通过调用PUT方法发来的请求任务，并返回数组格式的执行结果
     * @return array
     * @throws Exception
     */
    protected function handler()
    {
        $objResource = Dav_Resource::getInstance($_REQUEST['HEADERS']['Resource']);
        if (empty($objResource) || $objResource->status == Dav_Resource::STATUS_FAILED) {
            return ['code' => 503];
        }
        $isLocked = $objResource->checkLocked();
        if ($isLocked && !(isset($_SESSSION['user']) && in_array($_SESSSION['user'], $objResource->locked_info['owner'])) && !in_array($objResource->locked_info['locktoken'], $this->arrInput['locktoken'])) {
            return ['code' => 403];
        }
        if ($objResource->status == Dav_Resource::STATUS_NORMAL) {
            if ($objResource->content_length > 0) {
                if (!empty($this->arrInput['etag']) || !empty($this->arrInput['lastmodified'])) {
                    if ($objResource->hadChanged($this->arrInput)) {
                        return ['code' => 416];
                    }
                    if (empty($this->arrInput['Request-Body-File'])) {
                        $res = Dav_Utils::accept_data($_REQUEST['HEADERS']['Resource']);
                    } else {
                        $res = Dav_PhyOperation::combineFile($_REQUEST['HEADERS']['Resource'], $this->arrInput['Request-Body-File']);
                    }
                    return ['code' => false === $res ? 503 : 200];
                }
            }
        }
        $res = Dav_Utils::save_data($_REQUEST['HEADERS']['Resource']);
        if (false === $res) {
            return ['code' => 503];
        }
        Dao_DavResource::getInstance()->getResourceConf($_REQUEST['HEADERS']['Resource'], true);
        Dav_Resource::getInstance();
        if ($objResource->status == Dav_Resource::STATUS_DELETE) {
            return ['code' => 201];
        } else {
            return ['code' => 200];
        }
    }

    /**
     * 获取并数组格式化的客户端发来的请求数据
     * @throws \Exception
     */
    protected function getArrInput()
    {
        $this->arrInput = [
            'locktoken'         => Dav_Utils::getLockToken(),
            'etag'              => Dav_Utils::getETagList(),
            'lastmodified'      => Dav_Utils::getLastModified(),
            'Request-Id'        => empty($_REQUEST['HEADERS']['Request-Id']) ? 0 : $_REQUEST['HEADERS']['Request-Id'],
            'Request-Body-File' => isset($_REQUEST['HEADERS']['Request-Body-File']) ? $_REQUEST['HEADERS']['Request-Body-File'] : ''
        ];
        $this->arrInput['Content-Type'] = empty($_REQUEST['HEADERS']['Content-Type']) ? 'text/plain' : $_REQUEST['HEADERS']['Content-Type'];
        if (isset($_REQUEST['HEADERS']['Expect'])) {
            $this->arrInput['Expect'] = strtok($_REQUEST['HEADERS']['Expect'], '-');
        }
        if (isset($_REQUEST['HEADERS']['Content-Length']) && is_numeric($_REQUEST['HEADERS']['Content-Length'])) {
            $this->arrInput['Content-Length'] = intval($_REQUEST['HEADERS']['Content-Length']);
        }
        if (isset($_REQUEST['HEADERS']['Redirect-Status'])) {
            $this->arrInput['Redirect-Status'] = intval($_REQUEST['HEADERS']['Redirect-Status']);
        }
    }
}
