<?php
namespace Index\Model;


class Coupon2Model extends \Common\Model\BaseModel
{
    /** 免费类型
     * @var int
     */
    static $FREE_TYPE = 1;
    /** 减免类型
     * @var int
     */
    static $REDUCE_TYPE = 2;

    /** 表名
     * @var string
     */
    protected $trueTableName = 'user_coupon_info';

    /** 基础添加接口
     * @param array $data 添加信息
     * @return mixed
     */
    public function _baseAdd ($data) {
        return $this->add($data);
    }

    /** 基础保存接口
     * @param array | string $where 保存塞选条件
     * @param array | string $data 保存数据
     * @param string|null $index 索引名
     * @return bool 返回值
     */
    public function _baseSave($where, $data, $index = null) {
        $index && $this->index($index);
        return $this->where($where)
            ->save($data);
    }

    /**
     * 获取指定条件的一条记录
     * @param array $where 条件
     * @param mixed $field * | array
     * @param mixed $order id=>dsc,do=>asc,name 默认asc
     * @param mixed $index 索引名
     * @return false|null|array 一维数组
     */
    public function _baseFind($where = [], $field = null, $order = null, $index = null)
    {
        foreach(['where', 'field', 'order', 'index'] as $v) {
            !empty($$v) && $this->$v ($$v);
        }
        return $this->find();
    }

    /** 获取打印订制品列表
     * @param mixed $where 查询条件
     * @param mixed $limit 查询分页
     * @param mixed $field 查询字段
     * @param mixed $order 排序规则
     * @param string $index 查询索引
     * @return mixed
     */
    public function _baseSelect($where, $limit = null, $field = null, $order = null, $index = null)
    {
        foreach(['where', 'field', 'order', 'index'] as $v) {
            !empty($$v) && $this->$v ($$v);
        }
        if(!empty($limit)) {
            is_array($limit) && $limit = implode(',',$limit);
            $this->limit($limit);
        }
        return $this->select();
    }

    /** 获取通用字段数组
     * @return array
     */
    protected function commonFields()
    {
        return ['id','type','sub_type','ex_data','expiration','create_time'];
    }

    /** 添加优惠卷
     * @param int $uid 用户 id
     * @param int $type 优惠卷类型
     * @param int $subType 优惠卷子类型
     * @param string $exData 扩展数据
     * @param null $expiration 超时时间
     * @return mixed
     */
    public function addCoupon($uid, $type, $subType, $exData, $expiration = null) {
        $data = [
            'uid' => $uid,
            'type' => $type,
            'sub_type' => $subType,
            'ex_data' => $exData
        ];
        !is_null($expiration) && $data['expiration'] = $expiration;
        return $this->_baseAdd($data);
    }

    /** 获取绑定优惠卷
     * @param int $userId 用户 id
     * @param int $couponId 优惠卷 id
     * @param int $cid 订单 id
     * @param bool|false $isPaid 是否支付
     * @return array|false|null
     */
    public function getBindCoupon($userId, $couponId, $cid, $isPaid = false)
    {
        $now = date('Y-m-d H:i:s');
        $where = ['id' => $couponId, 'uid' => $userId, 'order_id'=>$cid];
        !$isPaid && $where['expiration'] = ['gt', $now];
        $ret = $this->_baseFind(
            $where,
            $this->commonFields()
        );
        if($ret) {
            $ret['ex_data'] = $this->dealCouponExData($ret['type'], $ret['ex_data']);
        }
        return $ret;
    }

    /** 获取优惠卷列表
     * @param array|string $ids 优惠卷 id 数组
     * @return mixed
     */
    public function getCouponListByIds($ids)
    {
        return $this->_baseSelect(
            ['id'=>['in', $ids] ],
            null,
            $this->commonFields()
        );
    }

    /** 处理优惠卷
     * @param int $type 优惠卷类型
     * @param array $v 扩展数据字段
     * @return mixed
     */
    public function dealCouponExData($type, &$v) {
        $v = json_decode($v, true);
        if($type == self::$FREE_TYPE) {
            $v['reduce_cost'] = CalculateFeeModel::getFee($v['free_pic_cnt'])[2] * 100;
            $v['least_cost'] = 0;
        }
        return $v;
    }

