<?php
// +----------------------------------------------------------------------
// | zhanshop-admin / Request.php [ 2023/2/23 下午7:59 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

use zhanshop\console\command\Server;

class Request
{
    /**
     * 协议
     * @var int
     */
    protected $protocol = Server::HTTP;

    /**
     * 原始请求对象
     * @var \Swoole\Http\Request
     */
    protected mixed $rawRequest = null;

    /**
     * http请求内容类型
     * @var mixed|null
     */
    protected mixed $contentType = null;

    /**
     * 额外参数
     * @var array
     */
    protected array $extData = [];

    protected array $handler = [];

    // 请求的方法
    protected $method;

    /**
     * 构造器
     * @param int $protocol
     * @param mixed $rawRequest http协议：Request对象， tcp/udp：{"header": {}, "server", "get": {}, "post":{}}
     */
    public function __construct(int $protocol, mixed $rawRequest){
        $this->protocol = $protocol;
        $this->rawRequest = $rawRequest;

        if($this->method(true) == 'POST' && strpos($this->contentType(), 'json') !== false){
            $this->getInputData();
        }
    }

    /**
     * 设置Handler
     * @param array $handler
     * @return void
     */
    public function setHandler(array $handler)
    {
        $this->handler = $handler;
    }

    /**
     * 获取Handler
     * @return array
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * 获取客户端fd
     * @return int
     */
    public function fd(){
        return $this->rawRequest->fd;
    }

    /**
     * 获取请求方法
     * @param bool $original
     * @return string
     */
    public function method(bool $original = false) :string{
        if($original){
            return $this->rawRequest ? $this->rawRequest->getMethod() : '';
        }

        if($this->method){
            return $this->method;
        }else if(isset($this->rawRequest->post['_method'])){
            $this->method = $this->rawRequest->post['_method'];
            unset($this->rawRequest->post['_method']);
            return $this->method;
        }
        return $this->rawRequest->getMethod();
    }
    /**
     * 获取header
     * @param string $name
     * @param mixed|null $default
     * @return array|mixed|null
     */
    public function header(string $name = '', mixed $default = null)
    {
        if ('' === $name) {
            return $this->rawRequest->header ?? [];
        }

        return $this->rawRequest->header[$name] ?? $default;
    }

    /**
     * 获取请求类型
     * @return string
     */
    public function contentType(): string
    {
        if(null !== $this->contentType) return $this->contentType;

        $type = '';
        if(in_array($this->protocol, [Server::HTTP, Server::WEBSOCKET])){
            $contentType = $this->rawRequest->header['content-type'] ?? '';
            if ($contentType) {
                if (strpos($contentType, ';')) {
                    [$type] = explode(';', $contentType);
                } else {
                    $type = $contentType;
                }
                $type = trim($type);

                // 如果是json请求
                if(false !== strpos($type, 'json')){
                    $rawContent = $this->rawRequest->rawContent();
                    if($rawContent){
                        $rawData = json_decode($rawContent, true);
                        if($rawData){
                            $this->rawRequest->post = $rawData;
                        }else{
                            Log::errorLog(SWOOLE_LOG_WARNING, 'json请求格式错误:'.$rawContent);
                        }
                    }
                }
            }
        }

        $this->contentType = $type;
        return $type;
    }

    /**
     * 获取server
     * @param string $name
     * @param mixed|null $default
     * @return mixed|null
     */
    public function server(string $name = '', mixed $default = null){
        if ('' === $name) {
            return $this->rawRequest->server ?? [];
        }

        return $this->rawRequest->server[$name] ?? $default;
    }

    /**
     * 获取获取原始的 POST 包体
     * @return void
     */
    private function getInputData(){
        $rawContent= $this->rawRequest->getContent();
        if($rawContent){
            $rawContent = json_decode($rawContent, true);
            if($rawContent) $this->rawRequest->post = $rawContent;
        }
    }
    /**
     * 获取get参数
     * @param string $name
     * @param mixed|null $default
     * @return array|mixed|null
     */
    public function get(mixed $name = '', mixed $default = null){
        if ('' === $name) {
            return $this->rawRequest->get ?? [];
        }

        return $this->rawRequest->get[$name] ?? $default;
    }

    /**
     * 获取post参数
     * @param string $name
     * @param mixed|null $default
     * @return array|mixed|null
     */
    public function post(mixed $name = '', mixed $default = null){
        if ('' === $name) {
            return $this->rawRequest->post ?? [];
        }

        return $this->rawRequest->post[$name] ?? $default;
    }

    /**
     * 获取上传文件信息
     * @param string $name
     * @param mixed|null $default
     * @return array|mixed|null
     */
    public function files(mixed $name = '', mixed $default = null){
        if ('' === $name) {
            return $this->rawRequest->files ?? [];
        }

        return $this->rawRequest->files[$name] ?? $default;
    }

    /**
     * 获取请求参数
     * @param string $key
     * @param mixed|null $default
     * @return array|mixed|null
     */
    public function param(mixed $name = '', mixed $default = null){
        switch ($this->method(true)){
            case "POST":
                $ret = $this->post($name, $default);
                break;
            default:
                $ret = $this->get($name, $default);
                break;
        }
        return $ret;
    }

    /**
     * 设置额外参数
     * @param mixed $name
     * @param mixed $val
     * @return void
     */
    public function setData(string $name, mixed $val){
        $this->extData[$name] = $val;
    }

    /**
     * 获取额外参数
     * @param mixed $name
     * @param mixed $val
     * @return mixed
     */
    public function getData(string $name = '', mixed $default = null){
        if ('' === $name) {
            return $this->extData ?? [];
        }

        $val = $this->extData[$name] ?? $default;
        if($val === null) App::error()->setError($name.'不能为空。', Error::BAD_REQUEST);
        return $val;
    }

    /**
     * 获取原始Request对象
     * @return void
     */
    public function rawRequest(){

        return $this->rawRequest;
    }

    /**
     * 获取请求事件戳
     * @param bool $float
     * @return int|mixed
     */
    public function time(bool $float = false){
        if($float) return $this->rawRequest->server['request_time_float'] ?? microtime(true);
        return $this->rawRequest->server['request_time'] ?? time();
    }

    /**
     * 请求参数验证规则
     * @param array $rules
     * @param array $message
     * @return Validate
     */
    public function validateRule(array $rules, array $message = []){
        $params = $this->param();
        return new Validate($params ?? [], $rules, $message);
    }

    /**
     * 获取get请求参数并验证是否为空
     * @param array $rules
     * @param array $message
     * @return void
     */
    public function getParam(string $name){
        return $this->rawRequest->get[$name] ?? App::error()->setError($name.'不能为空', Error::BAD_REQUEST);
    }

    /**
     * 验证自定义数据
     * @return void
     */
    public function validateData(array $data, array $rules, array $message = []){
        return new Validate($data, $rules, $message);
    }

    /**
     * 获取真实IP
     * @return string
     */
    public function realIp()
    {
        $ip = $this->header('x-real-ip');
        if($ip && $this->header('proxy-auth') == App::config()->get("app.rproxy_auth")) return $ip;
        return $this->server('remote_addr', "");
    }
}