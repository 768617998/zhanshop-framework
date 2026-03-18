<?php

namespace zhanshop\util;

use zhanshop\App;
use zhanshop\console\command\Server;

class SnowflakeID
{
    private int $epoch;

    private int $datacenterId;
    private int $workerId;

    private int $lastTimestamp = 0;
    private int $sequence = 0;

    // 位数配置
    private int $timestampBits;
    private int $datacenterIdBits;
    private int $workerIdBits;
    private int $sequenceBits;

    // 掩码与移位
    private int $sequenceMask;
    private int $workerIdShift;
    private int $datacenterIdShift;
    private int $timestampLeftShift;

    /**
     * 构造函数
     *
     * @param int   $datacenterId      数据中心 ID (范围由 datacenterIdBits 决定)
     * @param int   $workerId          机器 ID (范围由 workerIdBits 决定)
     * @param int   $epoch             起始毫秒时间戳（推荐设置为过去某个时间点，如 2020-01-01 的毫秒数）
     * @param array $bitAllocation     位数分配，必须包含 timestampBits, datacenterIdBits, workerIdBits, sequenceBits
     *                                  总和必须 ≤ 63，且各部分必须为正整数。
     * @throws InvalidArgumentException
     */
    /**
     * 构造函数
     * @param int $epoch 自定义纪元毫秒时间
     * @param int $timestampBits 时间戳部分（42位）：最大可表示2^42毫秒。计算年限：2^42毫秒 = 2^42 / 1000 秒 = 2^42 / (1000*3600*24*365) 年。2^42 ≈ 4.398e12毫秒，除以1000得4.398e9秒，再除以每年31536000秒（365天）≈ 139.5年，远超百年需求
     * @param int $datacenterIdBits 数据中心ID（5位）：0~31，共32个不同的数据中心/服务器
     * @param int $workerIdBits 机器/进程ID（5位）：0~31，共32个不同的worker ID。注意，这里的worker ID可以代表物理机器，也可以代表同一台机器上的不同进程。但在分布式环境中，通常需要全局唯一组合（数据中心ID+worker ID）。所以总共可以支持32×32=1024个独立生成器实例
     * @param int $sequenceBits 序列号（11位）：每毫秒最多2^11=2048个ID。因此单个生成器实例每秒理论最大值为2048×1000=2,048,000 ≈ 204.8万。注意这是理想情况下的上限，实际受CPU、内存等因素影响可能略低，但对于10万/秒的需求绰绰有余
     */
    public function __construct(int $epoch = 1767196800000, int $timestampBits = 42, int $datacenterIdBits = 5, int $workerIdBits = 5, int $sequenceBits = 11)
    {
        $datacenterId = App::config()->get("app.serial_code");
        try {
            $workerId = intval(Server::$server->getWorkerId());
        }catch (\Throwable $exception){
            $workerId = 0;
        }
        $totalBits = $timestampBits + $datacenterIdBits + $workerIdBits + $sequenceBits;
        if ($totalBits > 63) {
            throw new InvalidArgumentException("Total bits ({$totalBits}) exceeds 63");
        }

        $this->timestampBits = $timestampBits;
        $this->datacenterIdBits = $datacenterIdBits;
        $this->workerIdBits = $workerIdBits;
        $this->sequenceBits = $sequenceBits;

        $maxDatacenterId = (1 << $this->datacenterIdBits) - 1;
        $maxWorkerId = (1 << $this->workerIdBits) - 1;

        if ($datacenterId < 0 || $datacenterId > $maxDatacenterId) {
            throw new InvalidArgumentException("datacenterId must be between 0 and {$maxDatacenterId}");
        }
        if ($workerId < 0 || $workerId > $maxWorkerId) {
            throw new InvalidArgumentException("workerId must be between 0 and {$maxWorkerId}");
        }

        $this->datacenterId = $datacenterId;
        $this->workerId = $workerId;
        $this->epoch = $epoch;

        // 计算掩码和移位
        $this->sequenceMask = (1 << $this->sequenceBits) - 1;
        $this->workerIdShift = $this->sequenceBits;
        $this->datacenterIdShift = $this->sequenceBits + $this->workerIdBits;
        $this->timestampLeftShift = $this->sequenceBits + $this->workerIdBits + $this->datacenterIdBits;
    }

    /**
     * 生成下一个唯一 ID
     * @return int|string 在 64 位系统上返回 int，32 位系统上返回 string
     * @throws RuntimeException 当时钟回拨超过 5 秒时抛出异常
     */
    public function nextId()
    {
        $timestamp = $this->currentTimestamp();

        if ($timestamp < $this->lastTimestamp) {
            $diff = $this->lastTimestamp - $timestamp;
            if ($diff > 5000) {
                throw new RuntimeException("Clock moved backwards. Refusing to generate id for {$diff} milliseconds");
            }
            while ($timestamp < $this->lastTimestamp) {
                usleep(1000);
                $timestamp = $this->currentTimestamp();
            }
        }

        if ($timestamp === $this->lastTimestamp) {
            $this->sequence = ($this->sequence + 1) & $this->sequenceMask;
            if ($this->sequence === 0) {
                while ($timestamp <= $this->lastTimestamp) {
                    usleep(1000);
                    $timestamp = $this->currentTimestamp();
                }
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastTimestamp = $timestamp;

        $id = (($timestamp - $this->epoch) << $this->timestampLeftShift)
            | ($this->datacenterId << $this->datacenterIdShift)
            | ($this->workerId << $this->workerIdShift)
            | $this->sequence;

        if (PHP_INT_SIZE === 4) {
            return (string) $id;
        }
        return $id;
    }

    /**
     * 获取当前毫秒时间戳
     */
    private function currentTimestamp(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * 解析 ID 的组成部分（使用当前对象的位数配置）
     *
     * @param int|string $id 生成的 ID
     * @return array ['timestamp' => 相对纪元毫秒数, 'datacenterId', 'workerId', 'sequence']
     */
    public function parseId($id): array
    {
        $id = (int) $id;

        $sequenceMask = (1 << $this->sequenceBits) - 1;
        $workerIdMask = (1 << $this->workerIdBits) - 1;
        $datacenterIdMask = (1 << $this->datacenterIdBits) - 1;

        $sequence = $id & $sequenceMask;
        $workerId = ($id >> $this->workerIdShift) & $workerIdMask;
        $datacenterId = ($id >> $this->datacenterIdShift) & $datacenterIdMask;
        $timestamp = ($id >> $this->timestampLeftShift);

        return [
            'timestamp'    => $timestamp,
            'datacenterId' => $datacenterId,
            'workerId'     => $workerId,
            'sequence'     => $sequence,
        ];
    }
}