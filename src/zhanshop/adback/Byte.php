<?php
// +----------------------------------------------------------------------
// | yunduo-ocr / Byte.php    [ 2024/5/29 下午1:07 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2024 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: Administrator <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\adback;

use zhanshop\App;
use zhanshop\Curl;

/**
 * CREATE TABLE `app_ad_postback_byte` (
 * `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
 * `event_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '事件名称',
 * `promotion_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '广告ID',
 * `project_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目ID',
 * `promotion_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '广告名称',
 * `project_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目名称',
 * `mid1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '图片素材宏参数(下发原始素材id）',
 * `mid2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '标题素材宏参数（下发原始素材id）',
 * `mid3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '视频素材宏参数（下发原始素材id）',
 * `mid4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '搭配试玩素材宏参数（下发原始素材id）',
 * `mid5` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '落地页素材宏参数（下发原始素材id）',
 * `mid6` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '安卓下载详情页素材宏参数（下发原始素材id）',
 * `aid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '广告计划id',
 * `aid_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '广告计划名称',
 * `cid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '广告创意id',
 * `cid_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '广告创意名称',
 * `campaign_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '广告组id',
 * `campaign_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '广告组名称',
 * `ctype` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '创意样式',
 * `advertiser_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '广告主id',
 * `csite` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '广告投放位置',
 * `convert_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '转化id',
 * `request_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '请求下发的id',
 * `track_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '    \r\n请求下发的id&创意id的md5,16位',
 * `sl` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '这次请求的语言',
 * `imei` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '安卓的设备 ID 的 md5 摘要，32位',
 * `idfa` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IOS 6+的设备id字段，32位',
 * `idfa_md5` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IOS 6+的设备id的md5摘要，32位',
 * `androidid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '安卓id原值的md5，32位',
 * `oaid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Android Q及更高版本的设备号，32位',
 * `oaid_md5` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Android Q及更高版本的设备号的md5摘要，32位',
 * `os` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作系统平台',
 * `mac` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '移动设备mac地址',
 * `mac1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '移动设备 mac 地址,转换成大写字母,并且取md5摘要后的结果，32位',
 * `ipv4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0' COMMENT '优先使用上报请求的对端 IP 地址。如果该IP为 IPv6, 则使用客户端获取的 client_ipv4 地址',
 * `ipv6` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '    \r\n优先使用上报请求的对端 IP 地址。如果该IP为 IPv4, 则使用客户端获取的 client_ipv6 地址',
 * `ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '[下发逻辑修改 - 0727生效]正常情况下，全量下发IPv4地址。极少数情况如IPV4无法取数，则下发 IPv6',
 * `ua` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '    \r\n用户代理(User Agent)，一个特殊字符串头，使得服务器能够识别客户使用的操作系统及版本、CPU类型、浏览器及版本、浏览器渲染引擎、浏览器语言、浏览器插件等',
 * `geo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '位置信息，包含三部分:latitude（纬度），longitude（经度）以及precise（确切信息,精度）',
 * `ts` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '客户端发生广告点击事件的时间，以毫秒为单位时间戳',
 * `callback_url` text COLLATE utf8mb4_unicode_ci COMMENT '直接把调用事件回传接口的url生成出来，广告主可以直接使用',
 * `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '手机型号',
 * `union_site` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '对外广告位编码',
 * `caid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '中国广告协会互联网广告标识',
 * `caid_md5` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '中国广告协会互联网广告标识，包含最新两个版本的CAID和版本号',
 * `productid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品id，仅支持站内',
 * `outerid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品广告点击监测宏参拼接商品id字段',
 * `callback_param` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '一些跟广告信息相关的回调参数，内容是一个加密字符串，在调用事件回传接口的时候会用到',
 * `create_time` int unsigned DEFAULT NULL COMMENT '入库时间',
 * `update_time` int unsigned DEFAULT NULL COMMENT '更新时间',
 * `status` tinyint unsigned DEFAULT '0' COMMENT '状态',
 * `result` text COLLATE utf8mb4_unicode_ci COMMENT '上报结果',
 * PRIMARY KEY (`id`)
 * ) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='字节跳动广告监测数据';
 */
class Byte
{

