<?php
// +----------------------------------------------------------------------
// | admin / JsonRpc.php    [ 2023/7/4 下午5:26 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\servevent;

use zhanshop\App;
use zhanshop\console\command\server\Accepted;
use zhanshop\Log;
use zhanshop\ServEvent;

class JsonRpcEvent extends ServEvent
{
    /**
     * 建立连接
     * @param $server
     * @param $fd
     * @param $reactorId
     * @return void
     */
    public function onConnect($server, $fd, $reactorId) :void
    {
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
        [$path, $query] = explode('?', $request["path"] ?? 'zhanshop\\RpcClient.undefined?0=undefined');
        parse_str($query ?? "", $query);
        [$class, $method] = explode('.', $path);
        if($query == false) $query = [];
        $code = 0;
        $msg = "OK";
        $data = null;
        try {
            $instance = new $class(...$query);
            $data = $instance->$method(...$request['body']);
        }catch (\Throwable $e){
            $code = 500;
            $msg = $e->getMessage();
        }
        $resp = [
            'code' => $code,
            'msg' => $msg,
            'header' => [],
            'data' => $data,
            'runtime' => microtime(true) - $startTime
        ];
        App::log()->push(json_encode([
            'path' => $path,
            'query' => $query,
            'body' => $request['body'],
            'resp' => $resp
        ], JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE), Log::NOTICE, 'RPC');
        
        $server->send($fd, json_encode($resp, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE)."\r\n");
    }

    public function onClose($server, $fd, $reactorId) :void
    {
        App::make(Accepted::class)->closeConnection($fd);
    }
}