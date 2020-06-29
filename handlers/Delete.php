<?php
/**
 * @name   Handler_Delete
 * @desc   Delete method
 * @author 刘重量(13439694341@qq.com)
 */
class Handler_Delete extends Dav_BaseHander {

    protected $arrInput = [
        'Redirect-Status' => 200,
    ];

    /**
     * @return array
     */
    protected function handler()
    {
        $objResource = Dav_Resource::getInstance();
        if ($objResource->status === Dav_Resource::STATUS_DELETE) {
            return ['code' => $this->arrInput['Redirect-Status']];
        }
        $res = $objResource->remove();
        return ['code' => ($res ? $this->arrInput['Redirect-Status'] : 503)];
    }

    /**
     * @return mixed|void
     */
    protected function getArrInput()
    {
        if(isset(Dav_Request::$_Headers['Redirect-Status'])){
            $this->arrInput['Redirect-Status'] = Dav_Request::$_Headers['Redirect-Status'];
        }
    }
}