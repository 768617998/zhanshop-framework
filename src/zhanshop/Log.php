<?php
// +----------------------------------------------------------------------
// | zhanshop-php / LogPool.php [ 2023/1/31 下午9:38 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

use zhanshop\console\Output;

class Log
{
    const NONE = -1;
    const DEBUG = 0;
    const TRACE = 1;
    const INFO = 2;
    const NOTICE = 3;
    const SQL = 4;
    const WARNING = 5;
    const ERROR = 6;

    protected $logLevel = 4;
    protected $logBuffer = [];
    protected $buffSize = 500;

    protected $lockTime = 1;

    public function __construct(){
        $this->buffSize = App::config()->get("log.buffsize", 20000);
        $this->logLevel = App::config()->get("log.level", 4);
        $this->lockTime = floatval(App::env()->get("LOG_LOCK_TIME", "1"));
        if(PHP_OS == 'Darwin') $this->lockTime = 5;
        \Swoole\Timer::tick(3000, function(){
            if($this->logBuffer) $this->write();
        });
    }

    /**
     * 日志进程
     * @param $process
     * @return void
     */
    public static function setProcess($process)
    {
        self::$process = $process;
    }

    /**
     * 写入日志
     * @param string $msg
     * @param $level
     * @return void
     */
    public function push($msg, $level = 2, $tag = ''){
        //$this->channel->push('['.date('Y-m-d H:i:s').']###[level'.$level.']###'.$msg, 0.01);
        //App::make(TaskManager::class)->pushLog($msg);
        if($level < $this->logLevel) return;
        $this->logBuffer[] = '['.date('Y-m-d H:i:s').']###['.$level.'-'.$tag.']###'.$msg;
        if(count($this->logBuffer) >= $this->buffSize){
            $this->write();
        }
    }

    /**
     * 打印错误日志
     * @param int $level
     * @param string $msg
     * @return void
     */
    public static function errorLog(int $level, string $msg) :void{
        // 直接输出日志
        $msg = '['.date('Y-m-d H:i:s *v').']	'.getmypid().' LEVEL '.$level.'	'.$msg;
        $stype = 'success';
        if ($level >= 5){
            $stype = 'error';
        }else if($level >= 4){
            $stype = 'info';
        }else if($level >= 2){
            $stype = 'debug';
        }
        App::make(Output::class)->output($msg, $stype);
    }

    /**
     * 输出日志
     * @param string $msg
     * @return void
     */
    public static function echo(string $msg)
    {
        echo $msg."\r\n";
    }

    /**
     * 脚本日志
     * @param string $event
     * @param mixed $msg
     * @return void
     */
    public static function scriptLog(string $event, mixed $msg = "")
    {
        $body = [
            'event' => $event,
            'body' => $msg
        ];
        $body = json_encode($body, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
        echo $body."\r\n";
    }

    /**
     * 设置日志锁超时时间
     * @param float $timeout
     * @return void
     */
    public function setLockTime(float $timeout)
    {
        $this->lockTime = $timeout;
    }

    public function write()
    {
        $logBuffer = implode(PHP_EOL, $this->logBuffer);
        $this->logBuffer = [];
        $lock = Mutex::lock($this->lockTime);
        if($lock){
            error_log($logBuffer.PHP_EOL, 3, App::runtimePath().'/log/'.date('Ymd').'.log');
            Mutex::unlock();
        }else{
            Log::errorLog(SWOOLE_LOG_WARNING, "日志写入锁获取失败");
        }
    }
}