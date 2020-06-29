<?php
/**
 * Class Handler_UnLock
 * 处理客户端调用UNLOCK 方法发来的解锁指定资源的请求
 */
class Handler_UnLock extends Dav_BaseHander
{
    protected $arrInput = [
        'locktoken' => []
    ];

    /**
     * 执行客户端调用UNLOCK方法发来的对请求资源解锁的任务，并返回数组格式化的执行结果
     * @return array
     * @throws Exception
     */
    protected function handler()
    {
        $objResource = Dav_Resource::getInstance(Dav_Request::$_Headers['Resource']);
        if (empty($objResource) || $objResource->status == Dav_Resource::STATUS_FAILED) {
            return ['code' => 503];
        }
        if ($objResource->status == Dav_Resource::STATUS_DELETE) {
            return ['code' => 404];
        }
        $arrResult = $objResource->unlock($this->arrInput['locktoken']);
        if (isset(Dav_Status::$Msg[$arrResult['code']])) {
            return $arrResult;
        }
        return ['code' => 503];
    }

    /**
     * 获取并数组格式化的客户端发来的请求数据
     * @throws Exception
     */
    protected function getArrInput()
    {
        $this->arrInput['locktoken'] = Dav_Request::getLockToken();
        if (empty($this->arrInput['locktoken'])) {
            $this->formatStatus = false;
        }
    }
}