<?php

/**
 * @name Method_PropFind
 * @desc Handle Request By PropFind Method
 * @author 刘重量(13439694341@qq.com)
 */
class Method_PropFind extends Dav_Method
{
    const BODY_ROOT = 'propfind';
    protected $arrInput = [
        'resources' => [],
        'fields'    => ['ns_id', 'prop_name', 'prop_value'],
        'prop'      => [],
        'depth'     => 0,
    ];
    private $statusList = [];

    /**
     * 执行对通过propfind方法调用发来请求数据的处理
     * @return array
     * @throws \Exception
     */
    public function handler()
    {
        $objResource = Dav_Resource::getInstance();
        if ($objResource->status == Dav_Resource::STATUS_DELETE) {
            return ['code' => 404];
        }
        if ($objResource->status == Dav_Resource::STATUS_FAILED) {
            return ['code' => 503];
        }
        $multistatus = [];
        foreach ($this->arrInput['resources'] as $resource) {
            $objResource = Dav_Resource::getInstance($resource, true);
            $responseList = $this->getResponse($objResource, $this->arrInput['depth']);
            $multistatus = array_merge($multistatus, $responseList);
        }
        $statsList = array_unique($this->statusList);
        return [
            'code' => count($multistatus) == 1 ? current($statsList) : 207,
            'body' => ['multistatus', $multistatus]
        ];
    }


    /**
     * 获取对指定处理资源的执行按照请求的查询条件检索指定范围的属性值集合结果
     * @param Dav_Resource $objResource
     * @param int $depth
     * @return array
     * @throws Exception
     */
    private function getResponse(Dav_Resource $objResource, $depth)
    {
        $arrResponseList = [];
        $response = [['href', Dav_Utils::href_encode($objResource->path)]];
        if (!isset($objResource->status) || false === $objResource->status) {
            $response[] = ['propstat', [['status', Dav_Status::$Msg[503]]]];
            $arrResponseList[] = ['response', $response];
            $this->statusList[] = 503;
            return $arrResponseList;
        }
        if ($objResource->status == Dav_Resource::STATUS_DELETE) {
            $response[] = ['propstat', [['status', Dav_Status::$Msg[404]]]];
            $arrResponseList[] = ['response', $response];
            $this->statusList[] = 404;
            return $arrResponseList;
        }
        $arrFoundProp = $objResource->getPropFind($this->arrInput['fields'], $this->arrInput['prop']);
        if (empty($arrFoundProp)) {
            $response[] = ['propstat', [['status', Dav_Status::$Msg[404]]]];
            $arrResponseList[] = ['response', $response];
            $this->statusList[] = 404;
            return $arrResponseList;
        }
        if (empty($prop)) {
            foreach ($arrFoundProp as $k => $v) {
                $propValue = isset($v['prop_value']) ? $v['prop_value'] : null;
                $arrFoundProp[$k] = [$v['prop_name'], $propValue, $v['ns_id']];
            }
            $response[] = ['propstat', [['prop', $arrFoundProp], ['status', Dav_Status::$Msg[200]]]];
            $this->statusList[] = 200;
            $arrResponseList[] = ['response', $response];
        } else {
            $arrFindProp = $prop;
            foreach ($arrFoundProp as $k => $v) {
                $arrFoundProp[$k] = [$v['prop_name'], $v['prop_value'], $v['ns_id']];
                $arrFindProp[$v['ns_id']] = array_diff($arrFindProp[$v['ns_id']], [$v['prop_name']]);
                if (empty($arrFindProp[$v['ns_id']])) {
                    unset($arrFindProp[$v['ns_id']]);
                }
            }
            $arrProp = array_values($arrFoundProp);
            $response[] = ['propstat', [['prop', $arrProp], ['status', Dav_Status::$Msg[200]]]];
            $this->statusList[] = 200;
            if (!empty($arrFindProp)) {
                $arrProp = [];
                foreach ($arrFindProp as $nsId => $propNames) {
                    foreach ($propNames as $name) {
                        $arrProp[] = [$name, null, $nsId];
                    }
                }
                $this->statusList[] = 404;
                $response[] = ['propstat', [['prop', $arrProp], ['status', Dav_Status::$Msg[404]]]];
            }
            $arrResponseList[] = ['response', $response];
        }
        if ($objResource->content_type == Dao_ResourceProp::MIME_TYPE_DIR && $depth != 0) {
            --$depth;
            $arrChildren = $objResource->getChildren();
            foreach ($arrChildren as $objChild) {
                $arrChildResponseList = $this->getResponse($objChild, $depth);
                $arrResponseList = array_merge($arrResponseList, $arrChildResponseList);
            }
        }
        return $arrResponseList;
    }

    /**
     * 获取并格式化数据请求数组
     */
    protected function getArrInput()
    {
        $this->arrInput['resources'] = [$_REQUEST['HEADERS']['Resource']];
        $this->arrInput['depth'] = empty($_REQUEST['HEADERS']['Depth']) ? 0 : (is_numeric($_REQUEST['HEADERS']['Depth']) ? intval($_REQUEST['HEADERS']['Depth']) : -1);
        if (isset($_REQUEST['HEADERS']['Redirect-Status'])) {
            $this->arrInput['Redirect-Status'] = intval($_REQUEST['HEADERS']['Redirect-Status']);
        }
        $requireData = Dav_Utils::getDomElement(self::BODY_ROOT);
        if (!empty($requireData)) {
            $this->getRequestResourceList();
            $this->getRequestPropList();
            $this->getRequestFields();
        }
    }

    /**
     *  格式化资源请求项
     */
    private function getRequestResourceList()
    {
        $objTarget = Dav_Utils::getObjElements(self::BODY_ROOT . '/target/href');
        if (!empty($objTarget) && $objTarget->length > 0) {
            $arrResource = [];
            for ($i = 0; $i < $objTarget->length; ++$i) {
                $href = trim($objTarget->item($i)->nodeValue);
                if (!empty($href)) {
                    if (0 === strpos($href, $_REQUEST['HEADERS']['Uri'])) {
                        $href = substr($href, strlen($_REQUEST['HEADERS']['Uri']));
                    }
                    $arrResource[] = rtrim($_REQUEST['HEADERS']['Path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim(urldecode($href), DIRECTORY_SEPARATOR);
                }
            }
            if (count($arrResource) > 0) {
                $arrInput['resources'] = $arrResource;
                $this->arrInput = $arrResource;
            }
        }
    }

    /**
     * 获取并格式化请求数据的prop列表项
     */
    private function getRequestPropList()
    {
        $elementProps = Dav_Utils::getObjElements(self::BODY_ROOT . '/prop');
        if (!empty($elementProps) && $elementProps->length > 0) {
            for ($i = 0; $i < $elementProps->length; ++$i) {
                $props = $elementProps->item($i);
                if ($props->childNodes->length > 0) {
                    for ($j = 0; $j < $props->childNodes->length; ++$j) {
                        $prop_name = trim($props->childNodes->item($j)->localName);
                        if (!empty($prop_name)) {
                            $nsId = Dav_Utils::getNsIdByUri($props->childNodes->item($j)->namespaceURI);
                            $this->arrInput['prop'][$nsId][] = $prop_name;
                        }
                    }
                }
            }
        }
    }

    /**
     * 获取并格式化请求数据的fields项
     */
    private function getRequestFields()
    {
        $nodePropName = Dav_Utils::getDomElement(self::BODY_ROOT . '/propname');
        if (!empty($nodePropName)) {
            $this->arrInput['fields'] = ['ns_id', 'prop_name'];
        }
    }
}
