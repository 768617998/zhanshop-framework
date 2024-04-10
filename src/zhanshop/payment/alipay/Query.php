<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Query.php    [ 2024/3/30 10:07 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\alipay;

use zhanshop\App;
use zhanshop\Curl;

class Query
{
    /**
     * 查询某个订单
     * @param string $orderId
     * @param string $option
     * @return array
     */
    public function trade(string $orderId, array $option = ['trade_settle_info'], array $other = [])
    {
        $config = App::make(Config::class);

        $data = [
            'method' => 'alipay.trade.query',
            'out_trade_no' => $orderId,
            'query_options' => $option
        ];

        if($other) $data = array_merge($data, $other);

        $params = $config->signParams($data, 'query');
        $curl = new Curl();
        $resp = $curl->request($config->get('gateway'), 'POST', http_build_query($params));
        $json = json_decode($resp['body'], true);
        if($json == false){
            $body = iconv("GBK", "UTF-8", $resp['body']);
            $json = json_decode($body, true);
        }
        return current($json);
    }

    /**
     * 查询某个退款订单
     * @param string $orderId
     * @param array $other
     * @return false|mixed
     */
    public function refund(string $orderId, array $other = [])
    {
        $config = App::make(Config::class);

        $data = [
            'method' => 'alipay.trade.fastpay.refund.query',
            'out_trade_no' => $orderId,
        ];

        if($other) $data = array_merge($data, $other);

        $params = $config->signParams($data, 'query');
        $curl = new Curl();
        $resp = $curl->request($config->get('gateway'), 'POST', http_build_query($params));
        $json = json_decode($resp['body'], true);
        if($json == false){
            $body = iconv("GBK", "UTF-8", $resp['body']);
            $json = json_decode($body, true);
        }
        return current($json);
    }

    /**
     * 查看某个日期的对账单
     * @param string $bype
     * @param string $date
     * @return void
     */
    public function bill(string $bype = 'trade', string $date = "", array $other = [])
    {
        if($date == false) $date = date('Y-m-d', strtotime('-1day'));
        $config = App::make(Config::class);

        $data = [
            'method' => 'alipay.data.dataservice.bill.downloadurl.query',
            'bill_type' => $bype,
            'bill_date' => $date
        ];

        if($other) $data = array_merge($data, $other);

        $params = $config->signParams($data, 'query');
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