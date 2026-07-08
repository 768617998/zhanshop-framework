<?php
// +----------------------------------------------------------------------
// | qxl-service / TencentQian.php    [ 2026/5/8 10:07 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2026 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\thirdparty;

use zhanshop\App;
use zhanshop\Log;

class TencentQian
{
    // https://console.cloud.tencent.com/cam/capi 腾讯云API密钥管理
    protected $config = [
        // 腾讯云API密钥管理里面的APPID
        "appId" => "123456",
        // 腾讯云API密钥管理里面的SecretId
        "secretId" => "xxxxxxxxxxxxxxxxxxx",
        // 腾讯云API密钥管理创建时候的secretKey
        "secretKey" => "xxxxxxxxxxxxxxxxxxx",
        // https://qian.tencent.com/console/user-info获取我的电子签账号
        "userId" => "xxxxxxxxxxxxxx",
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
        print_r($this->config);
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

    protected function sign($key, $msg) {
        return hash_hmac("sha256", $msg, $key, true);
    }

    /**
     * 模板列表
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function templates(int $page = 1, int $limit = 20)
    {
        // 密钥信息从环境变量读取，需要提前在环境变量中设置 TENCENTCLOUD_SECRET_ID 和 TENCENTCLOUD_SECRET_KEY
        // 使用环境变量方式可以避免密钥硬编码在代码中，提高安全性
        // 生产环境建议使用更安全的密钥管理方案，如密钥管理系统(KMS)、容器密钥注入等
        // 请参见：https://cloud.tencent.com/document/product/1278/85305
        // 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
        $offset = ($page - 1) * $limit;
        $secret_id = $this->config["secretId"];
        $secret_key = $this->config["secretKey"];
        $userId = $this->config["userId"];
        $token = "";

        $service = "ess";
        $host = "ess.tencentcloudapi.com";
        $req_region = "";
        $version = "2020-11-11";
        $action = "DescribeFlowTemplates";
        $payload = "{\"Operator\":{\"UserId\":\"{$userId}\"},\"Offset\":{$offset},\"Limit\":{$limit},\"WithPreviewUrl\":false}";
        $params = json_decode($payload);
        $endpoint = "https://ess.tencentcloudapi.com";
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
        $secret_date = $this->sign("TC3".$secret_key, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
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

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->debugLog($endpoint, "GET", $headers, $payload, $response);
            return json_decode($response ?? "", true);
        } catch (Exception $err) {
            throw new \Exception($err->getMessage());
        }

    }

    /**
     * 上传文件
     * @param string $name
     * @param string $body
     * @return array
     */
    public function uploadFiles(string $name, string $body, $businessType = "DOCUMENT")
    {

        // 密钥信息从环境变量读取，需要提前在环境变量中设置 TENCENTCLOUD_SECRET_ID 和 TENCENTCLOUD_SECRET_KEY
        // 使用环境变量方式可以避免密钥硬编码在代码中，提高安全性
        // 生产环境建议使用更安全的密钥管理方案，如密钥管理系统(KMS)、容器密钥注入等
        // 请参见：https://cloud.tencent.com/document/product/1278/85305
        // 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
        $secret_id = $this->config["secretId"];
        $secret_key = $this->config["secretKey"];
        $token = "";

        $service = "ess";
        $host = "file.ess.tencent.cn";
        $req_region = $this->config["region"];
        $version = "2020-11-11";
        $action = "UploadFiles";
        $payload = json_encode([
            "BusinessType" => $businessType,
            "FileInfos" => [
                [
                    "FileBody" => $body,
                    "FileName" => $name
                ]
            ],
            "FileType" => pathinfo($name, PATHINFO_EXTENSION)
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $params = json_decode($payload);
        $endpoint = "https://".$host;
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
        // return hash_hmac("sha256", $msg, $key, true);

        $secret_date = $this->sign("TC3".$secret_key, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
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

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->debugLog($endpoint, $http_request_method, $headers, $payload, $response);
            return json_decode($response ?? "", true);
        } catch (Exception $err) {
            throw new \Exception($err->getMessage());
        }
    }

    /**
     * 转换任务
     * @param string $id 电子签文件ID，可通过上传接口获取
     * @param string $name 上传时候的文件名称
     * @return mixed
     * @throws \Exception
     */
    public function convertTask(string $id, string $name)
    {
        // 密钥信息从环境变量读取，需要提前在环境变量中设置 TENCENTCLOUD_SECRET_ID 和 TENCENTCLOUD_SECRET_KEY
        // 使用环境变量方式可以避免密钥硬编码在代码中，提高安全性
        // 生产环境建议使用更安全的密钥管理方案，如密钥管理系统(KMS)、容器密钥注入等
        // 请参见：https://cloud.tencent.com/document/product/1278/85305
        // 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
        $secret_id = $this->config["secretId"];
        $secret_key = $this->config["secretKey"];
        $userId = $this->config["userId"];
        $token = "";

        $service = "ess";
        $host = "ess.tencentcloudapi.com";
        $req_region = "";
        $version = "2020-11-11";
        $action = "CreateConvertTaskApi";
        $payload = json_encode([
            "ResourceId" => $id,
            "ResourceType" => pathinfo($name, PATHINFO_EXTENSION),
            "ResourceName" => $name,
            "Operator" => [
                "UserId" => $userId
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $params = json_decode($payload);
        $endpoint = "https://ess.tencentcloudapi.com";
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
        $secret_date = $this->sign("TC3".$secret_key, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
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

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->debugLog($endpoint, $http_request_method, $headers, $payload, $response);
            return json_decode($response ?? "", true);
        } catch (Exception $err) {
            throw new \Exception($err->getMessage());
        }

    }

    /**
     * 获取转换任务结果
     * @param $taskId
     * @return mixed
     * @throws \Exception
     */
    public function convertTaskResult($taskId)
    {
        $secret_id = $this->config["secretId"];
        $secret_key = $this->config["secretKey"];
        $userId = $this->config["userId"];
        $token = "";

        $service = "ess";
        $host = "ess.tencentcloudapi.com";
        $req_region = "ap-guangzhou";
        $version = "2020-11-11";
        $action = "GetTaskResultApi";
        $payload = "{\"TaskId\":\"{$taskId}\",\"Operator\":{\"UserId\":\"{$userId}\"}}";


        $params = json_decode($payload);
        $endpoint = "https://ess.tencentcloudapi.com";
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
        $secret_date = $this->sign("TC3".$secret_key, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
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

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->debugLog($endpoint, $http_request_method, $headers, $payload, $response);
            return json_decode($response ?? "", true);
        } catch (Exception $err) {
            throw new \Exception($err->getMessage());
        }
    }

    /**
     * 创建签署流程
     * @param string $flowName
     * @param string $flowDescription
     * @param array $approvers
     * @return void
     */
    public function createFlow(string $flowName, string $flowDescription, array $approvers)
    {
        // 密钥信息从环境变量读取，需要提前在环境变量中设置 TENCENTCLOUD_SECRET_ID 和 TENCENTCLOUD_SECRET_KEY
// 使用环境变量方式可以避免密钥硬编码在代码中，提高安全性
// 生产环境建议使用更安全的密钥管理方案，如密钥管理系统(KMS)、容器密钥注入等
// 请参见：https://cloud.tencent.com/document/product/1278/85305
// 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
        $secret_id = $this->config["secretId"];
        $secret_key = $this->config["secretKey"];
        $userId = $this->config["userId"];
        $token = "";

        $service = "ess";
        $host = "ess.tencentcloudapi.com";
        $req_region = "";
        $version = "2020-11-11";
        $action = "CreateFlow";
        $payload = json_encode([
            "Operator" => [
                "UserId" => $userId,
            ],
            "FlowName" => $flowName,
            "Approvers" => $approvers
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE + JSON_INVALID_UTF8_IGNORE);
        //$payload = "{\"Operator\":{\"UserId\":\"yD3hDUU12qmrw7UxdPQRBS0gddCTH3IJ\"},\"FlowName\":\"欠款协议\",\"Approvers\":[{\"ApproverType\":1}]}";
        $params = json_decode($payload);
        $endpoint = "https://ess.tencentcloudapi.com";
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
        $secret_date = $this->sign("TC3".$secret_key, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
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

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->debugLog($endpoint, $http_request_method, $headers, $payload, $response);
            return json_decode($response, true);
        } catch (Exception $err) {
            throw new \Exception($err->getMessage());
        }

    }

    /**
     * 模板发起合同-创建电子文档
     * @param string $templateId
     * @param string $flowId
     * @param array $formFields
     * @return array
     */
    public function createDocument(string $templateId, string $flowId, array $formFields)
    {
        // 密钥信息从环境变量读取，需要提前在环境变量中设置 TENCENTCLOUD_SECRET_ID 和 TENCENTCLOUD_SECRET_KEY
// 使用环境变量方式可以避免密钥硬编码在代码中，提高安全性
// 生产环境建议使用更安全的密钥管理方案，如密钥管理系统(KMS)、容器密钥注入等
// 请参见：https://cloud.tencent.com/document/product/1278/85305
// 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
        $secret_id = $this->config["secretId"];
        $secret_key = $this->config["secretKey"];
        $userId = $this->config["userId"];
        $token = "";

        $service = "ess";
        $host = "ess.tencentcloudapi.com";
        $req_region = "ap-guangzhou";
        $version = "2020-11-11";
        $action = "CreateDocument";
        $payload = json_encode([
            "Operator" => [
                "UserId" => $userId,
            ],
            "TemplateId" => $templateId,
            "FlowId" => $flowId,
            "FormFields" => $formFields
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE + JSON_INVALID_UTF8_IGNORE);
        //$payload = "{\"Operator\":{\"UserId\":\"111111111\"},\"TemplateId\":\"ssssssssss\",\"FlowId\":\"444444444444444\",\"FormFields\":[{\"ComponentValue\":\"11111\",\"ComponentId\":\"22\",\"ComponentName\":\"22222\"}]}";
        $params = json_decode($payload);
        $endpoint = "https://ess.tencentcloudapi.com";
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
        $secret_date = $this->sign("TC3".$secret_key, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
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

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            return json_decode($response, true);
        } catch (Exception $err) {
            throw new \Exception($err->getMessage());
        }
    }

    /**
     * 创建签署流程
     * @param string $flowId
     * @return array
     */
    public function startFlow(string $flowId)
    {
        // 密钥信息从环境变量读取，需要提前在环境变量中设置 TENCENTCLOUD_SECRET_ID 和 TENCENTCLOUD_SECRET_KEY
// 使用环境变量方式可以避免密钥硬编码在代码中，提高安全性
// 生产环境建议使用更安全的密钥管理方案，如密钥管理系统(KMS)、容器密钥注入等
// 请参见：https://cloud.tencent.com/document/product/1278/85305
// 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
        $secret_id = $this->config["secretId"];
        $secret_key = $this->config["secretKey"];
        $userId = $this->config["userId"];
        $token = "";

        $service = "ess";
        $host = "ess.tencentcloudapi.com";
        $req_region = "";
        $version = "2020-11-11";
        $action = "StartFlow";
        $payload = "{\"Operator\":{\"UserId\":\"{$userId}\"},\"FlowId\":\"{$flowId}\"}";
        $params = json_decode($payload);
        $endpoint = "https://ess.tencentcloudapi.com";
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
        $secret_date = $this->sign("TC3".$secret_key, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
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

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->debugLog($endpoint, $http_request_method, $headers, $payload, $response);
            return json_decode($response, true);
        } catch (Exception $err) {
            throw new \Exception($err->getMessage());
        }
    }

    /**
     * 签署连接
     * @param string $flowId
     * @param array $flowApproverInfos
     * @return array
     */
    public function createFlowSignUrl(string $flowId, array $flowApproverInfos)
    {
        // 密钥信息从环境变量读取，需要提前在环境变量中设置 TENCENTCLOUD_SECRET_ID 和 TENCENTCLOUD_SECRET_KEY
// 使用环境变量方式可以避免密钥硬编码在代码中，提高安全性
// 生产环境建议使用更安全的密钥管理方案，如密钥管理系统(KMS)、容器密钥注入等
// 请参见：https://cloud.tencent.com/document/product/1278/85305
// 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
        $secret_id = $this->config["secretId"];
        $secret_key = $this->config["secretKey"];
        $userId = $this->config["userId"];
        $token = "";

        $service = "ess";
        $host = "ess.tencentcloudapi.com";
        $req_region = "ap-guangzhou";
        $version = "2020-11-11";
        $action = "CreateFlowSignUrl";
        $payload = json_encode([
            "FlowId" => $flowId,
            "FlowApproverInfos" => $flowApproverInfos,
            "Operator" => [
                "UserId" => $userId,
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE + JSON_INVALID_UTF8_IGNORE);
        //$payload = "{\"FlowId\":\"{$flowId}\",\"Operator\":{\"UserId\":\"{$userId}\"}}";
        $params = json_decode($payload);
        $endpoint = "https://ess.tencentcloudapi.com";
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
        $secret_date = $this->sign("TC3".$secret_key, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
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

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);

            $this->debugLog($endpoint, $http_request_method, $headers, $payload, $response);
            return json_decode($response, true);
        } catch (Exception $err) {
            throw new \Exception($err->getMessage());
        }
    }

    /**
     * 生成小程序签署地址
     * @param string $flowId
     * @param array $flowApproverInfos
     * @return mixed
     * @throws \Exception
     */
    public function createSchemeUrl(string $flowId, array $flowApproverInfos)
    {
        $secret_id = $this->config["secretId"];
        $secret_key = $this->config["secretKey"];
        $userId = $this->config["userId"];
        $token = "";

        $service = "ess";
        $host = "ess.tencentcloudapi.com";
        $req_region = "";
        $version = "2020-11-11";
        $action = "CreateSchemeUrl";
        $payload = [
            "FlowId" => $flowId,
            "Operator" => [
                "UserId" => $userId,
            ]
        ];
        $payload["OrganizationName"] = $flowApproverInfos[0]["OrganizationName"];
        $payload["Mobile"] = $flowApproverInfos[0]["ApproverMobile"];
        $payload["Name"] = $flowApproverInfos[0]["ApproverName"];

        $payload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE + JSON_INVALID_UTF8_IGNORE);
        $params = json_decode($payload);
        $endpoint = "https://ess.tencentcloudapi.com";
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
        $secret_date = $this->sign("TC3".$secret_key, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
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

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->debugLog($endpoint, $http_request_method, $headers, $payload, $response);
            return json_decode($response, true);
        } catch (Exception $err) {
            throw new \Exception($err->getMessage());
        }
    }

    private function debugLog($url, $method, $headers, $param, $output)
    {
        if($this->logger){
            $this->logger->debug(json_encode([
                'url' => $url,
                'method' => $method,
                'headers' => $headers,
                'param' => $param,
                'result' => $output
            ],JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE + JSON_INVALID_UTF8_IGNORE));
        }
    }
}