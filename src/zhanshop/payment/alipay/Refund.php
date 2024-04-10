<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Refund.php    [ 2024/3/29 17:56 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\alipay;

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
    public function pay(string $orderId, float $amount, string $reason = "", $other = [])
    {
        $config = App::make(Config::class);

        $data = [
            'method' => 'alipay.trade.refund',
            "out_trade_no" => $orderId,
            "refund_amount" => $amount,
            'refund_reason' => $reason
        ];

        if($other) $data = array_merge($data, $other);

        $params = $config->signParams($data, 'refundalipay');

        $curl = new Curl();
        $resp = $curl->request($config->get('gateway'), 'POST', http_build_query($params));
        $json = json_decode($resp['body'], true);
        if($json == false){
            $body = iconv("GBK", "UTF-8", $resp['body']);
            $json = json_decode($body, true);
        }
        return current($json);
    }
}