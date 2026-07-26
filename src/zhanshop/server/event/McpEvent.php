<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / McpServEvent.php    [ 2025/8/7 09:47 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\server\event;

use app\exception\HttpException;
use app\exception\McpException;
use stdClass;
use Swoole\Http\Request;
use Swoole\Http\Response;
use zhanshop\App;
use zhanshop\console\command\Server;
use zhanshop\Error;
use zhanshop\server\WebHandle;
use zhanshop\util\Annotations;
use zhanshop\server\route\Dispatch;

class McpEvent
{
    protected $mcpVersion = '3.1.0';
    /**
     * 获取输入参数
     * @param Request $request
     * @param Response $response
     * @return array
     */
    protected function getInputParam($request, $response): array|false|null
    {
        $content = $request->getContent();
        if($content && is_string($content)){
            $content = json_decode($content, true);
        }
        if(($content === false || $content === null) && ($request->server['request_method'] ?? 'POST') == "POST"){
            $response->header('Content-Type', 'text/html; charset=utf-8');
            $response->status(400);
            $response->end('400: 输入参数无效');
        }
        return $content;
    }
    /**
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function onRequest($request, $response) :void{
        $param = $this->getInputParam($request, $response);
        if($param === false || $param === null) return; // 参数无效，response 已在 getInputParam 中 end
        if($param){
            $request->post = $param['params']['arguments'] ?? [];
            unset($param['params']['arguments']);
            $request->header['mcp-input'] = $param;
        }
        $method = str_replace('/', '_', $param['method'] ?? 'unknown');
        $response->header('Content-Type', 'text/event-stream');
        try {
            $this->$method($request, $response);
        }catch (\Throwable $exception){
            $httpCode = intval($exception->getCode());
            if($httpCode < 200 || $httpCode > 500){
                $response->status(417);
            }else{
                $response->status($httpCode);
            }
            $version = $param['jsonrpc'] ?? '2.0';
            $id = $param['id'] ?? 0;
            $message = "event: message\r\n";
            $message .= 'data: '.'{"jsonrpc":"'.$version.'","id":'.$id.',"error":{"code":-32601,"message":"'.$exception->getMessage().'"}}'."\r\n\r\n";
            $response->write($message);
        }
    }

    /**
     * 初始化响应
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function initialize($request, $response)
    {
        $id = $request->header['mcp-input']['id'] ?? 0;
        $jsonrpc = $request->header['mcp-input']['jsonrpc'] ?? '2.0';
        $protocolVersion = $request->header['mcp-input']['params']['protocolVersion'] ?? '2025-11-25';
        $message = "event: message\r\n";
        $message .= 'data: {"jsonrpc":"'.$jsonrpc.'","id":'.$id.',"result":{"protocolVersion":"'.$protocolVersion.'","capabilities":{"experimental":{},"tools":{"listChanged":false}},"serverInfo":{"name":"zhanshop-mcp","version":"'.$this->mcpVersion.'"}}}'."\r\n\r\n";
        $session = ($request->server['remote_addr'] ?? '127.0.0.1').json_encode($request->header['mcp-input']).microtime(true);
        $response->header('mcp-session-id', md5($session));
        $response->write($message);
    }
    /**
     * 初始化响应
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function notifications_initialized($request, $response)
    {
        $sessionId = $request->header['mcp-session-id'] ?? '';
        $response->status(202);
        $response->header('mcp-session-id', $sessionId);
        $response->header('Content-Type', 'application/json');
        $response->end('');
    }
    /**
     * 初始化响应
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function tools_list($request, $response)
    {
        $servName = str_replace('/', '', $request->server['request_uri'] ?? '/mcp/');
        $id = $request->header['mcp-input']['id'] ?? 0;
        $jsonrpc = $request->header['mcp-input']['jsonrpc'] ?? '2.0';
        $sessionId = $request->header['mcp-session-id'] ?? '';
        $response->header('mcp-session-id', $sessionId);
        $response->header('Content-Type', 'text/event-stream');
        $message = "event: message\r\ndata: ";

        $tools = $this->getTools($servName);
        $result = [
            'jsonrpc' => $jsonrpc,
            'id' => $id,
            'result' => [
                'tools' => $tools
            ]
        ];
        $message .= json_encode($result, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE)."\r\n\r\n";
        $response->write($message);
    }

    /**
     * 提示词列表
     * @param $request
     * @param $response
     * @return void
     */
    public function prompts_list($request, $response)
    {
        $servName = str_replace('/', '', $request->server['request_uri'] ?? '/mcp/');
        $id = $request->header['mcp-input']['id'] ?? 0;
        $jsonrpc = $request->header['mcp-input']['jsonrpc'] ?? '2.0';
        $sessionId = $request->header['mcp-session-id'] ?? '';
        $response->header('mcp-session-id', $sessionId);
        $response->header('Content-Type', 'text/event-stream');

        $prompts = $this->getPrompts($servName);


        $message = "event: message\r\ndata: ";
        $result = [
            'jsonrpc' => $jsonrpc,
            'id' => $id,
            'result' => [
                'prompts' => $prompts
            ]
        ];
        $message .= json_encode($result, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE)."\r\n\r\n";
        $response->write($message);
    }

    /**
     * 心跳
     * @param $request
     * @param $response
     * @return void
     */
    public function ping($request, $response)
    {
        $id = $request->header['mcp-input']['id'] ?? 0;
        $jsonrpc = $request->header['mcp-input']['jsonrpc'] ?? '2.0';
        $sessionId = $request->header['mcp-session-id'] ?? '';
        $response->header('mcp-session-id', $sessionId);
        $response->header('Content-Type', 'text/event-stream');

        $message = "event: message\r\ndata: ";
        $message .= '{"jsonrpc":"'.$jsonrpc.'","id":'.$id.',"result":{}}'."\r\n\r\n";
        $response->write($message);
    }
    /**
     * 取消请求
     * @param $request
     * @param $response
     * @return void
     */
    public function notifications_cancelled($request, $response)
    {
        $id = $request->header['mcp-input']['id'] ?? 0;
        $jsonrpc = $request->header['mcp-input']['jsonrpc'] ?? '2.0';
        $sessionId = $request->header['mcp-session-id'] ?? '';
        $response->header('mcp-session-id', $sessionId);
        $response->header('Content-Type', 'text/event-stream');

        $message = "event: message\r\ndata: ";
        $message .= '{"jsonrpc":"'.$jsonrpc.'","id":'.$id.',"result":{}}'."\r\n\r\n";
        $response->write($message);
    }
    /**
     * 资源列表
     * @param $request
     * @param $response
     * @return void
     */
    public function resources_list($request, $response)
    {
        $id = $request->header['mcp-input']['id'] ?? 0;
        $jsonrpc = $request->header['mcp-input']['jsonrpc'] ?? '2.0';
        $sessionId = $request->header['mcp-session-id'] ?? '';
        $response->header('mcp-session-id', $sessionId);
        $response->header('Content-Type', 'text/event-stream');

        $resources = [];

        $message = "event: message\r\ndata: ";
        $result = [
            'jsonrpc' => $jsonrpc,
            'id' => $id,
            'result' => [
                'resources' => $resources
            ]
        ];
        $message .= json_encode($result, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE)."\r\n\r\n";
        $response->write($message);
    }

    /**
     * 工具调用
     * @param $request
     * @param $response
     * @return void
     */
    public function tools_call($request, $response)
    {
        $servName = str_replace('/', '', $request->server['request_uri'] ?? '/mcp/');
        $request->header['content-type'] = 'application/x-www-form-urlencoded';
        $uris = explode(':', ($request->header['mcp-input']['params']['name'] ?? ''));
        $request->server['request_uri'] = '/'.($uris[0] ?? '');
        $request->server['request_method'] = $uris[1] ?? 'POST';
        $this->httpDispatch($servName, $request, $response, Server::HTTP_MCP);
    }

    /**
     * http调度
     * @param $appName
     * @param $request
     * @param $response
     * @param int $protocol
     * @return void
     */
    private function httpDispatch($appName, $request, $response, int $protocol = Server::HTTP)
    {
        $httpRequest = new \zhanshop\server\Request($protocol, $request);
        $httpResponse = new \zhanshop\server\Response($protocol, $response);
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
            App::make(WebHandle::class)->middleware($route['middleware'], function ($req){
                list($controller, $action) = $req->getHandler();
                $resp = $req->getData('__resp__');
                $data = App::make($controller)->$action($req, $resp);
                $resp->setData($data);
                return $resp;
            })($httpRequest);
        }catch (\Throwable $error){
            $data = App::make(McpException::class)->handle($httpRequest, $error);
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
     * 方法未定义的响应
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function unknown($request, $response)
    {
        $sessionId = $request->header['mcp-session-id'] ?? '';
        if($sessionId){
            $response->write(' ');
            return;
        }
        App::error()->setError('method参数 不能为空', 400);
    }

    public function getTools(string $appName)
    {
        $menuFile = App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$appName.'-menu.json';
        if(!file_exists($menuFile)){
            App::error()->setError('工具文档还没有生成', Error::NOT_FOUND);
        }
        $menus = json_decode(file_get_contents($menuFile), true);
        $tools = [];
        foreach ($menus as $k => $v){
            $name = $v['name'];
            if(isset($v['methods']) == false) continue;
            $methodCount = count($v['methods']);
            foreach ($v['methods'] as $method){
                $uris = explode('/', $method['uri']);
                $schema = $this->apiSchema($appName, $uris[0], $uris[1], $method['method']);
                $tools[] = [
                    'name' => $method['uri'].':'.$method['method'],
                    'description' => $schema['description'],
                    'inputSchema' => $schema['inputSchema'],
                    'outputSchema' => $schema['outputSchema'],
                ];
            }
        }
        return $tools;
    }

    public function getPrompts(string $appName)
    {
        $menuFile = App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$appName.'-menu.json';
        if(!file_exists($menuFile)){
            App::error()->setError('工具文档还没有生成', Error::NOT_FOUND);
        }
        $menus = json_decode(file_get_contents($menuFile), true);
        $tools = [];
        foreach ($menus as $k => $v){
            $name = $v['name'];
            if(isset($v['methods']) == false) continue;
            $methodCount = count($v['methods']);
            foreach ($v['methods'] as $method){
                $uris = explode('/', $method['uri']);
                $schema = $this->apiSchema($appName, $uris[0], $uris[1], $method['method']);
                $tools[] = [
                    'name' => $method['uri'].':'.$method['method'],
                    'description' => $schema['prompt'],
                    'arguments' => $schema['promptSchema']['arguments'],
                ];
            }
        }
        return $tools;
    }

    /**
     * @param string $appName
     * @param string $version
     * @param string $uri
     * @param string $method
     * @return void
     */
    protected function apiSchema(string $appName, string $version, string $uri, string $method)
    {
        $route = App::make(Dispatch::class)->routes()[$appName][$version]['/'.$uri][$method] ?? App::error()->setError($uri.'路由未注册', Error::NOT_FOUND);;
        $handler = $route['handler'];
        $class = new \ReflectionClass($handler[0]);
        $apiDoc = (new Annotations($class->getMethod($handler[1])->getDocComment()))->all();

        $description = $apiDoc['api']['title'];
        $inputSchema = ['properties' => [], 'required' => [], 'type' => 'object'];
        $promptSchema = ['arguments' => []];
        foreach ($apiDoc['apiParam'] as $k => $v){
            $description .= "\n:param ".$v['name'].": ".$v['description'];
            $promptArgument = ['name' => $v['name'], 'required' => false];
            if($v['required'] == 'true'){
                $inputSchema['required'][] = $v['name'];
                $promptArgument['required'] = true;
            }
            $promptSchema['arguments'][] =  $promptArgument;
            $inputSchema['properties'][$v['name']] = [
                'title' => $v['description'],
                'type' => $v['type'],
            ];
        }
        $description .= "\n:return: 返回结果";

        $outputSchema = ['properties' => [], 'required' => [], 'type' => 'object'];
        foreach ($apiDoc['apiSuccess'] as $k => $v){
            if($v['required'] == 'true') $outputSchema['required'][] = $v['name'];
            $outputSchema['properties'][$v['name']] = [
                'title' => $v['description'],
                'type' => $v['type'],
            ];
        }

        if($outputSchema['properties'] == false) $outputSchema['properties'] = new StdClass();

        $apiSchema = [
            'name' => $version.'/'.$uri.':'.$method,
            'title' => $apiDoc['api']['title'],
            'description' => $description,
            'prompt' => $apiDoc['apiDescription'],
            'method' => $method,
            'header' => $apiDoc['apiHeader'],
            'inputSchema' => $inputSchema,
            'outputSchema' => $outputSchema,
            'promptSchema' => $promptSchema,
            'error' => $apiDoc['apiError'],
        ];
        return $apiSchema;
    }

    /**
     * 不存在的方法
     * @param string $name
     * @param array $arguments
     * @return void
     * @throws \Exception
     */
    public function __call(string $name, array $arguments)
    {
        App::error()->setError($name.' 方法不存在', 404);
    }
}