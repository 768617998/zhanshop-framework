<?php
// +----------------------------------------------------------------------
// | flow-course / Env.php    [ 2021/10/25 9:35 上午 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2021 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);


namespace zhanshop;


class Env
{
    /**
     * 环境变量数据
     * @var array
     */
    protected $data = [];

    /**
     * 构造方法载入env文件
     * Env constructor.
     */
    public function __construct()
    {

        $this->load($this->envFile());
    }

    /**
     * 获取env文件路径
     * @return string
     */
    public function envFile(){
        $envName = $_SERVER['APP_ENV'] ?? 'dev';
        $envFile = App::rootPath() .DIRECTORY_SEPARATOR. '.env.' . $envName;
        return $envFile;
    }

    /**
     * 读取环境变量定义文件
     * @access public
     * @param string $file 环境变量定义文件
     * @return void
     */
    public function load(string $file): void
    {
        if(file_exists($file)){
            $env = parse_ini_file($file, true) ?: [];
            $this->set($env);
        }
    }

    /**
     * 获取环境变量值
     * @access public
     * @param string $name    环境变量名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get(string $name = "", mixed $default = null)
    {
        if (is_null($name)) {
            return $this->data;
        }

        $name = strtoupper(str_replace('.', '_', $name));

        return $this->getEnv($name, $default);
    }

    /**
     * 获取环境变量指向的文件
     * @param string|null $path
     * @return false|string
     */
    public function getFileData(string $name = "")
    {
        $path = $this->get($name);
        if($path && file_exists($path)){
            return file_get_contents($path);
        }
        return "";
    }

    protected function getEnv(string $name, $default = null)
    {
        $result = getenv('PHP_' . $name);
        if (false === $result) {
            $result = $this->data[$name] ?? null;
            if($result === null){
                $result = $_SERVER[$name] ?? $default;
            }
        }

        if ('false' === $result) {
            $result = false;
        } elseif ('true' === $result) {
            $result = true;
        }
        
        return $result;
    }

    /**
     * 设置环境变量值
     * @access public
     * @param string|array $env   环境变量
     * @param mixed        $value 值
     * @return void
     */
    public function set($env, $value = null): void
    {
        if (is_array($env)) {
            $env = array_change_key_case($env, CASE_UPPER);

            foreach ($env as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        $this->data[$key . '_' . strtoupper($k)] = $v;
                    }
                } else {
                    $this->data[$key] = $val;
                }
            }
        } else {
            $name = strtoupper(str_replace('.', '_', $env));

            $this->data[$name] = $value;
        }
    }

    /**
     * 检测是否存在环境变量
     * @access public
     * @param string $name 参数名
     * @return bool
     */
    public function has(string $name): bool
    {
        return !is_null($this->get($name));
    }

    /**
     * 扩展检查
     * @param array $extNames
     * @return void
     */
    public function checkExtensions(array $extNames){
        $loadedExtensions = get_loaded_extensions();
        foreach ($extNames as $v){
            if(!in_array($v, $loadedExtensions)){
                Log::errorLog(SWOOLE_LOG_ERROR, $v.'扩展没有安装！');
                return false;
            }
        }
        return true;
    }
}