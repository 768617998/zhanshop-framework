<?php
// +----------------------------------------------------------------------
// | hotel-search / Tencent.php    [ 2025/10/31 13:31 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\oos;

class Tencent
{
    // https://console.cloud.tencent.com/cam/capi 腾讯云API密钥管理
    protected $config = [
        // 腾讯云API密钥管理里面的APPID
        "appId" => "123456",
        // 腾讯云API密钥管理里面的SecretId
        "secretId" => "xxxxxxxxxxxxxxxxxxx",
        // 腾讯云API密钥管理创建时候的secretKey
        "secretKey" => "xxxxxxxxxxxxxxxxxxx",
        // Bucket 所在区域 【常见有：ap-shanghai，ap-beijing，ap-guangzhou，ap-chongqing】
        "region" => "ap-shanghai",
        // 存储桶
        "bucket" => ""
    ];

    protected $logger;
    /**
     * 构造方法
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if($config){
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * 设置日志处理类
     * @param mixed $logger
     * @return $this
     */
    public function setLogger(mixed $logger)
    {
        $this->logger = $logger;
        return $this;
    }
    /**
     * 兼容PHP-FPM
     * @var bool
     */
    protected $isPhpFpm = false;
    public function isPhpFpm()
    {
        $this->isPhpFpm = true;
        return $this;
    }

    /**
     * 签名方法封装
     * @param string $method
     * @param string $domain
     * @param array $keyValues
     * @return string
     */
    protected function signature(string $method, string $domain, array $keyValues)
    {
        ksort($keyValues);
        $query = '';
        foreach ($keyValues as $k => $val){
            $query .= $k .'=' . $val . '&';
        }
        $query = rtrim($query, '&');
        return base64_encode(hash_hmac("sha1", $method . $domain . '/?' . $query, $this->config["secretKey"], true));
    }

    /**
     * 字符串转签名字符串 【腾讯云的签名规则感觉像是多个人复杂的签名规则不统一】
     * @param string $method
     * @param string $host
     * @param array $data
     * @param array $header
     * @return string
     */
    protected function strToSignature(string $method, string $host, array $data, array $header)
    {
        ksort($data);
        $dataStr = '';
        foreach ($data as $k => $val){
            $dataStr .= $k .'=' . $val . '&';
        }
        $dataStr = rtrim($dataStr, '&');

        ksort($header);
        $headerStr = '';
        foreach ($header as $k => $val){
            $headerStr .= $k .'=' . $val . '&';
        }
        $headerStr = rtrim($headerStr, '&');
        $data = $method ."\n". $host ."\n" . $dataStr."\n".$headerStr."\n";
        return sha1($data);
    }
    /**
     * 腾讯云OSS上传token
     * @param string $bucket 空间
     * @param int $expire 失效秒
     * @param string $resKey 资源KEY
     * @return array
     */
    public function uploadToken(string $bucket, int $expire = 60, string $resKey = "")
    {
        $url = 'https://sts.tencentcloudapi.com/';
    
    
        $host = parse_url($url, PHP_URL_HOST);
        if($resKey == false){
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            $str ="";
            for ( $i = 0; $i < 16; $i++ )  {
                $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
            }
            $resKey = date('ym').'/'.microtime(true).'-'.$str;
        }
        $data = [
            "SecretId" => $this->config['secretId'],
            "Timestamp" => time(),
            "Nonce" => rand(100000, 200000),
            "Action" => "GetFederationToken",
            "Version" => "2018-08-13",
            "DurationSeconds" => $expire,
            "Name" => "cos-sts-python",
        ];

        $policy = [
            "version" => "2.0",
            "statement" => [
                [
                    "action" => [
                        "name/cos:PutObject",
                        "name/cos:InitiateMultipartUpload",
                        "name/cos:ListMultipartUploads",
                        "name/cos:ListParts",
                        "name/cos:UploadPart",
                        "name/cos:CompleteMultipartUpload"
                    ],
                    "effect" => "allow",
                    "resource" => [
                        "qcs::cos:".$this->config["region"].":uid/".$this->config["appId"].":".$bucket."/".$resKey
                    ]
                ]
            ],
        ];
        $data["Policy"] = urlencode(json_encode($policy, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE));
        $data["Region"] = $this->config['region'];
        $data["Signature"] = $this->signature("POST", $host, $data);

        if($this->isPhpFpm){
            $curl = new Curl();
            return $curl->request($url, "POST", http_build_query($data));
        }
        return $this->request($url, "POST", $data);
    }

