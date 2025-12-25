<?php
// +----------------------------------------------------------------------
// | hotel-search / Tencent.php    [ 2025/12/12 11:08 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\util\ditu;

use zhanshop\client\Httpclient;

class TencentMap
{
    protected $config = [
        "app_id" => "",
        "app_name" => "",
        "app_key" => "",
    ];

    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 地址转经纬度
     * @param string $address
     * @return array
     */
    public function geocoding(string $address)
    {
        $client = new Httpclient();
        //

        https://apis.map.qq.com/ws/geocoder/v1/?address=

        $result = $client->request("https://apis.map.qq.com/ws/geocoder/v1/?address={$address}&policy=1&output=json&key=".$this->config['app_key']);
        $body = json_decode($result['body'] ?? "", true);
        if($body) $result['body'] = $body;
        return $result;
    }
}