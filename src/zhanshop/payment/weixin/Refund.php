<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Refund.php    [ 2024/3/29 17:56 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\weixin;

use zhanshop\App;
use zhanshop\Curl;

class Refund
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
     * 触发退款
     * @param string $orderId
     * @param float $amount
     * @param string $reason
     * @return array
     */
    public function pay(string $orderId, float $amount, string $reason = "系统退款", $other = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds';
        $data = [ // JSON请求体
            //'mchid' => $config->get('mchid'),//$config[''],
            //'appid' => $config->get('appid'),
            'out_trade_no' => $orderId,
            'out_refund_no' => $orderId,
            'reason' => $reason,
            //'notify_url' => $config['notifyurl'].'/refundwxpay',
            'amount' => [
                'refund' => $amount * 100,
                "total" => $amount * 100,
                "currency" => $this->config->get('currency', 'CNY'),
            ],
        ];

        if($other) $data = array_merge($data, $other);

        $curl = new Curl();
        $curl->seEncodeng(false);
        $curl->setHeader('Accept', 'application/json');
        $curl->setHeader('Content-Type', 'application/json');
        $nonce = $this->config->getNonce();
        $timestamp = strval(time());
        $path = parse_url($url, PHP_URL_PATH);

        $signature = $this->config->sign(implode("\n", [
                'POST', $path, $timestamp, $nonce, json_encode($data)
            ])."\n", $this->config->get('privatekey'));
        $curl->setHeader('Authorization', $this->config->authorization($this->config->get('mchid'), $nonce, $signature,$timestamp, $this->config->get('serialnum')));
        $payResp = $curl->request($url, 'POST', $data, false, false);
        if($payResp['code'] != 200){
            App::error()->setError($payResp['body']);
        }
        return json_decode($payResp['body'], true);
    }
}