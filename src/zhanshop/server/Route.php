<?php
// +----------------------------------------------------------------------
// | framework / Route.php    [ 2021/10/30 10:08 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2022 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\server;

use zhanshop\App;
use zhanshop\server\route\Dispatch;
use zhanshop\server\route\Group;
use zhanshop\server\route\Rule;

class Route
{
    protected $rules = [];
    protected $group = null;
    public function rule(string $method, string $uri, array $handler) :Rule{
        $rule = new Rule($method, $uri, $handler);
        if($this->group){
            $this->rules[] = $rule;
        }else{
            $rule->single = true;
        }
        return $rule;
    }


    /**
     * 分组路由
     * @param string $name
     * @param callable $fun
     * @return void
     */
    public function group(string $name, callable $fun){
        $group = new Group($name, $fun);
        $this->group = $group;
        $group->execute();
        $this->group = null;
        $group->bindRoute($this->rules);
        $this->rules = [];
        return $group;
    }

    /**
     * 注册grpc服务
     * @param string $uri
     * @param string $class
     * @return void
     */
    public function grpc(string $uri, string $class){
        $reflectionClass = new \ReflectionClass($class);
        $methods = $reflectionClass->getMethods();
        foreach($methods as $v){
            $method = $v->getName();
            $parameters = $reflectionClass->getMethod($method)->getParameters();
            if(isset($parameters[0]) && $parameters[1]){
                $request = $parameters[0]->getType()->getName();
                $response = $parameters[1]->getType()->getName();
                App::make(Dispatch::class)->regGrpc($uri.'/'.$method, [$class, $method], [$request, $response]);
            }
        }
    }

    /**
     * 注册jsonRpc服务
     * @param string $uri
     * @param string $class
     * @return void
     */
    public function jsonRpc(string $uri, string $class){
        $reflectionClass = new \ReflectionClass($class);
        $methods = $reflectionClass->getMethods();
        foreach($methods as $v){
            $method = $v->getName();
            $parameters = $reflectionClass->getMethod($method)->getParameters();
            if(isset($parameters[0]) && $parameters[1]){
                $request = $parameters[0]->getType()->getName();
                $response = $parameters[1]->getType()->getName();
                App::make(Dispatch::class)->regJsonRpc($uri.'/'.$method, [$class, $method], [$request, $response]);
            }
        }
    }
}