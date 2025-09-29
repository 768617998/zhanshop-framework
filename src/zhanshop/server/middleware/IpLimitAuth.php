<?php

namespace zhanshop\util;

use zhanshop\App;

class Limit
{
    protected $prefix = 'limit:';

    /**
     * 验证是否被限流
     * @param string $key
     * @param int $expireTime
     * @param $limit
     * @return bool
     */
    public function isPass(string $key, int $expireTime = 300, $limit = 10)
    {
        if($this->incr($key, $expireTime) > $limit){
            return false;
        }
        return true;
    }

    /**
     * 记录原子操作
     * @param string $key
     * @param int $expireTime
     * @param int $step
     * @return false|int|\Redis
     */
    public function incr(string $key, int $expireTime = 300, int $step = 1)
    {
        $key = $this->prefix.$key;
        App::cache()->set($key, 0, ['nx', 'ex' => $expireTime]);
        return App::cache()->incr($key, $step); // 递增
    }
}