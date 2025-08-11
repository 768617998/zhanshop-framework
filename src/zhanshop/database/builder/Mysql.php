<?php
// +----------------------------------------------------------------------
// | zhanshop-php / Mysql.php    [ 2023/1/29 20:06 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2023 zhangqiquan All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangqiquan <768617998@qq.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace zhanshop\database\builder;

use zhanshop\database\Query;

class Mysql
{
    public function &insert(Query &$query){
        $data = $query->getOptions("data", true);
        if($data){
            $field = implode(', ', array_keys($data[0]));
            $sql = 'INSERT INTO '.$query->getOptions('table').' ('.$field.') VALUES';

            foreach ($data as $k => $v){
                if($k != 0){
                    $sql .= ', ';
                }
                $sql .= ' (';
                $num = 0;
                foreach ($v as $kk => $vv){
                    $key = 'zhanshop_'.str_replace('.', '__', $kk).$k;
                    $query->setBind($key, $vv);
                    if($num != 0) $sql .= ', ';
                    $sql .= ':'.$key;
                    $num++;
                }
                $sql .= ')';
            }

            return $sql;
        }
        throw new \PDOException("未设定需要insert的数据", 2000);
    }

    public function delete(Query &$query){
        $where = $query->getOptions("where", true);
        $whereStr = $this->parseWhere($query, $where);
        if($whereStr){
            $sql = 'DELETE FROM '.$query->getOptions('table').$whereStr;
            return $sql;
        }
        throw new \PDOException("未设定需要delete的条件", 2001);
    }

    public function update(Query &$query){
        $where = $query->getOptions("where", true);
        $whereStr = $this->parseWhere($query, $where);
        if($whereStr){
            $setVal = "SET ";
            $data = $query->getOptions("data", true);
            foreach ($data[0] as $k => $v){
                if(is_object($v) && isset($v->data)){
                    $setVal .= $k.' = '.$v->data.', ';
                }else{
                    $key = 'zhanshop_set_'.str_replace('.', '__', $k);
                    $query->setBind($key, $v);
                    $setVal .= $k.' = :'.$key.', ';
                }
            }
            $setVal = rtrim($setVal, ', ');
            $sql = 'UPDATE '.$query->getOptions('table').' '.$setVal.' '.$whereStr;
            return $sql;
        }
        throw new \PDOException("未设定需要update的条件", 2002);
    }

    public function count(Query &$query){
        $joinStr = $this->parseJoin($query, $query->getOptions("join", true));
        $whereStr = $this->parseWhere($query, $query->getOptions("where", true));
        $havingStr = $this->parseHaving($query, $query->getOptions("having", true));
        $groupStr = $this->parseGroup($query, $query->getOptions("group", true));
        $alias = $this->parseAlias($query, $query->getOptions('alias', true));
        $forceIndex = $this->parseForceIndex($query, $query->getOptions('force_index', true));
        $distinct = $query->getOptions('distinct', true);
        $field = $query->getOptions('field', true);

        $sql = 'SELECT count('.($distinct ? ' DISTINCT' : '').' '.($field ?? '*').') as __count FROM '.$query->getOptions('table').$alias.$forceIndex.$joinStr.$whereStr.$groupStr.$havingStr;
        return $sql;
    }

    // 聚合查询只有where条件有效
    public function avg(Query &$query){
        $joinStr = $this->parseJoin($query, $query->getOptions("join", true));
        $whereStr = $this->parseWhere($query, $query->getOptions("where", true));
        $havingStr = $this->parseHaving($query, $query->getOptions("having", true));
        $groupStr = $this->parseGroup($query, $query->getOptions("group", true));
        $alias = $this->parseAlias($query, $query->getOptions('alias', true));
        $forceIndex = $this->parseForceIndex($query, $query->getOptions('force_index', true));
        $distinct = $query->getOptions('distinct', true);
        $field = $query->getOptions('field', true);

        $sql = 'SELECT AVG('.($distinct ? ' DISTINCT' : '').' '.($field ?? '*').') as __avg FROM '.$query->getOptions('table').$alias.$forceIndex.$joinStr.$whereStr.$groupStr.$havingStr;
        return $sql;
    }

