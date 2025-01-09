<?php
// +----------------------------------------------------------------------
// | zhanshop / ScanPorts.php    [ 2023/8/3 上午9:05 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console\command\software;

use zhanshop\App;
use zhanshop\console\Command;
use zhanshop\console\Input;
use zhanshop\console\Output;
use Swoole\Coroutine;
use function Swoole\Coroutine\run;

class ScanPorts extends Command
{
    protected $portDescription  = [
        20 => 'FTP服务',
        21 => 'FTP服务',
        22 => 'SSH服务',
        23 => 'Telnet远程终端服务',
        25 => 'SMTP简单邮件传输协议',
        53 => 'DNS协议',
        67 => 'DHCP服务',
        68 => 'DHCP服务',
        69 => 'TFTP简单文件传输协议',
        80 => 'HTTP超文本传输协议',
        443 => 'HTTP超文本传输协议',
        110 => 'POP3 邮局协议',
        123 => 'NTP网络时间协议',
        161 => 'SNMP简单网络管理协议',
        389 => 'LDAP轻型目录访问协议',
        636 => 'LDAP轻型目录访问协议',
        873 => 'rsync服务',
        3306 => 'MySQL服务',
        6379 => 'Redis服务',
        1521 => 'Oracle数据库',
        1433 => 'Sql Server数据库',
        5000 => 'DB2数据库',
        5432 => 'PostgreSQL数据库',
        5236 => 'DM达梦数据库',
        11211 => 'Memcached数据库',
        27017 => 'MongoDB数据库',
        3389 => 'windows远程桌面的服务'
    ];
    // 超时时间默认0.3秒
    protected $timeout = [
        "sec" => 0,
        "usec"=> 300000
    ];

    public function configure()
    {
        $this->setTitle('端口扫描')->setDescription('对指定IP进行端口扫描');
    }

    protected Output $output;
    public function execute(Input $input, Output $output)
    {
        $timeout = $input->param('timeout', '0.3'); //传入参数 -- timeout 1.0
        $timeouts = explode('.', $timeout);
        $this->timeout['sec'] = $timeouts[0];
        $this->timeout['usec'] = (int)substr((string)($timeouts[1] * 100000), 0, 6);
        $this->output = $output;
        $input->input('ip', '请输入需要扫描的IP地址');
        $input->input('ports', '请输入需要扫描的端口列表多个使用,分割');

        $ip = $input->param('ip');
        $ports = $input->param('ports');
        if($ports == -1){
            // 扫描所有
            $this->scanAll($ip);
        }else{
            $this->ports($ip, array_unique(explode(',', $ports)));
        }
    }

    /**
     * 扫描已知端口
     * @param $ip
     * @return void
     */
    public function scanAll(string $ip){
        foreach ($this->portDescription as $port => $title){
            $this->scan($ip, $port);
        }
    }

    public function ports(string $ip, array $ports){
        foreach($ports as $v){
            if(is_numeric($v)){
                $this->scan($ip, (int)$v);
            }
        }
    }

    public function scan(string $ip, int $port){
        $status = self::checkTcp($ip, $port);
        $description = $this->portDescription[$port] ?? '未知端口用途';
        $length = 8 - mb_strlen((string)$port);
        $port = str_repeat(' ', $length).$port;
        if($status == 1){
            $this->output->output($port.' 检测端口为开启状态  '.$description, 'success');
        }else if($status == 2){
            $this->output->output($port.' 检测端口为关闭状态  '.$description, 'error');
        }else{
            $this->output->output($port.' 检测端口为超时状态  '.$description, 'info');
        }
    }

    /**
     * 检查tcp端口是否开放
     * @param string $ip
     * @param int $port
     * @return false|int
     */
    public static function checkTcp(string $ip, int $port){
        try {
            $domain = AF_INET;
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $domain = AF_INET6;
            }
            $sock = socket_create($domain, SOCK_STREAM, SOL_TCP);

            socket_set_option($sock,SOL_SOCKET,SO_RCVTIMEO, array("sec" => 0, "usec"=> 300000) );
            socket_set_option($sock,SOL_SOCKET,SO_SNDTIMEO, array("sec" => 0, "usec"=> 300000) );

            socket_set_nonblock($sock);
            socket_connect($sock,$ip, $port);
            socket_set_block($sock);
            $r = array($sock);
            $w = array($sock);
            $f = array($sock);
            $status = socket_select($r, $w, $f, 1);
            return($status);
        }catch (\Throwable $e){
            return 0;
        }
    }
}
