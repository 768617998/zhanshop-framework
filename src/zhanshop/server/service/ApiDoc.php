<?php
// +----------------------------------------------------------------------
// | framework / ApiDoc.php    [ 2023/8/18 14:38 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\server\service;

use zhanshop\App;
use zhanshop\console\command\Server;
use zhanshop\Error;
use zhanshop\example\Jquery;
use zhanshop\example\Vue3;
use zhanshop\Helper;
use zhanshop\util\Annotations;
use zhanshop\server\Request;
use zhanshop\server\Response;
use zhanshop\server\route\Dispatch;

class ApiDoc
{
    protected $app = "";
    protected $password = "";
    protected $menuList = [];
    protected $versionList = [];

    public function __construct()
    {
        $this->password = App::env()->get("DOC_PASSWORD", "123456");
    }
    /**
     * 初始化
     * @param string $appName
     * @return void
     * @throws \Exception
     */
    public function init(string $app){
        if(($_SERVER['APP_ENV'] ?? 'dev') == 'production') App::error()->setError('访问的接口不存在', Error::NOT_FOUND);

        $this->app = $app;
        $menuFile = App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$app.'-menu.json';
        if(!file_exists($menuFile)){
            App::error()->setError('apiDoc菜单文件还没有生成', Error::NOT_FOUND);
        }
        $this->menuList = json_decode(file_get_contents($menuFile), true);
        foreach ($this->menuList as $k => $v){
            if($v['name'] == false && $v['pid'] == 0){
                unset($this->menuList[$k]);
            }
        }
        $versionFile = App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$app.'-version.json';
        if(!file_exists($versionFile)){
            App::error()->setError('apiDoc版本文件还没有生成', Error::NOT_FOUND);
        }

        $this->versionList = json_decode(file_get_contents($versionFile), true);
    }

    /**
     * 获取菜单列表
     * @param Request $request
     * @param Response $response
     * @return array
     */
    public function get(Request &$request, Response &$response){
        return [
            'menu' => array_values($this->menuList),
            'user' => [
                "user_id" => 0,
                "user_name" => "开发者",
                "avatar" => "./images/profile.png"
            ]
        ];
    }

    /**
     * 认证
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    public function authorization(Request $request, ?Response $response = null)
    {
        $authCode = $request->header('authorization', "");
        if($authCode == false){
            return false;
        }
        $arr = explode(" ", $authCode);
        if(isset($arr[1]) == false){
            return false;
        }
        $accPass = base64_decode($arr[1]);
        $arr = explode(":", $accPass);
        $account = $arr[0];
        $password = $arr[1] ?? '';
        if($password == $this->password){
            if($response) $response->rawResponse()->setCookie("APP-NAME", $account, 0, '/');
            return true;
        }
        return false;
    }

    /**
     * 调用
     * @param Request $request
     * @param Response $response
     * @return array
     * @throws \Exception
     */
    public function call(Request $request, Response $response){
        if($this->authorization($request, $response) == false){
            $response->setcode(401);
            $response->rawResponse()->header("WWW-Authenticate", 'Basic realm="My Realm"');
            return "";
        }
        $app = $request->getData('_app');
        $this->init($app);
        $method = strtolower($request->method());
        try{
            $data = $this->$method($request, $response);
        }catch (\Throwable $err){
            $method = $request->param('__method');
            $data = $this->$method($request, $response);
        }

        return [
            'code' => 0,
            'msg' => 'ok',
            'data' => $data,
        ];
    }

    protected function getApiMethods($uri){
        $methods = array_keys($this->menuList[$uri]['methods'] ?? []);

        $standardMethods = ['GET', "POST", "PUT", "DELETE"];

        $_methods = [];
        foreach ($standardMethods as $k => $v){
            if(in_array($v, $methods)){
                $_methods[] = $v;
            }
        }

        foreach ($methods as $k => $v){
            if(!in_array($v, $_methods)){
                $_methods[] = $v;
            }
        }

        $data = [];
        foreach($methods as $v){
            $data[] = $this->menuList[$uri]['methods'][$v];
        }
        return $data;
    }

    /**
     * 获取api组 同uri不同版本不同方法的api归一组
     * @param Request $request
     * @param Response $response
     * @return array
     * @throws \ReflectionException
     */
    public function apis(Request &$request, Response &$response){
        $uri = $request->param('uri');
        // 已知的请求方法 排序方式为GET POST PUT DELETE
        $methods = $this->getApiMethods($uri);

        $apiDocs = [];

        foreach($methods as $v){
            $uris = explode('/', $v['uri']);
            $version = $uris[0];
            $fullUri = '/'.$uris[1];

            $route = App::make(Dispatch::class)->routes()[$this->app][$version][$fullUri][$v['method']] ?? App::error()->setError($request->param('uri').'路由未注册', Error::NOT_FOUND);
            //print_r($route);
            $handler = $route['handler'];
            $class = new \ReflectionClass($handler[0]);
            $method = $class->getMethod($handler[1]);
            $apiDoc = (new Annotations($method->getDocComment()))->all();
            $apiDocs[] = [
                'uri' => '/'.$version.'/'.explode('.', $uri)[0].'.'.$apiDoc['api']['uri'],
                'title' => $apiDoc['api']['title'],
                'description' => $apiDoc['apiDescription'],
                'method' => $v['method'],
                'header' => array_values($apiDoc['apiHeader']),
                'param' => array_values($apiDoc['apiParam']),
                'success' => array_values($apiDoc['apiSuccess']),
                'error' => $apiDoc['apiError'],
                'response' => $this->getExample('/'.$version.$fullUri, $v['method']), // 响应示例
                'version' => $version,
                'versions' => array_unique($this->versionList[$uri][$v['method']])
            ];
        }
        return $apiDocs;
    }

