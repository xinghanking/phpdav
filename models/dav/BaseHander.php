<?php
/**
 * Class Dav_BaseHander
 * 根据客户端请求方法调用的handler类的抽象基类
 * @author 刘重量 (13439694341@qq.com)
 */
abstract class Dav_BaseHander
{
    protected $arrInput     = [];
    protected $formatStatus = true;

    /**
     * 构造函数，初始化并数组格式化前端发来的请求数据
     */
    public function __construct()
    {
        try{
            $this->getArrInput();
        } catch (Exception $e) {
            $this->formatStatus = false;
            Dav_Log::error($e);
        }
    }

    /**
     * 调用执行程序处理客户端发来的请求任务，并返回数组格式化的处理结果
     * @return array
     */
    public function execute(){
        if(false === $this->formatStatus){
            $response = ['code'=>422];
        } else {
            try{
                $response = $this->handler();
            } catch (Exception $e) {
                $code = $e->getCode();
                $msg = $e->getMessage();
                if(!isset(Dav_Status::$Msg[$code]) || $msg != Dav_Status::$Msg[$code]){
                    $response['code'] = 503;
                    Dav_Log::error($e);
                } else {
                    $response['code'] = $code;
                }
            }
        }
        return $response;
    }

    /**
     * 执行客户端发来的请求任务并返回执行结果
     * @return mixed
     */
    abstract protected function handler();

    /**
     * 数组格式化客户端发来的请求数据项
     * @return mixed
     */
    abstract protected function getArrInput();
}