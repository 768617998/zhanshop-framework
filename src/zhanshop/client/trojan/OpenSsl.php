<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / trojan/OpenSsl.php    [ 2026/10/12 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
// | libssl/libcrypto 的最小 FFI 绑定 + 用到的常量。
// |
// | FFI::cdef 只能写"声明"(typedef + 函数原型), **表达不了宏** —— 所以 openssl 头文件里
// |   那些 #define 出来的常量只能在这里写死数值, 逐个注明出处。改一处都要回头文件核对。
// |
// | 符号从哪来: FFI::cdef 第二参传 null = 用进程全局符号表(RTLD_DEFAULT)解析, 不需要
// |   dlopen 具体的 .so。实测本机 **php 可执行文件自身**就链了 libssl.so.3/libcrypto.so.3
// |   (ldd 可见), 所以不需要 openssl/curl 扩展在位 —— `php -n` 下符号照样解析成功。
// |   换环境若报 "undefined symbol", 先确认进程里确实加载了 libssl。
// |
// | 另: 客户端的 SSL_CTX 走**进程级缓存**(见 clientCtx)。理由是实测出来的 —— 每次
// |   SSL_CTX_set_default_verify_paths 都要把系统信任库整个解析一遍(本机 180KB / 122 张
// |   根证书 → 常驻 713KB、耗时 3.22ms), 而结果逐字节相同。原先每个 TlsStream 都重来
// |   一遍, 实测占掉那一层每请求 CPU 的 80%。完整数据见 clientCtx 的注释。
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client\trojan;

use FFI;

class OpenSsl
{
    /** SSL_ctrl 的 SSL_CTRL_SET_TLSEXT_HOSTNAME —— 用于设置 SNI */
    public const SSL_CTRL_SET_TLSEXT_HOSTNAME = 55;

    /** BIO_ctrl 的 BIO_CTRL_PENDING —— 查询 BIO 里还有多少可读字节 */
    public const BIO_CTRL_PENDING = 10;

    /** SSL_get_error 的返回值 */
    public const SSL_ERROR_WANT_READ = 2;   // 需要更多密文: flush 待发数据后从传输层取
    public const SSL_ERROR_WANT_WRITE = 3;  // 先把待发密文 flush 出去
    public const SSL_ERROR_ZERO_RETURN = 6; // 对端发来 close_notify, 相当于正常 EOF

    /** SSL_CTX_set_verify 的 mode */
    public const SSL_VERIFY_PEER = 1;

    /** SSL_get_verify_result 的 X509_V_OK */
    public const X509_V_OK = 0;

    /** 每次从 BIO/传输层搬运的字节数 */
    public const BUFFER_SIZE = 8192;

    /** FFI 句柄(懒加载单例: cdef 解析一次就够, 全进程复用) */
    private static ?FFI $ffi = null;

    /**
     * 客户端 SSL_CTX 的进程级缓存, 按是否校验分两档: [0] = verify=false, [1] = verify=true
     * 见 clientCtx() —— 这张表就是本次改动的全部意义所在。
     * @var array<int, mixed>
     */
    private static array $clientCtx = [];

    /**
     * 是否具备 FFI 能力
     *
     * 没有 FFI 时 TrojanClient 访问 https 目标是做不到的(swoole 单 SSL 对象叠不出两层 TLS),
     * 所以这里要能被外部查询 —— 宁可明确报错, 也不要静默降级成"只支持明文目标"。
     * FFI 扩展缺失时 class_exists('FFI') 为 false; 扩展在但 ffi.enable 没开时,
     * 要等真正调 FFI::cdef 才抛 FFI\Exception —— 两种都要算"不可用"。
     * @return bool
     */
    public static function available(): bool
    {
        if (!class_exists(FFI::class)) return false;
        try {
            self::ffi();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 取得 FFI 句柄
     * @return FFI
     */
    public static function ffi(): FFI
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef(<<<'CDEF'
typedef struct ssl_st SSL;
typedef struct ssl_ctx_st SSL_CTX;
typedef struct bio_st BIO;
typedef struct bio_method_st BIO_METHOD;
typedef struct ssl_method_st SSL_METHOD;
typedef struct x509_store_ctx_st X509_STORE_CTX;
const SSL_METHOD *TLS_client_method(void);
SSL_CTX *SSL_CTX_new(const SSL_METHOD *meth);
void SSL_CTX_free(SSL_CTX *ctx);
SSL *SSL_new(SSL_CTX *ctx);
void SSL_free(SSL *ssl);
void SSL_set_bio(SSL *ssl, BIO *rbio, BIO *wbio);
long SSL_ctrl(SSL *ssl, int cmd, long larg, void *parg);
int SSL_connect(SSL *ssl);
int SSL_read(SSL *ssl, void *buf, int num);
int SSL_write(SSL *ssl, const void *buf, int num);
int SSL_get_error(const SSL *ssl, int ret);
int SSL_shutdown(SSL *ssl);
void SSL_CTX_set_verify(SSL_CTX *ctx, int mode, int (*verify_callback)(int, X509_STORE_CTX *));
int SSL_CTX_set_default_verify_paths(SSL_CTX *ctx);
long SSL_get_verify_result(const SSL *ssl);
int SSL_set1_host(SSL *s, const char *hostname);
BIO *BIO_new(const BIO_METHOD *type);
const BIO_METHOD *BIO_s_mem(void);
int BIO_read(BIO *b, void *buf, int len);
int BIO_write(BIO *b, const void *buf, int len);
long BIO_ctrl(BIO *bp, int cmd, long larg, void *parg);
int BIO_free(BIO *a);
CDEF
        );
        }
        return self::$ffi;
    }

    /**
     * 取一个**进程级复用**的客户端 SSL_CTX(按是否校验分档)
     *
     * 为什么必须共享而不是每层 TLS 新建一个 —— 实测数据(本地 TLS 服务端 + 真证书校验,
     * SSL_CERT_FILE 指向「系统库 122 张 + 本地 CA」共 123 张, 与生产同量级, 各 50 次):
     *
     *   拆开量 SSL_CTX 的构造:
     *     SSL_CTX_new                                   0.103 ms   (3.1%)
     *     + SSL_CTX_set_verify(PEER)                    +0 ms
     *     + SSL_CTX_set_default_verify_paths            +3.217 ms  (96.9%)
     *     ----------------------------------------------------------
     *     合计                                          3.320 ms
     *   即 **代价几乎全在重新解析系统信任库**上, 而这 122 张证书每次一模一样。
     *   本机 /etc/ssl/certs/ca-certificates.crt = 180KB, 解析成 X509 对象后常驻 713.3 KB
     *   (SSL_CTX_new 本身只 20.9 KB)。
     *
     *   同一目标两臂对撞(同一 CA 库):
     *     每请求新建 ctx: 每请求 CPU 4.164 ms  ┐ 真正干活的只有 0.505ms
     *     全进程共享 ctx: 每请求 CPU 0.505 ms  ┘ → 浪费是它的 **7.2 倍**
     *   https 目标叠两层 TLS → 每请求白烧 ~7.0 ms CPU。
     *
     * 三个后果一起消失(同一个根因):
     *   ① CPU: 上面那 96.9%; 缓存后每层只 0.003ms
     *   ② 内存: 每层 713.3 KB → parallel(1000) 时瞬时 0.70 GB(两层目标 1.4GB),
     *      而本机可用内存只有约 3GB。缓存后全进程只留一份
     *   ③ **阻塞调度器**(这条最隐蔽): 那 3.2ms 是 libssl 内部的 libc fopen/fread,
     *      **swoole 的 file hook 只接管 PHP 层文件函数, 管不到它** → 校准探针实测心跳
     *      最大空档 5.21ms(★阻塞), 而 verify=false 分支只有 1.36ms(正常让出)。
     *
     * ⚠ 所有权: 缓存的 ctx **全进程共用、永不释放**(进程退出由内核回收)。
     *   因此 TlsStream::close() **绝不能**再 SSL_CTX_free 它 —— 那会把别的连接正在用的
     *   ctx 从脚下抽掉。这是本次改动最需要盯住的一处, 见 TlsStream::close() 的注释。
     *
     * 副作用(已向用户披露并同意): ctx 共享后 OpenSSL 的客户端会话缓存生效, 跨请求可以
     *   **复用 TLS 会话**(省一次完整握手)。同一 ctx 服务不同主机时的"错主机复用"由
     *   OpenSSL 依 RFC 6066 按 SNI 拒绝 —— 已实测: 同 ctx 先与 localhost 建会话, 再以
     *   alt.localhost 握手时 SSL_session_reused 仍为 0。
     *
     * @param bool $verify 是否校验对端证书链(关掉等于不设防, 只用于调试)
     * @return mixed SSL_CTX*
     */
    public static function clientCtx(bool $verify)
    {
        $k = $verify ? 1 : 0;
        if (array_key_exists($k, self::$clientCtx)) return self::$clientCtx[$k];

        $f = self::ffi();
        $ctx = $f->SSL_CTX_new($f->TLS_client_method());
        if ($ctx === null) throw new \RuntimeException('SSL_CTX_new 失败(内存不足?)');
        if ($verify) {
            $f->SSL_CTX_set_verify($ctx, self::SSL_VERIFY_PEER, null); // 校验对端证书链
            $f->SSL_CTX_set_default_verify_paths($ctx);                // 用系统信任库
        }
        self::$clientCtx[$k] = $ctx;
        return $ctx;
    }
}
