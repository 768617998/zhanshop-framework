<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / PoolClient.php    [ 2026/9/28 15:26 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client;

use zhanshop\App;
use zhanshop\console\command\Pool;

class PoolClient
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
        $socket = App::make(Pool::class)->getSocket();
        if(file_exists($socket)){
            $client = new Httpclient();
            $client->setHeader("Content-Type", "application/json");
            $result = $client->unix($socket, "/", "POST", json_encode([
                [$this->class, $name],
                $arguments
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));
            if($result['code'] != 200){
                App::error()->setError(json_encode($result['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE), 500);
            }
            $body = json_decode($result['body'], true);
            if($body) return $body;
            return $result['body'];
        }
        App::error()->setError("进程池未启动", 503);
    }
}