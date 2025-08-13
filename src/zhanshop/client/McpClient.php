<?php
// +----------------------------------------------------------------------
// | zhanshop-mcp / McpClient.php    [ 2025/8/10 17:39 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client;

use zhanshop\App;

class McpClient
{
    protected $apiHost;
    protected $apiKey;
    protected $debug = false;
    public function __construct(string $apiAddress, string $apiKey)
    {
        $this->apiHost = $apiAddress;
        $this->apiKey = $apiKey;
    }

    /**
     * 设置debug
     * @return $this
     */
    public function debug()
    {
        $this->debug = true;
        return $this;
    }

    /**
     * 初始化
     * @return array|bool|string
     */
    public function initialize()
    {
        $client = new Httpclient();
        $params = [
            'method' => 'initialize',
            'params' => [
                'clientInfo' => [
                    'name' => 'zhanshop',
                    'version' => ''
                ],
            ],
            'jsonrpc' => '2.0',
            'id' => 0,
        ];
        if($this->debug) $client->debug();
        if($this->apiKey) $client->setHeader('authorization', "Basic ".$this->apiKey);
        return $client->request($this->apiHost, "POST", json_encode($params));
    }

    /**
     * notifications/initialized
     * @return array|bool|string
     */
    public function notificationsInitialized()
    {
        $client = new Httpclient();
        $params = [
            'method' => 'notifications/initialized',
            'jsonrpc' => '2.0'
        ];
        if($this->debug) $client->debug();
        if($this->apiKey) $client->setHeader('authorization', "Basic ".$this->apiKey);
        return $client->request($this->apiHost, "POST", json_encode($params));
    }

    /**
     * 获取提示
     * @param int $id
     * @return array|bool|string
     */
    public function promptsList(int $id = -1)
    {
        $client = new Httpclient();
        $params = [
            'method' => 'prompts/list',
            'jsonrpc' => '2.0',
            'id' => $id,
        ];
        if($this->debug) $client->debug();
        if($this->apiKey) $client->setHeader('authorization', "Basic ".$this->apiKey);
        return $client->request($this->apiHost, "POST", json_encode($params));
    }

    /**
     * 获取工具列表
     * @param int $id
     * @return array|bool|string
     */
    public function toolsList(int $id = -1)
    {
        $client = new Httpclient();
        $params = [
            'method' => 'tools/list',
            'jsonrpc' => '2.0',
            'id' => $id,
        ];
        if($this->debug) $client->debug();
        if($this->apiKey) $client->setHeader('authorization', "Basic ".$this->apiKey);
        $result = $client->request($this->apiHost, "POST", json_encode($params));
        $body = explode("\r\ndata: ", $result['body'] ?? "");
        if($result['code'] != 200 || isset($body[1]) == false) App::error()->setError('mcp工具列表获取失败', $result['code']);
        $body[1] = str_replace('","inputSchema":{"properties":{"', '","input_schema":{"properties":{"', $body[1]);
        $body[1] = str_replace('"outputSchema":{"properties":', '"output_schema":{"properties":', $body[1]);
        $data = json_decode($body[1], true);
        $tools = $data['result']['tools'] ?? [];
        return $tools;
    }
    /**
     * 获取资源列表
     * @param int $id
     * @return array|bool|string
     */
    public function resourcesList(int $id = -1)
    {
        $client = new Httpclient();
        $params = [
            'method' => 'resources/list',
            'jsonrpc' => '2.0',
            'id' => $id,
        ];
        if($this->debug) $client->debug();
        if($this->apiKey) $client->setHeader('authorization', "Basic ".$this->apiKey);
        return $client->request($this->apiHost, "POST", json_encode($params));
    }

    /**
     * ping
     * @param int $id
     * @return void
     */
    public function ping(int $id = -1)
    {
        $client = new Httpclient();
        $params = [
            'method' => 'ping',
            'jsonrpc' => '2.0',
            'id' => $id,
        ];
        if($this->debug) $client->debug();
        if($this->apiKey) $client->setHeader('authorization', "Basic ".$this->apiKey);
        return $client->request($this->apiHost, "POST", json_encode($params));
    }


    /**
     * 调用工具
     * @param string $path
     * @param array $data
     * @return mixed
     */
    public function callTool(string $tool, array $data, int $id = -1)
    {
        $client = new Httpclient();
        $params = [
            'method' => 'tools/call',
            'params' => [
                'name' => $tool,
                'arguments' => $data,
            ],
            'jsonrpc' => '2.0',
            'id' => $id,
        ];
        if($this->debug) $client->debug();
        if($this->apiKey) $client->setHeader('authorization', "Basic ".$this->apiKey);
        $result = $client->request($this->apiHost, "POST", json_encode($params));
        $messages = explode("event: message\r\ndata: ", $result['body'] ?? "");
        $message = json_decode(end($messages), true);
        return $message['result'] ?? [];
    }
}