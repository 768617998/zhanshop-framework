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
        error_log($data.PHP_EOL, 3, App::runtimePath().'/log/'.date('Ymd').'.log');
    }
}