<?php
// +----------------------------------------------------------------------
// | zhanshop-admin / Crontab.php    [ 2023/6/8 下午9:55 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

use zhanshop\console\Command;

abstract class Crontab
{
    /**
     * 命令标题
     * @var
     */
    protected $title;

    /**
     * 命令描述
     * @var
     */
    protected $description = '';

    /**
     * 规则
     * @var string
     */
    protected $rule = [];

    public $timerId = 0;

    /**
     * 设置标题
     * @param string $title
     * @return $this
     */
    protected function setTitle(string $title)
    {
        $this->title = $title;

        return $this;
    }
    /**
     * 设置描述
     * @param string $description
     * @return $this
     */
    protected function setDescription(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * 获取定时任务标题
     * @return string
     */
    public function getTitle(){
        return $this->title;
    }

    /**
     * 获取定时任务描述
     * @return string
     */
    public function getDescription(){
        return $this->description;
    }
    /**
     * 获取规则
     * @return array|string
     */
    public function getRule(){
        return $this->rule;
    }

    public function debugInfo(string $msg){
        echo '['.date('Y-m-d H:i:s').' *000]      '.get_called_class().':class定时任务'.$msg.PHP_EOL;
    }

    /**
     * 每月多少号多少时多少点多少分多少秒执行一次
     * @param int $day
     * @param int $hour
     * @param $minute
     * @param $second
     * @return void
     */
    protected function everymonth(int $day = 1, int $hour = 0, $minute = 0, $second = 0){
        $this->rule['type'] = 'everymonth';
        $this->rule['day'] = $day;
        $this->rule['hour'] = $hour;
        $this->rule['minute'] = $minute;
        $this->rule['second'] = $second;
        return $this;
    }

    /**
     * 每周几多少点多少分多少秒执行一次
     * @param int $week
     * @param int $hour
     * @param $minute
     * @param $second
     * @return $this
     */
    protected function everyweek(int $week = 1, int $hour = 0, $minute = 0, $second = 0){
        $this->rule['type'] = 'everyweek';
        $this->rule['week'] = $week;
        $this->rule['hour'] = $hour;
        $this->rule['minute'] = $minute;
        $this->rule['second'] = $second;
        return $this;
    }

    /**
     * 每天多少点多少分多少秒执行一次
     * @param int $hour
     * @param $minute
     * @param $second
     * @return void
     */
    protected function everyday(int $hour, $minute = 0, $second = 0){
        $this->rule['type'] = 'everyday';
        $this->rule['hour'] = $hour;
        $this->rule['minute'] = $minute;
        $this->rule['second'] = $second;
        return $this;
    }
    /**
     * 每天多少点多少分多少秒执行一次然后后面随机半天到1天半的时间范围执行
     * @param int $hour
     * @param $minute
     * @param $second
     * @return void
     */
    protected function everydayRand(int $hour, $minute = 0, $second = 0){
        $this->rule['type'] = 'everydayRand';
        $this->rule['hour'] = $hour;
        $this->rule['minute'] = $minute;
        $this->rule['second'] = $second;
        return $this;
    }

    /**
     * 每隔多少秒执行一次
     * @param $second
     * @return void
     */
    protected function interval($second = 1){
        $this->rule['type'] = 'interval';
        $this->rule['interval'] = $second * 1000;
        return $this;
    }

    /**
     * 配置信息
     * @return mixed
     */
    abstract public function configure();

    /**
     * 执行handle
     * @param mixed $server
     * @return void
     */
    abstract public function execute();

}