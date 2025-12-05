<?php
// +----------------------------------------------------------------------
// | flow-course / Helper.php    [ 2021/10/27 10:54 上午 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2021 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);


namespace zhanshop;


use zhanshop\util\Aes;

class Helper
{
    /**
     * 下划线转驼峰
     * 思路：step1.原字符串转小写,原字符串中的分隔符用空格替换,在字符串开头加上分隔符，step2.将字符串中每个单词的首字母转换为大写,再去空格,去字符串首部附加的分隔符.
     * @param string $uncamelized_words
     * @param string $separator
     * @return string
     */

    public static function camelize(string $uncamelized_words, string $separator='_')
    {
        $uncamelized_words = $separator. str_replace($separator, " ", strtolower($uncamelized_words));
        return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator );
    }

    /**
     * 驼峰命名转下划线命名
     * 思路: 小写和大写紧挨一起的地方,加上分隔符,然后全部转小写
     * @param string $camelCaps
     * @param string $separator
     * @return string
     */
    public static function uncamelize(string $camelCaps, string $separator='_')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $camelCaps));
    }

    /**
     * 创建目录
     * @param string $path
     * @return bool
     */
    public static function mkdirs(string $path){
        if(!is_dir($path)){
            self::mkdirs(dirname($path));
            if(!mkdir($path, 0777)){
                return false;
            }
        }
        return true;
    }

    /**
     * 生成uuid
     * @param int $num
     * @return string
     */
    public static function uuid(){
        $ip = str_replace('.','',$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');//9位
        // 最高12
        if(is_numeric($ip) == false) $ip = '127001';
        // 最高14
        $time = substr((new \DateTime())->format('ymdHisu'), 0, 14);
        $code = self::getNonceStr(32 - strlen($ip) -  strlen($time));
        return $code.$time.$ip;
    }

    /**
     * 生成php文件头注释
     */
    public static function  headComment($class){
        $year = date('Y');
        $date = date('Y-m-d H:i:s');
        return "<?php
// +----------------------------------------------------------------------
// | {$class}【系统生成】   [ {$date} ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~{$year} zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);\n\n";

    }

    /**
     * 生成一个19位的订单号
     * @param $suffix
     * @return string
     */
    public static function orderId(){
        $date = new \DateTime();
        $date = substr($date->format('ymdHisu'), 0, 14).substr(strval(getmypid()), -1, 1);
        $date .= sprintf("%04d", rand(0, 9999));
        return intval($date);
    }

    /**
     * 生成一个小于等于19位的数字
     * @param $length
     * @return string
     */
    public static function getNonceNum($length = 6)
    {
        if($length >= 19) $length = 19;
        $max = intval(str_repeat("9", $length));
        return sprintf("%0{$length}d", rand(0, $max));
    }

    /**
     * 基于当前时间或者一个随机数
     * @param $length
     * @return string
     */
    public static function getDateNonceNum($length = 32){
        $date = new \DateTime();
        $date = substr($date->format('ymdHisu'), 0, 14).substr(strval(getmypid()), -1, 1);
        $length -= strlen($date);
        $max = intval(str_repeat("9", $length));
        $date .= sprintf("%0{$length}d", rand(0, $max));
        return $date;
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return string
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }

    /**
     * 解析model名字的字符
     * @param string $name
     * @return void
     */
    public static function parseStrModel(string $name){
        $tables = explode('.', $name);
        $table = $tables[0];
        $modelDir = $tables[0];
        if(isset($tables[1])){
            $table = $tables[1];
            //$modelDir .= '\\';
        }else{
            $modelDir = '';
        }
        return [
            'dir' => $modelDir,
            'table' => $table,
            'class' => '\\app\\model\\'.($modelDir ? $modelDir.'\\' : '').ucfirst(self::camelize($table))
        ];
    }

    /**
     * 获取schema文件路径
     * @param string $name
     * @return void
     */
    public static function getSchemaPath(string $name){
        $parseStrModel = self::parseStrModel($name);
        return App::appPath().DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.($parseStrModel['dir'] ? $parseStrModel['dir'].DIRECTORY_SEPARATOR : '').$parseStrModel['table'].'.php';
    }

    /**
     * 获取指定日期的年月周
     * @param int|string $date
     * @return void
     */
    public static function getWeek(int|string $date){
        if(is_string($date)){
            $date = strtotime($date);
        }

        $weekW = date("w", $date);
        $weekNum = $weekW == 1 ? -0:-1;

        $year = date("Y", $date);
        $month = date("m", $date);
        $yearWeek = date("W", $date);
        $day = date('d', $date);
        $monthWeek = ceil($day / 7);
        if($month == 1 && $yearWeek > 50){
            $year--;
            $month = 12;
            $yearLastMonthWeek1 = date("W", strtotime($year.'-'.$month));
            $monthWeek = $yearWeek - $yearLastMonthWeek1;
        }

        return [
            'date' => date('Y-m-d', $date),
            'year' => $year, // 当前年份
            'month' => $month, // 当前月份
            'year_week' => $yearWeek, // 当前年内周数
            'month_week' => $monthWeek // 当前月内周数
        ];
    }

    /**
     * 获取周一的日期
     * @param int|string $date
     * @return string
     */
    public static function getWeek1(int|string $date){

        if(is_string($date)){
            $date = strtotime($date);
        }

        $weekW = date("w", $date);
        $weekNum = $weekW == 1 ? -0:-1;
        return date("Y-m-d", strtotime(date("Y-m-d", $date).$weekNum." week Monday"));
    }

    /**
     * 获取周日的日期
     * @param int|string $date
     * @return string
     */
    public static function getWeek7(int|string $date){
        $date = self::getWeek1($date);
        return date('Y-m-d', strtotime('+6 day', strtotime($date)));
    }

    /**
     * 清除所有标点符号
     * @param string $str
     * @return array|string|string[]|null
     */
    public static function clearSymbol(string $str){
        $pattern = '/[[:punct:]]+/u';
        $replacement = '';
        return preg_replace($pattern, "", $str);
    }

    /**
     * GC清理
     * @return void
     */
    public function gcClean()
    {
        $num = gc_collect_cycles();
        App::log()->push(getmypid().'gc清理'.$num);
    }

    /**
     * 唯一编号
     * @param $serial
     * @return void
     */
    public static function uniqueNum(int $serial = 0)
    {
        $date = new \DateTime();
        return intval(substr($date->format('ymdHisuv'), 0, 18).rand(1, 9)) + $serial;
    }

    /**
     * 拷贝目录
     * @param $src
     * @param $dst
     * @return void
     */
    public static function copyDir($src, $dst)
    {
        $dir = opendir($src);
        mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($src . '/' . $file)) {
                    self::copyDir($src . '/' . $file, $dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * 删除一个目录
     * @param string $dir
     * @return bool
     */
    public static function rmdir(string $dir)
    {
        if (!file_exists($dir)) {
            return false;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            $item = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($item)) {
                self::rmdir($item);
            } else {
                unlink($item);
            }
        }

        return rmdir($dir);
    }

    /**
     * 获取某一天的开始时间戳和结束时间戳
     * @param string $datetime
     * @return array
     */
    public static function dayTimes(string $datetime = "")
    {
        if($datetime){
            $time = strtotime($datetime);
        }else{
            $time = time();
        }
        $startTime = strtotime(date('Y-m-d', $time));
        $endTime = $startTime + 86399;
        return [$startTime, $endTime];
    }

    /**
     * 域名解析
     * @param string $domainName
     * @param int $timeout
     * @return string
     */
    public static function gethostbyname(string $domainName, int $timeout = 3)
    {
        if(!filter_var($domainName, FILTER_VALIDATE_IP)){
            $addr = \Swoole\Coroutine\System::gethostbyname($domainName,AF_INET, $timeout);
            if($addr) return $addr;

            $addr = \Swoole\Coroutine\System::gethostbyname($domainName,AF_INET6, $timeout);
            if($addr) return $addr;
        }
        return $domainName;
    }

    /**
     * 使用对称加密
     * @param $code
     * @return string
     */
    public static function symmetryEncode(string $code)
    {
        $data = App::make(Aes::class)->encrypt(time().','.$code);
        $data = urlencode($data);
        return str_replace("%", "_", $data);
    }

    /**
     * 对称解密
     * @param string $code
     * @param $expire
     * @return string|null
     * @throws \Exception
     */
    public static function symmetryDecode(string $code, $expire = 9999999999)
    {
        $code = str_replace("_", "%", $code);
        $code = urldecode($code);
        $data = App::make(Aes::class)->decrypt($code);
        $arr = [];
        if($data){
            $arr = explode(',', $data, 2);
            if(intval($arr[0]) + $expire < time()){
                return null;
            }
        }
        return $arr[1] ?? null;
    }

    /**
     * 对IPv4/IPv6地址进行求余运算
     * @param string $ip IP地址（支持IPv4/IPv6）
     * @param int $modulus 模数（必须>0）
     * @return int|null 余数（0~modulus-1）
     */
    public static function ipMod($ip, $modulus = 256) {
        // 验证模数有效性
        if ($modulus <= 0) return 0;

        // 检测并规范化IP地址
        $ipType = 0;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipType = 4;
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipType = 6;
            // 展开IPv6缩写（如::1 -> 0000:0000:0000:0000:0000:0000:0000:0001）
            $ip = inet_pton($ip);
            $ip = inet_ntop($ip); // 标准化格式
        } else {
            return 0;
        }

        try {
            if ($ipType === 4) {
                // IPv4处理：转为32位无符号整数
                $numeric = sprintf('%u', ip2long($ip));
            } else {
                // IPv6处理：转为128位二进制后转十六进制字符串
                $binary = inet_pton($ip);
                $hex = unpack('H*', $binary)[1];
                $numeric = gmp_init($hex, 16);
            }

            // 取余运算
            if ($ipType === 4) {
                $remainder = bcmod($numeric, (string)$modulus);
            } else {
                $remainder = gmp_intval(gmp_mod($numeric, $modulus));
            }

            return (int)$remainder;
        } catch (\Exception $e) {
            return -1;
        }
    }

    /**
     * 字符串求余
     * @param string $str
     * @param $modulus
     * @return int
     */
    public static function strMod(string $str, $modulus = 256) {
        if ($modulus <= 0) return 0;
        $sum = 0;
        for ($i = 0; $i < strlen($str); $i++) {
            $sum += ord($str[$i]);
        }
        return  $sum % $modulus;
    }

    /**
     * 是否内网IPV4地址
     * @param string $ip
     * @return bool
     */
    public static function isPrivateIpv4(string $ip)
    {
        if(strpos($ip, '::ffff:') === 0){
            $count = 1;
            $ip = str_replace('::ffff:', '', $ip, $count);
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $ip = ip2long($ip);

        $privateRanges = [
            ['start' => ip2long('10.0.0.0'),     'end' => ip2long('10.255.255.255')],
            ['start' => ip2long('172.16.0.0'),   'end' => ip2long('172.31.255.255')],
            ['start' => ip2long('192.168.0.0'),  'end' => ip2long('192.168.255.255')],
            ['start' => ip2long('127.0.0.0'),    'end' => ip2long('127.255.255.255')],
            ['start' => ip2long('169.254.0.0'),  'end' => ip2long('169.254.255.255')],
        ];

        foreach ($privateRanges as $range) {
            if ($ip >= $range['start'] && $ip <= $range['end']) {
                return true;
            }
        }

        return false;
    }
}