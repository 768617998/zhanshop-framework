<?php

namespace zhanshop;

class Limit
{
    protected $key = 'limit:';
    protected $expireTime = 300;

    /**
     * 过期时间
     * @param int $expireTime
     * @return void
     */
    public function setExpireTime(int $expireTime)
    {
        $this->expireTime = $expireTime;
    }
    /**
     * 验证是否被限流
     * @param string $ip
     * @param string $key
     * @return void
     */
    public function isPass(string $ip, string $key, $limit = 10)
    {
        $key = $this->key.md5($ip).':'.$key;
        if(App::cache()->get($key) >= $limit){
            return false;
        }
        return true;
    }

    /**
     * 记录次数
     * @param string $ip
     * @param string $key
     * @return void
     */
    public function pass(string $ip, string $key)
    {
        $key = $this->key.md5($ip).':'.$key;
        $number = App::cache()->get($key);
        if($number == false){
            App::cache()->set($key, 0, $this->expireTime);
        }
        App::cache()->incr($key); // 递增
    }
}