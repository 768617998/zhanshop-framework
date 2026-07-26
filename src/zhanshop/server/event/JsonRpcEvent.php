<?php
// +----------------------------------------------------------------------
// | admin / JsonRpc.php    [ 2023/7/4 下午5:26 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\server\event;

use zhanshop\App;
use zhanshop\console\command\server\Accepted;
use zhanshop\Helper;
use zhanshop\Log;
use zhanshop\server\Event;

class JsonRpcEvent extends Event
{
    protected $passIps = [];
    public function __construct(mixed &$server, array $servNames)
    {
        parent::__construct($server, $servNames);
        $passIps = App::env()->get('RPC_PASS_IP');
        if($passIps){
            $this->passIps = explode(',', $passIps);
            $this->passIps[] = '127.0.0.1';
            $this->passIps[] = '::ffff:127.0.0.1';
        }
    }
    /**
     * 建立连接
     * @param $server
     * @param $fd
     * @param $reactorId
     * @return void
     */
    public function onConnect($server, $fd, $reactorId) :void
    {
        if($this->passIps){
            $clientInfo = $server->getClientInfo($fd);
            $clientIp = $clientInfo['remote_ip'] ?? '-1';
            if(Helper::isPrivateIpv4($clientIp) == false && !in_array($clientIp, $this->passIps)){
                $server->close($fd);
                return;
            }
        }
        App::make(Accepted::class)->newConnection($fd);
    }

    /**
     * 接收tcp消息
     * @param $server
     * @param $fd
     * @param $reactorId
     * @param $data
     * @return void
     */
    public function onReceive($server, $fd, $reactorId, $data) :void{
        $connection = App::make(Accepted::class)->getConnection($fd);
        if($connection && $data !== ""){
            $connection->setBuff($data);
            $fullData = &$connection->getBuff();
            try {
                if(strpos($fullData, "\r\n") !== false){
                    $arr = explode("\r\n", $fullData);
                    for($i = 0; $i < count($arr) - 1; ++$i){
                        $rowData = $arr[$i];
                        $this->rpcRequest($server, $fd, $reactorId, $rowData);
                        $fullData = substr($fullData, strlen($rowData) + 2);
                    }
                }
            }catch (\Throwable $e){
                App::log()->push($e->getMessage(), 5, "ERROR");
                $fullData = "";
            }
        }
    }

    /**
     * rpc请求
     * @param $server
     * @param $fd
     * @param $reactorId
     * @param $data
     * @return void
     */
    protected function rpcRequest($server, $fd, $reactorId, $data)
    {
        $startTime = microtime(true);
        $request = json_decode($data, true);
        if($request){
            list($class, $method) = explode('.', $request['path']);
            $query = $request['query'] ?? [];
            $code = 0;
            $msg = "OK";
            $data = null;
            try {
                $instance = new $class(...$query);
                $data = $instance->$method(...$request['body']);
                unset($instance);
            }catch (\Throwable $e){
                $code = 500;
                $msg = $e->getMessage();
                $data = $e->getTraceAsString();
            }
            $resp = [
                'code' => $code,
                'msg' => $msg,
                'header' => [],
                'data' => $data,
                'runtime' => microtime(true) - $startTime
            ];
            App::log()->push(json_encode([
                'path' => $request['path'],
                'query' => $query,
                'body' => $request['body'],
                'resp' => $resp
            ], JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE), Log::NOTICE, 'RPC');

            $server->send($fd, json_encode($resp, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE)."\r\n");
        }else{
            $server->send($fd, json_encode([
                'code' => 400,
                'msg' => 'Invalid JSON',
                'header' => [],
                'data' => 'JSON解析失败: '.substr($data, 0, 100),
            ], JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE)."\r\n");
        }
    }

    public function onClose($server, $fd, $reactorId) :void
    {
        App::make(Accepted::class)->closeConnection($fd);
    }
}