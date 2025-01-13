<?php
// +----------------------------------------------------------------------
// | zhanshop_admin / Response.php [ 2023/4/16 下午3:52 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

use app\http\Controller;
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
            }
        }
        return false;
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
            $this->data['trace_id'] = microtime(true).rand(10000, 99999).'.'. App::config()->get('app.serial_code', 0).'.'.getmypid();
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
            $this->data['trace_id'] = microtime(true).rand(10000, 99999).'.'. App::config()->get('app.serial_code', 0).'.'.getmypid();
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
            $this->data['trace_id'] = microtime(true).rand(10000, 99999).'.'. App::config()->get('app.serial_code', 0).'.'.getmypid();
            $this->data = json_encode($this->data);
        }
        return Server::$server->send($this->fromfd, $this->data."\r\n");
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
            $data = json_encode($data, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
        }
        try{
            return Server::$server->send($fd, $data.$partition);
        }catch (\Throwable $e){
            return false;
        }
    }

}