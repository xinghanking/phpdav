<?php

/**
 * Class Handler_Lock
 */
class Handler_Lock extends Dav_BaseHander
{
    const BODY_ROOT = 'lockinfo';

    protected $arrInput = [
        'timeout'   => 3600,
        'depth'     => 'Infinite',
        'lockscope' => 'exclusive',
        'locktype'  => 'write',
        'owner'     => [],
    ];

    /**
     * 执行客户端通过LOCK方法发来的对请求资源进行加锁的任务，并返回数组格式化的执行结果
     * @return array
     * @throws Exception
     */
    protected function handler()
    {
        $objResource = Dav_Resource::getInstance(REQUEST_RESOURCE);
        if (empty($objResource) || $objResource->status == Dav_Resource::STATUS_FAILED) {
            return ['code' => 503];
        }
        if ($objResource->status == Dav_Resource::STATUS_DELETE) {
            return ['code' => 404];
        }
        $arrResult = $objResource->lock($this->arrInput);
        if ($arrResult['code'] == 200) {
            $lockedInfo = $arrResult['locked_info'];
            $owner = [];
            foreach ($lockedInfo['owner'] as $user) {
                $owner[] = ['href', $user];
            }
            $lockedInfo['owner'] = $owner;
            $data = [
                'prop', [[
                    'lockdiscovery', [[
                        'activelock', [
                            ['locktype', [[$lockedInfo['locktype']]]],
                            ['lockscope', [[$lockedInfo['lockscope']]]],
                            ['depth', $lockedInfo['depth']],
                            ['owner', $lockedInfo['owner']],
                            ['timeout', 'Second-' . $lockedInfo['timeout']],
                            ['locktoken', [['href', $lockedInfo['locktoken']]]],
                        ],
                    ]],
                ]],
            ];
            return ['code' => 200, 'body' => $data];
        }
        if (!empty($arrResult['path']) && $arrResult['path'] != $objResource->path) {
            $data = [
                'multistatus', [
                    [
                        'response', [
                            ['href', Dav_Server::href_encode($arrResult['path'])],
                            ['status', Dav_Status::$Msg[$arrResult['code']]],
                         ]
                    ],
                    [
                        'response', [[
                            'propstat', [
                                ['prop', [['lockdiscovery']]],
                                ['status', Dav_Status::$Msg[424]]
                            ]
                        ]]
                    ]
                ]
            ];
            return ['code' => 207, 'body' => $data];
        }
        return ['code' => isset($arrResult['code']) ? $arrResult['code'] : 503];
    }

    /**
     * 获取并数组格式化的客户端发来的请求数据
     * @throws Exception
     */
    protected function getArrInput()
    {
        $lockInfo = Dav_Request::getObjElements(self::BODY_ROOT);
        if (empty($lockInfo)) {
            throw new Exception(Dav_Status::$Msg[422], 422);
        }
        $timeout = empty(Dav_Request::$_Headers['Timeout']) ? 'Infinite, Second-3600' : Dav_Request::$_Headers['Timeout'];
        $timeout = explode('-', $timeout);
        $this->arrInput['timeout'] = is_numeric($timeout[1]) ? intval($timeout[1]) : '3600';
        $timeout = explode(',', $timeout[0]);
        $this->arrInput['depth'] = is_numeric($timeout[0]) ? intval($timeout[0]) : strtolower('Infinite');
        $this->arrInput['locktoken'] = Dav_Request::getLockToken();
        $lockscopeInfo = Dav_Request::getObjElements(self::BODY_ROOT . '/lockscope');
        if (0 == $lockscopeInfo->length) {
            throw new Exception(Dav_Status::$Msg[422], 422);
        }
        $this->arrInput['lockscope'] = $lockscopeInfo->item(0)->childNodes->item(0)->localName;
        $locktypeInfo = Dav_Request::getObjElements(self::BODY_ROOT . '/locktype');
        if (0 == $locktypeInfo->length) {
            throw new Exception(Dav_Status::$Msg[422], 422);
        }
        $this->arrInput['locktype'] = $locktypeInfo->item(0)->childNodes->item(0)->localName;
        $this->arrInput['owner'] = Dav_Request::getElementList(self::BODY_ROOT . '/owner/href');
        foreach($this->arrInput['owner'] as $k => $v){
            $this->arrInput['owner'][$k] = strtr($v, '\\', '/');
        }
    }
}