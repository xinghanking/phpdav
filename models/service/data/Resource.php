<?php
/**
 * @name Service_Data_Resource
 * @desc Resource data service, 提供资源基本信息，属性查询的数据接口
 * @author 刘重量(13439694341@qq.com)
 */
class Service_Data_Resource
{
    const BOOL_YES = 1;    // 是
    const BOOL_NOT = 0;    // 否

    const STATUS_NORMAL = 0;    // 获取资源基本属性成功
    const STATUS_DELETE = 1;    // 资源已被删除
    const STATUS_FAILED = 2;    // 获取资源失败

    public $status = self::STATUS_NORMAL;    // 获取资源信息的情况
    public $id;                              // 资源(文件或目录)id
    public $creation_date;                   // 资源的创建时间
    public $level_no;                        // 资源位于的层级
    public $path;                            // 资源存储路径
    public $is_collection;                   // 是否一个集合（文件夹）
    public $content_type;                    // 资源的mime类型
    public $content_length;                  // 资源的大小
    public $last_modified;                   // 资源最后的修改时间戳
    public $etag;                            // 检测资源是否发生过改变的标识
    public $upper_id;                        // 资源上级(所属目录) 的id
    public $locked_info;                     // 资源的加锁信息
    public $opaquelocktoken = '';            // 加锁token(如果有的话)
    public $properties = [];                 // 属性集合数组
    public $children   = [];                 // 包含的下级资源实例集合数组

    private static $objDaoDavResource;       // Dao_DavResource  类实例
    private static $objDaoResourceProp;      // Dao_ResourceProp 类实例

    private static $arrInstances = [];       // 存储的资源实例集合

    /**
     * Service_Data_Resource constructor.
     * @param array $info
     * @throws Exception
     */
    private function __construct(array $info)
    {
        self::$objDaoDavResource  = Dao_DavResource::getInstance();
        self::$objDaoResourceProp = Dao_ResourceProp::getInstance();
        foreach ($info as $k => $v) {
            $this->$k = $v;
        }
        if (!empty($info['locked_info'])) {
            $this->locked_info = json_decode($info['locked_info'], true);
        }
    }

    /**
     * 魔术方法，可以直接调用一个资源属性对象中的函数
     * @param string $name
     * @param array  $arguments
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        return call_user_func_array([self::$objDaoResourceProp, $name], $arguments);
    }

    /**
     * 魔术方法，访问一个资源实例的对外只读属性值
     * @param  string $name 属性名
     * @return mixed
     * @throws Exception
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }
        $value = $this->getPropValue($name);
        return $value;
    }

    /**
     * 获取一个资源实例
     * @param  string $resourcePath
     * @return mixed
     * @throws Exception
     */
    public static function getInstance($resourcePath = REQUEST_RESOURCE)
    {
        if (empty(self::$arrInstances[$resourcePath]) || self::$arrInstances[$resourcePath] instanceof self) {
            self::$objDaoDavResource = Dao_DavResource::getInstance();
            $info = self::$objDaoDavResource->getResourceConf($resourcePath);
            if (empty($info)) {
                $info = ['status' => $info === null ? self::STATUS_DELETE : self::STATUS_FAILED];
            } else {
                $info['status'] = self::STATUS_NORMAL;
                $info['is_collection'] = $info['content_type'] == Dao_ResourceProp::MIME_TYPE_DIR;
            }
            $info['path'] = $resourcePath;
            self::$arrInstances[$resourcePath] = new self($info);
        }
        return self::$arrInstances[$resourcePath];
    }

    /**
     * 获取下级资源基本信息
     * @return array|mixed
     * @throws Exception
     */
    public function getChildren()
    {
        if (!empty($this->children)) {
            return $this->children;
        }
        $arrResources = self::$objDaoDavResource->getChildrenConfById($this->id);
        foreach ($arrResources as $info) {
            self::$arrInstances[$info['path']] = self::getInstance($info['path']);
            $this->children[$info['path']] = &self::$arrInstances[$info['path']];
        }
        $this->updateChildrenCollect();
        return $this->children;
    }

