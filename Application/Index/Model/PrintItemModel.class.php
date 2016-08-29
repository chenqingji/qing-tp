<?php
namespace Index\Model;

/**
 * Class PrintItemModel
 * @package Index\Model
 */
class PrintItemModel extends \Common\Model\BaseModel {
    /////////////////////////////////////////////
    // 订单状态
    /** 订单初始状态(还有为上传的图片或者没有图片[没有图片仅在 app 可能会出现]) */
    const STATUS_INIT = 0;
    /** 订单提交状态  */
    const STATUS_COMMIT = 1;
    /** 订单支付状态 */
    const STATUS_PAID = 10;
    /** 订单取消状态(创建超过3天的订单自动取消) */
    const STATUS_CANCEL_BY_TIME = 11;
    /** 订单取消状态(被客户取消) */
    const STATUS_CANCEL_BY_CUSTOMER = 12;
    /** 订单取消状态(被客服取消) */
    const STATUS_CANCEL_BY_ADMIN = 17;

    // 订单来源
    /** 微信 ios */
    const FROM_YIN_WX_IOS = 'ios';
    /** 微信 android */
    const FROM_YIN_WX_ANDROID = 'android';
    /** 微信其他平台手机 */
    const FROM_YIN_WX_OTHERS = 'others';
    /** 魔力相册一键打印 */
    const FROM_YIN_MAGIC_PRINT = 'moliWeb';
    /** 魔力相册一键打印 app(安卓端) */
    const FROM_YIN_MAGIC_PRINT_APP_ANDROID = 'moliAndroidAlbum';
    /** 魔力相册一键打印 app(ios 端) */
    const FROM_YIN_MAGIC_PRINT_APP_IOS = 'moliIosAlbum';
    /** 魔力相册 app(安卓端) */
    const FROM_YIN_MAGIC_APP_ANDROID = 'moliAndroid';
    /** 魔力相册 app(ios 端) */
    const FROM_YIN_MAGIC_APP_IOS = 'moliIos';
    /** 魔力相册 app(安卓端) */
    const FROM_YIN_ZPK = 'zpk';
    /** 魔力相册 app(ios 端) */
    const FROM_YIN_WXS = 'wxs';

    /**
     * @var string 模块对应表名
     */
    public $trueTableName = 'order_info';

    /** 获取通用字段
     * @return array
     */
    public function getCommonFields()
    {
        return [
            'cid','uid','orderno','pics','photo_number','create_time','coupon_id', // 订单信息
            'photo_fee','postage','price','mailno','paidTime','status', 'sys', // 支付相关信息
            'name','phone','province','city','area','street', 'express_type' // 联系方式
        ];
    }

    /**
     * @var array 图片类型配置
     */
    static public $PIC_TYPE = [
        'wxType' => 'wx',
        'uploadType' => 'upyun',
        'wxLocalType' => 'wxLocal',
    ];

    /**
     * @var array 系统类型配置
     */
    static public $SYS_TYPE = [
        'magicAlbum' => [PrintItemModel::FROM_YIN_MAGIC_PRINT, PrintItemModel::FROM_YIN_MAGIC_PRINT_APP_ANDROID, PrintItemModel::FROM_YIN_MAGIC_PRINT_APP_IOS],
        'magicAlbumApp' => [PrintItemModel::FROM_YIN_MAGIC_APP_IOS, PrintItemModel::FROM_YIN_MAGIC_APP_ANDROID],
        'wechatAlbum' => [PrintItemModel::FROM_YIN_WXS],
        'cardAlbum' => [PrintItemModel::FROM_YIN_ZPK,],
        'weChat' => [PrintItemModel::FROM_YIN_WX_IOS, PrintItemModel::FROM_YIN_WX_ANDROID, PrintItemModel::FROM_YIN_WX_OTHERS]
    ];

    /**
     * @var array 订单状态配置
     */
    static public $ORDER_STATUS = [
        'cancel' => [PrintItemModel::STATUS_CANCEL_BY_TIME, PrintItemModel::STATUS_CANCEL_BY_CUSTOMER, PrintItemModel::STATUS_CANCEL_BY_ADMIN],
        'webOrder' => [PrintItemModel::STATUS_INIT, PrintItemModel::STATUS_COMMIT, PrintItemModel::STATUS_PAID],
        'webEdit' => [PrintItemModel::STATUS_INIT, PrintItemModel::STATUS_COMMIT],
        'appOrder' => [PrintItemModel::STATUS_COMMIT, PrintItemModel::STATUS_PAID, PrintItemModel::STATUS_CANCEL_BY_TIME, PrintItemModel::STATUS_CANCEL_BY_CUSTOMER, PrintItemModel::STATUS_CANCEL_BY_ADMIN],
        'appEdit'=> [PrintItemModel::STATUS_COMMIT]
    ];

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
                'is_del' => 'del',
                'express_type' => 'express_type'
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

