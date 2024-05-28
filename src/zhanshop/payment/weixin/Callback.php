<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Callback.php    [ 2024/3/30 10:43 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\weixin;

use zhanshop\App;

class Callback
{
    protected $config = [];
    public function __construct()
    {
        $this->config = new Config();
    }

    /**
     * 设置配置
     * @param string $key
     * @param mixed $val
     * @return void
     */
    public function setConfig(string $key, mixed $val)
    {
        $this->config->setConfig($key, $val);
    }

    /**
     * 支付回调
     * @param array $params
     * @param callable $callBack
     * @return string|void
     * @throws \Exception
     */
    public function pay(array &$params, callable $callBack)
    {
        if($result = $this->config->verify(
            $params['resource']['associated_data'],
            $params['resource']['nonce'],
            $params['resource']['ciphertext'])){
            $params = json_decode($result, true);
            $callBack();
            // 异常错误的退款请在业务代码上实现
            return '<xml>
  <return_code><![CDATA[SUCCESS]]></return_code>
  <return_msg><![CDATA[OK]]></return_msg>
</xml> ';
        }
        App::error()->setError('微信支付签名验证失败');
    }
}