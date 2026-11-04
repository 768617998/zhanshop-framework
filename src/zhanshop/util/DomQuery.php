<?php

namespace zhanshop\util;
/**
元字符	说明
[a-z]	匹配任何包含小写字母a-z的字符串
[A-Z]	匹配任何包含大写字母A-Z的字符串
[0-9]	匹配任何包含0-9的字符串
[abc]	匹配任何包含小写字母a,b,c的字符串
[^abc]	匹配任何不包含小写字母a,b,c的字符串
[a-zA-Z0-9_]	匹配任何包含a-zA-Z0-9和下划线的字符串
\w	匹配任何包含a-zA-Z0-9和下划线的字符串
\W	匹配任何不包含a-zA-Z0-9和下划线的字符串
\d	匹配任何包含数字字符 [0-9]
\D	匹配任何非数字字符 [^0-9]
\s	匹配任何空白字符 等价 [\t\r\n\f]
\S	匹配任何非空白字符 [^\t\r\n\f]
\b	匹配是否到达了单词边界
\B	匹配没有到达了单词边界
\	匹配正则中的特殊字符
 */

/**
量词	说明
+	匹配任何至少包含一个前导字符串
 *	匹配任何包含零个或多个前导字符串
?	匹配任何包含零个或1个前导字符串
.	匹配任意一个字符串
{x}	匹配任何包含x个前导字符串
{x,y}	匹配任何包含 x 到 y 个前导字符串
{x,}	匹配任何包含至少x个前导字符串
^	匹配字符串的行首
$	匹配字符串的行尾
|	选择符 匹配字符串的左边或者右边
()	分组，提取
[]  匹配字符组里的每一个字符 [1,2]
 */

/**
模式修正符 	说明
i ：如果在修饰符中加上"i"，则正则将会取消大小写敏感性，即"a"和"A" 是一样的。
m：默认的正则开始"^"和结束"$"只是对于正则字符串如果在修饰符中加上"m"，那么开始和结束将会指字符串的每一行：每一行的开头就是"^"，结尾就是"$"。
s：如果在修饰符中加入"s"，那么默认的"."代表除了换行符以外的任何字符将会变成任意字符，也就是包括换行符！
x：如果加上该修饰符，表达式中的空白字符将会被忽略，除非它已经被转义。
e：本修饰符仅仅对于replacement有用，代表在replacement中作为PHP代码。
A：如果使用这个修饰符，那么表达式必须是匹配的字符串中的开头部分。比如说"/a/A"匹配"abcd"。
E：与"m"相反，如果使用这个修饰符，那么"$"将匹配绝对字符串的结尾，而不是换行符前面，默认就打开了这个模式。
U：和问号的作用差不多，用于设置"贪婪模式"。
 */
class DomQuery
{
    const selfCloseLabel = ['br', 'hr', 'area', 'base', 'img', 'input', 'link', 'meta', 'param', 'col', 'colgroup', 'command', 'frame', 'embed', 'keygen', 'source'];

    protected $outHTML = [];

    public function __construct(string $outHTML)
    {
        $this->outHTML = [$outHTML];
    }

