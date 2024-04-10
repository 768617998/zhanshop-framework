<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Close.php    [ 2024/3/30 10:29 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\weixin;

use zhanshop\App;
use zhanshop\Curl;

class Close
{
    /**
     * 关闭某个支付订单
     * @param string $orderId
     * @param array $other
     * @return array
     */
    public function pay(string $orderId, array $other = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/out-trade-no/'.$orderId.'/close';
        $config = App::make(Config::class);
        $data = [ // JSON请求体
            'mchid' => $config->get('mchid'),
            'appid' => $config->get('appid'),
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