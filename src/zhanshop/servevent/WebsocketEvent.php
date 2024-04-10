<?php
// +----------------------------------------------------------------------
// | zhanshop-server / WebsocketEvent.php    [ 2023/12/7 15:54 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\servevent;

use Swoole\WebSocket\Frame;
use zhanshop\App;
use zhanshop\console\command\Server;
use zhanshop\Request;
use zhanshop\Response;
use zhanshop\ServEvent;

class WebsocketEvent extends ServEvent
{
    /**
     * http请求
     * @param \Swoole\Http\Response $request
     * @param \Swoole\Http\Response $response
     * @param int $protocol
     * @param string $appName
     * @return void
     */
    public function onRequest($request, $response, $protocol = Server::WEBSOCKET, $appName = 'websocket') :void{
        if(!$this->onStatic($request, $response)){
            $servRequest = new Request($protocol, $request);
            $servResponse = new Response($response, $request->fd);
            App::webhandle()->dispatch($appName, $servRequest, $servResponse);
            $servResponse->sendHttp();
        }
    }

    /**
     * 首次请求
     * @param \Swoole\WebSocket\Server $server
     * @param Request $request
     * @param int $protocol
     * @param string $appName
     * @return void
     */
    public function onOpen($server, $request, $protocol = Server::WEBSOCKET, $appName = 'websocket') :void{
        $servRequest = new Request($protocol, $request);
        $servResponse = new Response($server, $request->fd);

        App::webhandle()->dispatchWebSocket($appName, $servRequest, $servResponse);

        if(!$servResponse->sendWebSocket()){
            $server->close($request->fd);
        }
    }

    /**
     * 消息响应
     * @param \Swoole\WebSocket\Server $server
     * @param Frame $frame
     * @param int $protocol
     * @param string $appName
     * @return void
     */
    public function onMessage($server, $frame, $protocol = Server::WEBSOCKET, $appName = 'websocket') :void{
        if($frame->data){
            $data = json_decode($frame->data, true);
            $request = \Swoole\Http\Request::create([]);
            $request->fd = $frame->fd;
            $clientInfo = $server->getClientInfo($request->fd);
            $request->server['remote_addr'] = $clientInfo['remote_ip'] ?? '-1';
            $request->server['request_uri'] = $data['uri'] ?? '/v1/index.index';
            $request->server['request_time'] = time();
            $request->server['request_method'] = 'POST';
            foreach($data['header'] ?? [] as $k => $v){
                $request->header[$k] = $v;
            }
            $request->post = $data['body'] ?? [];
            $servRequest = new Request($protocol, $request);
            $servResponse = new Response($server, $frame->fd);
            App::webhandle()->dispatchWebSocket($appName, $servRequest, $servResponse);
            if(!$servResponse->sendWebSocket()){
                $server->close($frame->fd);
            }
        }
    }

    /**
     * 静态访问响应
     * @param \Swoole\Http\Response $request
     * @param \Swoole\Http\Response $response
     * @return bool
     */
    private function onStatic($request, $response){
        try{
            $uri = App::rootPath().'/public'.$request->server['request_uri'];
            if(is_dir($uri)) $uri = rtrim($uri, '/').'/index.html';
            if(file_exists($uri)){
                $response->header('Server', 'zhanshop');
                $ext = pathinfo($uri, PATHINFO_EXTENSION);
                if($ext == 'js'){
                    $response->header('Content-Type', 'text/javascript');
                }else if($ext == 'css'){
                    $response->header('Content-Type', 'text/css');
                }else{
                    $response->header('Content-Type', mime_content_type($uri));
                }
                $lastModifiedTime = filemtime($uri);
                if(($request->header['if-modified-since'] ?? 0) == $lastModifiedTime){
                    $response->status(304);
                    $response->end();
                    return true;
                }

                $response->header('Last-Modified', $lastModifiedTime);
                $response->sendfile($uri);
                return true;
            }
        }catch (\Throwable $e){}

        return false;
    }
}