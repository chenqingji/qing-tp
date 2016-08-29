<?php

namespace Index\Model;


/**
 * order_relationship model
 */
class OrderRelationShipModel extends \Common\Model\BaseModel {

    /**
     * 未入库
     */
    const PROCESS_INIT = 0;

    /**
     * 已入库
     */
    const PROCESS_PUT_IN = 1;

    /**
     * 已拣货
     */
    const PROCESS_PICKED = 2;

    /**
     * 已出库
     */
    const PROCESS_PUT_OUT = 3;

    /**
     * 普通商品类型 规格品
     */
    const GOODS_TYPE_NORMAL = 1;

    /**
     * 打印定制品
     */
    const GOODS_TYPE_PRINT_NORMAL = 2;

    /**
     * 微信书
     */
    const GOODS_TYPE_PRINT_WXS = 3;

    /**
     * 照片卡
     */
    const GOODS_TYPE_PRINT_ZPK = 5;

    /**
     * @var array 分组类型配置
     */
    static public $GROUP_TYPE = [
        'normalGroup' => [OrderRelationShipModel::GOODS_TYPE_NORMAL],
        'printGroup' => [OrderRelationShipModel::GOODS_TYPE_PRINT_NORMAL, OrderRelationShipModel::GOODS_TYPE_PRINT_WXS, OrderRelationShipModel::GOODS_TYPE_PRINT_ZPK]
    ];



    // 订单状态
    /**
     * 订单可用
    */
    const ORDER_STATUS_USE = 0x01;

    /**
     * 订单支付
     */
    const ORDER_STATUS_PAID = 0x02;
    
    /**
     * 订单可用及支付
     */
    const ORDER_STATUS_USE_PAID = 0x03;


    /**
     * table name:order_relationship
     * @var string 
     */
    protected $trueTableName = "order_relationship";

//    /**
//     * 自动完成规则
//     * @var array
//     */
//    protected $_auto = [
//        ["update_time", 'nowTime', self::MODEL_BOTH, 'callback']
//    ];


    /**
     * 更新指定条件的一条记录
     * @param array $where 条件
     * @param mixed $data * | array
     * @return false|null|array 一维数组
     */
    public function baseSave($where, $data) {
        $data['update_time'] = date('Y-m-d H:i:s');
        return $this->where($where)->save($data);
    }

    /**
     * 获取指定条件的一条记录
     * @param array $where 条件
     * @param mixed $field * | array
     * @param mixed $order id=>dsc,do=>asc,name 默认asc
     * @return false|null|array 一维数组
     */
    public function baseFind($where = [], $field = "*", $order = []) {
        try {
            $this->field($field)->where($where);
            if (!empty($order)) {
                $this->order($order);
            }
            return $this->find();
        } catch (\Exception $ex) {
            E('baseFind ERR.');
        }
    }

    /**
     * 获取指定条件的记录
     * @param array $where 条件
     * @param mixed $field * | array
     * @param mixed $order id=>dsc,do=>asc,name 默认asc
     * @param mixed $limit '0,10' 10
     * @param mixed $group 'user_id'
     * @return false|null|array 二维数组
     */
    public function baseGet($where = [], $field = "*", $order = [], $limit = '', $group = null) {
        try {
            $this->field($field)->where($where);
            if (!empty($order)) {
                $this->order($order);
            }
            if (!empty($limit)) {
                $this->limit($limit);
            }
            if (!empty($group)) {
                $this->group($group);
            }
            return $this->select();
        } catch (\Exception $ex) {
            E('baseGet ERR.');
        }
    }

    /**
     * 添加关系记录
     * @param array $data 条件
     * @return int|false
     */
    public function baseAdd($data) {
        return $this->add($data);
    }

