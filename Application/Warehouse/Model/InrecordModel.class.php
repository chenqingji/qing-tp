<?php

namespace Warehouse\Model;

use Warehouse\Model\BaseModel;

/**
 * wh_in_record model
 */
class InrecordModel extends BaseModel {

    /**
     * table name:wh_in_record
     * @var string 
     */
    protected $_tableName = "wh_in_record";

    /**
     * 入库类型 普通品 1
     */
    const TYPE_NORMAL = 1;

    /**
     * 入库类型 定制品 2
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
