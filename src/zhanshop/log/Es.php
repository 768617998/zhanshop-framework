<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / Elasticsearch.php    [ 2025/6/3 15:43 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\log;

use zhanshop\App;
use zhanshop\Elasticsearch;
use zhanshop\Helper;

class Es
{
    public function write(array $data)
    {
        $time = time();
        $insertAll = [];
        foreach ($data as $v){
            $insertAll[] = ['id' => Helper::orderId(), 'body' => $v,  'create_time' => $time];
        }
        App::make(Elasticsearch::class)->indexName("zhanshop-logs")->insertAll($insertAll);
    }
}