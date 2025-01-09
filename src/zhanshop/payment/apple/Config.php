<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Config.php    [ 2024/3/29 15:06 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\apple;

use zhanshop\App;
use zhanshop\Httpclient;

class Config
{
    public $config = [
        // 苹果应用ID
        'appid' => '1234567890',
        'gateway' => 'https://buy.itunes.apple.com/verifyReceipt',
        'password' => '123456',
    ];

    public function __construct()
    {
        $config = App::config()->get("pay.apple");
        if($config) $this->config = array_merge($this->config, $config);
    }

    /**
     * 设置配置
     * @param string $key
     * @param mixed $val
     * @return void
     */
    public function setConfig(string $key, mixed $val)
    {
        $this->config[$key] = $val;
    }

    /**
     * 获取配置
     * @param string $name
     * @param $default
     * @return array|mixed|null
     */
    public function get(string $name = '', $default = null)
    {
        if($name === '') return $this->config;
        return $this->config[$name] ?? $default;
    }

    /**
     * 苹果验证签
     * @param $receipt
     * @return mixed|void
     * @throws \Exception
     */
    public function verify($receipt)
    {
        $httpClient = new Httpclient();
        $httpClient->setHeader("Content-Type", "application/json; charset=utf-8");
        $resp = $httpClient->request($this->config['gateway'], "POST", json_encode(['receipt-data' => $receipt, 'password' => $this->config['password']]));
        if($resp['body']){
            $body = json_decode($resp['body'], true);
            if($body) return $body;
        }
        App::error()->setError("苹果验签失败:".$resp['body']);
    }
}