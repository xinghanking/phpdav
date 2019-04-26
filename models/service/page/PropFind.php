<?php
/**
 * @name   Service_Page_Propfind
 * @desc   执行webdav的propfind方法
 * @author 刘重量(13439694341@qq.com)
 */
class Service_Page_PropFind {
    private $statusCode = 207;

    /**
     * 执行webdav的propfind方法
     * @param array input
     * @return array result
     **/
    public function execute(array $arrInput)
    {
        $arrResponseList = [];
        try {
            $objResource = Service_Data_Resource::getInstance();
            if (!isset($objResource->status)) {
                return ['code' => 500];
            }
            if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
                return ['code' => 404];
            }
            $fields = array_merge(['ns_id'], $arrInput['fields']);
            foreach ($arrInput['resources'] as $resourcePath) {
                $objResource = Service_Data_Resource::getInstance($resourcePath);
                $arrResponse = $this->getResponse($objResource, $fields, $arrInput['prop'], $arrInput['depth']);
                $arrResponseList = array_merge($arrResponseList, $arrResponse);
            }
        } catch (Exception $e) {
            Bd_Log::warning($e->getMessage(), $e->getCode());
            $errorCode = $e->getCode();
            $responseCode = isset(Httpsdav_StatusCode::$message[$errorCode]) ? $errorCode : 50;
            return ['code' => $responseCode];
        }
        if (count($arrResponseList) > 1) {
            $this->statusCode = 207;
        }
        return ['code' => $this->statusCode, 'body' => ['multistatus', $arrResponseList]];
    }

    /**
     * 按查询条件查询指定字段范围的资源属性
     * @param Service_Data_Resource $objResource 被查找资源的一个实例化对象
     * @param  array $fields 查询资源属性的字段
     * @param  array $prop   查找的资源属性数组
     * @param  int   $depth  查询深度
     * @return array
     */
    public function getResponse($objResource, array $fields, array $prop = [], int $depth = 0)
    {
        $arrResponseList = [];
        $response = [['href', Httpsdav_Server::href_encode($objResource->path)]];
        if (!isset($objResource->status)) {
            $this->statusCode = 500;
            $response[] = ['propstat', [['status', Httpsdav_StatusCode::$message[500]]]];
            $arrResponseList[] = ['response', $response];
            return $arrResponseList;
        }
        if ($objResource->status == Service_Data_Resource::STATUS_DELETE) {
            $this->statusCode = 404;
            $response[] = ['propstat', [['status', Httpsdav_StatusCode::$message[404]]]];
            $arrResponseList[] = ['response', $response];
            return $arrResponseList;
        }
        $arrFoundProp = $objResource->getPropFind($fields, $prop);
        if (empty($arrFoundProp)) {
            $this->statusCode = 404;
            $response[] = ['propstat', [['status', Httpsdav_StatusCode::$message[404]]]];
            $arrResponseList[] = ['response', $response];
            return $arrResponseList;
        }
        $arrPropStat = [];
        if (empty($prop)) {
            foreach ($arrFoundProp as $k => $v) {
                $propValue = $v['prop_value'] ?? null;
                $arrFoundProp[$k] = [$v['prop_name'], $propValue, $v['ns_id']];
            }
            $this->statusCode = 200;
            $response[] = ['propstat', [['prop', $arrFoundProp], ['status', Httpsdav_StatusCode::$message[200]]]];
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
            $this->statusCode = 200;
            $response[] = ['propstat', [['prop', $arrProp], ['status', Httpsdav_StatusCode::$message[200]]]];
            if (!empty($arrFindProp)) {
                $arrProp = [];
                foreach ($arrFindProp as $nsId => $propNames) {
                    foreach ($propNames as $name) {
                        $arrProp[] = [$name, null, $nsId];
                    }
                }
                $this->statusCode = 404;
                $response[] = ['propstat', [['prop', $arrProp], ['status', Httpsdav_StatusCode::$message[404]]]];
            }
            $arrResponseList[] = ['response', $response];
        }
        if ($objResource->is_collection == Service_Data_Resource::BOOL_YES && $depth != 0) {
            --$depth;
            $arrChildren = $objResource->getChildren();
            foreach ($arrChildren as $objChild) {
                $arrChildResponseList = $this->getResponse($objChild, $fields, $prop, $depth);
                $arrResponseList = array_merge($arrResponseList, $arrChildResponseList);
            }
        }
        return $arrResponseList;
    }
}
