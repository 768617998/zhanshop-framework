<?php
// +----------------------------------------------------------------------
// | shuangcloud / BaseLockedTable.php    [ 2026/6/17 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace zhanshop\server\table;

use zhanshop\App;

/**
 * 带锁保护的共享内存表基类
 *
 * 同样使用类名注册表模式，子类无需重新声明任何属性。
 *
 * 锁策略：
 *  - 全局锁: initLocks(0) 或 initLocks(null) → 单把 SWOOLE_MUTEX
 *  - 分片锁: initLocks(n) → n 把锁，按 key hash 分配
 */
abstract class BaseLockedTable extends BaseTable
{
    /**
     * 锁注册表: [子类类名 => Lock|Lock[]]
     * @var array<string, \Swoole\Lock|\Swoole\Lock[]>
     */
    private static array $allLocks = [];

    /**
     * 分片数注册表: [子类类名 => int]
     * @var array<string, int>
     */
    private static array $allLockShards = [];

    /**
     * 超时注册表: [子类类名 => float]
     * @var array<string, float>
     */
    private static array $allLockTimeouts = [];

    /**
     * 初始化锁
     *
     * @param int|null $shardNum null 或 0 = 全局锁, >0 = 分片锁数量
     */
    protected static function initLocks(?int $shardNum = null, float $timeout = 3.0): void
    {
        $class = static::class;
        self::$allLockTimeouts[$class] = $timeout;

        if ($shardNum === null || $shardNum <= 0) {
            self::$allLocks[$class] = new \Swoole\Lock(SWOOLE_MUTEX);
            self::$allLockShards[$class] = 0;
            return;
        }

        self::$allLockShards[$class] = $shardNum;
        $locks = [];
        for ($i = 0; $i < $shardNum; $i++) {
            $locks[$i] = new \Swoole\Lock(SWOOLE_MUTEX);
        }
        self::$allLocks[$class] = $locks;
    }

    /**
     * 根据 key 获取对应的锁
     */
    protected static function getLock(int|string $key): \Swoole\Lock
    {
        $class = static::class;
        $shard = self::$allLockShards[$class] ?? 0;

        if ($shard <= 0) {
            return self::$allLocks[$class];
        }
        $index = (is_int($key) ? $key : crc32((string)$key)) % $shard;
        $index = abs($index);
        return self::$allLocks[$class][$index];
    }

    /**
     * 获取锁 带超时
     */
    protected static function acquireLock(\Swoole\Lock $lock): void
    {
        $timeout = self::$allLockTimeouts[static::class] ?? 3.0;
        $microtime = microtime(true);
        while (true) {
            if ($lock->lock(LOCK_EX, 0.001)) {
                return;
            }
            if ((microtime(true) - $microtime) >= $timeout) {
                App::error()->setError('共享内存锁获取失败,超时=' . $timeout . 's');
            }
            usleep(100000);
        }
    }

    /**
     * 释放锁
     */
    protected static function releaseLock(\Swoole\Lock $lock): void
    {
        $lock->unlock();
    }

    /**
     * 在锁保护下执行回调
     *
     * @param int|string $lockKey 分片键
     * @param callable $fn 受保护的回调
     * @return mixed
     */
    protected static function withLock(int|string $lockKey, callable $fn): mixed
    {
        $lock = static::getLock($lockKey);
        static::acquireLock($lock);
        try {
            return $fn();
        } finally {
            static::releaseLock($lock);
        }
    }
}
