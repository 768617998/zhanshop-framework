<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Pay.php    [ 2024/3/29 14:59 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\alipay;

use zhanshop\App;
use zhanshop\Httpclient;

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
     * @param string $orderId 订单id
     * @param float $amount 支付金额
     * @param array $attach 回调参数
     * @param $other 其他参数，可用于覆盖原参数，对于签约支付参数也是放在上面(https://opendocs.alipay.com/open/a3ff3d2a_alipay.trade.app.pay中的agreement_sign_params说明)
 * @return string
     */
    public function app(string $orderId, float $amount, array $attach, $other = [])
    {
        $timeExpress = ceil($this->config->get('timeout') / 60).'m'; // 订单支付失效时间

        $data = [
            'method' => 'alipay.trade.app.pay',
            'subject' => $orderId.'订单',
            'out_trade_no' => $orderId,
            'total_amount' => $amount,
            'passback_params' => json_encode($attach),
            'timeout_express' => $timeExpress
        ];

        if($other) $data = array_merge($data, $other);

        $params = $this->config->signParams($data, 'appalipay');
        return http_build_query($params);
    }

    /**
     * 手机网页支付
     * @param string $orderId 订单id
     * @param float $amount 支付金额
     * @param array $attach 回调参数
     * @param $other 其他参数，可用于覆盖原参数，详见https://opendocs.alipay.com/open/02np8y
     * @return string
     */
    public function wap(string $orderId, float $amount, array $attach, $other = [])
    {
        $timeExpress = ceil($this->config->get('timeout') / 60).'m'; // 订单支付失效时间

        $data = [
            'method' => 'alipay.trade.wap.pay',
            'subject' => $orderId.'订单',
            'out_trade_no' => $orderId,
            'total_amount' => $amount,
            'passback_params' => json_encode($attach),
            'timeout_express' => $timeExpress,
            "quit_url" => $this->config->get('quit_url', 'https://www.zhanshop.cn/v1/order.success').'/'.$orderId,
            "product_code" => "QUICK_WAP_WAY"
        ];

        if($other) $data = array_merge($data, $other);

        $params = $this->config->signParams($data, 'wapalipay');

        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='" . $this->config->get('gateway') . "?charset=UTF-8" . "' method='POST'>";
        while (list ($key, $val) = $this->config->fun_adm_each($params)) {
            if (false === $this->config->checkEmpty($val)) {
                $val = str_replace("'", "&apos;", $val);
                //$val = str_replace("\"","&quot;",$val);
                $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
            }
        }

        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml . "<input type='submit' value='ok' style='display:none;'></form>";

        $sHtml = $sHtml . "<script>document.forms['alipaysubmit'].submit();</script>";

        return $sHtml;
    }

    /**
     * 用户主动扫码支付
     * @param string $orderId 订单id
     * @param float $amount 支付金额
     * @param array $attach 回调参数
     * @param array $other 其他参数，可用于覆盖原参数，详见https://opendocs.alipay.com/open/02np92
     * @return array
     */
    public function native(string $orderId, float $amount, array $attach, $other = [])
    {
        $timeExpress = ceil($this->config->get('timeout') / 60).'m'; // 订单支付失效时间

        $data = [
            'method' => 'alipay.trade.precreate',
            'subject' => $orderId.'订单',
            'out_trade_no' => $orderId,
            'total_amount' => $amount,
            'passback_params' => json_encode($attach),
            'timeout_express' => $timeExpress,
        ];

        if($other) $data = array_merge($data, $other);

        $params = $this->config->signParams($data, 'nativealipay');
        $httpClient = new Httpclient();
        $resp = $httpClient->request($this->config->get('gateway'), 'POST', http_build_query($params));
        $json = json_decode($resp['body'], true);
        if($json == false){
            $body = iconv("GBK", "UTF-8", $resp['body']);
            $json = json_decode($body, true);
        }
        return current($json);
    }

    /**
     * 签约扣款
     * @param $signNo
     * @param $orderId
     * @param $totalAmount
     * @param $other
     * @return false|mixed|void
     * @throws \Exception
     */
    public function signDeduction($signNo, $orderId, $totalAmount, $other = [])
    {
        $data = [
            'method' => 'alipay.trade.pay',
            'subject' => $orderId.'订单',
            'out_trade_no' => $orderId,
            'total_amount' => $totalAmount,
            'product_code' => 'CYCLE_PAY_AUTH',
            'agreement_params' => [
                'agreement_no' => $signNo,
            ]
        ];

        if($other) $data = array_merge($data, $other);

        $params = $this->config->signParams($data, 'appalipay');
        $params = http_build_query($params);
        $httpClient = new Httpclient();
        $resp = $httpClient->request($this->config->get('gateway'), 'POST', $params);
        if($resp['body']){
            $body = iconv("GBK", "UTF-8", $resp['body']);
            $json = json_decode($body, true);
            return current($json);
        }
        App::error()->setError("签约扣款失败返回的是空");
    }
}