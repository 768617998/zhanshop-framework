<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / IpLocate.php    [ 2025/4/30 17:17 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\util;

use itbdw\Ip\IpLocation;
use zhanshop\App;

class IpLocate
{
    public function __construct()
    {
        App::phar()->import('extend/library/ip-database');
        $qqwry = App::runtimePath().'/qqwry.dat';
        $ipv6wry = App::runtimePath().'/ipv6wry.db';
        if(file_exists($qqwry) == false || file_exists($ipv6wry) == false){
            App::error()->setError($qqwry.'/'.$ipv6wry.'文件不存在');
        }
        IpLocation::setIpV4Path($qqwry);
        IpLocation::setIpV6Path($ipv6wry);
    }

    /**
     * 获取IP定位信息
     * @param string $ip
     * @return array|mixed|string[]
     */
    public function getLocation(string $ip)
    {
        $data = IpLocation::getLocation($ip);
        $city = $data['city'] ?? '';
        $province = $data['province'] ?? '';
        if($city == false && $province && in_array($province, ['北京', '上海', '天津', '重庆', "香港", "澳门"])){
            $data['city'] = $province;
        }
        return $data;
    }
}