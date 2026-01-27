<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / BaiduMap.php    [ 2025/12/12 10:08 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\util\map;

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
     * @param string $retCoordtype
     * @return array|bool|string
     */
    public function geocoding(string $address, string $retCoordtype = 'bd09ll')
    {
        $address = urlencode($address);
        $client = new Httpclient();
        $result = $client->debug()->request("http://api.map.baidu.com/geocoding/v3/?address={$address}&extension_poi_infos=true&output=json&extension_analys_level=1&ret_coordtype={$retCoordtype}&ak=".$this->config['app_key']);
        $body = json_decode($result['body'] ?? "", true);
        if($body) $result['body'] = $body;
        return $result;
    }

    /**
     * 获取地标 有相似度对比
     * @param string $address
     * @param string $retCoordtype
     * @return array|bool|string
     */
    public function landmark(string $address, string $retCoordtype = 'bd09ll')
    {
        // 业务端可根据  poi_infos 下的level 验证地址类型是否正确
        //包含：UNKNOWN、国家、省、城市、区县、乡镇、村庄、道路、地产小区、商务大厦、政府机构、交叉路口、商圈、生活服务、休闲娱乐、餐饮、宾馆、购物、金融、教育、医疗 、工业园区 、旅游景点 、汽车服务、火车站、长途汽车站、桥 、停车场/停车区、港口/码头、收费区/收费站、飞机场 、机场 、收费处/收费站 、加油站、绿地、门址
        $geocoding = $this->geocoding($address, $retCoordtype);
        if(isset($geocoding['body']['poi_infos'])){
            $poiInfos = [];
            foreach ($geocoding['body']['poi_infos'] as $key => $value) {
                similar_text($value['formatted_address'], $address, $perc);
                $value['similarity'] = $perc;
                $perc = str_replace('.', '', strval($perc.$key));
                $poiInfos[intval($perc)] = $value;
            }
            krsort($poiInfos);
            $geocoding['body']['poi_infos'] = array_values($poiInfos);
        }
        return $geocoding;
    }
}