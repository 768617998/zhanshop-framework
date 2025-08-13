<?php

namespace zhanshop\client;

use Swoole\Coroutine\Client;
use zhanshop\App;

class RpcClient
{
    protected $host = null;
    protected $port = null;
    protected $path = null;
    protected $query = null;
    protected $param = [];
    protected $timeOut = 3;
    /**
     * 构建连接的rpc类
     * @template Type
     * @param class-string<Type> $className
     * @return Type
     */
    public function make(string $className, ...$value)
    {
        $rpcServer = App::env()->get("RPC_HOST", "zhanshop-cloud:7202");
        $this->timeOut = floatval(App::env()->get("RPC_TIMEOUT", "3"));
        [$this->host, $this->port] = explode(':', $rpcServer);
        $this->path = $className;
        $this->query = http_build_query($value);
        return $this;
    }

    /**
     * 请求
     * @param string $method
     * @param array $param
     * @return void
     * @throws \Exception
     */
    protected function request(string $method, array $param)
    {
        $client = new Client(SWOOLE_SOCK_TCP);
        if (!$client->connect($this->host, $this->port, $this->timeOut)){
            App::error()->setError($this->host.':'.$this->port.'rpc服务器连接失败#'.$client->errCode);
        }

        $request = [
            'path' => $this->path.'.'.$method.'?'.$this->query,
            'header' => [],
            'body' => $param,
        ];
        $client->send(json_encode($request, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE)."\r\n");
        $fullData = "";
        while (true){
            $recv = $client->recv();
            if($recv === ""){
                $fullData = $recv;
                break;
            }else{
                $fullData .= $recv;
                if(strpos($fullData, "\r\n") !== false){
                    break;
                }
            }
        }

        if($fullData === ""){
            $client->close();
            App::error()->setError($this->host.':'.$this->port.'/'.$this->path.'/'.$method.'?'.$this->query.' rpc连接失败');
        }else{
            $data = json_decode($fullData, true);
            if($data == false) App::error()->setError("rpc返回的数据格式错误\n".$fullData);
            if(($data['code'] ?? 500) !== 0) App::error()->setError($data['msg'] ?? "发生未知错误");
            return $data["data"] ?? null;
        }
    }

    /**
     * 容器获取app类
     * @param $name
     * @param array $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call(string $name, array $arguments)
    {
        return $this->request($name, $arguments);
    }

    public function undefined(string $name)
    {

    }
}