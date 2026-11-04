<?php
// +----------------------------------------------------------------------
// | zhanshop_admin / Response.php [ 2023/4/16 下午3:52 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\server;

use zhanshop\App;
use zhanshop\console\command\Server;

/**
 * @mixin \Swoole\Http\Response
 */
class Response
{
    /**
     * 协议
     * @var int
     */
    protected $protocol = Server::HTTP;

    /**
     * 原始响应对象
     * @var \Swoole\Http\Request
     */
    protected mixed $rawResponse = null;

    protected $code = 0;
    protected $msg = "OK";
    protected $data = [];
    protected $fromfd = 0;
    protected $targetfd = 0;

    /**
     * 进程级常量缓存：serial_code / pid 在 worker 生命周期内不变，
     * 仅首次生成 trace_id 时读取，避免每请求走容器/系统调用。
     * @var int|null
     */
    private static ?int $serialCode = null;
    private static ?int $pid = null;

    /**
     * 构造器
     * @param int $protocol
     * @param \Swoole\Http\Response|\Swoole\WebSocket\Server $rawResponse
     */
    public function __construct(int $protocol, mixed $rawResponse)
    {
        $this->protocol = $protocol;
        $this->rawResponse = $rawResponse;
    }

    /**
     * 设置来自用户fd
     * @param int $fd
     * @return void
     */
    public function setFromfd(int $fd)
    {
        $this->fromfd = $fd;
    }

    /**
     * 设置目标用户fd
     * @param int $fd
     * @return void
     */
    public function setTargetfd(int $fd)
    {
        $this->targetfd = $fd;
    }

    /**
     * 获取原始响应
     * @return mixed|\Swoole\Http\Request|\Swoole\Http\Response|null
     */
    public function rawResponse()
    {
        return $this->rawResponse;
    }

    /**
     * 设置code
     * @param int $code
     * @return void
     */
    public function setcode(int $code)
    {
        $this->code = $code;

    }

    /**
     * 获取HTTPcode
     * @return int|mixed
     */
    protected function getHttpCode()
    {
        if ($this->code >= 200 && $this->code <= 504){
            return $this->code;
        }elseif ($this->code >= 20000){
            return 200;
        }elseif ($this->code >= 10000){
            return 417;
        }else{
            return 500;
        }
    }

    /**
     * 设置消息
     * @param string $msg
     * @return void
     */
    public function setmsg(string $msg)
    {
        $this->msg = $msg;
    }

    /**
     * 设置响应数据
     * @param mixed $data
     * @return void
     */
    public function setdata(mixed $data)
    {
        $this->data = $data;
    }

    /**
     * 获取响应数据
     * @return array|mixed
     */
    public function getdata()
    {
        return $this->data;
    }

    /**
     * 发送消息响应
     * @param mixed $data
     * @return bool
     */
    public function send(){
        if($this->data !== false){
            switch ($this->protocol){
                case Server::HTTP:
                    return $this->sendHttp();
                case Server::WEBSOCKET:
                    return $this->sendWebsocket();
                case Server::TCP:
                    return $this->sendTcp();
                case Server::HTTP_MCP:
                    return $this->sendMcp();
            }
        }
        return false;
    }

    /**
     * 生成 trace_id（时间戳.随机数.集群序号.进程id）
     * serial_code / pid 为进程级常量仅首次读取；microtime + rand 每请求变化。
     * @return string
     */
    private function traceId(): string
    {
        if (self::$serialCode === null) {
            self::$serialCode = (int)App::config()->get('app.serial_code', 0);
        }
        if (self::$pid === null) {
            self::$pid = getmypid();
        }
        return sprintf('%.6f%d.%d.%d', microtime(true), rand(10000, 99999), self::$serialCode, self::$pid);
    }

    /**
     * 发送http响应
     * @return bool
     */
    private function sendHttp()
    {
        $this->rawResponse->status($this->getHttpCode());
        $this->rawResponse->header('Server', 'zhanshop');
        if(is_array($this->data)){
            $this->data['trace_id'] = $this->traceId();
            $this->rawResponse->header('Content-Type', 'application/json; charset=utf-8');
            $this->data = json_encode($this->data);
            return $this->rawResponse->end($this->data);
        }else if(is_object($this->data)){
            return $this->rawResponse->end(json_encode($this->data));
        }else{
            return $this->rawResponse->end(strval($this->data));
        }
    }

    /**
     * 响应websocket消息
     * @return bool
     */
    private function sendWebsocket()
    {
        if(is_array($this->data) || is_object($this->data)){
            $this->data['trace_id'] = $this->traceId();
            $this->data = json_encode($this->data);
        }
        return Server::$server->push($this->fromfd, $this->data."\r\n");
    }

    /**
     * 向指定fd推送websocket消息
     * @param $fd
     * @param $data
     * @return false
     */
    public function toPush($fd, $data)
    {
        if(is_array($data) || is_object($data)){
            $data = json_encode($data, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
        }
        try{
            return Server::$server->push($fd, $data);
        }catch (\Throwable $e){
            return false;
        }
    }

    /**
     * 响应tcp消息
     * @return bool
     */
    private function sendTcp()
    {
        if(is_array($this->data) || is_object($this->data)){
            $this->data['trace_id'] = $this->traceId();
            $this->data = json_encode($this->data);
        }
        return Server::$server->send($this->fromfd, $this->data."\r\n");
    }

    /**
     * 最终的响应MCP直接断开
     * @return mixed
     */
    protected function sendMcp()
    {
        //$this->rawResponse->header('Connection', 'close');
        $this->rawResponse->header('Server', 'zhanshop');
        if(is_array($this->data) || is_object($this->data)){
            $this->rawResponse->write("event: message\r\ndata: ".json_encode($this->data, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE)."\r\n\r\n");
            $this->data = [];
        }
    }

    /**
     * 向指定fd推送tcp消息
     * @param $fd
     * @param $data
     * @return false
     */
    public function toSend($fd, $data, $partition = "\r\n")
    {
        if(is_array($data) || is_object($data)){
            $data = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        try{
            return Server::$server->send($fd, $data.$partition);
        }catch (\Throwable $e){
            return false;
        }
    }

}