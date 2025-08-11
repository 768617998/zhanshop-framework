<?php
// +----------------------------------------------------------------------
// | zhanshop-admin / SSSCODE.php    [ 2023/8/20 14:09 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\server\service;

class ApiSampleCode{

    protected static $paramCode = "";
    protected static function paramCode(array &$param, int $tn = 1){
        $tstr = str_repeat("    ", $tn);
        if(self::$paramCode == false || self::$paramCode[mb_strlen(self::$paramCode) - 1] == ':'){
            self::$paramCode .= '{'.PHP_EOL;
        }else{
            self::$paramCode .= $tstr.'{'.PHP_EOL;
        }

        foreach($param as $v){
            if($v['type'] == 'object' && isset($v['children'])){
                self::$paramCode .= $tstr.'   "'.$v['name'].'":';
                self::$paramCode .= self::paramCode($v['children'], $tn + 1);
            }elseif($v['type'] == 'array'){
                self::$paramCode .= PHP_EOL.$tstr.'   "'.$v['name'].'":['.PHP_EOL;
                self::$paramCode .= self::paramCode($v['children'], $tn + 1);
                self::$paramCode .= ']';
            }else{
                self::$paramCode .= $tstr.'   "'.$v['name'].'":'.("\"".($v['default'] ?? '')."\",").' //'.$v['description'].PHP_EOL;
            }

        }
        self::$paramCode .= PHP_EOL.$tstr.'}';
    }

    public static function vue3(string $url, string $method, array $header, array $param){
        $headerCode = '{'.PHP_EOL;

        foreach($header as $v){
            $headerCode .= '        "'.$v['name'].'": '.("\""."\",").' //'.$v['description'].PHP_EOL;
        }
        $headerCode .= '    }';
        self::$paramCode = "";

        self::paramCode($param);
        $bodyCode = self::$paramCode;
        self::$paramCode = "";
        //$bodyCode .= '    }';
        $paramsCode = "{}";
        if($method == 'GET'){
            $paramsCode = $bodyCode;
            $bodyCode = '{}';
        }

        $code = "// vuejs示例代码 //
axios.request({
    // 请求的接口地址
    url: \"".$url."\",
    //请求方法
    method: \"".strtoupper($method)."\",
    //超时时间设置，单位毫秒
    timeout: 30000,
    //请求头参数
    headers: ".$headerCode.",
    // 与请求一起发送的 URL 参数
    params: ".$paramsCode.",
    // 请求主体被发送的数据
    data: ".$bodyCode.",
}).then(res => {
    // 请求成功处理
    alert(JSON.stringify(res));
}).catch(function (error) {
    // 请求失败处理
    alert(error.message);
});".PHP_EOL;
        return $code;
    }

    public static function jquery(string $url, string $method, array $header, array $param){
        $headerCode = '{'.PHP_EOL;

        foreach($header as $v){
            $headerCode .= '        "'.$v['name'].'": '.("\""."\",").' //'.$v['description'].PHP_EOL;
        }
        $headerCode .= '    }';
        self::$paramCode = "";

        self::paramCode($param);
        $bodyCode = self::$paramCode;
        self::$paramCode = "";
        if($method == 'GET'){
            $bodyCode = '{}';
        }
        $code = "// jquery示例代码 //
var request = $.ajax({
    //请求的接口地址
    url: '".$url."',
    //请求方法
    method: '".strtoupper($method)."',
     //超时时间设置，单位毫秒
    timeout: 30000,
    //请求头参数
    headers: ".$headerCode.",
    //请求的数据
    data: ".$bodyCode.",
});
// 请求成功
request.done(function(data) {
    alert(JSON.stringify(data));
});
// 请求异常
request.fail(function(jqXHR) {
    alert(\"接口出错\\n\"+jqXHR.statusText);
});".PHP_EOL;
        return $code;
    }

    public static function php($detail, &$request){
        $method = $request->param('method');
        $headerStr = '    "Content-Type:application/json", '.PHP_EOL;
        foreach($detail['header'] as $v){
            $headerStr .= '    "'.$v['name'].':'.$v['default'].'", //'.$v['description'].PHP_EOL;
        }
        $headerStr = rtrim($headerStr, PHP_EOL);
        $bodyCode = '{'.PHP_EOL;
        foreach($detail['body'] as $v){
            if(isset($v['children']) && $v['children']){
                self::jqueryRequestParam($v['name'], $v['type'], $bodyCode, $v['children']);
            }else{
                $bodyCode .= '        "'.$v['name'].'": '.(is_int($v['default'] ?? '') ? $v['default'] : ("\"".($v['default'] ?? '')."\",")).' //'.$v['description'].PHP_EOL;
            }
        }
        $bodyCode .= '    }';
        $code = "<?php\n\$ch = curl_init();
curl_setopt(\$ch, CURLOPT_URL, '".$detail['detail']['server_url']."');
curl_setopt(\$ch, CURLOPT_CUSTOMREQUEST, '".strtoupper($method)."'); //设置请求方式
curl_setopt(\$ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt(\$ch, CURLOPT_SSL_VERIFYHOST,false);

// 请求头 
curl_setopt(\$ch, CURLOPT_HTTPHEADER, [
$headerStr
]);

// 请求参数
curl_setopt(\$ch, CURLOPT_POST, 1);
curl_setopt(\$ch, CURLOPT_POSTFIELDS, '".$bodyCode."');

// 解析方式IPV4
curl_setopt(\$ch, CURLOPT_IPRESOLVE, 1);

\$output = curl_exec(\$ch);
\$code = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);

echo '响应状态:'.\$code.',结果:'.\$output;".PHP_EOL;
        return $code;
    }

    public static function java($detail, &$request){
        return '';
    }

    public static function go($detail, &$request){
        return '';
    }

    public static function python($detail, &$request){
        return '';
    }

    public function curl($detail, &$request){
        return '';
    }

    /**
     * 获取c语言的示例代码
     * @param $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic($name, array $arguments)
    {
        return self::get($name, $arguments);
    }


}