    /**
     * 简单上传
     * @param string $token
     * @param string $source
     * @param string $target
     * @return array
     */
    public function simpleUpload(string $token, string $source, string $target, int $timeout = 30)
    {
        $url = 'https://'.$this->config['bucket'].'.cos.'.$this->config['region'].'.myqcloud.com/'.$target;
        //$url = 'http://192.168.1.162:9501/';
        $host = parse_url($url, PHP_URL_HOST);
        $decodeUrl = parse_url($url);
        $scheme = $decodeUrl['scheme'] ?? 'http';
        $host = $decodeUrl['host'] ?? '127.0.0.1';
        $port = $decodeUrl['port'] ?? 0;
        if($port == false) $port = ($scheme == 'https') ? 443 : 80;
        $ssl = $scheme == 'https' ? true : false;
        $path = ($decodeUrl['path'] ?? '/') . (isset($decodeUrl['query']) ? "?".$decodeUrl['query'] : "");
        $startTime = microtime(true);
        $client = new Client($host, $port, $ssl);

        $header = [
            'content-length' => filesize($source),
            'host' => $host,
            'x-cos-security-token' => $token,
        ];
        $strToSign = $this->strToSignature("put", '/'.$target, [], $header);
        $startSignTime = time(); // 签名开始时间
        $signTime = ($startSignTime - 60).';'.($startSignTime+ 10000);
        $signData = "sha1\n{$signTime}\n{$strToSign}\n";
        $signKey = hash_hmac('sha1', $signTime, $this->config['secretKey']);
        $sign = hash_hmac('sha1', $signData, $signKey);

        $authorization = "q-sign-algorithm=sha1&q-ak=".$this->config['secretId']."&q-sign-time=".$signTime."&q-key-time=".$signTime."&q-header-list=content-length;host;x-cos-security-token&q-url-param-list=&q-signature={$sign}";
        $header['Authorization'] = $authorization;
        $header['user-agent'] = 'cos-python-sdk-v5.1.9.38';
        $header['x-cos-security-token'] = $token;
        //$header['x-cos-sdk-retry'] = 'true';
        $client->setHeaders($header);// 设置请求HEADER
        $client->setMethod("PUT");
        $fileData = file_get_contents($source);
        $client->setData($fileData);
        $client->execute($path);
        $outData = [
            'url' => $url,
            'code' => $client->getStatusCode(),
            'cookie' => $client->getCookies(),
            'header' => $client->getHeaders(),
            'body' =>  $client->getBody(),
            'runtime' => microtime(true) - $startTime,
        ];
        $client->close();
        $this->debugLog($url,"PUT", $header, $source, $outData);
        return $outData;
    }

