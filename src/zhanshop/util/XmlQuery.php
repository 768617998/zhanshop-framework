<?php

namespace zhanshop\util;

class XmlQuery
{
    protected $outHTML = [];

    public function __construct(string $outHTML)
    {
        $this->outHTML = [$outHTML];
    }

    /**
     * 根据xml的标签名称和clss属性和值提取元素
     * @param string $tagName
     * @param string $classCode
     * @return $this
     */
    public function getElementsByClassName(string $tagName, string $classCode){
        $allMatches = [];
        foreach($this->outHTML as $html){
            $pattern = '/<('.$tagName.') [^<>]*'.$classCode.'.*>/iUs';  // 字母 空格 [匹配任意字符除<>]0个多个 // U 禁止贪婪匹配
            preg_match_all($pattern, $html, $matches);
            $labelCount = count($matches[0]);
            $startLabel = array_unique($matches[0]);

            $mateHtml = [];
            foreach($startLabel as $k => $v){
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

    public function getAttribute(string $name, bool $firstVal = false){
        $allMatches = [];
        $pattern = '/'.$name.'=[\'|"](.*)[\'|"]/iUs'; // 记得给或加上中括号
        foreach($this->outHTML as $html){
            preg_match($pattern, $html, $matches);
            $allMatches[] = $matches[1] ?? null;
        }
        if($firstVal) return $allMatches[0] ?? null;
        return $allMatches == false ? null : $allMatches;
    }

    public function toArray(){
        return $this->outHTML;
    }
}