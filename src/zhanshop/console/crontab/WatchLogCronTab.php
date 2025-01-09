<?php
// +----------------------------------------------------------------------
// | zhanshop_admin / WatchLogCronTab.php [ 2023/4/19 下午9:52 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console\crontab;

use zhanshop\App;
use zhanshop\Crontab;

class WatchLogCronTab extends Crontab
{
    protected $maxLogFiles = 30;

    public function configure()
    {
        $this->setTitle("日志检查和清理")->everyday(3);
    }

    /**
     * 检查server日志文件个数是否超过上限如果上限删除部分文件
     * @return void
     */
    protected function serverLog(){
        $servLogs = glob(App::runtimePath().DIRECTORY_SEPARATOR.'server'.DIRECTORY_SEPARATOR. 'server*');
        $count = count($servLogs);
        if ($count > $this->maxLogFiles) {
            foreach($servLogs as $v){
                @unlink($v);
                swoole_error_log(SWOOLE_LOG_NOTICE, '清理server日志：'.$v);
                $count--;
                if($count <= $this->maxLogFiles) break;
            }
        }
    }

    /**
     * 检查框架日志文件个数是否超过上限如果上限删除部分文件
     * @return void
     */
    protected function frameworkLog(){
        $frameworkLogs = glob(App::runtimePath().DIRECTORY_SEPARATOR.'log'.DIRECTORY_SEPARATOR. '*.log');
        $count = count($frameworkLogs);
        if ($count > $this->maxLogFiles) {
            foreach($frameworkLogs as $v){
                @unlink($v);
                swoole_error_log(SWOOLE_LOG_NOTICE, '清理framework日志：'.$v);
                $count--;
                if($count <= $this->maxLogFiles) break;
            }
        }
    }


    public function execute()
    {
        $this->maxLogFiles = App::config()->get('log.max_files', 30);
        $this->serverLog();
        $this->frameworkLog();
    }
}