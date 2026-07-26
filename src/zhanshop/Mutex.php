<?php

namespace zhanshop;

class Mutex
{
    /**
     * @var \Swoole\Coroutine\Lock
     */
    protected static $lock = null;

    public static function init()
    {
        if (self::$lock == null) self::$lock = new \Swoole\Coroutine\Lock();
    }

    /**
     * 加锁（协程锁不支持原生超时，通过 LOCK_NB 非阻塞轮询实现）
     * @param float $timeout 超时秒数，-1表示永久等待
     * @return bool
     */
    public static function lock(float $timeout = 0.01): bool
    {
        if ($timeout < 0) {
            return self::$lock->lock();
        }
        $startTime = microtime(true);
        while (true) {
            if (self::$lock->lock(LOCK_NB)) {
                return true;
            }
            if ((microtime(true) - $startTime) >= $timeout) {
                return false;
            }
            \Swoole\Coroutine\System::sleep(0.001);
        }
    }

    /**
     * 释放锁
     * @return bool
     */
    public static function unlock(): bool
    {
        return self::$lock->unlock();
    }

}