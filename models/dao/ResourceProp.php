<?php

/**
 * @name Dao_ResourceProp
 * @desc 对resources_property表的操作
 * @author 刘重量(13439694341@qq.com)
 */
class Dao_ResourceProp extends HttpsDav_Db
{
    const TABLE = 'resource_prop';

    const MIME_TYPE_UNKNOW = 'application/unknow';
    const MIME_TYPE_DIR    = 'application/x-director';

    protected function init()
    {
        $this->_tbl = '`' . self::TABLE . '`';
    }

    /**
     * 检查更新资源基本属性的存储信息
     * @param array $info 存储的资源基本信息
     * @return bool
     * @throws Exception
     */
    public function upsertBaseProperties(array &$info)
    {
        clearstatcache();
        $arrStatList = stat($info['path']);
        $arrProperties = [
            'displayname'    => basename($info['path']),
            'resourcetype'   => '',
            'getcontenttype' => self::MIME_TYPE_DIR,
            'getetag'        => '',
        ];
        $atime = time();
        $mtime = empty($info['last_modified']) ? $atime : $info['last_modified'];
        $ctime = empty($info['creation_date']) ? $mtime : strtotime($info['creation_date']);
        if (!empty($arrStatList)) {
            $arrStatList['atime'] = max($arrStatList['atime'], $arrStatList['mtime'], $arrStatList['ctime']);
            $atime = empty($arrStatList['atime']) ? $atime : intval($arrStatList['atime']);
            $mtime = empty($arrStatList['mtime']) ? max($atime, $mtime) : intval($arrStatList['mtime']);
            $ctime = min($ctime, $mtime);
        }
        $arrProperties['getcontentlength'] = empty($arrStatList['size']) ? 0 : $arrStatList['size'];
        $info['content_length'] = $arrProperties['getcontentlength'];
        $arrProperties['getetag'] = dechex($arrProperties['getcontentlength']) . '-' . dechex($mtime);
        if (isset($info['etag']) && $info['etag'] == $arrProperties['getetag']) {
            return true;
        }
        $info['etag'] = $arrProperties['getetag'];
        if (is_file($info['path'])) {
            $arrProperties['getcontenttype'] = self::getFileMimeType($info['path']);
        }
        $info['last_modified'] = $mtime;
        $info['content_type'] = $arrProperties['getcontenttype'];
        $arrProperties['creationdate'] = gmdate('Y-m-d\TH:i:s\Z', $ctime);
        $arrProperties['getlastmodified'] = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
        if (is_dir($info['path'])) {
            $arrProperties['resourcetype'] = [['collection']];
        }
        if (is_writable($info['path'])) {
            $arrProperties['supportedlock'] = [
                ['lockentry', [['lockscope', [['exclusive']]], ['locktype', [['write']]]]],
                ['lockentry', [['lockscope', [['shared']]], ['locktype', [['write']]]]],
            ];
        } else {
            $arrProperties['lockdiscovery'] = [['activelock', [['lockscope', [['exclusive']]], ['locktype', [['write']]], ['depth', 0]]]];
        }
        try {
            $this->beginTransaction();
            if (empty($info['id'])) {
                $info['id'] = Dao_DavResource::getInstance()->insert($info);
                foreach ($arrProperties as $propName => $propValue) {
                    $arrProp = [
                        'ns_id'       => NS_DAV_ID,
                        'resource_id' => $info['id'],
                        'prop_name'   => $propName,
                        'prop_value'  => self::value_encode($propValue),
                    ];
                    $this->insert($arrProp);
                }
            } else {
                Dao_DavResource::getInstance()->update(['content_type' => $info['content_type'], 'content_length' => $info['content_length'], 'last_modified' => $info['last_modified'], '`etag`=' => $info['etag']], ['`id`=' => $info['id']]);
                foreach ($arrProperties as $propName => $propValue) {
                    $this->update(['prop_value' => self::value_encode($propValue)], [
                        '`ns_id`='       => NS_DAV_ID,
                        '`resource_id`=' => $info['id'],
                        '`prop_name`='   => $propName,
                    ]);
                }
            }
            $this->commit();
        } catch (Exception $e) {
            HttpsDav_Log::error($e);
            $this->rollback();
            $info = false;
            return false;
        }
        return true;
    }

