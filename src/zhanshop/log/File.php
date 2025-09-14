<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / File.php    [ 2025/6/3 15:35 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\log;

use zhanshop\App;

class File
{
    public function write(array $data)
    {
        $data = implode(PHP_EOL, $data);
        $fp = fopen(App::runtimePath().'/log/'.date('Ymd').'.log', 'a');
        if ($fp === false) {
            return false;
        }
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $data.PHP_EOL);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return true;
    }
}