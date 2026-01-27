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
    protected $appKey = "";
    protected $appSecret = "";
    protected $url = 'https://api.jpush.cn/v3/push';

    private $platforms = ['ios', 'android', 'winphone', 'hmos'];

    protected $sendData = [
        'platform' => 'all',
        'audience' => [],
        //'notification' => [],
        'options' => [],
    ];

    public function __construct(string $appKey, string $appSecret)
    {
        $this->sendData['options'] = [
            'sendno' => rand(100000, getrandmax()),
            //'apns_production' => []
        ];
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
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
     * 推送给全部（广播）
     * @return $this
     */
    public function audienceAll()
    {
        $this->sendData["audience"] = "all";
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

    /**
     * 通知内容体,是被推送到客户端的内容与 message 一起二者必须有其一，可以二者并存。
     * @param array $notification
     * @return $this
     */
    public function setNotification(array $notification)
    {
        $this->sendData['notification'] = $notification;
        return $this;
    }

    /**
     * 消息内容体,是被推送到客户端的内容 与 notification 一起二者必须有其一，可以二者并存。
     * @param array $message
     * @return $this
     */
    public function setMessage(array $message)
    {
        $this->sendData['message'] = $message;
        return $this;
    }

    /**
     * 面向于通知栏消息类型，需搭配notification参数一起使用，对于通知权限关闭的用户可设置启用此功能 不可与 message 同时并存。
     * @param array $inappMessage
     * @return $this
     */
    public function setInappMessage(array $inappMessage)
    {
        $this->sendData['inapp_message'] = $inappMessage;
        return $this;
    }

    /**
     * options：可选参数
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        if(isset($this->sendData['options']) == false){
            $this->sendData['options'] = $options;
        }else{
            foreach($options as $key => $value){
                $this->sendData['options'][$key] = $value;
            }
        }
        return $this;
    }

    /**
     * 自定义消息转厂商通知
     * @param array $notification3rd
     * @return $this
     */
    public function setNotification3rd(array $notification3rd)
    {
        $this->sendData['notification_3rd'] = $notification3rd;
        return $this;
    }

    public function send()
    {
        $client = new Httpclient();
        $result = $client->debug()->userPwd($this->appKey.':'.$this->appSecret)->setHeader('Content-Type', 'application/json')->request($this->url, 'POST', json_encode($this->sendData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $result;
    }
}