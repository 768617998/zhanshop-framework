<?php
// +----------------------------------------------------------------------
// | zhanshop-device / Callback.php    [ 2024/3/30 9:51 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\payment\alipay;

use zhanshop\App;

class Callback
{
    /**
     * 支付回调
     * @param array $params
     * @param callable $callBack
     * @return string|void
     * @throws \Exception
     */
    public function pay(array $params, callable $callBack)
    {
        $config = App::make(Config::class);
        if($config->verify($params)){
            try {
                $callBack();
            }catch (\Throwable $e){
                // 触发退款
                App::make(Refund::class)->pay($params['out_trade_no'], $data['total_amount'], $e->getMessage());
            }
            return 'success';
        }
        App::error()->setError('支付签名验证失败');
    }

}