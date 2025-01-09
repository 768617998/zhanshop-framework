<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / Encode.php    [ 2024/10/9 13:15 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\mqtt;

class Encode
{
    /**
     * 打包编码数据
     * @param int $type
     * @param array $data
     * @return string|void
     */
    public function pack(int $type, array $data = [])
    {
        switch ($type){
            case 1:
                return $this->connect($data);
            case 3:
                return $this->publish($data['topic'], $data['message'], $data['qos'], $data['message_id'], $data['retain'], $data['dup']);
            case 9:
                return $this->subAck($data['topics'], $data['message_id']);
        }
    }

    /**
     * 编码连接数据
     * @param array $data
     * @return string
     */
    protected function connect(array $data)
    {
        return chr(32) . chr(2) . chr(0) . chr(0);
    }

    /**
     * 接收主题消息并回复
     * @param array $array
     * @return string
     */
    public function publish(string $topic, string $message, int $qos = 0, int $messageId = 0, int $retain = 0, int $dup = 0): string
    {
        $body = $this->packStr($array['topic']);
        if ($qos && $messageId) {
            $body .= $this->packShortInt($messageId);
        }

        $body .= chr(0);

        $body .= $message;
        $head = $this->packHeader(3, strlen($body), $dup, $qos, $retain);
        return $head . $body;
    }

    /**
     * 打包订阅确认消息
     * @param array $topics
     * @param int $qos
     * @param int $messageId
     * @param int $retain
     * @param int $dup
     * @return string
     */
    public function subAck(array $topics, int $qos = 0, int $messageId = 0, int $retain = 0, int $dup = 0)
    {
        $payload = [];
        foreach ($topics as $k => $option) {
            $qos = $option['qos'];
            if (is_numeric($qos) && $qos < 3) {
                $payload[] = $qos;
            } else {
                $payload[] = 0x9B;
            }
        }

        $body = $this->packShortInt($messageId);

        $body .= chr(0);

        $body .= call_user_func_array(
            'pack',
            array_merge(['C*'], $payload)
        );
        $head = $this->packHeader(9, strlen($body), $dup, $qos, $retain);
        var_dump($head . $body);
        return $head . $body;
    }

    /**
     * 连接属性打包
     * @param array $array
     * @return void
     */
    protected function propertyConnect(array $array)
    {

    }

    /**
     * 打包字符串为二进制报文
     * @param string $str
     * @return string
     */
    protected function packStr(string $str)
    {
        $len = strlen($str);

        return pack('n', $len) . $str;
    }

    /**
     * 打包数字为无符号短（始终16位，大端字节顺序）报文
     * @param int $int
     * @return string
     */
    protected function packShortInt(int $int)
    {
        return pack('n', $int);
    }

    /**
     * 打包头部信息
     * @param int $type
     * @param int $bodyLength
     * @param int $dup
     * @param int $qos
     * @param int $retain
     * @return string
     */
    public function packHeader(int $type, int $bodyLength, int $dup = 0, int $qos = 0, int $retain = 0): string
    {
        $type = $type << 4;
        if ($dup) {
            $type |= 1 << 3;
        }
        if ($qos) {
            $type |= $qos << 1;
        }
        if ($retain) {
            $type |= 1;
        }

        return chr($type) . static::packRemainingLength($bodyLength);
    }

    /**
     * 打包长度信息
     * @param int $bodyLength
     * @return string
     */
    protected function packRemainingLength(int $bodyLength): string
    {
        $string = '';
        do {
            $digit = $bodyLength % 128;
            $bodyLength = $bodyLength >> 7;
            if ($bodyLength > 0) {
                $digit = ($digit | 0x80);
            }
            $string .= chr($digit);
        } while ($bodyLength > 0);

        return $string;
    }
}