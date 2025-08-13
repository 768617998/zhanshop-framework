<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / WorkerClient.php    [ 2024/11/10 18:11 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client;

use zhanshop\console\command\Server;

class WorkerClient
{
    protected $class = null;
    protected $param = null;
    protected $toWorkerId = -1;
    /**
     * 构建连接的rpc类
     * @template Type
     * @param class-string<Type> $className
     * @return Type
     */
    public function make(string $className, ...$value)
    {
        $this->class = $className;
        $this->param = $value;
        return $this;
    }

    /**
     * 设置目前进程
     * @param int $workerId
     * @return $this
     */
    public function setWorkerId(int $workerId)
    {
        $this->toWorkerId = $workerId;
        return $this;
    }
    /**
     * 容器获取app类
     * @param $name
     * @param array $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call(string $name, array $arguments)
    {
        return Server::sendWorkerMessage($this->toWorkerId, [$this->class, $name], ...$arguments);
    }
}