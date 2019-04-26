<?php

/**
 * Class Handler_Head
 */
class Handler_Head extends HttpsDav_BaseHander
{
    const  MAX_LENGTH   = 20971520;
    protected $arrInput = [
        'etag' => [],
        'lastmodified' => 0,
    ];
    private $range = [];
    /**
     * 请求数据单位对应的乘法换算因子
     * @var array
     */
    private $arrRate = [
        'bytes' => 1,
        'byte' => 1,
        'kb' => 1024,
        'mb' => 1048576,
        'gb' => 1073747824,
    ];
    private $textContentTypeList = ['application/unknow'];

    /**
     * 执行客户端调用HEAD方法发来预获取资源数据的请求任务
     * @return array
     * @throws \Exception
     */
    public function handler()
    {
        $objResource = Service_Data_Resource::getInstance();
        if (!isset($objResource->status)) {
            return ['code' => 503];
        }
        if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
            return ['code' => 404];
        }
        if ($objResource->content_type == Dao_ResourceProp::MIME_TYPE_DIR) {
            return [
                'code' => 200,
                'Content-Type' => 'text/html',
                'headers' => [
                    'Cache-Control: no-cache, no-store, must-revalidate',
                    'Content-Type: text/html; charset=utf8',
                ]
            ];
        }
        $contentType = in_array($objResource->content_type, $this->textContentTypeList) ? 'text/plain' : $objResource->content_type;
        $response = [
            'Content-Type' => $contentType,
            'headers' => [
                'Vary: Range',
                'Accept-Ranges: bytes',
                'Content-Type: ' . $contentType,
                'Content-Length: ' . $objResource->content_length,
                'ETag: "' . $objResource->etag . '"',
                'Last-Modified: ' . gmdate('D, d M Y H:i:s', $objResource->last_modified) . ' GMT',
                'Cache-Control: max-age=86400,must-revalidate',
            ]
        ];
        if ((empty($this->range) || $objResource->hadChanged($this->arrInput))) {
            $range = [0, $objResource->content_length];
        } else {
            if ($this->range[0] >= $objResource->content_length && $objResource->content_length > 0) {
                $response['code'] = 416;
                $response['headers'][] = 'Content-Range: */' . $objResource->content_length;
                return $response;
            }
            $range = [$this->range[0], (empty($this->range[1]) || $this->range[1] == -1) ? $objResource->content_length : min($this->range[1], $objResource->content_length)];
        }
        $start  = $range[0];
        $end    = $range[1];
        $length = $end - $start;
        $response['headers'][] = 'Content-Length: ' . $length;
        if ($length == $objResource->content_length) {
            $response['code'] = 200;
        } else {
            $response['code'] = 206;
            $response['headers'][] = 'Content-Range: bytes ' . $start . '-' . $end . '/' . $objResource->content_length;
        }
        return $response;
    }

    /**
     * 获取并格式化数据请求数组
     * @return array|mixed
     * @throws \Exception
     */
    protected function getArrInput()
    {
        $this->arrInput['etag'] = HttpsDav_Request::getETagList();
        $this->arrInput['lastmodified'] = HttpsDav_Request::getLastModified();
        if (!empty(HttpsDav_Request::$_Headers['Range'])) {
            $range = explode('=', HttpsDav_Request::$_Headers['Range']);
            $unit = strtolower($range[0]);
            if (!isset($this->arrRate[$unit])) {
                throw new Exception(Httpsdav_StatusCode::$message[422], 422);
            }
            if (!empty($rang[1])) {
                $rangList = explode(',', $rang[1]);
                $arrArangs = [];
                $rate = $this->arrRate[$unit];
                foreach ($rangList as $rang) {
                    $rang = explode('-', $rang);
                    if (count($rang) > 2) {
                        throw new Exception(Httpsdav_StatusCode::$message[422], 422);
                    }
                    if (!isset($rang[0]) || !is_numeric($rang[0]) || $rang[0] < 0) {
                        throw new Exception(Httpsdav_StatusCode::$message[422], 422);
                    }
                    $rang[0] = intval($rang[0]) * $rate;
                    if (isset($rang[1]) && is_numeric($rang[1])) {
                        $rang[1] = intval($rang[1]) * $rate;
                        if ($rang[1] <= $rang[0]) {
                            throw new Exception(Httpsdav_StatusCode::$message[422], 422);
                        }
                    } else {
                        $rang[1] = -1;
                    }
                    $arrArangs[$rang[0]] = $rang;
                }
                ksort($arrArangs, SORT_NUMERIC);
                $this->range = current($arrArangs);
            }
        }
    }
}