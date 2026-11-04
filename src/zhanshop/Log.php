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
    protected $logType = 'File';
    protected $logBuffer = [];
    protected $buffSize = 500;

    protected $lockTime = 1;

    /**
     * 防止 timer 重入导致 write() 并发执行
     */
    protected bool $isWriting = false;

    public function __construct(){
        $this->buffSize = App::config()->get("log.buffsize", 20000);
        $this->logLevel = App::config()->get("log.level", 4);
        $this->lockTime = floatval(App::env()->get("LOG_LOCK_TIME", "1"));
        if(PHP_OS == 'Darwin') $this->lockTime = 5;
        \Swoole\Timer::tick(3000, function($timerId){
            // 跨天句柄检查：即使 buffer 为空也要释放前一天句柄
            // 否则前一天日志被删除后磁盘空间无法回收
            if (!$this->isWriting) {
                $logType = $this->logType;
                if (method_exists($logType, 'rotateIfNeeded')) {
                    $logType::rotateIfNeeded();
                }
            }
            if($this->logBuffer && !$this->isWriting) $this->write();
        });
        $type = App::config()->get('log.type');
        if(strpos($type, '\\') === false){
            $type = '\\zhanshop\\log\\'.ucfirst($type);
        }
        $this->logType = $type;
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
        if($level < $this->logLevel) return;
        $this->logBuffer[] = '['.date('Y-m-d H:i:s').']###['.$level.'-'.$tag.']###'.$msg;
        if(count($this->logBuffer) >= $this->buffSize){
            $this->write();
        }
    }

    /**
     * 写入DEBUG日志
     * @param $msg
     * @return void
     */
    public function debug($msg)
    {
        $this->push($msg, self::DEBUG, "DEBUG");
    }

    /**
     * 写入信息日志
     * @param $msg
     * @return void
     */
    public function info($msg){
        $this->push($msg, self::INFO, "INFO");
    }

    /**
     * 写入警告日志
     * @param $msg
     * @return void
     */
    public function warning($msg){
        $this->push($msg, self::WARNING, "WARNING");
    }

    /**
     * 写入错误日志
     * @param $msg
     * @return void
     */
    public function error($msg){
        $this->push($msg, self::ERROR, "ERROR");
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
        $body = json_encode($body, JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE + JSON_INVALID_UTF8_IGNORE);
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
        if ($this->isWriting) return;
        if (empty($this->logBuffer)) return;

        $this->isWriting = true;
        try {
            // 原子换出：一次性快照，写盘期间新涌入的日志归入下一批
            // （由 3s 定时器或 buffSize 阈值触发），无需递归/循环排空。
            // 性能优先：换出即视为已处理，落盘失败/异常直接丢弃、不重试不阻塞。
            $batch = $this->logBuffer;
            $this->logBuffer = [];

            $this->flush($batch);
        } finally {
            // 无论成败都复位，防止异常逃逸导致 isWriting 永久卡死、后续日志静默丢失
            $this->isWriting = false;
        }
    }

    /**
     * 批量落盘。极端高并发下允许日志丢失：失败/异常直接丢弃，换取极致吞吐。
     */
    private function flush(array $batch): void
    {
        try {
            App::make($this->logType)->write($batch);
        } catch (\Throwable $e) {
            // 丢弃本批，不重试
        }
    }
}
