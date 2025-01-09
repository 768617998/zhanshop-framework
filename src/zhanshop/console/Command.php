<?php
// +----------------------------------------------------------------------
// | zhanshop-swoole / Command.php    [ 2021/12/30 5:06 下午 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2021 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console;

use Swoole\Coroutine;
use zhanshop\App;
use zhanshop\cache\CacheManager;
use zhanshop\database\DbManager;

abstract class Command
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
    protected $description;

    /**
     * 是否以协程方式运行
     * @var bool
     */
    protected $isCoroutine = false;

    /**
     * 配置信息
     * @return mixed
     */
    abstract public function configure();
    /**
     * 执行业务
     * @param Input $input
     * @param Output $output
     * @return mixed
     */
    abstract public function execute(Input $input, Output $output);

    /**
     * 设置标题
     * @param string $title
     * @return Command
     */
    protected function setTitle(string $title = '')
    {
        $this->title = $title;

        return $this;
    }
    /**
     * 设置描述
     * @param string $description
     * @return Command
     */
    protected function setDescription(string $description = '')
    {
        $this->description = $description;

        return $this;
    }

    /**
     * 是否以协程方式运行
     * @param bool $isCoroutine
     * @return void
     */
    protected function setIsCoroutine(bool $isCoroutine){
        $this->isCoroutine = $isCoroutine;
        return $this;
    }


    /**
     * 是否有使用到数据库
     * @param bool $use
     * @return $this
     */
    protected function useDatabase(){
        $this->isCoroutine = true;
        App::make(DbManager::class);
        return $this;
    }

    /**
     * 是否有使用到缓存
     * @param bool $use
     * @return $this
     */
    protected function useCache(){
        $this->isCoroutine = true;
        App::make(CacheManager::class);
        return $this;
    }

    /**
     * 获取当前命令程序是否以协程方式运行
     * @return bool
     */
    public function getIsCoroutine(){
        return $this->isCoroutine;
    }

    /**
     * 获取标题
     */
    public function getTitle(){
        return $this->title ?? '';
    }

    /**
     * 获取描述
     * @return mixed
     */
    public function getDescription(){
        return $this->description ?? '';
    }

    /**
     * 初始化
     * @return void
     */
    public function initialize(){

    }
}