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
use zhanshop\ShareData;

class BridgeHttpProxyEvent
{
    /**
     * @param \Swoole\Server $server
     * @param int $fd
     * @return void
     */
    protected function getProxyFd(mixed $server, int $fd)
    {
        $clientInfo = $server->getClientInfo($fd);
        if(isset($clientInfo['remote_ip']) == false){
            $server->close($fd);
            return false;
        }

        $remoteIp = $clientInfo['remote_ip'];
        foreach (ShareData::getInstance() as $row){
            if(strpos($row['proxy'], $remoteIp.',') !== false){
                return $row['fd'];
            }
        }
        $server->close($fd);
        return false;
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
        $toFd = $this->getProxyFd($server, $fd);
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
        $toFd = $this->getProxyFd($server, $fd);
        if($toFd){
            $server->send($toFd, json_encode([
                    'uri' => '/v1/proxy.httpClose/'.$fd,
                    'header' => [],
                    'body' => ""
                ])."\r\n");
        }
    }
}