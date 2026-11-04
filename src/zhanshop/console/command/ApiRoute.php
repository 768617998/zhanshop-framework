<?php
// +----------------------------------------------------------------------
// | flow-course / Help.php    [ 2021/10/28 2:26 下午 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2021 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console\command;

use zhanshop\App;
use zhanshop\console\Command;
use zhanshop\console\Input;
use zhanshop\console\Output;
use zhanshop\Helper;
use zhanshop\util\Annotations;
use zhanshop\Log;

class ApiRoute extends Command
{

    public function configure()
    {
        $this->setTitle('api注解生成路由')->setDescription('一键生成基于控制器配置的注解路由');
    }

    protected $versionRoutes = [];

    public function execute(Input $input, Output $output)
    {
        $apiDir = App::appPath() . DIRECTORY_SEPARATOR . 'api';
        $controllerFiles = glob($apiDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . '*.php');
        foreach ($controllerFiles as $k => $v) {
            $class = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], str_replace(App::rootPath(), '', $v));
            $this->generateClass($class);
        }
        $this->writeRoute();
        //$this->writeApiDoc();
        $this->writeOpenApi();
    }

    public function writeRoute(){
        foreach ($this->versionRoutes as $app => $versionRoutes){
            $routeDir = App::routePath().DIRECTORY_SEPARATOR.$app;
            Helper::mkdirs($routeDir);
            // 相同路由请求方式不一样的中间件必须一致
            foreach ($versionRoutes as $version => $groupRoute){
                $versionRouteCode = Helper::headComment($app.'/'.$version);
                $versionRouteCode .= 'use zhanshop\App;'.PHP_EOL.PHP_EOL;
                foreach($groupRoute as $group => $routes){
                    $versionRouteCode .= "App::route()->group(\"/{$group}\", function (){".PHP_EOL;
                    // 拿到当前分组的所有中间件
                    $middlewares = [];
                    foreach($routes as $route){
                        $middlewares = array_merge($middlewares, $route['apiMiddleware'] ?? []);
                    }
                    $middlewares = array_unique($middlewares); // 拿到当前分组的所有中间件

                    // 排除那些不在分组内都存在的中间件
                    foreach($middlewares as $mk => $middleware){
                        foreach($routes as $route){
                            if(!in_array($middleware, $route['apiMiddleware'])){
                                unset($middlewares[$mk]);
                            }
                        }
                    }

                    // 排除那些路由中的中间件在全局中的中间件
                    foreach($routes as $uri => $route){
                        foreach ($route['apiMiddleware'] as $mk => $middleware){
                            if(in_array($middleware, $middlewares)){
                                unset($routes[$uri]['apiMiddleware'][$mk]);
                            }
                        }
                    }

                    foreach($routes as $route){
                        $uri = explode('/', $route['api']['uri'])[0];
                        $class = $route['handler'][0];
                        $action = $route['handler'][1];
                        $versionRouteCode .= "      App::route()->rule(\"".$route['api']['method']."\", \"".$uri."\", [".$class."::class, \"".$action."\"])";
                        if($route['extra']){
                            $versionRouteCode .= '->extra('.json_encode($route['extra']).')';
                        }
                        if($route['apiMiddleware']){
                            $middleware = '['.implode(', ', array_values($route['apiMiddleware'])).']';
                            $versionRouteCode .= '->middleware('.$middleware.')';
                        }
                        $versionRouteCode .= ';'.PHP_EOL;
                    }

                    $versionRouteCode .= '})';
                    if($middlewares){
                        $middlewareStr = '['.implode(', ', array_values($middlewares)).']';
                        $versionRouteCode .= "->middleware(".$middlewareStr.")";
                    }
                    $versionRouteCode .= ';'.PHP_EOL.PHP_EOL; // 全局中间件加进去
                }
                file_put_contents($routeDir.DIRECTORY_SEPARATOR.str_replace('_', '.', $version).'.php', $versionRouteCode);
            }
        }
    }

    protected function generateClass(string $class)
    {
        $apiDocs = [];
        $reflection= new \ReflectionClass($class);
        foreach($reflection->getMethods() as $method){
            $apiDoc = $this->generateMethod($class, $method);
            if($apiDoc){
                $apiDocs[] = $apiDoc;
            }
        }

        if($apiDocs){
            $classPath = explode('\\', $class);
            $app = $classPath[3] ?? '';
            if($app == false){
                App::error()->setError($class.'中无法确定app');
            }
            $version = str_replace('_', '.', $classPath[4] ?? '');
            if($version == false){
                App::error()->setError($class.'中无法确定版本号');
                exit();
            }
            $prefix = lcfirst($classPath[count($classPath) - 1]);
            $this->versionRoutes[$app][$version][$prefix] = $apiDocs;
        }
    }

    /**
     * 生成控制器方法路由
     * @param $method
     * @return array|false
     */
    protected function generateMethod(string $class, mixed $method){
        if($method->getDocComment()){
            $apiDoc = (new Annotations($method->getDocComment()))->all();
            if($apiDoc['api']['method'] && $apiDoc['api']['uri'] && $apiDoc['api']['title']){
                $apiDoc['handler'] = [$class, $method->name];
                //var_dump([$method->class, $method->name]);
                $extra = explode('/', str_replace(['{', '}'], '', $apiDoc['api']['uri']));
                unset($extra[0]);
                $apiDoc['extra'] = array_values($extra);
                return $apiDoc;
            }
        }
        return [];
    }


    // 生成apiDoc
    public function writeApiDoc(){
        foreach($this->versionRoutes as $appName => $appRoutes){
            $menus = [];
            $versions = [];
            foreach($appRoutes as $version => $controllers){
                foreach($controllers as $controllerName => $controllerRoutes){
                    foreach($controllerRoutes as $route){
                        $uri = explode('/', $route['api']['uri'])[0];
                        $menus[md5($route['apiGroup'])] = [
                            'id' => md5($route['apiGroup']),
                            'name' => $route['apiGroup'],
                            'pid' => 0,
                            'icon' => 'mdi mdi-file-word',
                            'url' => '',
                            'target' => '_self',
                        ];
                        $menuId = $controllerName.'.'.$uri;
                        $versions[$menuId][$route['api']['method']][] = $version;

                        $menusMethods = $menus[$menuId]['methods'] ?? [];

                        $menuTitle = $menus[$menuId]['name'] ?? $route['api']['title'];

                        $menus[$menuId] = [
                            'id' => $menuId,
                            'name' => $menuTitle,
                            'pid' => md5($route['apiGroup']),
                            'icon' => '',
                            'url' => 'apis/'.$controllerName.'.'.$uri,
                            'target' => 'api',
                        ];


                        $menusMethods[$route['api']['method']] = [
                            'method' => $route['api']['method'],
                            'uri' => $version.'/'.$controllerName.'.'.$uri
                        ];
                        $menus[$menuId]['methods'] = $menusMethods;
                    }
                }
            }
            // 版本详情
            //$this->writeApiDocFile(App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$appName.'-'.$version.'-detail.json', $paths);
            // app菜单
            $this->writeApiDocFile(App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$appName.'-menu.json', $menus);
            $this->writeApiDocFile(App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$appName.'-version.json', $versions);
            //、、print_r($menus);die;

        }
        // 每个APP一个文件
    }

    // 如果把多版本和多请求方法放在一起
    // 切换版本 某一个方法只有老版本
    // 走最新版本
    // 但是走分享的时候又需要单独处理

    protected function writeApiDocFile(string $path, array $data){
        Helper::mkdirs(dirname($path));
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE));
    }

    // ==================== OpenAPI 3.1 生成 ====================

    public function writeOpenApi(){
        foreach($this->versionRoutes as $appName => $appRoutes){
            $allVersions = array_keys($appRoutes);
            $latestVersion = end($allVersions);

            $port = App::env()->get(strtoupper($appName).'_PORT');
            if(!$port){
                // 从 config/server.php 中匹配对应 app 的端口
                foreach(App::config()->get('server.servers', []) as $svr){
                    if(($svr['name'] ?? '') === $appName){
                        $port = $svr['port'] ?? '7201';
                        break;
                    }
                }
                $port = $port ?: '7201';
            }
            $host = App::env()->get('API_HOST', '127.0.0.1');
            $schema = ($_SERVER['APP_ENV'] ?? 'dev') === 'production' ? 'https' : 'http';

            $openapi = [
                'openapi' => '3.1.0',
                'info' => [
                    'title' => $appName,
                    'version' => $latestVersion,
                    'description' => 'API Documentation for '.$appName,
                ],
                'servers' => [
                    ['url' => $schema.'://'.$host.':'.$port, 'description' => $appName],
                ],
                'tags' => [],
                'paths' => [],
                'components' => [
                    'securitySchemes' => [
                        'BasicAuth' => [
                            'type' => 'http',
                            'scheme' => 'basic',
                        ],
                        'BearerAuth' => [
                            'type' => 'http',
                            'scheme' => 'bearer',
                            'bearerFormat' => 'token',
                        ],
                    ],
                ],
            ];

            $tags = [];
            foreach($appRoutes as $version => $controllers){
                foreach($controllers as $controllerName => $controllerRoutes){
                    foreach($controllerRoutes as $route){
                        $method = strtolower($route['api']['method']);
                        if(!in_array($method, ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'])){
                            continue;
                        }

                        $gTag = $route['apiGroup'] ?: 'Default';
                        if(!isset($tags[$gTag])){
                            $tags[$gTag] = ['name' => $gTag, 'description' => ''];
                        }

                        $uri = '/'.$version.'/'.$controllerName.'.'.$route['api']['uri'];
                        $pathParams = $route['extra'];

                        $hasToken = false;
                        foreach($route['apiHeader'] ?? [] as $h){
                            if(strtolower($h['name']) === 'token'){
                                $hasToken = true;
                                break;
                            }
                        }

                        // operationId: {controller}_{method}_{version} 确保多版本唯一
                        $baseUri = explode('/', str_replace(['{', '}'], '', $route['api']['uri']))[0];
                        $operationId = $controllerName.'_'.$baseUri.'_'.$method.'_'.$version;

                        $pathItem = $this->buildPathItem($route, $pathParams, $method, $hasToken, $gTag);
                        $pathItem['operationId'] = $operationId;

                        $openapi['paths'][$uri][$method] = $pathItem;
                    }
                }
            }

            $openapi['tags'] = array_values($tags);

            $outputPath = App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$appName.'.json';
            $this->writeApiDocFile($outputPath, $openapi);
        }
    }

    protected function buildPathItem(array $route, array $pathParams, string $method, bool $hasToken, string $tag): array {
        $item = [
            'tags' => [$tag],
            'summary' => $route['api']['title'] ?? '',
            'description' => $route['apiDescription'] ?? '',
            'responses' => $this->buildResponses($route),
        ];

        // security
        if($hasToken){
            $item['security'] = [['BearerAuth' => []]];
        }

        // parameters
        $parameters = $this->buildParameters($route, $pathParams);
        if($parameters){
            $item['parameters'] = $parameters;
        }

        // requestBody (POST/PUT/PATCH)
        if(in_array(strtoupper($route['api']['method']), ['POST', 'PUT', 'PATCH'])){
            $bodyParams = $this->filterBodyParams($route['apiParam'] ?? [], $pathParams);
            if($bodyParams){
                $item['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => $this->paramsToSchema($bodyParams),
                        ],
                    ],
                ];
            }
        }

        return $item;
    }

    /**
     * 过滤出 body 参数（排除路径参数和header参数）
     */
    protected function filterBodyParams(array $params, array $pathParams): array {
        $body = [];
        foreach($params as $p){
            if(!in_array($p['name'], $pathParams)){
                $body[] = $p;
            }
        }
        return $body;
    }

    /**
     * 构建 parameters 数组（query + path + header）
     */
    protected function buildParameters(array $route, array $pathParams): array {
        $parameters = [];
        $usedInBody = in_array(strtoupper($route['api']['method']), ['POST', 'PUT', 'PATCH']);
        $bodyParamNames = $usedInBody ? array_column($this->filterBodyParams($route['apiParam'] ?? [], $pathParams), 'name') : [];

        // path params
        foreach($pathParams as $name){
            $paramData = $this->findParam($route['apiParam'] ?? [], $name);
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'description' => $paramData['description'] ?? '',
                'schema' => $this->typeToSchema($paramData['type'] ?? 'string'),
            ];
        }

        // query params (only for GET/DELETE, or for POST params not in body)
        foreach($route['apiParam'] ?? [] as $p){
            if(in_array($p['name'], $pathParams)) continue;
            if($usedInBody && in_array($p['name'], $bodyParamNames)) continue;
            $parameters[] = [
                'name' => $p['name'],
                'in' => 'query',
                'required' => $p['required'] === 'true',
                'description' => $p['description'] ?? '',
                'schema' => $this->typeToSchema($p['type'] ?? 'string'),
            ];
        }

        // header params
        foreach($route['apiHeader'] ?? [] as $h){
            $parameters[] = [
                'name' => $h['name'],
                'in' => 'header',
                'required' => $h['required'] === 'true',
                'description' => $h['description'] ?? '',
                'schema' => $this->typeToSchema($h['type'] ?? 'string'),
            ];
        }

        return $parameters;
    }

    /**
     * 构建 responses
     */
    protected function buildResponses(array $route): array {
        $responses = [];

        // 200 success
        $successSchema = ['type' => 'object', 'properties' => []];
        if(isset($route['apiSuccess']) && $route['apiSuccess']){
            $successSchema = $this->paramsToSchema($route['apiSuccess']);
        }
        $responses['200'] = [
            'description' => '成功',
            'content' => [
                'application/json' => [
                    'schema' => $this->wrapStandardResponse($successSchema),
                ],
            ],
        ];

        // error responses
        foreach($route['apiError'] ?? [] as $err){
            $code = (string)($err['code'] ?? '500');
            if(!$code) continue;
            $responses[$code] = [
                'description' => $err['description'] ?? 'Error',
            ];
        }

        // 默认 401
        if(!isset($responses['401'])){
            $responses['401'] = ['description' => '未认证'];
        }

        return $responses;
    }

    /**
     * 将注解参数列表转为 OpenAPI Schema
     */
    protected function paramsToSchema(array $params): array {
        $schema = ['type' => 'object'];
        if(!$params) return $schema;

        $properties = [];
        $required = [];
        foreach($params as $p){
            $prop = $this->typeToSchema($p['type'] ?? 'string');
            if(!empty($p['description'])){
                $prop['description'] = $p['description'];
            }
            if(isset($p['default']) && $p['default'] !== null){
                $prop['default'] = $p['default'] === 'true' ? true :
                    ($p['default'] === 'false' ? false :
                    (is_numeric($p['default']) ? (int)$p['default'] : $p['default']));
            }
            // nested children
            if(!empty($p['children'])){
                $childSchema = $this->paramsToSchema($p['children']);
                if($p['type'] === 'array'){
                    $prop['type'] = 'array';
                    $prop['items'] = $childSchema;
                }else{
                    $prop = array_merge($prop, $childSchema);
                }
            }
            $properties[$p['name']] = $prop;
            if($p['required'] === 'true'){
                $required[] = $p['name'];
            }
        }

        $schema['properties'] = $properties;
        if($required){
            $schema['required'] = $required;
        }
        return $schema;
    }

    /**
     * 包装标准响应格式 {code, msg, data}
     */
    protected function wrapStandardResponse(array $dataSchema): array {
        return [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'integer', 'example' => 0],
                'msg' => ['type' => 'string', 'example' => 'ok'],
                'data' => $dataSchema,
            ],
        ];
    }

    /**
     * 注解类型 → OpenAPI schema 类型
     */
    protected function typeToSchema(string $type): array {
        return match (strtolower($type)) {
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double', 'number' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']],
            'object' => ['type' => 'object'],
            default => ['type' => 'string'],
        };
    }

    /**
     * 在参数列表中查找指定名称的参数
     */
    protected function findParam(array $params, string $name): array {
        foreach($params as $p){
            if($p['name'] === $name) return $p;
        }
        return [];
    }
}


