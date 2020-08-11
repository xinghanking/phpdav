<?php

/**
 * @name   Method_Delete
 * @desc   Delete method
 * @author 刘重量(13439694341@qq.com)
 */
class Method_Delete extends Dav_Method
{

    protected $arrInput = [
        'Redirect-Status' => 200,
    ];

    /**
     * @return array
     */
    protected function handler()
    {
        try {
            $objResource = Dav_Resource::getInstance();
            if ($objResource->status === Dav_Resource::STATUS_DELETE) {
                return ['code' => $this->arrInput['Redirect-Status']];
            }
            $res = $objResource->remove();
            return ['code' => ($res ? $this->arrInput['Redirect-Status'] : 503)];
        } catch (Exception $e) {
            $code = $e->getCode();
            if (!isset(Dav_Status::$Msg[$code])) {
                $code = 503;
            }
            Dav_Log::error($e);
            return ['code' => $code];
        }
    }

    /**
     * @return mixed|void
     */
    protected function getArrInput()
    {
        if (isset($_REQUEST['HEADERS']['Redirect-Status'])) {
            $this->arrInput['Redirect-Status'] = $_REQUEST['HEADERS']['Redirect-Status'];
        }
    }
}