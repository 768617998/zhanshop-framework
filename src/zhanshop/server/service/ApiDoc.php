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
        if ($url === 'table' && $id) {
            $menuConfig = App::database()->model("system_menu")->where(['id' => $id])->field('id,title,meta')->find();
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
        // 1. 标准 Basic Auth
        $authCode = $request->header('authorization', "");
        if($authCode){
            $arr = explode(" ", $authCode);
            if(isset($arr[1])){
                $accPass = base64_decode($arr[1]);
                $accPass = explode(":", $accPass);
                $account = $accPass[0];
                $password = $accPass[1] ?? '';
                if($password == $this->password){
                    if($response){
                        $response->rawResponse()->setCookie("APP-AUTH", $arr[1], 0, '/');
                    }
                    return true;
                }
            }
        }
        // 2. 兼容 apis.html 等页面传递的 auth 请求头
        $altAuth = $request->header('auth', "");
        if($altAuth && $altAuth == $this->password){
            return true;
        }
        // 3. 已有有效 cookie 会话 (之前 Basic Auth 成功过)
        $cookieName = $request->rawRequest()->cookie["APP-NAME"] ?? "";
        if($cookieName && $this->password){
            return true;
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
        $isTableDoc = ($apiUrl === 'table' && $apiId);
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

    public function success(Request &$request, Response &$response){
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

    public function failure(Request &$request, Response &$response){
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
            $current = &$tree;
            foreach ($parts as $i => $part) {
                $isLeaf = ($i === count($parts) - 1);
                $found = false;
                foreach ($current as $k => &$existing) {
                    if (($existing['name'] ?? '') === $part) {
                        if ($isLeaf) {
                            $existing = array_merge($existing, $item, ['name' => $part]);
                        } else {
                            if (!isset($existing['children'])) $existing['children'] = [];
                            $current = &$existing['children'];
                        }
                        $found = true;
                        break;
                    }
                }
                unset($existing);
                if (!$found) {
                    if ($isLeaf) {
                        $current[] = array_merge($item, ['name' => $part]);
                    } else {
                        $node = [
                            'name' => $part, 'type' => 'object', 'required' => '否',
                            'default' => '', 'description' => '', 'children' => [],
                        ];
                        if ($i === 0) {
                            $node['description'] = $item['description'] ?? '';
                            $node['required'] = $item['required'] ?? '否';
                            $node['default'] = $item['default'] ?? '';
                            $node['type'] = $item['type'] ?? 'object';
                        }
                        $current[] = $node;
                        $current = &$current[count($current) - 1]['children'];
                    }
                }
            }
        }
        return $tree;
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
    public function getTableDoc(array $menuConfig): array
    {
        $menuId = $menuConfig['id'] ?? '';
        $title = $menuConfig['title'] ?? '';
        $keyField = ($menuConfig['meta']['key_field'] ?? 'id') ?: 'id';
        $baseUri = '/v1/index.table/' . $menuId;

        $commonHeaders = [
            ['name' => 'token',    'type' => 'string', 'required' => '是', 'default' => '', 'description' => '用户登录凭证（AES加密的用户信息）'],
            ['name' => 'app-name', 'type' => 'string', 'required' => '是', 'default' => '', 'description' => '当前应用ID（ASCII，如 shop）'],
            ['name' => 'app-time', 'type' => 'string', 'required' => '是', 'default' => '', 'description' => '请求时间戳（Unix秒级）'],
            ['name' => 'app-sign', 'type' => 'string', 'required' => '是', 'default' => '', 'description' => 'HMAC-SHA1签名 → Base64编码'],
        ];

        $commonSuccess = [
            ['name' => 'code', 'type' => 'int',    'default' => '0',       'description' => '状态码，0 表示成功'],
            ['name' => 'msg',  'type' => 'string', 'default' => 'success', 'description' => '提示信息'],
            ['name' => 'data', 'type' => 'object', 'default' => '{}',      'description' => '响应数据'],
        ];

        $docs = [
            [
                'method' => 'POST',
                'uri' => $baseUri,
                'title' => $title . ' - 元数据',
                'description' => '获取表格的完整结构信息：表格配置（tableConfig）、列定义（columns）、菜单元数据（meta）。前端以此渲染表格界面。',
                'header' => $commonHeaders,
                'param' => [
                    ['name' => '_method', 'type' => 'string', 'required' => '是', 'default' => 'meta', 'description' => '方法名，固定为 meta'],
                ],
                'success' => array_merge($commonSuccess, [
                    ['name' => 'data.id',      'type' => 'string', 'default' => $menuId, 'description' => '菜单ID'],
                    ['name' => 'data.title',   'type' => 'string', 'default' => $title,  'description' => '菜单标题'],
                    ['name' => 'data.meta',    'type' => 'object', 'default' => '', 'description' => '菜单元数据（table_names, key_field, label_field 等）'],
                    ['name' => 'data.config',  'type' => 'object', 'default' => '', 'description' => '表格配置（width, height, skin, limits, page, headToolbar, rowToolbar 等）'],
                    ['name' => 'data.columns', 'type' => 'object', 'default' => '', 'description' => '列定义（字段名 → {field, type, title, search, value, input_type 等}）'],
                ]),
                'error' => [['code' => '404', 'description' => '菜单不存在']],
                'response' => ['code' => 0, 'msg' => 'success', 'data' => ['id' => $menuId, 'title' => $title, 'meta' => ['table_names' => ['db.table'], 'key_field' => 'id', 'label_field' => 'name'], 'config' => ['width' => 'auto', 'height' => 'full-150'], 'columns' => []]],
                'detail' => '',
            ],
            [
                'method' => 'GET',
                'uri' => $baseUri,
                'title' => $title . ' - 数据列表',
                'description' => '分页查询数据，支持多字段排序、AND/OR 组合条件搜索。搜索 op 支持 =、!=、>=、<=、between、like。',
                'header' => $commonHeaders,
                'param' => [
                    ['name' => 'page',  'type' => 'int',    'required' => '否', 'default' => '1',  'description' => '页码'],
                    ['name' => 'limit', 'type' => 'int',    'required' => '否', 'default' => '10', 'description' => '每页条数'],
                    ['name' => 'order', 'type' => 'array',  'required' => '否', 'default' => '[]', 'description' => '排序，例：[["sortrank","asc"],["id","desc"]]'],
                    ['name' => 'search','type' => 'object', 'required' => '否', 'default' => '{}', 'description' => '搜索条件，格式：{"and":[["field","=","val"]],"or":[["name","like","keyword"]]}'],
                    ['name' => 'where', 'type' => 'array',  'required' => '否', 'default' => '[]', 'description' => '额外 where 条件'],
                ],
                'success' => array_merge($commonSuccess, [
                    ['name' => 'data.list',  'type' => 'array', 'default' => '[]', 'description' => '数据记录列表'],
                    ['name' => 'data.total', 'type' => 'int',   'default' => '0',  'description' => '符合条件的总记录数'],
                ]),
                'error' => [['code' => '404', 'description' => '未配置关联表名']],
                'response' => ['code' => 0, 'msg' => 'success', 'data' => ['list' => [['id' => '1', 'name' => '示例']], 'total' => 1]],
                'detail' => '',
            ],
            [
                'method' => 'POST',
                'uri' => $baseUri,
                'title' => $title . ' - 添加页面',
                'description' => '获取新增表单的完整结构（多步骤，支持多表关联）。自动解析 value_menu 加载联动选项，自增主键自动隐藏。',
                'header' => $commonHeaders,
                'param' => [
                    ['name' => '_method', 'type' => 'string', 'required' => '是', 'default' => 'createFrom', 'description' => '方法名，固定为 createFrom'],
                ],
                'success' => array_merge($commonSuccess, [
                    ['name' => 'data[].title',      'type' => 'string', 'default' => '', 'description' => '表注释标题'],
                    ['name' => 'data[].table_name', 'type' => 'string', 'default' => '', 'description' => '关联表名（格式：数据库名.表名）'],
                    ['name' => 'data[].step',       'type' => 'int',    'default' => '0', 'description' => '步骤序号'],
                    ['name' => 'data[].schema.{field}', 'type' => 'object', 'default' => '', 'description' => '字段定义（field, title, input_type, default, value, in_field 等）'],
                ]),
                'error' => [['code' => '403', 'description' => '菜单不存在']],
                'response' => [],
                'detail' => '',
            ],
            [
                'method' => 'POST',
                'uri' => $baseUri,
                'title' => $title . ' - 编辑页面',
                'description' => '根据主键获取当前记录并返回编辑表单。主键字段自动转 hidden，default 填充数据库当前值。',
                'header' => $commonHeaders,
                'param' => [
                    ['name' => '_method', 'type' => 'string', 'required' => '是', 'default' => 'editFrom', 'description' => '方法名，固定为 editFrom'],
                    ['name' => $keyField, 'type' => 'string', 'required' => '是', 'default' => '', 'description' => '主键值（字段名由 meta.key_field 决定，通常为 id）'],
                ],
                'success' => array_merge($commonSuccess, [
                    ['name' => 'data[].schema.{field}.default', 'type' => 'mixed', 'default' => '', 'description' => '当前记录该字段的值'],
                ]),
                'error' => [
                    ['code' => '403', 'description' => '缺少主键ID'],
                    ['code' => '404', 'description' => '菜单不存在'],
                ],
                'response' => [],
                'detail' => '',
            ],
            [
                'method' => 'POST',
                'uri' => $baseUri,
                'title' => $title . ' - 添加处理',
                'description' => '新增数据。请求体为 JSON 数组（非对象），按步骤索引，事务保证原子性。第一步返回的主键自动传入后续步骤。',
                'header' => $commonHeaders,
                'param' => [
                    ['name' => '[0]',    'type' => 'object', 'required' => '是', 'default' => '', 'description' => '第一步数据（键值对，key 为字段名）'],
                    ['name' => '[1..N]', 'type' => 'object', 'required' => '否', 'default' => '', 'description' => '第N步数据（多表关联时）'],
                ],
                'success' => array_merge($commonSuccess, []),
                'error' => [
                    ['code' => '403', 'description' => '分步骤对应的表不存在'],
                    ['code' => '403', 'description' => '菜单无写权限'],
                    ['code' => '400', 'description' => '字段验证失败'],
                ],
                'response' => ['code' => 0, 'msg' => '新增成功', 'data' => []],
                'detail' => '<p style="color:#666;font-size:13px;line-height:2em;">⚠ 注意：<br>1. 请求体为 JSON 数组（非 JSON 对象），索引0=第一步、索引1=第二步<br>2. 第二步及之后的数据自动继承第一步返回的主键值<br>3. 数组类型的字段值自动 JSON 序列化存储</p>',
            ],
            [
                'method' => 'POST',
                'uri' => $baseUri,
                'title' => $title . ' - 编辑处理',
                'description' => '更新数据。请求体为 JSON 数组，每个步骤的数据必须包含主键字段。自动忽略 create_time/delete_time，更新 update_time。',
                'header' => $commonHeaders,
                'param' => [
                    ['name' => '[0]',    'type' => 'object', 'required' => '是', 'default' => '', 'description' => '第一步更新数据（必含 ' . $keyField . ' 主键字段）'],
                    ['name' => '[1..N]', 'type' => 'object', 'required' => '否', 'default' => '', 'description' => '第N步更新数据（多表关联时）'],
                ],
                'success' => array_merge($commonSuccess, [
                    ['name' => 'data.list', 'type' => 'array', 'default' => '[]', 'description' => '更新后的记录列表'],
                ]),
                'error' => [
                    ['code' => '403', 'description' => '未配置菜单关系表'],
                    ['code' => '403', 'description' => '菜单无写权限'],
                    ['code' => '400', 'description' => '字段验证失败'],
                ],
                'response' => ['code' => 0, 'msg' => '编辑成功', 'data' => ['list' => [['id' => '1', 'name' => '更新后的记录']]]],
                'detail' => '<p style="color:#666;font-size:13px;line-height:2em;">⚠ 注意：<br>1. 请求体为 JSON 数组<br>2. 每个步骤的数据必须包含主键字段（' . $keyField . '）<br>3. create_time 和 delete_time 会被自动忽略，update_time 自动更新</p>',
            ],
            [
                'method' => 'POST',
                'uri' => $baseUri,
                'title' => $title . ' - 批量删除',
                'description' => '批量删除。传入主键值数组，使用 WHERE IN 删除。事务保证所有关联表级联删除。',
                'header' => $commonHeaders,
                'param' => [
                    ['name' => '_method',  'type' => 'string', 'required' => '是', 'default' => 'deletes', 'description' => '方法名，固定为 deletes'],
                    ['name' => $keyField,  'type' => 'array',  'required' => '是', 'default' => '', 'description' => '主键值数组，例：["id1","id2","id3"]'],
                ],
                'success' => array_merge($commonSuccess, []),
                'error' => [
                    ['code' => '403', 'description' => '未配置菜单关系表'],
                    ['code' => '405', 'description' => '主键不能为空'],
                ],
                'response' => ['code' => 0, 'msg' => '批量删除成功', 'data' => []],
                'detail' => '<p style="color:#666;font-size:13px;line-height:2em;">⚠ 注意：<br>1. 字段名由 meta.key_field 决定<br>2. 参数值格式：' . $keyField . '[]=val1&' . $keyField . '[]=val2<br>3. 所有关联表级联删除</p>',
            ],
            [
                'method' => 'POST',
                'uri' => $baseUri,
                'title' => $title . ' - 单条删除',
                'description' => '删除单条记录。传入单个主键值，事务保证所有关联表级联删除。',
                'header' => $commonHeaders,
                'param' => [
                    ['name' => '_method',  'type' => 'string', 'required' => '是', 'default' => 'delete', 'description' => '方法名，固定为 delete'],
                    ['name' => $keyField,  'type' => 'string', 'required' => '是', 'default' => '', 'description' => '主键值（字段名由 meta.key_field 决定）'],
                ],
                'success' => array_merge($commonSuccess, []),
                'error' => [
                    ['code' => '403', 'description' => '未配置菜单关系表'],
                    ['code' => '405', 'description' => '主键不能为空'],
                ],
                'response' => ['code' => 0, 'msg' => '删除成功', 'data' => []],
                'detail' => '<p style="color:#666;font-size:13px;line-height:2em;">⚠ 注意：<br>1. 字段名由 meta.key_field 决定<br>2. 传单个 ID 即删单条，与批量删除共用底层实现<br>3. 所有关联表级联删除</p>',
            ],
        ];

        // 后处理：响应参数转树形，请求参数补子字段
        foreach ($docs as &$doc) {
            if (!empty($doc['success'])) {
                $doc['success'] = self::buildParamTree($doc['success']);
            }
            if (!empty($doc['param'])) {
                $doc['param'] = $this->enrichRequestParams($doc['param'], $keyField);
            }
        }
        unset($doc);

        return ['d' => $docs];
    }

    public function __call(string $name, array $arguments)
    {
        App::error()->setError($name.'不存在', 404);
    }
}