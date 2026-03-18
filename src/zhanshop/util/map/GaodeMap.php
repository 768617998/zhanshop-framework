<?php
// +----------------------------------------------------------------------
// | hotel-search / GaodeMap.php    [ 2025/12/12 14:42 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\util\map;

use zhanshop\client\Httpclient;

class GaodeMap
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
        $url = "https://restapi.amap.com/v3/geocode/geo?key=".$this->config["app_key"]."&address=".$address;
        $result = $client->debug()->request($url);
        $body = json_decode($result['body'] ?? "", true);
        if($body) $result['body'] = $body;
        return $result;
    }

    /**
     * 地址搜索
     * @param string $query
     * @param string $cicy
     * @param string $type
     * @return mixed
     */
    public function regionQuery(string $query, string $cicy, string $type)
    {
        $client = new Httpclient();
        $url = 'https://restapi.amap.com/v5/place/text?keywords='.urlencode($query).'&types='.$type.'&region='.urlencode($cicy).'&key='.$this->config["app_key"];
        $result = $client->debug()->request($url);
        $body = json_decode($result['body'] ?? "", true);
        if($body) $result['body'] = $body;
        return $result;
    }

    /**
     * 经纬度转省市区
     * @param string $lng 精度
     * @param string $lat 维度
     * @return array
     */
    public function geoconv(string $lng, string $lat)
    {
        $url = 'https://restapi.amap.com/v3/geocode/regeo?key='.$this->config["app_key"].'&location='.$lng.','.$lat;
        $client = new Httpclient();
        $result = $client->debug()->request($url);
        $body = json_decode($result['body'] ?? "", true);
        if($body) $result['body'] = $body;
        return $result;
    }
}