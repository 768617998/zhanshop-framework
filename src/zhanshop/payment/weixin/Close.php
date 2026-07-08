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
use zhanshop\client\Httpclient;

class Close
{
    /**
     * 关闭订单(未支付)
     * POST /v3/pay/transactions/out-trade-no/{out_trade_no}/close
     *
     * 与 cancel 的区别:
     *   close  → 只能关未支付,无资金流动
     *   cancel → 可撤销付款码订单,资金退回
     *
     * @param string $orderId
     * @param array $other
     * @return array
     * @see https://pay.weixin.qq.com/doc/v3/merchant/4012382158
     */
    public function close(string $orderId, array $other = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/out-trade-no/'.$orderId.'/close';
        $config = App::make(Config::class);
        $data = [ // JSON请求体
            'mchid' => $config->get('mchid'),
            'appid' => $config->get('appid'),
        ];

        if($other) $data = array_merge($data, $other);

        $httpClient = new Httpclient();
        $httpClient->setHeader('Accept', 'application/json');
        $httpClient->setHeader('Content-Type', 'application/json');
        $nonce = $config->getNonce();
        $timestamp = strval(time());
        $path = parse_url($url, PHP_URL_PATH);

        $signature = $config->sign(implode("\n", [
                'POST', $path, $timestamp, $nonce, json_encode($data)
            ])."\n", $config->get('privatekey'));
        $httpClient->setHeader('Authorization', $config->authorization($config->get('mchid'), $nonce, $signature,$timestamp, $config->get('serialnum')));
        $payResp = $httpClient->debug()->request($url, 'POST', json_encode($data));
        return json_decode($payResp['body'], true);
    }

    /**
     * 撤销付款码订单
     * POST /v3/pay/transactions/out-trade-no/{out_trade_no}/reverse
     *
     * 适用场景: codepay 返回 USERPAYING/PAYERROR 且轮询查询后仍不确定结果时调用。
     * 仅 trade_state=USERPAYING 或 SYSTEMERROR 时可撤销。
     *
     * @param string $orderId 商户订单号
     * @return array
     * @see https://pay.weixin.qq.com/doc/v3/merchant/4012382158
     */
    public function cancel(string $orderId)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/out-trade-no/' . $orderId . '/reverse';
        $config = App::make(Config::class);

        $data = [
            'mchid' => $config->get('mchid'),
            'appid' => $config->get('appid'),
        ];

        $client = new Httpclient();
        $client->setHeader('Accept', 'application/json');
        $client->setHeader('Content-Type', 'application/json');
        $nonce = $config->getNonce();
        $timestamp = strval(time());
        $path = parse_url($url, PHP_URL_PATH);

        $signature = $config->sign(implode("\n", [
                'POST', $path, $timestamp, $nonce, json_encode($data)
            ]) . "\n", $config->get('privatekey'));
        $client->setHeader('Authorization', $config->authorization(
            $config->get('mchid'), $nonce, $signature, $timestamp, $config->get('serialnum')
        ));
        $payResp = $client->debug()->request($url, 'POST', json_encode($data));
        if ($payResp['code'] != 200) {
            App::error()->setError($payResp['body']);
        }
        return json_decode($payResp['body'], true);
    }
}