    /**
     * 对象列表
     * @param string $prefix
     * @param $delimiter
     * @param string $marker
     * @param int $maxKeys
     * @param string $encodeingType
     * @return array
     */
    public function getObjectList(string $prefix = "", $delimiter = "", string $marker = "", int $maxKeys = 1000, string $encodeingType = "url")
    {
        $url = 'https://'.$this->config['bucket'].'.cos.'.$this->config['region'].'.myqcloud.com/';
        //$url = "http://192.168.1.162:9801/";
        $data = [
            "delimiter" => $delimiter,
            "encoding-type" => $encodeingType,
            "marker" => $marker,
            "max-keys" => $maxKeys,
            "prefix" => $prefix
        ];
        $url .= '?'.http_build_query($data);
        $host = parse_url($url, PHP_URL_HOST);
        $decodeUrl = parse_url($url);
        $scheme = $decodeUrl['scheme'] ?? 'http';
        $host = $decodeUrl['host'] ?? '127.0.0.1';
        $port = $decodeUrl['port'] ?? 0;
        if($port == false) $port = ($scheme == 'https') ? 443 : 80;
        $ssl = $scheme == 'https' ? true : false;
        $path = ($decodeUrl['path'] ?? '/') . (isset($decodeUrl['query']) ? "?".$decodeUrl['query'] : "");
        $startTime = microtime(true);
        $client = new Client($host, $port, $ssl);

        $header = [
            'host' => $host,
        ];
        $data['delimiter'] = urlencode($data['delimiter']);
        $data['marker'] = urlencode($data['marker']);
        $data['prefix'] = urlencode($data['prefix']);
        $strToSign = $this->strToSignature("get", '/', $data, $header);
        $startSignTime = time(); // 签名开始时间
        $signTime = ($startSignTime - 60).';'.($startSignTime+ 10000);
        $signData = "sha1\n{$signTime}\n{$strToSign}\n";
        $signKey = hash_hmac('sha1', $signTime, $this->config['secretKey']);
        $sign = hash_hmac('sha1', $signData, $signKey);
        $authorization = "q-sign-algorithm=sha1&q-ak=".$this->config['secretId']."&q-sign-time=".$signTime."&q-key-time=".$signTime."&q-header-list=host&q-url-param-list=delimiter;encoding-type;marker;max-keys;prefix&q-signature={$sign}";
        $header['Authorization'] = $authorization;
        $header['user-agent'] = 'cos-python-sdk-v5.1.9.38';
        //$header['x-cos-security-token'] = $token;
        //$header['x-cos-sdk-retry'] = 'true';
        $client->setHeaders($header);// 设置请求HEADER
        $client->setMethod("GET");
        $client->execute($path);


        $sxml = simplexml_load_string($client->getBody());
        $booksArray = json_decode(json_encode($sxml), true);


        $outData = [
            'url' => $url,
            'code' => $client->getStatusCode(),
            'cookie' => $client->getCookies(),
            'header' => $client->getHeaders(),
            'body' =>  $booksArray ? $booksArray : $client->getBody(),
            'runtime' => microtime(true) - $startTime,
        ];
        $client->close();
        return $outData;
    }
    /**
     * 统一请求
     * @param string $url
     * @param string $method
     * @param string|array $data
     * @param int $retry
     * @return array
     */
    protected function request(string $url, string $method = 'GET', string|array $data = [], int $retry = 0)
    {
        $decodeUrl = parse_url($url);
        $scheme = $decodeUrl['scheme'] ?? 'http';
        $host = $decodeUrl['host'] ?? '127.0.0.1';
        $port = $decodeUrl['port'] ?? 0;
        if($port == false) $port = ($scheme == 'https') ? 443 : 80;
        $ssl = $scheme == 'https' ? true : false;
        $path = ($decodeUrl['path'] ?? '/') . (isset($decodeUrl['query']) ? "?".$decodeUrl['query'] : "");
        $startTime = microtime(true);
        $client = new Client($host, $port, $ssl);
        $client->set([
            'timeout' => 5,
            'keep_alive' => false,
        ]);
        $header = [
            'Host' => $host,
            'Content-Type' => 'application/json; charset=utf-8',
        ];
        $client->setHeaders($header);
        $client->setMethod($method);
        if($data) $client->setData($data);

        $client->execute($path);
        $outData = [
            'code' => $client->getStatusCode(),
            'cookie' => $client->getCookies(),
            'header' => $client->getHeaders(),
            'body' =>  $client->getBody(),
            'runtime' => microtime(true) - $startTime,
        ];
        $client->close();

        if($retry == 0 && $outData['runtime'] < 3 && ($outData['code'] == false || $outData['code'] < 0 || $outData['code'] >= 500)){
            return $this->request($url, $method, $data, 1);
        }
        $this->debugLog($url, $method, $header, $data, $outData);
        return $outData;
    }

