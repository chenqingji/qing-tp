<?php

namespace Warehouse\Model;

use Warehouse\Model\BaseModel;

/**
 * wh_auth_rule model
 */
class AuthruleModel extends BaseModel {

    /**
     * table name wh_auth_rule
     * @var string 
     */
    protected $_tableName = "wh_auth_rule";

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
        array("name", 'require', '规则标识不能为空', self::EXISTS_VALIDATE),
        array("title", 'require', '规则名称不能为空', self::EXISTS_VALIDATE),
        array("name", '', '规则标识已经存在', self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),
        array("title", '', '规则名称已经存在', self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),
    );

    /**
     * 是否有子节点
     * @param type $id
     * @return type
     */
    public function hasChildid($id) {
        try {
            $m = M($this->_tableName);
            return $m->where(array('pid' => $id))->find();
        } catch (\Exception $ex) {
            E('hasChildid ERR.');
        }
    }

    /**
     * 更新子节点状态
     * @param type $pid
     * @param type $status
     * @return boolean
     */
    public function updateChildrenStatus($pid, $status) {
        if ($pid != '' && $status != '') {
            try {
                $m = M($this->_tableName);
                return $m->where(array("pid" => $pid))->save(array("status" => $status));
            } catch (\Exception $ex) {
                E('updateChildrenStatus ERR.');
            }
        }
        return false;
    }

}
