<?php
// +----------------------------------------------------------------------
// | framework / ApiDoc.php    [ 2023/8/18 14:38 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\server\service;

use zhanshop\App;
use zhanshop\Error;
use zhanshop\Helper;
use zhanshop\server\Request;
use zhanshop\server\Response;
use zhanshop\util\Annotations;

class ApiDoc
{
    protected $app = "";
    protected $password = "";
    protected $menuList = [];
    protected $versionList = [];

    public function __construct()
    {
        $this->password = App::env()->get("DOC_PASSWORD", "123456");
    }

    protected function getApiDoc(string $app, string $url, string $id = '')
    {
        // 动态生成表格接口文档：/api.doc/table/{menuId}
        if (($url === 'table' || $url === 'treetable' || $url === 'config') && $id) {
            $menuConfig = App::database()->model("system_menu")->where(['id' => $id])->field('id,title,meta,target')->find();
            if (!$menuConfig) App::error()->setError('菜单:'.$id.'不存在', 404);
            $menuConfig['meta'] = json_decode($menuConfig['meta'] ?? '{}', true);
            return $this->getTableDoc($menuConfig);
        }
        // 拼接完整路径
        $fullUrl = $url;
        if ($id) $fullUrl .= '/' . $id;
        $url = $fullUrl;
        if($url == false || $url == "" || $url == "null" || $url == 'undefined' || strlen($url) < 6){
            $url = App::runtimePath().'/apidoc/'.$app.'.json';
        }
        if(strpos($url, 'http') !== 0 && file_exists($url) == false){
            App::error()->setError($url.'文件无法访问', 404);
        }
        try {
            return json_decode(file_get_contents($url), true);
        }catch (\Throwable $exception){
            App::error()->setError($exception->getMessage(), 405);
        }
    }

    /**
     * 获取菜单列表
     * @param Request $request
     * @param Response $response
     * @return array
     */
    public function get(Request $request, Response $response){
        // 返回完整 OpenAPI JSON 内容，前端自行渲染菜单和文档
        return $request->getData('api.doc');
    }

    /**
     * 认证
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    public function authorization(Request $request, ?Response $response = null)
    {
        $configured = (string)$this->password;
        // 1. 标准 Basic Auth
        $authCode = $request->header('authorization', "");
        if($authCode){
            $arr = explode(" ", $authCode);
            if(isset($arr[1])){
                $accPass = explode(":", (string)base64_decode($arr[1]), 2);
                $password = $accPass[1] ?? '';
                if(hash_equals($configured, (string)$password)){
                    if($response){
                        $response->rawResponse()->setCookie("APP-AUTH", $arr[1], 0, '/');
                    }
                    return true;
                }
            }
        }
        // 2. 兼容 apis.html 等页面传递的 auth 请求头
        $altAuth = (string)$request->header('auth', "");
        if($altAuth !== '' && hash_equals($configured, $altAuth)){
            return true;
        }
        // 3. 已有有效 cookie 会话：校验 Basic Auth 成功时下发的 APP-AUTH（base64 的 account:password）
        $authCookie = (string)($request->rawRequest()->cookie["APP-AUTH"] ?? '');
        if($authCookie !== ''){
            $accPass = explode(":", (string)base64_decode($authCookie), 2);
            $password = $accPass[1] ?? '';
            if(hash_equals($configured, (string)$password)){
                return true;
            }
        }
        return false;
    }

    /**
     * 调用
     * @param Request $request
     * @param Response $response
     * @return array
     * @throws \Exception
     */
    public function call(Request $request, Response $response){
        $apiUrl = urldecode((string)$request->getData('url', ''));
        $apiId = urldecode((string)$request->getData('id', ''));
        // table 文档请求走 admin token 鉴权，跳过 Basic Auth
        $isTableDoc = (($apiUrl === 'table' || $apiUrl === 'treetable' || $apiUrl === 'config') && $apiId);
        if (!$isTableDoc) {
            if($this->authorization($request, $response) == false){
                $response->setcode(401);
                $response->rawResponse()->header("WWW-Authenticate", 'Basic realm="My Realm"');
                return "";
            }
        }
        $this->app = $request->getData('_app');
        $apiDoc = $this->getApiDoc($this->app, $apiUrl, $apiId);
        $request->setData('api.doc', $apiDoc);
        $method = strtolower($request->method());
        try{
            $data = $this->$method($request, $response);
        }catch (\Throwable $err){
            $data = $this->$method($request, $response);
        }

        return [
            'code' => 0,
            'msg' => 'ok',
            'data' => $data,
        ];
    }