    /**
     * 拆解URI
     * @param $uri
     * @param $method
     * @return array
     */
    protected function parseUri($uri){
        if($uri == false) App::error()->setError('uri不能为空');
        $uri = ltrim($uri, '/');
        $uris = explode('/', $uri);
        $version = str_replace('_', '.', $uris[0]);
        $fullUri = '/'.$uris[1];
        return [
            $version,
            $fullUri
        ];
    }

    /**
     * 获取单个api
     * @param Request $request
     * @param Response $response
     * @return array
     */
    public function api(Request &$request, Response &$response){
        $uri = $request->param('uri');
        $method = $request->param('method');
        [$version, $fullUri] = $this->parseUri($uri);

        $route = App::make(Dispatch::class)->routes()[$this->app][$version][$fullUri][$method] ?? App::error()->setError($uri.'路由未注册', Error::NOT_FOUND);

        $handler = $route['handler'];
        $class = new \ReflectionClass($handler[0]);
        $apiDoc = (new Annotations($class->getMethod($handler[1])->getDocComment()))->all();
        return [
            'uri' => $uri,
            'title' => $apiDoc['api']['title'],
            'description' => $apiDoc['apiDescription'],
            'method' => $method,
            'header' => $apiDoc['apiHeader'],
            'param' => $apiDoc['apiParam'],
            'success' => $apiDoc['apiSuccess'],
            'error' => $apiDoc['apiError'],
            'response' => $this->getExample('/'.$version.$fullUri, $handler[1]), // 响应示例
        ];
    }



    public function exampleCode(Request &$request, Response &$response){
        $data = $request->validateRule([
            'host' => '',
            'header' => '',
            'uri' => 'required',
            'method' => 'required',
            'language' => 'required',
        ])->getData();
        [$version, $fullUri] = $this->parseUri($data['uri']);
        $route = App::make(Dispatch::class)->routes()[$this->app][$version][$fullUri][$data['method']] ?? App::error()->setError($data['uri'].'路由未注册', Error::NOT_FOUND);
        $handler = $route['handler'];
        $class = new \ReflectionClass($handler[0]);
        $apiDoc = (new Annotations($class->getMethod($handler[1])->getDocComment()))->all();
        switch ($data['language']){
            case "vue3":
                return (new Vue3($apiDoc))->getCode(($data['host'] ?? $request->header('origin')).$data['uri'], $data['method'], $data['header'] ?? []);
                break;
            case "jquery":
                return (new Jquery($apiDoc))->getCode(($data['host'] ?? $request->header('origin')).$data['uri'], $data['method'], $data['header'] ?? []);
                break;
                break;
            case "php":
                return (new Php($apiDoc))->getCode();
                break;
            case "curl":
                return (new Php($apiDoc))->getCode();
                break;
            case "java":
                return (new Php($apiDoc))->getCode();
                break;
            case "python":
                return (new Php($apiDoc))->getCode();
                break;
            case "go":
                return (new Php($apiDoc))->getCode();
                break;
            case "c":
                return (new Php($apiDoc))->getCode();
                break;
        }
        return null;
        $language = $data['language'];
        $apiDoc = App::make(ApiDocService::class)->detail($app, $data['protocol'], $data['uri'], $data['version'] ?? "", $data['method'])['detail'][0];
        $code = ApiSampleCode::$language($request->header('origin').'/'.$apiDoc['version'].'/'.$apiDoc['uri'], $apiDoc['method'], $apiDoc['header'] ?? [], $apiDoc['param'] ?? []);
        return $code;
    }

    public function success(Request &$request, Response &$response){
        $data = $request->validateRule([
            'uri' => 'required',
            'method' => 'required',
            'body' => 'required',
        ])->getData();

        $uris = explode('/', $data['uri']);
        if(count($uris) > 2){
            $data['uri'] = '/'.$uris[1].'/'.$uris[2];
        }

            App::make(Dispatch::class)->routes()[$this->app][$uris[1]]['/'.$uris[2]][$data['method']] ?? App::error()->setError($request->param('uri').'路由未注册', Error::NOT_FOUND);

        $filePath = App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$this->app.DIRECTORY_SEPARATOR.'debug'.$data['uri'].'.'.$data['method'];
        if(is_array($data['body'])){
            $data['body'] = json_encode($data['body'], JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
        }
        Helper::mkdirs(dirname($filePath));
        file_put_contents($filePath, $data['body']);
        return [];
    }

    public function failure(Request &$request, Response &$response){
        return [];
    }

    public function servStatus(Request &$request, Response &$response){
        return Server::$server->stats();
    }

    protected function getExample(string $uri, string $method){
        $file = App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$this->app.DIRECTORY_SEPARATOR.'debug'.$uri.'.'.$method;
        //var_dump($file);
        $data = [];
        if(file_exists($file)){
            $data = json_decode(file_get_contents($file), true);
            if($data == false) $data = [];
        }
        return $data;
    }
}