<?php
// +----------------------------------------------------------------------
// | framework / ErrorException.php    [ 2021/10/29 11:29 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2022 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

use zhanshop\console\command\Server;

class Error extends \Error
{
    const OK = 0;
    const BAD_REQUEST = 400;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const SERVER_ERROR = 500;

    /**
     * 设置错误信息
     * @param int $code
     * @param string $msg
     */
    public function setError(string $msg, int $code = 500){
        throw new \Exception($msg, $code);
    }

    /**
     * 断言
     * @param mixed $condition
     * @param string $message
     * @param int $code
     * @return void
     */
    public function assert(mixed $condition, string $msg, int $code = 500){
        if($condition == false){
            $this->setError($msg, $code);
        }
    }

    /**
     * 系统异常处理
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     */
    public static function errorHandler(int $errno,  $errstr,  $errfile,  $errline){
        throw new \Exception($errstr.' '.$errfile.':'.$errline.PHP_EOL, $errno);
    }

    /**
     * 用户定义的错误
     * @param \Throwable $exception
     * @return void
     */
    public static function exceptionHandler(\Throwable $exception){
        Log::errorLog(5, $exception->getMessage().PHP_EOL.
            $exception->getFile().':'.$exception->getLine().PHP_EOL.
            $exception->getTraceAsString()
        );
    }

    /**
     * Server运行期致命错误
     * @return void
     */
    public static function shutdown(){
        // 无法提前捕获的错误
        $error = error_get_last();
        if(isset($error['message'])){
            Log::errorLog(5, "发生致命错误: ".$error['message']);
            $server = Server::$server;
            if($server) $server->reload();
        }

    }

}