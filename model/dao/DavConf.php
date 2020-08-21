<?php

/**
 * @name Dao_DavConf
 * @desc 对dav_conf表的操作
 * @author 刘重量(13439694341@qq.com)
 */
class Dao_DavConf extends Dav_Db
{
    const TABLE = 'dav_conf';

    protected function init()
    {
        $this->_tbl = '`' . self::TABLE . '`';
    }

    /**
     * @param string $http_host
     * @param string $path
     * @param bool $reset
     * @return int
     * @throws Exception
     */
    public static function setDavRoot($http_host, $path, $reset = false)
    {

        $resourceInfo = Dao_DavResource::getInstance()->getResourceConf($path);
        if (empty($resourceInfo['id'])) {
            throw new Exception('fatal error', 0);
        }
        $row = ['http_host' => $http_host, 'resource_id' => $resourceInfo['id']];
        if ($reset) {
            return self::getInstance()->replace($row);
        } else {
            return self::getInstance()->insert($row);
        }
    }

    /**
     * @param string $http_host
     * @return mixed
     * @throws Exception
     */
    public static function getDavRoot($http_host)
    {
        $conditions = [
            'FROM'  => '`' . self::TABLE . '` AS `d`, `' . Dao_DavResource::TABLE . '` AS `r`',
            'WHERE' => ['`d`.`resource_id`=`r`.`id`', '`d`.`http_host`= ' => $http_host],
        ];
        $davRoot = self::getInstance()->getColumn('`r`.`path`', $conditions);
        return $davRoot;
    }
}
