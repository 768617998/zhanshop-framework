<?php
// +----------------------------------------------------------------------
// | shuangcloud / BaseTable.php    [ 2026/6/17 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace zhanshop\server\table;

/**
 * 共享内存表基类
 *
 * 采用类名注册表模式：子类无需重新声明任何静态属性，
 * 在 init() 中调用 registerTable() 即可，基类通过 static::class 自动定位。
 */
abstract class BaseTable
{
    /**
     * 表注册表: [子类类名 => Swoole\Table]
     * @var array<string, \Swoole\Table>
     */
    private static array $tables = [];

    /**
     * 注册当前子类的 Swoole\Table 实例
     */
    protected static function registerTable(\Swoole\Table $table): void
    {
        self::$tables[static::class] = $table;
    }

    /**
     * 获取当前子类的 Swoole\Table 实例
     */
    protected static function table(): \Swoole\Table
    {
        return self::$tables[static::class];
    }

    /**
     * 获取一行数据
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $data = static::table()->get($key);
        return $data ?? $default;
    }

    /**
     * 写入一行数据
     * @param string $key
     * @param array $value
     * @return bool
     */
    public static function set(string $key, array $value): bool
    {
        return static::table()->set($key, $value);
    }

    /**
     * 删除一行
     * @param string $key
     * @return bool
     */
    public static function del(string $key): bool
    {
        return static::table()->del($key);
    }

    /**
     * 检查 key 是否存在
     * @param string $key
     * @return bool
     */
    public static function exist(string $key): bool
    {
        return static::table()->exist($key);
    }

    /**
     * 表状态信息
     * @return array|false
     */
    public static function stats(): array|false
    {
        return static::table()->stats();
    }

    /**
     * 行数统计
     */
    public static function count(): int
    {
        return static::table()->count();
    }

    /**
     * 获取底层 Swoole\Table 实例（用于遍历）
     */
    public static function getTable(): \Swoole\Table
    {
        return static::table();
    }
}
