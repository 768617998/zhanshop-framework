<?php

namespace zhanshop\helper;
use zhanshop\App;

/**
 * 注解支持123
 * @api {method} path title
 * @apiGroup User
 * @apiHeader {String} 字段名 描述
 * @apiParam {Int} page=1 页码
 * @apiSuccess string 字段名 描述
 * @apiError CODE 错误说明
 * @apiMiddleware 1,2
 * @apiDescription text
 */
class Annotations
{
    protected $docComment;
    public function __construct(string $docComment)
    {
        $this->docComment = $docComment;
    }

    public function api(){
        // @api POST goods 添加商品
        // @api  \s+匹配一个或者多个空格  (GET|POST|DELETE|PUT)匹配GET|POST|DELETE|PUT任意一项     \s+匹配一个或者多个空格     (\w+)匹配一个包含字母数字下划线的字符串     \s+匹配一个或者多个空格  (\S*) 匹配任意非空白字符
        $matched = preg_match('/@api\s+(GET|POST|DELETE|PUT|WS|TCP|UDP|MQTT|FTP)\s+(\S*)\s+(\S*)/i', $this->docComment, $matches);
        return [
            'method' => $matches[1] ?? '',
            'uri' => $matches[2] ?? '',
            'title' => str_replace(' ', '', $matches[3] ?? ''),
        ];
    }

    public function apiGroup(){
        // @apiGroup 医生
        // @apiGroup \s+ 匹配一个或者多个空格 (\S*) 匹配任意非空白字符
        $matched = preg_match('/@apiGroup\s+(\S*)/i', $this->docComment, $matches);
        return str_replace(' ', '', $matches[1] ?? '');
    }

    public function apiDescription(){
        $matched = preg_match('/@apiDescription\s+(.*)/i', $this->docComment, $matches);
        $description = $matches[1] ?? '';
        if($description == '*/') return '';
        return str_replace(' ', '', $description);
    }

    public function apiMiddleware(){
        $matched = preg_match('/@apiMiddleware\s+(\S*)/i', $this->docComment, $matches);
        $middlewares = [];
        if(isset($matches[1])){
            $matches[1] = array_values(array_filter(explode(',',$matches[1])));
            foreach($matches[1] as $v){
                if(strpos($v, '\\') === false){
                    $middlewares[] = '\\app\\middleware\\'.str_replace('/', '\\', $v);
                }else{
                    $middlewares[] = $v;
                }
            }
        }
        return $middlewares;
    }

    protected function listParam($matches){
        $data = [];
        foreach($matches[2] as $k => $v){
            $fieldsDefault = explode('=', $v);
            $fields = $fieldsDefault[0];
            $default = $fieldsDefault[1] ?? null;

            $fields = explode('.', $fields);
            $pid = $fields[count($fields) - 2] ?? null;
            $field = $fields[count($fields) - 1];
            $row = [
                'name' => $field,
                'pname' => $pid,
                'type' => $matches[1][$k],
                'required' => $default === null ? 'true' : 'false',
                'default' => $default,
                'description' => $matches[3][$k],
                //'is_parent' => true,
                'children' => [],
            ];
            $data[] = $row;
        }
        return $data;
    }

    protected function moreParam(array &$param, $data, $id){
        foreach($data as $k => $v){
            if($v['pname'] == $id){
                unset($data[$k]);
                unset($v['pname']);
                $param[$v['name']] = $v;
                $this->moreParam($param[$v['name']]['children'], $data, $v['name']);
            }
        }
    }

    public function apiHeader(){
        //@apiHeader string token 用户token
        $matched = preg_match_all('/@apiHeader\s+([a-zA-Z]+)\s+(\S+)\s+(.*)/i', $this->docComment, $matches);
        $data = $this->listParam($matches);
        $param = [];
        // 进行分组
        foreach($data as $k => $v){
            if($v['pname'] === null){
                unset($v['pname']);
                $param[$v['name']] = $v;
                $this->moreParam($param[$v['name']]['children'], $data, $v['name']);
            }
        }
        $this->paramValues($param);
        return $param;
    }

    public function apiParam(){
        // @apiParam object basic 基本信息
        $matched = preg_match_all('/@apiParam\s+([a-zA-Z]+)\s+(\S+)\s+(.*)/i', $this->docComment, $matches);
        $data = $this->listParam($matches);
        $param = [];
        // 进行分组
        foreach($data as $k => $v){
            if($v['pname'] === null){
                unset($v['pname']);
                $param[$v['name']] = $v;
                $this->moreParam($param[$v['name']]['children'], $data, $v['name']);
            }
        }
        $this->paramValues($param);
        return $param;
    }

    public function apiSuccess(){
        // @apiSuccess int id=1 商品id
        $matched = preg_match_all('/@apiSuccess\s+([a-zA-Z]+)\s+(\S+)\s+(.*)/i', $this->docComment, $matches);
        $data = $this->listParam($matches);
        $success = [];
        // 进行分组
        foreach($data as $k => $v){
            if($v['pname'] === null){
                unset($v['pname']);
                $success[$v['name']] = $v;
                $this->moreParam($success[$v['name']]['children'], $data, $v['name']);
            }
        }
        $this->paramValues($success);
        return $success;
    }

    public function apiError(){
        // @apiError 403 权限不足
        $matched = preg_match_all('/@apiError\s+([0-9]+)\s+(\S*)/i', $this->docComment, $matches);
        $data = [];
        foreach ($matches[1] as $k => $v){
            $data[] = [
                'code' => $v,
                'description' => $matches[2][$k] ?? "",
            ];
            //$data[$v] = $matches[2][$k] ?? "";
        }
        return $data;
    }

    public function all(){
        $data = [];
        $data['api'] = $this->api();
        $data['apiGroup'] = $this->apiGroup();
        $data['apiDescription'] = $this->apiDescription();
        $data['apiMiddleware'] = $this->apiMiddleware();
        $data['apiHeader'] = $this->apiHeader();
        $data['apiParam'] = $this->apiParam();
        $data['apiSuccess'] = $this->apiSuccess();
        $data['apiError'] = $this->apiError();
        return $data;
    }
    public static function getTitle(string $note){
        $note = explode("\n", $note)[1];
        $matched = preg_match('/\* (.*)/i', $note, $matches);
        var_dump($matches);
    }

    public static function getParam(string $name, string $note){
        // @param Request $request 可能会有空格
        // @ApiGroup 医生 // 可能会有空格
        // @Route(articleList/{id}, GET) 可能没有空格
        // \s* 零个或多个空白字符
        // ^\s 以空白字符开头
        //()当做一个整体 对空号内匹配到的数据单独放一个下标
        $matched = preg_match('/@'.$name.'(\s*.*)/i', $note, $matches);
        var_dump($matches);
    }


    protected function paramValues(array &$array){
        $array = array_values($array);
        foreach($array as $k => $v){
            if($v['children'] != false){
                $this->paramValues($array[$k]['children']);
            }
        }
    }


}