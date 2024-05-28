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
        $config = App::make(Config::class);
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
                "currency" => $config->get('currency', 'CNY'),
            ],
        ];

        if($other) $data = array_merge($data, $other);

        $curl = new Curl();
        $curl->seEncodeng(false);
        $curl->setHeader('Accept', 'application/json');
        $curl->setHeader('Content-Type', 'application/json');
        $nonce = $config->getNonce();
        $timestamp = strval(time());
        $path = parse_url($url, PHP_URL_PATH);

        $signature = $config->sign(implode("\n", [
                'POST', $path, $timestamp, $nonce, json_encode($data)
            ])."\n", $config->get('privatekey'));
        $curl->setHeader('Authorization', $config->authorization($config->get('mchid'), $nonce, $signature,$timestamp, $config->get('serialnum')));
        $payResp = $curl->request($url, 'POST', $data, false, false);
        return json_decode($payResp['body'], true);
    }
}