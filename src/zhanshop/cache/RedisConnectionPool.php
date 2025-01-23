<?php
// +----------------------------------------------------------------------
// | zhanshop-php / PDOConnectionPool.php    [ 2023/1/31 17:19 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\cache;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;
use zhanshop\Helper;

/**
 * @mixin \Redis
 */

class RedisConnectionPool
{
    protected $pool;

    protected $timeoutPool;

    protected $config;

    protected $maxConnections = 20;

    public function __construct(array $config){
        $this->config = $config;
        $this->maxConnections = $config['pool']['max_connections'] ?? 20;
        $this->timeoutPool = $config['pool']['timeout'] ?? 0.1;


        $hostname = Helper::gethostbyname($this->config['host']);
        if (filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hostname = '['.$hostname.']';
        }

        $this->pool = new RedisPool((new RedisConfig)
            ->withHost($hostname)
            ->withPort((int)$this->config['port'])
            ->withAuth($this->config['password'] ?? '')
            ->withDbIndex((int)$this->config['select'] ?? 0)
            ->withTimeout((float)$this->config['timeout'] ?? 1)
            ,$this->maxConnections
        );
    }

    public function callback($call){
        $redis = $this->getRedis();
        try {
            $call($redis);
            $this->recoveryRedis($redis);
        }catch (\Throwable $e){
            $this->recoveryRedis($redis);
            if(strpos($e->getMessage(), 'away')){
                try{
                    $this->reconnect();
                    $redis = $this->getRedis();
                    $call($redis);
                    $this->recoveryRedis($redis);
                }catch (\Throwable $err){}
            }
            throw new \Exception($e->getMessage().' '.$e->getFile().':'.$e->getLine(), $e->getCode());
        }
    }

    /**
     * 获取通道上的pdo
     * @return \Redis
     */
    protected function getRedis(){
        $redis = $this->pool->get($this->timeoutPool);
        if($redis == false){
            throw new \Exception('cache连接池耗尽【服务暂时不可用】', 503);
        }
        return $redis;
    }

    /**
     * 回收pdo至通道
     * @param $pdo
     * @return void
     */
    protected function recoveryRedis($redis){
        $this->pool->put($redis);
    }

    public function __call(string $name, array $arguments){
        $redis = $this->getRedis();
        try {
            $data = $redis->$name(...$arguments);
            $this->recoveryRedis($redis);
            return $data;
        }catch (\Throwable $e){
            $this->recoveryRedis($redis);
            if(strpos($e->getMessage(), 'away') || strpos($e->getMessage(), 'error')){
                try{
                    $this->reconnect();
                    $redis = $this->getRedis();
                    $data = $redis->$name(...$arguments);
                    $this->recoveryRedis($redis);
                    return $data;
                }catch (\Throwable $err){}
            }
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 重连
     * @return void
     */
    public function reconnect(){
        $this->pool->close();
        $this->pool = new RedisPool((new RedisConfig)
            ->withHost($this->config['host'])
            ->withPort((int)$this->config['port'])
            ->withAuth($this->config['password'] ?? '')
            ->withDbIndex((int)$this->config['select'] ?? 0)
            ->withTimeout((float)$this->config['timeout'] ?? 1)
            ,$this->maxConnections
        );
    }

    public function __destruct()
    {
        $this->pool->close();
    }
}