<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Pay.php    [ 2024/3/29 19:19 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\weixin;

use zhanshop\App;
use zhanshop\Curl;

class Pay
{
    /**
     * app支付
     * @param string $orderId
     * @param float $amount
     * @param array $attach
     * @param $other
     * @return array
     */
    public function app(string $orderId, float $amount, array $attach, $other = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/app';

        $config = App::make(Config::class);

        $data = [
            'mchid' => $config->get('mchid'),
            'appid' => $config->get('appid'),
            'out_trade_no' => $orderId,
            'description' => $orderId.'订单',
            'attach' => json_encode($attach),
            'amount' => [
                'total' => $amount * 1000,
                'currency' => $config->get('currency', 'CNY'),
            ],
            'notify_url' => $config->get('notifyurl').'/weixinapppay',
            'time_expire' => str_replace(' ', 'T', date('Y-m-d H:i:s', time() + $config->get('timeout'))).'+08:00'
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
        $payResp = json_decode($payResp['body'], true);
        $resData = [
            "appid" => $config->get('appid'),  // 微信开放平台 - 应用 - AppId，注意和微信小程序、公众号 AppId 可能不一致
            "noncestr" => substr(md5((string)microtime(true)),8,16), // 随机字符串
            "package" => "Sign=WXPay",        // 固定值
            "partnerid" => $config->get('mchid'),      // 微信支付商户号
            "prepayid" => $payResp['prepay_id'], // 统一下单订单号
            //"sign" => "A842B45937F6EFF60DEC7A2EAA52D5A0" // 签名，这里用的 MD5 签名
        ];

        $p =  ksort($resData);
        $strs = '';
        if($p){
            $str = '';
            foreach ($resData as $k=>$val){
                $str .= $k .'=' . $val . '&';
            }
            $strs = rtrim($str, '&');
        }

        $stringA = $strs;
        $stringSignTemp = $stringA."&key=".$config->get('aeskey'); //注：key为商户平台设置的密钥key
        $stringSignTemp = strtoupper(md5($stringSignTemp));
        $resData['sign'] = $stringSignTemp;
        $resData["timestamp"] = time();        // 时间戳（单位：秒）
        return $resData;
    }

    /**
     * wap支付
     * @param string $orderId
     * @param float $amount
     * @param array $attach
     * @param $other
     * @return array
     */
    public function wap(string $orderId, float $amount, array $attach, $other = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/h5';
        $config = App::make(Config::class);
        $data = [ // JSON请求体
            'mchid' => $config->get('mchid'),
            'appid' => $config->get('appid'),
            'out_trade_no' => $orderId,
            'description' => $orderId.'订单',
            'notify_url' => $config->get('notifyurl').'/weixinwappay',
            'amount' => [
                "total" => $amount * 100,
                "currency" => $config->get('currency', 'CNY'),
            ],
            'time_expire' => str_replace(' ', 'T', date('Y-m-d H:i:s', time() + $config->get('timeout'))).'+08:00',
            'scene_info' => [
                "payer_client_ip" => '114.114.114.114',
                "h5_info" => [
                    "type" => "Wap"
                ]
            ],
            'attach' => json_encode($attach), // 进行编码
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

    /**
     * js支付
     * @param string $orderId
     * @param float $amount
     * @param array $attach
     * @param $other
     * @return void
     */
    public function js(string $orderId, float $amount, array $attach, $other = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi';
        $config = App::make(Config::class);
        $data = [ // JSON请求体
            'mchid' => $config->get('mchid'),
            'appid' => $config->get('appid'),
            'out_trade_no' => $orderId,
            'description' => $orderId.'订单',
            'notify_url' => $config->get('notifyurl').'/weixinwappay',
            'amount' => [
                "total" => $amount * 100,
                "currency" => $config->get('currency', 'CNY'),
            ],
            'time_expire' => str_replace(' ', 'T', date('Y-m-d H:i:s', time() + $config->get('timeout'))).'+08:00',
            'scene_info' => [
                "payer_client_ip" => '114.114.114.114',
                "h5_info" => [
                    "type" => "Wap"
                ]
            ],
            'attach' => json_encode($attach), // 进行编码
        ];
        if(isset($other['payer']['openid']) == false) App::error()->setError('\$other[\'payer\'][\'openid\']缺失');
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

        if($payResp['code'] != 200) App::error()->setError($payResp['body'], $payResp['code']);

        $payResp = json_decode($payResp['body'], true);

        $resData = [
            "appid" => $config->get('appid'),  // 微信开放平台 - 应用 - AppId，注意和微信小程序、公众号 AppId 可能不一致
            "noncestr" => substr(md5((string)microtime(true)),8,16), // 随机字符串
            "package" => "Sign=WXPay",        // 固定值
            "partnerid" => $config->get('mchid'),      // 微信支付商户号
            "prepayid" => $payResp['prepay_id'], // 统一下单订单号
            //"sign" => "A842B45937F6EFF60DEC7A2EAA52D5A0" // 签名，这里用的 MD5 签名
        ];

        $p =  ksort($resData);
        $strs = '';
        if($p){
            $str = '';
            foreach ($resData as $k=>$val){
                $str .= $k .'=' . $val . '&';
            }
            $strs = rtrim($str, '&');
        }

        $stringA = $strs;
        $stringSignTemp = $stringA."&key=".$config->get('aeskey'); //注：key为商户平台设置的密钥key
        $stringSignTemp = strtoupper(md5($stringSignTemp));
        $resData['sign'] = $stringSignTemp;
        $resData["timestamp"] = time();        // 时间戳（单位：秒）
        return $resData;
    }
}