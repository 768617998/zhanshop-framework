<?php
// +----------------------------------------------------------------------
// | js-websocket-msg / Message.php    [ 2026/1/6 16:33 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\sns\wechat;

use zhanshop\client\Httpclient;

class Message
{
    /**
     * 发送微信小程序订阅消息
     * @param string $token
     * @param string $toOpenid
     * @param string $templateId
     * @param array $data
     * @param string $miniprogramState
     * @param string $lang
     * @param string $page
     * @return array
     */
    public function sendMiniSubscribeMsg(string $token, string $toOpenid, string $templateId, array $data, string $miniprogramState = 'formal', string $lang = "", string $page = "")
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$token;
        $client = new Httpclient();
        $data = [
            'template_id' => $templateId,
            'touser' => $toOpenid,
            'data' => $data,
            'miniprogram_state' => $miniprogramState,
            'lang' => $lang,
        ];
        if($page) $data['page'] = $page;
        $result = $client->debug()->setHeader('Content-Type', 'application/json')->request($url, "POST", json_encode($data));
        return $result;
    }

    /**
     * 发送微信公众号模板消息
     * @param string $token
     * @param string $toOpenid
     * @param string $tplId
     * @param string $topcolor
     * @param array $tplData
     * @param array $miniprogram
     * @param string $url
     * @return array
     */
    public function sendMptplMsg(string $token, string $toOpenid, string $tplId, string $topcolor, array $tplData, array $miniprogram = [], string $url = "")
    {

    }
}