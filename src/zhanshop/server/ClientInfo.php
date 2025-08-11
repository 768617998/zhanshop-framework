<?php
// +----------------------------------------------------------------------
// | zhanshop_admin / ClientInfo.php [ 2023/4/22 下午8:48 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

class ClientInfo
{
    protected $client = [];

    /**
     * 创建一个连接信息
     * @param int $fd
     * @return void
     */
    public function create(int $fd){
        Log::errorLog(SWOOLE_LOG_WARNING, '创建一个连接信息fd:'.$fd.',pid:'.getmypid());
        $this->client[$fd] = [];
    }

    /**
     * 设置连接信息
     * @param int $fd
     * @param string $name
     * @param mixed $data
     * @return void
     */
    public function set(int $fd, string $name, mixed $data){
        Log::errorLog(SWOOLE_LOG_DEBUG, '设置一个连接信息['.$fd.']['.$name.']'.print_r($data, true).' '.getmypid());
        $this->client[$fd][$name] = $data;
    }

    /**
     * 获取连接信息
     * @param int $fd
     * @param string $name
     * @param mixed|null $default
     * @return array|mixed|null
     */
    public function get(int $fd, string $name, mixed $default = null){
        $data = $this->client[$fd][$name] ?? $default;
        Log::errorLog(SWOOLE_LOG_WARNING, '获取一个连接信息['.$fd.']['.$name.'] '.print_r($this->client[$fd][$name] ?? $default, true).' '.getmypid());
        if ('' === $name) {
            return $this->client[$fd] ?? [];
        }
        if($data == false) print_r($this->client);
        return $data;
    }

    /**
     * 销毁一个连接信息
     * @param int $fd
     * @return void
     */
    public function close(int $fd){
        Log::errorLog(SWOOLE_LOG_ERROR, '销毁一个连接信息fd:'.$fd.',pid:'.getmypid());
        unset($this->client[$fd]);
    }
}