<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / BaiduMap.php    [ 2025/12/12 10:08 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\util\ditu;

use zhanshop\client\Httpclient;

class BaiduMap
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
        $address = urlencode($address);
        $client = new Httpclient();
        $result = $client->request("http://api.map.baidu.com/geocoding/v3/?address={$address}&output=json&ak=".$this->config['app_key']);
        var_dump($result);
        $body = json_decode($result['body'] ?? "", true);
        if($body) $result['body'] = $body;
        return $result;
    }
}