    /** 获取优惠卷
     * @param int $userId 用户 id
     * @param int $couponId 优惠卷 id
     * @param int|null $cid 订单 id
     * @return array|false|null
     */
    public function getCoupon($userId, $couponId, $cid = null) {
        $now = date('Y-m-d H:i:s');
        $where = [
            'id' => $couponId,
            'used' => 0,
            'expiration' => $now,
            '_complex' => [
                '_logic' => 'or',
                'unlock_time' => ['lt',$now],
                'order_id' => (is_null($cid) ? "0" : $cid)
            ]
        ];
        $userId && $where['uid'] = $userId;
        $ret = $this->_baseFind($where, $this->commonFields());
        if($ret) {
            $ret['ex_data'] = $this->dealCouponExData($ret['type'], $ret['ex_data']);
        }
        return $ret;
    }

    /** 设置优惠卷锁定
     * @param int $userId 用户 id
     * @param int $couponId 优惠卷 id
     * @param int $orderId 订单id
     * @param string $lockTime 解锁时间
     * @return bool
     */
    public function setLock($userId, $couponId, $orderId, $lockTime) {
        return $this->_baseSave(
            ['id'=>$couponId, 'uid'=>$userId, 'used'=>0 ],
            ['order_id'=> $orderId, 'unlock_time'=>$lockTime]
        );
    }

    /** 解锁优惠卷
     * @param int $userId 用户 id
     * @param int $orderId 订单 id
     * @param int $couponId 优惠卷 id
     * @return bool
     */
    public function setUnlock($userId, $orderId, $couponId) {
        return $this->_baseSave(
            ['id'=>$couponId, 'uid'=>$userId, 'used'=>0, 'order_id'=>$orderId ],
            ['order_id'=> 0, 'unlock_time'=>0]
        );
    }

    /** 设置优惠卷使用
     * @param int $userId 用户 id
     * @param int $couponId 优惠卷 id
     * @param int $orderId 订单 id
     * @return bool
     */
    public function setUsed($userId, $couponId, $orderId) {
        return $this->_baseSave(
            ['id'=>$couponId, 'uid'=>$userId, 'used'=>0],
            ['used'=> 1, 'order_id'=>$orderId]
        );
    }

    /** 获取优惠卷类型
     * @param int $userId 用户 id
     * @param int $cid 订单 id
     * @param int $type 订单类型
     * @param int $subType 订单子类型
     * @return mixed
     */
    public function getCouponList($userId, $cid =null, $type = null, $subType = null) {
        $now = date("Y-m-d H:i:s");
        $where = ['uid' => $userId, 'used' => 0, 'expiration' => ['gt', $now]];
        if($type) {
            $where['type'] = $type;
            $subType && $where['sub_type'] = $subType;
        }
        if($cid) {
            $where['_complex'] = ['_logic' => 'or', 'unlock_time' => ['lt',$now], 'order_id' => $cid];
        } else {
            $where['unlock_time'] = ['lt',$now];
        }
        return $this->_baseSelect($where);
    }

    /** 获取优惠卷个数
     * @param int $userId 用户 id
     * @param int $type 优惠卷类型
     * @param int $subType 优惠卷子类型
     * @return mixed
     */
    public function getCouponCount($userId, $type = null, $subType = null) {
        $now = date("Y-m-d H:i:s");
        $where = [
            'uid' => $userId,
            'used' => 0,
            'expiration' => ['gt', $now],
            '_complex' => ['_logic' => 'or', 'unlock_time' => ['lt',$now], 'order_id' => 0]
        ];
        if($type) {
            $where['type'] = $type;
            $subType && $where['sub_type'] = $subType;
        }
        return $this->index('award_type')->field('uid')->where($where)->count();
    }

    /** 获取用户未读优惠卷个数
     * @param int $userId 用户id
     * @return mixed
     */
    public function getUnReadCounponCount($userId) {
        $now = date("Y-m-d H:i:s");
        return $this->index('is_read')
            ->field(['uid'])
            ->where([
                'uid' => $userId,
                'used' => 0,
                'expiration' => ['gt', $now],
                'is_read' => 0,
                '_complex' => ['_logic' => 'or', 'unlock_time' => ['lt',$now], 'order_id' => 0]
            ])
            ->count();
    }

    /** 设置用户优惠卷为已读
     * @param int $userId 用户 id
     * @return bool
     */
    public function setUnreadCoupon($userId) {
        return $this->_baseSave(
            ['uid' => $userId, 'is_read' => 0],
            ['is_read' => 1],
            'is_read'
        );
    }

    /** 获取未读优惠卷列表
     * @param int $userId 用户 id
     * @return mixed
     */
    public function getUnReadCounponList($userId) {
        $now = date("Y-m-d H:i:s");
        return $this->_baseSelect(
            [
                'uid' => $userId,
                'used' => 0,
                'expiration' => ['gt', $now],
                'is_read' => 0,
                '_complex' => ['_logic' => 'or', 'unlock_time' => ['lt',$now], 'order_id' => 0]
            ],null,null,null,'is_read'
        );
    }

