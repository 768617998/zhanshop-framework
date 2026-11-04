<?php
// +----------------------------------------------------------------------
// | zhanshop-admin / Dispatch.php    [ 2023/4/29 15:04 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace zhanshop\server\route;

use zhanshop\App;
use zhanshop\Error;
use zhanshop\server\Request;
use zhanshop\server\Response;

/**
 * 路由调度基础类
 */
class Dispatch
{
    protected $app;
    protected $version;

    // 已注册的路由
    protected $routes = [];

    public function regRoute(Rule $rule){
        $rule->middleware = array_reverse(array_merge($rule->middleware, App::config()->get('middleware.'.$this->app, [])));
        $middlewares = [];
        foreach($rule->middleware as $middleware){
            $middlewares[] = function ($request, \Closure $next) use ($middleware){
                return App::make($middleware)->handle($request, $next);
            };
        }
        $rule->middleware = $middlewares;

        $this->routes[$this->app][$this->version][$rule->uri][$rule->method] = [
            'cache' => $rule->cache,
            'extra' => $rule->extra,
            'handler' => $rule->handler,
            'middleware' => $rule->middleware,
        ];
    }

    public function setApp(string $app){
        $this->app = $app;
    }

    public function setVersion(string $version){
        $this->version = $version;
    }

    /**
     * 获取路由
     * @param string $name
     * @param string $uri
     * @param string $method
     * @return mixed
     * @throws \Exception
     */
    public function getRoute(string $name, string $uri, string $method = "GET"){
        // 检查路由是否存在
        $params = explode("/", $uri);
        $version = $params[1] ? $params[1] : 'v1';
        $uri     = isset($params[2]) ? '/'.$params[2] : '/index.index';
        $route = $this->routes[$name][$version][$uri][$method] ?? App::error()->setError('您所访问的资源不存在', Error::NOT_FOUND);
        $extra = [];
        foreach ($route['extra'] as $k => $v){
            $extra[$v] = $params[$k + 3] ?? null;
        }
        $route['extra'] = $extra;
        return $route;
    }

    /**
     * 执行路由调度
     * @param string $name
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $servResponse
     * @return mixed
     */
    public function run(string &$name, Request $request, Response $servResponse){
        $roure = $request->getRoure();
        $action = $roure['handler'][1];
        $controller = $roure['handler'][0];
        // 在台式机上测试性能strtolower
        return App::make($controller)->$action($request, $servResponse);
    }

    public function routes(){
        return $this->routes;
    }

    protected $grpcs = [];

    /**
     * 挂载grpc路由
     * @param string $uri
     * @param array $handler
     * @param array $param
     * @return void
     */
    public function regGrpc(string $uri, array $handler, array $param){
        $this->grpcs[$uri] = [
            'handler' => $handler,
            'param' => $param
        ];
    }

    /**
     * 获取grpc路由
     * @param string $uri
     * @return mixed
     * @throws \Exception
     */
    public function getGrpc(string $uri){
        return $this->grpcs[$uri] ?? App::error()->setError('您所访问的grpc服务不存在', Error::NOT_FOUND);
    }

    protected $jsonRpcs = [];

    /**
     * 挂载jsonRpc路由
     * @param string $uri
     * @param array $handler
     * @param array $param
     * @return void
     */
    public function regJsonRpc(string $uri, array $handler, array $param){
        $this->jsonRpcs[$uri] = [
            'handler' => $handler,
            'param' => $param
        ];
    }

    /**
     * 获取jsonRpc路由
     * @param string $uri
     * @return mixed
     * @throws \Exception
     */
    public function getJsonRpc(string $uri){
        return $this->jsonRpcs[$uri] ?? App::error()->setError('您所访问的JsonRpc服务不存在', Error::NOT_FOUND);
    }
}
