<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Config.php    [ 2024/3/29 15:06 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\alipay;

use zhanshop\App;

class Config
{
    public $config = [
        // 支付宝应用ID
        'appid' => '1234567890',
        // 支付宝网站后台生成的支付宝公钥
        'privatekey' => '++MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCjsd5nWOrYhggm18ZR2gN+EQQrdFiGZwBsWKeHgZKTecKQ1IhjsQ6g4kGsFffB/vk2b8YuO6jtNLavalXjMJ7rTP6EhGAtWHH/yRHpcR6pYvCO1la61aWCDTJG2iEVIXehPMe4WYGWEZ85trboX3ZUmilUHiYi0+JX+3Lr2W2Bj/ZBxK+NjUaEz0/1ZSjd9lo9MlY0Troq3Vbjf5D4KoDQwNBk87DJ6ZBj0O9L/rdkv9KR472IIMlRUiTrSHYg62SoscoAHYni1Nj0DeXBei659sa8B39yhGCjakmpvxc9Jpua96UxIf/NbOOy9pVKLeGFDoRAnTpXJonVJxLXUu37AgMBAAECggEASs//1TvOGScokpgLC12YOwWL4lBKJSLuDpZ1+ng9hSkgwxBfRTCmfuDpIrznfsIJI9fxuy+JBOASnhj7J9oQx3lo2SemzRKfB0fd59LLMJ8HUkCjYsBB5C4jzD3k4MNq6UXeAWLm1q83FilrbsgyTHgoa0lcJI6x44QIAcJdSdjElkKIJ5cZIQbsRdNGtRPNXOrn6OVi0ZQ7am2XDdTe2RFpOgCrlyq5bfUIdxXiLnlYZIZ4WGLtpgjN0ntJpJiG/NWkq/Rmm4c9aCCEFeSaTAPGtTKTAoObauuoXddV0rk9X+wqrTgIJ2tlkaBgRiYOGXw3v4fWxahQwacb1TnAAQKBgQDs4ESYuFProv2kcSLkTaOil1CriySTVqVoYNVrsYdyiBEh8TqBTHm5SVh+uzOLmSxv0NAutIzIABLifWUnbhSrWh8od1lGubbyQwCoLuninE2HvOBlZFpc/vW4iPV7d3GD04B9ijgfFoGurB7Xa1QmZPXJTbCYwpkGxsj/CrAfYQKBgQCw6Rj924ViD35pODqAqc5SCPTFS4M2xPqOP5RAql00LpB7e5IGzsAb0MYyYGXCL3FpnCm9DvgOXnT4WZIcGe5rcvpGt5hXz5ADRzkrmmvIaxRMu2GPwWO9xk80mjnZxZQigRfigw+2U3g5baEpTUrJ5gaIHQwqw5py1YxSMI3W2wKBgQC0R0Mt0CvejJ/qBUj+5LADUJJO3IkrsagVtoz3d5eUbUZMxOE4AtIpWE8HtMXYGu2c9BldUoBKvvOgeLsBtV/5wY8xJxRFJZa87o2TcpECrN68zd0ijavQVyMAHQxnuVIleFS0NvvbfS+ZltmqLb9PsUr2uDANMx1v8N6MbL/6QQKBgERPncDYdSaqSOukNKLd7rcqkilkwSPy57Tpw/s8TYUKveuQiZBrYS7cbMujAWf8HdTj4hIIp6wUuSH0pECJmtCOsyl/VyoBEeDSLbcyh4/6GPqQGFoEGNl8q7TqIJCAhHTWWDy00upaTMAQHvztxYU0dy0z2jDhGtBguTA7Y2mlAoGBAK/Qn47b5RoBiwf5mIrXwT109l/9GKr+tRncf9pAH6CtxFHwYhkRVlHVzHBJwacokqf5or2jNbFZ51NGOXRqfSoF7kSzidk/C+nC2Q0ZPQUbbOu//VVZnqzgjTM0LtzZOZz/0dPkGJKObnK8NwPlgz3QvrzwF4UKldq5C3veeCLc',

        // 支付宝开放平台密钥工具生成的私钥
        'publickey' => '++MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAg9BZz02nyeDNanYV4cDaPrGgropLAJTyGIs+91AuPRZhXjw/jYQAcluupBzhGirgZt/vtVBxV/4lHevw99yeWiP3RnL2s+4RGbmX9Raj6tOnZWF2U07JgEd2AkA5NioVzLb1fUx/+UybKrm4z55dTrp/yihFdFcmBBZ5CTk44kxMF7/c6zr07Y3F4sOXRnGjSEyXPLe3/v9s93n67o1yJwwx6ooEoTCL0ojsQKQBOPXg6/2E1sljSazxSXFEBsQWHDwT02pnsmAq5T6Fx6E0+AWZP96BAxZDgPic01RLhdfKlr97nfIs3RzlJXRUCDLUkMeabzxx6PSd3cKgXnk/7QIDAQAB',
        // 签名算法类型
        'signtype' => 'RSA2',
        // 支付通知地址
        'notify' => 'https://api.zhanshop.cn/v1/trades.callback/alipay',
        // 【网页支付】用户付款中途退出返回商户网站的地址
        'quit_url' => 'https://www.zhanshop.cn/',
        // 【网页支付】支付成功后点击完成会自动跳转回商家页面地址， 同时在 URL 地址上附带支付结果参数，回跳参数可查看本文 附录 > 前台回跳参数说明。在 iOS 系统中，唤起支付宝客户端支付完成后，不会自动回到浏览器或商家 App。用户可手工切回到浏览器或商家 App。
        'return_url' => 'https://www.zhanshop.cn/',
        // 支付过期时间
        'timeout' => 1800,
        // 版本
        'version' => '1.0',
        // sdk名
        'sdkname' => 'alipay-easysdk-php-2.2.3',
        /**【签约支付】 单次扣款最大金额，商户每次发起扣款都不允许大于此金额 **/
        "single_amount" => 3000,
        /** 【签约支付】总金额限制，商户多次扣款的累计金额不允许超过此金额 **/
        "total_amount" => 999999999,
        /** 【签约支付】总扣款次数，商户成功扣款的次数不能超过此次数限制（扣款失败不计入） **/
        "total_payments" => 999999999,
        // 网关
        'gateway' => 'https://openapi.alipay.com/gateway.do'
    ];

    public function __construct()
    {
        $config = App::config()->get("pay.ali");
        if($config) $this->config = array_merge($this->config, $config);

    }

    /**
     * 获取配置
     * @param string $name
     * @param $default
     * @return array|mixed|null
     */
    public function get(string $name = '', $default = null)
    {
        if($name === '') return $this->config;
        return $this->config[$name] ?? $default;
    }

    /**
     * 签名参数
     * @param $data
     * @return array
     */
    public function signParams(&$data, $type = 'appalipay')
    {
        $systemParams = [
            "method" => $data['method'],
            "app_id" => $this->config['appid'],
            "timestamp" => date('Y-m-d H:i:s'),
            "format" => "json",
            "version" => $this->config['version'],
            "alipay_sdk" => $this->config['sdkname'],
            "charset" => "UTF-8",
            "sign_type" => $this->config['signtype'],
            "app_cert_sn" => null,
            "alipay_root_cert_sn" => null
        ];

        unset($data['method']);

        $systemParams['biz_content'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        $systemParams['notify_url'] = $this->config['notify'].'/'.$type;
        $systemParams['return_url'] = $this->config['return_url'].'/'.($data['out_trade_no'] ?? '0');

        ksort($systemParams);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($systemParams as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // 转换成目标字符集
                $v = $this->characet($v, "UTF-8");
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);

        $priKey = $this->config['privatekey'];
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        if($res == false) throw new Exception('您使用的私钥格式错误，请检查RSA私钥配置', 400);
        openssl_sign($stringToBeSigned, $sign, $res, OPENSSL_ALGO_SHA256);
        $sign = base64_encode($sign);
        $systemParams['sign'] = $sign;
        return $systemParams;
    }

    ///////////////////////////////////////////////////
    public function checkEmpty($value)
    {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;
        return false;
    }
    private function characet($data, $targetCharset)
    {
        if (!empty($data)) {
            $fileType = "UTF-8";
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
            }
        }
        return $data;
    }
    public function fun_adm_each(&$array)
    {
        $res = array();
        $key = key($array);
        if ($key !== null) {
            next($array);
            $res[1] = $res['value'] = $array[$key];
            $res[0] = $res['key'] = $key;
        } else {
            $res = false;
        }
        return $res;
    }
    public function verify($params)
    {
        $signature = $params['sign'];
        ksort($params);
        unset($params['sign']);
        unset($params['sign_type']);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if ("@" != substr($v, 0, 1)) {
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);

        $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($this->config['publickey'], 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        try {
            if (openssl_verify($stringToBeSigned, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256) == 1) {
                return true;
            }
        }catch (\Throwable $e){}
        return false;
    }
}