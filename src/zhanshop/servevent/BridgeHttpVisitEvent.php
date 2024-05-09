<?php
// +----------------------------------------------------------------------
// | zhanshop-device / ProxyEvent.php    [ 2023/08/27 下午11:00 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\servevent;

use zhanshop\App;
use zhanshop\bridg\proxy\Proxy;
use zhanshop\console\ServerStatus;
use zhanshop\ServEvent;
use zhanshop\ShareData;

class BridgeHttpVisitEvent
{
    public $proxyClients = [];

    public function getProxyFd($server, $fd, $data)
    {
        if(isset($this->proxyClients[$fd])){
            return $this->proxyClients[$fd];
        }

        if($data){
            $reqindex = strstr($data, "\r", true); // 请求行末尾
            if($reqindex){
                // 认证权限
                preg_match('/Proxy-Authorization: Basic (\S*)/', $data, $match);
                if(isset($match[1])){
                    $authInfo = base64_decode($match[1]);
                    list($user, $password) = explode(':', $authInfo);
                    if($password != App::config()->get('app.proxy_auth')){
                        $server->send($this->fd, "HTTP/1.1 405 Bad Request\r\n\r\n");
                        $server->close($this->fd);
                        return 0;
                    }
                    $toFd = intval($user);
                    $this->proxyClients[$fd] = $toFd;
                    return $toFd;
                }
            }
        }
        return 0;
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     * @return void
     */
    public function onConnect($server, $fd, $reactorId) :void{
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     * @param string $data
     * @return void
     */
    public function onReceive($server, $fd, $reactorId, $data) :void{

        $toFd = $this->getProxyFd($server, $fd, $data);
        if($toFd){
            $server->send($toFd, json_encode([
                    'uri' => '/v1/proxy.httpMessage/'.$fd,
                    'header' => [],
                    'body' => base64_encode($data)
                ])."\r\n");
        }
    }

    /**
     * 关闭消息
     * @param \Swoole\Server $server
     * @param int $fd
     * @return void
     */
    public function onClose($server, $fd) :void{
        $toFd = $this->getProxyFd($server, $fd, "");
        if($toFd){
            $server->send($toFd, json_encode([
                    'uri' => '/v1/proxy.httpClose/'.$fd,
                    'header' => [],
                    'body' => ""
                ])."\r\n");
            unset($this->proxyClients[$toFd]);
        }
    }
}