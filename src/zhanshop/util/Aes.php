<?php
namespace zhanshop\util;

use zhanshop\App;

class Aes
{
    /**
     * var string $method 加解密方法
     */
    protected $method = 'AES-256-CBC';

    protected $commonKey = 'bxV8lFZs8oqmREMz9^kacHn%y0zE*KnNGwp*dt!JV7olxwKz6d1NjLH9TQSBqEZhvF4C$vzC*6F^oonTi9$Xt4Tt35QAffigBiN';

    /**
     * var int $options
     */
    protected $options = 0;

    /**
     * 构造器
     * 要求 APP_KEY 必须配置且长度满足 AES-256 要求(32字节)，
     * 否则抛出异常拒绝启动，防止弱密钥或默认密钥导致 token 可被伪造。
     */
    public function __construct(){
        $appKey = App::config()->get('app.app_key');
        if(!$appKey || strlen($appKey) < 32){
            throw new \RuntimeException('APP_KEY 未配置或长度不足32字节，请在 .env 中设置 APP_KEY（生成方式: openssl rand -hex 32）');
        }
        $this->commonKey = $appKey;
    }

    /**
     * 加密
     * @param string $data 要加密的数据
     * @return string
     */
    public function encrypt($data)
    {
        $iv = openssl_random_pseudo_bytes(16);
        return base64_encode($iv . openssl_encrypt($data, $this->method, $this->commonKey, $this->options, $iv));
    }

    /**
     * 解密
     * @param string $data 要解密的数据
     * @return string
     */
    public function decrypt($data)
    {
        $decoded = base64_decode($data);
        $iv = substr($decoded, 0, 16);
        return openssl_decrypt(substr($decoded, 16), $this->method, $this->commonKey, $this->options, $iv);
    }
}
