<?php
/**
 * @name Handler_PropFind
 * @desc Handle Request By PropFind Method
 * @author 刘重量(13439694341@qq.com)
 */
class Handler_PropFind extends HttpsDav_BaseHander
{
    const BODY_ROOT = 'propfind';
    protected $arrInput = [
        'resources' => [REQUEST_RESOURCE],
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
        $objResource = Service_Data_Resource::getInstance();
        if (!isset($objResource->status)) {
            return ['code' => 503];
        }
        if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
            return ['code' => 404];
        }
        $multistatus = [];
        foreach ($this->arrInput['resources'] as $resource) {
            $objResource = Service_Data_Resource::getInstance($resource);
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
     * @param Service_Data_Resource $objResource
     * @param int $depth
     * @return array
     * @throws Exception
     */
    private function getResponse(Service_Data_Resource $objResource, $depth)
    {
        $arrResponseList = [];
        $response = [['href', HttpsDav_Server::href_encode($objResource->path)]];
        if (!isset($objResource->status) || false === $objResource->status) {
            $response[] = ['propstat', [['status', HttpsDav_StatusCode::$message[503]]]];
            $arrResponseList[] = ['response', $response];
            $this->statusList[] = 503;
            return $arrResponseList;
        }
        if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
            $response[] = ['propstat', [['status', HttpsDav_StatusCode::$message[404]]]];
            $arrResponseList[] = ['response', $response];
            $this->statusList[] = 404;
            return $arrResponseList;
        }
        $arrFoundProp = $objResource->getPropFind($this->arrInput['fields'], $this->arrInput['prop']);
        if (empty($arrFoundProp)) {
            $response[] = ['propstat', [['status', HttpsDav_StatusCode::$message[404]]]];
            $arrResponseList[] = ['response', $response];
            $this->statusList[] = 404;
            return $arrResponseList;
        }
        if (empty($prop)) {
            foreach ($arrFoundProp as $k => $v) {
                $propValue = isset($v['prop_value']) ? $v['prop_value'] : null;
                $arrFoundProp[$k] = [$v['prop_name'], $propValue, $v['ns_id']];
            }
            $response[] = ['propstat', [['prop', $arrFoundProp], ['status', HttpsDav_StatusCode::$message[200]]]];
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
            $response[] = ['propstat', [['prop', $arrProp], ['status', HttpsDav_StatusCode::$message[200]]]];
            $this->statusList[] = 200;
            if (!empty($arrFindProp)) {
                $arrProp = [];
                foreach ($arrFindProp as $nsId => $propNames) {
                    foreach ($propNames as $name) {
                        $arrProp[] = [$name, null, $nsId];
                    }
                }
                $this->statusList[] = 404;
                $response[] = ['propstat', [['prop', $arrProp], ['status', HttpsDav_StatusCode::$message[404]]]];
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
        $this->arrInput['depth'] = empty(HttpsDav_Request::$_Headers['Depth']) ? 0 : (is_numeric(HttpsDav_Request::$_Headers['Depth']) ? intval(HttpsDav_Request::$_Headers['Depth']) : -1);
        if (isset(HttpsDav_Request::$_Headers['Redirect-Status'])) {
            $this->arrInput['Redirect-Status'] = intval(HttpsDav_Request::$_Headers['Redirect-Status']);
        }
        $requireData = HttpsDav_Request::getDomElement(self::BODY_ROOT);
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
        $objTarget = HttpsDav_Request::getObjElements(self::BODY_ROOT . '/target/href');
        if (!empty($objTarget) && $objTarget->length > 0) {
            $arrResource = [];
            for ($i = 0; $i < $objTarget->length; ++$i) {
                $href = trim($objTarget->item($i)->nodeValue);
                if (!empty($href)) {
                    if (0 === strpos($href, REQUEST_URI)) {
                        $href = substr($href, strlen(REQUEST_URI));
                    }
                    $path = rtrim(REQUEST_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($href, DIRECTORY_SEPARATOR);
                    $arrResource[] = rtrim(DAV_ROOT . urldecode($path), DIRECTORY_SEPARATOR);
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
        $elementProps = HttpsDav_Request::getObjElements(self::BODY_ROOT . '/prop');
        if (!empty($elementProps) && $elementProps->length > 0) {
            for ($i = 0; $i < $elementProps->length; ++$i) {
                $props = $elementProps->item($i);
                if ($props->childNodes->length > 0) {
                    for ($j = 0; $j < $props->childNodes->length; ++$j) {
                        $prop_name = trim($props->childNodes->item($j)->localName);
                        if (!empty($prop_name)) {
                            $nsId = HttpsDav_Server::getNsIdByUri($props->childNodes->item($j)->namespaceURI);
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
        $nodePropName = HttpsDav_Request::getDomElement(self::BODY_ROOT . '/propname');
        if (!empty($nodePropName)) {
            $this->arrInput['fields'] = ['ns_id', 'prop_name'];
        }
    }
}