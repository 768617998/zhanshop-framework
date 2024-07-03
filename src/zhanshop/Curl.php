<?php
// +----------------------------------------------------------------------
// | flow-course / Curl.php    [ 2022/2/28 10:02 AM ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2022 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

class Curl
{
    protected $config = [
        'cookie' => '',
        'timeout' => 60000,
        'header' => [],
        'opts' => [],
        'upload' => [], // 上传对象
        'useragent' => 'Mozilla/5.0 (Windows NT 11.0; Win64; x64; rv:96.0) Gecko/20100101 Firefox/96.0',
        'ipresolve' => 0,
        'referer' => '', // 请求来源
        'maxredirs' => 3, // 最大跳转次数
        'encodeng' => '', // 解压编码 gzip
        'debug' => false,
    ];

    public function debug()
    {
        $this->config['debug'] = true;
        return $this;
    }
    /**
     * 更新curl参数
     * @param int $key
     * @param mixed $val
     * @return $this
     */
    public function setopt(int $key, mixed $val){
        $this->config['opts'][$key] = $val;
        return $this;
    }

    /**
     * 使用http代理
     * @param $ipPort ip和端口 示例值：192.168.2.100:7281
     * @param $accountPassword 账号和密码 示例值：demo:123456
     * @return $this
     */
    public function httpproxy($ipPort, $accountPassword = "")
    {
        $this->config['opts'][CURLOPT_HTTPPROXYTUNNEL] = true;
        $this->config['opts'][CURLOPT_PROXY] = $ipPort;
        if($accountPassword){
            $this->config['opts'][CURLOPT_PROXYUSERPWD] = $accountPassword;
        }
        return $this;
    }

    /**
     * 使用socks5代理
     * @param $ipPort ip和端口 示例值：192.168.2.100:7281
     * @param $accountPassword 账号和密码 示例值：demo:123456
     * @return $this
     */
    public function socks5($ipPort, $accountPassword = "")
    {
        $this->config['opts'][CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
        $this->config['opts'][CURLOPT_PROXY] = $ipPort;
        if($accountPassword){
            $this->config['opts'][CURLOPT_PROXYUSERPWD] = $accountPassword;
        }
        return $this;
    }

    /**
     * 设置curl请求超时时间
     * @param int $timeout
     * @return $this
     */
    public function setTimeout(int $timeout = 3000){
        $this->config['timeout'] = $timeout;
        return $this;
    }

    /**
     * 设置解压编码
     * @param $encodeng
     * @return void
     */
    public function seEncodeng($encodeng){
        $this->config['encodeng'] = $encodeng;
    }

    /**
     * 设置请求头参数
     * @param string $key
     * @param string $val
     * @return $this
     */
    public function setHeader(string $key, string $val){
        $this->config['header'][] = $key.':'.$val;
        return $this;
    }

    /**
     * 设置curl请求cookie
     * @param string $key
     * @param string $val
     * @return $this
     */
    public function setCookie(string $key, string $val){
        $this->config['cookie'] .= $key.'='.$val.';';
        return $this;
    }

    /**
     * 设置上次文件
     * @param string $key
     * @param string $filePath
     * @return $this
     */
    public function setUpload(string $key, string $filePath){
        $realpath = realpath($filePath);
        if($realpath){
            $curlFile = new \CURLFile($realpath);
            $curlFile->setPostFilename(basename($filePath));
            $this->config['upload'][$key] = $curlFile;
        }else{
            $this->config['upload'][$key] = $filePath;
        }
        return $this;
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
     * 处理post请求参数
     * @param $data
     * @param $existingKeys
     * @param $returnArray
     * @return array
     */
    protected function buildPostFields($data, $existingKeys = '', &$returnArray = []){
        if(($data instanceof CURLFile) or !(is_array($data) or is_object($data))){
            $returnArray[$existingKeys]=$data;
            return $returnArray;
        }
        else{
            foreach ($data as $key => $item) {
                $this->buildPostFields($item,$existingKeys?$existingKeys."[$key]":$key,$returnArray);
            }
            return $returnArray;
        }
    }

    /**
     * 请求
     * @param string $url
     * @param string $method
     * @param array $data
     * @return bool|string
     */
    public function request(string $url, string $method = 'GET', string|array $data = [], bool $report = false, bool $again = true){
        $ch = curl_init();
        foreach ($this->config['opts'] as $k => $v){
            curl_setopt($ch, $k, $v);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); //设置请求方式
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
        if($data){
            curl_setopt($ch, CURLOPT_POST, 1);
            if(is_array($data)){
                $data = json_encode($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }elseif($this->config['upload']){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->config['upload']);
        }

        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 跟踪爬取重定向页面302 redirect
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->config['maxredirs']);//最多重定向3次
        curl_setopt($ch, CURLOPT_USERAGENT, $this->config['useragent']);
        curl_setopt($ch, CURLOPT_IPRESOLVE, $this->config['ipresolve']);//在对应域名没有IPv6的情况下，会等待IPv6 DNS解析失败 TIMEOUT 之后才按以前的正常流程去找IPv4，
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_COOKIE, $this->config['cookie']);
        curl_setopt($ch, CURLOPT_REFERER, $this->config['referer']);

        curl_setopt($ch, CURLOPT_HEADER, true);

        if($this->config['encodeng']) curl_setopt($ch, CURLOPT_ENCODING ,$this->config['encodeng']);
        if($this->config['header']){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->config['header']);//设置请求头
        }

        $output = curl_exec($ch);
        $curlInfo = curl_getinfo($ch);
        $httpCode = $curlInfo['http_code'];

        // 如果发生错误打印错误报告
        if($httpCode != 200){
            if($again){
                $this->debugLog($url,  $method, $data, $output, $curlInfo); // 记录debug日志
                return self::request($url,  $method, $data, $report, false); // 再次尝试
            }
        }

        $arr = is_string($output) ? explode("\r\n\r\n", $output, 2) : [];

        $outData = [
            'code' => $httpCode,
            'header' => $arr[0] ?? "",
            'body' => $arr[1] ?? "",
            'time' => $curlInfo['total_time'],
        ];
        $this->config['cookie'] = '';
        $this->config['header'] = [];
        $this->config['upload'] = [];
        if($report) $outData['report'] = $curlInfo;
        curl_close($ch);
        $this->debugLog($url,  $method, $data, $output, $curlInfo); // 记录debug日志
        return $outData;
    }

