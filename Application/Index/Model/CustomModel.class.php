<?php

namespace Index\Model;

use Index\Model\BaseModel;

/**
 * wh_custom_made model
 */
class CustomModel extends BaseModel {

    /**
     * table name:wh_custom_made
     * @var string 
     */
    protected $_tableName = "wh_custom_made";

    /**
     * 自动完成规则
     * @var array 
     */
    protected $_auto_rules = array(
        array("update_time", 'time', self::MODEL_BOTH, 'function'),
    );

    /**
     * 自动验证规则
     * @var array
     */
    protected $_validate_rules = array(
        array("product_id", '', '定制区发现已存在与该商品编号一致的其他商品', self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),
        array("location", '', '位置分配重复[location]', self::EXISTS_VALIDATE, 'unique', self::MODEL_INSERT),
    );

    /**
     * 解绑定制品仓位
     * @param mixed $productId  '123' | array("123",'234')
     * @return boolean
     */
    public function emptyByProductId($productId) {
        if (empty($productId)) {
            return false;
        }
        try {
            $m = M($this->_tableName);
            $data = array("orderno" => '', "product_id" => '', "desc" => '', "update_time" => time());
            $where = is_array($productId) ? array("product_id" => array("in", $productId)) : array("product_id" => $productId);
            return $m->where($where)->save($data);
        } catch (\Exception $e) {
            E("emptyByProductId ERR.");
        }
    }

}