    public function success(Request $request, Response $response){
        $data = $request->validateRule([
            'uri' => 'required',
            'method' => 'required',
            'body' => 'required',
        ])->getData();

        $uris = explode('/', $data['uri']);
        if(count($uris) > 2){
            $data['uri'] = '/'.$uris[1].'/'.$uris[2];
        }

            App::make(Dispatch::class)->routes()[$this->app][$uris[1]]['/'.$uris[2]][$data['method']] ?? App::error()->setError($request->param('uri').'路由未注册', Error::NOT_FOUND);

        $filePath = App::runtimePath().DIRECTORY_SEPARATOR.'apidoc'.DIRECTORY_SEPARATOR.$this->app.DIRECTORY_SEPARATOR.'debug'.$data['uri'].'.'.$data['method'];
        if(is_array($data['body'])){
            $data['body'] = json_encode($data['body'], JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE);
        }
        Helper::mkdirs(dirname($filePath));
        file_put_contents($filePath, $data['body']);
        return [];
    }

    public function failure(Request $request, Response $response){
        return [];
    }

    /**
     * 将扁平的 dot-notation 参数数组转为树形结构（供 getTableDoc 使用）
     * 例: ['data.id', 'data.list'] → [{name:'data', children:[{name:'id'}, {name:'list'}]}]
     */
    public static function buildParamTree(array $flat): array
    {
        $tree = [];
        foreach ($flat as $item) {
            $parts = explode('.', $item['name']);
            if (count($parts) === 1) {
                $tree[] = $item;
                continue;
            }
            $tree = self::buildParamTreeInsert($tree, $parts, $item, true);
        }
        return $tree;
    }

