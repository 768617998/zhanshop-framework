<?php

namespace zhanshop;

class ShareConfig
{

    protected $data = [];
    
    /**
     * 共享配置内存初始化
     */
    public function __construct(){
        $this->load();
    }

    /**
     * 载入共享内存配置
     * @return mixed
     */
    public function load() {}
}