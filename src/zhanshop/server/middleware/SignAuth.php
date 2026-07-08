<?php

namespace zhanshop\server\middleware;

use zhanshop\App;
use zhanshop\server\Request;

class SignAuth
{
    const SIGN_TTL = 300;

    /** @var array|null 签名密钥表缓存 */
    protected static ?array $secretKeys = null;
    /** @var string|null API 文档密码缓存 */
    protected static ?string $docPassword = null;

    /**
     * 签名认证
     */
    public function handle(Request $request, \Closure $next)
    {
        $uri = $request->server('request_uri') ?? '';
        if ($uri === '/v1/api.doc' || str_starts_with($uri, '/v1/api.doc/')) {
            return $next($request);
        }

        $error = $this->verifySignature($request);
        if ($error !== null) {
            $error = $this->verifyCookie($request);
        }
        if ($error !== null) {
            App::error()->setError($error, 400);
        }

        return $next($request);
    }

    /**
     * 验证签名头，通过返回 null，失败返回错误信息
     */
    protected function verifySignature(Request $request): ?string
    {
        $appId = $request->header('app-id', '');
        $appTime = (int)$request->header('app-time', '0');
        $appSign = $request->header('app-sign', '');

        if ($appId === '' || $appTime === 0 || $appSign === '') {
            return "签名不能为空";
        }

        if (abs(time() - $appTime) > self::SIGN_TTL) {
            return "签名已过期";
        }

        $appSigns = explode(":", $appSign, 2);
        $secretKey = $this->secretKeys()[$appSigns[0]] ?? '';
        if (count($appSigns) !== 2 || $secretKey === '') {
            return "无效签名";
        }

        $payload = $appId . $appTime . $request->server('request_uri') . $request->rawRequest()->getContent();
        $servSign = $appSigns[0] . ':' . base64_encode(hash_hmac("sha1", $payload, $secretKey, true));

        return hash_equals($servSign, $appSign) ? null : "签名错误";
    }

    /**
     * cookie认证限流
     * @var int
     */
    protected $cookieAuth = 0;

    /**
     * Cookie 降级认证，通过返回 null，失败返回错误信息
     */
    protected function verifyCookie(Request $request): ?string
    {
        $time = time();
        $authCode = $request->rawRequest()->cookie["APP-AUTH"] ?? '';
        if ($authCode === '') {
            return "签名为空或错误";
        }elseif ($this->cookieAuth >= $time){
            return "签名限流";
        }

        $accPass = base64_decode($authCode);
        $parts = explode(":", $accPass, 2);
        $request->rawRequest()->header['app-id'] = $parts[0] ?? '';
        $password = $parts[1] ?? '';

        $this->cookieAuth = $time;
        return $password === $this->docPassword() ? null : "签名错误";
    }

    /**
     * 获取签名密钥表（静态缓存，进程生命周期内仅加载一次）
     */
    protected function secretKeys(): array
    {
        return self::$secretKeys ??= App::config()->get("sign") ?? [];
    }

    /**
     * 获取 API 文档密码（静态缓存，进程生命周期内仅加载一次）
     */
    protected function docPassword(): string
    {
        return self::$docPassword ??= App::env()->get("DOC_PASSWORD", "123456");
    }
}