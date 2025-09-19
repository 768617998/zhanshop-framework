<?php

namespace zhanshop;

use zhanshop\client\Httpclient;

class Elasticsearch
{
    protected $url;
    protected $user;
    protected $pass;
    protected $timeout = 3000;
    protected $retry = 0;
    protected $options = [];
    public function __construct()
    {
        $config = App::config()->get('elasticsearch');
        $this->url = $config['scheme'].'://'.$config['host'].':'.$config['port'];
        $this->user = $config['user'];
        $this->pass = $config['pass'];
        $this->retry = intval($config['retry']);
        if(isset($config['timeout']) && is_int($config['timeout'])) $this->timeout = $config['timeout'];
    }

    /**
     * 使用http代理
     * @param string $ip 代理IP
     * @param int $port 代理端口
     * @param string $user 代理用户
     * @param string $password 代理密码
     * @return $this
     */
    public function httpproxy(string $ip, int $port, string $user = "", string $password = "")
    {
        $ip = $this->getDnsHostIp($ip);
        $this->options['proxy'][] = [
            'type' => 1,
            'host' => $ip,
            'port' => $port,
            'user' => $user,
            'password' => $password
        ];
        return $this;
    }

    /**
     * 使用socks5代理
     * @param string $ip 代理IP
     * @param int $port 代理端口
     * @param string $user 代理用户
     * @param string $password 代理密码
     * @return $this
     */
    public function socks5(string $ip, int $port, string $user = "", string $password = "")
    {
        $this->options['proxy'][] = [
            'type' => 2,
            'host' => $ip,
            'port' => $port,
            'user' => $user,
            'password' => $password
        ];
        return $this;
    }

    /**
     * 获取所有表
     * @return string[]
     */
    public function showTables(){
        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $this->options = [];
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($this->url.'/_cat/indices?v');
        $tables = explode("\n", $result['body']);
        $titles = $tables[0];
        $titles = array_values(array_filter(explode(" ", $titles)));
        unset($tables[0]);
        $data = [];
        foreach ($tables as $k => $v){
            $vals = explode(" ", $v);
            if(count($vals) > 2){
                $vals = array_values(array_filter($vals, function ($str){
                    if ($str === "") return false;
                    return true;
                }));
                foreach ($titles as $kk => $vv){
                    $data[$k][$vv] = $vals[$kk] ?? '-';
                }
            }
        }
        return $data;
    }


    /**
     * 显示状态
     * @return string[]
     */
    public function showStatus(){
        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($this->url.'/_cat/health?v');
        $status = explode("\n", $result['body']);
        $titles = $status[0];
        $titles = array_values(array_filter(explode(" ", $titles)));
        unset($status[0]);
        $statusData = [];
        foreach ($status as $k => $v){
            $vals = explode(" ", $v);
            if(count($vals) > 2){
                $vals = array_values(array_filter($vals, function ($str){
                    if ($str === "") return false;
                    return true;
                }));
                foreach ($titles as $kk => $vv){
                    $statusData[$k][$vv] = $vals[$kk] ?? '-';
                }
            }
        }
        return $statusData;
    }

    /**
     * 清空数据表
     * @return void
     */
    public function truncate(){
        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($this->url.'/'.$this->options['index'], "DELETE");
        if($result['code'] >= 400){
            return true;
        }
        return false;
    }

    public function indexName(string $name){
        $this->options['index'] = $name;
        return $this;
    }

    /**
     * 修改索引【仅支持添加索引】
     * @param string $field
     * @param $type
     * @param array $value
     * @return true
     * @throws \Exception
     */
    public function alter(string $field, string $type, array $value = [])
    {
//            $data = [
//                'properties' => [
//                    "新字段" =>    [
//                        'type' => "text",
//                        'fields' => [
//                            'keyword' =>  [
//                                'type' => 'keyword',
//                                'ignore_above' => 256, // 索引长度过长将不会被索引
//                            ],
//                        ],
//                    ],
//                ],
//            ];
        $data = [
            'properties' => [
                $field => [
                    'type' => $type,
                ]
            ],
        ];

        foreach ($value as $k => $v){
            $data['properties'][$field][$k] = $v;
        }

        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $this->options = [];
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($this->url.'/'.$this->options['index'].'/_mapping', "PUT", json_encode($data));
        if($result['code'] >= 400){
            App::error()->setError($result['body']);
        }
        return true;
    }

