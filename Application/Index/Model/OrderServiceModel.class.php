<?php
namespace Index\Model;

use Warehouse\Model\CategoryModel as NormalModel;

/**
 * Class OrderServiceModel
 * @package Index\Model
 */
class OrderServiceModel
{
    /**
     * @var array 分组类型配置
     */
    static public $GROUP_TYPE = [
        'normalGroup' => [OrderRelationShipModel::GOODS_TYPE_NORMAL],
        'printGroup' => [OrderRelationShipModel::GOODS_TYPE_PRINT_NORMAL, OrderRelationShipModel::GOODS_TYPE_PRINT_WXS, OrderRelationShipModel::GOODS_TYPE_PRINT_ZPK]
    ];

    /**
     * @var OrderModel
     */
    public $order_m;
    /**
     * @var OrderRelationShipModel
     */
    public $relation_m;
    /**
     * @var PrintItemModel
     */
    public $orderinfo_m;
    /**
     * @var NormalModel
     */
    public $normal_m;

    /**
     * 构造函数
     */
    public function __construct() {
         $this->order_m = (new OrderModel());
         $this->relation_m = (new OrderRelationShipModel());
         $this->orderinfo_m = (new PrintItemModel());
         $this->normal_m = (new NormalModel());
    }

    /** 获取订单信息
     * @param int $id 订单 id
     * @return mixed
     */
    public function getCard($id)
    {
        return $this->findPrintItem(['id'=>$id]);
    }

    /** 创建新订单
     * @param array $info 订单信息
     * @return int |false
     */
    public function newCardData($info)
    {
        return $this->addPrintItem($info);
    }

    /** 转换打印数据为订单数据
     * @param $data
     * @return array
     */
    public function converToOrderData($data)
    {
        $order = [];
        foreach(
            [
                'uid' => 'uid',
                'orderno' => 'orderno',
                'price' => 'price',
                'paidTime' => 'paidTime',
                'mailno' => 'mailno',
                'name' => 'name',
                'phone' => 'phone',
                'province' => 'province',
                'city' => 'city',
                'area' => 'area',
                'street' => 'street',
                'is_del' => 'del'
            ]
            as $printField  => $orderField)
        {
            isset($data[$printField]) && $order[$orderField] = $data[$printField];
        }

        if(isset($data['pay_type'])) { // 支付类型装换
            $payTypeMap =  ['wx' => OrderModel::PAY_TYPE_WX, 'alipay' => OrderModel::PAY_TYPE_ALI];
            $order['pay_type'] = OrderModel::PAY_TYPE_UNKNOW;
            $order['pay_type'] = $payTypeMap[$data['pay_type']];
        }

        if(isset($data['coupon_id']) && isset($order['price'])) { // 价格转换
            $couponModel = new CouponModel();
            $coupoon = $couponModel->getCoupon(null, $data['coupon_id']);
            if($coupoon && $coupoon['ex_data'] && $coupoon['ex_data']['reduce_cost']) {
                $order['price'] -= ($coupoon['ex_data']['reduce_cost'] / 100);
            }
            $order['price'] = $order['price'] > 0 ? $order['price'] : 0;
        }

//        // 订单状态处理
//        if(isset($data['status']) && in_array($data['status'] ,PrintItemModel::$ORDER_STATUS['cancel'])) {
//            $order['status'] =['exp', 'status|'.(~OrderModel::STATUS_CANCEL)];
//        }
        $order['update_time'] = date("Y-m-d H:i:s");
        return $order;
    }

    /** 检查数据是否要同步到 order 里面
     * @param array $data 保存数据
     * @return bool
     */
    public function checkNeedToUpdateToOrder($data)
    {
        $needChangeOrder = false;
        $checkFields = ['uid', 'orderno', 'price', 'paidTime', 'mailno', 'name', 'phone', 'province', 'city', 'area', 'street', 'pay_type', 'coupon_id', 'status', 'is_del'];
        foreach($data as $key => $value) {
            $needChangeOrder = in_array($key, $checkFields);
            if($needChangeOrder) break;
        }
        return $needChangeOrder;
    }

