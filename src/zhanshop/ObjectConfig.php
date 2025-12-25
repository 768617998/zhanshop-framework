<?php

namespace zhanshop;

class ObjectConfig
{
    public function __construct(){
        $this->init();
    }
    abstract public function init();
}