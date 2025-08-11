<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / RamCache.php    [ 2025/4/11 11:38 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

class RamCache
{
    /**
     * @var \Swoole\Table
     */
    protected static $instance = null;
    protected static $size = 0;
    /**
     * 创建内存共享表
     * @return void
     */
    public static function create(int $size = 2048)
    {
        self::$size = $size;
        $table = new \Swoole\Table($size, 0.6);
        $table->column('key', \Swoole\Table::TYPE_STRING, 255);
        $table->column('string', \Swoole\Table::TYPE_STRING, 65535);
        $table->column('number', \Swoole\Table::TYPE_FLOAT);
        $table->column('expire', \Swoole\Table::TYPE_INT);
        $table->create();
        self::$instance = $table;
    }

    /**
     * 获取key
     * @param string $key
     * @return string | float
     */
    public static function get(string $key, &$expire = 0)
    {
        if(self::$instance){
            $data = self::$instance->get($key);
            if($data && $data['expire'] > time()){
                $expire = $data['expire'];
                if(empty($data["string"]) == false){
                    return $data["string"];
                }
                return $data["number"];
            }
            self::$instance->del($key);
        }
        return null;
    }

    /**
     * 设置key
     * @param string $key
     * @param string|float $val
     * @param int $expire
     * @return false
     */
    public static function set(string $key, string | float $val, int $expire = 864000000, bool $retry = true)
    {
        if(self::$instance){
            $data = [
                'key' => $key,
                'expire' => time() + $expire,
            ];
            if(is_float($val)){
                $data["number"] = $val;
            }else{
                $data["string"] = $val;
            }
            try {
                $ok = @self::$instance->set($key, $data);
            }catch (\Throwable $err){
                $ok = false;
            }

            if($retry == true){
                return self::set($key, $val, $expire, false);
            }
            if($ok == false && self::count() >= self::$size){
                App::error()->setError("内存表限制的".self::$size.'大小的容量被耗尽', 503);
            }
            return $ok;
        }

        return false;
    }

    /**
     * 删除key
     * @param string $key
     * @return false
     */
    public static function del(string $key)
    {
        if(self::$instance){
            return self::$instance->del($key);
        }
        return false;
    }

    /**
     * 状态信息
     * @return null
     */
    public static function stats()
    {
        if(self::$instance){
            return self::$instance->stats();
        }
        return null;
    }

    /**
     * 统计个数
     * @return int
     */
    public static function count()
    {
        if(self::$instance){
            return self::$instance->count();
        }
        return 0;
    }
    /**
     * 递减
     * @param string $key
     * @param int $decrby
     * @return float
     */
    public static function decr(string $key, int $decrby = 1)
    {
        if(self::$instance){
            return self::$instance->decr($key, 'number', $decrby);
        }
        return -1;
    }

    /**
     * 自增
     * @param string $key
     * @param int $decrby
     * @return float
     */
    public static function incr(string $key, int $decrby = 1)
    {
        if(self::$instance){
            return self::$instance->incr($key, 'number', $decrby);
        }
        return -1;
    }

    /**
     * 进行清理
     * @return void
     */
    protected static function clean(){
        $time = time();
        foreach (self::$instance as $v){
            if($v['expire'] <= $time){
                self::$instance->del($v['key']);
            }
        }
    }
}