    /**
     * @param int $orderId 订单 id
     * @param int $goodType 货物类型
     * @param int $goodId 货物 id
     * @param int $cnt 数量
     * @param string $uhash 地址 hash 值
     * @param string $orderno 订单号
     * @param string $productId 商品 id
     * @return mixed
     */
    public function addRelatioship($orderId, $goodType, $goodId, $cnt, $uhash, $orderno, $productId)
    {
        $date =  date("Y-m-d H:i:s", time());
        $data = [
            'status' =>  OrderRelationShipModel::ORDER_STATUS_USE,
            'order_id' => $orderId,
            'goods_type' => $goodType,
            'goods_id' => $goodId,
            'count' => $cnt,
            'orderno' => $orderno,
            'product_id' => $productId,
            'create_time' => $date,
            'update_time' => $date,
        ];
        !empty($uhash) && $data['uhash'] = $uhash;
        $fields = []; $values = []; $sets = [];
        foreach ($data as $k => $v) {
            $fields[] = $k;
            $values[] = "'$v'";
            $sets[] = "$k='$v'";
        }

        unset($sets['create_time']);
        $tableName = $this->trueTableName;
        $fields = implode(',', $fields);
        $values = implode(',', $values);
        $sets = implode(',', $sets);
        $sql = "INSERT INTO $tableName ($fields) VALUES($values) ON DUPLICATE KEY UPDATE $sets";

        return $this->execute($sql);
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
            $keys = array_keys($data[0]);
            $keyString = "(`" . implode("`,`", $keys) . "`)";
            $valueString = '';
            foreach ($data as $one) {
                $one = array_map("addslashes", $one);
                $valueString .= "('" . implode("','", $one) . "'),";
            }
            $valueString = rtrim($valueString, ",");
            $sql = "INSERT INTO " . $this->trueTableName . $keyString . " VALUES" . $valueString;
            return $this->execute($sql);
        } catch (\Exception $ex) {
            E('baseAdd ERR.');
        }
    }    

    /**
     * 检查是否有uid及uhash相同的其他未拣货且已经支付的有效商品
     * @param int $uid
     * @param string $uhash
     * @return int|false
     */
    public function hasSameUidUhashProdut($uid, $uhash) {
        return $this->where([
                    "uid" => $uid,
                    "uhash" => $uhash,
                    "process" => ["lt", self::PROCESS_PICKED],
                    "status" => ["exp", "&3=3"],
                    "count"=>["gt",0],
                ])
            ->count("product_id");
    }

    /**
     * 更新商品进度
     * @param array $where
     * @param int $newProcess
     * @return type
     */
    public function updateProcess($where, $newProcess) {
        try {
            return $this->where($where)->save(array("process" => $newProcess,"update_time"=>date("Y-m-d H:i:s",time())));
        } catch (\Exception $e) {
            E("updateProcess ERR.");
        }
    }


    /**
     * order_info中用户电话地址唯一标识
     * @param array $data 订单信息
     * @return string
     */
    static function addressMd5($data){
        $str = '';
        foreach($data as $v) {
            !empty($v) && $str .= $v;
        }
        return md5($str);
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
    public function baseGetPage($where = array(), $field = "*", $page = 1, $rows = 10, $order = array("id" => "desc")) {
        $m = M($this->trueTableName);
        $count = $m->where($where)->count();

        $page = ($page <= 0 || empty($page)) ? 1 : $page;
        $rows = ($rows <= 0 || empty($rows)) ? 10 : $rows;
        $order = empty($order) ? array("id" => "desc") : $order;

        $totalPage = ceil($count / $rows);
        $limitString = implode(',', $this->getPageLimit($page, $rows));
        $list = $m->where($where)
//                ->join('LEFT JOIN user_info ON order.uid = user_info.uid')
                ->field($field)
                ->order($order)
                ->limit($limitString)->select();
//        echo $m->getLastSql();exit;
        return array(
            "currpage" => $page,
            "totalpages" => $totalPage,
            "totalrecords" => $count,
            "rows" => $list
        );
    }       
}
