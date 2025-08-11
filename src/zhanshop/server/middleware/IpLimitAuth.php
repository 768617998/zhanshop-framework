<?php

namespace zhanshop\server\middleware;

use zhanshop\App;
use zhanshop\util\Limit;
use zhanshop\server\Request;

class IpLimitAuth
{
    protected $triggerNum = 20; // 限制执行时间内上限此书
    protected $expireTime = 300; // 有效时间
    public function __construct()
    {
        $this->triggerNum = intval(App::env()->get("LIMIT_IP_TRIGGER", "20"));
        $this->expireTime = intval(App::env()->get("LIMIT_EXPIRE_TIME", "600"));
    }
    /**
     * IP限流量认证
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     * @throws \Exception
     */
    public function handle(Request $request, \Closure $next){
        $key = md5($request->realIp()).$request->server("request_uri", "").':'.$request->server("request_method");
        $limit = App::make(Limit::class);
        if($limit->isPass($key, $this->expireTime, $this->triggerNum) == false){
            App::error()->setError('触发IP限流', 429);
        }
        return $next($request);
    }
}