    /** 是否有优惠卷
     * @param int $userId 用户 id
     * @return int
     */
    public function hasCoupon($userId) {
        return $this->_baseFind(['uid' => $userId]) ? 1: 0;
    }

    /** 获取优惠卷列表
     * @param int $userId 用户 id
     * @param int $type 优惠卷类型
     * @param int $page
     * @param int $pageCount 每页个数
     * @return array
     */
    public function getUserCouponList($userId, $type, $page, $pageCount) {
        $where = ['uid'=>$userId];
        $now = date("Y-m-d H:i:s");
        switch($type) {
            case -1:
                $where['_complex'] = "used=0 AND expiration<'$now') OR used=1";
                break;
            case 0:
                $where['used'] = 0;
                $where['expiration'] = ['gt',$now];
                break;
            case 1:
                $where['used'] = 1;
                break;
        }
        $page = ($page > 0 ? (($page - 1) * $pageCount) : 0);
        $ret = $this->_baseSelect(
            $where,
            [($page > 0 ? (($page - 1) * $pageCount) : 0), $pageCount]
        );
        $result = $this->dealCouponData($ret);
        return $result;
    }

    /** 优惠卷数据处理
     * @param $data
     * @return array
     */
    public function dealCouponData($data){
//        $item = array(
//            'type' => "",
//            'used' => "",
//            'order' => 0,
//            'bind' => 0,
//            'title' => array(
//                "title" => "",
//                "des" => ""
//            ),
//            "des" => array(
//                "title" => "",
//                "des" => "",
//                "duration" => "",
//            ),
//            'subDes' => array(
//                "val" => "",
//                "des" => "",
//            ),
//            "data" => array(
//                "id" => "",
//                "ex_data" => ""
//            ),
//        );
        $ret = array();
        $now = time();
        foreach ($data as $k => $v) {
            $item['used'] = $v['used'];
            $item['data']['id'] = $v['id'];
            $item['type'] = $v['type'];
            $item['order'] = $v['order_id'];
            $exData = json_decode($v['ex_data'], true);

            $lockTime = strtotime($v['unlock_time']);
            $item['bind'] = ($lockTime && $lockTime > $now)? 1 : 0;

            if($v['type'] == 1){
                $item['title']['title'] = '免费';
                $item['title']['des']= '无门槛使用';
                $item['des']['title'] = '免费打印照片'.$exData['free_pic_cnt'].'张';
                $item['des']['des'] = '魔力相册免费打印'.$exData['free_pic_cnt'].'张照片';

                $item['data']['ex_data'] = array(
                    'least' => 0,
                    'reduce' => CalculateFeeModel::getFee($exData['free_pic_cnt'])[2] * 100,
                );

                $item['subDes']['val'] = "免费券";
                $item['subDes']['des'] = "(免费打印".$exData['free_pic_cnt']."张)";
            } else if ($v['type'] == 2) {
                $item['title']['title'] = ($exData['reduce_cost'] / 100);
                $item['title']['des'] = '满'.($exData['least_cost'] / 100).'元使用';
                $item['des']['title'] = ($exData['reduce_cost'] / 100).'元优惠券';
                $item['des']['des'] = '魔力相册打印照片优惠券';

                $item['data']['ex_data'] = array(
                    'least' => $exData['least_cost'],
                    'reduce' => $exData['reduce_cost'],
                );

                $item['subDes']['val'] = ($exData['reduce_cost'] / 100)."元优惠券";
                $item['subDes']['des'] = "(满".($exData['least_cost'] / 100).'元减'.($exData['reduce_cost'] / 100)."元)";
            }

            $item['des']['duration'] = explode(" ",$v['create_time'])[0]." 到 ".explode(" ",$v['expiration'])[0];
            $ret[] = $item;
        }
        return $ret;
    }

    public function warpCouponList($couponList)
    {
        $wrapCouponList = [];
        foreach($couponList as $v) {
            is_string($v['ex_data']) && $v['ex_data'] = $this->dealCouponExData($v['type'],$v['ex_data']);
            $wrapCouponList[$v['id']] = [
                'reduce' => $v['ex_data']['reduce_cost'] / 100,
                'least' => $v['ex_data']['least_cost'] / 100,
                'freePicCnt' => $v['ex_data']['free_pic_cnt'],
                'type' => $v['type']
            ];
        }
        return $wrapCouponList;
    }


} 