    /**
     * 设置资源属性
     * @param int    $resourceId 资源编号
     * @param string $propName   资源属性名
     * @param string|array $propValue 资源属性值
     * @param int $nsId 命名空间编号
     * @return bool
     * @throws \Exception
     */
    public function setResourceProp($resourceId, $propName, $propValue, $nsId = NS_DAV_ID)
    {
        $resourceProp = [
            'resource_id' => $resourceId,
            'ns_id'       => $nsId,
            'prop_name'   => $propName,
            'prop_value'  => self::value_encode($propValue),
        ];
        return $this->replace($resourceProp);
    }

    /**
     * 对一个资源移除符合条件的一个属性
     * @param  int    $resourceId 资源id
     * @param  string $propName   被移除的属性名
     * @param  int    $nsId       所属命名空间
     * @return int
     * @throws \Exception
     */
    public function removeResourceProp($resourceId, $propName, $nsId = NS_DAV_ID)
    {
        $conditions = [
            '`resource_id`=' . $resourceId,
            '`ns_id`='       . $nsId,
            "`prop_name`='"  . $propName . "'",
        ];
        return $this->delete($conditions);
    }

    /**
     * 复制资源属性
     * @param int $sourceId 源资源id
     * @param int $destId 目标资源id
     * @throws \Exception
     */
    public function copyProp($sourceId, $destId)
    {
        $propLIst = $this->select(['ns_id', 'prop_name', 'prop_value'], ['WHERE' => ['resource_id=' => $sourceId]]);
        foreach ($propLIst as $prop) {
            $prop['resource_id'] = $destId;
            $this->replace($prop);
        }
    }

    /**
     * 查询资源的属性
     * @param  array $fields 查询的属性选项列表数组（可查找：所属资源编号、所属命名空间、属性名、属性值）
     * @param  array $conditions 查找条件
     * @return array
     * @throws Exception
     */
    public function getProperties(array $fields = ['ns_id', 'prop_name', 'prop_value'], array $conditions = ['`ns_id`=' => NS_DAV_ID])
    {
        $arrProp = $this->select($fields, $conditions);
        if (in_array('prop_value', $fields)) {
            foreach ($arrProp as $k => $v) {
                $propValue = self::value_decode($v['prop_value']);
                if ($v['prop_name'] == 'lockdiscovery' && is_array($propValue)) {
                    $propValue = self::lockedInfo_decode($propValue);
                }
                $arrProp[$k]['prop_value'] = $propValue;
            }
        }
        return $arrProp;
    }

    /**
     * 获取资源自身的加锁属性值
     * @param  int   $resourceId
     * @return array
     * @throws \Exception
     */
    public function getLockDiscovery($resourceId)
    {
        $lockDiscovery = $this->getColumn('prop_value', ['`ns_id`=' . NS_DAV_ID, '`resource_id`=' . $resourceId, "`prop_name`='lockdiscovery'"]);
        $lockDiscovery = empty($lockDiscovery) ? [] : self::value_decode($lockDiscovery);
        return $lockDiscovery;
    }

    /**
     * 获取从根级以后继承的所有的加锁信息
     * @param  string $path 资源路径
     * @return array
     * @throws Exception
     */
    public function getInheritLockedInfo($path)
    {
        $arrPaths = ["'" . $path . "'"];
        while ($path != DIRECTORY_SEPARATOR) {
            $path = dirname($path);
            $arrPaths[] = "'" . $path . "'";
        }
        $conditions = [
            '`ns_id`=' . NS_DAV_ID,
            "`prop_name`='lockdiscovery'",
            '`resource_id` IN (SELECT `id` FROM ' . Dao_DavResource::TABLE . ' WHERE `path` IN (' . implode(',', $arrPaths) . '))',
        ];
        $arrInfo = $this->select('`id`,`prop_value`', $conditions);
        if (empty($arrInfo) || !is_array($arrInfo)) {
            return [];
        }
        $lockedInfo = [];
        foreach ($arrInfo as $info) {
            $value = self::value_decode($info['prop_value']);
            if (!empty($value['owner']) && !empty($value['lock_time']) && !empty($value['timeout'])) {
                if (time() - $value['lock_time'] >= $value['timeout']) {
                    $this->update(['prop_value' => '[null]'], ['`id`=' . $info['id']]);
                } else {
                    $lockedInfo[] = $value;
                }
            }
        }
        return $lockedInfo;
    }

