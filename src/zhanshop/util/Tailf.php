<?php

namespace zhanshop\util;

class Tailf
{
    protected $fd;
    protected $watch = [];

    protected $fopen = [];
    protected $lastPos = [];
    protected $isFirst = [];
    protected $content = [];

    public function __construct()
    {
        try {
            $this->fd = inotify_init();
        }catch (\Throwable $e){
            $this->fd = 0;
        }
    }

    /**
     * 获取fopen
     * @param string $filename
     * @return void
     */
    protected function fopen(string $filename)
    {
        try {
            if(isset($this->fopen[$filename]) == false){
                $file = fopen($filename, 'r');
                fseek($file, 0, SEEK_END);
                // 文件上次读取位置
                $lastPos = ftell($file);

                $this->fopen[$filename] = $file;
                $this->lastPos[$filename] = $lastPos;
                $this->isFirst[$filename] = true;
                $this->content[$filename] = '';
                //var_dump("初始化文件句柄,上次打开的文件读取的文件位置:".$lastPos);
            }else{
                $this->isFirst[$filename] = false;
            }

            return $this->fopen[$filename];
        }catch (\Throwable $e){
            return false;
        }
    }

    /**
     * 销毁
     * @param string $filename
     * @return void
     */
    protected function fdelete(string $filename)
    {
        if(isset($this->fopen[$filename])){
            // 这里应该还需要移除文件监听inotify_rm_watch
            $file = $this->fopen[$filename];
            unset($this->fopen[$filename], $this->lastPos[$filename], $this->content[$filename]);
            fclose($file);
        }
    }

    /**
     * 记录上次文件指针读/写的位置
     * @param string $filename
     * @return void
     */
    protected function ftell(string $filename)
    {
        //var_dump("记录上次读取的位置");
        //var_dump($this->fopens);
        $lastPos = ftell($this->fopen[$filename]);
        $this->lastPos[$filename] = $lastPos;
    }

    /**
     * 移动光标到上次读取的位置
     * @param string $filename
     * @return void
     */
    protected function fseek(string $filename)
    {
        $file = $this->fopen[$filename];
        $lastPos = $this->lastPos[$filename];
        fseek($file, $lastPos);
    }

    /**
     * 设置拼装数据
     * @param string $filename
     * @param $content
     * @return void
     */
    protected function setContent(string $filename, $content)
    {
        $this->content[$filename] .= $content;
    }
    /**
     * 获取拼装内容
     * @param string $filename
     * @param $content
     * @return string
     */
    protected function getContent(string $filename, $content)
    {
        $content = $this->content[$filename].$content;
        $this->content[$filename] = '';
        return $content;
    }

    /**
     * 监控路径
     * @param string $path
     * @param int $event
     * @return void
     */
    public function addWatch(string $path, mixed $readCallback, mixed $delCallback, $readLenth = 51200, int $event = 774)
    {
        //if(!is_dir($path)) App::error()->setError($path.'不是一个目录');
        if($this->fd){
            $watchFd = inotify_add_watch($this->fd, $path, $event);
            $this->watch[$watchFd] = [$path, $readCallback, $delCallback, $readLenth];
        }
    }


    /**
     * 读取文件
     * @param $filename
     * @param mixed $callBack
     * @param $event
     * @return void
     */
    private function readFile($filename, $eventMask, $readCallback, $delCallback, $readLenth = 51200)
    {
        if($eventMask & IN_DELETE){
            $this->fdelete($filename);
            $delCallback($filename);
            return;
        }
        $fopen = $this->fopen($filename);
        if($fopen){
            $readNumber = 0;
            while(true) {
                // 读取文件更新内容
                $this->fseek($filename);
                $content = fread($fopen, $readLenth);
                // 判断有更新则输出
                if(!empty($content) && $content !== false) {
                    $this->ftell($filename);
                    $readNumber++;
                    $readCallback($filename, $content); // 这里只做区块化处理
                }else{
                    // 如果读取到的是0次
                    if($readNumber == 0 && $this->isFirst[$filename] == false){
                        $this->fdelete($filename);
                        $delCallback($filename);
                    }
                    break;
                }
            }
        }
    }

    protected function eventLoop()
    {
        \Swoole\Event::add($this->fd, function (){
            \Swoole\Event::del($this->fd);
            $events = inotify_read($this->fd);
            if ($events) {
                foreach ($events as $event) {
                    if(isset($this->watch[$event['wd']])){
                        $watch = $this->watch[$event['wd']];
                        $readCallback = $watch[1];
                        $delCallback = $watch[2];
                        $eventMask = $event['mask'];
                        $path = $watch[0].($event['name'] ? '/'.$event['name'] : '');
                        $this->readFile($path, $eventMask, $readCallback, $delCallback, $watch[3]);
                    }
                }
            }
            $this->eventLoop();
        });
    }

    /**
     * 启动
     * @return void
     */
    public function start()
    {
        if($this->fd) $this->eventLoop();

        while (true) sleep(9999999999);

    }
}