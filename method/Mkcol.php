<?php

/**
 * 创建文件目录
 * @name   Method_Mkcol
 * @desc   Mkcol method
 * @author 刘重量(13439694341@qq.com)
 */
class Method_Mkcol extends Dav_Method
{
    /**
     * @return mixed|void
     */
    protected function handler()
    {
        if (file_exists($_REQUEST['HEADERS']['Resource'])) {
            return ['code' => 409];
        }
        Dav_PhyOperation::createDir($_REQUEST['HEADERS']['Resource']);
        return ['code' => 201];
    }

    protected function getArrInput()
    {
        // TODO: Implement getArrInput() method.
    }
}
