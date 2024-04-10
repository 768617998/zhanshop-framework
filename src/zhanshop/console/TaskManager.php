<?php
// +----------------------------------------------------------------------
// | zhanshop-device / TaskManager.php    [ 2024/1/19 下午5:13 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console;

class TaskManager
{
    /**
     * server对象
     * @var Swoole\Server
     */
    protected $server;
    public function __construct(mixed &$server)
    {
        $this->server = $server;
    }
    /**
     * 获取server对象
     * @return \Swoole\Server
     */
    public function getServer(){
        return $this->server;
    }
    /**
     * 投递task任务
     * @param string $class
     * @param ...$value
     * @return void
     */
    public function callback(string $class, ...$value){
        $this->server->task([
            'class' => $class,
            'value' => $value
        ]);
    }
}