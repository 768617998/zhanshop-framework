<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Config.php    [ 2024/3/29 19:17 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\weixin;

use zhanshop\App;

class Config
{
    public $config = [
        'mchid' => '123456', // 商户号
        'appid' => '123456', // 应用ID
        'aeskey' => 'xxxxxxxxxxxxxxxxxxxxxx', //APIv3密钥
        'serialnum' => '1259FBB606F60E32461B98BCA049258A75868C6D', // API证书编号
        'notifyurl' => 'https://api.zhanshop.cn/v1/trades.callback', // 支付通知url
        'privatekey' => '-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDJCNnzZZCkuIL0
vpvXtm05rhQHQMgQHXnlvEykYJWGNSPyELJFQ16D5blqvR8RxwsNUEqYBv1gCQdT
4oJUOv4V34c98vkbtFlrFwNMf+4IkT8bieOqW65U4vmmvMi7cIh2MLDzrXSqSTpi
yzIlZ8xu6M7NkAzAUGgBI01vzE4aUWd5VXG5I6ovRuKjE9uDvFeBZBJSg7coaDPd
cWR6Np+605hmE3JhcnEw+tRe5C9TejJVN0VIOAKhL3ztvXxTEwHOv3n+w1XEY/Qu
zvmUEEkww4Vbacx+QCpTGT43jFSJ/wWTS1x5gVMckV23f4obEMB46KmaUw1v2RzM
o17LBPW5AgMBAAECggEBAL5Pg8xX1LMq/MXmf6+YwhO9iLZJDFu9G71xIXjm+gXf
3pR0W98X5KymV34FZRk2PW8wGD2NOGWmhmpR3qEQa6KXRfdOQaaJd7DaS0QrQ1KW
9ZADXCPUwNcZO88tnjVbVcPVWl6EURI3Wm9V/rXMaoqAF+4ftQKF279gBRmV5kYg
JZad1oBC3q2B1JXPH6LMjurUWgaY0S0igWvd2Wc6+vP5vQX7OskJ93EVUvAZF2Ud
C+hGSx6zTA8WBOpLRhfnghc5HjB+Ac8RzHSShL+S5PPO9vTudTMMh/9rilSq+Oi5
MlP+uLHxsX0rAC2S1ojWsZoXRPvglzBNw/UFn8tDc4UCgYEA8dzpUe5OLV6fYO9+
0yCrJrukXmDjmHAGmNiWexl+YUADfQopyMvAsduYvtH4aCBxel76cHpVyulGKfOQ
WWwgPGGqTSN3kckz1qWEaf+bmHgBYXV8IimiGp8yuU19VW3EISfoUyp+kadbJe+6
MjqNrZJi9kg6K0lSKtM4+x3O9bMCgYEA1MkCaoLR9bbVfWMJtM8cBwEhHgXoKovi
cFbNadM/cAD6ETe9kufM8BChswZlJ8qImdWbIdaVlb4u3myksLS5f4T19UGcaKY3
cySnqlroYaaMoI3hLbn5EiZbl3pItXDE8h9Ey8QljAGcgvvnC+WGRzTV/nB/V+c7
5At1H2UeiOMCgYEAwBBIrApMxJSOhgDJuskHgNOjfFr+gY/8fSYJoammBX47BvGq
Ks06sUKMMoQ0+iNBWgbQY7+SH3dNg4TqTUii9LAt9+HBUuDvLHX2B+K2YcZyej08
nThohTGEq0gQzmVDSX0SbfCh3/KBXEfZmYeL+Jj1w1Op9bPc7ysa5bdPIScCgYAB
EkoK00HPZO5KRVdlK63OczkBd7IllLK9MhKyt7eYDskzkP5JYRVtGAhYaG3V93Uc
oTC4yOXT7TM7N3zUzvQoKjk61Ou+syeQUtztRKvq4fXPUSeTHV6dsse/SYRYNYso
VPP5urEdtVxeZHpTWwKEFUYGk/ZwMN/iVBRw5gdzVQKBgFHp77cGy+/bYtZbP8Qu
GH80KraRAPql5bguBPCUcpjPyFvH8zaSbhc5MQcZdXhuAx4K3qeWeSY5/D9xAKel
UPAoA69/zhE5jbO/L3szmXgG0L0TfqwO3h2TnnyNiDehBgOzSQ9hQOUJuyhMB7Sd
R9hk0fdHgX6raoKdQbqZJcYc
-----END PRIVATE KEY-----',
        // 货币类型
        'currency' => 'CNY',
        // 支付超时时间
        'timeout' => 1800,
    ];

    public function __construct()
    {
        $config = App::config()->get("pay.weixin");
        if($config) $this->config = array_merge($this->config, $config);

    }

    /**
     * 获取配置
     * @param string $name
     * @param $default
     * @return array|mixed|null
     */
    public function get(string $name = '', $default = null)
    {
        if($name === '') return $this->config;
        return $this->config[$name] ?? $default;
    }

    //////////////////////////////////////////////////////////
    public function sign(string $message, $privateKey): string
    {
        if (!openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {

            throw new Exception('Signing the input $message failed, please checking your $privateKey whether or nor correct.', 400);
        }

        return base64_encode($signature);
    }
    public function authorization(string $mchid, string $nonce, string $signature, string $timestamp, string $serial): string
    {
        return sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",serial_no="%s",timestamp="%s",nonce_str="%s",signature="%s"',
            $mchid, $serial, $timestamp, $nonce, $signature
        );
    }
    public function getNonce($size = 32)
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Size must be a positive integer.');
        }

        return implode('', array_map(static function(string $c): string {
            return '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'[ord($c) % 62];
        }, str_split(random_bytes($size))));
    }
    public function verify(string $message, string $signature, $publicKey)
    {
        if (($result = openssl_verify($message, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256)) === false) {

            throw new Exception('Verified the input $message failed, please checking your $publicKey whether or nor correct.', 400);
        }

        return $result === 1;
    }
    public function decrypt(string $ciphertext, string $key, string $iv = '', string $aad = ''): string
    {
        if (!in_array('aes-256-gcm', openssl_get_cipher_methods())) {
            throw new Exception('It looks like the ext-openssl extension missing the `aes-256-gcm` cipher method.', 400);
        }

        $ciphertext = base64_decode($ciphertext);
        $authTag = substr($ciphertext, $tailLength = 0 - 16);
        $tagLength = strlen($authTag);

        /* Manually checking the length of the tag, because the `openssl_decrypt` was mentioned there, it's the caller's responsibility. */
        if ($tagLength > 16 || ($tagLength < 12 && $tagLength !== 8 && $tagLength !== 4)) {
            throw new RuntimeException('The inputs `$ciphertext` incomplete, the bytes length must be one of 16, 15, 14, 13, 12, 8 or 4.');
        }

        $plaintext = openssl_decrypt(substr($ciphertext, 0, $tailLength), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $authTag, $aad);

        if (false === $plaintext) {
            throw new Exception('Decrypting the input $ciphertext failed, please checking your $key and $iv whether or nor correct.', 400);
        }

        return $plaintext;
    }

}