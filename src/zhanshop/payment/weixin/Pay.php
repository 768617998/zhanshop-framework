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

        $data = [
            'mchid' => $this->config->get('mchid'),
            'appid' => $this->config->get('appid'),
            'out_trade_no' => $orderId,
            'description' => $orderId.'订单',
            'attach' => json_encode($attach),
            'amount' => [
                'total' => $amount * 100,
                'currency' => $this->config->get('currency', 'CNY'),
            ],
            'notify_url' => $this->config->get('notifyurl').'/appwxpay',
            'time_expire' => str_replace(' ', 'T', date('Y-m-d H:i:s', time() + $this->config->get('timeout'))).'+08:00'
        ];

        if($other) $data = array_merge($data, $other);


        $curl = new Curl();
        $curl->seEncodeng(false);
        $curl->setHeader('Accept', 'application/json');
        $curl->setHeader('Content-Type', 'application/json');
        $nonce = $this->config->getNonce();
        $timestamp = strval(time());
        $path = parse_url($url, PHP_URL_PATH);

        $signature = $this->config->sign(implode("\n", [
                'POST', $path, $timestamp, $nonce, json_encode($data)
            ])."\n", $this->config->get('privatekey'));
        $curl->setHeader('Authorization', $this->config->authorization($this->config->get('mchid'), $nonce, $signature,$timestamp, $this->config->get('serialnum')));
        $payResp = $curl->debug()->request($url, 'POST', $data, false, false);
        if($payResp['code'] != 200){
            App::error()->setError($payResp['body']);
        }
        $payResp = json_decode($payResp['body'], true);
        $resData = [
            "appid" => $this->config->get('appid'),  // 微信开放平台 - 应用 - AppId，注意和微信小程序、公众号 AppId 可能不一致
            "noncestr" => substr(md5((string)microtime(true)),8,16), // 随机字符串
            "package" => "Sign=WXPay",        // 固定值
            "partnerid" => $this->config->get('mchid'),      // 微信支付商户号
            "prepayid" => $payResp['prepay_id'], // 统一下单订单号
        ];
        $md5Sign = $this->config->md5Sign($resData);
        // 对App支付进行签名
        $time = strval(time());
        $sign = $this->config->sign(join("\n", [$resData['appid'], $time, $resData['noncestr'], $resData['prepayid'], '']), $this->config->get('privatekey'));
        $resData['package'] = 'Sign=WXPay';
        $resData['timestamp'] = $time;
        $resData['sign'] = $sign;
        $resData['sign_md5'] = $md5Sign;
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
        $data = [ // JSON请求体
            'mchid' => $this->config->get('mchid'),
            'appid' => $this->config->get('appid'),
            'out_trade_no' => $orderId,
            'description' => $orderId.'订单',
            'notify_url' => $this->config->get('notifyurl').'/wapwxpay',
            'amount' => [
                "total" => $amount * 100,
                "currency" => $this->config->get('currency', 'CNY'),
            ],
            'time_expire' => str_replace(' ', 'T', date('Y-m-d H:i:s', time() + $this->config->get('timeout'))).'+08:00',
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
        $nonce = $this->config->getNonce();
        $timestamp = strval(time());
        $path = parse_url($url, PHP_URL_PATH);

        $signature = $this->config->sign(implode("\n", [
                'POST', $path, $timestamp, $nonce, json_encode($data)
            ])."\n", $this->config->get('privatekey'));
        $curl->setHeader('Authorization', $this->config->authorization($this->config->get('mchid'), $nonce, $signature,$timestamp, $this->config->get('serialnum')));
        $payResp = $curl->request($url, 'POST', $data, false, false);
        if($payResp['code'] != 200){
            App::error()->setError($payResp['body']);
        }
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
        $data = [ // JSON请求体
            'mchid' => $this->config->get('mchid'),
            'appid' => $this->config->get('appid'),
            'out_trade_no' => $orderId,
            'description' => $orderId.'订单',
            'notify_url' => $this->config->get('notifyurl').'/jswxpay',
            'amount' => [
                "total" => $amount * 100,
                "currency" => $this->config->get('currency', 'CNY'),
            ],
            'time_expire' => str_replace(' ', 'T', date('Y-m-d H:i:s', time() + $this->config->get('timeout'))).'+08:00',
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
        $nonce = $this->config->getNonce();
        $timestamp = strval(time());
        $path = parse_url($url, PHP_URL_PATH);


        $signature = $this->config->sign(implode("\n", [
                'POST', $path, $timestamp, $nonce, json_encode($data)
            ])."\n", $this->config->get('privatekey'));
        $curl->setHeader('Authorization', $this->config->authorization($this->config->get('mchid'), $nonce, $signature,$timestamp, $this->config->get('serialnum')));
        $payResp = $curl->request($url, 'POST', $data, false, false);

        if($payResp['code'] != 200) App::error()->setError($payResp['body'], $payResp['code']);

        $payResp = json_decode($payResp['body'], true);

        $resData = [
            "appid" => $this->config->get('appid'),  // 微信开放平台 - 应用 - AppId，注意和微信小程序、公众号 AppId 可能不一致
            "noncestr" => substr(md5((string)microtime(true)),8,16), // 随机字符串
            "package" => "Sign=WXPay",        // 固定值
            "partnerid" => $this->config->get('mchid'),      // 微信支付商户号
            "prepayid" => $payResp['prepay_id'], // 统一下单订单号
        ];

        $time = strval(time());

        $sign = $this->signBuild(join("\n", [$resData['appid'], $time, $resData['noncestr'], "prepay_id={$resData['prepayid']}", '']), $this->config->get('privatekey'));
        $resData['timestamp'] = $time;
        $resData['signtype'] = 'RSA';
        $resData['sign'] = $sign;
        return $resData;
    }

    /**
     * Native支付
     * @param string $orderId
     * @param float $amount
     * @param array $attach
     * @param $other
     * @return array
     */
    public function native(string $orderId, float $amount, array $attach, $other = [])
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/native';
        $data = [ // JSON请求体
            'mchid' => $this->config->get('mchid'),
            'appid' => $this->config->get('appid'),
            'out_trade_no' => $orderId,
            'description' => $orderId.'订单',
            'notify_url' => $this->config->get('notifyurl').'/nativewxpay',
            'amount' => [
                "total" => $amount * 100,
                "currency" => $this->config->get('currency', 'CNY'),
            ],
            'time_expire' => str_replace(' ', 'T', date('Y-m-d H:i:s', time() + $this->config->get('timeout'))).'+08:00',
            'attach' => json_encode($attach), // 进行编码
        ];

        if($other) $data = array_merge($data, $other);

        $curl = new Curl();
        $curl->seEncodeng(false);
        $curl->setHeader('Accept', 'application/json');
        $curl->setHeader('Content-Type', 'application/json');
        $nonce = $this->config->getNonce();
        $timestamp = strval(time());
        $path = parse_url($url, PHP_URL_PATH);

        $signature = $this->config->sign(implode("\n", [
                'POST', $path, $timestamp, $nonce, json_encode($data)
            ])."\n", $this->config->get('privatekey'));
        $curl->setHeader('Authorization', $this->config->authorization($this->config->get('mchid'), $nonce, $signature,$timestamp, $this->config->get('serialnum')));
        $payResp = $curl->request($url, 'POST', $data, false, false);
        if($payResp['code'] != 200){
            App::error()->setError($payResp['body']);
        }
        return json_decode($payResp['body'], true);
    }
}