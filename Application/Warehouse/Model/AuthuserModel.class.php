<?php

namespace Warehouse\Model;

use Warehouse\Model\BaseModel;

/**
 * wh_auth_user model
 */
class AuthuserModel extends BaseModel {

    /**
     * table name wh_auth_user
     * @var string 
     */
    protected $_tableName = "wh_auth_user";
    
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
        array("uid", 'require', '用户ID必须填写', self::EXISTS_VALIDATE),
        array("pwd", 'require', '用户密码必须填写', self::EXISTS_VALIDATE),
        array("nickname", 'require', '用户昵称必须填写', self::EXISTS_VALIDATE),
        array("uid", '', '用户ID已经存在', self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),
        array("nickname", '', '用户昵称已经存在', self::EXISTS_VALIDATE, 'unique', self::MODEL_BOTH),
    );    
    
}
