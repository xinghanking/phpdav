<?php
/**
 * @name   Handler_Delete
 * @desc   Delete method
 * @author 刘重量(13439694341@qq.com)
 */
class Handler_Delete extends HttpsDav_BaseHander {

    protected $arrInput = [
        'Redirect-Status' => 200,
    ];

    /**
     * @return array
     */
    protected function handler()
    {
        try {
            $objResource = Service_Data_Resource::getInstance();
            if ($objResource->status === Service_Data_Resource::STATUS_DELETE) {
                return ['code' => $this->arrInput['Redirect-Status']];
            }
            $res = $objResource->remove();
            return ['code' => ($res ? $this->arrInput['Redirect-Status'] : 503)];
        } catch (Exception $e) {
            $code = $e->getCode();
            if (!isset(Httpsdav_StatusCode::$message[$code])) {
                $code = 503;
            }
            HttpsDav_Log::error($e);
            return ['code' => $code];
        }
    }

    /**
     * @return mixed|void
     */
    protected function getArrInput()
    {
        if(isset(HttpsDav_Request::$_Headers['Redirect-Status'])){
            $this->arrInput['Redirect-Status'] = HttpsDav_Request::$_Headers['Redirect-Status'];
        }
    }
}