    public function min(Query &$query){
        $joinStr = $this->parseJoin($query, $query->getOptions("join", true));
        $whereStr = $this->parseWhere($query, $query->getOptions("where", true));
        $havingStr = $this->parseHaving($query, $query->getOptions("having", true));
        $groupStr = $this->parseGroup($query, $query->getOptions("group", true));
        $alias = $this->parseAlias($query, $query->getOptions('alias', true));
        $forceIndex = $this->parseForceIndex($query, $query->getOptions('force_index', true));
        $distinct = $query->getOptions('distinct', true);
        $field = $query->getOptions('field', true);

        $sql = 'SELECT MIN('.($distinct ? ' DISTINCT' : '').' '.($field ?? '*').') as __min FROM '.$query->getOptions('table').$alias.$forceIndex.$joinStr.$whereStr.$groupStr.$havingStr;
        return $sql;
    }

    public function max(Query &$query){
        $joinStr = $this->parseJoin($query, $query->getOptions("join", true));
        $whereStr = $this->parseWhere($query, $query->getOptions("where", true));
        $havingStr = $this->parseHaving($query, $query->getOptions("having", true));
        $groupStr = $this->parseGroup($query, $query->getOptions("group", true));
        $alias = $this->parseAlias($query, $query->getOptions('alias', true));
        $forceIndex = $this->parseForceIndex($query, $query->getOptions('force_index', true));
        $distinct = $query->getOptions('distinct', true);
        $field = $query->getOptions('field', true);

        $sql = 'SELECT MAX('.($distinct ? ' DISTINCT' : '').' '.($field ?? '*').') as __max FROM '.$query->getOptions('table').$alias.$forceIndex.$joinStr.$whereStr.$groupStr.$havingStr;
        return $sql;
    }

    public function sum(Query &$query){
        $joinStr = $this->parseJoin($query, $query->getOptions("join", true));
        $whereStr = $this->parseWhere($query, $query->getOptions("where", true));
        $havingStr = $this->parseHaving($query, $query->getOptions("having", true));
        $groupStr = $this->parseGroup($query, $query->getOptions("group", true));
        $alias = $this->parseAlias($query, $query->getOptions('alias', true));
        $forceIndex = $this->parseForceIndex($query, $query->getOptions('force_index', true));
        $distinct = $query->getOptions('distinct', true);
        $field = $query->getOptions('field', true);

        $sql = 'SELECT SUM('.($distinct ? ' DISTINCT' : '').' '.($field ?? '*').') as __sum FROM '.$query->getOptions('table').$alias.$forceIndex.$joinStr.$whereStr.$groupStr.$havingStr;
        return $sql;
    }

    public function find(Query &$query){
        $joinStr = $this->parseJoin($query, $query->getOptions("join", true));
        $whereStr = $this->parseWhere($query, $query->getOptions("where", true));
        $havingStr = $this->parseHaving($query, $query->getOptions("having", true));
        $groupStr = $this->parseGroup($query, $query->getOptions("group", true));
        $orderStr = $this->parseOrder($query, $query->getOptions("order", true)); // RAND() | id desc, age asc
        $alias = $this->parseAlias($query, $query->getOptions('alias', true));
        $forceIndex = $this->parseForceIndex($query, $query->getOptions('force_index', true));
        $distinct = $query->getOptions('distinct', true);
        $field = $query->getOptions('field', true);

        $sql = 'SELECT'.($distinct ? ' DISTINCT' : '').' '.($field ?? '*').' FROM '.$query->getOptions('table').$alias.$forceIndex.$joinStr.$whereStr.$groupStr.$havingStr.$orderStr.' LIMIT 1';
        return $sql;
    }

    public function select(Query &$query){
        $joinStr = $this->parseJoin($query, $query->getOptions("join", true));
        $whereStr = $this->parseWhere($query, $query->getOptions("where", true));
        $havingStr = $this->parseHaving($query, $query->getOptions("having", true));
        $groupStr = $this->parseGroup($query, $query->getOptions("group", true));
        $orderStr = $this->parseOrder($query, $query->getOptions("order", true)); // RAND() | id desc, age asc
        $limitStr = $this->parseLimit($query, $query->getOptions("limit", true));
        $alias = $this->parseAlias($query, $query->getOptions('alias', true));
        $forceIndex = $this->parseForceIndex($query, $query->getOptions('force_index', true));
        $distinct = $query->getOptions('distinct', true);
        $field = $query->getOptions('field', true);

        $sql = 'SELECT'.($distinct ? ' DISTINCT' : '').' '.($field ?? '*').' FROM '.$query->getOptions('table').$alias.$forceIndex.$joinStr.$whereStr.$groupStr.$havingStr.$orderStr.$limitStr;
        return $sql;
    }

