<?php
// +----------------------------------------------------------------------
// | zhanshop-mcp / AichatClient.php    [ 2025/8/10 15:10 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client;

use zhanshop\App;

class AichatClient
{
    protected $apiHost = 'http://127.0.0.1:11434/v1';
    protected $apiKey = '';
    protected McpClient $mcpClient;
    protected $messageCallback = null;
    protected $config = [
        'model' => 'qwen3:8b',
        'messages' => [],
        'temperature' => 1,
        'top_p' => 1,
        'enable_thinking' => false,
        'stream' => true,
        'stream_options' => [
            'include_usage' => true
        ],
        'tools' => [
        ],
    ];

    /**
     * 构造方法
     * @param string $apiAddress
     * @param string $apiKey
     * @param string $model
     */
    public function __construct(string $apiAddress, string $apiKey, string $model)
    {
        $this->apiHost = $apiAddress;
        $this->apiKey = $apiKey;
        $this->config['model'] = $model;
        $this->config['messages'] = [
            [
                'role' => 'system',
                'content' => '如你是一个助手，你可以使用工具来解决用户的问题。'
            ]
        ];
    }

    /**
     * 系统提示词
     * @param string $prompt
     * @return void
     */
    public function systemPrompt(string $prompt)
    {
        $this->config['messages'] = [
            [
                'role' => 'system',
                'content' => $prompt
            ]
        ];
    }

    /**
     * 设置mcp连接
     * @param McpClient $client
     * @return void
     */
    public function setMcpClient(McpClient $client)
    {
        $this->mcpClient = $client;
        $tools = $this->mcpClient->toolsList();
        foreach ($tools as $k => $v){
            $this->config['tools'][] = [
                'type' => 'function',
                'function' => $v
            ];
        }
    }

    /**
     * 设置问题
     * @param string $content
     * @return void
     */
    public function setQuestion(string $content)
    {
        $data = [
            'role' => 'user',
            'content' => $content
        ];
        if(json_encode($data) == false) App::error()->setError("输入的内容异常");
        $this->config['messages'][] = $data;
    }

    /**
     * 监听chat消息事件
     * @param callable $callBack
     * @return void
     */
    public function onMessage(mixed $callBack)
    {
        $this->messageCallback = $callBack;
    }

    /**
     * 解析工具消息
     * @param array $messages
     * @return void
     */
    protected function decodeToolMessage($func, array $messages)
    {
        $firstData = $messages['content'][0];
        if($firstData['type'] == 'text'){
            $text = ($firstData['text'] ?? '');
            if($text){
                $content = ['role' => 'user', "content" => '工具（'.$func.'）返回的结果: '];
                $content['content'] .= $text;
                $this->config['messages'][] = $content;
                return true;
            }
        }

        $messageCallback = $this->messageCallback;
        $messageCallback($this, "\r\n\r\n".json_encode($firstData)."\n");
        return false;
    }
    /**
     * 向模型发送消息
     * @return void|null
     */
    private function sendModelMsg()
    {
        $client = new Httpclient();
        $client->setTimeout(60000);
        $client->setHeader('Content-Type', 'application/json');
        if($this->apiKey) $client->setHeader('authorization', "Basic ".$this->apiKey);
        $resultTool = [];
        $client->setOptions('write_func', function ($client, $data)use (&$resultMessage, &$resultTool){
            $data = substr($data, 6);
            $result = json_decode($data, true);
            $choices = $result['choices'] ?? [];
            foreach ($choices as $k => $v){
                $toolCalls = $v['delta']['tool_calls'] ?? [];
                if($toolCalls){
                    $resultTool = array_merge($resultTool, $toolCalls);
                }else{
                    $message = $v['delta']['content'] ?? '';
                    $func = $this->messageCallback;
                    $func($this, $message);
                }
            }
        });
        $data = json_encode($this->config, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
        $url = $this->apiHost.'/chat/completions';
        $client->request($url, 'POST', $data);
        foreach ($resultTool as $k => $v){
            $function = $v['function'];
            $funName = $function['name'];
            $arguments = json_decode($function['arguments'], true);
            $toolMessage = $this->mcpClient->callTool($funName, $arguments);
            if($toolMessage){
                if($this->decodeToolMessage($funName, $toolMessage)){
                    return $this->send();
                }
            }
        }
    }
    /**
     * 发送消息
     * @return void
     */
    public function send()
    {
        if($this->messageCallback == false) App::error()->setError('onMessage 监听chat消息事件回调未初始化');
        $this->sendModelMsg();
    }
}