        // 订单状态处理
        if(isset($data['status']) && in_array($data['status'] ,PrintItemModel::$ORDER_STATUS['cancel'])) {
            $order['status'] =['exp', 'status&'.(~OrderRelationShipModel::ORDER_STATUS_USE)];
        }
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
        $checkFields = ['uid', 'orderno', 'price', 'paidTime', 'mailno', 'name', 'phone', 'province', 'city', 'area', 'street', 'pay_type', 'coupon_id', 'status', 'is_del', 'express_type'];
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
        !empty($where) && $this->where($where);

        foreach(['orderno', 'sys'] as $v) {
            unset($data[$v]);
        }

        // 更新到订单
        if( $this->checkNeedToUpdateToOrder($data)) {
            if(isset($where['cid']) || isset($where['orderno']) ) { // 通过订单 id
                // 查询关系得到订单 id
                $relaWhere = isset($where['orderno']) ?
                    [ 'orderno' => $where['orderno']] :
                    [ 'goods_id' => $where['cid']];
                $relaWhere['goods_type'] = ['in', OrderRelationShipModel::$GROUP_TYPE['printGroup']];
                $relationshipModel = new OrderRelationShipModel();
                $relationship = $relationshipModel->baseFind(
                    $relaWhere,
                    ['id', 'order_id']
                );
                if($relationship) {
                    if($relationship['id'] && $this->checkNeedToUpdateToRelationship($data)) {
                        // 更新关系表
                        $relationData =  $this->converToRelationshipData($data);
                        $relationshipModel->baseSave(
                            ['id'=>$relationship['id']],
                            $relationData
                        );
                        // 更新选购物品信息
                        $goodsRlationData = [];
                        isset($relationData['status']) && $goodsRlationData['status'] = $relationData['status'];
                        isset($relationData['uhash']) && $goodsRlationData['uhash'] = $relationData['uhash'];
                        if(!empty($goodsRlationData)) {
                            $relationshipModel->baseSave(
                                [
                                    'order_id' =>  $relationship['order_id'],
                                    'goods_type' => OrderRelationShipModel::GOODS_TYPE_NORMAL,
                                    'count' => ['gt', 0]
                                ],
                                $goodsRlationData
                            );
                        }
                    }
                    // 更新订单
                    if($relationship['order_id']) {
                        $orderModel = new OrderModel();
                        $orderModel->saveOrder(
                            ['id'=>$relationship['order_id']],
                            $this->converToOrderData($data)
                        );
                    }
                }
            }
        }

        return $this->save($data);
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

    /** 保存为支付订单信息
     * @param int $uid 用户 id
     * @param int $cid 订单 id
     * @param array $info 保存信息
     * @return bool
     */
    public function saveCardDataCondition($uid, $cid, $info)
    {
        return $this->savePrintItem(['cid' => $cid, 'uid' => $uid, 'paidTime' => ['exp', ' IS NULL']], $info);
    }

