<?php
// +----------------------------------------------------------------------
// | zhanshop-admin / SchemaService.php    [ 2023/8/18 15:06 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\service;

use zhanshop\App;
use zhanshop\Helper;

class SchemaService
{
    /**
     * 创建初始化模型
     * @param string $name
     * @param $connection
     * @return void
     */
    public static function create(string $name){
        $data = self::getCreateSchema($name);
        $schemaPath = App::appPath().DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.str_replace('.', DIRECTORY_SEPARATOR, $name).'.php';
        Helper::mkdirs(dirname($schemaPath)); // 创建目录 如果这个文件不存在的话
        file_put_contents($schemaPath, '<?php'.PHP_EOL.'return '.var_export($data, true).';');
        return $data;
    }

    /**
     * 获取将要创建的schema
     * @param $table
     * @return array
     */
    public static function getCreateSchema($table){
        $oldSchema = self::getOldSchema($table);
        $newSchema = self::getNewSchema($table);
        foreach($newSchema as $kk => $vv){
            if(isset($oldSchema[$kk])){
                $newSchema[$kk]['search'] = $oldSchema[$kk]['search'] ?? $vv['search'];
                $newSchema[$kk]['value'] = $oldSchema[$kk]['value'] ?? $vv['value'];
                $newSchema[$kk]['in_list'] = $oldSchema[$kk]['in_list'] ?? $vv['in_list'];
                $newSchema[$kk]['in_field'] = $oldSchema[$kk]['in_field'] ?? $vv['in_field'];
                $newSchema[$kk]['width'] = $oldSchema[$kk]['width'] ?? $vv['width'];
                $newSchema[$kk]['value_menu'] = $oldSchema[$kk]['value_menu'] ?? $vv['value_menu'];
                $newSchema[$kk]['input_type'] = $oldSchema[$kk]['input_type'] ?? $vv['input_type'];
                if(isset($oldSchema[$kk]['templet']) && $oldSchema[$kk]['templet']) $newSchema[$kk]['templet'] = $oldSchema[$kk]['templet'];
                if(isset($oldSchema[$kk]['edit']) && $oldSchema[$kk]['edit']) $newSchema[$kk]['edit'] = $oldSchema[$kk]['edit'];
                if(isset($oldSchema[$kk]['escape'])) $newSchema[$kk]['escape'] = $oldSchema[$kk]['escape'];
                if(isset($oldSchema[$kk]['sort'])) $newSchema[$kk]['sort'] = $oldSchema[$kk]['sort'];
                if(isset($oldSchema[$kk]['unresize'])) $newSchema[$kk]['unresize'] = $oldSchema[$kk]['unresize'];
                if(isset($oldSchema[$kk]['event']) && $oldSchema[$kk]['event']) $newSchema[$kk]['event'] = $oldSchema[$kk]['event'];
                if(isset($oldSchema[$kk]['style']) && $oldSchema[$kk]['style']) $newSchema[$kk]['style'] = $oldSchema[$kk]['style'];
                if(isset($oldSchema[$kk]['align']) && $oldSchema[$kk]['align']) $newSchema[$kk]['align'] = $oldSchema[$kk]['align'];
            }
        }
        return $newSchema;
    }

    /**
     * 获取老的schema数据
     * @param $table
     * @return void
     */
    public static function getOldSchema($table){
        $schemaFile = App::appPath().DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.str_replace('.', DIRECTORY_SEPARATOR, $table).'.php';
        $data = [];
        if(file_exists($schemaFile)){
            $data = (array) include $schemaFile;
        }
        return $data;
    }

    public static function getNewSchema($table){
        $data = App::database()->model($table)->query('SHOW FULL COLUMNS FROM '.$table);
        $schemaData = [];
        foreach($data as $v){
            $maxlength = 0;
            $types = explode('(', $v['Type']);
            if(isset($types[1])){
                $maxlength = intval(explode(')', $types[1])[0]);
            }
            $schemaData[$v['Field']] = [
                'field' => $v['Field'],
                'type' => explode(' ', $v['Type'])[0],
                'key' => strtolower($v['Key']),
                'null' => strtolower($v['Null']),
                'default' => $v['Default'],
                'title' => $v['Comment'] ? $v['Comment'] : $v['Field'],
                'search' => false,
                'value' => [],
                'in_list' => true,
                'in_field' => true,
                'width' => 120,
                'value_menu' => '',
                'input_type' => 'text',
                'input_maxlength' => $maxlength,
            ];
        }
        return $schemaData;
    }
}