<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / Decode.php    [ 2024/10/9 13:15 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\mqtt;

use zhanshop\App;

class Decode
{
    protected $mqttCommands = [
        // 客户端和服务端建立连接
        1 => 'connect',
        // 服务器端确认连接建立
        2 => 'connack',
        // 发布消息
        3 => 'publish',
        // 收到发布消息确认
        4 => 'puback',
        // 发布消息收到
        5 => 'pubrec',
        // 发布消息释放
        6 => 'pubrel',
        // 发布消息完成
        7 => 'pubcomp',
        // 订阅请求
        8 => 'subscribe',
        // 订阅确认
        9 => 'suback',
        // 取消订阅
        10 => 'unsubscribe',
        // 取消订阅确认
        11 => 'unsuback',
        // 客户端发送PING消息
        12 => 'pingreq',
        // PING命令回复
        13 => 'pingresp',
        // 断开连接
        14 => 'disconnect',
    ];

    public function unpack(string $data)
    {
        /**
         * 第一个字节 包含消息类型，DUP,QOS等
         */
        $type = ord($data[0]) >> 4;

        /**
         * 第二个字节长度
         */
        $headBytes = $multiplier = 1;
        $value = 0;
        do {
            if (!isset($data[$headBytes])) {
                App::error()->setError('剩余长度格式不正确', 400);
            }
            $digit = ord($data[$headBytes]);
            $value += ($digit & 127) * $multiplier;
            $multiplier *= 128;
            ++$headBytes;
        } while (($digit & 128) != 0);

        $remainingLength = $value;
        $remainingData = substr($data, $headBytes, $remainingLength); // 剩余的内容
        $method = $this->mqttCommands[$type] ?? 'unknown';
        $data = $this->$method($remainingData);
        return [$type, $data];
    }

    /**
     * 解码连接消息
     * 前面两个字节是预留字段
     * @param string $remainingData
     * @return array
     */
    public function connect(string $remainingData)
    {
        $length = unpack('n', $remainingData)[1]; // 拿到协议名的长度
        $protocolName = substr($remainingData, 2, $length); // 拿到协议名称
        if($protocolName != "MQTT"){
            App::error()->setError("协议类型必须是MQTT");
            return false;
        }
        $remainingData = substr($remainingData, $length + 2); // 拿到除协议名称以外的后面内容协议名称占用两个字节
        $protocolLevel = ord($remainingData[0]); // 拿到协议的版本号
        if($protocolLevel != 5){
            App::error()->setError("当前服务端只支持MQTT5版本", 400);
            return false;
        }

        $remainingData = substr($remainingData, 1); // 拿协议版本后面的内容 协议版本号暂用一个字节

        $cleanSession = ord($remainingData[0]) >> 1 & 0x1;
        $willFlag = ord($remainingData[0]) >> 2 & 0x1;
        $willQos = ord($remainingData[0]) >> 3 & 0x3;
        $willRetain = ord($remainingData[0]) >> 5 & 0x1;
        $passwordFlag = ord($remainingData[0]) >> 6 & 0x1;
        $userNameFlag = ord($remainingData[0]) >> 7 & 0x1;

        $remainingData = substr($remainingData, 1); // 拿标志位后面的内容 标志位占用一个字节
        //var_dump($cleanSession,$willFlag,$willQos,$willRetain,$passwordFlag,$userNameFlag);

        $keepAlive = unpack('n', $remainingData)[1]; // 拿到保活时间 保活时间占用两个字节

        $remainingData = substr($remainingData, 2); // 拿保活时间后面的数据

        $propertiesTotalLength = ord($remainingData[0]); // 扩展属性的总长度
        $remainingData = substr($remainingData, 1);

        $properties = $this->propertyConnect($propertiesTotalLength, $remainingData);
        // 获取连接ID
        $clientIdLength = unpack('n', $remainingData)[1];
        $clientId = substr($remainingData, 2, $clientIdLength);
        $remainingData = substr($remainingData, $clientIdLength + 2);

//        echo "=======";
//        var_dump($cleanSession,$willFlag,$willQos,$willRetain,$passwordFlag,$userNameFlag);
//        echo "=======";

        $userName = $password = '';
        if($passwordFlag){
            $passwordLength = unpack('n', $remainingData)[1];
            $password = substr($remainingData, 2, $passwordLength);
            $remainingData = substr($remainingData, $passwordLength + 2);
        }

        if($userNameFlag){
            $unameLength = unpack('n', $remainingData)[1];
            $userName = substr($remainingData, 2, $unameLength);
            $remainingData = substr($remainingData, $unameLength + 2);
        }

        $package = [
            'type' => 1,
            'protocol_name' => $protocolName,
            'protocol_level' => $protocolLevel,
            'clean_session' => $cleanSession,
            'client_id' => $clientId,
            'user_name' => $userName,
            'password' => $password,
            'keep_alive' => $keepAlive,
        ];

        if($properties) $package['properties'] = $properties;
        return $package;
    }

    /**
     * 订阅请求
     * @param string $remainingData
     * @return void
     */
    public function subscribe(string $remainingData)
    {
        $messageId = $this->unpackShortInt($remainingData);

        $package = [
            'type' => 8,
            'message_id' => $messageId,
        ];

        $propertiesTotalLength = ord($remainingData[0]); // 预留暂时还没有使用
        $remainingData = substr($remainingData, 1);


        $topics = [];
        while ($remainingData) {
            $topic = $this->unpackStr($remainingData);
            $topics[$topic] = [
                'qos' => ord($remainingData[0]) & 0x3,
                'no_local' => (bool) (ord($remainingData[0]) >> 2 & 0x1),
                'retain_as_published' => (bool) (ord($remainingData[0]) >> 3 & 0x1),
                'retain_handling' => ord($remainingData[0]) >> 4,
            ];
            $remainingData = substr($remainingData, 1);
        }

        $package['topics'] = $topics;

        return $package;
    }

    /**
     * 发布消息 【收到客户端的发布消息】
     * @param string $data
     * @return void
     */
    public function publish(string $data)
    {
        $dup = ord($data[0]) >> 3 & 0x1;
        $qos = ord($data[0]) >> 1 & 0x3;
        $retain = ord($data[0]) & 0x1;


        $topic = $this->unpackStr($data);

        $package = [
            'type' => 3,
            'dup' => $dup,
            'qos' => $qos,
            'retain' => $retain,
            'topic' => $topic,
        ];

        $propertiesTotalLength = ord($data[0]); // 属性长度
        //var_dump("消息的属性长度", $propertiesTotalLength);
        $data = substr($data, 1);
        $package['message'] = $data;
        return $package;
    }

    /**
     * 解包二进制报文为字符串
     * @param string $str
     * @return string
     */
    protected function unpackStr(string &$remaining)
    {
        $length = unpack('n', $remaining)[1];
        $string = substr($remaining, 2, $length);
        $remaining = substr($remaining, $length + 2);

        return $string;
    }

    /**
     * 解包二进制报文为字符串
     * @param string $str
     * @return string
     */
    protected function unpackShortInt(string &$remaining)
    {
        $tmp = unpack('n', $remaining);
        $remaining = substr($remaining, 2);

        return $tmp[1];
    }


    /**
     * 解码连接属性
     * @param int $length
     * @param string $remainingData
     * @return array
     */
    private function propertyConnect(int $length, string &$remainingData)
    {
        $properties = [];
        do {
            $property = ord($remainingData[0]); // 属性标识符
            $remainingData = substr($remainingData, 1);
            switch ($property){
                case 17:
                    $properties[17] = unpack('N', $remainingData)[1];
                    $remainingData = substr($remainingData, 4);
                    $length -= 5;
                    break;
                case 22:
                    break;
                case 25:
                    break;
                case 34:
                    break;
                case 38:
                    break;
            }
        } while ($length > 0);
        return $properties;
    }


    /**
     * 未定义的方法
     * @param string $name
     * @param array $arguments
     * @return void
     */
    public function __call(string $name, array $arguments)
    {

    }
}