    /**
     * 创建索引
     * @param array $data
     * @return array
     */
    public function createIndex(array $data){
        /**
         * {
        "settings": {
        "number_of_shards": 3,
        "number_of_replicas": 2
        },
        "mapping": {
        "_doc": {
        "properties": {
        "commodity_id": {
        "type": "long"
        },
        "commodity_name": {
        "type": "text"
        },
        "picture_url": {
        "type": "keyword"
        },
        "price": {
        "type": "double"
        }
        }
        }
        }
        }



        {
        "doctor": {
        "aliases": {},
        "mappings": {
        "properties": {
        "id": {
        "type": "long"
        },
        "name": {
        "type": "text",
        "fields": {
        "keyword": {
        "type": "keyword",
        "ignore_above": 256
        }
        }
        },
        "proficient": {
        "type": "text",
        "fields": {
        "keyword": {
        "type": "keyword",
        "ignore_above": 256
        }
        }
        }
        }
        },
        "settings": {
        "index": {
        "routing": {
        "allocation": {
        "include": {
        "_tier_preference": "data_content"
        }
        }
        },
        "number_of_shards": "1",
        "provided_name": "doctor",
        "creation_date": "1697448853800",
        "number_of_replicas": "1",
        "uuid": "fqhMEfJDS6eiLjBFt4Y5DA",
        "version": {
        "created": "8080199"
        }
        }
        }
        }
        }
         */

//        $data = [
//            'mappings' => [
//                'properties' => [
//                    'id' =>   [
//                        "type" => "long"
//                    ],
//                    'username' =>   [
//                        'type' => 'text',
//                        'fields' =>
//                            [
//                                'keyword' =>  [
//                                    'type' => 'keyword',
//                                    'ignore_above' => 256, // 索引长度过长将不会被索引
//                                ],
//                            ],
//                    ],
//                    'introduction' =>   [
//                        'type' => 'text',
//                        'fields' =>
//                            [
//                                'keyword' =>  [
//                                    'type' => 'keyword',
//                                    'ignore_above' => 256, // 索引长度过长将不会被索引
//                                ],
//                            ],
//                    ],
//                ],
//            ],
//        ];

        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($this->url.'/'.$this->options['index'], "PUT", json_encode($data));
        $this->options = [];
        return json_decode($result['body'], true);
    }

    /**
     * 插入单条
     * @param array $data
     * @param string $id
     * @return mixed
     * @throws \Exception
     */
    public function insert(array $data, string $id = ""){
        if($id == false) $id = Helper::orderId();

        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($this->url.'/'.$this->options['index'].'/_doc/'.$id.'?pretty', "POST", json_encode($data));
        $this->options = [];
        if($result['code'] >= 400){
            App::error()->setError($result['body']);
        }
        return json_decode($result['body'], true);
    }

    /**
     * 插入多条
     * @param array $data
     * @return array
     */
    public function insertAll(array $data, array $ids = []){
        $saveAll = "";
        foreach($data as $k => $v){
            $orderId = $ids[$k] ?? Helper::orderId((string)$k);
            $save = [
                'index' => [
                    '_index' => $this->options['index'],
                    '_id' => $orderId
                ],
            ];
            $saveAll .= json_encode($save)."\n";
            $saveAll .= json_encode($v)."\n";
        }
        $saveAll .= "\r\n";

        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $this->options = [];
        //var_dump($this->user.':'.$this->pass);
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($this->url.'/_bulk', "POST", $saveAll);
        $this->options = [];
        if($result['code'] >= 400){
            App::error()->setError($result['body']);
        }
        return json_decode($result['body'], true);

    }

    /**
     * 原始查询
     * @param array $arr
     * @return $this
     */
    public function whereRaw(array $arr){
        $this->options['whereRaw'][] = $arr;
        return $this;
    }

    /**
     * 条件
     * @param array $data
     * @return $this
     */
    public function where(string $field, string $condition, mixed $value){
        $this->options['where'][] = [$field, $condition, $value];
        return $this;
    }

    /**
     * 或条件
     * @param string $field
     * @param string $condition
     * @param mixed $value
     * @return $this
     */
    public function whereOr(string $field, string $condition, mixed $value){
        $this->options['whereOr'][] = [$field, $condition, $value];
        return $this;
    }

    /**
     * 仅返回指定字段
     * @param string $field
     * @return $this
     */
    public function field(string $field){
        $this->options['field'] = $field;
        return $this;
    }
    /**
     * 排序值
     * @param string $order
     * @return $this
     */
    public function order(string $order){
        $this->options['order'][] = $order;
        return $this;
    }

