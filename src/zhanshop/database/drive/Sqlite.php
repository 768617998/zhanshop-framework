<?php
// +----------------------------------------------------------------------
// | zhanshop-box  / Sqlite.php    [ 2025/3/24 09:43 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\database\drive;

use Swoole\Database\PDOConfig;
use zhanshop\Helper;

class Sqlite
{
    protected $config = [];

    public function __construct(array $config){
        $this->config = $config;
    }

    public function pdoConfig()
    {

        $pdoConfig = (new PDOConfig)->withDriver("sqlite")->withDbName($this->config['database']);
        return $pdoConfig;
    }
}