<?php

namespace zhanshop\middleware;

use zhanshop\App;
use zhanshop\Request;
use zhanshop\service\ApiDoc;

class SignAuth
{
    /**
     * 签名认证
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     * @throws \Exception
     */
    public function handle(Request $request, \Closure $next){
        if($request->server('request_uri') != '/v1/api.doc' && App::make(ApiDoc::class)->authorization($request) == false){
            $appName = $request->header('app-name', "");
            $apps = App::config()->get('app.app_list', []);
            if(!in_array($appName, $apps)) App::error()->setError($appName."无效", 401);
            $appTime = intval($request->header('app-time', 0));
            $appSign = $request->header('app-sign', "");
            if($appTime == false || $appSign == false || $appName == false){
                App::error()->setError("签名参数无效", 401);
            }
            $diff = $request->time() - $appTime;
            if($diff > 300 || $diff < -300){
                App::error()->setError("时间戳无效,当前服务器时间:".date('Y-m-d H:i:s', $request->time()));
            }

            $appSigns = explode(":", $appSign);
            $config = App::config()->get("app.app_secret");
            $secretKey = $config[$appSigns[0]];
            if(isset($appSigns[1]) == false || $secretKey == false){
                App::error()->setError("签名不存在", 401);
            }
            $servSign = $signs[0].':'.base64_encode(hash_hmac("sha1", $appName.$request->server('request_uri').$appTime, $secretKey, true));
            if($servSign != $appSign){
                App::error()->setError("签名参数无效", 401);
            }
        }
        return $next($request);
    }
}