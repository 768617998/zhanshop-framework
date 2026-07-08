<?php
// +----------------------------------------------------------------------
// | websocket-msg / Oauth.php    [ 2026/1/6 15:32 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\sns\wechat;

use zhanshop\client\Httpclient;

class Oauth
{
    protected string $appId;
    protected string $secret;
    public function __construct(string $appId = "", string $secret = "")
    {
        $this->appId = $appId;
        $this->secret = $secret;
    }
    /**
     * 获取微信凭证
     * @param bool $refresh
     * @return array
     */
    public function getAccessToken(bool $refresh = false)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/stable_token';
        $client = new Httpclient();
        $result = $client->setHeader('Content-Type', 'application/json')->setTimeout(30000)->request($url, "POST", json_encode([
            'grant_type' => 'client_credential',
            'appid' => $this->appId,
            'secret' => $this->secret,
            'force_refresh' => $refresh
        ]));
        return json_decode($result['body'] ?? '', true);
    }

    /**
     * 小程序登录凭证校验
     * @param string $jsCode
     * @return array|mixed|string[]|null
     */
    public function getJscode2Session(string $jsCode)
    {
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$this->appId.'&secret='.$this->secret.'&js_code='.$jsCode.'&grant_type=GRANT_TYPE';
        $client = new Httpclient();
        $result = $client->debug()->setHeader('Content-Type', 'application/json')->setTimeout(30000)->request($url, "GET");
        return json_decode($result['body'] ?? '', true);
    }
}