<?php

/**
 * @name Dao_DavResource
 * @desc 对resources_info表的操作
 * @author 刘重量(13439694341@qq.com)
 */
class Dao_DavResource extends HttpsDav_Db
{
    const TYPE_COLLECTION = 1;    //资源类型为一个集合
    const TYPE_STANDALONE = 0;    //资源为单独一个文件
    const TYPE_DIRECTOR   = 'application/x-director';
    const TABLE = 'dav_resource';

    protected function init()
    {
        $this->_tbl = '`' . self::TABLE . '`';
    }

    /**
     * @param  string $path 资源地址
     * @return array|bool|null
     */
    public function getResourceConf($path)
    {
        try {
            $arrInfo = $this->getRow(['`id`', '`level_no`', '`locked_info`', '`path`', '`content_type`', '`content_length`', '`etag`', '`last_modified`', '`upper_id`'], ['`path`=' => $path]);
            if (false === file_exists($path)) {
                if (!empty($arrInfo['id'])) {
                    $this->deleteResourceByPath([$path]);
                }
                return null;
            }
            if (empty($arrInfo)) {
                $dirName = dirname($path);
                if ($dirName == $path) {
                    $arrInfo = [
                        'path'           => $path,
                        'level_no'       => 1,
                        'content_type'   => Dao_ResourceProp::MIME_TYPE_DIR,
                        'content_length' => disk_total_space($path),
                        'etag'           => '',
                        'upper_id'       => 0
                    ];
                } else {
                    $upperConf = $this->getResourceConf($dirName);
                    if (empty($upperConf)) {
                        return false;
                    }
                    $arrInfo = ['path' => $path, 'level_no'=>$upperConf['level_no']+1, 'upper_id' => $upperConf['id']];
                }
            }
            Dao_ResourceProp::getInstance()->upsertBaseProperties($arrInfo);
            return $arrInfo;
        } catch (Exception $e) {
            HttpsDav_Log::error($e, 'path=' . $path);
            return false;
        }
    }

    /**
     * 根据资源路径获取资源所继承和包含的加锁信息
     * @param  string $resourcePath 资源路径
     * @param  int    $levelNo      层级编号
     * @return array
     * @throws Exception
     */
    public function getLockedInfo($resourcePath, $levelNo)
    {
        $arrPaths = [$resourcePath];
        $currentPath = $resourcePath;
        $upperPath = dirname($resourcePath);
        while ($upperPath != $currentPath) {
            $arrPaths[] = $upperPath;
            $currentPath = $upperPath;
            $upperPath = dirname($currentPath);
        }
        $conditions = [
            'WHERE' => [
                '`path` IN' => $arrPaths,
                '`locked_info`!=' => '',
            ],
        ];
        $arrInfos = $this->select('`id`, `level_no`, `locked_info`', $conditions);
        if (empty($arrInfos) || !is_array($arrInfos)) {
            return [];
        }
        $lockedInfoList = ['active' => [], 'invalid' => []];
        foreach ($arrInfos as $info) {
            $lockedInfo = json_decode($info['locked_info'], true);
            if (is_array($lockedInfo) && !empty($lockedInfo['lock_time']) && !empty($lockedInfo['timeout'])) {
                if (time() - $lockedInfo['lock_time'] >= $lockedInfo['timeout']) {
                    $lockedInfoList['invalid'][] = $info['id'];
                } elseif (empty($lockedInfo['depth']) || (is_numeric($lockedInfo['depth']) && $levelNo - $info['level_no'] <= $lockedInfo['depth'])) {
                    $lockedInfoList['active'][$info['id']] = $lockedInfo;
                }
            }
        }
        return $lockedInfo;
    }

    /**
     * 根据资源路径获取资源所继承和包含的加锁信息
     * @param  string $resourcePath 资源路径
     * @param  int    $maxLevel     最大深度
     * @return array
     * @throws Exception
     */
    public function getItemLockedInfos($resourcePath, $maxLevel = 0)
    {
        $conditions = [
            'WHERE' => [
                '`path` LIKE' => $resourcePath . DIRECTORY_SEPARATOR . '%',
                '`locked_info`!=' => ''
            ]
        ];
        if ($maxLevel > 0) {
            $conditions['WHERE']['level_no <='] = $maxLevel;
        }
        $infosList = $this->select('`id`,`path`, `locked_info`', $conditions);
        if (empty($infoList) || !is_array($infoList)) {
            return [];
        }
        $lockedInfoList = ['active' => [], 'invalid' => []];
        foreach ($infosList as $info) {
            $lockedInfo = json_decode($info['locked_info'], true);
            if (is_array($lockedInfo) && isset($lockedInfo['lock_time']) && isset($lockedInfo['timeout'])) {
                if (time() - $lockedInfo['lock_time'] >= $lockedInfo['timeout']) {
                    $lockedInfoList['invalid'][] = $info['id'];
                } else {
                    $lockedInfo['path'] = $info['path'];
                    $lockedInfoList['active'][$info['id']] = $lockedInfo;
                }
            }
        }
        return $lockedInfoList;
    }

