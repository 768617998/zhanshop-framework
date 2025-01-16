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
            if(!in_array($appName, $apps)) App::error()->setError($appName."app无效", 401);
            $appTime = intval($request->header('app-time', 0));
            $appSign = $request->header('app-sign', "");
            if($appTime == false || $appSign == false || $appName == false){
                App::error()->setError("签名不能为空", 401);
            }
            $diff = $request->time() - $appTime;
            if($diff > 120 || $diff < -120){
                App::error()->setError("时间戳无效", 401);
            }

            $appSigns = explode(":", $appSign);
            $config = App::config()->get("app.app_secret");
            $secretKey = $config[$appSigns[0]] ?? '';
            if(isset($appSigns[1]) == false || $secretKey == false){
                App::error()->setError("签名不存在", 401);
            }
            $servSign = $appSigns[0].':'.base64_encode(hash_hmac("sha1", $appName.$appTime.$request->server('request_uri').$request->rawRequest()->getContent(), $secretKey, true));
            if($servSign != $appSign){
                App::error()->setError("签名参数错误", 401);
            }
        }
        return $next($request);
    }
}