    public function chunkedDownload(string $url, mixed $callback, string $method = 'GET', string|array $data = [], bool $report = false, bool $again = true){
        $ch = curl_init();
        foreach ($this->config['opts'] as $k => $v){
            curl_setopt($ch, $k, $v);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); //设置请求方式
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
        if($data){
            curl_setopt($ch, CURLOPT_POST, 1);
            if(is_array($data)){
                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }elseif($this->config['upload']){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->config['upload']);
        }

        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 跟踪爬取重定向页面302 redirect
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->config['maxredirs']);//最多重定向3次
        curl_setopt($ch, CURLOPT_USERAGENT, $this->config['useragent']);
        curl_setopt($ch, CURLOPT_IPRESOLVE, $this->config['ipresolve']);//在对应域名没有IPv6的情况下，会等待IPv6 DNS解析失败 TIMEOUT 之后才按以前的正常流程去找IPv4，
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_COOKIE, $this->config['cookie']);
        curl_setopt($ch, CURLOPT_REFERER, $this->config['referer']);

        curl_setopt($ch, CURLOPT_HEADER, false);
        if($this->config['encodeng']) curl_setopt($ch, CURLOPT_ENCODING ,$this->config['encodeng']);
        if($this->config['header']){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->config['header']);//设置请求头
        }

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunkedData) use (&$url, &$callback, $method, &$httpInfo, $data, $report, $again){
            if($httpInfo == false){
                $httpInfo = curl_getinfo($ch);
                if($httpInfo['http_code'] != 200){
                    if($again){
                        $httpInfo = [];
                        return $this->chunkedDownload($url, $callback,  $method, $data, $report, false); // 再次尝试
                    }
                    App::error()->setError($url.'请求'.$httpInfo['http_code'].'错误 '.$chunkedData);
                }
            }
            $callback($ch, $chunkedData);
            return strlen($chunkedData);
        });
        $output = curl_exec($ch);
        $this->config['cookie'] = '';
        $this->config['header'] = [];
        $this->config['upload'] = [];
        curl_close($ch);
        return $output;
    }

    /**
     * 下载文件
     * @param string $url
     * @param string $saveToFile
     * @param mixed|null $callback
     * @return void
     * @throws \Exception
     */
    public function download(string $url, string $saveToFile, mixed $callback = null)
    {
        // 初始化 cURL 句柄
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
        // 设置 cURL 选项
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // 不直接返回文件内容，而是输出到文件
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); // 在启用 CURLOPT_RETURNTRANSFER 时，返回原生的二进制数据
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use ($saveToFile, $callback) {
            // 使用文件句柄将 cURL 获取的数据写入到文件中
            $file = fopen($saveToFile, 'ab');

            if ($file) {
                fwrite($file, $data);
                fclose($file);
                $strlen = strlen($data);
                if($callback) $callback($strlen);
                return $strlen; // 返回写入的字节数
            }
            return false; // 如果打开文件失败，返回 false
        });

        // 开始下载
        curl_exec($ch);

        // 检查是否有错误发生
        if (curl_errno($ch)) {
            App::error()->setError(curl_error($ch));
        }

        // 关闭 cURL 句柄
        curl_close($ch);
    }

    /**
     * 打印日志
     * @param $url
     * @param $method
     * @param $data
     * @param $output
     * @param $curlInfo
     * @return void
     */
    private function debugLog($url,  $method, $param, $output, $curlInfo)
    {
        if($this->config['debug']){
            App::log()->push(json_encode([
                'url' => $url,
                'method' => $method,
                'param' => $param,
                'code' => $curlInfo['http_code'] ?? 0,
                'resp' => $output,
                'time' => $curlInfo['total_time'] ?? 0,
            ]), "CURL");
        }
    }
}