    /**
     * 获取id
     * @param string $idName
     * @return $this
     */
    public function getElementById(string $idName, string $idCode = "id"){
        $allMatches = [];
        foreach($this->outHTML as $html){
            $pattern = '/<([a-zA-Z0-9_]+) [^<>]*'.$idCode.'="(\S*)(\s*)".$idName."(.*)".*>/iUs';  // 字母 空格 [匹配任意字符除<>]0个多个 // U 禁止贪婪匹配
            preg_match($pattern, $html, $matche);
            $startLabel = $matche[0];

            $arr = explode($matche[0], $html);

            $mateHtml = [
                'start' => $matche[0],
                'body' => $matche[0].$arr[1],
                'end' => $matche[1]
            ];

            $body = $mateHtml['body'];
            $body = str_replace('</'.$mateHtml['end'].'>', '</'.$mateHtml['end'].'>'.'<---ZHANSHOP###分割符号--->', $body);
            $arr = explode('</'.$mateHtml['end'].'>', $body);

            $mateHtml['body'] = "";
            foreach($arr as $kk => $vv){
                $startLabelCount = substr_count($mateHtml['body'], '<'.$mateHtml['end']);
                $endLabelCount = substr_count($mateHtml['body'], '</'.$mateHtml['end'].'>');
                echo $startLabelCount.'=='.$endLabelCount.PHP_EOL;
                if($mateHtml['body'] == false ||  $startLabelCount != $endLabelCount){
                    $rowHtml = str_replace([" ", "\r", "\n"], "", $vv);
                    $vv = str_replace('<---ZHANSHOP###分割符号--->', '', $vv);
                    $strpos = strpos($rowHtml, '<---ZHANSHOP###分割符号--->');
                    if($strpos === 0){
                        $mateHtml['body'] .= '</'.$mateHtml['end'].'>';
                        if(substr_count($mateHtml['body'], '<'.$mateHtml['end']) == substr_count($mateHtml['body'], '</'.$mateHtml['end'].'>')){
                            break;
                        }
                        $mateHtml['body'] .= $vv;
                        // 先统计一下 如果满了就跳出
                    }else if($strpos > 0){
                        $mateHtml['body'] .= $vv;
                        $mateHtml['body'] .= '</'.$mateHtml['end'].'>';
                    }else{
                        $mateHtml['body'] .= $vv;
                    }
                }
            }
            $allMatches[] = $mateHtml;
        }
        $this->outHTML = array_column($allMatches, 'body');
        return $this;
    }

    /**
     * 获取name
     * @param string $name
     * @return $this
     */
    public function getElementsByName(string $name){
        $allMatches = [];
        foreach($this->outHTML as $html){
            $pattern = '/<([a-zA-Z0-9_]+) [^<>]*name="'.$name.'".*>/iUs'; // 加上U之后它只匹配了每个的第一个值
            if(preg_match($pattern, $html, $matches)){
                $label = $matches[1];
                if(in_array($label, self::selfCloseLabel)){
                    $pattern = '/<'.$label.' [^<>]*name=["|\']'.$name.'[^<>]*>(.*)'.'>/iUs';
                    preg_match_all($pattern, $html, $matches);
                    $allMatches = array_merge($allMatches, $matches[0]);
                }else{
                    $pattern = '/<'.$label.' [^<>]*name=["|\']'.$name.'[^<>]*>(.*)<\/'.$label.'>/iUs'; // 没有s会导致匹配失败
                    preg_match_all($pattern, $html, $matches);
                    $allMatches = array_merge($allMatches, $matches[0]);
                }
            }
        }
        $this->outHTML = $allMatches;
        return $this;
    }

    /**
     * 获取标签数据列表
     * @param string $tag
     * @return $this
     */
    public function getElementsByTagName(string $tag, $isPattern = false){
        if(in_array($tag, self::selfCloseLabel)){
            $pattern = '/<'.$tag.'.*>/iUs';
        }else{
            $pattern = '/<'.$tag.'.*>(.*)<\/'.$tag.'>/iUs';
        }

        if($isPattern) $pattern = $tag;

        $allMatches = [];
        foreach($this->outHTML as $html){
            preg_match_all($pattern, $html, $matches);
            $allMatches = array_merge($allMatches, $matches[0]);
        }
        $this->outHTML = $allMatches;
        return $this;
    }

