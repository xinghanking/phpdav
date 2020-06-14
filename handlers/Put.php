<?php

/**
 * Class Handler_Put
 */
class Handler_Put extends HttpsDav_BaseHander
{
    const LIMIT_SIZE    = 10485760;
    protected $arrInput = [];

    /**
     * 执行客户端通过调用PUT方法发来的请求任务，并返回数组格式的执行结果
     * @return array
     * @throws Exception
     */
    protected function handler(){
        $objResource = Service_Data_Resource::getInstance(REQUEST_RESOURCE);
        if (empty($objResource) || $objResource->status == Service_Data_Resource::STATUS_FAILED) {
            return ['code' => 503];
        }
        $isLocked = $objResource->checkLocked();
        if ($isLocked && !in_array($objResource->locked_info['locktoken'], $this->arrInput['locktoken'])) {
            HttpsDav_Log::debug(print_r([$isLocked, $objResource->locked_info, $this->arrInput['locktoken']], true));
            return ['code' => 403];
        }
        if ($objResource->status == Service_Data_Resource::STATUS_NORMAL) {
            if ($objResource->content_length > 0) {
                if (!empty($this->arrInput['etag']) || !empty($this->arrInput['lastmodified'])) {
                    if ($objResource->hadChanged($this->arrInput)) {
                        return ['code' => 416];
                    }
                    if (empty($this->arrInput['Request-Body-File'])) {
                        $res = file_put_contents(REQUEST_RESOURCE, file_get_contents('php://input'), FILE_APPEND);
                    } else {
                        $res = HttpsDav_PhyOperation::combineFile(REQUEST_RESOURCE, $this->arrInput['Request-Body-File']);
                    }
                    return ['code' => false === $res ? 503 : 200];
                }
            }
        }
        if (empty($this->arrInput['Request-Body-File'])) {
            $res = file_put_contents(REQUEST_RESOURCE, file_get_contents('php://input'));
        } else {
            $res = HttpsDav_PhyOperation::move($this->arrInput['Request-Body-File'], REQUEST_RESOURCE);
        }
        if (false === $res) {
            return ['code' => 503];
        }

        if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
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
            'locktoken'         => HttpsDav_Request::getLockToken(),
            'etag'              => HttpsDav_Request::getETagList(),
            'lastmodified'      => HttpsDav_Request::getLastModified(),
            'Request-Id'        => HttpsDav_Request::$_Headers['Request-Id'],
            'Request-Body-File' => HttpsDav_Request::$_Headers['Request-Body-File'],
        ];
        HttpsDav_Log::debug(print_r([$_SERVER, HttpsDav_Request::getInputContent()], true));
        $this->arrInput['Content-Type'] = empty(HttpsDav_Request::$_Headers['Content-Type']) ? 'text/plain' : HttpsDav_Request::$_Headers['Content-Type'];
        if (isset(HttpsDav_Request::$_Headers['Expect'])) {
            $this->arrInput['Expect'] = strtok(HttpsDav_Request::$_Headers['Expect'], '-');
        }
        if (isset(HttpsDav_Request::$_Headers['Content-Length']) && is_numeric(HttpsDav_Request::$_Headers['Content-Length'])) {
            $this->arrInput['Content-Length'] = intval(HttpsDav_Request::$_Headers['Content-Length']);
        }
        if (isset(HttpsDav_Request::$_Headers['Redirect-Status'])) {
            $this->arrInput['Redirect-Status'] = intval(HttpsDav_Request::$_Headers['Redirect-Status']);
        }
    }
}