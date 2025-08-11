<?php
namespace zhanshop\util;

use zhanshop\App;

class Aes
{
    /**
     * var string $method 加解密方法，可通过openssl_get_cipher_methods()获得
     */
    protected $method = 'AES-256-ECB';

    protected $commonKey = 'bxV8lFZs8oqmREMz9^kacHn%y0zE*KnNGwp*dt!JV7olxwKz6d1NjLH9TQSBqEZhvF4C$vzC*6F^oonTi9$Xt4Tt35QAffigBiN';

    /**
     * var string $iv 加解密的向量，有些方法需要设置比如CBC
     */
    protected $iv = '';

    /**
     * var string $options （不知道怎么解释，目前设置为0没什么问题）
     */
    protected $options = 0;

    /**
     * 构造器
     * Aes constructor.
     */
    public function __construct(){
        $appKey = App::config()->get('app.app_key');
        if($appKey) $this->commonKey = $appKey;
    }

    /**
     * @加密方法，对数据进行加密，返回加密后的数据
     *
     * @param string $data 要加密的数据
     *
     * @return string
     *
     */
    public function encrypt($data)
    {
        return openssl_encrypt(openssl_encrypt($data, $this->method, $this->commonKey, $this->options, $this->iv), $this->method, $this->commonKey, $this->options, $this->iv);
    }

    /**
     * @解密方法，对数据进行解密，返回解密后的数据
     *
     * @param string $data 要解密的数据
     *
     * @return string
     *
     */
    public function decrypt($data)
    {
        return openssl_decrypt(openssl_decrypt($data, $this->method, $this->commonKey, $this->options, $this->iv), $this->method, $this->commonKey, $this->options, $this->iv);
    }
}

