<?php
// +----------------------------------------------------------------------
// | zhanshop-mcp / Message.php    [ 2025/8/8 18:19 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\server\response\mcp;

use zhanshop\App;

class Message
{
    /**
     * @var \zhanshop\server\Response
     */
    protected $response;
    protected $data = [
        'jsonrpc' => '2.0',
        'id' => 0,
        'result' => [
            'content' => [], // 给模型看的
            'structuredContent' => [], // 给程序用的
            'isError' => false
        ],
    ];

    protected $sendNum = 0;

    /**
     * 构造方法
     * @param \zhanshop\server\Request $request
     * @param \zhanshop\server\Response $response
     * @return void
     */
    public function __construct(mixed $request, mixed $response)
    {
        $this->response = $response;
        $mcpInput = $request->header('mcp-input');
        $this->data['jsonrpc'] = $mcpInput['jsonrpc'] ?? '2.0';
        $this->data['id'] = $mcpInput['id'] ?? 0;
    }

    /**
     * 添加消息内容
     * @param mixed $data
     * @return void
     */
    public function addText(mixed $data)
    {
        if(is_array($data) || is_object($data)){
            $this->data['result']['structuredContent'] = $data;
            $data = json_encode($data);
        }
        $this->data['result']['content'][] = [
            'type' => 'text',
            'text' => $data
        ];
    }

    /**
     * 添加图片消息
     * @param string $imagePath
     * @param string $description
     * @return void
     * @throws \Exception
     */
    public function addImage(string $imagePath, string $description)
    {
        if(file_exists($imagePath) == false) App::error()->setError($imagePath.' not exist');
        $this->data['result']['content'][] = [
            'type' => 'image',
            'data' => base64_encode(file_get_contents($imagePath)),
            'mimeType' => mime_content_type($imagePath),
            'content' => $description
        ];
    }

    /**
     * 音频消息
     * @param string $audioPath
     * @param string $description
     * @return void
     * @throws \Exception
     */
    public function addAudio(string $audioPath, string $description)
    {
        if(file_exists($audioPath) == false) App::error()->setError($audioPath.' not exist');
        $this->data['result']['content'][] = [
            'type' => 'audio',
            'data' => base64_encode(file_get_contents($audioPath)),
            'mimeType' => mime_content_type($audioPath),
            'content' => $description
        ];
    }

    /**
     * 发送通知消息
     * @param string $data
     * @param string $type
     * @return void
     */
    public function notice(string $data, string $type = 'info')
    {
        $message = [
            'method' => 'notifications/message',
            'params' => [
                'level' => $type,
                'logger' => 'notification_stream',
                'data' => $data,
            ],
            'jsonrpc' => $this->data['jsonrpc']
        ];
        $message = json_encode($message, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
        if($message == false){
            App::error()->setError('输出内容有问题JSON编码失败');
        }
        $data = "event: message\r\ndata: ".$message."\r\n\r\n";
        $this->response->rawResponse()->write($data);
    }
    /**
     * 发送响应
     * @return void
     */
    public function send()
    {
        $data = json_encode($this->data, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
        if($data == false){
            $this->data['result']['content'] = [
                [
                    'type' => 'text',
                    'text' => '输出内容有问题JSON编码失败'
                ]
            ];
            $this->data['isError'] = true;
            $data = json_encode($this->data, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
            $data = $data ?? "";
        }
        $this->data['result']['content'] = [];
        $data = "event: message\r\ndata: ".$data."\r\n\r\n";


        var_dump($data);

        $this->response->rawResponse()->write($data);
    }
}