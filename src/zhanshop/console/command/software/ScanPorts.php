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
use zhanshop\Helper;
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
        1080 => 'SOCKS5',
        3306 => 'MySQL服务',
        6379 => 'Redis服务',
        1521 => 'Oracle数据库',
        1433 => 'Sql Server数据库',
        4444 => 'WebDriver',
        5000 => 'DB2数据库',
        5432 => 'PostgreSQL数据库',
        5236 => 'DM达梦数据库',
        9200 => 'elasticsearch',
        11211 => 'Memcached数据库',
        27017 => 'MongoDB数据库',
        3389 => 'windows远程桌面的服务'
    ];

    public function configure()
    {
        $this->useCache()->setTitle('端口扫描')->setDescription('对指定IP进行端口扫描');
    }

    protected Output $output;
    public function execute(Input $input, Output $output)
    {
        $this->output = $output;
        $host = $input->input('host', '请输入需要扫描的域名或IP地址');
        $input->input('ports', '请输入需要扫描的端口列表多个使用,分割');
        $ports = $input->param('ports');
        $host = Helper::gethostbyname($host);
        $scanPorts = array_keys($this->portDescription);
        if($ports != -1){
            $ports = array_unique(explode(',', $ports));
        }
        foreach ($scanPorts as $port){
            $this->scan($host, intval($port));
        }

    }

    /**
     * 扫描TCP端口
     * @param string $ip
     * @param int $port
     * @return void
     */
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
            $socket = new Coroutine\Socket($domain, SOCK_STREAM, 0);
            if($socket->connect($ip, $port, 1.5)){
                return 1;
            }
        }catch (\Throwable $e){
            return 0;
        }
    }
}