    /**
     * 递归把单个 dot-notation 参数插入树（供 buildParamTree 使用）
     */
    private static function buildParamTreeInsert(array $nodes, array $parts, array $item, bool $topLevel): array
    {
        $part = array_shift($parts);
        $isLeaf = empty($parts);
        $found = false;
        foreach ($nodes as $k => $existing) {
            if (($existing['name'] ?? '') === $part) {
                if ($isLeaf) {
                    $nodes[$k] = array_merge($existing, $item, ['name' => $part]);
                } else {
                    if (!isset($existing['children'])) $existing['children'] = [];
                    $nodes[$k]['children'] = self::buildParamTreeInsert($existing['children'], $parts, $item, false);
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            if ($isLeaf) {
                $nodes[] = array_merge($item, ['name' => $part]);
            } else {
                $node = [
                    'name' => $part, 'type' => 'object', 'required' => '否',
                    'default' => '', 'description' => '', 'children' => [],
                ];
                if ($topLevel) {
                    $node['description'] = $item['description'] ?? '';
                    $node['required'] = $item['required'] ?? '否';
                    $node['default'] = $item['default'] ?? '';
                    $node['type'] = $item['type'] ?? 'object';
                }
                $node['children'] = self::buildParamTreeInsert([], $parts, $item, false);
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    /**
     * 为复杂请求参数补充子字段说明
     */
    protected function enrichRequestParams(array $params, string $keyField): array
    {
        $enriched = [];
        foreach ($params as $item) {
            $name = $item['name'] ?? '';
            if ($name === 'search') {
                $item['children'] = [
                    ['name' => 'and', 'type' => 'array', 'required' => '否', 'default' => '[]', 'description' => 'AND 条件组，格式：[[field, op, val], ...]'],
                    ['name' => 'or',  'type' => 'array', 'required' => '否', 'default' => '[]', 'description' => 'OR 条件组，格式：[[field, op, val], ...]'],
                ];
            } elseif ($name === 'order') {
                $item['children'] = [
                    ['name' => '[0]', 'type' => 'array', 'required' => '否', 'default' => '', 'description' => '排序项，如 ["sortrank", "asc"]'],
                    ['name' => '[1]', 'type' => 'array', 'required' => '否', 'default' => '', 'description' => '更多排序项'],
                ];
            } elseif ($name === '[0]') {
                $item['children'] = [
                    ['name' => $keyField, 'type' => 'string', 'required' => '是(编辑)/否(新增)', 'default' => '', 'description' => '主键字段（新增时自增可省略）'],
                    ['name' => '{field}', 'type' => 'string', 'required' => '否', 'default' => '', 'description' => '各业务字段（字段名由 schema 定义）'],
                ];
            } elseif ($name === '[1..N]') {
                $item['children'] = [
                    ['name' => '{field}', 'type' => 'mixed', 'required' => '否', 'default' => '', 'description' => '多表关联时的额外步骤字段'],
                ];
            }
            $enriched[] = $item;
        }
        return $enriched;
    }

    /**
     * 根据菜单配置生成 CRUD 表格的完整 API 文档
     * @param array $menuConfig Finder 的 config 数组（含 id, title, meta）
     * @return array ['d' => [8个API方法的文档]]
     */
    /**
     * 根据菜单配置生成 API 文档
     * 优先从 meta.api_class → 约定命名空间自动发现 → fallback 硬编码默认值
     */
    public function getTableDoc(array $menuConfig): array
    {
        $menuId   = $menuConfig['id'] ?? '';
        $title    = $menuConfig['title'] ?? '';
        $target   = $menuConfig['target'] ?? 'table';
        $keyField = ($menuConfig['meta']['key_field'] ?? 'id') ?: 'id';
        $baseUri  = ($target === 'treetable' ? '/v1/index.treetable/' : '/v1/index.table/') . $menuId;

        // 1. 优先使用 meta.api_class 显式配置
        $apiClass = $menuConfig['meta']['api_class'] ?? '';
        // 2. 未配置时，按约定命名空间自动发现（与 BaseService::getFinder 同逻辑）
        if (!$apiClass || !class_exists($apiClass)) {
            $apiClass = $this->resolveFinderClass($menuId);
        }
        // 3. 反射提取 @api 注解生成文档
        if ($apiClass && class_exists($apiClass)) {
            $docs = $this->buildFromAnnotations($apiClass, $menuId, $title, $keyField, $baseUri);
            if (!empty($docs)) return ['d' => $docs];
        }
        // 4. 最终回退到硬编码默认 CRUD 方法
        return $this->buildTableDefault($menuId, $title, $keyField, $baseUri);
    }

    /**
     * 按约定命名空间自动发现 finder 类
     * 检查 finder/{MenuId}.php 文件是否存在，与 BaseService::getFinder() 逻辑一致
     */
    protected function resolveFinderClass(string $menuId): string
    {
        $app = $this->app ?: 'admin';
        $versions = ['v1', 'v2'];
        foreach ($versions as $ver) {
            $class = "\\app\\api\\{$app}\\{$ver}\\service\\finder\\{$menuId}";
            if (class_exists($class)) {
                return $class;
            }
        }
        return '';
    }

    /**
     * 反射扫描类中带 @api 的 public 方法，从注释提取文档
     */
    protected function buildFromAnnotations(string $className, string $menuId, string $menuTitle, string $keyField, string $baseUri): array
    {
        try {
            $ref = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            return [];
        }
        $docs = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $comment = $method->getDocComment();
            if ($comment === false || strpos($comment, '@api') === false) continue;
            $doc = $this->parseAnnotation($comment, $baseUri, $menuTitle);
            if ($doc) $docs[] = $doc;
        }
        foreach ($docs as $k => $entry) {
            if (!empty($entry['success'])) $docs[$k]['success'] = self::buildParamTree($entry['success']);
            if (!empty($entry['param']))   $docs[$k]['param']   = $this->enrichRequestParams($entry['param'], $keyField);
        }
        return $docs;
    }

    /**
     * 借助 Annotations 工具类解析方法注释
     */
    protected function parseAnnotation(string $comment, string $baseUri, string $menuTitle): array
    {
        $ann = new Annotations($comment);
        $a   = $ann->all();

        $headers = $a['apiHeader'] ?? [];
        if (empty($headers)) {
            $headers = [
                ['name'=>'token','type'=>'string','required'=>'是','default'=>'','description'=>'用户登录凭证'],
                ['name'=>'app-name','type'=>'string','required'=>'是','default'=>'','description'=>'应用ID'],
                ['name'=>'app-time','type'=>'string','required'=>'是','default'=>'','description'=>'时间戳'],
                ['name'=>'app-sign','type'=>'string','required'=>'是','default'=>'','description'=>'签名'],
            ];
        }
        $success = $a['apiSuccess'] ?? [];
        if (empty($success)) {
            $success = [
                ['name'=>'code','type'=>'int','default'=>'0','description'=>'状态码'],
                ['name'=>'msg','type'=>'string','default'=>'success','description'=>'提示信息'],
                ['name'=>'data','type'=>'object','default'=>'{}','description'=>'响应数据'],
            ];
        }

        // Annotations::required 返回 'true'/'false' → 统一处理
        $fixBool = function(array &$arr) use (&$fixBool) {
            foreach ($arr as $k => $v) {
                if (($v['required'] ?? '') === 'true')  $arr[$k]['required'] = '是';
                if (($v['required'] ?? '') === 'false') $arr[$k]['required'] = '否';
                if (($v['default'] ?? null) === null)   $arr[$k]['default']  = '';
                if (!empty($v['children'])) $fixBool($arr[$k]['children']);
            }
        };
        $fixBool($headers);
        $params = $a['apiParam'] ?? [];
        $fixBool($params);
        $fixBool($success);

        $method = $a['api']['method'] ?? 'POST';
        $uri    = $a['api']['uri'] ?? '';
        // 没有有效路径时回退到菜单 baseUri
        if ($uri === '' || $uri === '无' || $uri === '/' || strpos($uri, '/v1/') === false) $uri = $baseUri;

        $apiTitle = $a['api']['title'] ?? '';
        $docTitle = $apiTitle ? ($menuTitle . ' - ' . $apiTitle) : $menuTitle;

        return [
            'method'      => strtoupper($method),
            'uri'         => $uri,
            'title'       => $docTitle,
            'description' => $a['apiDescription'] ?? $apiTitle,
            'header'      => $headers,
            'param'       => $params,
            'success'     => $success,
            'error'       => $a['apiError'] ?? [],
            'response'    => (object)[],
            'detail'      => '',
            'versions'    => [],
        ];
    }

    protected function buildTableDefault(string $menuId, string $title, string $keyField, string $baseUri): array
    {
        $h = [
            ['name'=>'token','type'=>'string','required'=>'是','default'=>'','description'=>'用户登录凭证（AES加密）'],
            ['name'=>'app-name','type'=>'string','required'=>'是','default'=>'','description'=>'当前应用ID'],
            ['name'=>'app-time','type'=>'string','required'=>'是','default'=>'','description'=>'请求时间戳（Unix秒级）'],
            ['name'=>'app-sign','type'=>'string','required'=>'是','default'=>'','description'=>'HMAC-SHA1签名→Base64'],
        ];
        $s = [
            ['name'=>'code','type'=>'int','default'=>'0','description'=>'状态码，0 表示成功'],
            ['name'=>'msg','type'=>'string','default'=>'success','description'=>'提示信息'],
            ['name'=>'data','type'=>'object','default'=>'{}','description'=>'响应数据'],
        ];
        $docs = [
            ['method'=>'POST','uri'=>$baseUri,'title'=>$title.' - 元数据','description'=>'获取表格结构信息：tableConfig、columns、meta','header'=>$h,'param'=>[['name'=>'_method','type'=>'string','required'=>'是','default'=>'meta','description'=>'方法名，固定为 meta']],'success'=>array_merge($s,[['name'=>'data.id','type'=>'string','default'=>$menuId,'description'=>'菜单ID'],['name'=>'data.title','type'=>'string','default'=>$title,'description'=>'菜单标题'],['name'=>'data.meta','type'=>'object','default'=>'','description'=>'菜单元数据'],['name'=>'data.config','type'=>'object','default'=>'','description'=>'表格配置'],['name'=>'data.columns','type'=>'object','default'=>'','description'=>'列定义']]),'error'=>[['code'=>'404','description'=>'菜单不存在']],'response'=>['code'=>0,'msg'=>'success','data'=>['id'=>$menuId,'title'=>$title,'meta'=>[],'config'=>[],'columns'=>[]]],'detail'=>''],
            ['method'=>'GET','uri'=>$baseUri,'title'=>$title.' - 数据列表','description'=>'分页查询数据，支持排序、AND/OR搜索','header'=>$h,'param'=>[['name'=>'page','type'=>'int','required'=>'否','default'=>'1','description'=>'页码'],['name'=>'limit','type'=>'int','required'=>'否','default'=>'10','description'=>'每页条数'],['name'=>'order','type'=>'array','required'=>'否','default'=>'[]','description'=>'排序'],['name'=>'search','type'=>'object','required'=>'否','default'=>'{}','description'=>'搜索条件'],['name'=>'where','type'=>'array','required'=>'否','default'=>'[]','description'=>'额外条件']],'success'=>array_merge($s,[['name'=>'data.list','type'=>'array','default'=>'[]','description'=>'数据记录'],['name'=>'data.total','type'=>'int','default'=>'0','description'=>'总记录数']]),'error'=>[['code'=>'404','description'=>'未配置关联表名']],'response'=>['code'=>0,'msg'=>'success','data'=>['list'=>[],'total'=>0]],'detail'=>''],
            ['method'=>'POST','uri'=>$baseUri,'title'=>$title.' - 添加页面','description'=>'获取新增表单结构（多步骤，多表关联）','header'=>$h,'param'=>[['name'=>'_method','type'=>'string','required'=>'是','default'=>'createFrom','description'=>'方法名']],'success'=>array_merge($s,[['name'=>'data[].title','type'=>'string','default'=>'','description'=>'表注释标题'],['name'=>'data[].table_name','type'=>'string','default'=>'','description'=>'关联表名'],['name'=>'data[].step','type'=>'int','default'=>'0','description'=>'步骤序号'],['name'=>'data[].schema.{field}','type'=>'object','default'=>'','description'=>'字段定义']]),'error'=>[['code'=>'403','description'=>'菜单不存在']],'response'=>[],'detail'=>''],
            ['method'=>'POST','uri'=>$baseUri,'title'=>$title.' - 编辑页面','description'=>'获取编辑表单（填充当前值）','header'=>$h,'param'=>[['name'=>'_method','type'=>'string','required'=>'是','default'=>'editFrom','description'=>'方法名'],['name'=>$keyField,'type'=>'string','required'=>'是','default'=>'','description'=>'主键值']],'success'=>array_merge($s,[['name'=>'data[].schema.{field}.default','type'=>'mixed','default'=>'','description'=>'当前值']]),'error'=>[['code'=>'403','description'=>'缺少主键'],['code'=>'404','description'=>'菜单不存在']],'response'=>[],'detail'=>''],
            ['method'=>'POST','uri'=>$baseUri,'title'=>$title.' - 添加处理','description'=>'新增数据（JSON数组，事务）','header'=>$h,'param'=>[['name'=>'[0]','type'=>'object','required'=>'是','default'=>'','description'=>'第一步数据'],['name'=>'[1..N]','type'=>'object','required'=>'否','default'=>'','description'=>'第N步数据']],'success'=>array_merge($s,[]),'error'=>[['code'=>'403','description'=>'分步骤表不存在'],['code'=>'403','description'=>'无写权限'],['code'=>'400','description'=>'字段验证失败']],'response'=>['code'=>0,'msg'=>'新增成功','data'=>[]],'detail'=>'<p style="color:#666;font-size:13px;line-height:2em;">⚠ 注意：<br>1. 请求体为 JSON 数组<br>2. 第二步起自动继承第一步的主键值<br>3. 数组字段自动 JSON 序列化</p>'],
            ['method'=>'POST','uri'=>$baseUri,'title'=>$title.' - 编辑处理','description'=>'更新数据（JSON数组，含主键）','header'=>$h,'param'=>[['name'=>'[0]','type'=>'object','required'=>'是','default'=>'','description'=>'第一步更新数据（必含'.$keyField.'）'],['name'=>'[1..N]','type'=>'object','required'=>'否','default'=>'','description'=>'第N步数据']],'success'=>array_merge($s,[['name'=>'data.list','type'=>'array','default'=>'[]','description'=>'更新后记录']]),'error'=>[['code'=>'403','description'=>'未配置关系表'],['code'=>'403','description'=>'无写权限'],['code'=>'400','description'=>'字段验证失败']],'response'=>['code'=>0,'msg'=>'编辑成功','data'=>['list'=>[]]],'detail'=>'<p style="color:#666;font-size:13px;line-height:2em;">⚠ 注意：<br>1. 请求体为 JSON 数组<br>2. 每步数据必须包含主键字段（'.$keyField.'）<br>3. create_time/delete_time 自动忽略，update_time 自动更新</p>'],
            ['method'=>'POST','uri'=>$baseUri,'title'=>$title.' - 批量删除','description'=>'批量删除（WHERE IN，事务级联）','header'=>$h,'param'=>[['name'=>'_method','type'=>'string','required'=>'是','default'=>'deletes','description'=>'方法名'],['name'=>$keyField,'type'=>'array','required'=>'是','default'=>'','description'=>'主键值数组']],'success'=>array_merge($s,[]),'error'=>[['code'=>'403','description'=>'未配置关系表'],['code'=>'405','description'=>'主键不能为空']],'response'=>['code'=>0,'msg'=>'批量删除成功','data'=>[]],'detail'=>'<p style="color:#666;font-size:13px;line-height:2em;">⚠ 注意：<br>1. 字段名由 meta.key_field 决定<br>2. 格式：'.$keyField.'[]=val1&'.$keyField.'[]=val2</p>'],
            ['method'=>'POST','uri'=>$baseUri,'title'=>$title.' - 单条删除','description'=>'删除单条记录（事务级联）','header'=>$h,'param'=>[['name'=>'_method','type'=>'string','required'=>'是','default'=>'delete','description'=>'方法名'],['name'=>$keyField,'type'=>'string','required'=>'是','default'=>'','description'=>'主键值']],'success'=>array_merge($s,[]),'error'=>[['code'=>'403','description'=>'未配置关系表'],['code'=>'405','description'=>'主键不能为空']],'response'=>['code'=>0,'msg'=>'删除成功','data'=>[]],'detail'=>'<p style="color:#666;font-size:13px;line-height:2em;">⚠ 注意：<br>1. 字段名由 meta.key_field 决定<br>2. 与批量删除共用底层实现</p>'],
        ];
        foreach ($docs as $k => $doc) {
            if (!empty($doc['success'])) $docs[$k]['success'] = self::buildParamTree($doc['success']);
            if (!empty($doc['param']))   $docs[$k]['param']   = $this->enrichRequestParams($doc['param'], $keyField);
        }
        return ['d' => $docs];
    }

    public function __call(string $name, array $arguments)
    {
        App::error()->setError($name.'不存在', 404);
    }
}
