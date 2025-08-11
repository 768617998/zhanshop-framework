<?php
declare (strict_types = 1);

namespace zhanshop\server\route;

use zhanshop\App;
use zhanshop\server\Request;
use zhanshop\server\Route;

/**
 * 路由规则
 */
class Rule
{
    public $app;
    public $version;
    public $uri;
    public $method;
    public $handler;
    public $extra = [];
    public $middleware = [];
    public $cache = -1;

    public $single = false;

    public function __construct(string $method, string $uri, array $handler)
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->handler = $handler;
    }

    public function middleware(array $class){
        $this->middleware = $class;
        return $this;
    }

    public function extra(array $extra){
        $this->extra = $extra;
        return $this;
    }

    public function cache(int $second){
        $this->cache = $second;
        return $this;
    }

    public function __destruct()
    {
        if($this->single){
            App::make(Dispatch::class)->regRoute($this);
        }
    }
}