    /**
     * Url内容审查
     * @param string $url
     * @return array
     */
    public function review(string $url)
    {
        $secret_id = $this->config['secretId'];
        $secret_key = $this->config['secretKey'];
        $token = "";

        $service = "ims";
        $host = "ims.tencentcloudapi.com";
        $req_region = $this->config['region'];
        $version = "2020-07-13";
        $action = "ImageModeration";
        $payload = "{\"FileUrl\":\"{$url}\"}";
        $params = json_decode($payload);
        $endpoint = "https://ims.tencentcloudapi.com";
        $algorithm = "TC3-HMAC-SHA256";
        $timestamp = time();
        $date = gmdate("Y-m-d", $timestamp);

        // ************* 步骤 1：拼接规范请求串 *************
        $http_request_method = "POST";
        $canonical_uri = "/";
        $canonical_querystring = "";
        $ct = "application/json; charset=utf-8";
        $canonical_headers = "content-type:".$ct."\nhost:".$host."\nx-tc-action:".strtolower($action)."\n";
        $signed_headers = "content-type;host;x-tc-action";
        $hashed_request_payload = hash("sha256", $payload);
        $canonical_request = "$http_request_method\n$canonical_uri\n$canonical_querystring\n$canonical_headers\n$signed_headers\n$hashed_request_payload";

        // ************* 步骤 2：拼接待签名字符串 *************
        $credential_scope = "$date/$service/tc3_request";
        $hashed_canonical_request = hash("sha256", $canonical_request);
        $string_to_sign = "$algorithm\n$timestamp\n$credential_scope\n$hashed_canonical_request";

        // ************* 步骤 3：计算签名 *************
        $secret_date = hash_hmac("sha256", $date, "TC3".$secret_key, true);
        $secret_service = hash_hmac("sha256", $service, $secret_date, true);
        $secret_signing = hash_hmac("sha256", "tc3_request", $secret_service, true);
        $signature = hash_hmac("sha256", $string_to_sign, $secret_signing);

        // ************* 步骤 4：拼接 Authorization *************
        $authorization = "$algorithm Credential=$secret_id/$credential_scope, SignedHeaders=$signed_headers, Signature=$signature";

        // ************* 步骤 5：构造并发起请求 *************
        $headers = [
            "Authorization" => $authorization,
            "Content-Type" => "application/json; charset=utf-8",
            "Host" => $host,
            "X-TC-Action" => $action,
            "X-TC-Timestamp" => $timestamp,
            "X-TC-Version" => $version
        ];
        if ($req_region) {
            $headers["X-TC-Region"] = $req_region;
        }
        if ($token) {
            $headers["X-TC-Token"] = $token;
        }

        if($this->isPhpFpm){
            $curl = new Curl();
            foreach ($headers as $k => $v){
                $curl->setHeader(strval($k), strval($v));
            }
            return $curl->request($endpoint, "POST", $payload);
        }
        $client = new Httpclient();
        foreach ($headers as $k => $v){
            $client->setHeader(strval($k), strval($v));
        }
        $outData = $client->setLogger($this->logger)->request($endpoint, "POST", $payload);
        return $outData;
    }

    /**
     * 记录请求日志
     * @param string $url
     * @param string $method
     * @param array $header
     * @param string|array $param
     * @param string $output
     * @return void
     */
    public function debugLog(string $url, string $method, array $header, string | array $param, array $output)
    {
        if($this->logger){
            $data = json_encode([
                'url' => $url,
                'method' => $method,
                'header' => $header,
                'param' => $param,
                'output' => $output
            ]);
            if($data) $this->logger->debug($data);
        }
    }
}