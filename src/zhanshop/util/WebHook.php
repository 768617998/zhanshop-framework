<?php

namespace zhanshop\util;

use zhanshop\App;
use zhanshop\client\Httpclient;

class WebHook
{
    protected $weChatUrl = '';
    protected $feishuUrl = '';
    protected $bridgeMsgUrl = '';
    public function __construct()
    {
        $this->weChatUrl = App::env()->get("WEBHOOK_WECHAT");
        $this->feishuUrl = App::env()->get("WEBHOOK_FEISHU");

        $bridgeMsgUrl = App::env()->get("BRIDGE_MSGURL");
        if($bridgeMsgUrl == false){
            $bridgeMsgUrl = 'http://'.App::config()->get('bridge.ip').':'.(intval(App::config()->get('bridge.port')) - 1).'/v1/webhook.notice/'.App::config()->get('bridge.token');
        }
        $this->bridgeMsgUrl = $bridgeMsgUrl;
    }

    /**
     * 推送微信机器人消息
     * @param string $clientIp 发送方IP
     * @param string $msgtype 支持【servtext, text，markdown，news】
     * @param mixed $content
     * @param array $mentionedList
     * @param array $mentionedMobileList
     * @return void
     */
    public function weChat(string $clientIp, string $msgtype, mixed $content, array $mentionedList = ['@all'], array $mentionedMobileList = [])
    {
        if($this->weChatUrl){
            $data = [
                'msgtype' => $msgtype,
            ];
            switch ($msgtype) {
                case 'text':
                    $data['text']['content'] = $content;
                    $data['mentioned_list'] = $mentionedList;
                    $data['mentioned_mobile_list'] = $mentionedMobileList;
                    break;
                case 'servtext':
                    $data['msgtype'] = 'text';
                    $data['text']['content'] = $clientIp.' '.$content;
                    $data['mentioned_list'] = $mentionedList;
                    $data['mentioned_mobile_list'] = $mentionedMobileList;
                    break;
                case 'markdown':
                    $data['markdown']['content'] = $content;
                    break;
                case 'news':
                    $contents = [];
                    foreach ($content as $item) {
                        $contents[] = [
                            "title" => $item["title"] ?? '未知标题',
                            "description" => $item["description"] ?? '未知描述',
                            "url" => $item["url"] ?? 'http://baidu.com',
                            "picurl" => $item["picurl"] ?? 'https://inews.gtimg.com/newsapp_bt/0/15822349471/0',
                        ];
                    }
                    $data['news']['articles'] = $contents;
                    break;
            }
            $body = (new Httpclient())->debug()->setHeader('Content-Type', 'application/json')->request($this->weChatUrl, "POST", json_encode($data))['body'];
            if($body) return json_decode($body, true);
        }else{
            App::error()->setError("服务端微信机器人还没有配置");
        }
    }

    /**
     * 推送飞书机器人消息
     * @param string $clientIp
     * @param string $msgtype
     * @param mixed $content
     * @param array $mentionedList
     * @param array $mentionedMobileList
     * @return void
     */
    public function feishu(string $clientIp, string $msgtype, mixed $content, array $mentionedList = ['@all'], array $mentionedMobileList = [])
    {
        if($this->feishuUrl){
            $body = (new Httpclient())->debug()->setHeader('Content-Type', 'application/json')->request($this->feishuUrl, 'POST', json_encode([
                'msg_type' => 'text',
                'content' => [
                    'text' => $content
                ]
            ]))['body'];
            if($body) return json_decode($body, true);
        }else{
            App::error()->setError("服务端飞书机器人还没有配置");
        }
    }

    /**
     * 推送网桥消息
     * @param string $message
     * @param string $msgType
     * @param array $mentionedList
     * @param array $mentionedMobileList
     * @return void
     */
    public function bridge(string $message, string $msgType = "servtext", array $mentionedList = ['@all'], array $mentionedMobileList = [])
    {
        if($this->bridgeMsgUrl){
            $msg = '['.date('Y-m-d H:i:s').'] '.$message;
            $appName = App::env()->get('APP_NAME', 'zhanshop-server');
            try{
                (new Httpclient())->enableIpv6()->debug()->setHeader('Content-Type', 'application/json')->request($this->bridgeMsgUrl, "POST", json_encode([
                    'msgtype' => $msgType,
                    'content' => $appName.PHP_EOL.$msg,
                ]));
            }catch (\Throwable $e){}
        }

    }
}