<?php
// +----------------------------------------------------------------------
// | zhanshop-device / TaskManager.php    [ 2024/1/19 下午5:13 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console;

class TaskManager
{
    /**
     * server对象
     * @var Swoole\Server
     */
    protected $server;
    public function __construct(mixed &$server)
    {
        $this->server = $server;
    }
    /**
     * 获取server对象
     * @return \Swoole\Server
     */
    public function getServer(){
        return $this->server;
    }
    /**
     * 投递task任务
     * @param string $class
     * @param ...$value
     * @return void
     */
    public function callback(string $class, ...$value){
        $this->server->task([
            'class' => $class,
            'value' => $value
        ]);
    }

    /**
     * 发送http数据
     * @param int $fd
     * @param string $data
     * @return void
     */
    public function sendHttp(int $fd, mixed $data)
    {
        $body = "HTTP/1.1 200 OK\r\n";
        $body .= "Access-Control-Allow-Origin: *\r\n";
        $body .= "Access-Control-Allow-Headers: *\r\n";
        $body .= "Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE\r\n";
        $body .= "Access-Control-Max-Age: 3600\r\n";
        $body .= "Server: zhanshop\r\n";
        $body .= "Connection: keep-alive\r\n";
        if(is_array($data) || is_object($data)){
            $body .= "Content-Type: application/json; charset=utf-8\r\n";
            $data = json_encode($data);
        }
        $body .= "Content-Length: ".strlen($data)."\r\n";
        $body .= "\r\n";
        $body .= $data;
        $this->server->send($fd, $body);
    }
}