    /** 保存为支付订单信息(微信书专用,因为微信书能允许非uid所有者变更订单信息,所以独立接口)
     * @param int $cid 订单 id
     * @param array $info 保存信息
     * @return bool
     */
    public function saveCardDataConditionWxs($cid, $info)
    {
        return $this->savePrintItem(['cid' => $cid, 'sys' => 'wxs', 'paidTime' => ['exp', ' IS NULL']], $info);
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

    /** 创建新订单
     * @param array $info 订单信息
     * @return int |false
     */
    public function newCardData($info)
    {
        return $this->addPrintItem($info);
    }

    /** 添加打印数据
     * @param array $data 添加的数据
     * @return mixed
     */
    public function addPrintItem($data)
    {
        $time = time();
        !isset($data['orderno']) && $data['orderno'] = $time.rand(1000, 9999);

        //商品表
        $printItemId = $this->add($data);

        // 添加订单
        $orderModel = new OrderModel();
        $order = $this->converToOrderData($data);
        $order['from'] = OrderModel::FROM_ORDER_SYS;
//        $order['id'] = $printItemId; // new
        $orderId = $orderModel->addOrder($order);

        // 添加关系
        $relationshipModel = new OrderRelationShipModel();
        $relationship = $this->converToRelationshipData($data);
        $relationship['status'] = OrderRelationShipModel::ORDER_STATUS_USE;
//        $relationship['id'] = $printItemId; // new
        $relationship['order_id'] = $orderId; // change
        $relationship['goods_id'] = $printItemId;
        $relationship['count'] = 1;
        $relationship['product_id'] = $data['orderno'];
        $relationship['create_time'] = $relationship['update_time'] = date("Y-m-d H:i:s", $time);
        $relationshipModel->baseAdd($relationship);

        // 返回订单 id
        return $printItemId;
    }

    /** 删除订单
     * @param int $uid 用户 id
     * @param int $cid 订单 id
     * @return bool
     */
    public function deleteOrder($uid, $cid)
    {
        return $this->savePrintItem(
            ['cid' => $cid, 'uid'=>$uid, 'is_del'=>0],
            ['is_del'=>1, "coupon_id"=>0]
        );
    }

    /** 取消用户订单
     * @param int $uid 用户 id
     * @param int $cid 订单 id
     * @return bool
     */
    public function userCancelOrder($uid, $cid)
    {
        return $this->savePrintItem(
            ['cid' => $cid, 'uid'=>$uid, 'is_del'=>0],
            ['status'=>PrintItemModel::STATUS_CANCEL_BY_CUSTOMER, "coupon_id"=>0]
        );
    }

    /** 查找指定的一个订单
     * @param string|array $where 查询条件
     * @param null|string|array $field 返回字段
     * @param null|string $order 排序规则
     * @param bool $getGoods 是否获取物品信息
     * @return mixed
     */
    public function findPrintItem($where, $field = null, $order = null, $getGoods = true)
    {
        foreach(['where', 'field', 'order'] as $v) {
            !empty($$v) && $this->$v ($$v);
        }
        $ret = $this->find();

        if($ret) {
            $relationM = new OrderRelationShipModel();
            $relationship = $relationM->baseFind(
                [
                    'goods_type' => ['in', OrderRelationShipModel::$GROUP_TYPE['printGroup']],
                    'goods_id' => $ret['cid']
                ]
            );
            $price = 0;
            $goodsRelationship = [];
            if($relationship) {
                $rawList = $relationM->baseGet(
                    [
                        'goods_type' => OrderRelationShipModel::GOODS_TYPE_NORMAL,
                        'order_id' => $relationship['order_id'],
                        'count' => ['gt', 0]
                    ]
                );
                $config = C('GOODS');
                if($rawList) {
                    foreach ($rawList as $k => $v) {
                        $goodsRelationship[$v['goods_id']] = $config[$v['goods_id']];
                        $goodsRelationship[$v['goods_id']]['count'] = $v['count'];
                        $price += $v['count'] * $config[$v['goods_id']]['preferential_price'];
                    }
                    $ret['goodsPrice'] = $price;
                    !empty($price) && isset($ret['price']) && $ret['price'] += $price;
                }
            }
            $ret['goods'] = $goodsRelationship;
        }
        return $ret;
    }

    /** 获取打印订制品列表
     * @param mixed $where 查询条件
     * @param mixed $limit 查询分页
     * @param mixed $field 查询字段
     * @param mixed $order 排序规则
     * @return mixed
     */
    public function findPrintItemList($where, $limit = null, $field = null, $order = null)
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
        $rawList = $this->select();
        $retList = [];
        if($rawList) {
            foreach($rawList as $v) {
                $retList[$v['cid']] = $v;
            }
            $relationM = new OrderRelationShipModel();
            $relationships = $relationM->baseGet(
                [
                    'goods_type' => ['in', OrderRelationShipModel::$GROUP_TYPE['printGroup']],
                    'goods_id' => ['in', array_keys($retList)],
                ]
            );
            $config = C('GOODS');
            foreach($relationships as $relationship) {
                $goodsRelationship = [];
                $price = 0;
                $rawGoodsRelationshipList = $relationM->baseGet(
                    [
                        'goods_type' => OrderRelationShipModel::GOODS_TYPE_NORMAL,
                        'order_id' => $relationship['order_id'],
                        'count' => ['gt', 0]
                    ]
                );
                if($rawGoodsRelationshipList) {
                    foreach ($rawGoodsRelationshipList as $k => $v) {
                        $goodsRelationship[$v['goods_id']] = $config[$v['goods_id']];
                        $goodsRelationship[$v['goods_id']]['count'] = $v['count'];
                        $price += $v['count'] * $config[$v['goods_id']]['preferential_price'];
                    }
                }
                $retList[$relationship['goods_id']]['goods'] = $goodsRelationship;
                $retList[$relationship['goods_id']]['goodsPrice'] = $price;
                !empty($price) && isset($retList[$relationship['goods_id']]['price']) && $retList[$relationship['goods_id']]['price'] += $price;
            }
        }
        return array_values($retList);
    }

