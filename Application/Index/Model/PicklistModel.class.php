<?php

namespace Index\Model;

use Index\Model\BaseModel;

/**
 * wh_pick_list model
 */
class PicklistModel extends BaseModel {

    /**
     * table name:wh_pick_list
     * @var string 
     */
    protected $_tableName = "wh_pick_list";

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
        array("pick_id", '', '拣货单已经生成过，请重新打印拣货单', self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),
    );

    /**
     * 标记未取消的订单号为取消状态
     * @param mixed $orderNos '1,5,8' ||array('1','5','8')
     * @return boolean
     */
    public function markIsDel($orderNos) {
        if (empty($orderNos)) {
            return false;
        }
        try {
            $m = M($this->_tableName);
            $m->where(array("is_del" => 0, array("orderno" => array("in", $orderNos))));
            return $m->save(array("is_del" => 1, "update_time" => time()));
        } catch (\Exception $ex) {
            E('baseSaveById ERR.');
        }
    }

}
