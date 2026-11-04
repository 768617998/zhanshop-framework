<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / trojan/ChunkedBody.php    [ 2026/10/12 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
// | Transfer-Encoding: chunked 的增量解码器。
// |
// | 要增量的原因: 隧道里字节是一批批到的, chunk 头可能被切断在两个批次之间(实测很常见),
// |   所以状态必须跨批次保留 —— 不能等全部收完再一次性解。
// | 用法: 每收到一批原始字节就 feed() 一次, 返回值是本次新解出的明文(可直接拼 body 或写文件)。
// |
// | 大小写: chunk-size 用十六进制(0-9a-fA-F), 十六进制里的字母大小写都合法;
// |   chunk 扩展(`;ext=val`)按 RFC 7230 直接忽略。
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client\trojan;

class ChunkedBody
{
    protected const STATE_SIZE = 0;      // 等 chunk-size 行
    protected const STATE_DATA = 1;      // 等 chunk 数据
    protected const STATE_DATA_END = 2;  // 等 chunk 数据后的 CRLF
    protected const STATE_TRAILER = 3;   // 等 trailer(以空行结束)
    protected const STATE_DONE = 4;

    protected string $buffer = '';
    protected int $state = self::STATE_SIZE;
    protected int $remain = 0;
    protected bool $done = false;
    protected bool $bad = false;

    /**
     * 喂入一批原始字节, 取回新解出的明文
     * @param string $data
     * @return string
     */
    public function feed(string $data): string
    {
        $this->buffer .= $data;
        $out = '';
        while (true) {
            if ($this->state === self::STATE_SIZE) {
                $pos = strpos($this->buffer, "\r\n");
                if ($pos === false) break;
                $line = substr($this->buffer, 0, $pos);
                $this->buffer = substr($this->buffer, $pos + 2);
                $semi = strpos($line, ';');
                if ($semi !== false) $line = substr($line, 0, $semi); // 丢掉 chunk 扩展
                $line = trim($line);
                if ($line === '' || !ctype_xdigit($line)) {
                    $this->bad = true;
                    $this->finish();
                    break;
                }
                $size = intval($line, 16);
                if ($size === 0) {          // 结束 chunk: 后面只剩 trailer
                    $this->state = self::STATE_TRAILER;
                    continue;
                }
                $this->remain = $size;
                $this->state = self::STATE_DATA;
                continue;
            }
            if ($this->state === self::STATE_DATA) {
                if ($this->buffer === '') break;
                $have = strlen($this->buffer);
                $n = $this->remain < $have ? $this->remain : $have;
                $out .= substr($this->buffer, 0, $n);
                $this->buffer = substr($this->buffer, $n);
                $this->remain -= $n;
                if ($this->remain === 0) $this->state = self::STATE_DATA_END;
                continue;
            }
            if ($this->state === self::STATE_DATA_END) {
                if (strlen($this->buffer) < 2) break;
                $this->buffer = substr($this->buffer, 2); // 吃掉 CRLF
                $this->state = self::STATE_SIZE;
                continue;
            }
            if ($this->state === self::STATE_TRAILER) {
                $pos = strpos($this->buffer, "\r\n");
                if ($pos === false) break;
                $line = substr($this->buffer, 0, $pos);
                $this->buffer = substr($this->buffer, $pos + 2);
                if ($line === '') $this->finish(); // 空行 = trailer 结束
                continue;
            }
            break; // STATE_DONE
        }
        return $out;
    }

    /**
     * 是否已解完(遇到 0 chunk 且 trailer 收完)
     * @return bool
     */
    public function done(): bool
    {
        return $this->done;
    }

    /**
     * 是否遇到了畸形的 chunk 头
     * @return bool
     */
    public function bad(): bool
    {
        return $this->bad;
    }

    /**
     * @return void
     */
    protected function finish(): void
    {
        $this->done = true;
        $this->state = self::STATE_DONE;
    }
}
