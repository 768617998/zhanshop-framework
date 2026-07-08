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
use zhanshop\client\Httpclient;

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
    public function pay(string $orderId, float $amount, string $reason = "", $other = [])
    {
        $data = [
            'method' => 'alipay.trade.refund',
            "out_trade_no" => $orderId,
            "refund_amount" => $amount,
            'refund_reason' => $reason
        ];

        if($other) $data = array_merge($data, $other);

        $params = $this->config->signParams($data, 'refundalipay');

        $httpClient = new Httpclient();
        $resp = $httpClient->debug()->request($this->config->get('gateway'), 'POST', http_build_query($params));
        $json = json_decode($resp['body'], true);
        if($json == false){
            $body = iconv("GBK", "UTF-8", $resp['body']);
            $json = json_decode($body, true);
        }
        return is_array($json) ? (current($json) ?: []) : [];
    }
}