    protected function getRawParam(array &$params){
        if(isset($this->options['whereRaw'])){
            foreach ($this->options['whereRaw'] as $v){
                $params = array_merge($params, $v);
            }

        }
    }
    /**
     * 请求参数组合AND
     * @return array
     */
    protected function getQueryAndParam(array &$params){
        if(isset($this->options['where'])){
            foreach($this->options['where'] as $v){
                if($v[1] == 'like'){
                    $field = is_int($v[2]) ?  $v[0] : $v[0];
                    $params['bool']['must'][] = [
                        'wildcard' => [$field => '*'.$v[2].'*']
                    ];
                }else if($v[1] == '='){
                    $field = is_int($v[2]) ?  $v[0] : $v[0];
                    $params['bool']['must'][]['term'][$field] = $v[2];
                }else if($v[1] == 'in'){
                    $field = is_int($v[2][0]) ?  $v[0] : $v[0];
                    $params['bool']['must'][]['terms'][$field] = $v[2];
                }else if($v[1] == '!='){
                    $field = is_int($v[2]) ?  $v[0] : $v[0];
                    $params['bool']['must_not'][]['terms'][$field] = $v[2];
                }else if($v[1] == 'not in'){
                    $field = is_int($v[2][0]) ?  $v[0] : $v[0];
                    $params['bool']['must_not'][]['terms'][$field] = $v[2];
                }else{
                    // 大于等于 小于等于 不等于 range
                    $arr = [">=" => 'gte', '>' => 'gt', '<=' => 'lte', '<' => 'lt'];
                    $params['bool']['must'][]['range'][$v[0]][$arr[$v[1]] ?? App::error()->setError($v[1].'不支持的操作符')] = $v[2];
                }
            }
        }
    }

    /**
     * 请求参数组合OR
     * @return array
     */
    protected function getQueryORParam(array &$params){
        if(isset($this->options['whereOr'])){
            foreach($this->options['whereOr'] as $v){
                if($v[1] == 'like'){
                    $field = is_int($v[2]) ?  $v[0] : $v[0];
                    $params['bool']['should'][] = [
                        'wildcard' => [$field => '*'.$v[2].'*']
                    ];
                }else if($v[1] == '='){
                    $field = is_int($v[2]) ?  $v[0] : $v[0];
                    $params['bool']['should'][]['term'][$field] = $v[2];
                }else if($v[1] == 'in'){
                    $field = is_int($v[2][0]) ?  $v[0] : $v[0];
                    $params['bool']['should'][]['terms'][$field] = $v[2];
                }else{
                    // 大于等于 小于等于 不等于 range
                    $arr = [">=" => 'gte', '>' => 'gt', '<=' => 'lte', '<' => 'lt'];
                    $params['bool']['should'][]['range'][$v[0]][$arr[$v[1]] ?? App::error()->setError($v[1].'不支持的操作符')] = $v[2];
                }
            }
        }
    }

    /**
     * 获取排序参数
     * @return array
     */
    protected function getOrderParam(array &$params){
        if(isset($this->options['order'])){
            foreach($this->options['order'] as $v){
                $arr = explode(' ', $v);
                $params['sort'][$arr[0]] = ['order' => $arr[1]];
            }
        }
    }

