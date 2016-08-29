<?php

namespace Index\Model;

/**
 * Class OrderModel
 * @package Index\Model
 */
class OrderModel extends \Common\Model\BaseModel {
    /**
     * @var string 数据表名
     */
    protected $trueTableName = 'order';
    // 来源类型
    /** 订单来源订单系统 */
    CONST FROM_ORDER_SYS = 1;
    /**
     * 订单来自淘宝平台
     */
    const FROM_TAOBAO = 2;
    /**
     * 订单来自微小店平台
     */
    const FROM_WEIDIAN = 3;
    
    // 支付类型
    /** 错误支付类型 */
    CONST PAY_TYPE_UNKNOW = 0;
    /** 支付宝支付类型 */
    CONST PAY_TYPE_ALI = 1;
    /** 微信宝支付类型 */
    CONST PAY_TYPE_WX = 2;

    // 取消状态
    CONST STATUS_CANCEL = 1;

    /**
     * @var array 支付宝支付类型配置
     */
    static public $payType = [
        'checkTypes' => [OrderModel::PAY_TYPE_ALI, OrderModel::PAY_TYPE_WX]
    ];

    /** 添加订单接口
     * @param array $data 添加信息
     * @return mixed
     */
    public function addOrder ($data) {
        return $this->add($data);
    }

    /** 基础保存接口
     * @param array | string $where 保存塞选条件
     * @param array | string $data 保存数据
     * @return bool 返回值
     */
    public function saveOrder($where, $data) {
        return $this->where($where)
            ->save($data);
    }

    /** 添加订单接口
     * @param int $uid 用户 id
     * @param int $from 订单来源
     * @return mixed
     */
    public function createOrder ($uid, $from = OrderModel::FROM_ORDER_SYS) {
        return $this->addOrder(['uid' => $uid, 'from' => $from, 'order_no' => time().rand(1000, 9999)]);
    }

    /** 保存联系方式接口
     * @param int $id 订单 id
     * @param int $uid 用户 id
     * @param string $name 联系姓名名
     * @param string $phone 联系电话
     * @param string $province 联系地址 -- 省
     * @param string $city 联系地址 -- 市
     * @param string $area 联系地址 -- 区
     * @param string $street 联系地址 -- 街道
     * @param int $price 支付价格
     * @return bool
     */
    public function setContacts ($id, $uid, $name, $phone, $province, $city, $area, $street, $price) {
        foreach(['name', 'phone', 'province', 'city', 'area', 'area', 'street'] as $v) {
           if(!($ $v = trim($ $v))) return false;
        }
        $where = ['id' => $id,];
        !is_null($uid) && $where['uid'] = $uid;

        return $this->saveOrder($where, [
            'pay_price' => $price,
            'contacts_name' => $name,
            'contacts_phone' => $phone,
            'contacts_province' => $province,
            'contacts_city' => $city,
            'contacts_area' => $area,
            'contacts_street' => $street,
        ]);
    }

    /** 设置支付状态
     * @param int $id 订单 id
     * @param int $uid 用户 id
     * @param int $payType 支付类型
     * @return mixed
     */
    public function setPay($id, $uid, $payType) {
        $where = ['id' => $id];
        !is_null($uid) && $where['uid'] = $uid;

        if(!in_array($payType, OrderModel::$payType['checkTypes'])) {
            $payType = OrderModel::PAY_TYPE_ERROR;
        }

        return $this->saveOrder($where, [
            'pay_type' => $payType,
            'pay_time' => date("Y-m-d H:i:s"),
        ]);
    }

    /** 设置物流号
     * @param int $orderno 订单号
     * @param int $expressNum 物流号
     * @return bool
     */
    public function setExpressNum($orderno, $expressNum) {
        if(!($expressNum = trim($expressNum)))
            return false;
        $where = ['orderno' => $orderno];

        return $this->saveOrder($where, ['mailno' => $expressNum]);
    }

    /** 获取一条数据
     * @param mixed $where 查询条件
     * @param mixed $field 获取字段
     * @param mixed $order 排序规则
     * @return mixed
     */
    public function findOrder($where, $field = null, $order = null)
    {
        foreach(['where', 'field', 'order'] as $v) {
            !empty($$v) && $this->$v ($$v);
        }
        return $this->find();
    }

    /** 查询订单列表
     * @param mixed $where 查询条件
     * @param mixed $limit 查询条数限制
     * @param mixed  $field 查询字段
     * @param mixed $order 排序规则
     * @return mixed
     */
    public function findOrderList($where, $limit = null, $field = null, $order = null)
    {
        foreach(['where', 'field', 'order'] as $v) {
            !empty($$v) && $this->$v ($$v);
        }

        if(!empty($limit)) {
            if(is_string($limit)) {
                $this->limit($limit);
            }
            if(is_array($limit)) {
                list($offset, $count) = $limit;
                $this->limit($offset, $count);
            }
        }

        return $this->select();
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
                ->join('LEFT JOIN user_info ON order.uid = user_info.uid')
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