    protected $config = [
        'gateway' => 'https://analytics.oceanengine.com/api/v2/conversion',
        'table' => 'app_ad_postback_byte', // 表名
        'pk' => 'id', // 组件名
        'event_field' => 'event_name', // 事件字段 事件类型(show:展示，click点击，play:播放，playend:播放结束，playvalid：有效播放)
        'id_field' => ['oaid', 'imei', 'mac', 'idfa', 'androidid'], // 匹配的id名
        'callback_field' => 'callback_param', // 回调参数字段
        'status_field' => 'status', // 状态字段
        'result_field' => 'result', // 响应结果字段
        'uptime_field' => 'update_time',
        'events' => [
            'active' => 'active', // 激活的别名
            'register' => 'active_register', // 注册
            'complete_order' => 'in_app_order', // 下单
            'purchase' => 'active_pay', // 付费
            'ltroi' => 'lt_roi', // 广告变现ROI
            'retention7d' => 'retention_7d', // 7日留存
            'nextdayopen' => 'next_day_open', // 次留
            'uv' => 'in_app_uv', // 上报uv
        ],
    ];

    /**
     * 修改配置
     * @param string $key
     * @param mixed $val
     * @return void
     */
    public function setConfig(string $key, mixed $val)
    {
        $this->config[$key] = $val;
    }

    /**
     * 获取查询条件语句
     * @param array $where
     * @return string
     */
    protected function whereRawOr(array $where)
    {
        $whereRaw = "";
        foreach ($where as $k => $v){
            if(in_array($k, $this->config['id_field'])){
                if($v){
                    $whereRaw .= $k.' = "'.addslashes($v).'" or ';
                }
            }
        }
        return rtrim($whereRaw, 'or ');
    }

    /**
     * 事件转归因数字
     * @param string $eventName
     * @return void
     */
    protected function matchType(string $eventName)
    {
        $type = 0;
        if($eventName == "click"){
            $type = 1;
        }else if($eventName == 'play'){
            $type = 2;
        }else if($eventName == 'playend'){
            $type = 2;
        }else if($eventName == 'playvalid'){
            $type = 2;
        }
        return $type;
    }


    /**
     * 激活上报
     * @param string $oaid
     * @param array $otherId
     * @param array $moreParam
     * @return void
     */
    public function active(string $oaid, array $otherId = [], array $moreParam = [])
    {
        $where = [$this->config['id_field'][0] => $oaid];
        $where = array_merge($where, $otherId);
        $backLog = App::database()->model($this->config['table'])->where([$this->config['status_field'] => 0])->whereRaw($this->whereRawOr($where))->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
        if($backLog){
            $param = [
                'event_type' => $this->config['events']['active'] ?? 'active',
                'context' => [
                    'ad' => [
                        'callback' => $backLog[$this->config['callback_field']],
                        'match_type' => $this->matchType($backLog[$this->config['event_field']]),
                    ]
                ],
                'timestamp' => time() * 1000
            ];
            if($moreParam){
                foreach ($moreParam as $k => $v){
                    $param[$k] = $v;
                }
            }
            $resp = (new Curl())->debug()->setHeader("Content-Type", "application/json")->request($this->config['gateway'], "POST", $param)['body'];
            App::database()->model($this->config['table'])->where([$this->config['pk'] => $backLog[$this->config['pk']]])->update([$this->config['status_field'] => 1, $this->config['result_field'] => $resp, $this->config['uptime_field'] => time()]);
            return $resp;
        }
    }

    /**
     * uv事件上报
     * @param string $oaid
     * @param array $otherId
     * @param array $moreParam
     * @return void
     */
    public function uv(string $oaid, array $otherId = [], array $moreParam = [])
    {
        $where = [$this->config['id_field'][0] => $oaid];
        $where = array_merge($where, $otherId);
        $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->where(['status' => 1])->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
        if($backLog){
            $param = [
                'event_type' => $this->config['events']['uv'] ?? 'in_app_uv',
                'context' => [
                    'ad' => [
                        'callback' => $backLog[$this->config['callback_field']],
                        'match_type' => $this->matchType($backLog[$this->config['event_field']]),
                    ]
                ],
                'timestamp' => time() * 1000
            ];
            if($moreParam){
                foreach ($moreParam as $k => $v){
                    $param[$k] = $v;
                }
            }
            $resp = (new Curl())->debug()->setHeader("Content-Type", "application/json")->request($this->config['gateway'], "POST", $param)['body'];
            App::database()->model($this->config['table'])->where([$this->config['pk'] => $backLog[$this->config['pk']]])->update([$this->config['status_field'] => 1, $this->config['result_field'] => $resp, $this->config['uptime_field'] => time()]);
            return $resp;
        }
    }