    /**
     * 获取符合查找条件的资源属性字段（值、条目）
     * @param  array $fields   查找的属性字段
     * @param  array $prop     查找的属性
     * @return array
     */
    public function getPropFind(array $fields = ['ns_id', 'prop_name', 'prop_value'], array $prop = [])
    {
        $conditions = ['`resource_id`=' . $this->id];
        if (!empty($prop)) {
            foreach ($prop as $nsId => $propNames) {
                $prop[$nsId] = '`ns_id`=' . $nsId . " AND `prop_name` IN ('" . implode("','", $propNames) . "')";
            }
            if (count($prop) == 1) {
                $conditions[] = current($prop);
            } else {
                $conditions[] = '((' . implode(') OR (', $prop) . '))';
            }
        }
        $arrProp = self::$objDaoResourceProp->getProperties($fields, $conditions);
        return $arrProp;
    }

    /**
     * 获取集合类型的资源展示页
     * @return string
     * @throws \Exception
     */
    public function getCollectView(){
        $showPath = urldecode(REQUEST_HREF);
        $href  = REQUEST_HREF;
        $childrenList = $this->getChildren();
        $itemList = [];
        foreach ($childrenList as $item) {
            $itemList[] = [
                'href'         => HttpsDav_Server::href_encode($item->path),
                'name'         => basename($item->path),
                'content_type' => $item->content_type,
                'size'         => $item->content_length,
                'modified'     => date('Y-m-d H:i:s', $item->last_modified)
            ];
        }
        $collectViewHtml = include TEMPLATE_COLLECT_VIEW;
        return $collectViewHtml;
    }
    /**
     * 资源加锁
     * @param  array $appInfo 加锁申请信息
     * @return array
     */
    public function lock(array $applyInfo)
    {
        try {
            $lockToken = '';
            $lockedInfoList = self::$objDaoDavResource->getLockedInfo($this->path, $this->level_no);
            $applyInfo['lock_time'] = time();
            if (!empty($lockedInfoList['active'])) {
                if (empty($applyInfo['locktoken'])) {
                    return ['code' => 423, 'path' => $this->path];
                }
                foreach ($lockedInfoList as $id => $lockedInfo) {
                    if (!in_array($lockedInfo['locktoken'], $applyInfo['locktoken']) || ($applyInfo['lockscope'] != 'shared' && $applyInfo['owner'] != $lockedInfo['owner'])) {
                        return ['code' => 423, 'path' => $this->path];
                    }
                    if ($applyInfo['lockscope'] == 'shared') {
                        $applyInfo['owner'] = array_merge($applyInfo['owner'], $lockedInfo['owner']);
                    }
                    $lockToken = $lockedInfo['locktoken'];
                }
                sort($applyInfo['owner']);
            }
            $setLockList = [];
            $invalidLockResourceIds = [];
            if (!empty($lockedInfoList['invalid'])) {
                $invalidLockResourceIds = $lockedInfoList['invalid'];
            }
            if ($this->content_type == Dao_DavResource::TYPE_DIRECTOR && (!isset($applyInfo['depth']) || $applyInfo['depth'] != 0)) {
                $maxLevel = isset($applyInfo['depth']) && is_numeric($applyInfo['depth']) ? $this->level_no + $applyInfo['depth'] : 0;
                $lockedInfoList = self::$objDaoDavResource->getItemLockedInfos($this->path, $maxLevel);
                if (!empty($lockedInfoList['active'])) {
                    if (empty($applyInfo['locktoken'])) {
                        return ['code' => 423, 'path' => $this->path];
                    }
                    foreach ($lockedInfoList['active'] as $id => $lockedInfo) {
                        if (!in_array($lockedInfo['locktoken'], $applyInfo['locktoken']) || ($applyInfo['owner'] != $lockedInfo['owner'] && ($lockedInfo['lockscope'] != 'shared' || $applyInfo['lockscope'] != 'shared'))) {
                            return ['code' => 412, 'path' => $lockedInfo['path']];
                        }
                        if ($applyInfo['lockscope'] == 'shared') {
                            $lockedInfo['owner'] = array_merge($lockedInfo['owner'], $applyInfo['owner']);
                            sort($lockedInfo);
                        } else {
                            $lockedInfo = $applyInfo;
                        }
                        $lockToken = $lockedInfo['locktoken'];
                        $setLockList[$id] = $lockedInfo;
                    }
                }
                if (!empty($lockedInfoList['invalid'])) {
                    $invalidLockResourceIds = array_merge($invalidLockResourceIds, $lockedInfoList['invalid']);
                }
            }
            if (!empty($this->locked_info['locktoken'])) {
                $applyInfo['locktoken'] = $this->locked_info['locktoken'];
            } else {
                $applyInfo['locktoken'] = empty($lockToken) ? $this->createLockToken() : $lockToken;
            }
            $_SESSION['LOCK_TOKEN'][$this->id] = $applyInfo['locktoken'];
            $setLockList[$this->id] = $applyInfo;
            $res = self::$objDaoDavResource->setResourcesLockinfo($setLockList);
            if ($res) {
                $response = ['code' => 200, 'locked_info' => $applyInfo];
            }
            if (!empty($invalidLockResourceIds)) {
                self::$objDaoDavResource->freeResourcesLock($invalidLockResourceIds);
            }
        } catch (Exception $e) {
            HttpsDav_Log::error($e);
            if (empty($response)) {
                $response = ['code' => 503, 'path' => $this->path];
            }
        }
        return $response;
    }

