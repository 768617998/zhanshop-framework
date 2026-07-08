<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Close.php    [ 2024/3/30 10:29 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\alipay;

use zhanshop\App;
use zhanshop\client\Httpclient;

class Close
{
    /**
     * 关闭交易(未支付订单)
     * alipay.trade.close — 仅可关闭 WAIT_BUYER_PAY 状态的订单
     *
     * 与 cancel 的区别:
     *   close  → 只能关未支付,无资金流动
     *   cancel → 可撤销已支付/支付中,资金退回
     *
     * @param string $orderId
     * @param array $other
     * @return array
     * @see https://opendocs.alipay.com/apis/api_1/alipay.trade.close
     */
    public function close(string $orderId, array $other = [])
    {
        $config = App::make(Config::class);

        $data = [
            'method' => 'alipay.trade.close',
            'out_trade_no' => $orderId,
        ];

        if($other) $data = array_merge($data, $other);

        $params = $config->signParams($data, 'query');
        $httpClient = new Httpclient();
        $resp = $httpClient->debug()->request($config->get('gateway'), 'POST', http_build_query($params));
        $json = json_decode($resp['body'], true);
        if($json == false){
            $body = iconv("GBK", "UTF-8", $resp['body']);
            $json = json_decode($body, true);
        }
        return is_array($json) ? (current($json) ?: []) : [];
    }

    /**
     * 撤销交易(付款码支付结果不确定时调用)
     * alipay.trade.cancel — 可撤销已支付订单,资金退回买家
     *
     * 适用场景: codepay 返回 10003(不确定)且轮询查询后仍不明 → 调此接口兜底
     *
     * @param string $orderId  商户订单号
     * @param string $tradeNo  支付宝交易号(选填)
     * @return array
     * @see https://opendocs.alipay.com/apis/api_1/alipay.trade.cancel
     */
    public function cancel(string $orderId, string $tradeNo = '')
    {
        $config = App::make(Config::class);

        $data = [
            'method'       => 'alipay.trade.cancel',
            'out_trade_no' => $orderId,
        ];
        if ($tradeNo) {
            $data['trade_no'] = $tradeNo;
        }

        $params = $config->signParams($data, 'query');
        $httpClient = new Httpclient();
        $resp = $httpClient->debug()->request($config->get('gateway'), 'POST', http_build_query($params));
        $json = json_decode($resp['body'], true);
        if ($json == false) {
            $body = iconv("GBK", "UTF-8", $resp['body']);
            $json = json_decode($body, true);
        }
        return $json['alipay_trade_cancel_response'] ?? $json;
    }
}