    /**
     * 注册
     * @param string $oaid
     * @param array $otherId
     * @param array $moreParam
     * @return void
     */
    public function register(string $oaid, array $otherId = [], array $moreParam = [])
    {
        $where = [$this->config['id_field'][0] => $oaid];
        $where = array_merge($where, $otherId);
        $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->where(['status' => 1])->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
        if($backLog == false){
            $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
            if($backLog){
                $this->active($oaid, $otherId, $moreParam);
            }
        }
        if($backLog){
            $param = [
                'event_type' => $this->config['events']['register'] ?? 'active_register',
                'context' => [
                    'ad' => [
                        'callback' => $backLog[$this->config['callback_field']],
                        'match_type' => $this->matchType($backLog[$this->config['event_field']]),
                    ]
                ],
                'timestamp' => time() * 1000
            ];
            if($moreParam){
                foreach ($moreParam as $k => $v){
                    $param[$k] = $v;
                }
            }
            $resp = (new Curl())->debug()->setHeader("Content-Type", "application/json")->request($this->config['gateway'], "POST", $param)['body'];
            App::database()->model($this->config['table'])->where([$this->config['pk'] => $backLog[$this->config['pk']]])->update([$this->config['status_field'] => 1, $this->config['result_field'] => $resp, $this->config['uptime_field'] => time()]);
            return $resp;
        }
    }

    /**
     * 下单
     * @param string $oaid
     * @param array $otherId
     * @param array $moreParam
     * @return void
     */
    public function completeOrder(string $oaid, array $otherId = [], array $moreParam = [])
    {
        $where = [$this->config['id_field'][0] => $oaid];
        $where = array_merge($where, $otherId);
        $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->where(['status' => 1])->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
        if($backLog == false){
            $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
            if($backLog){
                $this->active($oaid, $otherId, $moreParam);
            }
        }
        if($backLog){
            $param = [
                'event_type' => $this->config['events']['complete_order'] ?? 'in_app_order',
                'context' => [
                    'ad' => [
                        'callback' => $backLog[$this->config['callback_field']],
                        'match_type' => $this->matchType($backLog[$this->config['event_field']]),
                    ]
                ],
                'timestamp' => time() * 1000
            ];
            if($moreParam){
                foreach ($moreParam as $k => $v){
                    $param[$k] = $v;
                }
            }
            $resp = (new Curl())->debug()->setHeader("Content-Type", "application/json")->request($this->config['gateway'], "POST", $param);
            $resp = $resp['body'];
            App::database()->model($this->config['table'])->where([$this->config['pk'] => $backLog[$this->config['pk']]])->update([$this->config['status_field'] => 1, $this->config['uptime_field'] => time(), $this->config['result_field'] => $resp]);
            return $resp;
        }
    }

    /**
     * 购买
     * @param string $oaid
     * @param array $otherId
     * @param array $moreParam
     * @return void
     */
    public function purchase(string $oaid, array $otherId = [], array $moreParam = [])
    {
        $where = [$this->config['id_field'][0] => $oaid];
        $where = array_merge($where, $otherId);
        $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->where(['status' => 1])->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
        if($backLog == false){
            $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
            if($backLog){
                $this->active($oaid, $otherId, $moreParam);
            }
        }
        if($backLog){
            $param = [
                'event_type' => $this->config['events']['purchase'] ?? 'active_pay',
                'context' => [
                    'ad' => [
                        'callback' => $backLog[$this->config['callback_field']],
                        'match_type' => $this->matchType($backLog[$this->config['event_field']]),
                    ]
                ],
                'timestamp' => time() * 1000
            ];
            if($moreParam){
                foreach ($moreParam as $k => $v){
                    $param[$k] = $v;
                }
            }
            $resp = (new Curl())->debug()->setHeader("Content-Type", "application/json")->request($this->config['gateway'], "POST", $param)['body'];

            App::database()->model($this->config['table'])->where([$this->config['pk'] => $backLog[$this->config['pk']]])->update([$this->config['status_field'] => 1, $this->config['result_field'] => $resp, $this->config['uptime_field'] => time()]);
            return $resp;
        }
    }

