<?php
// +----------------------------------------------------------------------
// | zhanshop-php / Rsa.php    [ 2023/2/6 12:52 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\util;

class Rsa
{
    private $_privkey = '-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQCL51x8tkIJaft7tUOVcTa3IaWx/3gKRRcmJdM35NzWb0w/xSxD
ufhmRkI5jPjtpcCX/1hlzG6+7vNIy275h3CB/A7M4eDDoK66V5uIq6cOG402FvYO
PbI0zz23eV0tSxiGYUS9L7L1BHZxxGOXxdKd75h9Y0VsrCKpYqrcCGK8OwIDAQAB
AoGASptNuf/jxWtsPfNTIC2otQ5Pc1W+KRPhHWnMcqEsl3nw2o0GZvOUoM3U4SXp
Vaovw57bACZ95Ho+6NH70zvD5e1SdDLXldftZHFFCGBA0h/VaQnB37V0hDS4C5uJ
9EW1v8/zEdJ/uqnkxU1sE3BcdkZJpw6830xlEq6uN4vP38kCQQDzJSfr5boVltnK
Vjfey+kOClVdLVIXgMhBTKXX7L/V0wNMeAtYcajvmn7a7oENYemwUeCIdkOjhcLT
G2LS6iC9AkEAk0zi7Yd5RGX7ATbM1seoVy6IKI2EPiEfBJ5rQSnB+7azZ/1xp8co
3ae0sTxMouQVCoACMVMn4bUSiqkpEtfMVwJBAIqUzdkk+VeewAkT9QoWPIHVH+xY
jWZ57ylQr2GPPZN4jnPCI+1H8tqFuvG7aHChMtldAVWl2k1USsOZ40yFRx0CQHMK
aXD5jUxOGWspUXGtHVx5iv2hCNc9JuyEjV+nLUlZt2RQHKPcd54ljImffnr5hzVc
JFpBh7RPptuEof+FaqECQDs30HzNgEyLQ3t2j79b1I011kzAN9GFmeec8Qlh+wm3
iWcmTr+pPs8GhjtjamLkX/Ov2QNtcjIONl5cxVzlkyA=
-----END RSA PRIVATE KEY-----';

    private $_pubkey = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCL51x8tkIJaft7tUOVcTa3IaWx
/3gKRRcmJdM35NzWb0w/xSxDufhmRkI5jPjtpcCX/1hlzG6+7vNIy275h3CB/A7M
4eDDoK66V5uIq6cOG402FvYOPbI0zz23eV0tSxiGYUS9L7L1BHZxxGOXxdKd75h9
Y0VsrCKpYqrcCGK8OwIDAQAB
-----END PUBLIC KEY-----';

    private $_isbase64 = true;

    /**
     *
     * @初始化key值
     * @param string $privkey 私钥
     * @param string $pubkey 公钥
     * @param boolean $isbase64 是否base64编码
     * @return null
     */
    public function __construct($privkey, $pubkey, $isbase64 = true)
    {
        $this->_privkey = $privkey;
        $this->_pubkey = $pubkey;
        $this->_isbase64 = $isbase64;
    }

    /**
     *
     * @私钥加密
     * @param string $data 原文
     * @return string 密文
     */
    public function privEncode($data)
    {
        $outval = '';
        $res = openssl_pkey_get_private($this->_privkey);
        openssl_private_encrypt($data, $outval, $res);
        if ($this->_isbase64) {
            $outval = base64_encode($outval);
        }
        return $outval;
    }

    /**
     * @ 公钥解密
     * @param string $data 密文
     * @return string 原文
     */
    public function pubDecode($data)
    {
        $outval = '';
        if ($this->_isbase64) {
            $data = base64_decode($data);
        }
        $res = openssl_pkey_get_public($this->_pubkey);
        openssl_public_decrypt($data, $outval, $res);
        return $outval;
    }

    /**
     *
     * @公钥加密
     * @param string $data
     *            原文
     * @return string 密文
     */
    public function pubEncode($data)
    {
        $outval = '';
        $res = openssl_pkey_get_public($this->_pubkey);
        openssl_public_encrypt($data, $outval, $res);
        if ($this->_isbase64) {
            $outval = base64_encode($outval);
        }
        return $outval;
    }

    /**
     *
     * @私钥解密
     * @param string $data
     *            密文
     * @return string 原文
     */
    public function privDecode($data)
    {
        $outval = '';
        if ($this->_isbase64) {
            $data = base64_decode($data);
        }
        $res = openssl_pkey_get_private($this->_privkey);
        openssl_private_decrypt($data, $outval, $res);
        return $outval;
    }
}