<?php

/**
 * Class Handler_Get
 */
class Handler_Get extends HttpsDav_BaseHander
{
    const BLOCK_SIZE = MAX_READ_LENGTH; //区间请求，分段传输，大小上限
    protected $arrInput = [
        'etag' => [],
        'lastmodified' => 0,
        'range' => null,
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
    private $textContentTypeList = ['application/unknow', 'inode/x-empty'];

    private $objResource = null;

    /**
     * 执行客户端调用GET方法发来获取资源数据的请求任务
     * @return array
     * @throws \Exception
     */
    public function handler()
    {
        $this->objResource = Service_Data_Resource::getInstance();
        $objResource = $this->objResource;
        if (!isset($objResource->status)) {
            return ['code' => 503];
        }
        if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
            return ['code' => 404];
        }
        if ($objResource->content_type == Dao_ResourceProp::MIME_TYPE_DIR) {
            $collectHtml = $objResource->getCollectView();
            return [
                'code' => 200,
                'headers' => [
                    'Cache-Control: no-cache, no-store, must-revalidate',
                    'Content-Type: text/html; charset=utf8',
                ],
                'body' => $collectHtml,
            ];
        }
        $contentType = in_array($objResource->content_type, $this->textContentTypeList) ? 'text/plain' : $objResource->content_type;
        $response = [
            'Content-Type' => $contentType,
            'headers' => [
                'Accept-Ranges: bytes',
                'Vary: Accept-Encoding',
                'ETag: "' . $objResource->etag . '"',
                'Date: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT',
                'Last-Modified: ' . gmdate('D, d M Y H:i:s', $objResource->last_modified) . ' GMT',
                'Cache-Control: max-age=86400, must-revalidate',
            ]
        ];
        if ($objResource->content_length == 0) {
            $response['headers'][] = 'Content-Length: 0';
            return $response;
        }
        $arrRanges = (empty($this->arrInput['range']) || $objResource->hadChanged($this->arrInput)) ? [['start' => 0, 'end' => $objResource->content_length - 1]] : $this->arrInput['range'];
        $rangeList = [];
        $contentEnd = $objResource->content_length - 1;
        foreach ($arrRanges as $range) {
            $range['end'] = empty($range['end']) ? $contentEnd : min($range['end'], $contentEnd);
            if ($range['end'] >= $range['start'] && $range['end'] <= $contentEnd) {
                $rangeList[] = $range;
            }
        }
        if (empty($rangeList)) {
            $response['code'] = 416;
            $response['headers'][] = 'Content-Range: */' . $objResource->content_length;
            return $response;
        }
        if (count($rangeList) == 1) {
            $range = current($rangeList);
            $length = $range['end'] - $range['start'] + 1;
            $response['headers'][] = 'Content-Length: ' . $length;
            $response['headers'][] = 'Content-Type: ' . $contentType;
            if ($length == $objResource->content_length) {
                $response['code'] = 200;
            } else {
                $response['code'] = 206;
                $response['headers'][] = 'Content-Range: bytes ' . $range['start'] . '-' . $range['end'] . '/' . $objResource->content_length;
            }
            $response['code'] = $length == $objResource->content_length ? 200 : 206;
            if ($length <= self::BLOCK_SIZE) {
                $contentData = $objResource->getContent($range['start'], $length);
                if ($contentData === false || strlen($contentData) < $length) {
                    $response['code'] = 503;
                    return $response;
                }

                $response['body'] = $contentData;
                return $response;
            }
            $response['headers'][] = HttpsDav_StatusCode::$message[$response['code']];
            foreach ($response['headers'] as $field) {
                header($field);
            }
            $this->outMaxContent($objResource, $range['start'], $range['end']);
            return ['code' => 0];
        }
        set_time_limit(0);
        $response['headers'][] = HttpsDav_StatusCode::$message[206];
        $response['headers'][] = 'Content-Type: multipart/byteranges; boundary=SWORD_OF_LZL';
        foreach ($response['headers'] as $field) {
            header($field);
        }
        foreach ($rangeList as $range) {
            $rangeHeaders = [
                '--SWORD_OF_LZL',
                'Content-Type: ' . $contentType,
                'Content-Range: bytes ' . $range['start'] . '-' . $range['end'] . '/' . $objResource->content_length
            ];
            $rangeHeaders = implode("\r\n", $rangeHeaders) . "\r\n";
            $getSendRes = file_put_contents('php://output', $rangeHeaders);
            if (false === $getSendRes) {
                return ['code' => 0];
            }
            $getSendRes = $this->outMaxContent($objResource, $range['start'], $range['end']);
            if (false === $getSendRes) {
                return ['code' => 0];
            }
        }
        return ['code' => 0];
    }

    /**
     * 输出需要超出预设置的单次限定发送单元大小上限需要分段传输的资源内容区间数据
     * @param \Service_Data_Resource $objResource
     * @param int $start
     * @param int $end
     * @return bool
     */
    private function outMaxContent(Service_Data_Resource $objResource, $start = 0, $end = -1)
    {
        while ($start < $end) {
            $length = min(self::BLOCK_SIZE, $end - $start + 1);
            $contentData = $objResource->getContent($start, $length);
            if (false === $contentData || strlen($contentData) != $length) {
                return false;
            }
            $size = file_put_contents('php://output', $contentData);
            if ($size != $length) {
                return false;
            }
            ob_flush();
            $start += $length;
        }
        return true;
    }

    /**
     * 获取并格式化数据请求数组
     * @return array|mixed
     * @throws \Exception
     */
    protected function getArrInput()
    {
        $this->arrInput = [
            'etag' => HttpsDav_Request::getETagList(),
            'lastmodified' => HttpsDav_Request::getLastModified()
        ];
        if (!empty(HttpsDav_Request::$_Headers['Range'])) {
            $range = explode('=', HttpsDav_Request::$_Headers['Range']);
            $unit = strtolower($range[0]);
            if (!isset($this->arrRate[$unit])) {
                throw new Exception(Httpsdav_StatusCode::$message[422], 422);
            }
            if (!empty($range[1])) {
                $arrRanges = explode(',', $range[1]);
                $rangeList = [];
                $rate = $this->arrRate[$unit];
                foreach ($arrRanges as $strRange) {
                    $range = explode('-', $strRange);
                    if (count($range) > 2) {
                        throw new Exception(Httpsdav_StatusCode::$message[422], 422);
                    }
                    if (!is_numeric($range[0]) || $range[0] < 0) {
                        throw new Exception(Httpsdav_StatusCode::$message[422], 422);
                    }
                    $range[0] = intval($range[0]) * $rate;
                    if (isset($range[1]) && is_numeric($range[1])) {
                        $range[1] = intval($range[1]) * $rate;
                        if ($range[1] < $range[0]) {
                            throw new Exception(Httpsdav_StatusCode::$message[422], 422);
                        }
                    } else {
                        $range[1] = null;
                    }
                    $rangeList[$range[0]] = ['start' => $range[0], 'end' => $range[1]];
                }
                ksort($rangeList, SORT_NUMERIC);
                $this->arrInput['range'] = $rangeList;
            }
        }
    }
}