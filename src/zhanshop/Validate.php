<?php
// +----------------------------------------------------------------------
// | zhanshop-php / Validate.php [ 2023/2/2 下午9:18 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop;

class Validate
{
    protected array $message = [];

    protected array $errors = [];

    protected array $data = [];

    /**
     * 构造器
     * @param array $data
     * @param array $rules
     * @param array $message
     */
    public function __construct(array $data, array $rules = [], array $message = []){
        $this->message = $message;
        foreach ($rules as $k => $v){
            $functions = explode(' | ', $v);
            foreach($functions as $vv){
                $function = explode(':', $vv);
                $method = $function[0];
                unset($function[0]);
                $args = array_merge([$k, $data[$k] ?? ''], array_filter($function));
                //echo $method.'('.implode(',', $args).')'.PHP_EOL;
                //print_r($args);
                $this->data[$k] = $data[$k] ?? null;
                call_user_func([$this, $method], ...$args);
            }
        }
    }

    /**
     * 获取验证通过的数据
     * @return void
     */
    public function getData(){
        if($this->errors){
            try {
                $badRequest = \app\constant\ApiCode::BAD_REQUEST;
            }catch (\Throwable $throwable){
                $badRequest = 400;
            }
            App::error()->setError(implode(', ', $this->errors), $badRequest);
        }
        return $this->data;
    }

    /**
     * 输出错误
     * @return void
     * @throws \Exception
     */
    public function errors(){
        return $this->errors;
    }

    /**
     * 检查是否为空
     * @param string $key
     * @param mixed $val
     * @return void
     */
    protected function required(string $key ,mixed $val){
        if($val == '' || $val === null){
            $this->errors[] = ($this->message[$key] ?? $key).'不能为空';
        }
    }

    /**
     * 最大值
     * @param string $key
     * @param int $maxLength
     * @param mixed $val
     * @return void
     */
    protected function max(string $key, mixed $val, int $max){
        if($val > $max){
            $this->errors[] = ($this->message[$key] ?? $key).'不能大于'.$max;
        }
    }

    /**
     * 最小值
     * @param string $key
     * @param int $minLength
     * @param mixed $val
     * @return void
     */
    protected function min(string $key, mixed $val, int $min){
        if($val < $min){
            $this->errors[] = ($this->message[$key] ?? $key).'不能小于'.$min;
        }
    }

    /**
     * 区间验证
     * @param string $key
     * @param array $between
     * @param mixed $val
     * @return void
     */
    protected function between(string $key, mixed $val, int $min = 0, int $max = 0){
        if($val != null && $min && $max && !($val >= $min && $val <= $max)){
            $this->errors[] = ($this->message[$key] ?? $key).'的值必须在'.$min.'至'.$max.'之间';
        }
    }

    /**
     * 邮箱验证
     * @param string $key
     * @param mixed $val
     * @return void
     */
    protected function email(string $key ,mixed $val){
        if($val != null && !filter_var($val, FILTER_VALIDATE_EMAIL))
        {
            $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的email';
        }
    }

    /**
     * url验证
     * @param string $key
     * @param mixed $val
     * @return void
     */
    protected function url(string $key ,mixed $val){
        if($val != null && !filter_var($val, FILTER_VALIDATE_URL))
        {
            $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的url';
        }
    }

    /**
     * 是否一个有效的整数
     * @param string $key
     * @param mixed $val
     * @return void
     */
    public function int(string $key ,mixed $val){
        if(($int = (int)$val) != $val && $val != null){
            $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的整数';
        }
        $this->data[$key] = $int;
    }
    /**
     * 数字验证 小数点也算
     * @param string $key
     * @param mixed $val
     * @return void
     */
    protected function number(string $key ,mixed $val){
        if($val != null && !is_numeric($val)){
            $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的数字';
        }
    }

    /**
     * 浮点数验证
     * @param string $key
     * @param mixed $val
     * @return void
     */
    protected function float(string $key ,mixed $val){
        if($val != null && (float)$val != $val){
            $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的浮点数';
        }
    }

    /**
     * 字符串验证
     * @param string $key
     * @param mixed $val
     * @return void
     */
    public function string(string $key ,mixed $val)
    {
        if($val != null && !is_string($val)){
            $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的字符串';
        }
    }

    /**
     * 数组验证
     * @param string $key
     * @param mixed $val
     * @return void
     */
    protected function array(string $key ,mixed $val){
        if($val != null && !is_array($val)){
            $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的数组/字典';
        }else if($val == false) $this->data[$key] = [];
    }

    /**
     * 手机号码验证
     * @param string $key
     * @param mixed $val
     * @return void
     */
    protected function mobile(string $key ,mixed $val){
        $rule = '/^1[3-9]\d{9}$/';
        if($this->regex($key, $val, $rule, true) == false) $this->errors[] = ($this->message[$key] ?? $key).'手机号码格式不正确';
    }

    /**
     * 身份证号码验证
     * @param string $key
     * @param mixed $val
     * @return void
     */
    protected function card(string $key ,mixed $val){
        $rule = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
        if($this->regex($key, $val, $rule, true) == false) $this->errors[] = ($this->message[$key] ?? $key).'手机号码格式不正确';
    }

    /**
     * 使用自定义函数验证
     * @param string $key
     * @param string $func
     * @param mixed $val
     * @return void
     */
    protected function func(string $key , mixed $val, string $func = ''){
        if($func && $func($val) == false){
            $this->errors[] = ($this->message[$key] ?? $key).'的值不符合要求';
        }
    }

    /**
     * 长度验证
     * @param string $key
     * @param array $lengths
     * @param mixed $val
     * @return void
     */
    protected function length(string $key, mixed $val, mixed $minLength, mixed $maxLength){
        $val = (string) $val;
        $minLength = (int) $minLength;
        $maxLength = (int) $maxLength;
        if($minLength && $maxLength){
            $error = '';
            $strlen = mb_strlen($val);
            if($strlen <  $minLength){
                $error .= '不能低于'.$minLength.'个字符';
            }
            if($maxLength && $strlen >  $maxLength){
                $error .= '不能超过'.$maxLength.'个字符';
            }

            if($error) $this->errors[] = ($this->message[$key] ?? $key).$error;
        }
    }
    /**
     * 使用正则验证数据
     * @access public
     * @param mixed $value 字段值
     * @param mixed $rule  验证规则 正则规则或者预定义正则名
     * @return bool
     */
    protected function regex(string $key , mixed $value, string $rule = '', bool $retuen = false): bool
    {
        if($rule){
            if (is_string($rule) && 0 !== strpos($rule, '/') && !preg_match('/\/[imsU]{0,4}$/', $rule)) {
                // 不是正则表达式则两端补上/
                $rule = '/^' . $rule . '$/';
            }

            $bool = is_scalar($value) && 1 === preg_match($rule, (string) $value);
            if($retuen == false && $bool == false){
                $this->errors[] = ($this->message[$key] ?? $key).'的值不符合要求';
            }
            return $bool;
        }
        return true;
    }

    public function __call(string $name, array $arguments)
    {
    }
}