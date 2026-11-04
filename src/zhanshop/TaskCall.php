<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / Task.php    [ 2024/8/18 20:49 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

use zhanshop\console\command\Server;

class TaskCall
{
    protected $class = null;
    /**
     * 构建连接的task类
     * @template Type
     * @param class-string<Type> $className
     * @return Type
     */
    public function make(string $className, ...$value)
    {
        $this->class = $className;
        return $this;
    }

    public function __call(string $name, array $arguments)
    {
        if(Server::$server){
            Server::$server->task([[$this->class, $name], $arguments]);
        }
    }
}