    /**
     * 批量设置资源的加锁信息
     * @param array $lockInfoList
     * @return bool
     * @throws Exception
     */
    public function setResourcesLockinfo(array $lockInfoList)
    {
        HttpsDav_Log::debug('setResourcesLockinfo: ' . print_r($lockInfoList, true));
        try {
            $objResourceProp = Dao_ResourceProp::getInstance();
            $this->beginTransaction();
            foreach ($lockInfoList as $resourceId => $lockInfo) {
                $value = json_encode($lockInfo, JSON_UNESCAPED_UNICODE);
                $this->update(['locked_info' => $value], ['`id`=' => $resourceId]);
                $ownerList = [];
                foreach ($lockInfo['owner'] as $owner) {
                    $ownerList[] = ['href', $owner];
                }
                $value = [[
                    'activelock',
                    [
                        ['lockscope', [[$lockInfo['lockscope']]]],
                        ['locktype',  [[$lockInfo['locktype']]]],
                        ['depth',     $lockInfo['depth']] ,
                        ['owner',     $ownerList],
                        ['timeout',   'Second-' . $lockInfo['timeout']],
                        ['locktoken', [['href', $lockInfo['locktoken']]]],
                    ],
                ]];
                $objResourceProp->setResourceProp($resourceId, 'lockdiscovery', $value);
            }
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
        return true;
    }

    /**
     * 批量释放资源锁
     * @param array $resourceIds
     * @return bool
     * @throws \Exception
     */
    public function freeResourcesLock(array $resourceIds)
    {
        try {
            $objResourceProp = Dao_ResourceProp::getInstance();
            $this->beginTransaction();
            $this->update(['locked_info' => ''], ['`id` IN' => $resourceIds]);
            $conditions = [
                '`prop_name`=' => 'lockdiscovery',
                '`ns_id`=' => NS_DAV_ID,
                '`resource_id` IN' => $resourceIds
            ];
            $objResourceProp->update(['prop_value' => '[null]'], $conditions);
            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
    }

    /**
     * 在数据库中创建一条资源记录
     * @param $info
     * @return int
     * @throws \Exception
     */
    public function createResource($info){
        if(!isset($info['upper_id'])){
            $upperPath = dirname($info['path']);
            $upperInfo = $this->getResourceConf($upperPath);
            if(empty($upperInfo)) {
                if($upperPath == $info['path']){
                    $info['upper_id'] = 0;
                } else {
                    $upperInfo = ['path' => $upperPath, 'content_type' => Dao_ResourceProp::MIME_TYPE_DIR, 'content_length' => 0];
                    $info['upper_id'] = $this->createResource($upperInfo);
                }
            } else {
                $info['upper_id'] = $upperInfo['id'];
            }
        }
        $id = $this->insert($info);
        return $id;
    }

    /**
     * 根据路径删除存储的资源信息（包含所有下级子资源）
     * @param array $pathList
     * @return bool
     * @throws \Exception
     */
    public function deleteResourceByPath(array $pathList)
    {
        try {
            if(count($pathList) == 1) {
                $path = current($pathList);
                $res  = true;
                if (is_file($path)) {
                    $res = unlink($path);
                } elseif (is_dir($path)) {
                    exec('rm -fr ' . $path, $msg, $status);
                    $res = 0 === $status;
                }
                if (false === $res) {
                    throw new Exception('faltal delete resource', 403);
                }
                return true;
            }
            $resourceList = $pathList;
            $this->beginTransaction();
            foreach ($pathList as $k => $path) {
                $pathList[$k] = "`path`='" . $path . "' OR `path` LIKE '" . $path . "/%'";
            }
            $strWhere = implode(' OR ', $pathList);
            $conditions = ['`resource_id` IN (SELECT `id` FROM ' . $this->_tbl . ' WHERE ' . $strWhere . ')'];
            Dao_ResourceProp::getInstance()->delete($conditions);
            $this->delete([$strWhere]);
            foreach ($resourceList as $path) {
                $res = true;
                if (is_file($path)) {
                    $res = unlink($path);
                } elseif (is_dir($path)) {
                    exec('rm -fr ' . $path, $msg, $status);
                    $res = 0 === $status;
                }
                if (false === $res) {
                    throw new Exception('faltal delete resource', 403);
                }
            }
            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollback();
            HttpsDav_Log::error($e);
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 根据路径信息删除符合条件的路径记录
     * @param  array|string $pathInfo
     * @return bool
     * @throws Exception
     */
    public function removePathRecord($pathInfo){
        $pathList = is_string($pathInfo) ? [$pathInfo] : $pathInfo;
        try{
            $this->beginTransaction();
            foreach($pathList as $resourcePath){
                if (substr($resourcePath, -1) == '*') {
                    $strWhere = "`path` LIKE '" . substr($resourcePath, 0, -1) . "%'";
                } else {
                    $strWhere = "`path` ='" . $resourcePath . "' OR `path` LIKE '" . $resourcePath . "/%'";
                }
                $this->delete([$strWhere]);
                $conditions = ['`resource_id` IN (SELECT `id` FROM ' . $this->_tbl . ' WHERE ' . $strWhere . ')'];
                Dao_ResourceProp::getInstance()->delete($conditions);
            }
            $this->commit();
        }catch (Exception $e) {
            $this->rollback();
            HttpsDav_Log::error($e);
            return false;
        }
        return true;
    }

    /**
     * 将资源复制到指定地址
     * @param string $sourcepath 资源
     * @param string $destination 目标地址
     * @return bool
     * @throws Exception
     */
    public function copyRecord($sourcepath, $destination)
    {
        if (substr($sourcepath, -1) == '*') {
            $strWheres = "`path` LIKE '" . rtrim($sourcepath, '*') . "%'";
            $sourcepath = dirname($sourcepath);
        } else {
            $sourcepath = rtrim($sourcepath, '/');
            $strWheres = "`path`='" . $sourcepath . "' OR `path` LIKE '" . $sourcepath . "/%'";
        }
        $destPathConf = $this->getResourceConf($destination);
        if(empty($destPathConf)){
            $upperPath = dirname($destination);
            $upperInfo = $this->getResourceConf($upperPath);
            if(empty($upperInfo)){
                $Id = $this->createResource(['path' => $upperPath, 'content_type'=>Dao_ResourceProp::MIME_TYPE_DIR]);
                $pathIds = [$upperPath => $Id];
            } else {
                $pathIds = [$upperInfo['path'] => $upperInfo['id']];
            }
        } else {
            if($destPathConf['content_type'] == Dao_ResourceProp::MIME_TYPE_DIR){
                $pathIds = [$destPathConf['path'] => $destPathConf['id']];
                $destination .= DIRECTORY_SEPARATOR . basename($sourcepath);
            } else {
                $pathIds = [dirname($destPathConf['path']) => $destPathConf['upper_id']];
            }
        }
        $depthPos = count(explode(DIRECTORY_SEPARATOR, $destination)) - count(explode(DIRECTORY_SEPARATOR, $sourcepath));
        $pathInfoList = $this->select('`id`, `path`, `level_no`, `content_type`, `content_length`', ['WHERE' => $strWheres, 'ORDER BY' => '`level_no` ASC']);
        $objDaoProp = Dao_ResourceProp::getInstance();
        foreach($pathInfoList as $info) {
            $path = $destination . substr($info['path'], strlen($sourcepath));
            $dest = [
                'path' => $path,
                'upper_id' => $pathIds[dirname($path)],
                'level_no' => $info['level_no'] + $depthPos,
                'content_type' => $info['content_type'],
                'content_length' => $info['content_length'],
            ];
            $destId = $this->replace($dest);
            $objDaoProp->copyProp($info['id'], $destId);
        }
        return true;
    }

    /**
     * 将资源移动到指定地址
     * @param string $sourceResource 资源源地址
     * @param string $destination 目标地址
     * @return bool
     * @throws Exception
     */
    public function move($sourceResource, $destination, $contentType = '')
    {
        try{
            $this->beginTransaction();
            $destPathConf = $this->getResourceConf($destination);
            if (empty($destPathConf)) {
                $upperPath = dirname($destination);
                $upperInfo = $this->getResourceConf($upperPath);
                if (empty($upperInfo)) {
                    $upperId = $this->createResource(['path' => $upperPath, 'content_type' => Dao_ResourceProp::MIME_TYPE_DIR]);
                } else {
                    $upperId = $upperInfo['id'];
                }
            } else {
                $upperId = $destPathConf['upper_id'];
                $this->delete(['id=' . $destPathConf['id']]);
            }
            $this->update(['path' => $destination, 'upper_id' => $upperId], ['`path`=' => $sourceResource]);
            if($contentType == Dao_ResourceProp::MIME_TYPE_DIR){
                $pathInfoList = $this->select('`id`, `path`', ['WHERE' => "`path` LIKE '" . $sourceResource . DIRECTORY_SEPARATOR . "%'"]);
                foreach($pathInfoList as $info){
                    $path = $destination . DIRECTORY_SEPARATOR . substr($info['path'], strlen($sourceResource));
                    $this->update(['path' => $path], ['id=' . $info['id']]);
                }
            }
            $res = HttpsDav_PhyOperation::move($sourceResource, $destination);
            if ($res) {
                $this->commit();
                return true;
            }
        } catch (Exception $e) {
            HttpsDav_Log::debug($e);
        }
        $this->rollback();
        return false;
    }

    /**
     * 根据上级 id 获取所以下级资源基本信息数组
     * @param  int $id 上级id
     * @return array
     */
    public function getChildrenConfById($id)
    {
        $fields = ['id', 'path', 'content_type', 'content_length', 'etag', 'last_modified', 'upper_id'];
        $condtions = ['`upper_id`=' . $id];
        $arrResources = $this->select($fields, $condtions);
        return $arrResources;
    }
}