    /**
     * @param int $cid
     * @param int $goodId
     * @param int $cnt
     * @return mixed
     */
    public function addGoods($cid, $goodId, $cnt)
    {
        $relationM = new OrderRelationShipModel();
        $relationship = $relationM->baseFind(
            [
                'goods_type' => ['in', OrderRelationShipModel::$GROUP_TYPE['printGroup']],
                'goods_id' => $cid
            ]
        );
        if(!$relationship) {
            return false;
        }

        $categoryM = new \Warehouse\Model\CategoryModel();
        $category = $categoryM->getCategory($goodId);
        if(!$category) {
            return false;
        }

        $ret = $relationM->addRelatioship(
            $relationship['order_id'],
            OrderRelationShipModel::GOODS_TYPE_NORMAL,
            $goodId,
            $cnt,
            $relationship['uhash'],
            $relationship['orderno'],
            $category['category_id']
        );
        return $ret;
    }

    public function resetGoods($cid)
    {
        $relationM = new OrderRelationShipModel();
        $relationship = $relationM->baseFind(
            [
                'goods_type' => ['in', OrderRelationShipModel::$GROUP_TYPE['printGroup']],
                'goods_id' => $cid
            ]
        );
        if(!$relationship) {
            return false;
        }
        return $relationM->baseSave(
            [
                'order_id' =>  $relationship['order_id'],
                'goods_type' => OrderRelationShipModel::GOODS_TYPE_NORMAL,
                'count' => ['gt',0]
            ],
            ['count'=>0]
        );
    }

    //备注：(status:0默认，1提交未付款，10已付款，11已取消，17 ———— 不要用)
    /** 检查订单是否存在
     * @param int $cid 订单 id
     * @param int $uid 用户 id
     * @return bool
     */
    public function checkCardId($cid, $uid)
    {
        $where = ["cid"=>$cid];
        !empty($uid) && $where["uid"] = $uid;
        $exist = $this->findPrintItem($where,['cid'], null, null, false);
        return !empty($exist);
    }

    /** 获取订单信息
     * @param int $cid 订单 id
     * @param int $uid 用户 id
     * @return mixed
     */
    public function getCardInfo($cid,$uid)
    {
        $where = ["cid"=>$cid, 'uid'=> $uid,'is_del' => 0];
        return $this->findPrintItem(
            $where,
            $this->getCommonFields()
        );
    }

    /** 获取在上传状态的最后一个订单
     * @param int $uid 用户 id
     * @param int $status 订单状态
     * @return mixed
     */
    public function getLastUploadCardInfo($uid, $status)
    {
        return $this->findPrintItem(
            [
                'status'=> $status ,
                'uid' =>$uid,
                'is_del' => 0,
                'sys' => ['in', PrintItemModel::$SYS_TYPE['weChat']]
            ],
            null,
            'cid desc'
        );
    }

    /** 获取订单信息
     * @param int $cid 订单 id
     * @return mixed
     */
    public function getCard($cid)
    {
        return $this->findPrintItem(['cid'=>$cid]);
    }

    /** 获取订单信息
     * @param string $orderNo 订单号
     * @return mixed
     */
    public function getCardByOrderNo($orderNo)
    {
        return $this->findPrintItem(['orderno'=>$orderNo]);
    }

    /** 获取用户消费额度(快印里消费的金额)
     * @param $uid 用户 id
     * @return float 消费额度
     */
    public function getUserConsume($uid)
    {
        $ret = $this->where([
                'uid'=>$uid,
                'sys'=>['in',PrintItemModel::$SYS_TYPE['magicAlbumApp']],
                'status' => 10
            ])
            ->sum('price');
        return round($ret, 2);
    }