    public function parseAlias(Query &$query, ?string $alias){
        if($alias){
            $alias = ' AS '.$alias;
        }
        return $alias;
    }

    public function parseForceIndex(Query &$query, ?string $field){
        if($field){
            $field = ' FORCE INDEX ('.$field.') ';
        }
        return $field;
    }

    protected function parseWhere(Query &$query, ?array $where){
        $whereStr = '';
        $ands = $where['AND'] ?? [];
        $firstKey = array_key_first($ands);
        foreach ($ands as $k => $v){
            if($firstKey != $k){
                $whereStr .= ' AND ';
            }
            $bindKey = 'ZhanShopBind_Where_'.str_replace('.', '__', $k);
            $whereStr .= $k.' = :'.$bindKey;
            $query->setBind($bindKey, $v);
        }

        $ins = $where['In'] ?? [];
        foreach ($ins as $k => $v){
            if($whereStr){
                $whereStr .= ' AND ';
            }
            $v = "'".implode("', '", $v)."'";
            $whereStr .= $k.' IN ('.$v.')';
        }

        $notins = $where['NotIn'] ?? [];
        foreach ($notins as $k => $v){
            if($whereStr){
                $whereStr .= ' AND ';
            }
            $v = "'".implode("', '", $v)."'";
            $whereStr .= $k.' NOT IN ('.$v.')';
        }

        $raws = $where['RAW'] ?? [];
        foreach ($raws as $k => $v){
            if($whereStr){
                $whereStr .= ' AND (';
            }else{
                $whereStr .= '(';
            }
            $whereStr .= $v[0];
            $query->setBindArray($v[1]);
            $whereStr .= ')';
        }
        if($whereStr) $whereStr = ' WHERE '.$whereStr;
        return $whereStr;
    }

    public function parseHaving(Query &$query, ?array $having){
        $havingStr = '';
        foreach ($having ?? [] as $k => $v){
            if($havingStr){
                $havingStr .= ' AND (';
            }else{
                $havingStr .= '(';
            }
            $havingStr .= $v[0];
            $query->setBindArray($v[1]);
            $havingStr .= ')';
        }
        if($havingStr) $havingStr = ' HAVING '.$havingStr;
        return $havingStr;
    }

    public function parseOrder(Query &$query, ?array $order){
        $orderStr = '';
        if($order){
            $orderStr = ' ORDER BY '.implode(',', $order);
        }
        return $orderStr;
    }

    public function parseGroup(Query &$query, ?string $group){
        if($group) $group = " GROUP BY ".$group;
        return $group;
    }

    public function parseLimit(Query &$query, ?array $limit){
        $limitStr = '';
        if($limit){
            $limitStr = " LIMIT ".$limit[0];
            $length = $limit[1];
            if($length){
                $limitStr .= ",".$length;
            }

        }
        return $limitStr;
    }

    public function parseJoin(Query &$query, ?array $join){
        $joinStr = '';
        if($join){
            foreach($join as $v){
                $joinStr .= ' '.$v['type'].' JOIN ';
                $joinStr .= $v['table'].' as '.$v['alias'];
                $joinStr .= ' ON '.$v['condition'];
                if($v['bind']) $query->setBindArray($v['bind']);
            }
        }
        return $joinStr;
    }

    public function buildSql(Query &$query){
        $sql = $this->select($query);
        $bind = $query->getBind();
        return $this->fetchSql($sql, $bind);
    }

    protected function fetchSql(string &$sql, array &$bind){
        $key = [];
        $val = [];
        foreach ($bind as $k => $v){
            $key[] = ':'.$k;
            $val[] = is_string($v) ?  '"'.addslashes($v).'"': $v;
        }
        $sql = str_replace($key, $val, $sql);
        return $sql;
    }
}