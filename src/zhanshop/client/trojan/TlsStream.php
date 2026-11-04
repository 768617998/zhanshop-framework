<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / trojan/TlsStream.php    [ 2026/10/12 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
// | 一层 TLS, 用 FFI 直调 libssl + memory BIO 手动驱动 —— 因此可以递归嵌套。
// |
// | 数据流(以「到 trojan 节点」这层为例):
// |   明文  --SSL_write-->  wbio(memory BIO)  --flushWbio-->  Transport::write  --> socket
// |   socket --Transport::read--> feedRbio --> rbio(memory BIO) --SSL_read--> 明文
// |
// | 为什么必须 memory BIO 而不能让 libssl 直接管 socket: libssl 直连 fd 时会自己做阻塞读写,
// |   那会把整个 reactor 线程钉住(本项目的铁律是全程非阻塞)。memory BIO 把"传输"交还给 PHP,
// |   于是可以换成任意 Transport —— 包括另一层 TLS 的明文流。
// |
// | 内存: SSL_set_bio 之后 rbio/wbio **归 SSL 所有**, SSL_free 会连带释放它们。
// |   故本类绝不单独 BIO_free(会 double free)。
// |
// | ctx 的归属(2026-10-12 改): SSL_CTX **不再由本类新建**, 来自 OpenSsl::clientCtx 的
// |   进程级缓存 —— 所以 close() 也**不释放**它, 只断引用。理由与实测数据见 OpenSsl::clientCtx。
// |
// | ⚠ 缓冲区一律用 $ffi->new() 而**不是** FFI::new(): PHP 8.3 起把 FFI::new/type/cast/arrayType
// |   的**静态**调用标为 deprecated, 会发 E_DEPRECATED。而 zhanshop 的 Error::errorHandler 是
// |   拿 E_ALL 注册的, 它把任何错误(含 deprecated)**一律抛成异常** —— 异常再被
// |   TrojanClient::execute() 的 catch(\Throwable) 接住变成 code=false。于是现象是"访问不了
// |   外网", 真实原因却是 PHP 版本告警, 排障时看不到 (2026-10-12 实测: 修好构造函数的第 68 行
// |   后, 报错点顺着握手路径挪到 204 行, 逐个修完才通)。实例调用 FFI::string/memcpy 等不受影响。
// |   已实测到 **8.5.9 仍是同一状态**(实例调用无告警 / 静态调用报 deprecated, 同一份代码在 8.3 与
// |   8.5 上表现一致), 所以**不要**为此按 PHP 版本分支。注意社区里还有一版方向相反的 RFC
// |   (弃用实例调用、改用新的 FFI\CDef / FFI::Scope), 它没有落地 —— 别照它改回来。
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client\trojan;

use FFI;
use RuntimeException;

class TlsStream implements Transport
{
    protected FFI $ffi;
    protected Transport $transport;

    protected $ssl = null;   // SSL*
    protected $rbio = null;  // BIO*  密文流入
    protected $wbio = null;  // BIO*  密文流出
    protected $ctx = null;   // SSL_CTX*

    /** 是否校验对端证书链 + 主机名 */
    protected bool $verify;

    /** 握手用的读缓冲(复用, 避免每次 FFI::new) */
    protected $inBuf = null;

    public function __construct(Transport $transport, string $sni = '', bool $verify = true)
    {
        $this->ffi = OpenSsl::ffi();
        $this->transport = $transport;
        $this->verify = $verify;

        $f = $this->ffi;
        // ctx 走 OpenSsl 的**进程级缓存**, 不在这里新建。原因: 每次新建 ctx 都要重新解析
        // 系统信任库(实测 3.32ms / 713KB, 占该层每请求 CPU 的 80%, 而结果逐字节相同)。
        // 校验开关也由缓存分档承担, 这里不再重复设置 —— 详见 OpenSsl::clientCtx 的注释。
        $this->ctx = OpenSsl::clientCtx($verify);
        $this->ssl = $f->SSL_new($this->ctx);
        $this->rbio = $f->BIO_new($f->BIO_s_mem());
        $this->wbio = $f->BIO_new($f->BIO_s_mem());
        $f->SSL_set_bio($this->ssl, $this->rbio, $this->wbio);
        if ($sni !== '') {
            // FFI::cdef 表达不了宏, 55 是 SSL_CTRL_SET_TLSEXT_HOSTNAME(见 OpenSsl)
            $f->SSL_ctrl($this->ssl, OpenSsl::SSL_CTRL_SET_TLSEXT_HOSTNAME, 0, $sni);
            if ($verify) $f->SSL_set1_host($this->ssl, $sni); // 主机名与证书 SAN/CN 匹配
        }
        $this->inBuf = $f->new('char[' . OpenSsl::BUFFER_SIZE . ']');   // 必须 $f->new 而非 FFI::new, 见类注释
    }

    /**
     * 完成 TLS 握手
     * 失败一律抛异常 —— 证书不被信任、协议谈不拢这类问题必须让调用方看得见,
     * 静默当成"空响应"只会让人查半天。
     * @return void
     */
    public function handshake(): void
    {
        $f = $this->ffi;
        for (;;) {
            $ret = $f->SSL_connect($this->ssl);
            if ($ret == 1) {
                if ($this->verify) {
                    $v = $f->SSL_get_verify_result($this->ssl);
                    if ($v !== OpenSsl::X509_V_OK) {
                        throw new RuntimeException('TLS 证书校验失败 verify=' . $v);
                    }
                }
                return;
            }
            $err = $f->SSL_get_error($this->ssl, $ret);
            if ($err == OpenSsl::SSL_ERROR_WANT_READ) {
                $this->flushWbio();
                $d = $this->transport->read();
                if ($d === false) throw new RuntimeException($this->closeReason('TLS 握手期间传输层关闭'));
                $this->feedRbio($d);
            } elseif ($err == OpenSsl::SSL_ERROR_WANT_WRITE) {
                $this->flushWbio();
            } else {
                throw new RuntimeException('TLS 握手失败 err=' . $err);
            }
        }
    }

    /**
     * 读一批明文
     * @param int $max
     * @return string|false
     */
    public function read(int $max = OpenSsl::BUFFER_SIZE)
    {
        $f = $this->ffi;
        if ($max > OpenSsl::BUFFER_SIZE) $max = OpenSsl::BUFFER_SIZE;
        $buf = $max === OpenSsl::BUFFER_SIZE ? $this->inBuf : $f->new("char[$max]");
        for (;;) {
            $ret = $f->SSL_read($this->ssl, $buf, $max);
            if ($ret > 0) return FFI::string($buf, $ret);
            $err = $f->SSL_get_error($this->ssl, $ret);
            if ($err == OpenSsl::SSL_ERROR_WANT_READ) {
                $this->flushWbio();
                $d = $this->transport->read();
                if ($d === false) return false; // 对端关闭 / 本层超时
                $this->feedRbio($d);
            } elseif ($err == OpenSsl::SSL_ERROR_WANT_WRITE) {
                $this->flushWbio();
            } elseif ($err == OpenSsl::SSL_ERROR_ZERO_RETURN) {
                return false; // 对端 close_notify
            } else {
                throw new RuntimeException('SSL_read 失败 err=' . $err);
            }
        }
    }

    /**
     * 写一批明文
     * @param string $data
     * @return bool
     */
    public function write(string $data): bool
    {
        $f = $this->ffi;
        $buf = $f->new('char[' . OpenSsl::BUFFER_SIZE . ']');
        $len = strlen($data);
        $off = 0;
        while ($off < $len) {
            $n = $len - $off;
            if ($n > OpenSsl::BUFFER_SIZE) $n = OpenSsl::BUFFER_SIZE;
            FFI::memcpy($buf, substr($data, $off, $n), $n);
            $ret = $f->SSL_write($this->ssl, $buf, $n);
            if ($ret > 0) {
                $off += $ret;
                continue;
            }
            $err = $f->SSL_get_error($this->ssl, $ret);
            if ($err == OpenSsl::SSL_ERROR_WANT_READ) {
                $this->flushWbio();
                $d = $this->transport->read();
                if ($d === false) return false;
                $this->feedRbio($d);
            } elseif ($err == OpenSsl::SSL_ERROR_WANT_WRITE) {
                $this->flushWbio();
            } else {
                throw new RuntimeException('SSL_write 失败 err=' . $err);
            }
        }
        return true;
    }

    /**
     * 是否已超时(本质是下层的超时)
     * @return bool
     */
    public function timedOut(): bool
    {
        return $this->transport->timedOut();
    }

    /**
     * 释放资源
     * @return void
     */
    public function close(): void
    {
        if ($this->ssl !== null) {
            $this->ffi->SSL_free($this->ssl); // 连带释放 rbio/wbio, 不要再单独 BIO_free
            $this->ssl = null;
            $this->rbio = null;
            $this->wbio = null;
        }
        // ⚠ 这里**绝不能** SSL_CTX_free —— ctx 来自 OpenSsl::clientCtx 的进程级缓存, 全进程
        //   共用同一个。释放它 = 把别的连接(可能正在握手)脚下的 ctx 抽掉, 后果是随机的
        //   use-after-free。只断本对象的引用, 所有权留在 OpenSsl。
        //   (历史: 2026-10-12 之前这里是 SSL_CTX_free, 因为那时 ctx 是本对象私有的。)
        $this->ctx = null;
    }

    /**
     * 把 wbio 里攒着的密文全部推给传输层
     * @return void
     */
    protected function flushWbio(): void
    {
        $f = $this->ffi;
        $buf = $f->new('char[' . OpenSsl::BUFFER_SIZE . ']');
        while ($f->BIO_ctrl($this->wbio, OpenSsl::BIO_CTRL_PENDING, 0, null) > 0) {
            $n = $f->BIO_read($this->wbio, $buf, OpenSsl::BUFFER_SIZE);
            if ($n <= 0) break;
            if (!$this->transport->write(FFI::string($buf, $n))) {
                throw new RuntimeException($this->closeReason('TLS 密文写出失败'));
            }
        }
    }

    /**
     * 把收到的密文喂进 rbio
     * @param string $d
     * @return void
     */
    protected function feedRbio(string $d): void
    {
        $len = strlen($d);
        $buf = $this->ffi->new("char[$len]");
        FFI::memcpy($buf, $d, $len);
        $this->ffi->BIO_write($this->rbio, $buf, $len);
    }

    /**
     * 传输层关闭时的错误文案(区分超时与其他原因)
     * @param string $prefix
     * @return string
     */
    protected function closeReason(string $prefix): string
    {
        return $prefix . ($this->transport->timedOut() ? '(IO 超时)' : '');
    }
}
