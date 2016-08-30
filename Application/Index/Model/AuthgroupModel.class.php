<?php

namespace Index\Model;

use Index\Model\BaseModel;

/**
 * wh_auth_group model
 */
class AuthgroupModel extends BaseModel {

    /**
     * table name wh_auth_user
     * @var string 
     */
    protected $_tableName = "wh_auth_group";

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
        array("title", 'require', '用户组名称必须填写', self::EXISTS_VALIDATE),
        array("title", '', '用户组名称已经存在', self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),
    );

    /**
     * 通过uid获取用户组信息
     * @param type $uid
     * @return type
     */
    public function getGroupInfoByUid($uid) {
        try {
            $m = M("wh_auth_user_group");
            $list = $m->where(array("wh_auth_user_group.uid" => $uid))
                            ->join('LEFT JOIN wh_auth_group ON wh_auth_user_group.group_id = wh_auth_group.id')
                            ->field(array("uid","group_id","title","status"))->find();
//            echo $m->getLastSql();exit;
            return $list;
        } catch (\Exception $ex) {
            E('baseGet ERR.');
        }
    }

}
