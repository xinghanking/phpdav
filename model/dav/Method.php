<?php

/**
 * Class HttpsDav_Method
 * 根据客户端请求方法调用的handler类的抽象基类
 * @author 刘重量 (13439694341@qq.com)
 */
abstract class Dav_Method
{
    protected $arrInput = [];
    protected $formatStatus = true;

    /**
     * 构造函数，初始化并数组格式化前端发来的请求数据
     */
    public function __construct()
    {
        try {
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
    public function execute(&$responseHeader = null, &$responseBody = null)
    {
        if (false === $this->formatStatus) {
            $response = ['code' => 422];
        } else {
            try {
                $response = $this->handler();
            } catch (Exception $e) {
                $code = $e->getCode();
                $msg = $e->getMessage();
                if (!isset(Dav_Status::$Msg[$code]) || $msg != Dav_Status::$Msg[$code]) {
                    $response['code'] = 503;
                    Dav_Log::error($e);
                } else {
                    $response['code'] = $code;
                }
            }
        }
        if (isset($response['code']) && isset(Dav_Status::$Msg[$response['code']])) {
            $response['header'] = [Dav_Status::$Msg[$response['code']]];
            if (isset($response['headers']) && is_array($response['headers'])) {
                $response['header'] = array_merge($response['header'], $response['headers']);
                unset($response['headers']);
            }
            if (isset($response['body'])) {
                if (is_array($response['body'])) {
                    $xmlDoc = new DOMDocument('1.0', 'UTF-8');
                    $xmlDoc->formatOutput = true;
                    $element = Dav_Utils::xml_encode($xmlDoc, $response['body']);
                    $xmlDoc->appendChild($element);
                    $response['body'] = trim($xmlDoc->saveXML());
                }
                $response['header'][] = 'Content-Length: ' . strlen($response['body']);
            } else {
                $response['header'][] = 'Content-Length: 0';
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
