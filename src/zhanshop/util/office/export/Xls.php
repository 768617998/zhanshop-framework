<?php
// +----------------------------------------------------------------------
// | zhanshop-cloud / Xls.php    [ 2025/12/19 16:50 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2025 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\util\office\export;

class Xls
{
    protected $workbook = false;
    protected $workStyle = false;

    protected array $style = [
        'default' => [
            'color' => '#000000',
            'bold' => false,
            'font-family' => '微软雅黑',
            'font-size' => '12px', // 字体大小
            'text-align' => 'left', // left（左对齐）、right（右对齐）、center（水平居中对齐）
            'vertical-align' => 'middle', // top（上对齐）、bottom（下对齐）、middle（垂直居中对齐）
            'background-color' => '#ffffff', // 背景颜色
        ],
        'bold' => [
            'color' => '#000000',
            'bold' => true,
            'font-family' => '微软雅黑',
            'font-size' => '12px', // 字体大小
            'text-align' => 'left', // left（左对齐）、right（右对齐）、center（水平居中对齐）
            'vertical-align' => 'middle', // top（上对齐）、bottom（下对齐）、middle（垂直居中对齐）
            'background-color' => '#ffffff', // 背景颜色
        ],
    ];

    public function __construct(array $style = [])
    {
        foreach($style as $k => $v){
            $this->style[$k] = $v;
        }
    }

    /**
     * 工作薄
     * @return string
     */
    public function Workbook(){
        $content = '<?xml version="1.0"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
xmlns:o="urn:schemas-microsoft-com:office:office"
xmlns:x="urn:schemas-microsoft-com:office:excel"
xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
xmlns:html="http://www.w3.org/TR/REC-html40">';
        $this->workbook = true;
        return $content;
    }

    /**
     * 获取工作薄样式
     * @return void
     */
    public function styles(){
        if($this->workbook == false) throw new \Exception('工作薄还没有创建', 400);
        $content = '<Styles>';
        foreach ($this->style as $k => $v){
            $content .= '<Style ss:ID="'.$k.'">';

            // 设置居中
            if(isset($v['text-align']) || isset($v['vertical-align'])){
                $textAalign = isset($v['text-align']) ? ' ss:Horizontal="'.ucfirst($v['text-align']).'" ' : '';
                $verticalAlign = isset($v['vertical-align']) ? ' ss:Vertical="'.ucfirst($v['vertical-align']).'" ' : '';
                $content .= '<Alignment '.$textAalign.$verticalAlign.'/>';
            }

            // 设置背景色
            if(isset($v['background-color'])){
                $content .= '<Interior ss:Color="'.$v['background-color'].'" ss:Pattern="Solid"/>';
            }

            // 设置字体及样式
            $fontFamily = $v['font-family'] ?? '微软雅黑';
            $fontSize = $v['font-size'] ?? '12';
            $color = $v['color'] ?? '#00B050';
            $bold = isset($v['bold']) ? ' ss:Bold="'.intval($v['bold']).'" ' : '';
            $content .= '<Font ss:FontName="'.$fontFamily.'" x:CharSet="134" ss:Size="'.$fontSize.'" ss:Color="'.$color.'"'.$bold.'/>';

            $content .= '<Borders>';
            $content .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
            $content .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
            $content .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
            $content .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
            $content .= '</Borders>';

            $content .= '</Style>';
        }
        $this->workStyle = true;
        return $content.'</Styles>';
    }

    /**
     * 添加一个工作表
     * @param string $name
     * @return \zhanshop\util\XlsWorksheet
     */
    public function worksheet(){
        if($this->workStyle == false) throw new \Exception('工作薄样式创建gzb', 400);
        $worksheet = new XlsWorksheet();
        return $worksheet;
    }

    /**
     * 结束
     * @return string
     */
    public function end(){
        $content = '</Workbook>';
        return $content;
    }
}

/**
 * 工作表
 */
class XlsWorksheet{

    /**
     * 工作表名
     * @var string
     */
    protected $name = '';

    public function __construct()
    {

    }

    /**
     * 设置工作表名称并返回工作表内容
     * @param string $name
     * @return void
     */
    public function title(string $name){
        $content = '<Worksheet ss:Name="'.$name.'">'.PHP_EOL;
        $content .= '  <Table>';
        $this->name = $name;
        return $content;
    }

    /**
     * 表头
     * @param array $cellDatas
     * @param int $lineHeight
     * @return void
     */
    public function header(array $cellDatas, int $lineHeight = 20){
        if(!$this->name) throw new \Exception('工作表名还没有设置', 400);
        $column = '';
        $content = '<Row ss:Height="'.intval($lineHeight).'">';
        foreach($cellDatas as $k => $v){
            $width = $v['width'] ?? 'auto';
            $widthStyle = ' ss:AutoFitWidth="1"';
            if($width != 'auto'){
                $widthStyle = ' ss:Width="'.$width.'"';
            }
            $column .= '<Column ss:Index="'.($k+1).'" '.$widthStyle.'/>';
            $content .= '<Cell ss:StyleID="'.$v['style'].'"><Data ss:Type="'.ucfirst($v['type']).'">'.htmlspecialchars($v['value']).'</Data></Cell>';
        }
        $content .= '</Row>';
        return $column.$content;
    }

    /**
     * 添加行
     * @return void
     */
    public function row(array $cellDatas, int $lineHeight = 20){
        if(!$this->name) throw new \Exception('工作表名还没有设置', 400);

        $content = '<Row ss:Height="'.intval($lineHeight).'">';
        foreach($cellDatas as $k => $v){
            $content .= '<Cell ss:StyleID="'.$v['style'].'"><Data ss:Type="'.ucfirst($v['type']).'">'.htmlspecialchars($v['value']).'</Data></Cell>';
        }
        $content .= '</Row>';
        return $content;
    }

    /**
     * 结束符
     * @return string
     */
    public function end(){
        return '</Table></Worksheet>';
    }
}