    /** 转换打印数据为订单关系数据
     * @param $data
     * @return array
     */
    public function converToRelationshipData($data)
    {
        $relationship = [];
        if($data['name'] || $data['phone'] || $data['province'] || $data['city'] || $data['area'] || $data['street']) {
            $relationship['uhash'] = OrderRelationShipModel::addressMd5(
                [$data['name'], $data['phone'], $data['province'], $data['city'], $data['area'], $data['street']]
            );
        }
        // 商品类型转换
        $relationship['goods_type'] = OrderRelationShipModel::GOODS_TYPE_PRINT_NORMAL;
        foreach([
                    'wechatAlbum' => OrderRelationShipModel::GOODS_TYPE_PRINT_WXS,
                    'cardAlbum' => OrderRelationShipModel::GOODS_TYPE_PRINT_ZPK,
                ]
                as $k => $v)
        {
            in_array($data['sys'], PrintItemModel::$SYS_TYPE[$k]) && $relationship['goods_type'] = $v;
        }

        // 取消订单, 支付订单处理
        if(isset($data['paidTime']) && !empty($data['paidTime'])) {
            $relationship['status'] = ['exp', 'status|'.OrderRelationShipModel::ORDER_STATUS_PAID];
        }

        // 状态处理
        if( $data['is_del'] == 1
            || (isset($data['status']) && in_array($data['status'] ,PrintItemModel::$ORDER_STATUS['cancel'])) )
        {
            $exp = 'status&'.(~OrderRelationShipModel::ORDER_STATUS_USE);
            if(isset($relationship['status'])) {
                $exp = '('.$relationship['status'][1].')&'.(~OrderRelationShipModel::ORDER_STATUS_USE);
            }
            $relationship['status'] = ['exp', $exp];
        }

        foreach(['orderno' => 'orderno', 'uid' => 'uid'] as $orderField => $relationshipField) {
            isset($data[$orderField]) && $relationship[$relationshipField] = $data[$orderField];
        }
        //$relationship['update_time'] = date("Y-m-d H:i:s");
        return $relationship;
    }

    /** 转换打印数据为订单关系数据
     * @param $data
     * @return array
     */
    public function checkNeedToUpdateToRelationship($data)
    {
        $needChange = false;
        $checkFields = ['paidTime','name', 'phone', 'province', 'city', 'area', 'street', 'status', 'is_del'];
        foreach($data as $key => $value) {
            $needChange = in_array($key, $checkFields);
            if($needChange) break;
        }
        return $needChange;
    }

    /** 保存订单基本接口 注意 orderno 字段会被此函数取消掉
     * @param mixed $where 保存的过滤条件
     * @param array $data 保存数据
     * @return bool
     */
    public function savePrintItem($where, $data)
    {
        // 事务开始
        $this->order_m->startTrans();

        if($this->checkNeedToUpdateToOrder($data)) {
            // 判断是否要更新到订单表
            $relationShipWhere = isset($where['cid']) ?
                [ 'goods_id' => $where['cid']] :
                [ 'orderno' => $where['orderno']];
            $relationship = $this->relation_m->baseFind(
                $relationShipWhere,
                ['id', 'order_id']
            );
            if(!$relationship) { // 查询失败
                $this->order_m->rollback();
                return null;
            }
            if($this->checkNeedToUpdateToRelationship($data)) {
                // 判断是否要更新到数据库
                $ret = $this->relation_m->baseSave(
                    ['order_id' => $relationship['order_id']],
                    $this->converToRelationshipData($data)
                );
                if(!$ret) {
                    $this->order_m->rollback();
                    return null;
                }
            }
            // 更新订单
            $ret = $this->order_m->saveOrder(
                ['id'=>$relationship['order_id']],
                $this->converToOrderData($data)
            );
            if(!$ret) {
                $this->order_m->rollback();
                return null;
            }
        }

        $ret = $this->orderinfo_m->savePrintItem($where, $data);
        if(!$ret) {
            $this->order_m->rollback();
            return null;
        }

        $this->order_m->commit();
        return true;
    }

    /** 添加打印数据
     * @param array $data 添加的数据
     * @return mixed
     */
    public function addPrintItem($data)
    {
        // 事务开始
        $this->order_m->startTrans();

        $time = time();
        !isset($data['orderno']) && $data['orderno'] = $time.rand(1000, 9999);

        // 添加订单
        $order = $this->converToOrderData($data);
        $order['from'] = OrderModel::FROM_ORDER_SYS;
        $orderId = $this->order_m->addOrder($order);

        if(!$orderId) {
            // 插入失败回滚
            $this->order_m->rollback();
            return false;
        }

        //商品表
        $printItemId = $this->orderinfo_m->add($data);
        if(!$printItemId) {
            // 插入失败回滚
            $this->order_m->rollback();
            return false;
        }

        // 添加关系
        $relationship = $this->converToRelationshipData($data);
        $relationId = $this->relation_m->addRelatioship($orderId,
            $relationship['goods_type'],
            $printItemId,
            1,
            $relationship['uhash'],
            $data['orderno'],
            $data['orderno']);

        if(!$relationId) {
            // 插入失败回滚
            $this->order_m->rollback();
            return false;
        }

        // 事务结束
        $this->order_m->commit();
        // 返回订单 id
        return $orderId;
    }

