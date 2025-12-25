<?php

namespace zhanshop\database\drive;

use Swoole\Database\PDOConfig;
use zhanshop\Helper;

class Pgsql
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
            ->withDriver("pgsql")
            ->withHost($hostname)
            ->withPort((int)$this->config['hostport'])
            ->withDbName($this->config['database'])
            ->withCharset($this->config['charset'])
            ->withUsername($this->config['username'])
            ->withPassword($this->config['password'])
            ->withOptions($this->config['params']);
        return $pdoConfig;
    }
}