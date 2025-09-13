<?php

namespace zhanshop;

use zhanshop\client\Httpclient;

class Elasticsearch
{
    protected $url;
    protected $user;
    protected $pass;
    protected $timeout = 3000;
    protected $options = [];
    public function __construct()
    {
        $config = App::config()->get('elasticsearch');
        $this->url = $config['scheme'].'://'.$config['host'].':'.$config['port'];
        $this->user = $config['user'];
        $this->pass = $config['pass'];
        if(isset($config['timeout']) && is_int($config['timeout'])) $this->timeout = $config['pass'];
    }

    /**
     * 获取所有表
     * @return string[]
     */
    public function showTables(){
        $httpClient = new Httpclient();
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
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($this->url.'/'.$this->options['index'].'/_doc/'.$id.'?pretty', "POST", json_encode($data));
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
        $this->options = [];

        $httpClient = new Httpclient();
        //var_dump($this->user.':'.$this->pass);
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $result = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($this->url.'/_bulk', "POST", $saveAll);
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
                    $field = is_int($v[2]) ?  $v[0] : $v[0].'.keyword';
                    $params['bool']['must'][] = [
                        'wildcard' => [$field => '*'.$v[2].'*']
                    ];
                }else if($v[1] == '='){
                    $field = is_int($v[2]) ?  $v[0] : $v[0].'.keyword';
                    $params['bool']['must'][]['term'][$field] = $v[2];
                }else if($v[1] == 'in'){
                    $field = is_int($v[2][0]) ?  $v[0] : $v[0].'.keyword';
                    $params['bool']['must'][]['terms'][$field] = $v[2];
                }else if($v[1] == '!='){
                    $field = is_int($v[2]) ?  $v[0] : $v[0].'.keyword';
                    $params['bool']['must_not'][]['terms'][$field] = $v[2];
                }else if($v[1] == 'not in'){
                    $field = is_int($v[2][0]) ?  $v[0] : $v[0].'.keyword';
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
                    $field = is_int($v[2]) ?  $v[0] : $v[0].'.keyword';
                    $params['bool']['should'][] = [
                        'wildcard' => [$field => '*'.$v[2].'*']
                    ];
                }else if($v[1] == '='){
                    $field = is_int($v[2]) ?  $v[0] : $v[0].'.keyword';
                    $params['bool']['should'][]['term'][$field] = $v[2];
                }else if($v[1] == 'in'){
                    $field = is_int($v[2][0]) ?  $v[0] : $v[0].'.keyword';
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

        $this->options = [];

        $data = [
            'total' => 0,
            'list' => [],
            'max_score' => 0,
            'errmsg' => '',
            'original' => [],
        ];

        try {
            $httpClient = new Httpclient();
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
        return $data;
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

        $this->options = [];

        $httpClient = new Httpclient();
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

        $this->options = [];

        $httpClient = new Httpclient();
        $httpClient->userPwd($this->user.':'.$this->pass);
        $httpClient->setHeader('Content-Type', 'application/json');
        $ret = $httpClient->setTimeout($this->timeout)->enableIpv6()->request($url, 'POST', json_encode($params));
        if($ret['code'] >= 400){
            App::error()->setError($ret['body']);
        }
        return json_decode($ret['body'], true);
    }

}