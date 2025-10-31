<?php

namespace zhanshop\server\service;

use Swoole\Coroutine\Channel;

abstract class QueueService
{
    protected Channel $channel;
    protected $capacity = 500;
    public function __construct()
    {
        $this->channel = new Channel($this->capacity);
    }

    /**
     * 生产数据
     * @param mixed $data
     * @param float $timeout
     * @return bool
     */
    public function push(mixed $data, float $timeout = 0.001)
    {
        return $this->channel->push($data, $timeout);
    }

    /**
     * 消费数据
     * @param float $timeout
     * @return mixed
     */
    public function pop(float $timeout = 0.001)
    {
        return $this->channel->pop($timeout);
    }

    /**
     * 元素数量
     * @return int
     */
    public function length()
    {
        return $this->channel->length();
    }

    /**
     * 是否为空
     * @return bool
     */
    public function isEmpty()
    {
        return $this->channel->isEmpty();
    }

    /**
     * 是否已满
     * @return mixed
     */
    public function isFull()
    {
        return $this->channel->isFull();
    }

    /**
     * 消费者
     * @return mixed
     */
    abstract public function consume();
}