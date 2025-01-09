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

use zhanshop\apidoc\ApiDocModel;
use zhanshop\apidoc\ApiDocService;
use zhanshop\App;
use zhanshop\console\Command;
use zhanshop\console\Input;
use zhanshop\console\Output;
use zhanshop\Helper;
use zhanshop\helper\Annotations;
use zhanshop\Log;

class ApiRoute extends Command
{

    public function configure()
    {
        $this->useDatabase()->setTitle('api注解生成路由')->setDescription('一键生成基于控制器配置的注解路由');
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
        $this->writeApiDoc();
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
                        $middlewares = '['.implode(', ', array_values($middlewares)).']';
                        $versionRouteCode .= "->middleware(".$middlewares.")";
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
}


