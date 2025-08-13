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
     * 关闭某个支付订单
     * @param string $orderId
     * @param array $other
     * @return array
     */
    public function pay(string $orderId, array $other = [])
    {
        $config = App::make(Config::class);

        $data = [
            'method' => 'alipay.trade.close',
            'out_trade_no' => $orderId,
        ];

        if($other) $data = array_merge($data, $other);

        $params = $config->signParams($data, 'query');
        $httpClient = new Httpclient();
        $resp = $httpClient->request($config->get('gateway'), 'POST', http_build_query($params));
        $json = json_decode($resp['body'], true);
        if($json == false){
            $body = iconv("GBK", "UTF-8", $resp['body']);
            $json = json_decode($body, true);
        }
        return current($json);
    }
}