    /**
     * 广告变现ROI上报
     * @param string $oaid
     * @param array $otherId
     * @param array $moreParam
     * @return void
     */
    public function ltRoi(string $oaid, array $otherId = [], array $moreParam = [])
    {
        $where = [$this->config['id_field'][0] => $oaid];
        $where = array_merge($where, $otherId);
        $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->where(['status' => 1])->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
        if($backLog == false){
            $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
            if($backLog){
                $this->active($oaid, $otherId, $moreParam);
            }
        }
        if($backLog){
            $param = [
                'event_type' => $this->config['events']['ltroi'] ?? 'lt_roi',
                'context' => [
                    'ad' => [
                        'callback' => $backLog[$this->config['callback_field']],
                        'match_type' => $this->matchType($backLog[$this->config['event_field']]),
                    ]
                ],
                'timestamp' => time() * 1000
            ];
            if($moreParam){
                foreach ($moreParam as $k => $v){
                    $param[$k] = $v;
                }
            }
            $resp = (new Curl())->debug()->setHeader("Content-Type", "application/json")->request($this->config['gateway'], "POST", $param)['body'];

            App::database()->model($this->config['table'])->where([$this->config['pk'] => $backLog[$this->config['pk']]])->update([$this->config['status_field'] => 1, $this->config['result_field'] => $resp, $this->config['uptime_field'] => time()]);
            return $resp;
        }
    }

    /**
     * 7日留存
     * @param string $oaid
     * @param array $otherId
     * @param array $moreParam
     * @return void
     */
    public function retention7d(string $oaid, array $otherId = [], array $moreParam = [])
    {
        $where = [$this->config['id_field'][0] => $oaid];
        $where = array_merge($where, $otherId);
        $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->where(['status' => 1])->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
        if($backLog == false){
            $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
            if($backLog){
                $this->active($oaid, $otherId, $moreParam);
            }
        }
        if($backLog){
            $param = [
                'event_type' => $this->config['events']['retention7d'] ?? 'retention_7d',
                'context' => [
                    'ad' => [
                        'callback' => $backLog[$this->config['callback_field']],
                        'match_type' => $this->matchType($backLog[$this->config['event_field']]),
                    ]
                ],
                'timestamp' => time() * 1000
            ];
            if($moreParam){
                foreach ($moreParam as $k => $v){
                    $param[$k] = $v;
                }
            }
            $resp = (new Curl())->debug()->setHeader("Content-Type", "application/json")->request($this->config['gateway'], "POST", $param)['body'];

            App::database()->model($this->config['table'])->where([$this->config['pk'] => $backLog[$this->config['pk']]])->update([$this->config['status_field'] => 1, $this->config['result_field'] => $resp, $this->config['uptime_field'] => time()]);
            return $resp;
        }
    }

    /**
     * 次留
     * @param string $oaid
     * @param array $otherId
     * @param array $moreParam
     * @return void
     */
    public function nextdayopen(string $oaid, array $otherId = [], array $moreParam = [])
    {
        $where = [$this->config['id_field'][0] => $oaid];
        $where = array_merge($where, $otherId);
        $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->where(['status' => 1])->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
        if($backLog == false){
            $backLog = App::database()->model($this->config['table'])->whereRaw($this->whereRawOr($where))->order($this->config['pk'].' desc')->field($this->config['pk'].','.$this->config['event_field'].','.$this->config['callback_field'])->find();
            if($backLog){
                $this->active($oaid, $otherId, $moreParam);
            }
        }
        if($backLog){
            $param = [
                'event_type' => $this->config['events']['nextdayopen'] ?? 'next_day_open',
                'context' => [
                    'ad' => [
                        'callback' => $backLog[$this->config['callback_field']],
                        'match_type' => $this->matchType($backLog[$this->config['event_field']]),
                    ]
                ],
                'timestamp' => time() * 1000
            ];
            if($moreParam){
                foreach ($moreParam as $k => $v){
                    $param[$k] = $v;
                }
            }
            $resp = (new Curl())->debug()->setHeader("Content-Type", "application/json")->request($this->config['gateway'], "POST", $param)['body'];

            App::database()->model($this->config['table'])->where([$this->config['pk'] => $backLog[$this->config['pk']]])->update([$this->config['status_field'] => 1, $this->config['result_field'] => $resp, $this->config['uptime_field'] => time()]);
            return $resp;
        }
    }
}