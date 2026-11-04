<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / MysqlRecovery.php    [ 2025/9/26 10:57 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console\command\software;

use zhanshop\App;
use zhanshop\console\Command;
use zhanshop\console\Input;
use zhanshop\console\Output;
use zhanshop\util\MaxFile;

class MysqlRecovery extends Command
{
    protected $recoveryPath = 'docker/mysql/recovery/';

    /**
     * PDO 选项键: "连上之后先执行一条语句"(这里用来统一字符集)。
     * 不能直接写 \PDO::MYSQL_ATTR_INIT_COMMAND —— PHP 8.5 把 PDO 的**驱动专属**常量迁到了
     * Pdo\Mysql 并弃用旧名, 旧名在 8.5 上会发 E_DEPRECATED; 而本框架的 Error::errorHandler
     * 是拿 E_ALL 注册并把任何错误(含 deprecated)抛成异常的, 于是"数据恢复"这个命令会在建连那行
     * 直接失败, 且看不出跟 PHP 版本有关。Pdo\Mysql 是 8.4 才有的类, 8.3 及更早只有旧名可用,
     * 所以按存在性二选一(三元是惰性的, 未选中的那支不会被求值, 因此 8.5 上不会碰到旧名)。
     * @return int
     */
    protected static function initCommandOption(): int
    {
        return defined('Pdo\Mysql::ATTR_INIT_COMMAND')
            ? \Pdo\Mysql::ATTR_INIT_COMMAND
            : \PDO::MYSQL_ATTR_INIT_COMMAND;
    }

    public function configure()
    {
        $this->useCache()->setTitle('mysql')->setDescription('数据恢复');
    }

    public function execute(Input $input, Output $output)
    {
        $mysqlbinlog = $input->input('mysqlbinlog', '请输入mysqlbinlog路径', 'mysqlbinlog');
        $ip = $input->input('ip', '请输入mysql服务器的地址');
        $port = $input->input('port', '请输入mysql服务器的端口');
        $user = $input->input('user', '请输入mysql服务器的用户');
        $pwd = $input->input('pwd', '请输入mysql服务器的密码');
        $startDate = $input->input('start-date', '请输入恢复的开始时间如2023-09-09 00:00:00,不传最新7天', date('Y-m-d 00:00:00', strtotime('-1 day')));
        $endDate = $input->input('end-date', '请输入恢复的截止时间如2023-09-12 00:00:00', date('Y-m-d H:i:s'));
        if(strtotime($startDate) == false) App::error()->setError('恢复的开始时间无效');
        if(strtotime($endDate) == false) App::error()->setError('恢复的结束时间无效');

        $pdo = new \PDO('mysql:host='.$ip.';port='.$port, $user, $pwd, [
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            self::initCommandOption() => "SET NAMES 'utf8mb4'"
        ]);

        $expire = $pdo->query("SHOW VARIABLES LIKE 'binlog_expire_logs_seconds';")->fetch();
        if($expire == false){
            $expire = $pdo->query("show variables like 'expire_logs_days';")->fetch();
        }
        if($expire) echo "二进制日志过期时间：".$expire['Value'].PHP_EOL;

        $logs = $pdo->query('show binary logs;')->fetchAll();

        $logNames = [];
        foreach ($logs as $k => $v){
            $logNames[] = $this->recoveryPath.$v['Log_name'];
            echo "正在下载:".$v['Log_name'].';大小:'.($v['File_size'] / 1024 / 1024).'MB'.PHP_EOL;
            // 下载二进制文件
            $command = $mysqlbinlog.' --read-from-remote-server --raw --host='.$ip.' --port='.$port.' --user='.$user.' --password='.$pwd.' '.$v['Log_name'].' --result-file='.$this->recoveryPath;
            //echo $command.PHP_EOL;
            system($command);
        }
        $command = $mysqlbinlog.' --start-datetime="'.$startDate.'"  --stop-datetime="'.$endDate.'" -v --base64-output=DECODE-ROWS '.implode(' ', $logNames).' > '.$this->recoveryPath.'recovery.sql';
        //echo $command.PHP_EOL;
        system($command);
        $this->extract($this->recoveryPath.'recovery.sql');
    }

    public function extract($filePath)
    {
        file_put_contents($this->recoveryPath.'recovery.log', "");
        $lastRow = '';
        $status = -1;
        $table = "";
        $insert = [];
        (new MaxFile())->read($filePath, function ($v) use (&$status, &$table, &$insert, &$lastRow){

            $newStr = preg_replace('/\s+/', '', $v);

            if($status == 0 && (strpos($newStr, '#at') === 0  || strpos($newStr, '###DELETEFROM') === 0)){
                // 写数据
                error_log($table.PHP_EOL.json_encode($insert, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE).PHP_EOL, 3, $this->recoveryPath.'recovery.log');
                $status = -1;
                $insert = [];
            }

            if(strpos($newStr, '###DELETEFROM') === 0){
                $status = 0;
                $lastRows = explode(' ', $lastRow);
                $date = '';
                if(isset($lastRows[0]) && isset($lastRows[2])){
                    $date = ' --- '.(str_replace('#', '', $lastRows[0] ?? '')).' '.($lastRows[2] ?? '');
                }
                $table = rtrim($v, "\n").$date;
            }
            if($status == 0 && strpos($newStr, '###@') === 0){
                $rows = explode('###   @', $v);
                $fieldData = $rows[1] ?? 0;
                if($fieldData != false){
                    $field = explode('=', $fieldData)[0];
                    $count = 1;
                    $rowData = str_replace($field.'=', '', $fieldData, $count);
                    $rowData = (trim(rtrim($rowData, "\n"), '"'));
                    $rowData = trim($rowData, "'");
                    $insert[$field] = $rowData;
                }
            }
            $lastRow = $v;
        });
    }
}