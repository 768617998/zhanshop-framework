<?php
// +----------------------------------------------------------------------
// | zhanshop_admin / Web.php [ 2023/4/28 下午8:35 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\server;

use app\exception\HttpException;
use app\exception\WebSocketException;
use zhanshop\console\command\Server;
use zhanshop\server\route\Dispatch;
use zhanshop\server\service\ApiDoc;
use zhanshop\App;

class WebHandle
{

    protected $webServers = [];
    public function setServers(array $servers)
    {
        $this->webServers = $servers;
        return $this;
    }

    /**
     * 载入路由
     * @return void
     */
    public function router()
    {
        $dispatch = App::make(Dispatch::class);
        foreach($this->webServers as $v){
            $routePath = App::routePath().DIRECTORY_SEPARATOR.$v;
            if(!file_exists($routePath)) continue;
            $files = scandir($routePath);
            foreach ($files as $kk => $vv){
                $versionInfo = pathinfo($vv);
                if($versionInfo['extension'] == 'php'){
                    $routeFile = App::routePath() .DIRECTORY_SEPARATOR.$v.'/'. $vv;
                    $dispatch->setApp($v);
                    $dispatch->setVersion($versionInfo['filename']);
                    require_once $routeFile; // 事先载入路由
                }
            }
            $dispatch->setApp($v);
            $dispatch->setVersion('v1');
            App::route()->rule('GET', '/api.doc', [ApiDoc::class, 'call']);
            App::route()->rule('POST', '/api.doc', [ApiDoc::class, 'call']);
        }
    }

    public function middleware($middleware, \Closure $next){
        return array_reduce(
            $middleware,
            $this->carry(),
            $next
        );
    }

    protected function carry()
    {
        /**
         * @$stack 上一次中间件对象
         * @$pipe 当前中间件对象
         */
        return function ($stack, $pipe) {
            /**
             * @$passable request请求对象
             */
            return function ($request) use ($stack, $pipe) {
                return $pipe($request, $stack);
            };
        };
    }

    /**
     * http调度
     * @param string $appName
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @return void
     */
    public function httpDispatch($appName, $request, $response, int $protocol = Server::HTTP){

        $httpRequest = new Request($protocol, $request);
        $httpResponse = new Response($protocol, $response);
        $httpResponse->setcode(200);
        $httpRequest->setData('__resp__', $httpResponse);
        try {
            $dispatch = App::make(Dispatch::class);

            $route = $dispatch->getRoute($appName, $request->server['request_uri'] ?? '', $request->server['request_method'] ?? "GET");
            $httpRequest->setHandler($route['handler']);
            foreach ($route['extra'] as $name => $val){
                $httpRequest->setData($name, $val);
            }
            $httpRequest->setData('_app', $appName);
            $this->middleware($route['middleware'], function ($req){
                list($controller, $action) = $req->getHandler();
                $resp = $req->getData('__resp__');
                $data = App::make($controller)->$action($req, $resp);
                $resp->setData($data);
                return $resp;
            })($httpRequest);
        }catch (\Throwable $error){
            $data = App::make(HttpException::class)->handle($httpRequest, $error);
            $httpResponse->setcode(intval($error->getCode()));
            $httpResponse->setmsg($error->getMessage());
            $httpResponse->setdata($data);
        }
        try {
            $httpResponse->send();
        }catch (\Throwable $error){}

        unset($httpResponse, $httpRequest);
    }

    /**
     * websocket调度
     * @param $appName
     * @param $request
     * @param $response
     * @return void
     */
    public function websocketDispatch($appName, $request)
    {
        $wsRequest = new Request(Server::WEBSOCKET, $request);
        $wsResponse = new Response(Server::WEBSOCKET, null);
        $wsResponse->setFromfd($request->fd);
        $wsRequest->setData('__resp__', $wsResponse);

        try {
            $dispatch = App::make(Dispatch::class);

            $route = $dispatch->getRoute($appName, $request->server['request_uri'] ?? '', $request->server['request_method'] ?? "POST");
            $wsRequest->setHandler($route['handler']);
            foreach ($route['extra'] as $name => $val){
                $wsRequest->setData($name, $val);
            }
            $this->middleware($route['middleware'], function ($req){
                list($controller, $action) = $req->getHandler();
                $resp = $req->getData('__resp__');
                $data = App::make($controller)->$action($req, $resp);
                $resp->setData($data);
                return $resp;
            })($wsRequest);
        }catch (\Throwable $error){
            $data = App::make(WebsocketException::class)->handle($wsRequest, $error);
            $wsResponse->setcode(intval($error->getCode()));
            $wsResponse->setmsg($error->getMessage());
            $wsResponse->setdata($data);
        }
        try {
            $wsResponse->send();
        }catch (\Throwable $error){}

        unset($wsResponse, $wsRequest);
    }

    /**
     * 桥接调度
     * @param string $appName
     * @param Request $request
     * @param \Swoole\Coroutine\Channel $servResponse
     * @return string|true
     */
    public function dispatchBridg(string $appName, Request $request, \Swoole\Coroutine\Channel &$servResponse){
        try {
            $dispatch = App::make(Dispatch::class);
            $route = $dispatch->getRoute($appName, $request->server('request_uri'), $request->server('request_method'));
            foreach ($route['extra'] as $name => $val){
                $request->setData($name, $val);
            }
            list($controller, $action) = $route['handler'];
            App::make($controller)->$action($request, $servResponse);
            return true;
        }catch (\Throwable $e){
            return $e->getMessage().PHP_EOL.$e->getFile().':'.$e->getLine().PHP_EOL.$e->getTraceAsString();
        }
    }

    /**
     * tcp调度
     * @param string $appName
     * @param Request $request
     * @param Response $servResponse
     * @return string|true
     */
    public function dispatchtTcp(string $appName, Request $request, Response $servResponse){

        try {
            $dispatch = App::make(Dispatch::class);
            $route = $dispatch->getRoute($appName, $request->server('request_uri'), $request->server('request_method'));
            foreach ($route['extra'] as $name => $val){
                $request->setData($name, $val);
            }
            list($controller, $action) = $route['handler'];
            App::make($controller)->$action($request, $servResponse);
            return true;
        }catch (\Throwable $e){
            return $e->getMessage().PHP_EOL.$e->getFile().':'.$e->getLine().PHP_EOL.$e->getTraceAsString();
        }
    }
}