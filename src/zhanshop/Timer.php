<?php
// +----------------------------------------------------------------------
// | zhanshop_admin / CronTab.php [ 2023/4/19 下午8:57 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

use Swoole\Timer as STimer;

class Timer
{
    /**
     * 启动瞬间记录当前时间戳
     * @var int
     */
    protected $microtime = 0;

    /**
     * 效验时间当系统时间发生修改后重启定时器
     * @var int
     */
    protected $correctionInterval = 60; // 每60秒校验一次

    /**
     * 当前运行的定时任务
     * @var array
     */
    protected $crontabs = [];

    public function __construct()
    {
        App::config()->loadIni();

        $this->microtime = microtime(true);
        // 添加一个效验时间的定时器
        STimer::tick($this->correctionInterval * 1000, [$this, 'correction']);
    }

    /**
     * 获取所有定时任务
     * @return array|mixed
     */
    public function crontabs(){
        return $this->crontabs;
    }

    /**
     * 查看定时器状态
     * @return void
     */
    public function stats(){
        STimer::stats();
    }

    /**
     * 获取指定定时器ID的信息
     * @param int $timerId
     * @return array|null
     */
    public function info(int $timerId){
        return STimer::info($timerId);
    }

    /**
     * 重载所有定时任务
     * @return void
     */
    protected function reload(){
        foreach($this->crontabs as $k => $v){
            STimer::clear($v->timerId); // 清理现在所有正在运行的定时器
            $type = $v->getRule()['type'];
            $this->$type($v); // 再次启动
        }
    }

    /**
     * 时间校验
     * @return void
     */
    protected function correction(){
        $currentMicrotime = microtime(true);
        $microtime = $this->microtime += $this->correctionInterval; // 这种方式如果不进行修正的相差会越来越大
        $differ = $currentMicrotime - $microtime;
        $this->microtime = $currentMicrotime;
        // 偏差超过1秒
        if(floor(abs($differ)) > 0){
            // 停止掉所有定时器 重新添加
            $this->reload();
        }
    }

    /**
     * 启动一个每隔一月执行一次
     * @param mixed $crontab
     * @return void
     */
    protected function everymonth(mixed &$crontab){
        $rule = $crontab->getRule();
        $currentTime = time();
        $ruleTime = strtotime(date('Y-m-'.$rule['day'].' '.$rule['hour'].':'.$rule['minute'].':'.$rule['second']));
        if($ruleTime < $currentTime + 3){
            $ruleTime = strtotime('+1 month', $ruleTime);
        }
        $ms = ($ruleTime - $currentTime) * 1000;
        $crontab->timerId = STimer::after($ms, function () use (&$crontab){
            $crontab->execute();
            $this->everymonth($crontab);
        });
    }

    /**
     * 启动一个每周执行的任务
     * @param Crontab $crontab
     * @return void
     */
    protected function everyweek(mixed &$crontab){
        $rule = $crontab->getRule();
        $week = $rule['week'] - 1;

        $currentTime = time();
        $ruleTime = strtotime(date('Y-m-d '.$rule['hour'].':'.$rule['minute'].':'.$rule['second'], strtotime('+'.$week.' day', strtotime(date('Y-m-d').'-1'." week Monday"))));

        if($ruleTime < $currentTime + 3){
            // 下周执行的时间
            $ruleTime = strtotime('+7 day', $ruleTime);
        }
        $ms = ($ruleTime - $currentTime) * 1000;
        // 设置第一次执行的时间
        STimer::after($ms, function () use (&$crontab){
            STimer::tick(86400 * 7 * 1000, [$crontab, 'execute']); // 首次执行后添加下次执行的时间也就1周后的现在
            $crontab->execute(); // 第一次执行
        });
    }

    /**
     * 启动一个每天执行的任务
     * @param Crontab $crontab
     * @return void
     */
    protected function everyday(mixed &$crontab){
        $rule = $crontab->getRule();

        $currentTime = time();
        $ruleTime = strtotime(date('Y-m-d '.$rule['hour'].':'.$rule['minute'].':'.$rule['second']));
        // 当前时间 0：0：0 执行是时间为0：0：5
        // 如果时间已经过了今天就不会再执行了
        if($ruleTime < $currentTime + 3){
            // 明天执行的时间
            $ruleTime = strtotime('+1 day', $ruleTime);
        }
        $ms = ($ruleTime - $currentTime) * 1000;
        // 设置第一次执行的时间
        $crontab->timerId = STimer::after($ms, function () use (&$crontab){
            $crontab->timerId = STimer::tick(86400 * 1000, [$crontab, 'execute']); // 首次执行后添加下次执行的时间也就1天后的现在
            $crontab->execute(); // 第一次执行
        });
    }

    /**
     * 启动一个间歇定时任务
     * @param Crontab $crontab
     * @return void
     */
    protected function interval(mixed &$crontab){
        $timerId = STimer::tick($crontab->getRule()['interval'], [$crontab, 'execute']);
        $crontab->timerId = $timerId; // 关联定时id
    }


    /**
     * 注册一个定时任务类
     * @param Crontab $crontab
     * @return void
     */
    public function register(mixed $crontab){
        $crontab->configure();
        $this->crontabs[$crontab->getTitle()] = $crontab;
        // 并启动
        $type = $crontab->getRule()['type'];
        $this->$type($crontab);
    }
}