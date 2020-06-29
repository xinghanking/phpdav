<?php

/**
 * @name Dao_PropNs
 * @desc propNs dao, 可以访问数据库prop_ns表的内容
 * @author 刘重量(13439694341@qq.com)
 */
class Dao_PropNs extends Dav_Db
{
    const TABLE = 'prop_ns';
    const LIMIT = 256;
    public static $uriMap = [];
    public static $nsList = [];
    private $prefixDict = [
        'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y',
        'Z', 'A', 'B', 'C', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r',
        's', 't', 'u', 'v', 'w', 'x', 'y', 'z'
    ];

    /**
     * 初始化资源属性命名空间类
     */
    protected function init()
    {
        $this->_tbl = '`' . self::TABLE . '`';
        $arrRes = $this->select(['`id`', '`uri`', '`prefix`'], ['LIMIT' => self::LIMIT]);
        if (is_array($arrRes)) {
            foreach ($arrRes as $ns) {
                self::$nsList[$ns['id']] = ['prefix' => $ns['prefix'], 'uri' => $ns['uri']];
                self::$uriMap[$ns['uri']] = $ns['id'];
            }
        }
    }

    /**
     * 根据uri获取命名空间id
     * @param string $uri 命名空间uri
     * @return int|mixed
     * @throws Exception
     */
    public function getNsIdByUri($uri)
    {
        if (isset(self::$uriMap[$uri])) {
            return self::$uriMap[$uri];
        }
        $maxId = max(self::$uriMap);
        if ($maxId >= self::LIMIT) {
            $id = $this->getColumn('`id`', ['`uri`=' => $uri]);
            if (is_numeric($id)) {
                self::$uriMap[$uri] = $id;
                return $id;
            }
        }
        $info = ['uri' => $uri, 'user_agent' => Dav_Request::$_Headers['User-Agent']];
        try {
            $this->beginTransaction();
            $id = $this->replace($info);
            if (isset($this->prefixDict[$id])) {
                $prefix = $this->prefixDict[$id];
            } else {
                $num = count($this->prefixDict);
                $prefix = $this->prefixDict[$id % $num - 1] . floor($id / $num);
            }
            $this->update(['`prefix`' => $prefix], ['`uri`=' => $uri]);
            $this->commit();
            self::$uriMap[$uri] = $id;
            return $id;
        } catch (Exception $e) {
            $this->rollback();
            Dav_Log::error($e);
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 根据id查询命名空间信息
     * @param int $id 命名空间id
     * @return array|mixed
     * @throws Exception
     */
    public function getNsInfoById($id)
    {
        if (isset(self::$nsList[$id])) {
            return self::$nsList[$id];
        }
        $info = $this->getRow(['`prefix`', '`uri`'], ['`id`=' => $id]);
        if (empty($info)) {
            throw new Exception('有命名空间id查不到对应的信息，可能是prop_ns表中有数据丢失，id = ' . $id);
        }
        self::$nsList[$id] = $info;
        self::$uriMap[$info['uri']] = $id;
        return $info;
    }
}
