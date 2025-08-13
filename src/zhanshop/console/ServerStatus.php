<?php
// +----------------------------------------------------------------------
// | zhanshop_admin / ServerStatus.php [ 2023/4/22 上午10:54 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console;

use zhanshop\App;
use zhanshop\client\Httpclient;

class ServerStatus
{
    protected static $config = [
        'start_time' => '服务启动时间',
        'connection_num' => '当前连接数量',
        'abort_count' => '拒绝连接数量',
        'accept_count' => '接受连接数量',
        'close_count' => '关闭连接数量',
        'worker_num' => '工作进程数量',
        'task_worker_num' => '任务进程数量',
        'idle_worker_num' => '空闲进程数量',
        'tasking_num' => '工作任务进程',
        'request_count' => '收到请求次数',
        'response_count' => '响应返回次数',
        'total_recv_bytes' => '数据接收总数',
        'total_send_bytes' => '数据发送总数',
        'min_fd' => '最小的连接符',
        'max_fd' => '最大的连接符',
        'coroutine_num' => '当前协程数量',
        'coroutine_peek_num' => '全部协程数量',
        'task_idle_worker_num' => '空闲任务进程',
        'task_queue_num' => '消息队列任务',
        'task_queue_bytes' => '消息队列占用',
        'worker_dispatch_count' => '主程投递次数',
    ];
    /**
     * 获取服务器状态信息
     * @param string $url
     * @return array
     */
    public static function info(string $url){
        if(strpos($url, 'http') === 0 ||  strpos($url, 'ws') === 0){
            $url = "http://".explode("://", $url)[1];
            return self::http($url);
        }else{
            return self::client($url);
        }
    }

    /**
     * tcp和udp的请求
     * @param $url
     * @param $data
     * @return void
     */
    public static function client($url){
        $statusData = [];
        $fp = stream_socket_client($url, $errno, $errstr, 30);
        if (!$fp) {
            echo "$errstr ($errno)\n";
        } else {
            $requestData = [
                'server' => [
                    'request_uri' => '/_status',
                ],
                'post' => [
                    'app_key' => App::config()->get('app.app_key')
                ],
            ];
            fwrite($fp, json_encode($requestData)."\r\n");
            while (!feof($fp)) {
                $data = fgets($fp, 4096);
                if($data){
                    $data = json_decode($data, true);
                    if($data) $statusData = $data;
                }
            }
            fclose($fp);
        }
        return self::translate($statusData);
    }

    public static function http($url){
        $httpclient = new Httpclient();
        $httpclient->setHeader("Content-Type", "application/json; charset=utf-8");
        $response = $httpclient->request($url.'/_status?app_key='.App::config()->get('app.app_key'));
        $body = json_decode($response['body'], true);
        return self::translate($body);
    }

    protected static function translate(array $data){
        $ret = [];
        $data['start_time'] = date('Y-m-d H:i:s', $data['start_time']).'    累计运行时间：'.self::getTimeStr(time() - $data['start_time']).PHP_EOL;
        foreach (self::$config as $k => $v){
            $ret[$v] = $data[$k] ?? '-';
        }
        return $ret;
    }

    /**
     * 获取时间
     * @param int $time
     * @return string
     */
    public static function getTimeStr(int $time){
        $d = floor($time / (3600*24));
        $h = floor(($time % (3600*24)) / 3600);
        $m = floor((($time % (3600*24)) % 3600) / 60);

        $s = floor((($time % (3600*24)) % 3600) % 60);
        if($d > 0){
            return $d.'天'.$h.'小时'.$m.'分'.$s.'秒';
        }else{
            if($h != 0){
                return $h.'小时'.$m.'分'.$s.'秒';
            }elseif($m != 0){
                return $m.'分'.$s.'秒';
            }else{
                return $time.'秒';
            }
        }
    }
}