    /**
     * 解锁资源
     * @param array $locktokenList
     * @return array
     */
    public function unlock(array $locktokenList)
    {
        $res = ['code' => 204];
        $this->checkLocked();
        if (empty($this->locked_info)) {
            return $res;
        }
        if (isset($this->locked_info['locktoken']) && !in_array($this->locked_info['locktoken'], $locktokenList)) {
            $res['code'] = 403;
            return $res;
        }
        $res = self::$objDaoDavResource->freeResourcesLock([$this->id]);
        return ['code' => $res ? 204 : 503];
    }

    /**
     * 删除资源
     * @return bool
     */
    public function remove()
    {
        try {
            $res = self::$objDaoDavResource->removePathRecord(REQUEST_PATH);
            if ($res) {
                $res = HttpsDav_PhyOperation::removePath(REQUEST_PATH);
            }
            return $res;
        } catch (Exception $e) {
            $log = 'fatal delete a dir resource :' . $this->path . '. File' . $e->getFile() . ':' . $e->getLine() . '; msg:' . $e->getMessage();
            HttpsDav_Log::debug($log);
            return false;
        }
    }

    /**
     * 检查资源是否被加锁
     * @return bool
     */
    public function checkLocked()
    {
        if (empty($this->locked_info)) {
            return false;
        }
        if (empty($this->locked_info['lock_time']) || empty($this->locked_info['timeout']) || time() - $this->locked_info['lock_time'] >= $this->locked_info['timeout']) {
            self::$objDaoDavResource->freeResourcesLock([$this->id]);
            $this->locked_info = [];
            return false;
        }
        return true;
    }

    /**
     * 返回资源是否在上次请求（同一服务器本次请求之前）后发生变化
     * @param  array $input
     * @return bool
     */
    public function hadChanged(array $input)
    {
        if (isset($input['etag'])) {
            foreach ($input['etag'] as $info) {
                $eTag = '"' . ($info['is_w'] ? strtoupper($this->etag) : $this->etag) . '"';
                if ($info == '[*]') {
                    return false;
                }
                if (!in_array($eTag, $info['list'])) {
                    return true;
                }
            }
        }
        if (isset($input['lastmodified']) && $this->last_modified > $input['lastmodified']) {
            return true;
        }
        return false;
    }