    /**
     * 获取一个集合（文件夹）资源内所包含的所有内部资源加锁信息
     * @param  string $path 资源路径
     * @return array
     * @throws \Exception
     */
    public function getCollectLockedInfo($path)
    {
        $conditions = [
            'FROM'  => self::TABLE . ' As `p`,' . Dao_DavResource::TABLE . ' AS `r`',
            'WHERE' => [
                '`r`.`id`=`p`.`resource_id`',
                "`r`.`path` LIKE '" . $path . DIRECTORY_SEPARATOR . "%'",
                '`p`.`ns_id`=' . NS_DAV_ID,
                "`p`.`prop_name`='lockdiscovery'",
                "`p`.`prop_value`!='[null]'",
            ],
        ];
        $infoList = $this->select('`r`.`path` AS `path`,`p`.`id` AS `id`, `p`.`prop_value` AS `prop_value`', $conditions);
        if (empty($infoList) || !is_array($infoList)) {
            return [];
        }
        $arrLockedInfo = [];
        foreach ($infoList as $lockedInfo) {
            $value = self::value_decode($lockedInfo['prop_value']);
            if (!empty($value['owner']) && !empty($value['lock_time']) && !empty($value['timeout'])) {
                if (time() - $value['lock_time'] >= $value['timeout']) {
                    $this->update(['prop_value' => '[null]'], ['`id`=' . $lockedInfo['id']]);
                } else {
                    $arrLockedInfo [$lockedInfo['path']] = $value;
                }
            }
        }
        return $arrLockedInfo;
    }

    /**
     * 将存储资源加锁信息转换成输出前预处理的格式
     * @param  array $lockedInfo
     * @return array|null
     */
    public static function lockedInfo_decode(array $lockedInfo)
    {
        if (empty($lockedInfo['owner']) || empty($lockedInfo['lock_time']) || empty($lockedInfo['timeout'])) {
            return $lockedInfo;
        }
        if (time() - $lockedInfo['lock_time'] >= $lockedInfo['timeout']){
            return null;
        }
        if (is_array($lockedInfo['owner'])) {
            foreach ($lockedInfo['owner'] as $k => $v) {
                $lockedInfo['owner'][$k] = ['href', $v];
            }
        }
        if (empty($lockedInfo['locktoken'])) {
            $lockedInfo['locktoken'] = null;
        } elseif (is_array($lockedInfo['locktoken'])) {
            foreach ($lockedInfo['locktoken'] as $k => $v) {
                $lockedInfo['locktoken'][$k] = ['href', $v];
            }
        }
        $lockedValue = [
            [
                'activelock',
                [
                    ['lockscope', [['exclusive']]],
                    ['locktype', [['write']]],
                    ['depth', 'Infinity'],
                    ['owner', $lockedInfo['owner']],
                    ['timeout', 'Second-' . $lockedInfo['timeout']],
                    ['locktoken', $lockedInfo['locktoken']],
                ],
            ],
        ];
        return $lockedValue;
    }

    /**
     * 将资源属性值由程序运行使用的格式编码成存储所用的格式(json格式)
     * @param  string|array $value
     * @return string
     */
    public static function value_encode($value)
    {
        return json_encode([$value], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 将存储的资源属性值格式解码成程序运行所需要的格式
     * @param  string $value
     * @return array|string
     */
    public static function value_decode($value)
    {
        $value = json_decode($value, true);
        return $value[0];
    }

    /**
     * 获取资源的MIME类型
     * @param  string $filePath 资源全路径
     * @return string
     */
    public static function getFileMimeType($filePath)
    {
        $extName = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!empty($extName) && !empty($_SESSION['MIME_TYPE_LIST'][$extName])) {
            return $_SESSION['MIME_TYPE_LIST'][$extName];
        }
        $mimeType = mime_content_type($filePath);
        if (empty($mimeType)) {
            $mimeType = self::MIME_TYPE_UNKNOW;
        }
        if (!empty($extName) && !in_array($mimeType, ['inode/x-empty',self::MIME_TYPE_UNKNOW])) {
            $_SESSION['MIME_TYPE_LIST'][$extName] = $mimeType;
        }
        return $mimeType;
    }
}
