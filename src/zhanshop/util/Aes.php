<?php
namespace zhanshop\util;

use zhanshop\App;

class Aes
{
    /**
     * var string $method 加解密方法
     */
    protected $method = 'AES-256-CBC';

    protected $commonKey = '';

    /**
     * 认证密钥：由主密钥派生，专门用于 HMAC-SHA256
     */
    protected $macKey = '';

    /**
     * var int $options
     */
    protected $options = OPENSSL_RAW_DATA;

    private const IV_LEN = 16;

    private const MAC_LEN = 32;

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
        // 加密与认证使用不同派生密钥，避免同一密钥同时承担两种用途
        $this->macKey = hash_hmac('sha256', 'zhanshop-aes-mac-v1', $appKey, true);
    }

    /**
     * 加密（Encrypt-then-MAC）
     * 格式：base64(IV || CIPHERTEXT || HMAC-SHA256(IV || CIPHERTEXT))
     * @param string $data 要加密的数据
     * @return string
     */
    public function encrypt($data)
    {
        $iv = random_bytes(self::IV_LEN);
        $cipher = openssl_encrypt((string)$data, $this->method, $this->commonKey, $this->options, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('AES 加密失败');
        }
        // 认证标签覆盖 IV 和密文：改 IV（CBC 可塑性）或改密文都会导致校验失败
        $mac = hash_hmac('sha256', $iv . $cipher, $this->macKey, true);
        return base64_encode($iv . $cipher . $mac);
    }

    /**
     * 解密
     * 格式不对或 MAC 校验失败一律返回 false；旧 token 没有 MAC，会被直接拒绝
     * @param string $data 要解密的数据
     * @return string|false
     */
    public function decrypt($data)
    {
        if (!is_string($data)) {
            return false;
        }
        $raw = base64_decode($data, true);
        if ($raw === false || strlen($raw) < self::IV_LEN + 16 + self::MAC_LEN) {
            return false;
        }
        $iv = substr($raw, 0, self::IV_LEN);
        $cipher = substr($raw, self::IV_LEN, -self::MAC_LEN);
        $mac = substr($raw, -self::MAC_LEN);
        // 密文必须是 AES 块长的整数倍
        if ($cipher === '' || strlen($cipher) % 16 !== 0) {
            return false;
        }
        // 必须先验 MAC 再解密：先解密再判断等于没有防护
        if (!hash_equals(hash_hmac('sha256', $iv . $cipher, $this->macKey, true), $mac)) {
            return false;
        }
        return openssl_decrypt($cipher, $this->method, $this->commonKey, $this->options, $iv);
    }
}
