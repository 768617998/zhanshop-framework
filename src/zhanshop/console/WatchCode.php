<?php
// +----------------------------------------------------------------------
// | zhanshop-php / WatchCode.php    [ 2023/2/2 09:32 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\console;

use zhanshop\App;

class WatchCode
{
    protected $watchPath;
    /**
     * 排除目录
     * @var string[]
     */
    public $excludeDir = ['vendor', 'runtime', 'public', 'test'];

    protected $hashes = [];

    /**
     * 监视的文件后缀
     * @var string
     */
    public $watchExt = 'php';

    /**
     * 初始化【像这种可能比较耗时的任务丢到Task, 还有日志的循环和写入也丢进去】
     * @param string $watchPath
     * @return int
     */
    public function init(string $watchPath = '.'){
        if($this->watchPath == false) $this->watchPath = $watchPath;
        $this->watchPath = $watchPath;
        $files = $this->getFiles($this->watchPath);
        $this->hashes = array_combine($files, array_map([$this, 'fileHash'], $files));
        $count = count($this->hashes);
        return $count;

    }

    /**
     * 是否变化
     * @return bool
     */
    public function isChange() :bool{
        $files = $this->getFiles($this->watchPath);
        if($files == false) return false;
        $newHashes = array_combine($files, array_map([$this, 'fileHash'], $files));

        foreach($newHashes as $k => $v){
            if(isset($this->hashes[$k]) == false){
                //echo '文件新增:'.$k.PHP_EOL;
                // 有新增文件
                return true;
            }elseif (isset($this->hashes[$k]) && $this->hashes[$k] != $v){
                //echo '文件发生变化:'.$k.PHP_EOL;
                // 文件发生变化
                return true;
            }
        }
        foreach($this->hashes as $k => $v){
            // 如果老的文件不在新的中被删除了
            if(isset($newHashes[$k]) == false){
                return true;
            }
        }
        // 如果新增 或者 变化
        return false;
    }

    /**
     * 获取所有文件
     * @param string $path
     * @return array
     */
    protected function getFiles(string $path){
        try {
            $directory = new \RecursiveDirectoryIterator($path);
            $filter = new WatchCodeFilter($directory);
            $iterator = new \RecursiveIteratorIterator($filter);
            return array_map(function ($fileInfo) {
                return $fileInfo->getPathname();
            }, iterator_to_array($iterator));
        }catch (\Throwable $e){}
        return [];
    }

    /**
     * 获取文件hash值
     * @param string $pathname
     * @return string
     */
    protected function fileHash(string $pathname): string
    {
        $contents = filemtime($pathname); // 拿文件最后修改时间
        if (false === $contents) {
            return 'deleted';
        }
        return md5((string)$contents);
    }


}

class WatchCodeFilter extends \RecursiveFilterIterator
{
    public function accept() :bool
    {
        if ($this->current()->isDir()) {
            if (preg_match('/^\./', $this->current()->getFilename())) {
                return false;
            }
            return !in_array($this->current()->getFilename(), App::make(WatchCode::class)->excludeDir);
        }
        $list = array_map(function (string $item): string {
            return "\.$item";
        }, explode(',', App::make(WatchCode::class)->watchExt.','.($_SERVER['APP_ENV'] ?? 'dev')));
        $list = implode('|', $list);
        $int = preg_match("/($list)$/", $this->current()->getFilename());
        return boolval($int);
    }
}