    /**
     * 分页查询
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function finder(int $page = 1, int $limit = 20){
        $url = $this->url.'/'.$this->options['index'].'/_search';
        $offset = ($page - 1) * $limit;
        $page--;
        // 字符串字段不支持排序
        $params = ['query' => []];
        $params['from'] = $page;
        $params['size'] = $limit;

        $this->getQueryAndParam($params['query']);

        $this->getQueryORParam($params['query']);

        $this->getRawParam($params['query']);

        $this->getOrderParam($params);

        if(isset($this->options['field'])) $params['_source'] = explode(',', $this->options['field']);

        if($params['query'] == false) unset($params['query']);



        $data = [
            'total' => 0,
            'list' => [],
            'max_score' => 0,
            'errmsg' => '',
            'original' => [],
        ];
        try {
            $httpClient = new Httpclient();
            foreach ($this->options['proxy'] ?? [] as $v){
                if($v['type'] == 1){
                    $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
                }else if($v['type'] == 2){
                    $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
                }
            }
            $httpClient->userPwd($this->user.':'.$this->pass);
            $httpClient->setHeader('Content-Type', 'application/json');
            $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url, 'POST', json_encode($params));
            if($result['code'] >= 400){
                App::error()->setError($result['body']);
            }
            $resp = json_decode($result['body'], true);
            $data['total'] = $resp['hits']['total'];
            $data['max_score'] = $resp['hits']['max_score'];
            $data['list'] = $resp['hits']['hits'];
            unset($resp['hits']);
            $data['original'] = $resp;
        }catch (\Throwable $e){
            $data['errmsg'] = $e->getMessage();
        }
        $this->options = [];
        return $data;
    }

    /**
     * 根据ES组件id查询数据
     * @param mixed $id
     * @return mixed
     * @throws \Exception
     */
    public function get(mixed $id)
    {
        $url = $this->url.'/'.$this->options['index'].'/_doc/'.$id;
        if(isset($this->options['field']) && $this->options['field']){
            $url .= '?_source='.$this->options['field'];
        }
        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $params = [];
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url);
        if($result['code'] >= 400){
            App::error()->setError($result['body']);
        }
        $result = json_decode($result['body'], true);
        return $result;
    }

    /**
     * 执行sql
     * @param string $sql
     * @return array
     */
    public function query(string $sql){
        //POST /_sql?format=txt
        $url = $this->url.'/_sql?format=json';
        // 字符串字段不支持排序
        $params = ['query' => $sql];

        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $this->options = [];
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $ret = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url, "POST", json_encode($params));
        if($ret['code'] >= 400){
            App::error()->setError($ret['body']);
        }
        $ret = json_decode($ret['body'], true);

        $data = [];
        try {
            foreach($ret['rows'] as $vals){
                $row = [];
                foreach($vals as $k => $v){
                    $row[$ret['columns'][$k]['name']] = $v;
                }
                $data[] = $row;
            }
        }catch (\Throwable $throwable){}

        return $data;
    }

    /**
     * 查询并删除
     * @return array
     */
    public function delete(){
        $url = $this->url.'/'.$this->options['index'].'/_delete_by_query';
        $params = ['query' => []];

        $this->getQueryAndParam($params['query']);

        $this->getQueryORParam($params['query']);

        if($params['query'] == false) unset($params['query']);

        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }

        $this->options = [];

        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $ret = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url, 'POST', json_encode($params));
        if($ret['code'] >= 400){
            App::error()->setError($ret['body']);
        }
        return json_decode($ret['body'], true);
    }

    /**
     * 查询并更新
     * @return array
     */
    public function update(array $data){
        // 如果触发409 并发版本更新冲突，将会导致部分成功部分失败
        $url = $this->url.'/'.$this->options['index'].'/_update_by_query?conflicts=proceed&wait_for_completion=false';
        $params = ['query' => []];

        $this->getQueryAndParam($params['query']);

        $this->getQueryORParam($params['query']);

        if($params['query'] == false) unset($params['query']);

        $source = "";
        foreach($data as $k => $v){
            if(is_int($k)){
                $source .= 'ctx._source.'.$v.';';
            }else{
                $source .= "ctx._source.{$k} = '".$v."';";
            }
        }
        $source = rtrim($source, ';');

        $params['script'] = [
            'source' => $source,
            'lang' => 'painless',
        ];

        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }

        $this->options = [];

        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $ret = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url, 'POST', json_encode($params));
        if($ret['code'] >= 400){
            App::error()->setError($ret['body']);
        }
        $result = json_decode($ret['body'], true);
        $versionConflicts = $result['version_conflicts'] ?? 0;
        if($versionConflicts > 0){
            // 有了failures 中能拿到已经失败的id，现在需要的就是重复执行把IDS换一下
        }
        return $result;
    }

    /**
     * 基于组件ID 进行批量更新
     * @param array $listData
     * @param array $ids
     * @return mixed
     */
    public function updateAll(array $listData, array $ids){
        $url = $this->url.'/'.$this->options['index'].'/_bulk';
        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/x-ndjson');
        $params = [];
        $updateAll = "";
        foreach($listData as $k => $v){
            $update = ['update' => ['_id' => $ids[$k]]];
            $updateAll .= json_encode($update)."\n";
            $data = ['doc' => $v];
            $updateAll .= json_encode($data)."\n";
        }
        $updateAll .= "\r\n";

        $ret = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url, 'POST', $updateAll);
        if($ret['code'] >= 400){
            App::error()->setError($ret['body']);
        }
        return json_decode($ret['body'], true);
    }

    /**
     * 使用脚本更新
     * @param string $script
     * @param mixed $_id
     * @return mixed
     * @throws \Exception
     */
    public function updateScript(string $script, mixed $_id, array $params = [])
    {
        $_params = '{}';
        if($params){
            $_params = json_encode($params);
        }
        $data = '{
  "script": {
    "source": "'.str_replace(["\n", "  "], "", $script).'",
    "lang": "painless",
    "params": '.$_params.'
  }
}';
        $url = $this->url.'/'.$this->options['index'].'/_update/'.$_id.'?retry_on_conflict='.$this->retry;
        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $params = [];
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url, 'POST', $data);
        if($result['code'] >= 400){
            App::error()->setError($result['body']);
        }
        $result = json_decode($result['body'], true);
        return $result;
    }

    /**
     * 使用脚本批量更新
     * @param array $scripts
     * @param array $_ids
     * @return mixed
     * @throws \Exception
     */
    public function updateScripts(array $scripts, array $_ids)
    {
        $updateAll = "";
        foreach ($scripts as $k => $script){
            $update = ['update' => ['_id' => $_ids[$k]]];
            $updateAll .= json_encode($update)."\n";
            $_script = '{"script": {"source": "'.str_replace(["\n", "  "], "", $script).'","lang": "painless"}}';
            $updateAll .= $_script;
        }
        $updateAll .= "\r\n";
        $url = $this->url.'/'.$this->options['index'].'/_bulk';
        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $params = [];
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url, 'POST', $updateAll);
        if($result['code'] >= 400){
            App::error()->setError($result['body']);
        }
        $result = json_decode($result['body'], true);
        return $result;
    }

    /**
     * 更新查询的数据
     * @param string $script
     * @param array $_ids
     * @param array $params
     * @return mixed
     */
    public function updateQuery(string $script, array $_ids, array $params = [], int $retry = 0)
    {
        $_params = '{}';
        if($params){
            $_params = json_encode($params);
        }
        $data = '{
  "script": {
    "source": "'.str_replace(["\n", "  "], "", $script).'",
    "lang": "painless",
    "params": '.$_params.'
  },
  "query":{
    "terms": {"_id": '.json_encode($_ids).'}
  }
}';
        $url = $this->url.'/'.$this->options['index'].'/_update_by_query?conflicts=proceed';
        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $params = [];
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url, 'POST', $data);
        if($result['code'] >= 400){
            App::error()->setError($result['body']);
        }
        $result = json_decode($result['body'], true);
        // 冲突测试需要覆盖到
        $versionConflicts = $result['version_conflicts'] ?? 0;
        if($versionConflicts > 0 && $retry < $this->retry){
            // 有了failures 中能拿到已经失败的id，现在需要的就是重复执行把IDS换一下
            $_ids = [];
            foreach ($result['failures'] ?? [] as $v){
                $_ids[] = $v['id'];
            }
            if($_ids){
                $retry += 1;
                return $this->updateQuery($script, $_ids, $params, $retry);
            }
        }
        $this->options = [];
        return $result;
    }

    /**
     * 统计条数
     * @return mixed
     * @throws \Exception
     */
    public function count()
    {
        $url = $this->url.'/'.$this->options['index'].'/_count';
        $params = ['query' => []];

        $this->getQueryAndParam($params['query']);

        $this->getQueryORParam($params['query']);

        if($params['query'] == false) unset($params['query']);

        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }

        $this->options = [];

        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');

        $ret = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url, 'POST', json_encode($params));
        if($ret['code'] >= 400){
            App::error()->setError($ret['body']);
        }
        return json_decode($ret['body'], true);
    }

    /**
     * 索引结构
     * @return mixed
     * @throws \Exception
     */
    public function mapping()
    {
        $url = $this->url.'/'.$this->options['index'].'/_mapping';

        $httpClient = new Httpclient();
        foreach ($this->options['proxy'] ?? [] as $v){
            if($v['type'] == 1){
                $httpClient->httpproxy($v['host'], $v['port'], $v['user'], $v['password']);
            }else if($v['type'] == 2){
                $httpClient->socks5($v['host'], $v['port'], $v['user'], $v['password']);
            }
        }

        $this->options = [];

        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $ret = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url);
        if($ret['code'] >= 400){
            App::error()->setError($ret['body']);
        }
        return json_decode($ret['body'], true);
    }
}
