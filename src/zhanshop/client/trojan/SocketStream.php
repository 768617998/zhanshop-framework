<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / trojan/SocketStream.php    [ 2026/10/12 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
// | Swoole\Coroutine\Socket 的传输层包装(直接收发隧道字节)。
// |
// | IO 超时(实测定的方案, 不是抄来的):
// |   Swoole\Coroutine\Socket **没有** setTimeout 方法, 超时得自己兜。本类用一个 deadline
// |   (绝对时刻) + Timer, 到点调 socket->shutdown() 把挂起的 IO 捅醒。三条实测结论:
// |     · recv 挂起 + cancel()      → 立即返回 false, errCode=125(SOCKET_ECANCELED)
// |     · recv 对端正常关闭         → 立即返回 ''(空串), errCode=0
// |     · send 挂起(发送缓冲区写满) → **cancel() 打不断**, 只有 shutdown() 能捅醒
// |   第三点是选 shutdown 而不是 cancel 的原因 —— 写方向 cancel 无效, 会把请求永久钉住。
// |   代价是 shutdown 后的 recv 返回 '' 与"对端正常关闭"无法用返回值区分, 故额外记
// |   一个 timedOut 标志, 供上层判断"对端关了"还是"我们超时了"。
// |
// | shutdown 对 TLS 的影响: 外层 TLS 的 read 会从本层读到 false, 它据此判定"传输层关闭"。
// |   内层(TlsStream)因此拿不到更多密文 —— 这正是我们要的: 超时后整条隧道立即失效。
// |
// | ⚠ 2026-10-12 语义变更: setTimeout() 从"整条请求的总预算"改成"空闲超时"
// |   (旧语义下 deadline 只在开头设一次, 中途不重置)。改的根据是一条实测事实:
// |   经 trojan 节点访问外网时, 单连接吞吐会长时间塌到 45~60KB/s(512KB 要 8.6~11.5 秒),
// |   但**字节一直在到** —— 塌得最狠的那次(45KB/s)里, 相邻两次读的最大间隔只有 0.282 秒,
// |   全程 119 次读里 36 次超过 50ms, 没有一次"静默"。旧语义下这种"慢但活着"的连接必然
// |   打穿总预算(3 秒 → 45KB/s 要 11.5 秒), 表现为"请求失败"; 新语义下它只是慢。
// |   总预算的能力没丢, 由 setTotalTimeout() 单独提供; 两者同时生效, 先到者触发。
// |
// |   塌是链路属性而非本实现缺陷的判据: 同一时刻并发对撞, curl 经 pproxy 走同一条隧道
// |   也一样塌(同一轮里 h2 46KB/s、h1.1 42KB/s, 而本客户端 513KB/s)。
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client\trojan;

use Swoole\Coroutine\Socket;
use Swoole\Timer;

class SocketStream implements Transport
{
    protected Socket $socket;

    /** 空闲超时(秒); 0 = 不限。语义: 连续这么久没有任何 IO 进展才判超时 */
    protected float $idleSeconds = 0;

    /** 空闲超时的绝对截止时刻(microtime); 0 = 不限 */
    protected float $idleDeadline = 0;

    /** 整次请求总预算的绝对截止时刻(microtime); 0 = 不限 */
    protected float $totalDeadline = 0;

    /** 已武装的超时定时器 id; -1 = 未武装 */
    protected int $timerId = -1;

    /** 是否因超时而中断 */
    protected bool $timedOut = false;

    public function __construct(Socket $socket)
    {
        $this->socket = $socket;
    }

    /**
     * 设置【空闲】超时(秒)
     * "连续 N 秒没有任何 IO 进展"才判超时 —— 每次成功的读/写都会把截止时刻往后推。
     * 与 Httpclient 的 timeout 语义不同(那是总预算), 差异是刻意的: 隧道链路的吞吐会长时间
     * 塌到几十 KB/s, 用总预算卡会把"慢但活着"误判成失败。要总预算请用 setTotalTimeout()。
     * @param float $seconds
     * @return $this
     */
    public function setTimeout(float $seconds)
    {
        $this->idleSeconds = $seconds > 0 ? $seconds : 0;
        $this->idleDeadline = $this->idleSeconds > 0 ? microtime(true) + $this->idleSeconds : 0;
        return $this;
    }

    /**
     * 设置整次请求的总预算(秒); 0 = 不限
     * 与空闲超时同时生效, 先到者触发。给"这条链路再慢也不许超过 N 秒"的场景用。
     * @param float $seconds
     * @return $this
     */
    public function setTotalTimeout(float $seconds)
    {
        $this->totalDeadline = $seconds > 0 ? microtime(true) + $seconds : 0;
        return $this;
    }

    /**
     * 剩余可用时间(秒), 没设任何超时时返回 0
     * 供连接建立等"底层自带超时参数"的调用使用。
     * @return float
     */
    public function remainTimeout(): float
    {
        $left = $this->secondsLeft();
        if ($left === null) return 0;
        return $left > 0 ? $left : 0.001;
    }

    /**
     * 距最近一个截止时刻还剩多少秒; 一个都没设时返回 null
     * @return float|null
     */
    protected function secondsLeft(): ?float
    {
        $now = microtime(true);
        $best = null;
        if ($this->idleDeadline > 0) $best = $this->idleDeadline - $now;
        if ($this->totalDeadline > 0) {
            $t = $this->totalDeadline - $now;
            if ($best === null || $t < $best) $best = $t;
        }
        return $best;
    }

    /**
     * (重新)武装超时定时器
     * 定时器到点时**自己判断是不是真的到期**: 期间若有 IO 进展, 回调只把定时器顺延到新的
     * 截止时刻, 不判超时。这样"推进截止时刻"就只是改一个浮点数(见 touchIo), 不必每次读/写
     * 都 clear+after 一遍 —— 大响应有成千上万次读, 那样就是成千上万次定时器堆操作 + 闭包分配。
     * 代价只是连接活着时每 idleSeconds 醒一次(顺延), 可以忽略。
     * @return void
     */
    protected function scheduleTimer(): void
    {
        if ($this->timerId >= 0) {
            Timer::clear($this->timerId);
            $this->timerId = -1;
        }
        $left = $this->secondsLeft();
        if ($left === null) return;
        if ($left <= 0) {
            $this->markTimedOut();
            return;
        }
        $self = $this;
        $this->timerId = Timer::after((int)ceil($left * 1000), function () use ($self) {
            $self->timerId = -1;
            $left = $self->secondsLeft();
            if ($left !== null && $left > 0) {   // 这期间有过 IO 进展 → 截止时刻被推后了, 顺延
                $self->scheduleTimer();
                return;
            }
            $self->markTimedOut();
        });
    }

    /**
     * IO 有进展时调用: 把空闲截止时刻往后推
     * **只改截止时刻, 不动定时器** —— 这是热路径(大响应成千上万次读), 每次都重排定时器
     * 是纯粹的浪费; 定时器到点时自己会发现"其实还没到期"并顺延(见 scheduleTimer)。
     * @return void
     */
    protected function touchIo(): void
    {
        if ($this->idleSeconds <= 0) return;   // 只有总预算时截止时刻不移动, 无需记
        $this->idleDeadline = microtime(true) + $this->idleSeconds;
    }

    /**
     * 首次进入 IO 时武装定时器(已武装则不动)
     * @return void
     */
    protected function armTimer(): void
    {
        if ($this->timerId >= 0) return;
        if ($this->idleDeadline <= 0 && $this->totalDeadline <= 0) return;
        $this->scheduleTimer();
    }

    /**
     * 标记超时并捅醒挂起的 IO
     * @return void
     */
    protected function markTimedOut(): void
    {
        if ($this->timedOut) return;
        $this->timedOut = true;
        $this->socket->shutdown();
    }

    /**
     * IO 失败后判断是否已经过了 deadline
     * 兜底用: 定时器回调与 IO 返回在同一时刻竞争时, 可能返回时标志还没置上。
     * @return void
     */
    protected function checkDeadline(): void
    {
        if ($this->timedOut) return;
        $left = $this->secondsLeft();
        if ($left !== null && $left <= 0) $this->timedOut = true;
    }

    /**
     * 读一批隧道密文
     * @param int $max
     * @return string|false
     */
    public function read(int $max = OpenSsl::BUFFER_SIZE)
    {
        $this->armTimer();
        $data = $this->socket->recv($max);
        if ($data === '' || $data === false) {
            $this->checkDeadline();
            return false;
        }
        $this->touchIo();
        return $data;
    }

    /**
     * 把数据全部写出去
     * 用 send 自循环而不用 sendAll: sendAll 一旦挂起, cancel 打不断它(实测挂死),
     * 只能靠本类的超时定时器 shutdown 捅醒 —— 而 shutdown 之后 sendAll 的返回值不可靠
     * (实测返回了一个已写字节数而不是 false)。自己循环 + 每次检查进度更可控。
     * @param string $data
     * @return bool
     */
    public function write(string $data): bool
    {
        $this->armTimer();
        $len = strlen($data);
        $off = 0;
        while ($off < $len) {
            $n = $this->socket->send($off === 0 ? $data : substr($data, $off));
            if ($n === false || $n <= 0) {
                $this->checkDeadline();
                return false;
            }
            $off += $n;
            $this->touchIo();
        }
        return true;
    }

    /**
     * 是否已超时
     * @return bool
     */
    public function timedOut(): bool
    {
        return $this->timedOut;
    }

    /**
     * 释放资源
     * @return void
     */
    public function close(): void
    {
        if ($this->timerId >= 0) {
            Timer::clear($this->timerId);
            $this->timerId = -1;
        }
        $this->socket->close();
    }
}
