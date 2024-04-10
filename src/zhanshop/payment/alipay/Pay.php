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
use zhanshop\Curl;

class Pay
{
    /**
     * app支付
     * @param string $orderId
     * @param float $amount
     * @param array $attach
     * @param $other
     * @return string
     */
    public function app(string $orderId, float $amount, array $attach, $other = [])
    {
        $config = App::make(Config::class);

        $timeExpress = ceil($config->get('timeout') / 60).'m'; // 订单支付失效时间

        $data = [
            'method' => 'alipay.trade.app.pay',
            'subject' => $orderId.'订单',
            'out_trade_no' => $orderId,
            'total_amount' => $amount,
            'passback_params' => json_encode($attach),
            'timeout_express' => $timeExpress
        ];

        if($other) $data = array_merge($data, $other);

        $params = $config->signParams($data, 'appalipay');
        return http_build_query($params);
    }

    /**
     * 手机网页支付
     * @param string $orderId
     * @param float $amount
     * @param array $attach
     * @param $other
     * @return string
     */
    public function wap(string $orderId, float $amount, array $attach, $other = [])
    {
        $config = App::make(Config::class);

        $timeExpress = ceil($config->get('timeout') / 60).'m'; // 订单支付失效时间

        $data = [
            'method' => 'alipay.trade.wap.pay',
            'subject' => $orderId.'订单',
            'out_trade_no' => $orderId,
            'total_amount' => $amount,
            'passback_params' => json_encode($attach),
            'timeout_express' => $timeExpress,
            "quit_url" => $config->get('quit_url', 'https://www.zhanshop.cn/v1/order.success').'/'.$orderId,
            "product_code" => "QUICK_WAP_WAY"
        ];

        if($other) $data = array_merge($data, $other);

        $params = $config->signParams($data, 'wapalipay');

        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='" . $config->get('gateway') . "?charset=UTF-8" . "' method='POST'>";
        while (list ($key, $val) = $config->fun_adm_each($params)) {
            if (false === $config->checkEmpty($val)) {
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
     * @param string $orderId
     * @param float $amount
     * @param array $attach
     * @param $other
     * @return array
     */
    public function native(string $orderId, float $amount, array $attach, $other = [])
    {
        $config = App::make(Config::class);

        $timeExpress = ceil($config->get('timeout') / 60).'m'; // 订单支付失效时间

        $data = [
            'method' => 'alipay.trade.precreate',
            'subject' => $orderId.'订单',
            'out_trade_no' => $orderId,
            'total_amount' => $amount,
            'passback_params' => json_encode($attach),
            'timeout_express' => $timeExpress,
        ];

        if($other) $data = array_merge($data, $other);

        $params = $config->signParams($data, 'nativealipay');

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
     * 签约支付
     * @param string $orderId
     * @param float $amount
     * @param array $attach
     * @param array $signData
     * @param $other
     * @return void
     */
    public function contract(string $orderId, float $amount, array $attach, array $signData = [], $other = [])
    {
        $config = App::make(Config::class);

        $timeExpress = ceil($config->get('timeout') / 60).'m'; // 订单支付失效时间

        $period = $signData['period'] ?? 1;
        $periodType = $signData['period_type'] ?? "MONTH";
        $executeTime = date('Y-m-d', strtotime('+1 '.strtolower($periodType))); // 下个日期
        $agreementSignParams = [

            /** 个人签约产品码，周期扣款固定值：CYCLE_PAY_AUTH_P **/
            "personal_product_code" =>"CYCLE_PAY_AUTH_P",

            /** 商户签约号，代扣协议中标示用户的唯一签约号（确保在商户系统中唯一） **/
            "external_agreement_no" =>  $attach['uid'] ?? App::error()->setError('$attach中缺失uid'),

            /** 协议签约场景，根据商户签约场景自行选择相同或者相近的行业，参考：https=>//opendocs.alipay.com/open/20190319114403226822/signscene **/
            "sign_scene" => $signData['sign_channel'] ?? 'INDUSTRY|CARRENTAL',

            /** 签约方式，建议入参 ALIPAYAPP(钱包h5页面签约) **/
            "access_params" => ["channel" => $signData['channel'] ?? 'ALIPAYAPP'], // 签约方式

            /** 当前用户签约请求的协议有效周期,支持单位：1、d(天)；2、m(月) **/
            "sign_validity_period" => "999m",

            /** 周期管控参数,可参考：https=>//opensupport.alipay.com/support/helpcenter/109/201602495831   **/
            "period_rule_params" => [

                /** 周期类型，支持单位：1、DAY(扣款周期按天计);2、MONTH(扣款周期按自然月) **/
                "period_type" => $periodType,

                /** 周期数 ,与period_type组合使用确定扣款周期，例如period_type为DAY，period=90，则扣款周期为90天。按天扣款下，扣款周期必须大于7天 **/
                "period" => $period,

                /** 首次执行时间，格式为yyyy-MM-dd;按月周期扣款下，不能入参每月28号之后的日期，不包含28号 **/
                "execute_time" => $executeTime, // 首次扣款时间为下个月

                /** 单次扣款最大金额，商户每次发起扣款都不允许大于此金额 **/
                "single_amount" => $config->get('single_amount'),

                /** 总金额限制，商户多次扣款的累计金额不允许超过此金额 **/
                "total_amount" => $config->get('total_amount'),

                /** 总扣款次数，商户成功扣款的次数不能超过此次数限制（扣款失败不计入） **/
                "total_payments" => $config->get('total_payments')

            ]
        ];

        $data = [
            'method' => 'alipay.trade.app.pay',
            'subject' => $orderId.'订单',
            'out_trade_no' => $orderId,
            'total_amount' => $amount,
            'passback_params' => json_encode($attach),
            'timeout_express' => $timeExpress,
            'agreement_sign_params' => json_encode($agreementSignParams, JSON_UNESCAPED_UNICODE)
        ];

        if($other) $data = array_merge($data, $other);

        $params = $config->signParams($data, 'contractalipay');
        return http_build_query($params);
    }
}