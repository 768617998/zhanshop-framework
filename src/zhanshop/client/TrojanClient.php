<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / TrojanClient.php    [ 2026/10/12 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
// | trojan 协议的 HTTP 客户端 —— 对外 API 与同目录的 Httpclient 一致, 只是把"直连目标"
// | 换成"经 trojan 节点中转"。
// |
// | 与 Httpclient 的关系: 用法一样(new -> setHeader/setCookie/setTimeout -> request),
// |   返回的也是同一张表(code/cookie/header/body/dnstime/dnsip/runtime)。差别在两点:
// |     · 目标是 https 时, 隧道里装的是目标站点的原始 TLS 流, 所以要在隧道上**再叠一层 TLS**
// |       (嵌套 TLS)。而 swoole 的 Swoole\Coroutine\Socket 一个 socket 只挂一个 SSL 对象,
// |       第二次 sslHandshake 必失败 —— 这就是本类必须依赖 FFI 直调 libssl 的原因。
// |     · 目标域名**不在这里解析**, 而是编进 trojan 头的 ATYP 字段, 交给节点去解析(域名不出
// |       本地 DNS, 这是 trojan 的设计也是它的隐私属性)。所以结果里的 dnsip/dnstime 描述的是
// |       **节点**的地址与解析耗时, 不是目标的。
// |
// | 前置依赖: PHP 需有 FFI 扩展且 ffi.enable=true(它默认是 preload —— 只在 CLI 生效, 跑在
// |   php-fpm 下会抛 "FFI API is restricted", 必须显式写成 true)。检测见
// |   \zhanshop\client\trojan\OpenSsl::available()
// |
// | 未实现(刻意, 不是漏): httpproxy() / socks5() / unix()。trojan 隧道本身就是一个代理,
// |   再往隧道里套代理没有意义; unix 域套接字也不经 trojan 节点。
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client;

use Swoole\Coroutine;
use Swoole\Coroutine\Socket;
use Swoole\Coroutine\WaitGroup;
use zhanshop\App;
use zhanshop\Log;
use zhanshop\client\trojan\ChunkedBody;
use zhanshop\client\trojan\MultipartBody;
use zhanshop\client\trojan\OpenSsl;
use zhanshop\client\trojan\SocketStream;
use zhanshop\client\trojan\TlsStream;

class TrojanClient
{
    /** 响应头的长度上限(防畸形响应把内存吃光) */
    protected const MAX_HEADER_SIZE = 65536;

    /** 响应体长度上限的默认值(字节); 见 options.max_body_size */
    protected const DEFAULT_MAX_BODY_SIZE = 33554432;   // 32MB

    protected $config = [
        "enable_ipv6" => false,
        'options' => [
            // 空闲超时(秒): 连续这么久没有任何 IO 进展才判失败。**不是**整条请求的总预算
            // (见下面 setTimeout 的说明); 要总预算用 total_timeout。
            'timeout' => 30,
            // 整条请求的总预算(秒); 0 = 不限
            'total_timeout' => 0,
            // 响应体上限(字节); <=0 = 不限。两条路都受它管:
            //   · 线上字节: 读的过程中超过即失败(否则 `$body .=` 会被对端喂到把内存吃光)
            //   · 解压后字节: 作为解压函数的上限, 让 zip 炸弹(几十 KB 解出几 GB)在接近上限时中止
            // 落盘的 download() 不受限(它是流式写文件, 内存不随体增长)。
            'max_body_size' => self::DEFAULT_MAX_BODY_SIZE,
            // 流式响应回调; 不设 = 体攒在内存里由 result()['body'] 交出。
            //   设了之后每解出一批体就调一次 function(TrojanClient $client, string $data): bool,
            //   即收即交、不攒整份(result()['body'] 恒为空串); 回调返回 false = 要求中止。
            //   回调签名与 Httpclient 的 write_func 一致, 所以 relay 那侧换客户端不用改回调。
            //   三处刻意的差异(都是流式语义的必然结果, 不是漏):
            //     · 走 Accept-Encoding: identity —— 回调拿到的是**线上字节**, 不替调用方解压
            //     · 不重试、不跟重定向 —— 体已经吐出去了, 再来一次就是把同一份内容吐两遍
            //     · 与 download() 落盘互斥 —— 一个响应体只能有一个落点
            //   半截响应在流式路径上**同样报错**(不会因为"体不留在内存"就放松完整性判据)。
            // 'write_func' => function($client, $data){ ... },
            // 注: 这里原有一个 'keep_alive' => false, 已删(2026-10-12)。它是个**死键** ——
            // 全库没有任何读取点, 设了不生效也不报错, 只会让人以为能开长连接。
            // 隧道固定 Connection: close(一次请求一条隧道, 见 execute()), 这是刻意的。
        ],
        // trojan 节点
        'node' => [
            'host' => '',
            'port' => 443,
            'password' => '',
            'tls' => true,      // 到节点这一跳是否 TLS(标准 trojan = true; 明文调试可关)
            'verify' => true,   // 是否校验节点证书(默认校验, 关了等于不设防)
        ],
        'cookie' => [],
        'header' => [],
        'uploadfile' => [], // 上传文件对象
        'uploaddata' => [], // 上传文件内容
        'useragent' => 'Mozilla/5.0 (Windows NT 11.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
        'debug' => false,
        'ipresolve' => 0,   // 1=ipv4 2=ipv6 0=跟随 enable_ipv6
        'referer' => '',
        'maxredirs' => 0,   // 0=不跟随重定向(与 Httpclient 的 request 行为一致)
    ];

    /** 最后一次响应的状态码(连不上/超时为 0) */
    protected int $lastStatus = 0;

    /** 最后一次失败原因(成功时为空串) */
    protected string $lastError = '';

    /**
     * @param string $host trojan 节点地址(域名或 IP)
     * @param int $port 节点端口
     * @param string $password trojan 密码
     */
    public function __construct(string $host = '', int $port = 443, string $password = '')
    {
        if ($host !== '') $this->config['node']['host'] = $host;
        if ($password !== '') $this->config['node']['password'] = $password;
        $this->config['node']['port'] = $port;
    }

    /**
     * 设置 trojan 节点
     * @param string $host 节点地址(域名或 IP)
     * @param int $port 节点端口
     * @param string $password trojan 密码
     * @return $this
     */
    public function setNode(string $host, int $port, string $password)
    {
        $this->config['node']['host'] = $host;
        $this->config['node']['port'] = $port;
        $this->config['node']['password'] = $password;
        return $this;
    }

    /**
     * 到节点这一跳是否走 TLS
     * @param bool $tls
     * @return $this
     */
    public function setTls(bool $tls = true)
    {
        $this->config['node']['tls'] = $tls;
        return $this;
    }

    /**
     * 是否校验对端(节点 + 目标)证书
     * @param bool $verify
     * @return $this
     */
    public function setVerify(bool $verify = true)
    {
        $this->config['node']['verify'] = $verify;
        return $this;
    }

    /**
     * 最后一次响应的状态码(失败为 0)
     * @return int
     */
    public function getStatus(): int
    {
        return $this->lastStatus;
    }

    /**
     * 最后一次的失败原因(成功时为空串)
     * Httpclient 只给得出 code=false, 排障时看不出是超时还是证书错误 —— 这个补上。
     * @return string
     */
    public function getError(): string
    {
        return $this->lastError;
    }

    /**
     * 开启调试日志
     * @return $this
     */
    public function debug()
    {
        $this->config['debug'] = true;
        return $this;
    }

    /**
     * 启用IPV6
     * @return $this
     */
    public function enableIpv6()
    {
        $this->config['enable_ipv6'] = true;
        return $this;
    }

    /**
     * 设置参数
     * @param string $field
     * @param mixed $value
     * @return $this
     */
    public function setOptions(string $field, mixed $value)
    {
        $this->config['options'][$field] = $value;
        return $this;
    }

    /**
     * 使用HTTP认证
     * @param $accountPassword
     * @return $this
     */
    public function userPwd($accountPassword = "")
    {
        $this->setHeader("authorization", "Basic ".base64_encode($accountPassword));
        return $this;
    }

    /**
     * 设置【空闲】超时时间(毫秒)
     * 语义: 连续这么久没有任何 IO 进展才判失败, 每次读到/写出数据都会把计时往后推。
     * 与 Httpclient::setTimeout 不同(Httpclient 那个是整条请求的总预算), 差异是刻意的:
     * 经 trojan 节点访问外网时单连接吞吐会长时间塌到 45~60KB/s(512KB 要 8.6~11.5 秒),
     * 但字节一直在到(实测最狠的一次里, 相邻两次读的最大间隔只有 0.282 秒)。用总预算卡
     * 会把这种"慢但活着"的连接误判成失败 —— 这正是"默认 3 秒经常超时"的真因。
     * 要总预算请用 setTotalTimeout()。
     * @param int $timeout 毫秒
     * @return $this
     */
    public function setTimeout(int $timeout = 30000){
        $this->setOptions('timeout', $timeout / 1000);
        return $this;
    }

    /**
     * 设置整条请求的总预算(毫秒); 0 = 不限
     * 与空闲超时同时生效, 先到者触发。给"再慢也不许超过 N 秒"的场景用。
     * @param int $timeout 毫秒
     * @return $this
     */
    public function setTotalTimeout(int $timeout = 0){
        $this->setOptions('total_timeout', $timeout / 1000);
        return $this;
    }

    /**
     * 设置请求头参数
     * @param string $key
     * @param string $val
     * @return $this
     */
    public function setHeader(string $key, string $val){
        $this->config['header'][$key] = $val;
        return $this;
    }

    /**
     * 设置curl请求cookie
     * @param string $key
     * @param string $val
     * @return $this
     */
    public function setCookie(string $key, string $val){
        $this->config['cookie'][$key] = $val;
        return $this;
    }

    /**
     * 设置上传文件
     * @param string $key 表单的名称【必选参数，FILES 参数中的 key】
     * @param string $filePath 文件的路径【必选参数，不能为空文件或者不存在的文件】
     * @param string $type 文件的 MIME 格式，【可选参数，底层会根据文件的扩展名自动推断】
     * @param string $filename 文件名称【可选参数】
     * @param int $offset 上传文件的偏移量【可选参数，可以指定从文件的中间部分开始传输数据。此特性可用于支持断点续传。】
     * @param int $length 发送数据的尺寸【可选参数】
     * @return void
     */
    public function setUploadFile(string $key, string $filePath, string $type = '', string $filename = '', int $offset = 0, int $length = 0){
        $data = [
            'path' => $filePath,
            'type' => $type,
            'filename' => $filename,
            'offset' => $offset,
            'length' => $length,
        ];
        $this->config['uploadfile'][$key] = $data;
    }

    /**
     * 设置上传文件内容
     * @param string $name 表单的名称【必选参数，$_FILES 参数中的 key】
     * @param string $data 数据内容【必选参数】
     * @param string $mimeType 文件的 MIME 格式【可选参数，默认为 application/octet-stream】
     * @param string $filename 文件名称【可选参数，默认为 $name】
     * @return void
     */
    public function uploadData(string $name, string $data, string $mimeType = '', string $filename = '')
    {
        $uploadData = [
            'data' => $data,
            'mimeType' => $mimeType,
            'filename' => $filename
        ];
        $this->config['uploaddata'][$name] = $uploadData;
    }

    /**
     * 设置浏览器UserAgent信息
     * @param string $useragent
     * @return $this
     */
    public function setUseragent(string $useragent){
        $this->config['useragent'] = $useragent;
        return $this;
    }

    /**
     * 解析方式仅支持【1:ipv4|2:ipv6】
     * 作用于**节点**域名的解析(目标域名由节点去解析, 见类注释)。
     * @param int $ipresolve
     * @return $this
     */
    public function setIpresolve(int $ipresolve){
        $this->config['ipresolve'] = $ipresolve;
        return $this;
    }

    /**
     * 伪造http访问来源地址
     * @param string $url
     * @return $this
     */
    public function setReferer(string $url){
        $this->config['referer'] = $url;
        return $this;
    }

    /**
     * 设置请求中最大跳转次数
     * 0(默认)=不跟随, 与 Httpclient 的 request 行为一致; 大于 0 才跟随 301/302。
     * @param int $num
     * @return $this
     */
    public function setMaxredirs(int $num){
        $this->config['maxredirs'] = $num;
        return $this;
    }

    /**
     * 解析域名
     * @param $domainName
     * @return mixed|string|null
     */
    protected function getDnsHostIp($domainName)
    {
        $resolve = $this->config['ipresolve'] ?: ($this->config['enable_ipv6'] ? 2 : 0);
        if($resolve == 0) return $domainName;
        try {
            if (!filter_var($domainName, FILTER_VALIDATE_IP)) {
                $addr = \Swoole\Coroutine\System::dnsLookup($domainName, 5, $resolve == 1 ? AF_INET : AF_INET6);
                if ($addr) {
                    return $addr;
                }elseif (strpos($domainName, '[') !== false){
                    return str_replace(['[', ']'], '', $domainName);
                }
            }
        }catch (\Throwable $e){}
        return $domainName;
    }

    /**
     * 请求
     * @param string $url
     * @param string $method
     * @param string|array $data
     * @param bool $again 失败时是否重试一次
     * @return array
     */
    public function request(string $url, string $method = 'GET', string|array $data = [], bool $again = true){
        $decodeUrl = $this->parseUrl($url);
        $node = $this->node();
        $startTime = microtime(true);
        $dnsHostIp = $this->getDnsHostIp($node['host']);
        $dnsTime = microtime(true) - $startTime;

        $payload = $this->buildPayload($data);
        $headers = $this->buildHeaders($decodeUrl['host'], $payload);

        $outData = $this->execute(
            strtoupper($method), $decodeUrl['path'], $decodeUrl['host'], $decodeUrl['port'], $decodeUrl['ssl'],
            $payload, $headers,
            ['startTime' => $startTime, 'dnsip' => $dnsHostIp, 'dnstime' => $dnsTime]
        );

        // 流式(write_func)下**既不重试也不跟重定向**, 这是流式语义的必然要求, 不是保守:
        //   · 重试: 体已经一段段吐给回调了, 再来一次等于把同一份内容吐两遍, 下游拼起来就是坏数据
        //   · 重定向: 等于把重定向页当成用户要的内容流出去, 而调用方此时拿不到 body 去反应过来
        // 失败就失败, 由调用方自己决定要不要重来(它有完整的上下文)。
        $streaming = ($this->config['options']['write_func'] ?? null) !== null;
        // 重试在"清配置"之前 —— 否则第二次请求会丢掉 header/cookie/上传内容
        if(!$streaming && $again && $outData['runtime'] < 3 && ($outData['code'] == false || $outData['code'] < 0 || $outData['code'] >= 500)){
            if($this->config['maxredirs'] > 0) return $outData;
            return $this->request($url, $method, $data, false);
        }
        // 跟随重定向(默认关, 与 Httpclient 的 request 行为一致)
        if(!$streaming && $this->config['maxredirs'] > 0 && ($outData['code'] == 301 || $outData['code'] == 302)){
            $location = $outData['header']['location'] ?? '';
            if($location !== ''){
                $this->config['maxredirs']--;
                $this->debugLog($url, $method, $data, $outData);
                return $this->request($this->absoluteUrl($location, $decodeUrl), $method, $data, $again);
            }
        }
        $this->debugLog($url, $method, $data, $outData); // 记录debug日志
        $this->clearConfig();
        return $outData;
    }

    /**
     * 文件下载
     * @param string $url
     * @param string $saveFile
     * @param int $offset 重定向剩余次数(负数兜底, 与 Httpclient 一致)
     * @return bool
     */
    public function download(string $url, string $saveFile, int $offset = 0)
    {
        // 先写**同目录**的临时文件, 全部成功后再 rename 就位。三点理由:
        //   · 失败时不留半截文件 —— 旧行为会在目标路径留下一个"看着像下载好了"的残file,
        //     调用方 catch 到异常却看到文件已存在, 很容易当成下载成功(尤其配合 Bug#1 的静默成功)
        //   · 落盘过程原子: 别的进程/协程读 $saveFile 永远读到完整文件, 不会读到写了一半的
        //   · 同目录保证 rename 不跨文件系统(跨了 rename 会失败)
        // 重定向递归全程复用同一个临时名 —— execute() 每次以 'wb' 打开会自行截断。
        $dir = dirname($saveFile);
        if(!is_dir($dir) || !is_writable($dir)){
            App::error()->setError($url.'下载失败: 目录不存在或不可写 '.$dir, 500);
        }
        $tmp = $saveFile . '.zhanshop-part-'.bin2hex(random_bytes(6));
        try{
            $this->fetchToFile($url, $tmp, $offset);
        }catch(\Throwable $e){
            @unlink($tmp);   // 失败路径: 残file 只留在临时名上, 一并清掉
            throw $e;
        }
        if(!@rename($tmp, $saveFile)){
            @unlink($tmp);
            App::error()->setError($url.'下载失败: 无法就位到 '.$saveFile, 500);
        }
        return true;
    }

    /**
     * 下载的实际拉取: 写 $tmp, 需要时跟随 301/302
     * @param string $url
     * @param string $tmp 临时文件(递归时原样传下去, 不再套第二层临时名)
     * @param int $offset 重定向剩余次数(负数兜底, 与 Httpclient 一致)
     * @return bool
     */
    protected function fetchToFile(string $url, string $tmp, int $offset): bool
    {
        $decodeUrl = $this->parseUrl($url);
        $node = $this->node();
        $startTime = microtime(true);
        $dnsHostIp = $this->getDnsHostIp($node['host']);
        $dnsTime = microtime(true) - $startTime;

        $payload = $this->buildPayload([]);
        $headers = $this->buildHeaders($decodeUrl['host'], $payload);

        try{
            $outData = $this->execute(
                'GET', $decodeUrl['path'], $decodeUrl['host'], $decodeUrl['port'], $decodeUrl['ssl'],
                $payload, $headers,
                ['startTime' => $startTime, 'dnsip' => $dnsHostIp, 'dnstime' => $dnsTime],
                $tmp
            );

            $statusCode = $outData['code'];
            if($statusCode == 301 || $statusCode == 302){
                $location = (string)($outData['header']['location'] ?? '');
                if($location !== '' && $offset >= -3){
                    // 递归必须在**清配置之前** —— 清早了第二次请求就会丢掉 header/cookie,
                    // 而"跟重定向"恰恰是要用同一套凭据再发一次(request() 那条路早有同样的注释,
                    // download() 这条是漏的: 它先 clearConfig 再递归, 于是跳转后的请求全是裸的)。
                    return $this->fetchToFile($this->absoluteUrl($location, $decodeUrl), $tmp, $offset - 1);
                }
                // 跟不了(没 Location / 跳转次数用尽)就落到下面按失败处理。
                // 旧判据只写了 `< 200 || >= 399`, 3xx 整个漏过 —— 于是"跟不了的重定向"
                // 会以 ret=true 返回, 还把 18 字节的重定向页当成用户要的文件落了盘。
            }
            if($statusCode === false || $statusCode < 200 || $statusCode >= 300){
                App::error()->setError($url."下载失败".($this->lastError !== '' ? ': '.$this->lastError : ''), is_int($statusCode) ? $statusCode : 500);
            }
            return true;
        }finally{
            // finally: 递归那条路上配置要被下一跳继续用, 所以只能等本层真正退出时再清;
            // 抛异常时也要清(否则会泄漏到调用方的下一次请求)。清理是幂等的, 多层各清一次无害。
            $this->clearConfig();
        }
    }

    /**
     * 并发请求
     * @param string $url
     * @param string $method
     * @param string|array $data
     * @param int $number
     * @return array
     */
    public function parallel(string $url, string $method = 'GET', string|array $data = [], int $number = 1000)
    {
        $startTime = microtime(true);
        // 快照配置: 配置是逐请求有状态的(cookie/header 用完即清), 每个协程必须拿自己那一份
        $config = $this->config;
        $config['url'] = $url;
        $config['method'] = $method;
        $config['data'] = $data;

        $result = [];
        // 用 WaitGroup 而不是 while(count($result) != $number) usleep(10):
        // 后者是每 10 微秒醒一次的轮询, 1000 并发下等于每秒十万次空转。同工作量 A/B
        // (500 协程各睡 1 秒): 轮询版 CPU 0.0141s, WaitGroup 版 0.0025s, 差 5.6 倍。
        // 轮询版还有一处更硬的毛病: 它数的是"结果个数", 一旦有协程没能把结果 append 上
        // (比如 create 失败), 计数永远凑不够, 就是死循环。
        $wg = new WaitGroup();
        for ($i = 0; $i < $number; $i++){
            $wg->add();
            $cid = Coroutine::create(function () use ($config, &$result, $wg){
                try{
                    $client = new self($config['node']['host'], $config['node']['port'], $config['node']['password']);
                    $client->config = $config;
                    $out = $client->request($config['url'], $config['method'], $config['data']);
                    $result[] = $out['code'];
                }finally{
                    // add 与 done 一一对应(即使以后这段加了 catch 也不会漏)
                    $wg->done();
                }
            });
            // 协程创建失败(如已达协程数上限)时补一次 done —— add 了没人 done 就是死等
            if($cid === false) $wg->done();
        }
        $wg->wait();
        return ['status' => $result, 'runtime' => microtime(true) - 0.00001 - $startTime];
    }

    /**
     * GET
     * @param string $url
     * @param string|array $data
     * @return array
     */
    public function get(string $url, string|array $data = [])
    {
        return $this->request($url, 'GET', $data);
    }

    /**
     * POST
     * @param string $url
     * @param string|array $data
     * @return array
     */
    public function post(string $url, string|array $data = [])
    {
        return $this->request($url, 'POST', $data);
    }

    /**
     * PUT
     * @param string $url
     * @param string|array $data
     * @return array
     */
    public function put(string $url, string|array $data = [])
    {
        return $this->request($url, 'PUT', $data);
    }

    /**
     * DELETE
     * @param string $url
     * @param string|array $data
     * @return array
     */
    public function delete(string $url, string|array $data = [])
    {
        return $this->request($url, 'DELETE', $data);
    }

    /**
     * 解析并校验 URL
     * @param string $url
     * @return array scheme/host/port/path/ssl
     */
    protected function parseUrl(string $url): array
    {
        $decodeUrl = parse_url($url);
        // parse_url 对一部分畸形 URL 直接返回 false(实测: 端口写 70000), 此时再取下标会先冒一条
        // "array offset on bool" 警告 —— 显式挡掉, 让"URL 无效"只有一个来源
        if($decodeUrl === false) App::error()->setError($url.'不是一个有效的url');
        $scheme = $decodeUrl['scheme'] ?? App::error()->setError($url.'不是一个有效的url');
        $host = $decodeUrl['host'] ?? App::error()->setError($url.'不是一个有效的url');
        $port = $decodeUrl['port'] ?? 0;
        // 沿用 Httpclient 的写法: `== false` 对端口 0 也成立(parse_url 认 `https://a.com:0` 的端口就是 0),
        // 于是 :0 走默认端口而不是报错 —— 与 Httpclient 行为一致, 不另立一套
        if($port == false) $port = ($scheme == 'https') ? 443 : 80;
        // pack('n') 与 htons 一样只认 uint16, 越界会**静默截断**(73307 变 7771), 这里必须挡住
        if($port < 1 || $port > 65535) App::error()->setError($url.' 端口越界: '.$port);
        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'ssl' => $scheme == 'https',
            'path' => $this->safePath(($decodeUrl['path'] ?? '/') . (isset($decodeUrl['query']) ? "?".$decodeUrl['query'] : "")),
        ];
    }

    /**
     * 校验节点配置并返回
     * @return array
     */
    protected function node(): array
    {
        $node = $this->config['node'];
        if($node['host'] === '' || $node['password'] === ''){
            App::error()->setError('未配置 trojan 节点: 请用 new TrojanClient($host, $port, $password) 或 setNode() 指定');
        }
        if($node['port'] < 1 || $node['port'] > 65535){
            App::error()->setError('trojan 节点端口越界: '.$node['port']);
        }
        if(!OpenSsl::available()){
            App::error()->setError('trojan 客户端缺少 FFI: 需要 PHP 编译了 FFI 扩展, 且 ffi.enable=true');
        }
        return $node;
    }

    /**
     * 组织请求体
     * @param string|array $data
     * @return array body/contentType/multipart
     */
    protected function buildPayload(string|array $data): array
    {
        $payload = ['body' => '', 'contentType' => '', 'multipart' => null];
        if($this->config['uploadfile'] || $this->config['uploaddata']){
            // 有上传内容 -> 整个请求体走 multipart
            $multipart = new MultipartBody();
            foreach($this->config['uploadfile'] as $name => $file){
                $multipart->addFile($name, $file['path'], $file['type'], $file['filename'], $file['offset'], $file['length']);
            }
            foreach($this->config['uploaddata'] as $name => $content){
                $multipart->addData($name, $content['data'], $content['mimeType'], $content['filename']);
            }
            if(is_array($data)){
                foreach($data as $k => $v){
                    if(is_array($v)) App::error()->setError('multipart 的普通字段不支持数组: '.$k);
                    $multipart->addField((string)$k, (string)$v);
                }
            }
            $payload['multipart'] = $multipart;
            $payload['contentType'] = $multipart->contentType();
        }elseif(is_array($data) && $data){
            $payload['body'] = http_build_query($data);
            $payload['contentType'] = 'application/x-www-form-urlencoded';
        }elseif(is_string($data)){
            $payload['body'] = $data;
        }
        return $payload;
    }

    /**
     * 组织请求头
     * @param string $host 目标主机
     * @param array $payload buildPayload 的结果
     * @return array
     */
    protected function buildHeaders(string $host, array $payload): array
    {
        $this->config['header']['User-Agent'] = $this->config['useragent'];
        $this->config['header']['Host'] = $host;
        $headers = $this->config['header'];
        if($payload['contentType'] !== '' && !$this->hasHeader($headers, 'content-type')){
            $headers['Content-Type'] = $payload['contentType'];
        }
        if($this->config['referer'] !== '') $headers['Referer'] = $this->config['referer'];
        if($this->config['cookie']){
            $pairs = [];
            foreach($this->config['cookie'] as $k => $v) $pairs[] = $k.'='.$v;
            $headers['Cookie'] = implode('; ', $pairs);
        }
        return $headers;
    }

    /**
     * 头是否已存在(键不区分大小写)
     * @param array $headers
     * @param string $lowerName
     * @return bool
     */
    protected function hasHeader(array $headers, string $lowerName): bool
    {
        foreach($headers as $k => $v){
            if(strtolower($k) === $lowerName) return true;
        }
        return false;
    }

    /**
     * 相对 Location 补成绝对 URL
     * @param string $location
     * @param array $base parseUrl 的结果
     * @return string
     */
    protected function absoluteUrl(string $location, array $base): string
    {
        if(preg_match('#^https?://#i', $location)) return $location;
        if(strpos($location, '//') === 0) return $base['scheme'].':'.$location;
        if(strpos($location, '/') !== 0){
            $dir = rtrim(dirname($base['path']), '/');
            $location = $dir.'/'.$location;
        }
        $portPart = ($base['ssl'] && $base['port'] == 443) || (!$base['ssl'] && $base['port'] == 80) ? '' : ':'.$base['port'];
        return $base['scheme'].'://'.$base['host'].$portPart.$location;
    }

    /**
     * 清掉一次请求用完即弃的配置(与 Httpclient 的处理一致)
     * @return void
     */
    protected function clearConfig(): void
    {
        $this->config['cookie'] = [];
        $this->config['header'] = [];
        $this->config['uploadfile'] = [];
        $this->config['uploaddata'] = [];
        $this->config['referer'] = '';
    }

    /**
     * 路径安全化: 控制字符会拆出额外请求行, 空格会破坏请求行
     * @param string $path
     * @return string
     */
    protected function safePath(string $path): string
    {
        $path = str_replace(["\r", "\n", "\0"], '', $path);
        return str_replace(' ', '%20', $path);
    }

    /**
     * 真正干活: 建连 -> 外层 TLS -> trojan 握手 -> 内层 TLS -> 发请求 -> 收响应
     *
     * @param string $method
     * @param string $path
     * @param string $host 目标主机
     * @param int $port 目标端口
     * @param bool $ssl 目标是否 https(是则要叠内层 TLS)
     * @param array $payload buildPayload 的结果
     * @param array $headers
     * @param array $meta startTime/dnsip/dnstime
     * @param string|null $saveFile 非 null 时响应体流式写文件
     * @return array
     */
    protected function execute(string $method, string $path, string $host, int $port, bool $ssl, array $payload, array $headers, array $meta, ?string $saveFile = null): array
    {
        $this->lastStatus = 0;
        $this->lastError = '';
        $node = $this->node();

        // 流式回调(options.write_func): 每解出一批体就交给它, 不在本类里攒整份。
        // 与 download() 的落盘互斥 —— 一个响应体只能有一个落点, 同时给两个必然有一个拿到残缺的。
        $writeFunc = $this->config['options']['write_func'] ?? null;
        if($writeFunc !== null){
            if(!is_callable($writeFunc)) return $this->fail($meta, 'write_func 不是可调用的');
            if($saveFile !== null) return $this->fail($meta, 'write_func 与 download() 落盘互斥: 响应体只能有一个落点');
        }

        $timeout = (float)$this->config['options']['timeout'];
        if($timeout <= 0) $timeout = 30;                 // 空闲超时(秒)
        $totalTimeout = (float)($this->config['options']['total_timeout'] ?? 0);   // 总预算(秒), 0 = 不限

        $outer = null;
        $inner = null;
        $stream = null;
        try{
            $socket = new Socket(AF_INET, SOCK_STREAM, 0);
            // 关 Nagle: 本客户端把 trojan 头与 HTTP 请求分两次 write, 正是 Nagle 的触发形态。
            // ⚠ 但 Nagle 是**对端**属性, 这里关只对上行有效; 服务端自己不关的话往返仍会被
            //   delayed-ACK 顶到 40ms 量级(本库 ProxyServer 已在 ProxySession 里补上)。
            $socket->setOption(SOL_TCP, TCP_NODELAY, 1);
            $stream = new SocketStream($socket);
            $stream->setTimeout($timeout);
            if($totalTimeout > 0) $stream->setTotalTimeout($totalTimeout);

            if(!$socket->connect($meta['dnsip'], $node['port'], $stream->remainTimeout())){
                return $this->fail($meta, '连接节点失败: '.$socket->errMsg);
            }

            $tunnel = $stream;
            if($node['tls']){
                $outer = new TlsStream($stream, $node['host'], (bool)$node['verify']);
                $outer->handshake();
                $tunnel = $outer;
            }

            // trojan 握手: sha224(密码) 的十六进制 + CRLF + CMD(0x01=CONNECT) + ATYP + 地址 + 端口 + CRLF
            if(!$tunnel->write(hash('sha224', $node['password']) . "\r\n\x01" . $this->encodeAddr($host) . pack('n', $port) . "\r\n")){
                return $this->fail($meta, $this->ioReason($tunnel, 'trojan 握手写出失败'));
            }

            if($ssl){
                // 目标 https: 在隧道上再叠一层 —— 内层 TLS 的传输层就是外层解出来的明文流
                $inner = new TlsStream($tunnel, $host, (bool)$node['verify']);
                $inner->handshake();
                $tunnel = $inner;
            }

            $req = $method . ' ' . $path . " HTTP/1.1\r\n";
            foreach($headers as $k => $v) $req .= $k . ': ' . $v . "\r\n";
            // Connection: close 是隧道语义(一条连接一次请求)必带的。
            // Accept-Encoding 分三种:
            //   · 体留在内存的路径要 gzip —— 塌窗口里 233KB 的谷歌首页压到 ~60KB(实测 4 倍),
            //     "12 秒"因此变成"1 秒多"; 解压是本地瞬时开销, 不占链路。
            //   · 落盘的 download() 刻意**不要**压缩: 文件下载要的是与线上逐字节一致的字节流
            //     (解压完再落盘等于悄悄改了文件内容), 且下载多是已压缩内容, 压不动。
            //   · 流式 write_func 也是 identity: 回调拿到的是**线上字节**, 我们不在中间解开压缩
            //     (Swoole 的 write_func 同理 —— 它连自动解压都跳过)。调用方若要压缩态就自己解。
            // 调用方自己设了 Accept-Encoding 就不再追加, 否则会发出两条同名头(服务端取哪条看实现)。
            // 编解码能力来自 zlib(gzdecode); 没有 zlib 时不谎报 gzip, 退回 identity。
            $req .= "Connection: close\r\n";
            if(!$this->hasHeader($headers, 'accept-encoding')){
                if($saveFile === null && $writeFunc === null && function_exists('gzdecode')){
                    $req .= "Accept-Encoding: gzip\r\n";
                }else{
                    $req .= "Accept-Encoding: identity\r\n";
                }
            }
            if($payload['multipart'] !== null){
                $req .= 'Content-Length: ' . $payload['multipart']->length() . "\r\n\r\n";
            }elseif($payload['body'] !== ''){
                $req .= 'Content-Length: ' . strlen($payload['body']) . "\r\n\r\n" . $payload['body'];
            }else{
                $req .= "\r\n";
            }
            if(!$tunnel->write($req)){
                return $this->fail($meta, $this->ioReason($tunnel, '请求写出失败'));
            }
            if($payload['multipart'] !== null){
                $ok = $payload['multipart']->writeTo(function (string $chunk) use ($tunnel){
                    return $tunnel->write($chunk);
                });
                if(!$ok) return $this->fail($meta, $this->ioReason($tunnel, '上传内容写出失败'));
            }

            return $this->readResponse($tunnel, $method, $meta, $saveFile, $writeFunc);
        }catch(\Throwable $e){
            return $this->fail($meta, $e->getMessage());
        }finally{
            if($inner !== null) $inner->close();
            if($outer !== null) $outer->close();
            if($stream !== null) $stream->close();
        }
    }

    /**
     * 读响应(状态行 + 头 + 体); 体有三个落点, 由调用方选一个:
     *   · 攒在内存  —— 默认, 走 decodeBody 解压后交给 result()['body']
     *   · 落盘      —— $saveFile(download 用)
     *   · 流式回调  —— $writeFunc(options.write_func, 每解出一批调一次, 即收即交)
     * 三者的**完整性判据是同一条**(见下面 $complete): 半截响应在任何一条路上都必须报错,
     * 不能因为"体不留在内存"就放松 —— 流式场景下静默截断更隐蔽, 下游只看到流"正常结束"了。
     * @param mixed $tunnel
     * @param string $method
     * @param array $meta
     * @param string|null $saveFile
     * @param callable|null $writeFunc 形如 function(TrojanClient $client, string $data): bool
     * @return array
     */
    protected function readResponse($tunnel, string $method, array $meta, ?string $saveFile, ?callable $writeFunc = null): array
    {
        $pending = '';
        for(;;){ // 循环是为了跳过 1xx 中间响应(如 100 Continue), 最终响应在后面
            while(($pos = strpos($pending, "\r\n\r\n")) === false){
                $chunk = $tunnel->read();
                if($chunk === false) return $this->fail($meta, $this->ioReason($tunnel, '读响应头失败'));
                $pending .= $chunk;
                if(strlen($pending) > self::MAX_HEADER_SIZE) return $this->fail($meta, '响应头超过 '.self::MAX_HEADER_SIZE.' 字节');
            }
            $headers = $this->parseHead(substr($pending, 0, $pos));
            $pending = substr($pending, $pos + 4);
            $status = $this->lastStatus;
            if($status >= 100 && $status < 200 && $status !== 101) continue;

            if($method === 'HEAD' || $status === 204 || $status === 304){
                return $this->result($status, $headers, '', $meta);
            }

            $chunked = isset($headers['transfer-encoding']) && stripos($headers['transfer-encoding'], 'chunked') !== false;
            $decoder = $chunked ? new ChunkedBody() : null;
            $length = isset($headers['content-length']) ? (int)$headers['content-length'] : -1;

            $fp = null;
            if($saveFile !== null){
                $fp = @fopen($saveFile, 'wb');
                if($fp === false) return $this->fail($meta, '无法写入文件: '.$saveFile);
            }
            $body = '';
            $got = 0;
            // 上限只管"攒在内存里"的那条路: 落盘是流式写文件、流式回调是即收即交,
            // 两者的内存都不随响应体增长, 拿字节上限去卡它们等于凭空砍掉大文件下载/长流。
            $maxBody = ($fp === null && $writeFunc === null) ? (int)($this->config['options']['max_body_size'] ?? 0) : 0;
            try{
                if($pending !== ''){
                    $out = $decoder !== null ? $decoder->feed($pending) : $pending;
                    if(!$this->emitBody($out, $fp, $body, $writeFunc)) return $this->fail($meta, '流式回调要求中止');
                    $got += strlen($out);
                }
                $pending = '';
                // 结束条件: chunked 收到终止块 / 有 Content-Length 收满 / 否则等对端关连接
                while(true){
                    if($decoder !== null && $decoder->done()) break;
                    if($decoder === null && $length >= 0 && $got >= $length) break;
                    $chunk = $tunnel->read();
                    if($chunk === false) break;
                    $out = $decoder !== null ? $decoder->feed($chunk) : $chunk;
                    if(!$this->emitBody($out, $fp, $body, $writeFunc)) return $this->fail($meta, '流式回调要求中止');
                    $got += strlen($out);
                    // 判据放在循环里而不是循环后: 出来再比就已经把内存吃掉了
                    if($maxBody > 0 && $got > $maxBody) return $this->fail($meta, '响应体超过 '.$maxBody.' 字节上限');
                }
            }finally{
                if($fp !== null) fclose($fp);
            }

            // 完整性判据: 把三种定界方式(有 Content-Length / chunked / 靠对端关闭)合成**一个**谓词。
            // 这里原本是三条**并列**的 if, 漏掉的正是"读超时 + 非 chunked + 没读满"这一格:
            // 三条全不命中 → 半截体被当成完整响应返回(静默数据损坏, 而且下载落盘时同样是半截文件)。
            // 关键认识: **超时只能说明对端不再发数据, 不能说明它发完了**。无长度定界时尤其致命 ——
            // "已经发完"与"卡在中间"在字节层面一模一样, 所以宁可判失败。
            // (先比"收满没有"再比"超没超时", 正好避开"计时器与最后一个字节同时到"的边界:
            //  那种情况下 $got >= $length 已经成立, 不会被误判成超时。)
            $complete = $decoder !== null
                ? ($decoder->done() && !$decoder->bad())
                : ($length >= 0 ? $got >= $length : !$tunnel->timedOut());
            if(!$complete){
                if($tunnel->timedOut()) return $this->fail($meta, '读响应体超时: 已收 '.$got.' 字节'.($length >= 0 ? ', Content-Length 是 '.$length : ''));
                if($decoder !== null) return $this->fail($meta, $decoder->bad() ? '响应体 chunked 编码畸形' : 'chunked 响应不完整(对端提前断开)');
                return $this->fail($meta, '响应体不完整: 收到 '.$got.' 字节, Content-Length 是 '.$length);
            }
            if($saveFile !== null) return $this->result($status, $headers, true, $meta);

            // 流式: 体已经一段一段交给回调了, 这里再攒一份等于把流式的意义抵消掉。
            // body 交空串 —— 与 Httpclient 挂上 write_func 之后的语义一致(那是 Swoole
            // getBody() 的行为, 回调模式下它同样为空)。
            if($writeFunc !== null) return $this->result($status, $headers, '', $meta);

            // 完整性判据(上面几条)用的是**线上的字节数**, 所以解压必须放在它们之后 ——
            // 先解压再比对就变成拿解压后的长度去比 content-length, 那是必然不等的。
            $decoded = $this->decodeBody($headers, $body);
            if($decoded === false) return $this->fail($meta, '响应体解压失败(content-encoding: '.($headers['content-encoding'] ?? '').', '.strlen($body).' 字节)');
            return $this->result($status, $headers, $decoded, $meta);
        }
    }

    /**
     * 把一段响应体交给它的落点(落盘 / 流式回调 / 攒内存)
     * 三个落点在这里收口, 是为了让上面那个读循环只需要写一次、也只有一份结束语义 ——
     * 否则"落盘那条路记得判完整性、流式那条路忘了"这种漏法迟早发生。
     * @param string $out 已解开 chunked 的一段明文
     * @param resource|null $fp
     * @param string $body 攒内存时按引用追加
     * @param callable|null $writeFunc 回调返回 false 表示调用方要求中止
     * @return bool false = 中止(调用方主动停)
     */
    protected function emitBody(string $out, $fp, string &$body, ?callable $writeFunc): bool
    {
        if($out === '') return true;
        if($fp !== null){
            fwrite($fp, $out);
            return true;
        }
        if($writeFunc !== null){
            // 首参传 $this: 与 Httpclient 的 write_func 回调签名 ($client, $data) 对齐 ——
            // 调用方(如 relay 的 Sink)不用为两个客户端写两种回调
            return $writeFunc($this, $out) !== false;
        }
        $body .= $out;
        return true;
    }

    /**
     * 按 content-encoding 解压响应体, 并把返回的表改成自洽的
     * 只认 gzip / x-gzip / deflate(我们只声明 gzip, 另两个是容错);
     * 不认识的编码(如 br)一律**返回 false 让调用方失败**, 绝不把压缩字节当明文交出去 ——
     * 把二进制当文本返回, 调用方只会拿到一堆乱码还以为请求成功了。
     * 解压成功后从 header 里摘掉 content-encoding、并把 content-length 改成解压后的长度,
     * 这样 result() 返回的表内部自洽: strlen(body) === header['content-length']。
     * 注: 只在"体留在内存"的路径上会被调用(download 那条路不带 gzip)。
     * @param array $headers 引用传入: 需要就地改写
     * @param string $body
     * @return string|false
     */
    protected function decodeBody(array &$headers, string $body)
    {
        $enc = strtolower(trim((string)($headers['content-encoding'] ?? '')));
        if($enc === '' || $enc === 'identity') return $body;

        // 解压上限: 防 zip 炸弹 —— 几十 KB 的响应体可以解出几 GB, 光靠"线上字节上限"拦不住。
        // 实测 gzdecode 的行为: 解压结果超过上限时返回 **false**(不是静默截断), 允许约 10% 的
        // 超出量后才触发 —— 所以下面再加一道精确判据, 保证交出去的体真的不超过上限。
        $max = (int)($this->config['options']['max_body_size'] ?? 0);
        $limit = $max > 0 ? $max : PHP_INT_MAX;

        if($enc === 'gzip' || $enc === 'x-gzip'){
            $out = @gzdecode($body, $limit);
        }elseif($enc === 'deflate'){
            // deflate 这个名字在历史上被两种格式用过: zlib 包装的、和裸的。两个都试。
            $out = @gzuncompress($body, $limit);
            if($out === false) $out = @gzinflate($body, $limit);
        }else{
            return false;
        }
        if($out === false) return false;
        if($max > 0 && strlen($out) > $max) return false;   // 上一条判据的 10% 余量在这补齐

        unset($headers['content-encoding']);
        if(isset($headers['content-length'])) $headers['content-length'] = (string)strlen($out);
        return $out;
    }

    /**
     * 组装成功结果(形状与 Httpclient 的 request 返回一致)
     * @param int $status
     * @param array $headers
     * @param mixed $body
     * @param array $meta
     * @return array
     */
    protected function result(int $status, array $headers, mixed $body, array $meta): array
    {
        return [
            'code' => $status,
            'cookie' => $this->parseCookies($headers),
            'header' => $headers,
            'body' => $body,
            'dnstime' => $meta['dnstime'],
            'dnsip' => $meta['dnsip'],
            'runtime' => microtime(true) - $meta['startTime'],
        ];
    }

    /**
     * 解析状态行与响应头, 顺便把状态码记到 lastStatus
     * @param string $head
     * @return array
     */
    protected function parseHead(string $head): array
    {
        $lines = explode("\r\n", $head);
        $statusLine = array_shift($lines);
        $this->lastStatus = preg_match('#^HTTP/\S+\s+(\d+)#', $statusLine, $m) ? (int)$m[1] : 0;
        $headers = [];
        foreach($lines as $line){
            $p = strpos($line, ':');
            if($p === false) continue;
            $k = strtolower(trim(substr($line, 0, $p)));
            $v = trim(substr($line, $p + 1));
            if($k === 'set-cookie'){
                // set-cookie 合并会破坏 Cookie 语义(每条各有 expires/path), 必须保序成数组
                if(!isset($headers[$k])) $headers[$k] = [];
                $headers[$k][] = $v;
            }elseif(isset($headers[$k])){
                $headers[$k] .= ', ' . $v;
            }else{
                $headers[$k] = $v;
            }
        }
        return $headers;
    }

    /**
     * 从 set-cookie 提取 name=value
     * @param array $headers
     * @return array
     */
    protected function parseCookies(array $headers): array
    {
        $cookies = [];
        $list = $headers['set-cookie'] ?? [];
        if(!is_array($list)) $list = [$list];
        foreach($list as $line){
            $pair = explode(';', $line, 2)[0];
            $eq = strpos($pair, '=');
            if($eq === false) continue;
            $cookies[trim(substr($pair, 0, $eq))] = trim(substr($pair, $eq + 1));
        }
        return $cookies;
    }

    /**
     * 目标地址 ATYP 编码(SOCKS5 同款): 0x01=IPv4 / 0x04=IPv6 / 0x03=域名
     * 域名走 0x03 而不是先本地解析成 IP —— 这样目标域名由**节点**解析, 本地 DNS 不会看到它。
     * @param string $h
     * @return string
     */
    protected function encodeAddr(string $h): string
    {
        if (filter_var($h, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return "\x01" . inet_pton($h);
        if (filter_var($h, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return "\x04" . inet_pton($h);
        // ATYP=0x03 的长度字段只有 1 字节, 超了会被截断成别的域名 —— 必须挡住
        if (strlen($h) > 255) App::error()->setError('域名过长(ATYP 域名长度只有 1 字节): ' . $h);
        return "\x03" . chr(strlen($h)) . $h;
    }

    /**
     * IO 失败的文案(区分超时与对端关闭)
     * @param mixed $tunnel
     * @param string $prefix
     * @return string
     */
    protected function ioReason($tunnel, string $prefix): string
    {
        return $prefix . ($tunnel->timedOut() ? '(IO 超时)' : '(隧道已关闭)');
    }

    /**
     * 失败结果 —— 形状与成功时一致, 只把 code/body 置 false(对齐 swoole 的失败语义)
     * @param array $meta
     * @param string $error
     * @return array
     */
    protected function fail(array $meta, string $error): array
    {
        $this->lastStatus = 0;
        $this->lastError = $error;
        return [
            'code' => false,
            'cookie' => [],
            'header' => [],
            'body' => false,
            'dnstime' => $meta['dnstime'],
            'dnsip' => $meta['dnsip'],
            'runtime' => microtime(true) - $meta['startTime'],
        ];
    }

    /**
     * 打印日志
     * @param $url
     * @param $method
     * @param $param
     * @param $output
     * @return void
     */
    private function debugLog($url, $method, $param, $output)
    {
        if($this->config['debug']){
            if(is_string($output["body"]) && strlen($output["body"]) > 2000){
                $output["body"] = substr($output["body"], 0, 2000);
            }
            if($this->lastError !== '') $output['error'] = $this->lastError;
            App::log()->push(json_encode([
                'url' => $url,
                'method' => $method,
                'header' => $this->config['header'],
                'param' => $param,
                'result' => $output
            ],JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE + JSON_INVALID_UTF8_IGNORE), Log::NOTICE, 'TROJAN_CLIENT');
        }
    }
}
