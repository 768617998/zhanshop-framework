<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / File.php    [ 2025/6/3 15:35 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\log;

use zhanshop\App;

class File
{
    /**
     * 当前活跃的句柄，换日自动关闭旧句柄
     */
    protected static mixed $handle = null;
    protected static string $handleDate = '';

    public function write(array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $today = date('Ymd');
        $filePath = App::runtimePath() . '/log/' . $today . '.log';
        $data = implode(PHP_EOL, $data) . PHP_EOL;

        // 换日：关闭旧句柄，打开新文件
        // fclose/fopen 是 I/O 会触发协程切换，fopen 后需 CAS 防重复 fd 泄漏
        if ($today !== self::$handleDate || !is_resource(self::$handle)) {
            $oldHandleDate = self::$handleDate;
            if (is_resource(self::$handle)) {
                fclose(self::$handle);
            }
            // 协程切换后，其他协程可能已完成换日
            // handleDate 变化且句柄有效才算已被处理
            if ($oldHandleDate !== self::$handleDate && is_resource(self::$handle)) {
                // 已被处理，直接复用
                $fp = self::$handle;
            } else {
                $dir = dirname($filePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $fp = fopen($filePath, 'a');
                if ($fp === false) {
                    self::$handle = null;
                    self::$handleDate = '';
                    return false;
                }
                // fopen 也触发了协程切换，再次检查
                if ($oldHandleDate !== self::$handleDate && is_resource(self::$handle)) {
                    fclose($fp);
                    $fp = self::$handle;
                } else {
                    self::$handle = $fp;
                    self::$handleDate = $today;
                }
            }
        }

        $fp = self::$handle;

        // 检测外部删文件：inode 的 link count 为 0 说明已被 unlink
        // 此时文件不可见但磁盘未释放
        $stat = fstat($fp);
        if ($stat === false || $stat['nlink'] === 0) {
            // 先尝试开新文件，成功后再关旧句柄
            // fopen 失败则继续用旧句柄写入（数据不丢，磁盘下次修复后释放）
            $newFp = fopen($filePath, 'a');
            if ($newFp !== false) {
                // fopen 触发了协程切换，其他协程可能已重建过句柄
                if (self::$handle === $fp) {
                    fclose($fp);
                    self::$handle = $newFp;
                } else {
                    fclose($newFp);
                }
                $fp = self::$handle;
            }
        }

        // O_APPEND + fwrite：多进程原子追加，不加 flock
        $written = fwrite($fp, $data);
        if ($written !== false) {
            fflush($fp);
        }

        return $written !== false;
    }

    /**
     * 跨天或无日志时释放前一天句柄
     * 由 Log timer 定期调用，防止句柄泄漏导致磁盘无法回收
     */
    public static function rotateIfNeeded(): void
    {
        $today = date('Ymd');
        if ($today !== self::$handleDate && is_resource(self::$handle)) {
            $oldHandleDate = self::$handleDate;
            fclose(self::$handle);
            // fclose 是 I/O 操作会触发协程切换，其他协程可能已通过 write()
            // 创建了新句柄（可能是同一天也可能是新的一天）。
            // 仅在 handleDate 未被改变且句柄未被重建的情况下才清空。
            if (self::$handleDate === $oldHandleDate && !is_resource(self::$handle)) {
                self::$handle = null;
                self::$handleDate = '';
            }
        }
    }

    /**
     * 关闭当前句柄
     */
    public static function close(): void
    {
        if (is_resource(self::$handle)) {
            fclose(self::$handle);
        }
        self::$handle = null;
        self::$handleDate = '';
    }
}