    /**
     * 获取class的数据
     * @param $className
     * @param $isPattern
     * @return $this
     */
    public function getElementsByClassName($className, $isPattern = false){
        $allMatches = [];
        foreach($this->outHTML as $html){
            $pattern = '/<([a-zA-Z0-9_]+) [^<>]*class="(\S*)(\s*)'.$className.'(.*)".*>/iUs';  // 字母 空格 [匹配任意字符除<>]0个多个 // U 禁止贪婪匹配
            if($isPattern) $pattern = $className;
            preg_match_all($pattern, $html, $matches);
            $labelCount = count($matches[0]);
            $startLabel = array_unique($matches[0]);

            $mateHtml = [];
            foreach($startLabel as $k => $v){
                // 判断他是否是单行标签
                //var_dump($v);
                preg_match("/<([a-zA-Z0-9_]+)/is", $v, $matche);
                $tagName = $matche[1];
                if(in_array($tagName, self::selfCloseLabel)){
                    $mateHtml[] = [
                        'start' => $v,
                        'body' => $v,
                        'end' => ""
                    ];
                }else{
                    $arr = explode($v, $html);
                    unset($arr[0]);
                    foreach($arr as $vv){
                        $mateHtml[] = [
                            'start' => $v,
                            'body' => $v.$vv,
                            'end' => $matches[1][$k]
                        ];
                    }
                }

            }

            foreach($mateHtml as $k => $v){
                $body = $v['body'];
                $body = str_replace('</'.$v['end'].'>', '</'.$v['end'].'>'.'<---ZHANSHOP###分割符号--->', $body);
                //print_r($body);
                $arr = explode('</'.$v['end'].'>', $body);
                $mateHtml[$k]['body'] = "";
                foreach($arr as $kk => $vv){
                    $startLabelCount = substr_count($mateHtml[$k]['body'], '<'.$v['end']);
                    $endLabelCount = substr_count($mateHtml[$k]['body'], '</'.$v['end'].'>');
                    if($mateHtml[$k]['body'] == false ||  $startLabelCount != $endLabelCount){
                        $rowHtml = str_replace([" ", "\r", "\n"], "", $vv);
                        $vv = str_replace('<---ZHANSHOP###分割符号--->', '', $vv);
                        $strpos = strpos($rowHtml, '<---ZHANSHOP###分割符号--->');
                        if($strpos === 0){
                            $mateHtml[$k]['body'] .= $v['end'] ? '</'.$v['end'].'>' : "";
                            if(substr_count($mateHtml[$k]['body'], '<'.$v['end']) == substr_count($mateHtml[$k]['body'], '</'.$v['end'].'>')){
                                break;
                            }
                            $mateHtml[$k]['body'] .= $vv;
                            // 先统计一下 如果满了就跳出
                        }else if($strpos > 0){
                            $mateHtml[$k]['body'] .= $vv;
                            $mateHtml[$k]['body'] .= $v['end'] ? '</'.$v['end'].'>' : "";
                        }else{
                            $mateHtml[$k]['body'] .= $vv;
                        }
                    }
                }
            }
            $allMatches = array_merge($allMatches, $mateHtml);
        }
        $this->outHTML = array_column($allMatches, 'body');
        return $this;
    }



    /**
     * 获取包含自身的html
     * @return mixed|string
     */
    public function outHtml(){
        return $this->outHTML;
    }

    /**
     * 获取文本
     * @return string
     */
    public function innerText(){
        $allMatches = [];
        foreach($this->outHTML as $html){
            $allMatches[] = strip_tags($html);
        }
        return $allMatches == false ? null : $allMatches;
    }

    /**
     * 替换属性
     * @param string $name
     * @param string $val
     * @return void
     */
    public function setAttribute(string $name, string $val){
        $allMatches = [];
        $pattern = '/'.$name.'=[\'|"](.*)[\'|"]/iUs'; // 记得给或加上中括号
        foreach($this->outHTML as $k => $html){
            $this->outHTML[$k] = preg_replace($pattern,$name.'="'.$val.'"', $html);
        }
    }

    /**
     * 获取属性
     * @param string $name
     * @return string|null
     */
    public function getAttribute(string $name){
        $allMatches = [];
        $pattern = '/'.$name.'=[\'|"](.*)[\'|"]/iUs'; // 记得给或加上中括号
        foreach($this->outHTML as $html){
            preg_match($pattern, $html, $matches);
            $allMatches[] = $matches[1] ?? null;
        }
        return $allMatches == false ? null : $allMatches;
    }

    public function toArray(): array{
        return $this->outHTML;
    }
}