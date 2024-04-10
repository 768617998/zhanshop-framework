<?php
// +----------------------------------------------------------------------
// | zhanshop-php / Task.php    [ 2023/2/2 11:08 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

abstract class Task
{
    protected \Swoole\Server\Task $task;
    public function __construct(\Swoole\Server\Task $task)
    {
        $this->task = $task;
    }
    /**
     * 任务启动
     * @return mixed
     */
    public function onStart(){}

    /**
     * 任务运行
     * @return mixed
     */
    abstract public function execute();

    /**
     * 任务结束
     * @return mixed
     */
    public function onEnd(){}
}