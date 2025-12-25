<?php
// +----------------------------------------------------------------------
// | zhanshop-message  / Chuanglan.php    [ 2025/4/23 16:38 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\sms;

use zhanshop\client\Httpclient;

class Chuanglan
{
    protected $config = [
        "account" => "",
        "password" => "",
        "phoneNumbers" => "",
        "templateParamJson" => "",
        "template_code" => "",
        "sign_name" => "",
        "callback_url" => "http://api.zhanshop.cn/v1/sms.reply/chuanglan/123456"
    ];

    public function __construct($config = [])
    {
        $this->config["account"] = $config["account"] ?? throw new \Exception("account参数不能为空");
        $this->config["password"] = $config["password"] ?? throw new \Exception("password参数不能为空");
        $this->config["sign_name"] = $config["sign_name"] ?? throw new \Exception("sign_name参数不能为空");
        $this->config["template_code"] = $config["template_code"] ?? throw new \Exception("template_code参数不能为空");
        if(isset($config["callback_url"])) $this->config["callback_url"] = $config["callback_url"];

        $this->config['nonce'] = $this->getNonceNum(32);
        $this->config['timestamp'] = time();
    }

    /**
     * 基于时间生成随机字符串
     * @param int $length
     * @return string
     */
    protected function getNonceNum(int $length)
    {
        $date = new \DateTime();
        $date = substr($date->format('ymdHisu'), 0, 14).substr(strval(getmypid()), -1, 1);
        $length -= strlen($date);
        $max = intval(str_repeat("9", $length));
        $date .= sprintf("%0{$length}d", rand(0, $max));
        return $date;
    }

    /**
     * 发送创蓝短信
     * @param string $area
     * @param string $phone
     * @param array $param
     * @return array|bool|string
     */
    public function sendSms(string $area, string $phone, array $param, string $callbackData = "")
    {
        $url = 'https://smssh.253.com/msg/sms/v2/tpl/send';

        $md5Password = md5($this->config['password']);
        $array = [$md5Password, $this->config['timestamp'], $this->config['nonce']];
        sort($array, SORT_STRING);
        $str = implode('', $array);
        $strWithoutSpaces = preg_replace('/\s+/', '', $str);
        $signature = hash_hmac('sha256', $strWithoutSpaces, $md5Password);

        $client = new Httpclient();
        $client->setHeader('Content-Type', 'application/json; charset=utf-8');
        $client->setHeader('X-QA-Hmac-Signature', $signature);

        $data = [
            'account' => $this->config['account'],
            'password' => $this->config['password'],
            'nonce' => $this->config['nonce'],
            'timestamp' => $this->config['timestamp'],
            'phoneNumbers' => $phone,
            'templateId' => $this->config['template_code'],
            'signature' => $this->config['sign_name'],
            'report' => true,
            'callbackUrl' => $this->config['callback_url'],
            'uid' => $callbackData
        ];
        if($param) $data["templateParamJson"] = json_encode([$param]);

        return $client->setTimeout(5000)->debug()->request($url, "POST", json_encode($data));
    }

    /**
     * 获取回执结果
     * @param array $param
     * @return array
     */
    public static function getReceipt(array $param)
    {
        $status = ($param['status'] ?? '') == "DELIVRD" ? true : false;
        $time = 0;
        if($status) $time = strtotime('20'.($param['notifyTime'] ?? '0'));
        return [
            'status' => $status === true ? 1 : 0,
            'errmsg' => mb_substr(addslashes($status ? "SUCCESS" : ($param['status'] ?? 'unknown').($param['statusDesc'] ?? '')), 0, 60),
            'msgid' => $param['msgid'] ?? '',
            'arrive_time' => $time,
            'attach' => $param['uid'] ?? ""
        ];
    }
}