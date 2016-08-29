<?php

namespace Warehouse\Model;

use Warehouse\Model\BaseModel;

/**
 * wh_auth_user_group model
 */
class AuthusergroupModel extends BaseModel {

    /**
     * table name wh_auth_user_group
     * 虽然是关系表，但uid只能对应一个group_id  
     * 关系表主要是满足tp的结构
     * @var string 
     */
    protected $_tableName = "wh_auth_user_group";
    
    /**
     * 更新一行数据  
     * @param type $uid
     * @param type $groupId
     * @return type
     */
    public function saveRow($uid,$groupId){
        try {
            $m = M($this->_tableName);
                return $m->where(array('uid'=>$uid))->save(array("group_id"=>$groupId));
        } catch (\Exception $ex) {
            E('saveRow ERR.');
        }        
    }
}
