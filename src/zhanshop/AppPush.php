<?php
// +----------------------------------------------------------------------
// | jswk-websocket-message / AppPush.php    [ 2025/12/3 17:28 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

use zhanshop\client\Httpclient;

class AppPush
{
    protected $url = 'https://api.jpush.cn/v3/push';

    private $platforms = ['ios', 'android', 'winphone', 'hmos'];

    protected $sendData = [
        'platform' => 'all',
        'audience' => [],
        'notification' => [],
        'options' => [],
    ];

    public function __construct()
    {
        $this->sendData['options'] = [
            'sendno' => rand(100000, getrandmax()),
            'apns_production' => []
        ];
    }
    /**
     * 设置平台
     * @param $platform
     * @return $this
     */
    public function setPlatform(string $platform)
    {
        if($platform != 'all' && in_array($platform, $this->platforms)) {
            App::error()->setError($platform.'无效');
        }
        $this->sendData["platform"] = $platform;
        return $this;
    }

    /**
     * 设置设置接收消息的目标ID
     * @param string $registrationId
     * @return $this
     */
    public function addRegistrationId(string $registrationId)
    {
        if(isset($this->sendData["audience"]['registration_id']) == false) $this->sendData["audience"]['registration_id'] = [];
        $this->sendData["audience"]['registration_id'][] = $registrationId;
        return $this;
    }

    /**
     * 设置消息内容
     * @param string $content
     * @return $this
     */
    public function setNotificationAlert(string $content)
    {
        $this->sendData['notification']['alert'] = $content;
        return $this;
    }

    public function send()
    {
        $appKey = App::env()->get("JG_APPKEY", "");
        $appSecret = App::env()->get("JG_APPSECRET", "");
        $client = new Httpclient();
        $result = $client->userPwd($appKey.':'.$appSecret)->setHeader('Content-Type', 'application/json')->request($this->url, 'POST', json_encode($this->sendData));
        return $result;
    }
}