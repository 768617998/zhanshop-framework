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
use zhanshop\App;
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
            if($this->isLostConnection($e)){
                try{
                    $this->reconnect();
                    $redis = $this->getRedis();
                    try {
                        $call($redis);
                    } finally {
                        // 无论重试成败都回收，防止重试二次失败导致连接泄漏
                        $this->recoveryRedis($redis);
                    }
                    return;
                }catch (\Throwable $err){
                    App::log()->error('Redis callback retry failed: '.$err->getMessage().' '.$err->getFile().':'.$err->getLine());
                }
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
            // 仅连接断开类错误才重试，避免业务逻辑错误触发误重连
            if($this->isLostConnection($e)){
                try{
                    $this->reconnect();
                    $redis = $this->getRedis();
                    try {
                        $data = $redis->$name(...$arguments);
                    } finally {
                        // 无论重试成败都回收，防止重试二次失败导致连接泄漏
                        $this->recoveryRedis($redis);
                    }
                    return $data;
                }catch (\Throwable $err){
                    App::log()->error('Redis retry failed: '.$err->getMessage().' '.$err->getFile().':'.$err->getLine());
                }
            }
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 重连
     * @return void
     */
    public function reconnect(){
        try{
            $this->pool->close();
        }catch (\Throwable $e){
            // 并发重连时旧池可能已被其他协程关闭，忽略 close 失败
        }

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

    /**
     * 判断是否为连接断开类错误（仅匹配确定性断连特征，避免误判业务错误触发无谓重连）
     * went away / Connection lost / Connection refused / read error on connection(恢复瞬间)
     * @param \Throwable $e
     * @return bool
     */
    protected function isLostConnection(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        return strpos($msg, 'went away') !== false
            || strpos($msg, 'Connection lost') !== false
            || strpos($msg, 'Connection refused') !== false
            || strpos($msg, 'read error on connection') !== false
            || strpos($msg, 'reset by peer') !== false
            || strpos($msg, 'Broken pipe') !== false;
    }

    public function __destruct()
    {
        $this->pool->close();
    }
}
