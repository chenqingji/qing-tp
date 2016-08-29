<?php
namespace Index\Model;
use Index\Model\CalculateFeeModel;

class CouponModel {
    static $FREE_TYPE = 1;
    static $REDUCE_TYPE = 2;
    public function addCoupon($uid, $type, $subType, $exData, $expiration = null) {
        $m = M();
        $data = array(
            'uid' => $uid,
            'type' => $type,
            'sub_type' => $subType,
            'ex_data' => $exData
        );
        !is_null($expiration) && $data['expiration'] = $expiration;
        return $m->table('user_coupon_info')->add($data);
    }

    public function getBindCoupon($userId, $couponId, $cid, $isPaid = false) {
        $now = date('Y-m-d H:i:s');
        $m = M();

        $cond = "";
        if(!$isPaid) {
            $cond =  " AND expiration >'$now'";
        }

        $ret = $m->table('user_coupon_info')
            ->field('id,type,sub_type,ex_data,expiration,create_time')
            ->where("id=$couponId AND uid=$userId AND order_id=$cid".$cond)
            ->find();

        if($ret) {
            $ret['ex_data'] = $this->dealCouponExData($ret['type'], $ret['ex_data']);
        }

        return $ret;
    }

    public function getCouponListByIds($ids) {
        $ids = implode(",", $ids);
        $m = M();
        $ret = $m->table('user_coupon_info')
            ->field('id,type,sub_type,ex_data,expiration,create_time')
            ->where("id IN ($ids)")
            ->select();
        return $ret;
    }

    public function dealCouponExData($type, &$v) {
        $v = json_decode($v, true);
        if($type == 1) {
            $v['reduce_cost'] = CalculateFeeModel::getFee($v['free_pic_cnt'])[2] * 100;
            $v['least_cost'] = 0;
        }
        return $v;
    }

    public function getCoupon($userId, $couponId, $cid = null) {
        $now = date('Y-m-d H:i:s');
        $m = M();
        $cond = "order_id=".(is_null($cid)?"0":$cid);
        $ret = $m->table('user_coupon_info')
            ->field('id,type,sub_type,ex_data,expiration,create_time')
            ->where("id=$couponId ".($userId ? "AND uid=$userId ": '')."AND used=0 AND expiration >'$now' AND ($cond OR unlock_time<'$now')")
            ->find();

        if($ret) {
            $ret['ex_data'] = $this->dealCouponExData($ret['type'], $ret['ex_data']);
        }

        return $ret;
    }

    public function setLock($userId, $couponId, $orderId, $lockTime) {
        $m = M();
        return  $m->table('user_coupon_info')
            ->where("id=$couponId AND uid=$userId AND used=0")
            ->save(array('order_id'=>$orderId,'unlock_time'=> $lockTime));
    }

    public function setUnlock($userId, $orderId, $couponId) {
        $m = M();
        $ret = $m->table('user_coupon_info')
            ->where("id=$couponId AND uid=$userId AND order_id=$orderId AND used=0")
            ->save(array('order_id'=>0,'unlock_time'=>0));
        return $ret;
    }

    public function setUsed($userId, $couponId, $orderId) {
        $m = M();
        return  $m->table('user_coupon_info')
            ->where("id=$couponId AND uid=$userId AND used=0")
            ->save(array('used'=>1,'order_id'=>$orderId));
    }

    public function getCouponList($userId, $cid =null, $type = null, $subType = null) {
        $condition = "";
        if($type) {
            $condition = " AND type=$type";
            if($subType) {
                $condition .= " AND sub_type=$subType";
            }
        }
        $orderCond = "";
        if($cid) {
            $orderCond = "order_id=$cid OR";
        }
        $now = date("Y-m-d H:i:s");
        $m = M();
        $ret = $m->table('user_coupon_info')
            ->where("uid=$userId AND used=0 AND expiration>'$now' AND ($orderCond unlock_time<'$now')".$condition)
            ->select();
        return $ret;
    }

    public function getCouponCount($userId, $type = null, $subType = null) {
        $condition = "";
        if($type) {
            $condition = " AND type=$type";
            if($subType) {
                $condition .= " AND sub_type=$subType";
            }
        }
        $now = date("Y-m-d H:i:s");
        $m = M();
        $ret = $m->table('user_coupon_info USE INDEX(award_type)')
            ->field('uid')
            ->where("uid=$userId AND used=0 AND expiration>'$now' AND (order_id=0 OR unlock_time<'$now')".$condition)
            ->count();

        return $ret;
    }

    public function getUnReadCounponCount($userId) {
        $now = date("Y-m-d H:i:s");
        $m = M();
        $ret = $m->table('user_coupon_info USE INDEX(is_read)')
            ->field('uid')
            ->where("uid=$userId AND used=0 AND expiration>'$now' AND (order_id=0 OR unlock_time<'$now') AND is_read=0")
            ->count();

        return $ret;
    }

    public function setUnreadCoupon($userId) {
        $now = date("Y-m-d H:i:s");
        $m = M();
        $ret = $m->table('user_coupon_info USE INDEX(is_read)')
            ->where("uid=$userId AND is_read=0")
            ->save(array("is_read" => 1));
    }

    public function getUnReadCounponList($userId) {
        $now = date("Y-m-d H:i:s");
        $m = M();
        $ret = $m->table('user_coupon_info USE INDEX(is_read)')
            ->where("uid=$userId AND used=0 AND expiration>'$now' AND (order_id=0 OR unlock_time<'$now') AND is_read=0")
            ->select();

        return $ret;
    }

    public function hasCoupon($userId) {
        $m = M();
        $ret = $m->table('user_coupon_info')
            ->where("uid=$userId")
            ->find();
        return $ret? 1 : 0;
    }

    public function getUserCouponList($userId, $type, $page, $pageCount) {
        $condition = "";
        $now = date("Y-m-d H:i:s");
        if($type == -1) {
            $condition = " AND ((used=0 AND expiration<'$now') OR used=1)";
        } else if($type == 0) {
            $condition = " AND used=0 AND expiration>'$now'";
        } else if($type == 1) {
            $condition = ' AND used=1';
        }
        $m = M();
        $ret = $m->table('user_coupon_info')
            ->where("uid=$userId".$condition)
            ->page($page, $pageCount)
            ->select();
        $result = $this->dealCouponData($ret);

        return $result;
    }

    public function dealCouponData($data){
        $item = array(
            'type' => "",
            'used' => "",
            'order' => 0,
            'bind' => 0,
            'title' => array(
                "title" => "",
                "des" => ""
            ),
            "des" => array(
                "title" => "",
                "des" => "",
                "duration" => "",
            ),
            'subDes' => array(
                "val" => "",
                "des" => "",
            ),
            "data" => array(
                "id" => "",
                "ex_data" => ""
            ),
        );
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
} 