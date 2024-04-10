<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Socks5.php    [ 2023/08/26 下午1:42 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\servevent;

use zhanshop\App;
use zhanshop\proxy\Proxy;
use zhanshop\proxy\ProxyService;


class Socks5Event
{
    /**
     * 认证客户端的账号密码是否满足要求
     * @param string $userName
     * @param string $password
     * @return bool
     */
    public function authAccount(string $userName, string $password)
    {
        return false;
    }

    /**
     * 认证客户端的ip地址是否允许连接到代理服务器
     * @param string $ipAddress
     * @return bool
     */
    public function authIpAddress(string $ipAddress)
    {
        return true;
    }
    /**
     * 认证客户端的要访问的域名是否是否允许访问
     * @param string $ipAddress
     * @return bool
     */
    public function authTargetHost(string $host)
    {
        return true;
    }

    /**
     * 目标服务器发来响应
     * @param \Swoole\Server $server
     * @param int $fd
     * @param string $data
     * @return void
     */
    public function targetResponse($server, $fd, $data)
    {
        $server->send($fd, $data);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     * @return void
     */
    public function onConnect($server, $fd, $reactorId) :void{
        //App::make(ProxyService::class, [$server])->($fd);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     * @param string $data
     * @return void
     */
    public function onReceive($server, $fd, $reactorId, $data) :void{
        App::make(ProxyService::class, [$server])->socks5Message($fd, $data);
    }

    /**
     * 关闭消息
     * @param \Swoole\Server $server
     * @param int $fd
     * @return void
     */
    public function onClose($server, $fd) :void{
        App::make(ProxyService::class, [$server, $this])->close($fd);
    }
}