    /** 查找指定的一个订单
     * @param string|array $where 查询条件
     * @param null|string|array $field 返回字段
     * @param null|string $order 排序规则
     * @return mixed
     */
    public function findPrintItem($where, $field = null, $order = null)
    {
        // return all info
        $order_res = $this->order_m->findOrder($where, $field, $order);
        // echo '<pre>';var_dump($order_res);die;
        if(!empty($order_res) && is_array($order_res)){
            $order_res['extra_relation_info'] = [
                'normal' => [],
                'print' => []
            ];
            $relation_res = $this->relation_m->baseGet(['order_id' => $order_res['id']]);
            // echo '<pre>';var_dump($relation_res);die;
            $normal_arr = &$order_res['extra_relation_info']['normal'];
            $print_arr = &$order_res['extra_relation_info']['print'];
            foreach ($relation_res as $relation_value){
                $goods_type = intval($relation_value['goods_type']);
                $goods_id = $relation_value['goods_id'];
                // 定制品
                if(in_array(intval($goods_type), OrderServiceModel::$GROUP_TYPE['printGroup'])){
                    $orderinfo_res = $this->orderinfo_m->getCard($goods_id);
                    array_push($print_arr, $orderinfo_res);
                    // echo '<pre>';var_dump($orderinfo_res);die;
                }
                // 普通品
                if(in_array(intval($goods_type), OrderServiceModel::$GROUP_TYPE['normalGroup'])){
                    $normalinfo_res = $this->normal_m->getCategory($goods_id);
                    $normalinfo_res['order_count'] = $relation_value['count'];
                    array_push($normal_arr, $normalinfo_res);
                    // echo '<pre>';var_dump($normalinfo_res);die;
                }
            }
            return $order_res;
        }else{
            return null;
        }
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
        return $this->savePrintItem($where, ['mailno' => $expressNum]);
    }

    /** 保存订单信息
     * @param int $cid 订单 id
     * @param array $info 订单信息
     * @return bool
     */
    public function saveCard($cid, $info)
    {
        return $this->savePrintItem(['cid' => $cid], $info);
    }

    /** 保存为支付订单信息(微信书专用,因为微信书能允许非uid所有者变更订单信息,所以独立接口)
     * @param int $cid 订单 id
     * @param array $info 保存信息
     * @return bool
     */
    public function saveCardDataConditionWxs($cid, $info)
    {
        return $this->savePrintItem(['cid' => $cid, 'sys' => 'wxs', 'paidTime' => ['exp', ' IS NULL'] ], $info);
    }

    /** 保存订单信息
     * @param int $uid 用户 id
     * @param int $cid 订单 id
     * @param array $info 保存数据
     * @return bool
     */
    public function saveCardData($uid, $cid, $info)
    {
        return $this->savePrintItem(['cid' => $cid, 'uid' => $uid], $info);
    }

    /** 转换打印数据为订单关系数据
     * @param $data
     * @return array
     */
    public function checkOrder2Relationship($data)
    {
        $needChange = false;
        $checkFields = ['paidTime','name', 'phone', 'province', 'city', 'area', 'street', 'status', 'del'];
        foreach($data as $key => $value) {
            $needChange = in_array($key, $checkFields);
            if($needChange) break;
        }
        return $needChange;
    }

    /** 转换打印数据为订单关系数据
     * @param $data
     * @return array
     */
    public function convertOrder2Relationship($data)
    {
        $relationship = [];
        if($data['name'] || $data['phone'] || $data['province'] || $data['city'] || $data['area'] || $data['street']) {
            $relationship['uhash'] = OrderRelationShipModel::addressMd5(
                [$data['name'], $data['phone'], $data['province'], $data['city'], $data['area'], $data['street']]
            );
        }

        // 取消订单, 支付订单处理
        if(isset($data['paidTime']) && !empty($data['paidTime'])) {
            $relationship['status'] = ['exp', 'status|'.OrderRelationShipModel::ORDER_STATUS_PAID];
        }

        // 状态处理
        if( $data['del'] == 1) {
            $exp = 'status&'.(~OrderRelationShipModel::ORDER_STATUS_USE);
            if(isset($relationship['status'])) {
                $exp = '('.$relationship['status'][1].')&'.(~OrderRelationShipModel::ORDER_STATUS_USE);
            }
            $relationship['status'] = ['exp', $exp];
        }
        return $relationship;
    }

    /** 保存订单
     * @param array $where 保存条件
     * @param array $data 保存数据
     * @return bool|null
     */
    public function saveOrder($where, $data)
    {
        // 事务开始
        $this->order_m->startTrans();

        if($this->checkOrder2Relationship($data)) {
            // 判断是否要更新到订单表
            $relationShipWhere = isset($where['id']) ?
                [ 'order_id' => $where['id']] :
                [ 'orderno' => $where['orderno']];

            // 更新关系到数据库
            $ret = $this->relation_m->baseSave(
                $relationShipWhere,
                $this->converToRelationshipData($data)
            );
            if(!$ret) {
                $this->order_m->rollback();
                return null;
            }
        }

        // 更新订单
        $ret = $this->order_m->saveOrder($where, $data);
        if(!$ret) {
            $this->order_m->rollback();
            return null;
        }

        $this->order_m->commit();
        return true;
    }

    /** 删除订单
     * @param int $uid 用户 id
     * @param int $cid 订单 id
     * @return bool
     */
    public function deletePrintOrder($uid, $cid)
    {
        return $this->savePrintItem(
            ['cid' => $cid, 'uid'=>$uid, 'is_del' => 0],
            ['is_del' => 1, "coupon_id" => 0]
        );
    }
}