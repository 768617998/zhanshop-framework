<?php
// +----------------------------------------------------------------------
// | zhanshop-admin / Grpc.php    [ 2023/4/25 09:07 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\util;

use zhanshop\App;
use zhanshop\Error;

class Grpc
{
    /**
     * Serialize
     * @param \Google\Protobuf\Internal\Message $message
     * @return string
     */
    public static function serialize(\Google\Protobuf\Internal\Message $message): string
    {
        return static::pack($message->serializeToString());
    }

    /**
     * Deserialize
     * @param \Google\Protobuf\Internal\Message $message
     * @param string $data
     * @throws \Exception
     */
    public static function deserialize(\Google\Protobuf\Internal\Message $message, string $data)
    {
        $message->mergeFromString(static::unpack($data));
    }

    /**
     * Pack
     * @param string $data
     * @return string
     */
    public static function pack(string $data): string
    {
        return pack('CN', 0, strlen($data)) . $data;
    }

    /**
     * Unpack
     * @param string $data
     * @return string
     */
    public static function unpack(string $data): string
    {
        // it's the way to verify the package length
        // 1 + 4 + data
        // $len = unpack('N', substr($data, 1, 4))[1];
        // assert(strlen($data) - 5 === $len);
        return (string)substr($data, 5);
    }

    /**
     * 获取当前时间, 单位: 秒, 粒度: 微秒
     * @return float
     */
    public static function microtime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    public static function urlClass(string $uri){
        if($uri == false) App::error()->setError('请求参数错误', Error::FORBIDDEN);
        $parts = explode('.', $uri);
        $class = '';
        foreach($parts as $v){
            $class .= ucfirst($v).'\\';
        }
        $class = rtrim($class, '\\');
        return $class.'Service';
    }

    /**
     * 调用grpc
     * @param string $class
     * @param string $method
     * @param string $data
     * @return string
     * @throws \Exception
     */
    public static function callGrpc(string $class, string $method, string $data){
        if($method == false) App::error()->setError('请求方法错误', Error::FORBIDDEN);
        $method = lcfirst($method);
        $arr = App::route()->getGrpc($class, $method);
        $request = new $arr[0];
        self::deserialize($request, $data);
        $response = new $arr[1];
        App::make($class)->$method($request, $response);
        return self::serialize($response);
    }

    public static function getStatus(int $code){
        if($code < 200){
            return 500;
        }else if($code > 505){
            return 417;
        }else{
            return 200;
        }
    }
}