    /** 获取订单列表
     * @param int $uid 用户 id
     * @param mixed $isPaid 是否支付
     * @param int $page 查询页面索引
     * @param int $pageCount 每页查询条数
     * @return mixed
     */
    public function listCardData($uid, $isPaid=null, $page=1, $pageCount = 100)
    {
        $showSys = PrintItemModel::$SYS_TYPE['weChat'];
        C('SHOW_ALL_ORDER') && $showSys = array_merge($showSys, PrintItemModel::$SYS_TYPE['magicAlbumApp'], PrintItemModel::$SYS_TYPE['wechatAlbum'], PrintItemModel::$SYS_TYPE['cardAlbum']);
        $isPaid && $showSys = array_merge($showSys, PrintItemModel::$SYS_TYPE['magicAlbum']);
        $where = [
            'uid' => $uid,
            'sys' => ['in', $showSys],
            'photo_number' => ['gt', 0],
            'status' => ['in', PrintItemModel::$ORDER_STATUS['webOrder']],
            'is_del' => 0
        ];
        if(!is_null($isPaid)) {
            $where['paidTime'] = ['exp',' IS'.($isPaid ? ' NOT' : '').' NULL'];
            empty($isPaid) && $where['create_time'] =  ['gt', date('Y-m-d H:i:s', time() - 86400 * 3)];
        } else {
            $where['_complex'] = [
                '_logic' => 'or',
                'paidTime' => ['exp',' IS NOT NULL'],
                'create_time' => ['gt', date('Y-m-d H:i:s', time() - 86400 * 3)]
            ];
        }
        $page = $page < 1 ? 1 : $page;
        return $this->findPrintItemList($where,[($page-1)*$pageCount, $pageCount],$this->getCommonFields(),'cid desc');
    }

    /**  获取订单列表
     * @param int $uid 用户 id
     * @param int $isPaid 是否支付
     * @param int $page 页面索引
     * @param int $pageCount 每页显示条数
     * @return mixed
     */
    public function listCardDataPage($uid, $isPaid, $page, $pageCount)
    {
        $where = [
            'uid'=>$uid,
            'paidTime' => ['exp', $isPaid ? ' IS NOT NULL' : ' IS NULL'],
            'sys' => ['in',array_merge(PrintItemModel::$SYS_TYPE['weChat'], PrintItemModel::$SYS_TYPE['magicAlbum'])],
            'is_del' => 0
        ];
        $page = $page < 1 ? 1 : $page;
        return $this->findPrintItemList($where,[($page-1)*$pageCount, $pageCount],$this->getCommonFields(),'cid desc');
    }

