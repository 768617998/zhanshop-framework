<?php
// +----------------------------------------------------------------------
// | zhanshop-message / Aliyun.php    [ 2025/12/4 17:03 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\sms;

use zhanshop\client\Httpclient;

class Aliyun
{
    protected $config = [
        "url" => "https://dysmsapi.aliyuncs.com",
        "access_key" => "",
        "access_secret" => "",
        "sign_name" => "",
        "template_code" => "",
        "region_id" => "cn-hangzhou",
        "version" => "2017-05-25",
    ];

    /**
     * 构造方法
     * @param array $config
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        $this->config["access_key"] = $config["access_key"] ?? throw new \Exception("access_key参数不能为空");
        $this->config["access_secret"] = $config["access_secret"] ?? throw new \Exception("access_secret参数不能为空");
        $this->config["sign_name"] = $config["sign_name"] ?? throw new \Exception("sign_name参数不能为空");
        $this->config["template_code"] = $config["template_code"] ?? throw new \Exception("template_code参数不能为空");
        if(isset($config["region_id"])) $this->config["region_id"] = $config["region_id"];
        if(isset($config["version"])) $this->config["version"] = $config["version"];
    }

    /**
     * 签名编码
     * @param string $str
     * @return array|string|string[]|null
     */
    private function encodeSignature($str)
    {
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);
        return $res;

    }

    /**
     * 发送阿里云短信验证码
     * @param string $area
     * @param string $phone
     * @param array $param
     * @return array
     */
    public function sendSms(string $area, string $phone, array $param, string $app)
    {
        $params = [
            'PhoneNumbers' => $area.$phone,
            'SignName' => $this->config["sign_name"],
            'TemplateCode' => $this->config["template_code"],
            'TemplateParam' => json_encode($param, JSON_UNESCAPED_UNICODE),
            'RegionId' => $this->config["region_id"],
            'Action' => 'SendSms',
            'Version' => $this->config['version'],
            'OutId' => $app
        ];
        return $this->request($this->config['url'], $params);
    }

    /**
     * 获取回执结果
     * @param array $param
     * @return array
     */
    public static function getReceipt(array $param)
    {
        $success = $param['success'] ?? false;
        $time = 0;
        if($success) $time = strtotime($param['report_time']);
        return [
            'status' => $success === true ? 1 : 0,
            'errmsg' => ($param['err_msg'] ?? ''),
            'msgid' => $param['biz_id'] ?? '',
            'arrive_time' => $time,
            'attach' => $param['out_id'] ?? '',
        ];
    }

    /**
     * 生成签名并发起请求
     * @param string $url
     * @param array $params
     * @return array|bool|string
     */
    protected function request(string $url, array $params) {
        $apiParams = array_merge(array (
            "SignatureMethod" => "HMAC-SHA1",
            "SignatureNonce" => uniqid((string)mt_rand(0, 99999999), true),
            "SignatureVersion" => "1.0",
            "AccessKeyId" => $this->config['access_key'],
            "Timestamp" => gmdate("Y-m-d\TH:i:s\Z"),
            "Format" => "JSON",
        ), $params);
        ksort($apiParams);

        $sortedQueryStringTmp = "";
        foreach ($apiParams as $key => $value) {
            $sortedQueryStringTmp .= "&" . $this->encodeSignature($key) . "=" . $this->encodeSignature($value);
        }

        $stringToSign = "GET&%2F&" . $this->encodeSignature(substr($sortedQueryStringTmp, 1));

        $sign = base64_encode(hash_hmac("sha1", $stringToSign, $this->config['access_secret'] . "&",true));
        $signature = $this->encodeSignature($sign);

        $client = new Httpclient();
        return $client->setTimeout(5000)->debug()->request($url.'?'."Signature={$signature}{$sortedQueryStringTmp}");
    }
}