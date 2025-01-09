<?php

namespace zhanshop;

class Mutex
{
    /**
     * @var \Swoole\Lock
     */
    protected static $lock = null;
    public static function init()
    {
        if(self::$lock == null) self::$lock = new \Swoole\Lock(SWOOLE_MUTEX);
    }

    /**
     * 加锁
     * @param float $timeout
     * @return bool
     */
    public static function lock(float $timeout = 0.02)
    {
        return self::$lock->lockwait($timeout);
    }

    /**
     * 释放锁
     * @return bool
     */
    public static function unlock(){
        return self::$lock->unlock();
    }

}