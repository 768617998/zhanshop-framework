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
use zhanshop\client\Httpclient;

class Config
{
    protected const SANDBOX_GATEWAY = 'https://sandbox.itunes.apple.com/verifyReceipt';

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
     * @return array
     * @throws \Exception
     */
    public function verify($receipt)
    {
        $body = $this->requestVerify($this->config['gateway'], $receipt);
        // 21007：沙箱收据被发到了生产环境，按 Apple 要求改用沙箱地址重新验证
        if((int)($body['status'] ?? -1) === 21007 && $this->config['gateway'] !== self::SANDBOX_GATEWAY){
            $body = $this->requestVerify(self::SANDBOX_GATEWAY, $receipt);
        }
        // 只有 status === 0 才代表收据有效；21002/21007 等错误码同样是合法 JSON，不能当作验签成功
        if((int)($body['status'] ?? -1) !== 0){
            App::error()->setError('苹果验签失败: status='.($body['status'] ?? 'invalid'));
        }
        return $body;
    }

    /**
     * 请求 Apple 验签网关并解析响应
     * @param string $gateway
     * @param $receipt
     * @return array
     * @throws \Exception
     */
    protected function requestVerify(string $gateway, $receipt)
    {
        $httpClient = new Httpclient();
        $httpClient->setHeader("Content-Type", "application/json; charset=utf-8");
        $resp = $httpClient->debug()->request($gateway, "POST", json_encode(['receipt-data' => $receipt, 'password' => $this->config['password']]));
        $body = json_decode($resp['body'] ?? '', true);
        if(!is_array($body)) App::error()->setError('苹果验签失败: 响应格式错误');
        return $body;
    }
}