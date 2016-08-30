<?php

namespace Index\Model;

use Index\Model\BaseModel;

/**
 * wh_out_record model
 */
class OutrecordModel extends BaseModel {

    /**
     * table name  wh_out_record
     * @var string 
     */
    protected $_tableName = 'wh_out_record';

    /**
     * 出库类型 普通品 1
     */
    const TYPE_NORMAL = 1;

    /**
     * 出库类型 定制品 2
     */
    const TYPE_CUSTOM = 2;
    
    /**
     * 自动完成规则
     * @var array 
     */
    protected $_auto_rules = array(
        array("create_time", 'time', self::MODEL_INSERT, 'function'),
    );    

}
