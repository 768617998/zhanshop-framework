<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / trojan/Transport.php    [ 2026/10/12 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
// | 隧道传输层抽象。
// |
// | 嵌套 TLS 全靠这一层递归: 外层 TLS(到 trojan 节点)的传输层是 socket,
// |   内层 TLS(到 https 目标)的传输层是「外层 TLS 解出来的明文流」。两层实现同一个接口,
// |   于是能任意叠 —— 这正是 swoole 的 Swoole\Coroutine\Socket 做不到的地方(一个 socket
// |   只挂一个 SSL 对象, 第二次 sslHandshake 必失败)。
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client\trojan;

interface Transport
{
    /**
     * 读一批数据
     * 返回字符串 = 读到的字节; 返回 false = 读不到了(对端关闭 / 出错 / 超时)。
     * 三种"读不到"要区分时看 timedOut(), 它是唯一可靠的判据 ——
     * 底层超时是靠 shutdown() 捅醒挂起的 IO, 那会让 recv 返回 ''(与对端正常关闭一模一样)。
     * @param int $max 最多读多少字节
     * @return string|false
     */
    public function read(int $max = OpenSsl::BUFFER_SIZE);

    /**
     * 把数据全部写出(协程挂起直到写完或失败)
     * @param string $data
     * @return bool 全部写完为 true
     */
    public function write(string $data): bool;

    /**
     * 这条传输层是否已因超时而中断
     * @return bool
     */
    public function timedOut(): bool;

    /**
     * 释放资源(可重复调用)
     * @return void
     */
    public function close(): void;
}
