<?php
// +----------------------------------------------------------------------
// | admin / TestCase.php    [ 2023/6/13 下午1:09 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

/**
 * 断言两个变量具有相同的类型和值
 * @method assertSame(mixed $expected, mixed $actual, string $message = '')
 *
 * 断言某个条件为真
 * @method assertTrue(mixed $condition, string $message = '')
 *
 * 断言两个变量相等
 * @method assertEquals(mixed $expected, mixed $actual, string $message = '')
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * 输出字符串
     * @param string $msg
     * @return void
     */
    public function echo(string $msg){
        $this->expectOutputString($msg);
    }


    protected function runTest() : mixed
    {
        $data = null;
        if(extension_loaded('swoole')){
            \Swoole\Coroutine\run(function() use (&$data){
                $data = $this->{$this->name()}();
                \Swoole\Timer::clearAll();
            });
        }else{
            $data = $this->{$this->name()}();
        }
        return $data;
    }
    
}