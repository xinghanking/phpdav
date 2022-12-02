<?php

/**
 * Class Method_PropPatch
 */
class Method_PropPatch extends Dav_Method
{
    const BODY_ROOT = 'propertyupdate';
    protected $arrInput = [
        'Lock-Token' => [],
        'props'      => [],
        'set'        => [],
        'remove'     => [],
    ];

    /**
     * 执行对客户端通过PROPPATCH方法发来请求的任务处理，并返回执行结果
     * @return array
     * @throws Exception
     */
    protected function handler()
    {
        $objResource = Dav_Resource::getInstance($_REQUEST['HEADERS']['Resource']);
        if (empty($objResource) || $objResource->status == Dav_Resource::STATUS_FAILED) {
            return ['code' => 503];
        }
        if ($objResource->status == Dav_Resource::STATUS_DELETE) {
            return ['code' => 404];
        }
        $isLocked = $objResource->checkLocked();
        if ($isLocked && !(isset($_SESSSION['user']) && in_array($_SESSSION['user'], $objResource->locked_info['owner'])) && !in_array($objResource->locked_info['locktoken'], $this->arrInput['Lock-Token'])) {
            return ['code' => 403];
        }
        $response = ['code' => 503];
        $res = $objResource->propPatch($this->arrInput);
        if ($res) {
            $response = [
                'code' => 200,
                'body' => [
                    'multistatus',
                    [[
                        'response',
                        [
                            ['href', $_REQUEST['HEADERS']['Uri']],
                            ['propstat', [['prop', $this->arrInput['props']], ['status', Dav_Status::$Msg[200]]]]
                        ]
                    ]]
                ]
            ];
        }
        return $response;
    }

    /**
     * 获取数组格式化客户端发来的请求数据
     */
    protected function getArrInput()
    {
        $this->arrInput['Lock-Token'] = Dav_Utils::getLockToken();
        $requestData = Dav_Utils::getObjElements(self::BODY_ROOT);
        if (empty($requestData)) {
            $this->formatStatus = false;
        } else {
            $this->getSetProps();
            $this->getRemoveProps();
        }
        if (empty($this->arrInput['props'])) {
            $this->formatStatus = false;
        }
    }

    /**
     * 获取并格式化请求数据中要设置的属性列表的请求项数组
     */
    private function getSetProps()
    {
        $objSetProp = Dav_Utils::getObjElements(self::BODY_ROOT . '/set/prop');
        if (!empty($objSetProp) && $objSetProp->length > 0) {
            for ($i = 0; $i < $objSetProp->length; ++$i) {
                $props = $objSetProp->item($i);
                if ($props->childNodes->length > 0) {
                    for ($j = 0; $j < $props->childNodes->length; ++$j) {
                        $prop_name = trim($props->childNodes->item($j)->localName);
                        if (!empty($prop_name)) {
                            $nsId = Dav_Utils::getNsIdByUri($props->childNodes->item($j)->namespaceURI);
                            $this->arrInput['set'][] = [
                                'ns_id'      => $nsId,
                                'prop_name'  => $prop_name,
                                'prop_value' => Dav_Utils::xml_decode($props->childNodes->item($j)),
                            ];
                            $this->arrInput['props'][] = [$prop_name, null, $nsId];
                        }
                    }
                }
            }
        }
    }

    /**
     * 获取并数组格式化请求数据中表示要删除的属性项列表
     */
    private function getRemoveProps()
    {
        $objRemoveProp = Dav_Utils::getObjElements(self::BODY_ROOT . '/remove/prop');
        if (!empty($objRemoveProp) && $objRemoveProp->length > 0) {
            for ($i = 0; $i < $objRemoveProp->length; ++$i) {
                $props = $objRemoveProp->item($i);
                if ($props->childNodes->length > 0) {
                    for ($j = 0; $j < $props->childNodes->length; ++$j) {
                        $prop_name = trim($props->childNodes->item($j)->localName);
                        if (!empty($prop_name)) {
                            $nsId = Dav_Utils::getNsIdByUri($props->childNodes->item($j)->namespaceURI);
                            $this->arrInput['remove'][] = [
                                'ns_id'     => $nsId,
                                'prop_name' => $prop_name,
                            ];
                            $this->arrInput['props'][] = [$prop_name, null, $nsId];
                        }
                    }
                }
            }
        }
    }
}