    /**
     * 对一个资源的属性进行变更操作
     * @param  array $propList
     * @return bool
     */
    public function propPatch(array $propList)
    {
        try {
            self::$objDaoResourceProp->beginTransaction();
            if (isset($propList['set'])) {
                foreach ($propList['set'] as $prop){
                    self::$objDaoResourceProp->setResourceProp($this->id, $prop['prop_name'], $prop['prop_value'], $prop['ns_id']);
                }
            }
            if (isset($propList['remove'])) {
                foreach ($propList['remove'] as $prop) {
                    self::$objDaoResourceProp->removeResourceProp($this->id, $prop['prop_name'], $prop['ns_id']);
                }
            }
            self::$objDaoResourceProp->commit();
            return true;
        } catch (Exception $e) {
            self::$objDaoResourceProp->rollback();
            return false;
        }
    }

    /**
     * 获取指定区间范围内的资源内容
     * @param int $start  读取资源内容的起始位置
     * @param int $length 获取资源内容的区间长度
     * @return false|string
     */
    public function getContent($start, $length){
        $content = file_get_contents($this->path, false, null, $start, $length);
        return $content;
    }

    public function putContent($dataContent, $contentType ='text/plain') {
        $size = file_put_contents($this->path, $dataContent);
        if ($size != strlen($dataContent)) {
            return false;
        }
    }

    /**
     * 将资源复制到指定地址
     * @param string $destination 目标地址
     * @return mixed
     */
    public function copy($destination)
    {
        $res = HttpsDav_PhyOperation::copyResource(REQUEST_PATH, $destination);
        if($res){
            self::$objDaoDavResource->copy(REQUEST_PATH, $destination);
        }
        return $res;
    }

    /**
     * 将资源移动到指定地址
     * @param string $destination
     * @return mixed
     * @throws Exception
     */
    public function move($destination)
    {
        $objDestResource = self::getInstance($destination);
        $response = ['code' => $objDestResource->status == Service_Data_Resource::STATUS_DELETE ? 201 : 204];
        $res = self::$objDaoDavResource->move($this->path, $destination, $this->content_type);
        if ($res) {
            return $response;
        }
        return ['code' => 502];
    }

    /**
     * 根据属性名和所属命名空间id获取属性值
     * @param  string $propName
     * @param  int    $nsId
     * @return array|null
     */
    public function getPropValue($propName, $nsId = NS_DAV_ID)
    {
        $propValue = $this->getPropFind(['prop_name', 'prop_value'], [$nsId => [$propName]]);
        $propValue = isset($propValue[0]['prop_value']) ? $propValue[0]['prop_value'] : null;
        return $propValue;
    }

    /**
     * 更新数据库中存储的下级资源集合信息
     * @throws Exception
     */
    private function updateChildrenCollect()
    {
        $arrResources = scandir($this->path);
        $arrResources = array_diff($arrResources, ['.', '..']);
        foreach ($arrResources as $k => $resourceName) {
            $arrResources[$k] = $this->path . DIRECTORY_SEPARATOR . $resourceName;
        }
        if(!empty($this->children)){
            $arrChildren = array_keys($this->children);
            $arrRemoveChildren = array_diff($arrChildren, $arrResources);
            if (!empty($arrRemoveChildren)) {
                self::$objDaoDavResource->deleteResourceByPath($arrRemoveChildren);
                foreach ($arrRemoveChildren as $resource) {
                    unset(self::$arrInstances[$resource]);
                    unset($this->children[$resource]);
                }
            }
            $arrResources = array_diff($arrResources, $arrChildren);
        }
        foreach ($arrResources as $k => $path) {
            self::getInstance($path);
            $this->children[$path] = &self::$arrInstances[$path];
        }
    }

    /**
     * 创建token
     * @return string
     */
    private function createLockToken(){
        $sessionId = session_id();
        $opaqueLockToken = $this->id . '-' . $sessionId . '-' . time();
        return $opaqueLockToken;
    }
}
