<?php

namespace Index\Model;

use Index\Model\BaseModel;

/**
 * wh_category model
 */
class CategoryModel extends BaseModel {

    /**
     * table name:wh_category
     * @var string 
     */
    protected $_tableName = "wh_category";

    /**
     * 自动完成规则
     * @var array 
     */
    protected $_auto_rules = array(
        array("create_time", 'time', self::MODEL_INSERT, 'function'),
        array("update_time", 'time', self::MODEL_BOTH, 'function'),
    );

    /**
     * 自动验证规则
     * @var array
     */
    protected $_validate_rules = array(
        array("category_id", 'require', '品类编号必须填写', self::EXISTS_VALIDATE),
        array("category_name", 'require', '品类名称必须填写', self::EXISTS_VALIDATE),
        array("category_id", '', '品类编号已经存在', self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),
        array("category_name", '', '品类名称已经存在', self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),
        array("shelf_no", '', '货架已经被占用', self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),
    );

    /**
     * 出库 减少库存 累计出库量
     * @param array $where
     * @param int $count
     * @return boolean|int
     */
    public function decCurCount($where, $count) {
        if ($count <= 0) {
            return false;
        }
        try {
            $m = M();
            $rs = $m->table($this->_tableName)
                    ->where($where)
                    ->save(array(
                "cur_count" => array("exp", "cur_count-" . $count),
                "total_out_count" => array("exp", "total_out_count+" . $count))
            );
            return $rs;
        } catch (\Exception $ex) {
            E('decCurCount ERR.');
        }
        return false;
    }
    
    /**
     * 上架 下架操作
     * @param type $ids
     * @return boolean
     */
    public function setLine($id,$cid,$action){
        if (empty($id)) {
            return false;
        }
        $action = empty($action)?0:$action;
        if($action != 0){
            $action = 1;
        }
        try {
            $m = M();
            $rs = $m->table($this->_tableName)
                    ->where(array("id"=>$id,"category_id"=>$cid))
                    ->save(array("offline" => $action,));
            return $rs;
        } catch (\Exception $ex) {
            E('offline ERR.');
        }
        return false;        
    }

    /** 查找指定的普通品信息
     * @param string|array $where 查询条件
     * @param null|string|array $field 返回字段
     * @param null|string $order 排序规则
     * @return mixed
     */
    public function findPrintItem($where, $field = null, $order = null)
    {
        $m = M();
        $m->table($this->_tableName);
        foreach(['where', 'field', 'order'] as $v) {
            !empty($$v) && $m->$v ($$v);
        }
        $ret = $m->find();
        return $ret;
    }

    /** 查找指定的普通品信息
     * @param string|array $where 查询条件
     * @param string $limit 查询限制
     * @param null|string|array $field 返回字段
     * @param null|string $order 排序规则
     * @return mixed
     */
    public function findPrintItemList($where, $limit = null, $field = null, $order = null)
    {
        $m = M();
        $m->table($this->_tableName);
        foreach(['where', 'field', 'order', 'limit'] as $v) {
            !empty($$v) && $m->$v ($$v);
        }
        $ret = $m->select();
        return $ret;
    }

    /** 根据id获取普通品信息
     * @param int $id 品类 id
     * @param mixed $field 获取字段
     * @return mixed
     */
    public function getCategory($id, $field = null)
    {
        return $this->findPrintItem(['id'=>$id], $field);
    }

    /** 根据id获取普通品信息
     * @param string|array $ids 品类 id 列表
     * @param mixed $field 获取字段
     * @return mixed
     */
    public function getCategoryList($ids, $field = null)
    {
        return $this->findPrintItemList(['id'=>['in',$ids]], null, $field);
    }


    /** 获取上线的选购商品
     * @param array $where 查询字段
     * @return mixed
     */
    public function getOnlineCategoryList($where)
    {
        return $this->findPrintItemList(
            ['offline' => 0, 'cur_count' => ['gt', C('CUR_LEAST_COUNT')]],
            null,
            $where);
    }
}
