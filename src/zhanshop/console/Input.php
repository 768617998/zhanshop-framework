<?php
// +----------------------------------------------------------------------
// | zhanshop-swoole / Input.php    [ 2021/12/30 5:07 下午 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2021 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);


namespace zhanshop\console;


/**
 * 控制台请求对象
 * Class Input
 * @package kernel\console
 */
class Input
{
    /**
     * 当前指令
     * @var null
     */
    protected $command = null;

    /**
     * 当前环境
     * @var string
     */
    protected $appEnv = null;
    /**
     * 请求参数
     * @var array
     */
    protected $param = [];

    /**
     * 控制台输入源
     * @var array
     */
    protected $argv = [];

    /**
     * 处理控制台参数
     * Input constructor.
     * @param $param
     */
    public function __construct($input){

        $this->setCommand($input);

        $this->setParam($input);

        $this->setAppEnv($input);
    }

    /**
     * 设置当前控制台命令
     * @param string $command
     */
    protected function setCommand(array &$input){
        $command = $input[1] ?? 'help';
        unset($input[0], $input[1]);
        $input = array_values($input);
        $this->command = $command;
        $this->argv = $input;
    }

    /**
     * 设置请求参数
     * @param $input
     */
    protected function setParam(array &$input){
        foreach($input as $k => $v){
            if($k % 2 == 0){
                $v = str_replace('--', '', $v);
                $this->param[$v] = $input[$k + 1] ?? null;
            }
        }
        $input = $this->param;
    }

    /**
     * 设置当前控制台环境
     * @param string $appEnv
     */
    protected function setAppEnv(array &$param){
        $appEnv = $this->param['APP_ENV'] ?? 'production';
        unset($this->param['APP_ENV']);
        $this->appEnv = $appEnv;
    }

    /**
     * 获取当前控制台环境
     * @param string $appEnv
     */
    public function getAppEnv(){
        return $this->appEnv;
    }

    /**
     * 获取当前控制台指令
     * @param string $appEnv
     */
    public function getCommand(){
        return $this->command;
    }

    /**
     * 获取请求参数
     * @param string $name
     * @param mixed $default
     */
    public function param(string $name, mixed $default = null){
        return $this->param[$name] ?? $default;
    }

    /**
     * 获取用户键盘输入内容
     * @param string $name
     * @return string
     */
    public function input(string $name, string $description, ?string $default = null){
        if($description) echo '请输入('.$name.')'.$description . '：';
        if(PHP_OS == 'WINNT'){
            $input = readline();
        }else{
            while (true){
                try {
                    $fp = fopen('/dev/stdin', 'r');
                    $input = fgets($fp, 255);
                    fclose($fp);
                    break;
                }catch (\Throwable $err){
                    return $this->input($name, '', $default);
                }
            }
        }
        $input = chop($input);
        $input = $input === '' ? $default : $input;
        $this->param[$name] = $input ?? $this->input($name, $description, $default);
        return $input;
    }

    /**
     * 获取用户键盘输入内容
     * @return string
     */
    public function read(){
        if(PHP_OS == 'WINNT'){
            $input = readline();
        }else{
            $fp = fopen('/dev/stdin', 'r');
            $input = fgets($fp, 255);
            fclose($fp);
        }
        $input = chop($input);
        return $input;
    }

    /**
     * 获取所有的请求参数
     * @return array
     */
    public function all(){
        return $this->param;
    }

    /**
     * 获取控制台源参数
     * @return array
     */
    public function getArgv(){
        return $this->argv;
    }

    /**
     * 设定当前请求参数
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value){
        $this->param[$offset] = $value;
    }
}