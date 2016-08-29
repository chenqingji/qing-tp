<?php
namespace Index\Controller;

use  Index\Model\CouponModel;

class ServiceController extends BaseController {
    protected $appKeyMap = array(
        "molima" => "7fb8iiudx6azt0yq"
    );

    private $login_cookie = "ky_login_userid";

    public function __construct() {
        $sign = I("post.sign", I("get.sign", null));
        $time = I("post.time", I("get.time", null));
        $appId = I("post.appId", I("get.appId", null));

        $appKey = $this->appKeyMap[$appId];

        if(is_null($appKey) || md5($appId.$appKey.$time) != $sign) {
            $this->ajaxReturn(array(
                'status' => 'error',
                'reason' => 'sign error'
            ));
            die;
        }
    }

    protected function checkUser() {
        // web
        $uid = session('uid');

        if(!empty($uid)){
            // 检测用户是否真实存在
            $userInfo = $this->getUserInfo($uid);
            if(!$userInfo) {
                $uid = null;
            }
        }

        if(is_null($uid)){
            //授权完跳转回当前页面继续操作
            header('Location: '.$this->getWeixinUrl());
            die();
        } else {
            $this->user_id = $uid;
            setcookie($this->login_cookie,$uid,time() + 0,"/");
        }
    }

    protected function checkArgs($argArray) {
        foreach ($argArray as $k => $v) {
            if(is_null($v)) {
                $this->ajaxReturn(array(
                    'status' => 'error',
                    'reason' => "invalid arg($k)"
                ));
                die;
            }
        }
        return $argArray;
    }

    public function addCoupon($host, $back, $appId, $idCount) {
        $this->checkUser();
        ignore_user_abort(true);
        set_time_limit(0);
        $timeStamp = time();
        $appKey = $this->appKeyMap[$appId];
        $url = "http://$host/Index";
        $idCount = intval($idCount);
        vendor("curl.function");
        $c = new \curl();
        for($i = 0;$i < $idCount;++$i) {
            $id = I("get.id_$i",null);
            if(!is_null($id)) {
                $queryArg = "id=$id&appId=$appId&time=$timeStamp&sign=".md5($appId.$appKey.$timeStamp);
                $retRaw = $c->get("$url/Coupon/setCash?$queryArg");
                $ret = json_decode($retRaw, true);
                if($ret['status'] == "ok") {
                    if($ret['needAdd']) {
                        $m = new CouponModel();
                        $expiration = $ret['expiration'];
                        $ret = $ret['data'];
                        $couponId = $m->addCoupon($this->user_id, $ret['type'], $ret['sub_type'], $ret['extData'], $expiration);
                        $c->get("$url/Coupon/setCashCouponId?$queryArg&couponId=$couponId");
                    }
                }
            }
        }
        header('Location: '."$url/$back");
        die;
    }
} 