    /** 查询 app 订单列表
     * @param int $uid 用户 id
     * @param int $status 订单状态
     * @param int $type 订单类型
     * @param int $sort 排序规则
     * @param int $wx 是否显示微信订单
     * @param int $getCoupon 是否获取绑定优惠卷的订单
     * @param int $pageOrIndex 页面索引
     * @param int $pageCount 页面显示个数
     * @return mixed
     */
    public function listCardDataPageNew($uid, $status, $type, $sort, $wx, $getCoupon, $pageOrIndex, $pageCount)
    {
        $date = date('Y-m-d 00:00:00', time() - (86400 * 3));
        $this->savePrintItem(
            ['status' => ['in', PrintItemModel::$ORDER_STATUS['webEdit']], 'uid' => $uid, 'create_time' => ['lt', $date]],
            ['status' => PrintItemModel::STATUS_CANCEL_BY_TIME]
        );

        $showSys = PrintItemModel::$SYS_TYPE['magicAlbumApp'];
        intval($wx) != 0 && $showSys = array_merge($showSys, PrintItemModel::$SYS_TYPE['weChat']);
        $showSys = array_merge($showSys, PrintItemModel::$SYS_TYPE['magicAlbum']);

        $where = [
            'uid' => $uid,
            'photo_number' => ['gt', 0],
            'is_del' => 0,
            'sys' =>  ['in', $showSys],
            'status' => ($status == 200 ? ['in', PrintItemModel::$ORDER_STATUS['appOrder']] : $status)
        ];
        empty($getCoupon) && $where['coupon_id'] = 0;
        $sort = ($sort == 'pay'? 'paidTime desc' : 'cid desc');

        $page = $pageOrIndex < 1 ? 1 : $pageOrIndex;
        $limit = ($type == 1 ? [$pageOrIndex, $pageCount] : [($page-1)*$pageCount, $pageCount]);

        $orderRawList = $this->findPrintItemList($where, $limit, $this->getCommonFields(), $sort);
        return $orderRawList;
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

    /** 更新订单图片数
     * @param string $orderno 订单号
     * @param int $success_number 添加图片数
     */
    public function updatePhotoNumber($orderno, $success_number)
    {
        $ret = $this->savePrintItem(
            ['orderno'=>$orderno],
            ['photo_number'=>['exp', 'photo_number+'.$success_number]]
        );
        $add_info = $orderno.'补充图片'.$success_number.($ret === false? '失败' : '成功');
        M()->table('admin_log')->add(['info' => $add_info, 'type' => 'add_pic', 'uid' => 4120, 'orderno' => $orderno]);
    }

    public function getAppFields()
    {
        return [
            "cid","uid","aid","orderno","pics","message","create_time","sys", 'express_type',
            "photo_fee","postage","price","name","phone","province","city","area","street",
            "status","mailno","photo_number","paidTime","pay_type","print_size","coupon_id"
        ];
    }

    public function appGetCard($cid,$uid)
    {
        return $this->findPrintItem(['cid'=>$cid, 'uid'=>$uid], $this->getAppFields());
    }

    public function appGetCardNew($cid){
        return $this->findPrintItem(['cid'=>$cid], $this->getAppFields());
    }
/////////////////////////////////////////////
// 旧的接口
//////////////////////////////////////////////////////////
// 后台接口
    public function getField() {
        return [
            // order info
            'order_info.cid'=>'cid',
            'order_info.uid'=>'uid',
            'order_info.orderno'=>'orderno',
            'order_info.name'=>'name',
            'order_info.phone'=>'phone',
            'order_info.province'=>'province',
            'order_info.city'=>'city',
            'order_info.area'=>'area',
            'order_info.street'=> 'street',
            'order_info.message'=>'message',
            'order_info.create_time'=>'create_time',
            'order_info.paidTime'=>'paidTime',
            'order_info.sys'=>'sys',
            'order_info.status'=>'status',
            'order_info.mailno'=>'mailno',
            'order_info.is_pdf'=>'is_pdf',
            'order_info.is_sync'=>'is_sync',
            'order_info.pdf_file'=>'pdf_file',
            'order_info.op_status'=>'op_status',
            'order_info.pics'=>'pics',
            // user info
            'user_info.avatar' => 'avatar',
            'user_info.nickname' => 'nickname'
        ];
    }

    // 控制台获取订单列表
    public function searchCardData(int $page,int $limit, $condition) {
        try {
            return  M()->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page,$limit)
                ->join('LEFT JOIN user_info ON order_info.uid = user_info.uid')
                ->field($this->getField())
                ->select();
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    // 搜索联系人
    public function searchContactData(int $page,int $limit, $name, $condition) {
        try {
            $m = M();

            if(!empty($condition)) {
                $condition .= " AND ";
            }
            $condition.= "name='$name'";

            $ret = $m->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page,$limit)
                ->join( 'LEFT JOIN user_info ON order_info.uid = user_info.uid')
                ->field($this->getField())
                ->select();

            return $ret;
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    public function searchAppData(int $page,int $limit, $condition) {
        try {
            $m = M();

            if(!empty($condition)) {
                $condition .= " AND ";
            }
            $condition.= 'sys IN (\'moliAndroid\', \'moliIos\')';

            $ret = $m->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page, $limit)
                ->join(array( 'LEFT JOIN user_info ON order_info.uid = user_info.uid'))
                ->field($this->getField())
                ->select();

            return $ret;

        } catch (\Exception $ex) {
            E('searchSyncFailData_ERR');
        }
    }

    public function searchSyncFailData(int $page,int $limit) {
        try {
            $m = M();

            $ret = $m->table('order_info')
                ->where('is_sync=2 OR is_sync=5')
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page,$limit)
                ->join(
                    array(
                        'LEFT JOIN address_info ON order_info.aid = address_info.id',
                        'LEFT JOIN user_info ON order_info.uid = user_info.uid'
                    )
                )
                ->field($this->getField())
                ->select();

            return $ret;

        } catch (\Exception $ex) {
            E('searchSyncFailData_ERR');
        }
    }

    public function searchAuditFailData(int $page, int $limit, int $is_sync) {
        try {

            $m = M();

            $ret = $m->table('order_info')
                ->where('is_sync='.$is_sync)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page,$limit)
                ->join(
                    array(
                        'LEFT JOIN address_info ON order_info.aid = address_info.id',
                        'LEFT JOIN user_info ON order_info.uid = user_info.uid'
                    )
                )
                ->field($this->getField())
                ->select();

            return $ret;

        } catch (\Exception $ex) {
            E('searchAuditFailData_ERR');
        }
    }

