<?php
// +----------------------------------------------------------------------
// | zhanshop-php / Mysql.php    [ 2023/1/31 18:28 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\database\drive;

use Swoole\Database\PDOConfig;
use zhanshop\Helper;

class Mysql
{
    protected $config = [];

    protected $prepareXa = 0;

    public function __construct(array $config){
        $this->config = $config;
    }

    public function pdoConfig()
    {
        $options = [
            \PDO::ATTR_TIMEOUT => 3,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        foreach($options as $k => $v){
            if(isset($this->config['params'][$k]) == false) $this->config['params'][$k] = $v;
        }

        $hostname = Helper::gethostbyname($this->config['hostname']);
        if (filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hostname = '['.$hostname.']';
        }

        $pdoConfig = (new PDOConfig)
            ->withHost($hostname)
            ->withPort((int)$this->config['hostport'])
            ->withDbName($this->config['database'])
            ->withCharset($this->config['charset'])
            ->withUsername($this->config['username'])
            ->withPassword($this->config['password'])
            ->withOptions($this->config['params']);
        return $pdoConfig;
    }

    /**
     * 启动XA事务
     * @access public
     * @param  string $xid XA事务id
     * @return void
     */
    public function startTransXa(mixed $pdo, string $xid): void
    {
        $pdo->exec("XA START '$xid'");
    }

    /**
     * 预编译XA事务
     * @access public
     * @param  string $xid XA事务id
     * @return void
     */
    public function prepareXa(mixed $pdo, string $xid): void
    {
        $pdo->exec("XA END '$xid'");
        $pdo->exec("XA PREPARE '$xid'");
        $this->prepareXa = 1;
    }

    /**
     * 提交XA事务
     * @access public
     * @param  string $xid XA事务id
     * @return void
     */
    public function commitXa(mixed $pdo, string $xid): void
    {
        $pdo->exec("XA COMMIT '$xid'");
    }

    /**
     * 回滚XA事务
     * @access public
     * @param  string $xid XA事务id
     * @return void
     */
    public function rollbackXa(mixed $pdo, string $xid): void
    {
        // xa start之后必须xa end,否则不能执行xa commit和xa rollback 所以如果在执行xa事务过程中有语句出错了,你也需要先xa end一下,然后才能xa rollback
        $this->prepareXa($pdo, $xid);
        $pdo->exec("XA ROLLBACK '$xid'");
    }
}