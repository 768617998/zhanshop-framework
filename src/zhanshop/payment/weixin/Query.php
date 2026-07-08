<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Query.php    [ 2024/3/30 10:07 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\weixin;

use zhanshop\App;
use zhanshop\client\Httpclient;

class Query
{
    /**
     * 查询某个订单
     * @param string $orderId
     * @param string $option
     * @return array
     */
    public function trade(string $orderId, array $other = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/out-trade-no/'.$orderId;
        $config = App::make(Config::class);
        $data = [ // JSON请求体
            'mchid' => $config->get('mchid'),
            'appid' => $config->get('appid'),
        ];

        if($other) $data = array_merge($data, $other);

        $client = new Httpclient();
        $client->setHeader('Accept', 'application/json');
        $client->setHeader('Content-Type', 'application/json');
        $nonce = $config->getNonce();
        $timestamp = strval(time());
        $path = parse_url($url, PHP_URL_PATH);

        $signature = $config->sign(implode("\n", [
                'POST', $path, $timestamp, $nonce, json_encode($data)
            ])."\n", $config->get('privatekey'));
        $client->setHeader('Authorization', $config->authorization($config->get('mchid'), $nonce, $signature,$timestamp, $config->get('serialnum')));
        $payResp = $client->debug()->request($url, 'GET', json_encode($data));
        return json_decode($payResp['body'], true);
    }

    /**
     * 查询某个退款订单
     * @param string $orderId
     * @param array $other
     * @return false|mixed
     */
    public function refund(string $orderId, array $other = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds/'.$orderId;
        $config = App::make(Config::class);
        $data = [ // JSON请求体
            'mchid' => $config->get('mchid'),
            'appid' => $config->get('appid'),
        ];

        if($other) $data = array_merge($data, $other);

        $client = new Httpclient();
        $client->setHeader('Accept', 'application/json');
        $client->setHeader('Content-Type', 'application/json');
        $nonce = $config->getNonce();
        $timestamp = strval(time());
        $path = parse_url($url, PHP_URL_PATH);

        $signature = $config->sign(implode("\n", [
                'GET', $path, $timestamp, $nonce, json_encode($data)
            ])."\n", $config->get('privatekey'));
        $client->setHeader('Authorization', $config->authorization($config->get('mchid'), $nonce, $signature,$timestamp, $config->get('serialnum')));
        $payResp = $client->debug()->request($url, 'POST', json_encode($data));
        return json_decode($payResp['body'], true);
    }
}