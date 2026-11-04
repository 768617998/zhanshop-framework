<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / Httpclient.php    [ 2024/11/1 10:28 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\client;

use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine;
use zhanshop\App;
use zhanshop\Log;

class Httpclient
{
    protected $config = [
        "enable_ipv6" => false,
        'options' => [
            'timeout' => 3,
            'keep_alive' => false,
            // 用于处理流式响应内容
            //'write_func' => function($client, $data){},
        ],
        'cookie' => [],
        'header' => [],
        'uploadfile' => [], // 上传文件对象
        'uploaddata' => [], // 上传文件内容
        'useragent' => 'Mozilla/5.0 (Windows NT 11.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
        'debug' => false,
    ];

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
     * 使用http代理
     * @param string $ip 代理IP
     * @param int $port 代理端口
     * @param string $user 代理用户
     * @param string $password 代理密码
     * @return $this
     */
    public function httpproxy(string $ip, int $port, string $user = "", string $password = "")
    {
        $ip = $this->getDnsHostIp($ip);
        $this->config['options']['http_proxy_host'] = $ip;
        $this->config['options']['http_proxy_port'] = $port;
        if($user) $this->config['options']['http_proxy_user'] = $user;
        if($password) $this->config['options']['http_proxy_password'] = $password;
        return $this;
    }

    /**
     * 使用socks5代理
     * @param string $ip 代理IP
     * @param int $port 代理端口
     * @param string $user 代理用户
     * @param string $password 代理密码
     * @return $this
     */
    public function socks5(string $ip, int $port, string $user = "", string $password = "")
    {
        $this->config['options']['socks5_host'] = $ip;
        $this->config['options']['socks5_port'] = $port;
        if($user) $this->config['options']['socks5_username'] = $user;
        if($password) $this->config['options']['socks5_password'] = $password;
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
     * 设置curl请求超时时间
     * @param int $timeout
     * @return $this
     */
    public function setTimeout(int $timeout = 3000){
        $this->setOptions('timeout', $timeout / 1000);
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
     * @param string $data 数据内容【必选参数，最大长度不得超过 buffer_output_size】
     * @param string|null $mimeType 文件的 MIME 格式【可选参数，默认为 application/octet-stream】
     * @param string|null $filename 文件名称【可选参数，默认为 $name】
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
        if($this->config['enable_ipv6'] == false) return $domainName;
        try {
            if (!filter_var($domainName, FILTER_VALIDATE_IP)) {
                $addr = \Swoole\Coroutine\System::dnsLookup($domainName,5, AF_INET6);
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
     * @param array $data
     * @return bool|string
     */
    public function request(string $url, string $method = 'GET', string|array $data = [], bool $again = true){
        $decodeUrl = parse_url($url);
        $scheme = $decodeUrl['scheme'] ?? App::error()->setError($url.'不是一个有效的url');
        $host = $decodeUrl['host'] ?? App::error()->setError($url.'不是一个有效的url');
        $port = $decodeUrl['port'] ?? 0;
        if($port == false) $port = ($scheme == 'https') ? 443 : 80;
        $ssl = $scheme == 'https' ? true : false;
        $path = ($decodeUrl['path'] ?? '/') . (isset($decodeUrl['query']) ? "?".$decodeUrl['query'] : "");
        $startTime = microtime(true);
        $dnsHostIp = $this->getDnsHostIp($host);
        $dnsTime = microtime(true) - $startTime;
        $client = new Client($dnsHostIp, $port, $ssl);
        $client->set($this->config['options']);
        $this->config['header']['User-Agent'] = $this->config['useragent'];
        $this->config['header']['Host'] = $host;
        $client->setHeaders($this->config['header']);
        if($this->config['cookie']) $client->setCookies($this->config['cookie']);
        $client->setMethod($method);
        foreach($this->config['uploadfile'] as $name => $file){
            $client->addFile($file['path'], $name, $file['type'], $file['filename'], $file['offset'], $file['length']);
        }
        foreach($this->config['uploaddata'] as $name => $content){
            $client->addData($content['data'], $name, $content['mimeType'], $content['filename']);
        }

        if($data) $client->setData($data);
        $client->execute($path);
        $outData = [
            'code' => $client->getStatusCode(),
            'cookie' => $client->getCookies(),
            'header' => $client->getHeaders(),
            'body' =>  $client->getBody(),
            'dnstime' => $dnsTime,
            'dnsip' => $dnsHostIp,
            'runtime' => microtime(true) - $startTime,
        ];
        $client->close();

        if($again && $outData['runtime'] < 3 && ($outData['code'] == false || $outData['code'] < 0 || $outData['code'] >= 500)){
            return $this->request($url, $method, $data, false);
        }
        $this->debugLog($url,  $method, $data, $outData); // 记录debug日志
        $this->config['cookie'] = [];
        $this->config['header'] = [];
        $this->config['uploadfile'] = [];
        $this->config['uploaddata'] = [];
        return $outData;
    }

    /**
     * unix请求
     * @param string $host
     * @param string $path
     * @param $method
     * @param string|array $data
     * @param mixed|null $callback
     * @return array
     */
    public function unix(string $host, string $path, $method = "GET", string|array $data = [], mixed $callback = null)
    {
        $startTime = microtime(true);
        $hosts = explode(':', $host);
        $host = 'unix:/'.$hosts[0];
        $client = new Client($host, intval($hosts[1] ?? 80));
        $client->set($this->config['options']);
        $client->setHeaders($this->config['header']);
        if($this->config['cookie']) $client->setCookies($this->config['cookie']);
        $client->setMethod($method);
        foreach($this->config['uploadfile'] as $name => $file){
            $client->addFile($file['path'], $name, $file['type'], $file['filename'], $file['offset'], $file['length']);
        }
        foreach($this->config['uploaddata'] as $name => $content){
            $client->addData($content['data'], $name, $content['mimeType'], $content['filename']);
        }
        if($data) $client->setData($data);
        if($callback){
            $client->set(['write_func' => function ($client, $data) use($callback){
                $callback($client, $data);
            }]);
        }
        
        $client->execute($path);
        $outData = [
            'code' => $client->getStatusCode(),
            'cookie' => $client->getCookies(),
            'header' => $client->getHeaders(),
            'body' =>  $client->getBody(),
            'runtime' => microtime(true) - $startTime,
        ];
        $client->close();

        $this->config['cookie'] = [];
        $this->config['header'] = [];
        $this->config['uploadfile'] = [];
        $this->config['uploaddata'] = [];
        return $outData;
    }
    /**
     * 打印日志
     * @param $url
     * @param $method
     * @param $data
     * @param $output
     * @return void
     */
    private function debugLog($url,  $method, $param, $output)
    {
        if($this->config['debug']){
            if(is_string($output["body"]) && strlen($output["body"]) > 2000){
                $output["body"] = substr($output["body"], 0, 2000);
            }
            App::log()->push(json_encode([
                'url' => $url,
                'method' => $method,
                'header' => $this->config['header'],
                'param' => $param,
                'result' => $output
            ],JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE + JSON_INVALID_UTF8_IGNORE), Log::NOTICE, 'HTTP_CLIENT');
        }
    }

    /**
     * 文件下载
     * @param string $path
     * @param string $filename
     * @param int $offset
     * @return bool
     */
    public function download(string $url, string $saveFile,  int $offset = 0)
    {
        $decodeUrl = parse_url($url);
        $scheme = $decodeUrl['scheme'] ?? App::error()->setError($url.'不是一个有效的url');
        $host = $decodeUrl['host'] ?? App::error()->setError($url.'不是一个有效的url');
        $port = $decodeUrl['port'] ?? 0;
        if($port == false) $port = ($scheme == 'https') ? 443 : 80;
        $ssl = $scheme == 'https' ? true : false;
        $path = ($decodeUrl['path'] ?? '/') . (isset($decodeUrl['query']) ? "?".$decodeUrl['query'] : "");
        $startTime = microtime(true);
        $dnsHostIp = $this->getDnsHostIp($host);
        $dnsTime = microtime(true) - $startTime;
        $client = new Client($dnsHostIp, $port, $ssl);
        $client->set($this->config['options']);
        $this->config['header']['User-Agent'] = $this->config['useragent'];
        $this->config['header']['Host'] = $host;
        $client->setHeaders($this->config['header']);
        if($this->config['cookie']) $client->setCookies($this->config['cookie']);

        $client->download($path, $saveFile);
        $statusCode = $client->getStatusCode();
        $headers = $client->getHeaders();
        $client->close();
        $this->config['cookie'] = [];
        $this->config['header'] = [];
        $this->config['uploadfile'] = [];
        $this->config['uploaddata'] = [];
        if($statusCode == 301 || $statusCode == 302){
            if(isset($headers['location']) && $headers['location'] && $offset >= -3){
                return $this->download($headers['location'], $saveFile, $offset - 1);
            }
        }
        // 判据是 2xx 才叫成功(原为 `< 200 || >= 399`): 3xx 若上面没能跟过去(没 Location 或
        // 跳转次数用尽), 落到这里必须报错 —— 否则会把**重定向页本身**当成用户要的文件交出去,
        // 还返回 true。TrojanClient::download() 那份是同一个根因, 已一并订正。
        if($statusCode < 200 || $statusCode >= 300) App::error()->setError($url."下载失败", $statusCode);
        return true;
    }

    /**
     * 并发请求
     * @param string $url
     * @param string $method
     * @param string|array $data
     * @return void
     */
    public function parallel(string $url, string $method = 'GET', string|array $data = [], int $number = 1000)
    {
        $decodeUrl = parse_url($url);
        $scheme = $decodeUrl['scheme'] ?? App::error()->setError($url.'不是一个有效的url');
        $host = $decodeUrl['host'] ?? App::error()->setError($url.'不是一个有效的url');
        $port = $decodeUrl['port'] ?? 0;
        if($port == false) $port = ($scheme == 'https') ? 443 : 80;
        $ssl = $scheme == 'https' ? true : false;
        $path = ($decodeUrl['path'] ?? '/') . (isset($decodeUrl['query']) ? "?".$decodeUrl['query'] : "");
        $startTime = microtime(true);
        $dnsHostIp = $this->getDnsHostIp($host);
        $dnsTime = microtime(true) - $startTime;
        $this->config['header']['User-Agent'] = $this->config['useragent'];
        $this->config['header']['Host'] = $host;
        $config = $this->config;
        $config['host'] = $dnsHostIp;
        $config['port'] = $port;
        $config['ssl'] = $ssl;
        $config['path'] = $path;
        $config['method'] = $method;
        $config['data'] = $data;

        $result = [];
        for ($i = 0; $i < $number; $i++){
            Coroutine::create(function () use ($config, &$result){
                $client = new Client($config['host'], $config['port'], $config['ssl']);
                $client->set($config['options']);
                $client->setHeaders($config['header']);
                if($config['cookie']) $client->setCookies($config['cookie']);

                $client->setMethod($config['method']);
                foreach($config['uploadfile'] as $name => $file){
                    $client->addFile($file['path'], $name, $file['type'], $file['filename'], $file['offset'], $file['length']);
                }
                foreach($config['uploaddata'] as $name => $content){
                    $client->addData($content['data'], $name, $content['mimeType'], $content['filename']);
                }

                if($config['data']) $client->setData($config['data']);

                $client->execute($config['path']);
                $result[] = $client->getStatusCode();
                $client->close();
            });
        }

        while (count($result) != $number){
            usleep(10);
        }
        return ['status' => $result, 'runtime' => microtime(true) - 0.00001 - $startTime];
    }
}