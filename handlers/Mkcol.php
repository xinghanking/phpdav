<?php
/**
 * 创建文件目录
 * @name   Handler_Mkcol
 * @desc   Mkcol method
 * @author 刘重量(13439694341@qq.com)
 */
class Handler_Mkcol extends Dav_BaseHander
{
    /**
     * @return mixed|void
     */
    protected function handler()
    {
        if (file_exists(REQUEST_RESOURCE)) {
            $arrResponse = ['code' => 409];
        } else {
            $res = mkdir(REQUEST_RESOURCE, 0700, true);
            $arrResponse = ['code' => false === $res ? 403 : 201];
        }
        return $arrResponse;
    }

    protected function getArrInput()
    {
        // TODO: Implement getArrInput() method.
    }
}