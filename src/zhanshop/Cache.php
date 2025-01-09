<?php
// +----------------------------------------------------------------------
// | flow-course / Database.php    [ 2021/10/25 9:35 上午 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2021 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);


namespace zhanshop;

use zhanshop\cache\CacheManager;
use zhanshop\cache\RedisConnectionPool;

/**
 * @mixin \Redis
 */
class Cache{

    protected $connection;

    public function __construct(){
        $this->connection = App::config()->get('cache.default');
    }
    /**
     * 实例
     * @param string|null $connection 连接标识
     * @param mixed|null $redis 已确定的redis
     * @return RedisConnectionPool
     * @throws \Exception
     */
    public function instance(mixed $connection = null){
        if(gettype($connection) == 'object') return $connection;

        if($connection == false) $connection = App::config()->get('cache.default');
        $redisConnectionPool = App::make(CacheManager::class)->get($connection);
        return $redisConnectionPool;
    }

    public function callback($callback, string $connection = ""){
        if($connection == false) $connection = App::config()->get('cache.default');
        $redisConnectionPool = App::make(CacheManager::class)->get($connection);
        return $redisConnectionPool->callback($callback);
    }

    public function __call(string $name, array $arguments){
        return $this->instance($this->connection)->$name(...$arguments);
    }
}