    // 搜索微信号
    public function searchWxData(int $page,int $limit, $name, $condition) {
        try {
            $m = M();

            $ids = $m->table('user_info')
                ->where("nickname='%s'", array($name))
                ->select();
            foreach($ids as $k=>$v) {
                $ids[$k] = $v['uid'];
            }

            if(!empty($condition)) {
                $condition .= " AND ";
            }
            $condition.= "`order_info`.`uid` in(".implode(",",$ids).")";

            $ret = $m->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page,$limit)
                ->join('LEFT JOIN user_info ON order_info.uid = user_info.uid')
                ->field($this->getField())
                ->select();

            return $ret;
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    // 搜索快递号
    public function searchExpressData(int $page,int $limit, $mailno, $condition) {
        try {
            $m = M();

            if(!empty($condition)) {
                $condition .= " AND ";
            }
            $condition.= "mailno='$mailno'";

            $ret = $m->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit * $page, $limit)
                ->join( 'LEFT JOIN user_info ON order_info.uid = user_info.uid')
                ->field($this->getField())
                ->select();

            return $ret;
        } catch (\Exception $ex) {
            E('searchWxData_ERR');
        }
    }

    // 搜索电话号码
    public function searchPhoneData(int $page,int $limit, $phone, $condition) {
        try {
            $m = M();

            if(!empty($condition)) {
                $condition .= " AND ";
            }
            $condition.= "phone='$phone'";

            $ret = $m->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit * $page, $limit)
                ->join( 'LEFT JOIN user_info ON order_info.uid = user_info.uid')
                ->field($this->getField())
                ->select();

            return $ret;
        } catch (\Exception $ex) {
            E('searchWxData_ERR');
        }
    }

    // 获取订单总数
    public function getTotalCardCount() {
        try {
            return  M()->table('order_info')
                ->where("paidTime IS NOT NULL")
                ->count();
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    // 获取订单总数
    public function getOrderCount($time, $type) {
        try {
            $today = date("Y-m-d", $time);
            return M()->table('order_info')
                ->field('count(sys) as count, sys')
                ->where("$type BETWEEN '$today 00:00:00' AND '$today 23:59:59'")
                ->group('sys')
                ->select();
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    public function getTodayPayOrderCount() {
       return $this->getOrderCount(time(), "paidTime");
    }
    public function getTodayCreateOrderCount() {
        return $this->getOrderCount(time(), "create_time");
    }
    public function getYesterdayPayOrderCount() {
        $now = time();
        return $this->getOrderCount($now - ($now % 86400) - 86400, "paidTime");
    }
    public function getYesterdayCreateOrderCount() {
        $now = time();
        return $this->getOrderCount($now - ($now % 86400) - 86400 , "create_time");
    }
    
    // 获取本月订单总数
    public function getMonthCardCount() {
    	try {
    		$firstday = date('Y-m-01');					//当前月第一天
    		$lastday = date('Y-m-t');					//当前月最后一天
    		return  M()->table('order_info')
    		->where("paidTime BETWEEN '".$firstday." 00:00:00' AND '".$lastday." 23:59:59'")
    		->count();
    	} catch (\Exception $ex) {
    		E('getMonthCardCount_ERR');
    	}
    }
    
    //获取上月订单总数
    public function getPreMonthCardCount($timeStr){
    	try {
    		$firstday = date('Y-m-01', strtotime($timeStr));			//上月第一天
    		$lastday = date('Y-m-t', strtotime($timeStr));				//上月最后一天
    		return  M()->table('order_info')
        		->where("paidTime BETWEEN '".$firstday." 00:00:00' AND '".$lastday." 23:59:59'")
        		->count();
    	} catch (\Exception $ex) {
    		E('getReMonthCardCount_ERR');
    	}
    }

    // 获取订单总数
    public function getNotDownloadCount() {
        try {
            return  M()->table('order_info')
                ->where("op_status = 0 AND paidTime IS NOT NULL")
                ->count();
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    // 更新订单地址信息
    public function updateCardData($cid, $info) {
        return $this->savePrintItem(['cid' => $cid, 'mailno' => ['exp', ' IS NULL']], $info);
    }

    public function resetAllOrder(){
        try {
            $rs = 0;
            $m = M();

            $result = $m->query("UPDATE order_info SET is_sync=0, is_pdf=0, mailno=NULL WHERE is_sync=5 OR is_sync=2");
            if($result === false){
                $reset_info = '重置所有订单失败';
            }else{
                $reset_info = '重置所有订单成功';
            }
            $m->query("INSERT INTO admin_log (info, type, uid, orderno) VALUES ('".$reset_info."', 'reset_order', '4120', '0')");

            return $rs;
        } catch (\Exception $ex) {
            E('resetAllOrder_ERR');
        }
    }

    // 重置订单
    public function resetCardData($cid,$oid) {
        try {
            $rs = 0;
            $m = M();
            $order = $m->table('order_info')
                ->where("cid=$cid AND orderno=$oid")
                ->find();

            if($order) {
                $result = $m->query("UPDATE order_info SET is_sync=0, is_pdf=0, mailno=NULL WHERE cid=$cid");
                if($result === false){
                    $reset_info = $oid.'重置订单失败';
                }else{
                    $reset_info = $oid.'重置订单成功';
                }
                $m->query("INSERT INTO admin_log (info, type, uid, orderno) VALUES ('".$reset_info."', 'reset_order', '4120', '".$oid."')");

                $packageM = new PackageModel();
                $rs = $packageM->resetPackage($oid);
            }

            return $rs;
        } catch (\Exception $ex) {
            E('saveCardData_ERR');
        }
    }

    // 取消订单
    public function cancelCardData($cid,$oid) {
        try {
            $rs = 0;
            $m = M();
            $order = $m->table('order_info')
                ->where("cid=$cid AND orderno=$oid")
                ->find();

            if($order['coupon_id']) {
                $m->table('user_coupon_info USE INDEX(order_id)')
                    ->where("order_id=$cid")
                    ->save(array(
                        'order_id'=>0,
                        'used' => 0,
                        'unlock_time'=>0
                    ));
            }

            if($order) {
                /*
                if($order['is_sync'] != 0){
                    return 1;
                }else{
                    $m->query("UPDATE order_info SET status=17 WHERE cid=$cid");
                }
                */
                $m->query("UPDATE order_info SET status=17,coupon_id=0 WHERE cid=$cid");
            }

            return $rs;
        } catch (\Exception $ex) {
            E('cancelCardData_ERR');
        }
    }
    
    //保存错误信息
    public function savePayError($type,$msg){
    	return M()->table('pay_error')
    	->data(array("type"=>$type,"msg"=>$msg))
    	->add();
    }


    /**
     * 获取指定条件的一条记录
     * @param array $where 条件
     * @param array $field * | array
     * @param string $order id=>dsc,do=>asc,name 默认asc
     * @return false|null|array 一维数组
     */
    public function baseFind($where = array(), $field = "*", $order = array()) {
        try {
            $m = M("order_info");
            $m->field($field)->where($where);
            if (!empty($order)) {
                $m->order($order);
            }
            return $m->find();
        } catch (\Exception $ex) {
            E('baseFind ERR.');
        }
    }

    /**
     * 获取指定条件的记录
     * @param array $where 条件
     * @param array $field * | array
     * @param string $order id=>dsc,do=>asc,name 默认asc
     * @param string $limit '0,10' 10
     * @return false|null|array 二维数组
     */
    public function baseGet($where = array(), $field = "*", $order = array(),$limit='') {
        try {
            $m = M("order_info");
            $m->field($field)->where($where);
            if (!empty($order)) {
                $m->order($order);
            }
            if(!empty($limit)){
                $m->limit($limit);
            }
            return $m->select();
        } catch (\Exception $ex) {
            E('baseGet ERR.');
        }
    }

    /**
     * 更新订单进度process
     * @param array $ordernos
     * @return int|false
     */
    public function orderToPicking($ordernos) {
        $ordernoString = "'" . implode("','" , $ordernos) . "'";
        $m = M("order_info");
        return $m->where(array("orderno" => array("in", $ordernoString)))->save(array("process" => self::PROCESS_PICKED));
    }

    /**
     * 订单详情
     * @return string
     */
   /* public function dealOrderInfoData($cid,$uid){
        $orderData = $this->getCardInfo();
    }*/
}