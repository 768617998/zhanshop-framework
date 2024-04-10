<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Callback.php    [ 2024/3/30 10:43 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\weixin;

class Callback
{
    /**
     * 支付回调
     * @param array $params
     * @param callable $callBack
     * @return string|void
     * @throws \Exception
     */
    public function pay(array &$params, callable $callBack)
    {
        $config = App::make(Config::class);

        $inWechatpayTimestamp = time(); // 从$data参数中取
        $inWechatpayNonce = ''; // 从$data参数中取
        $inBody = json_encode($data);
        $timeOffsetStatus = 300 >= abs(time() - (int)$inWechatpayTimestamp);

        $verifiedStatus = $config->verify(
            implode("\n", [
                'POST', $inWechatpayTimestamp, $inWechatpayNonce, $inBody
            ])."\n",
            $inWechatpayNonce,
            $config->get('privatekey')
        );
        if ($timeOffsetStatus && $verifiedStatus) {
            // 转换通知的JSON文本消息为PHP Array数组
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $data;
            // 加密文本消息解密
            $inBodyResource = $config->decrypt($ciphertext, $config->get('aeskey'), $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = (array)json_decode($inBodyResource, true);
            try {
                $params = $inBodyResourceArray;
                $callBack();
                return '<xml>
  <return_code><![CDATA[SUCCESS]]></return_code>
  <return_msg><![CDATA[OK]]></return_msg>
</xml> ';
            }catch (\Throwable $e){
                // 触发退款
                App::make(Refund::class)->pay($params['out_trade_no'], $data['total_amount'], $e->getMessage());
            }
        }
        App::error()->setError('支付签名验证失败');
    }
}