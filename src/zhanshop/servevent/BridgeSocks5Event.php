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
use zhanshop\bridg\proxy\Proxy;
use zhanshop\bridg\proxy\socks5\ProxyShake;
use zhanshop\bridg\proxy\socks5\server\BridgeSocks5SerEvent;
use zhanshop\console\ServerStatus;
use zhanshop\Log;
use zhanshop\ShareData;


class BridgeSocks5Event
{

    protected function getProxyFd(mixed $server, int $fd)
    {
        $clientInfo = $server->getClientInfo($fd);
        if(isset($clientInfo['remote_ip']) == false){
            $server->close($fd);
            return false;
        }

        $remoteIp = $clientInfo['remote_ip'];
        $toFd = 0;
        foreach (ShareData::getInstance() as $row){
            if(strpos($row['proxy'], $remoteIp.',') !== false){
                $toFd = $row['fd'];
            }
        }
        if($toFd) return $toFd;
        
        $server->close($fd);
        return false;
    }

    /**
     * 认证客户端的账号密码是否满足要求
     * @param string $userName
     * @param string $password
     * @return bool
     */
    public function authAccount(string $userName, string $password)
    {
        return true;
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
        $toFd = $this->getProxyFd($server, $fd);
        if ($toFd) App::make(BridgeSocks5SerEvent::class)->connect($server, $fd, $toFd);
    }

    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     * @param string $data
     * @return void
     */
    public function onReceive($server, $fd, $reactorId, $data) :void{
        App::make(BridgeSocks5SerEvent::class)->receive($server, $fd, $data);
    }


    /**
     * 关闭消息
     * @param \Swoole\Server $server
     * @param int $fd
     * @return void
     */
    public function onClose($server, $fd) :void{
        App::make(BridgeSocks5SerEvent::class)->close($server, $fd);
    }
}