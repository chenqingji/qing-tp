<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Warehouse\Model;

/**
 * Description of BaseModel
 */
class BaseModel extends \Common\Model\BaseModel {

    /**
     * construct
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * 通过当前页码和每页数据条数，获取查询的起始及终止位置
     * @param int $page
     * @param int $rows
     * @return array
     */
    protected function getPageLimit($page = 1, $rows = 10) {
        $limitStart = ($page - 1) * $rows;
        $limitEnd = $page * $rows;
        return array($limitStart, $limitEnd);
    }

    /**
     * 获取指定条件的记录
     * @param array $where 条件
     * @param array $field * | array
     * @param string $order id=>dsc,do=>asc,name 默认asc
     * @param string $limit '0,10' 10
     * @return false|null|array 二维数组
     */
    public function baseGet($where = array(), $field = "*", $order = array(), $limit = '') {
        try {
            $m = M($this->_tableName);
            $m->field($field)->where($where);
            if (!empty($order)) {
                $m->order($order);
            }
            if (!empty($limit)) {
                $m->limit($limit);
            }
            return $m->select();
        } catch (\Exception $ex) {
            E('baseGet ERR.');
        }
    }

    /**
     * 获取指定条件的一条记录
     * @param array $where 条件
     * @param array $field * | array
     * @param string $order id=>dsc,do=>asc,name 默认asc
     * @return false|null|array 一维数组
     */
    public function baseFind($where = array(), $field = "*", $order = array()) {
        try {
            $m = M($this->_tableName);
            $m->field($field)->where($where);
            if (!empty($order)) {
                $m->order($order);
            }
            return $m->find();
        } catch (\Exception $ex) {
            E('baseFind ERR.');
        }
    }

    /**
     * 添加记录
     * @param array $data 添加数据数组
     * @return false|int 成功返回自增id|失败返回false 在控制器层调用$model->getError()获取控制器的错误信息
     */
    public function baseAdd($data) {
        try {
            $this->autoCheckFields = true;
            $m = M($this->_tableName);
            if ($m->validate($this->_validate_rules)->create($data)) {
                $m->auto($this->_auto_rules)->create($data);
                $res = $m->add();
                \Warehouse\Common\Marktest::writeTestData($m->getLastSql());
                return $res;
            }
            $this->error = $m->getError();
            return false;
        } catch (\Exception $ex) {
            E('baseAdd ERR.');
        }
    }

    /**
     * 批量插入记录
     * @param array $data $data[] = array('name'=>'thinkphp','email'=>'thinkphp@gamil.com');
     * @return int|false
     */
    public function baseAddAll($data) {
        if (empty($data)) {
            return false;
        }
        try {
            $m = M($this->_tableName);
            $keys = array_keys($data[0]);
            $keyString = "(`" . implode("`,`", $keys) . "`)";
            $valueString = '';
            foreach ($data as $one) {
                $one = array_map("addslashes", $one);
                $valueString .= "('" . implode("','", $one) . "'),";
            }
            $valueString = rtrim($valueString, ",");
            $sql = "INSERT INTO " . $this->_tableName . $keyString . " VALUES" . $valueString;
            return $m->execute($sql);
        } catch (\Exception $ex) {
            E('baseAdd ERR.');
        }
    }

    /**
     * 获取分页数据
     * 注意：where条件可能需要进一步扩展复杂查询，增加condition；注意相关字段的索引；
     * @param array $where
     * @param string $field *表示查询所有字段
     * @param int $page 当前页码
     * @param int $rows 每页记录数
     * @param array $order 排序信息  array("a"=>"desc",'b'=>'asc','c') 默认asc
     * @return array
     */
    public function baseGetPage($where = array(), $field = "*", $page = 1, $rows = 10, $order = array("create_time" => "desc")) {
        $m = M($this->_tableName);
        $count = $m->where($where)->count();

        $page = ($page <= 0 || empty($page)) ? 1 : $page;
        $rows = ($rows <= 0 || empty($rows)) ? 10 : $rows;
        $order = empty($order) ? array("create_time" => "desc") : $order;

        $totalPage = ceil($count / $rows);
        $limitString = implode(',', $this->getPageLimit($page, $rows));
        $list = $m->where($where)->field($field)->order($order)->limit($limitString)->select();
//        echo $m->getLastSql();exit;
        return array(
            "currpage" => $page,
            "totalpages" => $totalPage,
            "totalrecords" => $count,
            "rows" => $list
        );
    }

    /**
     * 通过主键获取记录
     * @param type $ids (int | array)
     * @param type $field * | array
     * @return boolean|array
     */
    public function baseGetByIds($ids, $field = "*") {
        if (empty($ids)) {
            return false;
        } elseif (is_array($ids)) {
            $ids = implode(',', $ids);
        }
        try {
            $m = M($this->_tableName);
            return $m->field($field)->where("id in (%s)", array($ids))->order("create_time desc")->selecet();
        } catch (\Exception $ex) {
            E("baseGetByIds ERR.");
        }
    }

    /**
     * 更新记录 by id
     * 注意：需要在各自model定义$_validate_rules及$_auto_rules 范围protected
     * @param array $data 要更新的数组（要求含有表主键，如id，才可顺利自动验证唯一）
     * @return 成功返回影响条数|失败返回false
     */
    public function baseSaveById($data) {
        try {
            $this->autoCheckFields = true;
            $m = M($this->_tableName);
            if ($m->validate($this->_validate_rules)->create($data)) {
                $m->auto($this->_auto_rules)->create($data);
                return $m->save();
            }
            $this->error = $m->getError();
            return FALSE;
        } catch (\Exception $ex) {
            E('baseSaveById ERR.');
        }
    }

    /**
     * 通过主键id删除记录
     * @param mixed $ids 主键id 支持数字1和字符串 1,2,3
     * @return boolean|int 失败返回false
     */
    public function baseDelByIds($ids) {
        if (empty($ids)) {
            return false;
        }
        try {
            $m = M($this->_tableName);
            return $m->delete($ids);
        } catch (\Exception $ex) {
            E("baseDelByIds ERR.");
        }
    }

}
