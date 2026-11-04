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
                $extra = array_values(array_filter($function, 'strlen'));
                $val = $data[$k] ?? '';
                $this->data[$k] = $data[$k] ?? null;
                // AOT 下 call_user_func([$this, $method]) 无法暴露 protected 方法作用域(user-code frame), 改显式分发
                switch($method){
                    case 'required': $this->required($k, $val); break;
                    case 'max': $this->max($k, $val, intval($extra[0] ?? 0)); break;
                    case 'min': $this->min($k, $val, intval($extra[0] ?? 0)); break;
                    case 'between': $this->between($k, $val, intval($extra[0] ?? 0), intval($extra[1] ?? 0)); break;
                    case 'email': $this->email($k, $val); break;
                    case 'url': $this->url($k, $val); break;
                    case 'int': $this->int($k, $val); break;
                    case 'number': $this->number($k, $val); break;
                    case 'float': $this->float($k, $val); break;
                    case 'string': $this->string($k, $val); break;
                    case 'array': $this->array($k, $val); break;
                    case 'mobile': $this->mobile($k, $val); break;
                    case 'card': $this->card($k, $val); break;
                    case 'date': $this->date($k, $val); break;
                    case 'time': $this->time($k, $val); break;
                    case 'func': $this->func($k, $val, $extra[0] ?? ''); break;
                    case 'length': $this->length($k, $val, $extra[0] ?? 0, $extra[1] ?? 0); break;
                    case 'regex': $this->regex($k, $val, $extra[0] ?? '', false); break;
                }
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
    protected function min(string $key, mixed $val, int $min = 0){
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
        if($val != null && ($min || $max) && !($val >= $min && $val <= $max)){
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
        $this->data[$key] = strval($val);
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
        $this->data[$key] = strval($val);
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
        $this->data[$key] = intval($val);
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
        $this->data[$key] = floatval($val);
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
        $this->data[$key] = floatval($val);
    }

    /**
     * 字符串验证
     * @param string $key
     * @param mixed $val
     * @return void
     */
    public function string(string $key ,mixed $val)
    {
        if(!is_string($val)){
            $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的字符串';
        }else{
            $this->data[$key] = $val;
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
        $val = strval($val);
        if($val){
            $rule = '/^1[3-9]\d{9}$/';
            if($this->regex($key, $val, $rule, true) == false) $this->errors[] = ($this->message[$key] ?? $key).'手机号码格式不正确';
        }
        $this->data[$key] = $val;
    }

    /**
     * 身份证号码验证
     * @param string $key
     * @param mixed $val
     * @return void
     */
    protected function card(string $key ,mixed $val){
        $val = strval($val);
        if($val != ''){
            $rule = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
            if($this->regex($key, $val, $rule, true) == false) $this->errors[] = ($this->message[$key] ?? $key).'身份证号码格式不正确';
        }
        $this->data[$key] = $val;
    }

    /**
     * 日期验证
     * @param string $key
     * @param mixed $val
     * @return bool
     */
    protected function date(string $key ,mixed $val){
        if($val != null && $val !== '' && strtotime($val) === false) $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的日期';
        $this->data[$key] = $val;
    }
    /**
     * 时间验证
     * @param string $key
     * @param mixed $val
     * @return bool
     */
    protected function time(string $key ,mixed $val){
        if($val != null && $val !== '' && strtotime($val) === false) $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的时间';
        $this->data[$key] = $val;
    }
    /**
     * 使用自定义函数验证
     * @param string $key
     * @param string $func
     * @param mixed $val
     * @return void
     */
    protected function func(string $key , mixed $val, string $func = ''){
        if($func && $func($val) === false){
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
        // 数组/对象不能强转字符串:PHP 会发 Array to string conversion 警告,经框架
        // errorHandler 直接逃逸成 500。null 与标量保持原语义,行为不变
        if(is_array($val) || is_object($val)){
            $this->errors[] = ($this->message[$key] ?? $key).'不是一个有效的字符串';
            return;
        }
        $val = (string) $val;
        $minLength = (int) $minLength;
        $maxLength = (int) $maxLength;
        if($minLength || $maxLength){
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