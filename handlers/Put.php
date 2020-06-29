<?php

/**
 * Class Handler_Put
 */
class Handler_Put extends Dav_BaseHander
{
    const LIMIT_SIZE    = 10485760;
    protected $arrInput = [];

    /**
     * 执行客户端通过调用PUT方法发来的请求任务，并返回数组格式的执行结果
     * @return array
     * @throws Exception
     */
    protected function handler(){
        $objResource = Dav_Resource::getInstance(Dav_Request::$_Headers['Resource']);
        if (empty($objResource) || $objResource->status == Dav_Resource::STATUS_FAILED) {
            return ['code' => 503];
        }
        $isLocked = $objResource->checkLocked();
        if ($isLocked && !in_array($objResource->locked_info['locktoken'], $this->arrInput['locktoken'])) {
            return ['code' => 403];
        }
        if ($objResource->status == Dav_Resource::STATUS_NORMAL) {
            if ($objResource->content_length > 0) {
                if (!empty($this->arrInput['etag']) || !empty($this->arrInput['lastmodified'])) {
                    if ($objResource->hadChanged($this->arrInput)) {
                        return ['code' => 416];
                    }
                    if (empty($this->arrInput['Request-Body-File'])) {
                        $res = file_put_contents(Dav_Request::$_Headers['Resource'], file_get_contents('php://input'), FILE_APPEND);
                    } else {
                        $res = Dav_PhyOperation::combineFile(Dav_Request::$_Headers['Resource'], $this->arrInput['Request-Body-File']);
                    }
                    return ['code' => false === $res ? 503 : 200];
                }
            }
        }
        if (empty($this->arrInput['Request-Body-File']) || $this->arrInput['Content-Length'] <= MAX_READ_LENGTH) {
            $res = file_put_contents(Dav_Request::$_Headers['Resource'], file_get_contents('php://input'));
        } else {
            $res = Dav_PhyOperation::move($this->arrInput['Request-Body-File'], Dav_Request::$_Headers['Resource']);
        }
        if (false === $res) {
            return ['code' => 503];
        }

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
            'locktoken'         => Dav_Request::getLockToken(),
            'etag'              => Dav_Request::getETagList(),
            'lastmodified'      => Dav_Request::getLastModified(),
            'Request-Id'        => isset(Dav_Request::$_Headers['Request-Id']) ? Dav_Request::$_Headers['Request-Id'] : 0,
            'Request-Body-File' => isset(Dav_Request::$_Headers['Request-Body-File']) ? Dav_Request::$_Headers['Request-Body-File'] : null,
        ];
        $this->arrInput['Content-Type'] = empty(Dav_Request::$_Headers['Content-Type']) ? 'text/plain' : Dav_Request::$_Headers['Content-Type'];
        if (isset(Dav_Request::$_Headers['Expect'])) {
            $this->arrInput['Expect'] = strtok(Dav_Request::$_Headers['Expect'], '-');
        }
        if (isset(Dav_Request::$_Headers['Content-Length']) && is_numeric(Dav_Request::$_Headers['Content-Length'])) {
            $this->arrInput['Content-Length'] = intval(Dav_Request::$_Headers['Content-Length']);
        }
        if (isset(Dav_Request::$_Headers['Redirect-Status'])) {
            $this->arrInput['Redirect-Status'] = intval(Dav_Request::$_Headers['Redirect-Status']);
        }
    }
}
