<?php
// +----------------------------------------------------------------------
// | flow-course / Database.php    [ 2021/10/25 9:35 上午 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2021 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);


namespace zhanshop;

use zhanshop\database\DbManager;
use zhanshop\database\Query;

/**
 * @mixin Query
 */
class Database{

    private array $querys = []; // 连接标识就应该有多少个Query
    //protected Query $query;
    protected $models = [];
    public function getQuery(string $connection){
        $type = App::config()->get('database.connections')[$connection]['type'] ?? 'mysql';
        $query = new Query($connection, $type);
        return $query;
    }

    /**
     * 获取model
     * @param mixed $name
     * @return Query
     */
    public function model(string $name){
        $parseStrModel = Helper::parseStrModel($name);
        $model = $this->getModel($parseStrModel['class']);
        if(!$model){
            $model = $this->table($name);
        }
        return $model;
    }
    /**
     * 容器获取注册app类
     * @param string $name
     * @param mixed $value
     */
    private function getModel(mixed $model){
        try {
            $obj = new $model();
            return $obj;
        }catch (\Throwable $e){
            return false;
        }
    }

    public function table(string $table, string $connection = ""){
        if($connection == false) $connection = App::config()->get('database.default');
        $query = $this->getQuery($connection);
        $query->table($table);
        return $query;
    }

    public function query(string $sql, array $bind = [], string $connection = "", mixed $pdo = null){
        if($connection == false) $connection = App::config()->get('database.default');
        $query = $this->getQuery($connection);
        return $query->query($sql, $bind, $pdo);
    }

    public function execute(string $sql, array $bind = [], string $connection = "", bool $lastID = false, mixed $pdo = null){
        if($connection == false) $connection = App::config()->get('database.default');
        $query = $this->getQuery($connection);
        return $query->execute($sql, $bind, $lastID, $pdo);
    }

    public function transaction($callback, string $connection = ""){
        if($connection == false) $connection = App::config()->get('database.default');
        $query = $this->getQuery($connection);
        return $query->transaction($callback); // 其他的执行也要走事务的query
    }

    /**
     * 执行分布事务
     * @param $callback
     * @param array $connections
     * @return void
     * @throws \Exception
     */
    public function transactionXa(mixed $callback, array $connections, bool $isRetry = true){
        // SHOW VARIABLES LIKE 'event_scheduler'; 分布事务 先要在mysql服务器上开启
        /**
         * mysql> XA START 'xatest';

        Query OK, 0 rows affected (0.00 sec)

        mysql> INSERT INTO test (name,tel) VALUES ('123','123');

        Query OK, 1 row affected (0.00 sec)

        mysql> XA END 'xatest';

        Query OK, 0 rows affected (0.00 sec)

        mysql> XA PREPARE 'xatest';

        Query OK, 0 rows affected (0.00 sec)

        mysql>

        mysql>

        mysql> XA COMMIT 'xatest';
         */
        $pdos = [];
        $xid = uniqid('xa').rand(0, 99999); // 如果报事务id已存在先确认是否事务在一个数据库中，如果是就会报错，如果是多个不同的服务器连接就没有问题
        try {
            foreach($connections as $k => $v){
                $pdoPoll = DbManager::get($v);
                $pdo = $pdoPoll->getPDO();
                $pdos[] = [
                    'poll' => $pdoPoll,
                    'pdo' => $pdo
                ];
                $pdoPoll->drive()->startTransXa($pdo, $xid); // 开启分布式事务
            }
        }catch (\Throwable $e){
            //  当出现连接池不够用可能会报错导致连接无法回收,在这里进行处理和回收
            foreach($pdos as $v){
                $v['poll']->recoveryPDO($v['pdo']);
            }
            App::error()->setError($e->getMessage().' '.$e->getFile().':'.$e->getLine());
        }

        try {
            // 开启事务
            $_pdos = array_column($pdos, 'pdo');
            $callback($_pdos);
            // 全部回收掉
            foreach($pdos as $k => $v){
                $pdoPoll = $v['poll'];
                $pdo = $v['pdo'];
                $pdoPoll->drive()->prepareXa($pdo, $xid); // 结束 和 准备提交
            }
            foreach($pdos as $k => $v){
                $pdoPoll = $v['poll'];
                $pdo = $v['pdo'];
                $pdoPoll->drive()->commitXa($pdo, $xid); // 提交
            }

            foreach($pdos as $v){
                $v['poll']->recoveryPDO($v['pdo']);
            }
        }catch (\Throwable $e){
            $error = $e->getMessage().' '.$e->getFile().':'.$e->getLine().' ; ';
            if($e->getCode() == 2006 || strpos($e->getMessage(), '2006') || strpos($e->getMessage(), 'ackets ')){
                DbManager::clean();
                DbManager::init(); // 重新初始化
                if($isRetry){
                    return $this->transactionXa($callback, $connections, false);
                }
                App::error()->setError($error);
            }

            try {
                foreach($pdos as $k => $v){
                    $ok = $v['poll']->drive()->rollbackXa($v['pdo'], $xid);
                }
            }catch (\Throwable $err){
                $error .= $err->getMessage().' '.$err->getFile().':'.$err->getLine();
            }

            foreach($pdos as $v){
                $v['poll']->recoveryPDO($v['pdo']);
            }

            App::error()->setError($error);
        }
    }

    /**
     * raw更新对象
     * @param string $data
     * @return stdClass
     */
    public function raw(string $data){
        $obj = new \stdClass();
        $obj->data = $data;
        return $obj;
    }
    /**
     * 调用数据库操作方法
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments){
        return $this->query->$name(...$arguments);
    }
}