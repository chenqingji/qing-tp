<?php
namespace Index\Controller;
use Index\Model\WeixinModel;
use Index\Model\CouponModel;
//use Index\Model\CardDataModel;
use Index\Model\PrintItemModel as CardDataModel;
use Index\Model\CalculateFeeModel;
use Index\Model\OrderServiceModel;

class IndexController extends BaseController {
    // 无需检验认证的web端页面请求
    private $noCheckActions = array(
        're',
        'OauthCallback',
        'mail',
        'qianniu',
        'getMail',
        'test'
    );

    protected $cookie_time = 0;
    protected $auth_time = '1433129516';
    protected $accessTokenTime = 43200;
    private $login_cookie = "ky_login_userid";
    private $display_domain;

    public $default_thumb = 'http://7xoa8x.com2.z0.glb.qiniucdn.com/resource/images/molika/orderlist/fm_new.png';

    public function __construct() {
        parent::__construct();
        // session('uid', 25);   //测试环境测试使用

        $this->display_domain = $_SERVER['HTTP_HOST'];
        $this->cookie_time = (1 * 365 * 24 * 60 * 60);
        $uid = session('uid');
        if(!empty($uid) && $uid>0){
            $this->user_id = $uid;
            setcookie($this->login_cookie,$uid,time() + $this->cookie_time,"/",$this->display_domain);
        }

        $is_wx = is_weixin() ? 'wx':'';

        // 是否需要认证
        if(empty($this->user_id)){
            $needToCheck = true;
            foreach ($this->noCheckActions as $action) {
                if(!strnatcasecmp($action, ACTION_NAME)) {
                    $needToCheck = false;
                }
            }
            if($needToCheck) {
                $this->checkIdentify();
            }
        }

        $this->assign('is_wx',$is_wx);

        if($is_wx){
            $nonceStr = random_string(16);
            $wxtime = time();
            $wxM = new WeixinModel();
            $signature = $wxM->getSignature($nonceStr, $wxtime);
            $this->assign("wxappid",$wxM->getAppid());
            $this->assign("wxtime",$wxtime);
            $this->assign("nonceStr",$nonceStr);
            $this->assign("signature",$signature);
        }
        $this->assign('static_v',$this->static_v);//  版本号
    }

    public function qianniu() {
        $this->assign("resyuming",RES_YUMING);
        $this->display("Index:qianniu");
    }

    public function customerServices() {
        $userData = $this->getUserInfo($this->user_id);
        $uid = "kuaiyin_".$this->user_id;
        $name = $userData['nickname'];
        $avatar  = $userData['avatar'];

        define("TOP_SDK_WORK_DIR", TEMP_PATH);
        Vendor('taobaoIM.TopSdk');
        $c = new \TopClient;
        $token = md5("$uid-kuaiyin-$uid");
        $c->appkey = '23345070';
        $c->secretKey = 'e6fd5af6c78ea80e8b26f82b4c63dc72';
        $req = new \OpenimUsersAddRequest;

        $req->setUserinfos("{\"userid\":\"$uid\",\"password\":\"$token\",\"nick\":\"$name\",\"icon_url\":\"$avatar\"}");
        $c->execute($req);

        $this->assign("uid",$uid);
        $this->assign("pwd",$token);
        $this->assign("avatar",$avatar);
        $this->display("Index:communicate");
    }

    public function clear_cookie(){
        cookie('hash_value', null);
        session(null);
    }

    public function generate_cookie($uid){
        return $uid.'-'.md5($uid.'-kuaiyin-'.$uid);
    }

    protected function checkIdentify($no_redirect = false) {
        // web
        $uid = session('uid');

        // 是否有会话
        if(empty($uid)) {
            $uid = cookie($this->login_cookie);
        }

        if(!empty($uid)){
            // 检测用户是否真实存在
            $userInfo = $this->getUserInfo($uid);
            if(!$userInfo) {
                $uid = null;
            }
        }

        if($no_redirect){
            return $uid;
        }

        if($userInfo['webLastUpdate'] < $this->auth_time){
            $uid = null;
        }

        if(is_null($uid)){
            //授权完跳转回当前页面继续操作
            header('Location: '.$this->getWeixinUrl());
            die();
        } else {
            $this->user_id = $uid;
            setcookie($this->login_cookie,$uid,time() + $this->cookie_time,"/",$this->display_domain);
        }
    }

    public function index($cid = null) {
        //$this->order($cid);
        $this->navigation();
    }

    protected function setOrder(&$order, $getImg = true) {
        $order["pics"] = json_decode($order["pics"]);
        $pics = array();
        if($getImg) {
            $accessToken = (new WeixinModel())->getAccessToken();
        }

        foreach($order["pics"] as $v) {
            $v = (array)$v;
            $pic = array(
                "type" => $v["type"],
                "url" => $v["url"],
                "id" => $v["url"],
            );

            switch($v["type"]) {
                case $this->uploadType["wxType"]:
                    $pic["url"] = $getImg? "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=".$accessToken."&media_id=".$pic["id"] : "";
                    break;
                case $this->uploadType["uploadType"];
                case $this->uploadType["wxLocalType"];
                    $pic["id"] = 0;
                    break;
            }

            $pics[] = $pic;
        }
        $order["pics"] = $pics;
        if($order["aid"]){
            $contactData = array(
                'name' => $order['name'],
                'phone' => $order['phone'],
                'province' => $order['province'],
                'city' => $order['city'],
                'area' => $order['area'],
                'street' => $order['street'],
            );

            if(empty($order["province"])) {
                // 订单无地址从地址簿中去取
                $contactData = D("Index/Address")->getAddressData($order["aid"],$order['uid']);
            }
        }else{
            $contactData = D("Index/Address")->getLastAddressData($this->user_id);
        }

        $this->assign("contactData",$contactData);
        $this->assign("orderData",$order);
    }

    // 设置优惠卷使用状态
    public function setCoupon($cid, $couponId) {
        //设置优惠卷已使用.
        $couponM = new CouponModel();
        $coupon = $couponM->getBindCoupon($this->user_id, $couponId, $cid, false);
        if($coupon['ex_data']['backUrl']) {
            vendor("curl.function");
            $c = new \curl();
            $c->get($coupon['ex_data']['backUrl']);
        }
        $couponM->setUsed($this->user_id, $couponId, $cid);
        header("location: /Index/Index/orderDetailEx/cid/$cid");
    }

    // 订单
    public function order($cid = null) {
        $this->orderEx($cid);
    }

    // 订单 优惠卷
    public function orderEx($cid=null, $backUrl="navigation", $activityId= null, $subType=null) {
        if(!is_null($cid)) {
            $order =(new CardDataModel())->getCardInfo($cid,$this->user_id);
            if($order) {
                if($order['status'] == $this->orderStatus["paid"]["value"]) {
                    header('Location: '."/Index/Index/orderDetailEx/cid/".$cid);
                    die;
                }

                // 订单创建超过三天提示订单过期
                if(time() - strtotime($order['create_time']) >=  259200) { // 60 * 60 * 24 * 3 = 259200
                    $this->assign("resyuming",RES_YUMING);
                    $this->assign("url","/Index/Index/order");
                    $this->assign("text","您的订单已过期");
                    $this->assign("btnText","新建订单");
                    $this->display("Index/overdue");
                    return;
                }

                if(!C("SHOW_ALL_ORDER") && ($order['sys'] == "moliAndroid" || $order['sys'] == "moliIos")) {
                    $this->assign("resyuming",RES_YUMING);
                    $this->assign("url","http://a.app.qq.com/o/simple.jsp?pkgname=com.xingluo.mpa");
                    $this->assign("text","请在 app 上编辑此订单");
                    $this->assign("btnText","下载 APP");
                    $this->display("Index/overdue");
                    return;
                }

                $this->setOrder($order, false);
            } else {
                $this->showErrMsg("no this order!");
            }
        }
        $this->assign("minUploadPicCount",$this->minUploadPicCount);
        $this->assign("wxType",$this->uploadType["wxType"]);
        $this->assign("wxLocalType",$this->uploadType["wxLocalType"]);
        $this->assign("uploadType",$this->uploadType["uploadType"]);
        $this->assign("resyuming",RES_YUMING);

        $backUrl = str_replace('-','/',$backUrl);
        if(strpos($backUrl,"?") === false) {
            if($activityId) {
                $backUrl .= "/activityId/$activityId";
                if($subType) {
                    $backUrl .= "/subType/$subType";
                }
            }
        } else {
            if($activityId) {
                $backUrl .= "?activityId=$activityId";
                if($subType) {
                    $backUrl .= "&subType=$subType";
                }
            }
        }
        $this->assign("backUrl", "/Index/Index/$backUrl");

        if(is_null($cid)) {
            // 找到最后一个在上传状态的订单
            $order =(new CardDataModel())->getLastUploadCardInfo($this->user_id,$this->orderStatus["init"]["value"]);
            if($order && time() - strtotime($order['create_time']) <  259200) { // 60 * 60 * 24 * 3 = 259200
                // 订单创建小于三天
                $cid = $order['cid'];
                $this->setOrder($order, false);
            } else {
                $contactData = D("Index/Address")->getLastAddressData($this->user_id);
                $this->assign("contactData",$contactData);
            }
        }
        // 优惠卷列表
        $couponM = new CouponModel();
        $couponList = $couponM->getCouponList($this->user_id, $cid);

        if($couponList) {
            $couponList = $couponM->dealCouponData($couponList);
            $couponList = json_encode($couponList);
            $this->assign("couponList",$couponList);
        } else {
            $this->assign("couponList","[]");
        }
//        $this->assign("excepArea",CalculateFeeModel::$postExceptArea);
        $this->display("Index/orderEx");
    }

    // 导航页
    public function navigation($activityId = null, $subType = null) {
        $activityCfg = C('ACTIVITY');
        $banner = $activityCfg['common'];
        if(!is_null($activityId) && $activityCfg[$activityId]) {
            if(!is_null($subType) && $activityCfg[$activityId][$subType]) {
                $banner = array_merge($activityCfg[$activityId][$subType], $banner);
            } else {
                $banner = array_merge($activityCfg[$activityId], $banner);
            }
        }
        $couponM = new CouponModel();
        $couponCount = $couponM->getCouponCount($this->user_id);
        $this->assign("navType", "navigation");
        if($activityId) {
            $this->assign("activityId",$activityId);
            if($subType) {
                $this->assign("subType",$subType);
            }
        }
        $this->assign("couponCount", $couponCount);
        $this->assign("banner",$banner);
        $this->assign("resyuming",RES_YUMING);
        $this->display("Index/navigation");
    }

    // 订单详情
    public function orderDetail($cid) {
    	$this->orderDetailEx($cid);
    }

    protected function checkPayOrder($orderData) {
        foreach(array('name', 'phone', 'street', 'province', 'city', 'area') as $v) {
            $testVal = trim($orderData[$v]);
            if(empty($testVal)) {
                return  '联系方式不完整';
            }
        }

        foreach($orderData['pics'] as $val) {
            $val = (array)$val;
            if($val["type"] == "wxLocal") {
               return "还有图片未全上传完成";
            }
        }

        return "";
    }

    protected function dealOrderDetailData($orderData, $activityId = null, $subType = null) {
        $contact = array(
            'name' => $orderData['name'],
            'phone' => $orderData['phone'],
            'street' => $orderData['street'],
            'positon' => '',
        );

        foreach(array('province','city','area') as $v) {
            if($orderData[$v]) {
                if($contact['positon']) {
                    $contact['positon'] .= "/";
                }
                $contact['positon'] .= $orderData[$v];
            }
        }

        foreach ($contact as $k => $v) {
            if(empty($v)) {
                unset($contact[$k]);
            }
        }

        $coupon = array(
            'fee' => '0',
            'des' => ''
        );

        if($orderData['coupon_id']) {
            $couponM = new CouponModel();
            $couponData = $couponM->getBindCoupon($this->user_id, $orderData['coupon_id'], $orderData['cid'], ($orderData['status'] == 10));
            if($couponData) {
                $coupon['fee'] = ($couponData['ex_data']['reduce_cost'] / 100);
                if($couponData['type'] == CouponModel::$FREE_TYPE) {
                    $coupon['des'] = '(免费打印'.$couponData['ex_data']['free_pic_cnt'].'张)';
                } else if($couponData['type'] == CouponModel::$REDUCE_TYPE) {
                    $coupon['des'] = "(满 ".($couponData['ex_data']['least_cost'] / 100)." 减 ".($couponData['ex_data']['reduce_cost'] / 100).")";
                }
            } else {
                $orderData['coupon_id'] = 0;
            }
        }

        $payTime = $orderData['paidTime'];
        if($payTime) {
            $payTime = explode(" ",$orderData['paidTime'])[0];
            $btns = array(
                'first' =>array(
                    'text' => '查看物流',
                    'url' => '/Index/Index/mailEx/cid/'.$orderData['cid']
                ),
                'second' =>array(
                    'text' => '再次打印',
                    'url' => '/Index/Index/orderEx'
                )
            );
            if(!in_array($orderData['sys'],
                array_merge(CardDataModel::$SYS_TYPE['magicAlbumApp'],
                    CardDataModel::$SYS_TYPE['magicAlbum'],
                    CardDataModel::$SYS_TYPE['weChat']) ) )
            {
                $btns['second']['hide'] = 'hide';
            }
        } else {
            $orderData['pics'] = json_decode( $orderData['pics']);
            $payUrl = "javascript:getPayUrl(".$orderData['cid'].");";

            $cond = '';
            if($activityId) {
                $cond .= "/activityId/$activityId";
                if($subType) {
                    $cond .= "/subType/$subType";
                }
            }
            $btns = array(
                'first' =>array(
                    'text' => '编辑订单',
                    'url' => '/Index/Index/orderEx/backUrl/orderDetailEx-cid-'.$orderData['cid']."/cid/".$orderData['cid'].$cond
                ),
                'second' =>array(
                    'text' => '继续支付',
                    'url' => $payUrl
                )
            );
            if(!in_array($orderData['sys'],
                array_merge(CardDataModel::$SYS_TYPE['magicAlbumApp'],
                    CardDataModel::$SYS_TYPE['magicAlbum'],
                    CardDataModel::$SYS_TYPE['weChat']) ) )
            {
                $btns['first']['hide'] = 'hide';
            }
        }

        $display_name = $orderData['photo_number'].'张6寸照片';
        if(in_array($orderData['sys'], CardDataModel::$SYS_TYPE['cardAlbum'])){
            $display_name = $orderData['photo_number'].'张6寸照片卡';
        }

//        if($orderData['sys'] == 'wxs'){
//            $display_name = '微信书';
//            $btns['second']['text'] = '联系客服';
//            $btns['second']['url'] = '/Index/Index/customerServices';
//        }else if($orderData['sys'] == 'zpk'){
//            $display_name = '照片卡';
//            $btns['second']['text'] = '联系客服';
//            $btns['second']['url'] = '/Index/Index/customerServices';
//        }

        return  array(
            'payTime' => $payTime,
            'createTime' => explode(" ", $orderData['create_time'])[0],
            'orderno' => $orderData['orderno'],
            'name' => $display_name,
            'pay' => array(
                'photoFee' => $orderData['photo_fee'],
                'postFee' => $orderData['postage'],
                'totalFee' => $orderData['price'],
                'coupon' => $coupon,
                'payFee' => round($orderData['price'] - $coupon['fee'],1)
            ),
            'contact' => $contact,
            'button' => $btns,
        );
    }

    // 订单详情
    public function orderDetailEx($cid,$activityId = null, $subType = null) {
        $orderData =(new CardDataModel())->getCardInfo($cid,$this->user_id);

        if($orderData) {
            $retData = $this->dealOrderDetailData($orderData, $activityId, $subType);
            if($activityId) {
                $this->assign("activityId",$activityId);
                if($subType) {
                    $this->assign("subType",$subType);
                }
            }

            $this->assign("ret",$retData);
            $this->assign("resyuming",RES_YUMING);
            $this->display("Index/orderDetailEx");
        } else {
            $this->showErrMsg("该订单不存在!");
        }
    }

    // 订单列表
    public function orderList() {
        $this->payOrderList();
	    return;
    }

    public function OauthCallback(){
        $unionid = I('get.unionid','');
        if(empty($unionid)){ die('error'); }

        $info = array(
            'webOpenID'  => I('get.openid'),
            'unionID'    => I('get.unionid'),
            'webAccess'  => I('get.access_token'),
            'webRefresh' => I('get.refresh_token'),
        );

        $info_url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$info['webAccess']."&openid=".$info['webOpenID']."&lang=zh_CN";

        $opts = array(
            'http'=>array(
                'method'=>"GET",
                'timeout'=>3
            )
        );
        $txt = file_get_contents(
            $info_url,
            false,
            stream_context_create($opts)
        );

        $txt = json_decode(trim($txt),true);

        if (!array_key_exists('errcode', $txt)) {
            $info['nickname'] = $txt['nickname'];
            $info['avatar'] = $txt['headimgurl'];
            $info['sex'] = $txt['sex'];// 值为1时是男性，值为2时是女性，值为0时是未知
            $info['country'] = $txt['country'];
        }

        $url = cookie('backUrl');
        if(empty($url)) {
            $url = 'http://'.$_SERVER['HTTP_HOST'].'/';
        }
        $info['webLastUpdate'] = time();

        if(strpos($url,"?") === false) {
            $url .= "?from=singlemessage&isapinstalled=0";
        } else {
            $url .= "&from=singlemessage&isapinstalled=0";
        }

        // 检查是否注册过
        $res = $this->checkReg($unionid);

        if($res) {
            // 注册过，刷新值
            $info['uid'] = $res['uid'];
            $this->refreshInfo($info);
            $uid = $res['uid'];
        } else {
            // 没注册过，写入数据库，获取uid
            $uid = $this->insertInfo($info);
        }

        session('uid', $uid);
        header('Location: '.$url);
    }

    private function showErrMsg($msgStr){
        echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, minimal-ui">';
        echo $msgStr;
        die;
    }

    // 保存订单
    public function saveOrder() {
        $this->saveOrderEx();
    }

    public function setPay($cid, $couponId) {
        ignore_user_abort(true);
        set_time_limit(0);

        $m =(new CardDataModel());
        $cardData = $m->getCard($cid);
        if($cardData) {
            if($cardData['status'] != $this->orderStatus["paid"]["value"]) {
                if($couponId == $cardData['coupon_id']) {
                    $userData = $this->getUserInfo($this->user_id);
                    $url = $this->getPayUrl($userData, $cardData, null, true);
                    $m->saveCardDataCondition($this->user_id, $cid, array(
                        'status' => $cardData['status'],
                        'paidTime' => $cardData['paidTime'],
                    ));
                    header('Location: '.$url);
                } else {
                    echo "无效优惠卷";
                }
            } else {
                echo "已经支付";
            }
        } else {
            echo "支付失败";
        }
    }

    public function getOrderPayUrl($cid) {
        $orderData =(new CardDataModel())->getCardInfo($cid,$this->user_id);
        $ret = array('status'=>"error");
        if($orderData) {
            $orderData['pics'] = json_decode($orderData['pics'],true);
            $errMsg = $this->checkPayOrder($orderData);
            if($errMsg) {
                $ret['reason'] = $errMsg;
            } else {
                $userData = $this->getUserInfo($this->user_id);
                $payUrl = $this->getPayUrl($userData, $orderData);
                if(is_array($payUrl)) {
                    $ret['reason']  = $payUrl['reason'];
                } else {
                    $ret['data'] = $payUrl;
                    $ret['status']  = "ok";
                }
            }
        } else {
            $ret['reason'] = "查无此订单";
        }
        $this->ajaxReturn($ret);
    }

    public function payWxs($cid){

        if(!is_null($cid)) {
            $order =(new CardDataModel())->getCardInfo($cid,33);
            if($order) {
                if($order['status'] == $this->orderStatus["paid"]["value"]) {
                    header('Location: '."/Index/Index/orderDetailEx/cid/".$cid);
                    die;
                }

                $this->setOrder($order, false);

            } else {
                $this->showErrMsg("no this order!");
            }
        }else{
            $this->showErrMsg("no this order!");
        }

        $this->assign("minUploadPicCount",$this->minUploadPicCount);
        $this->assign("wxType",$this->uploadType["wxType"]);
        $this->assign("wxLocalType",$this->uploadType["wxLocalType"]);
        $this->assign("uploadType",$this->uploadType["uploadType"]);
        $this->assign("resyuming",RES_YUMING);

//        $this->assign("excepArea",CalculateFeeModel::$postExceptArea);
        $this->display("Index/paywxs");
    }

    protected function getPayWxsUrl($userData, &$orderData) {

        $retUrl = "";
        $query = array(
            "wxOpenId"=>$userData['webOpenID']?:null,
            "wxOrderId"=>$orderData['orderno'],
            "fee"=>$orderData['price'] * 100,
            "notifyUrl"=> 'http://'.$_SERVER['HTTP_HOST']."/Index/Wxpay/payCallBack",
            "successUrl"=> 'http://'.$_SERVER['HTTP_HOST']."/Index/Index/orderDetailEx/cid/".$orderData['cid'],
            "errorUrl"=> 'http://'.$_SERVER['HTTP_HOST']."/Index/Index/payWxs/cid/".$orderData['cid']
        );

        if($query) {
            //$query["fee"] = 1;  // 测试用
            $query['wxOrderId'] .= "-". $query["fee"];
            $retUrl = "http://".$_SERVER['HTTP_HOST']."/Index/Wxpay/payInterface?t=".urlencode(authcode(http_build_query($query),'ENCODE'));
        }
        return $retUrl;
    }

    protected function getPayUrl($userData, &$orderData, $couponId = null, $setUsed = false) {
        $retUrl = "";
        $query = array(
            "wxOpenId"=>$userData['webOpenID']?:null,
            "wxOrderId"=>$orderData['orderno'],
            "fee"=>$orderData['price'] * 100,
            "notifyUrl"=> 'http://'.$_SERVER['HTTP_HOST']."/Index/Wxpay/payCallBack",
            "successUrl"=> 'http://'.$_SERVER['HTTP_HOST']."/Index/Index/orderDetailEx/cid/".$orderData['cid'],
            "errorUrl"=> 'http://'.$_SERVER['HTTP_HOST']."/Index/Index/orderDetailEx/cid/".$orderData['cid']
        );

        $bindOid = null;
        if(is_null($couponId)) {
            $couponId = $orderData['coupon_id'];
            $bindOid = $orderData['cid'];
        }
        $couponM = new CouponModel();
        if($couponId) {
            $coupon = $couponM->getCoupon($this->user_id, $couponId, $bindOid);
            if($coupon) {
                if($coupon['type'] == CouponModel::$REDUCE_TYPE) {
                    if($coupon['ex_data']['least_cost'] > $query["fee"]) {
                        return array('status'=>"error",'reason'=>"没有满足最低消费");
                    }
                    $lockTime = date("Y-m-d H:i:s",strtotime($orderData['create_time']) + 259200/* 60*60*24*3 */);
                    $couponM->setUnlock($this->user_id, $orderData['cid'], $orderData['coupon_id']);
                    $couponM->setLock($this->user_id, $couponId, $orderData['cid'], $lockTime);

                    $query["fee"] = intval(($query['fee'] - $coupon['ex_data']['reduce_cost']));
                    $query["successUrl"] = "/Index/Index/setCoupon/cid/".$orderData['cid']."/couponId/$couponId";
                }

                if($coupon['type'] == CouponModel::$FREE_TYPE) {
                    if($coupon['ex_data']['free_pic_cnt'] >= $orderData['photo_number']) {
                        if($setUsed) {
                            //设置订单为已支付
                            $orderData['status'] = $this->orderStatus["paid"]["value"];
                            $orderData['paidTime'] = date("Y-m-d H:i:s");
                            //设置优惠卷已使用.
                            if(!$couponM->setUsed($this->user_id, $couponId, $orderData['cid'])) {
                                return array('status'=>"error",'reason'=>"优惠卷已被使用");
                            }
                            if($coupon['ex_data']['backUrl']) {
                                vendor("curl.function");
                                $c = new \curl();
                                $c->get($coupon['ex_data']['backUrl']);
                            }
                            $retUrl = 'http://'.$_SERVER['HTTP_HOST']."/Index/Index/orderDetailEx/cid/".$orderData['cid'];
                        } else {
                            $retUrl = "/Index/Index/setPay/cid/".$orderData['cid']."/couponId/$couponId";
                        }
                        $query = null;
                    } else {
                        $lockTime = date("Y-m-d H:i:s",strtotime($orderData['create_time']) + 259200 /* 60*60*24*3 */);
                        $couponM->setUnlock($this->user_id, $orderData['cid'], $orderData['coupon_id']);
                        $couponM->setLock($this->user_id, $couponId, $orderData['cid'], $lockTime);

                        $query["fee"] = intval($query['fee'] - (CalculateFeeModel::getFee($coupon['ex_data']['free_pic_cnt'], $orderData['province'], $orderData['city'], $orderData['area'], $orderData['street'])[2]*100));
                        $query["successUrl"] = "/Index/Index/setCoupon/cid/".$orderData['cid']."/couponId/$couponId";
                    }
                }
                $orderData['coupon_id'] = $couponId;
            }
        } else {
            $couponM->setUnlock($this->user_id, $orderData['cid'], $orderData['coupon_id']);
            $orderData['coupon_id'] = 0;
        }
        if($query) {
//            // 测试用
            $query["fee"] = 1;
            $query['wxOrderId'] .= "-". $query["fee"];
            $retUrl = "http://".$_SERVER['HTTP_HOST']."/Index/Wxpay/payInterface?t=".urlencode(authcode(http_build_query($query),'ENCODE'));
        }
        return $retUrl;
    }

    public function saveOrderWxs(){

        ignore_user_abort(true);
        set_time_limit(0);

        $postData = I('post.');

        if(empty($postData['cid'])){
            $this->ajaxReturn(array('status'=>"error",'reason'=>"cid为空"));
        }

        $addressData = array (
            'uid' => $this->user_id,
            'name' => trim($postData['name']),
            'phone' => trim($postData['phone']),
            'province' => trim($postData['province']),
            'city' => trim($postData['city']),
            'area' => trim($postData['area']),
            'street' => trim($postData['street'])
        );

        foreach($addressData as $k => $v) {
            if(empty($v)) {
                $this->ajaxReturn(array('status'=>"error",'reason'=>"the $k is invalid"));die;
            }
        }
        $lastAddress = D("Index/Address")->getLastAddressData($this->user_id);
        if($lastAddress) {
            $postData["aid"] = $lastAddress["id"];
            D("Index/Address")->saveAddressData($postData['aid'],$addressData);
        } else {
            $postData['aid'] = D("Index/Address")->addAddressData($addressData);
        }

        unset($postData['pics']);

        $m =(new CardDataModel());
        $cardData = $m->getCard($postData['cid']);
        if($cardData['status'] == $this->orderStatus["paid"]["value"]) {
            $this->ajaxReturn(array('status'=>"error",'reason'=>"订单已付款"));
            die;
        }
        $postData['orderno'] = $cardData['orderno'];
        $postData['create_time'] = $cardData['create_time'];
        $postData['price'] = $cardData['price'];

        $ret = array();
        if($cardData['status'] == $this->orderStatus["submit"]["value"] && $cardData) {
            // 获取 openId;
            $userData = $this->getUserInfo($this->user_id);
            $ret['payUrl'] = $this->getPayWxsUrl($userData, $postData);
            if(is_array($ret['payUrl'])) {
                $this->ajaxReturn($ret['payUrl']); // 出错返回
            }
        }

        unset($postData['nocheck']);

        // 保存订单
        $postData['uid'] = $this->user_id;
        $m->saveCardDataConditionWxs($postData['cid'],$postData);

        $ret['aid'] = $postData['aid'];
        $ret['cid'] = $postData['cid'];

        $this->ajaxReturn(array('status'=>"ok",'data'=>$ret));
    }

    // 保存订单
    public function saveOrderEx($couponId = null) {
        ignore_user_abort(true);
        set_time_limit(0);

        $postData = I('post.');
        $addressData = array (
            'uid' => $this->user_id,
            'name' => trim($postData['name']),
            'phone' => trim($postData['phone']),
            'province' => trim($postData['province']),
            'city' => trim($postData['city']),
            'area' => trim($postData['area']),
            'street' => trim($postData['street'])
        );

        $photoNum = count($postData['pics']);
        if($photoNum < $this->minUploadPicCount) {
            $this->ajaxReturn(array('status'=>"error",'reason'=>'上传图片张数过少'));
            return;
        }

        // 效验必填信息
        if(!is_null($postData['status']) && $postData['status'] == $this->orderStatus["submit"]["value"] && !$postData['nocheck']) {
            foreach($addressData as $k => $v) {
                if(empty($v)) {
                    $this->ajaxReturn(array('status'=>"error",'reason'=>"the $k is invalid"));die;
                }
            }
            // 图片检测
            foreach($postData['pics'] as $v) {
                if($v["type"] == "wxLocal") {
                    $this->ajaxReturn(array('status'=>"error",'reason'=>"还有图片未全上传完成"));die;
                }
            }
            $lastAddress = D("Index/Address")->getLastAddressData($this->user_id);
            if($lastAddress) {
                $postData["aid"] = $lastAddress["id"];
                D("Index/Address")->saveAddressData($postData['aid'],$addressData);
            } else {
                $postData['aid'] = D("Index/Address")->addAddressData($addressData);
            }
        }

        $postData['photo_number'] = $photoNum;

        list($postData['photo_fee'],
            $postData['postage'],
            $postData['price']) = CalculateFeeModel::getFee($photoNum, $postData['province'], $postData['city'], $postData['area'], $postData['street']);

        $postData['pics'] = json_encode($postData['pics']);

        if(!is_null($postData['status'])) {
            $postData['status'] = intval($postData['status']);
            if($postData['status'] >= $this->orderStatus["paid"]["value"]) {
                $postData['status'] = $this->orderStatus["init"]["value"];
            }
        }

        $m =(new CardDataModel());
        $cardData = null;
        if(!is_null($postData['cid'])) {
            $cardData = $m->getCard($postData['cid']);
            if($cardData['status'] == $this->orderStatus["paid"]["value"]) {
                $this->ajaxReturn(array('status'=>"error",'reason'=>"订单已付款"));
                die;
            }
            $postData['coupon_id'] = $cardData['coupon_id'];
            $postData['orderno'] = $cardData['orderno'];
            $postData['create_time'] = $cardData['create_time'];
        }
        // 解绑优惠卷
        if($couponId != $cardData['coupon_id']) {
            $couponM = new CouponModel();
            $couponM->setUnlock($this->user_id, $cardData['cid'],$cardData['coupon_id']);
            $cardData['coupon_id'] = 0;
            $postData['coupon_id'] = 0;
        }

        $ret = array();
        if(!is_null($postData['status']) && $postData['status'] == $this->orderStatus["submit"]["value"] && $cardData && !$postData['nocheck']) {
            // 获取 openId;
            $userData = $this->getUserInfo($this->user_id);
            $ret['payUrl'] = $this->getPayUrl($userData, $postData, $couponId, true);
            if(is_array($ret['payUrl'])) {
                $this->ajaxReturn($ret['payUrl']);
            }
        }
        unset($postData['nocheck']);

        if(is_null($postData['cid'])) {
            // 新建订单
            $postData['orderno'] = time().rand(1000, 9999);
            $postData['uid'] = $this->user_id;
            $postData['cid'] =(new CardDataModel())->newCardData($postData);
        } else {
            // 保存订单
            $m->saveCardDataCondition($this->user_id,$postData['cid'],$postData);
        }

        $ret['aid'] = $postData['aid'];
        $ret['cid'] = $postData['cid'];

        $this->ajaxReturn(array('status'=>"ok",'data'=>$ret));
    }

    public function useCoupon($cid, $couponId = 0) {
        $m =(new CardDataModel());
        $cardData = $m->getCard($cid);
        if(!$cardData) {
            $this->ajaxReturn(array('status'=>"error",'reason'=>"no order"));
        }

        if($couponId) {
            $couponM = new CouponModel();
            $coupon = $couponM->getCoupon($this->user_id, $couponId);

            if($coupon) {
                if($coupon['type'] == CouponModel::$REDUCE_TYPE) {
                    if($coupon['ex_data']['least_cost'] > $cardData['price']) {
                        $this->ajaxReturn(array('status'=>"error",'reason'=>"no meet coupon least"));
                    }
                }

                if($coupon['type'] == CouponModel::$FREE_TYPE) {
                    $photoNum = count(json_decode($cardData['pics'], true));
                    if($coupon['ex_data']['free_pic_cnt'] < $photoNum) {
                        $this->ajaxReturn(array('status'=>"error",'reason'=>"over free coupon free_pic_cnt"));
                    }
                }

                $lockTime = date("Y-m-d H:i:s",strtotime($cardData['create_time'])+ 259200/* 60*60*24*3 */) ;
                $couponM->setLock($this->user_id, $couponId, $cardData['cid'], $lockTime);
                $couponM->setUnlock($this->user_id, $cardData['cid'], $cardData['coupon_id']);
                $m->saveCard($cardData['cid'], array("coupon_id"=>$couponId));
                $this->ajaxReturn(array('status'=>"ok"));
            } else {
                $this->ajaxReturn(array('status'=>"error",'reason'=>"no coupon"));
            }
        } else {
            $couponM = new CouponModel();
            $couponM->setUnlock($this->user_id, $cardData['cid'],  $cardData['coupon_id']);
            $m->saveCard($cardData['cid'], array("coupon_id"=>$couponId));
            $this->ajaxReturn(array('status'=>"ok"));
        }
    }

    public function is_mobile(){
        //正则表达式,批配不同手机浏览器UA关键词。
        $regex_match="/(nokia|iphone|ipad|android|motorola|^mot\-|softbank|foma|docomo|kddi|up\.browser|up\.link|";
        $regex_match.="htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|";
        $regex_match.="blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|bunjalloo|maui|";
        $regex_match.="symbian|smartphone|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\-|longcos|pantech|gionee|^sie\-|portalmmm|";
        $regex_match.="jig\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\s*mobi|opera\*mini|320x320|240x320|176x220";
        $regex_match.=")/i";
        return isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE']) or preg_match($regex_match, strtolower($_SERVER['HTTP_USER_AGENT'])); //如果UA中存在上面的关键词则返回真。
    }

    //显示快递路径
    // http://99yin.ygj.com.cn/index/Index/mail?mailno=3909170409229
    public function mail(){
        $mailNo = I('get.mailno');
        if(!$mailNo){
            die('错误的订单号信息');
        }

        vendor("kdniao.function");
        $curlRet = getOrderTracesByJson('YD', $mailNo);
        $kuaidi = json_decode ($curlRet);
        $msg = "";

        if ($kuaidi->Success == 0) {
            $msg = $kuaidi->Reason;
            if ($msg == "单号不存在"){
                $msg = "韵达快递单号 ".$mailNo."，商品已出库。";
            }
        }elseif(count($kuaidi->Traces) == 0){
            $msg = "韵达快递单号 ".$mailNo."，商品已出库。";
        }else{
            foreach ($kuaidi->Traces as $record) {
                $msg = "<p>$record->AcceptTime<br>$record->AcceptStation</p>".$msg;
            }
            $msg = "<h4>韵达快递 $mailNo 跟踪：</h4><div>$msg</div>";
        }
        $this->assign("resyuming",RES_YUMING);
        $this->assign("msg",$msg);
        $this->display();
    }

    public function mailEx($cid) {
        $orderInfo =(new CardDataModel())->appGetCardNew($cid);
        if(!$orderInfo) {
            die('错误的订单信息');
        }
        $mailno = $orderInfo['mailno'];
        vendor("kdniao.function");
        $kuaidi = null;

        if($mailno) {
            $curlRet = getOrderTracesByJson('YD', $mailno);
            $kuaidi = json_decode ($curlRet);
        }

        $first_btn_style = '';
        $second_btn_style = '';

        if(!in_array($orderInfo['sys'],
            array_merge(CardDataModel::$SYS_TYPE['magicAlbumApp'],
                CardDataModel::$SYS_TYPE['magicAlbum'],
                CardDataModel::$SYS_TYPE['weChat']) ) )
        {
            $first['url'] = $this->default_thumb;
            $first_btn_style = 'style="display:none;";';
        }else{
            $pics = json_decode($orderInfo['pics'], true);
            $first = (array)current($pics);

            $accessToken = (new WeixinModel())->getAccessToken();
            if($first['type'] == $this->uploadType["wxType"]) {
                $first['url'] =  "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=".$accessToken."&media_id=".$first["url"];
            }
        }


        $ret = array(
            'status' => array('text' => '已配货', 'color' => '#1EBC21'),
            'expressCompany' => '韵达快递',
            'orderNum' =>  $orderInfo['orderno'],
            'expressNum' => $orderInfo['mailno'] ? $orderInfo['mailno'] : '',
            'picUrl' => $first['url'],
            'expressDetail' => array()
        );

        if(empty($ret['expressNum'])) {
            $ret['expressDetail'][] = array(
                "time"=>  "", // 每条跟踪信息的时间
                "text" => "商品已出库，正联系快递上门取件。"	// 每条跟综信息的描述
            );
        }

        if ($kuaidi && $kuaidi->Success != 0 && count($kuaidi->Traces) != 0){
            $ret["state"]=$kuaidi->State;
            if($ret["state"] == "2"){
                $ret['status']  = array('text' => '在途中', 'color' => '#1EBC21');
            }else if($ret["state"] == "3"){
                $ret['status']  = array('text' => '已签收', 'color' => '#1EBC21');
            }else if($ret["state"] == "4"){
                $ret['status']  = array('text' => '问题件', 'color' => '#1EBC21');
            }
            foreach ($kuaidi->Traces as $record) {
                $ret['expressDetail'][] = array(
                    "time"=>  $record->AcceptTime, // 每条跟踪信息的时间
                    "text" => $record->AcceptStation	// 每条跟综信息的描述
                );
            }
        }

        $this->assign('first_btn_style', $first_btn_style);
        $this->assign('second_btn_style', $second_btn_style);

        $this->assign("resyuming",RES_YUMING);
        $this->assign("ret",$ret);
        $this->display("Index:express");
    }

    public function getMail(){
        $ret = array(
            'status' =>  -1,
        );
        $orderno = I('get.orderno');
        if($orderno){
            $orderInfo =(new CardDataModel())->getCardByOrderNo($orderno);

            if($orderInfo) {
                $ret['status'] = 0;
                if($orderInfo['mailno']) {
                    $ret['status'] = 1;

                    vendor("kdniao.function");
                    $curlRet = getOrderTracesByJson('YD', $orderInfo['mailno']);
                    $kuaidi = json_decode ($curlRet);

                    if ($kuaidi->Success == 0 || count($kuaidi->Traces) == 0) {
                        $ret['status'] = 0;
                    }else{
                        $ret['data'] = array(
                            'express' => array(
                                'name' => '韵达',
                                'id' => $orderInfo['mailno'],
                            ),
                            'traces' => array()
                        );
                        foreach ($kuaidi->Traces as $record) {
                            $ret['data']['traces'][] = array(
                                'time' => $record->AcceptTime,
                                'station' => $record->AcceptStation
                            );
                        }
                    }
                }
            }
        }
        $this->ajaxReturn($ret);
    }

    public function test() {

        $where = ['id' => 15];
        $res = (new OrderServiceModel())->findPrintItem($where);
        echo '<pre>';var_dump($res);

        die;

        $data = [
            'uid' => $this->user_id,
            'name' => 'name_tt',
            'phone' => 'phone_tt',
            'province' => 'province_tt',
            'city' =>  'city_tt',
            'area' =>  'area_tt',
            'street' =>  'street_a_tt',
            'mailno' => '12',
            'pay_type' => 'wx',
            'paidTime' => date("Y-m-d H:i:s", time()),
            'status' => 11,
//            'coupon_id' => 5,
//            'price' => 120
        ];
        (new CardDataModel())->saveCardData($this->user_id, 1, $data);

        $m = new OrderServiceModel();
        $m->deletePrintOrder(1,2);
    }

    public function testList($uid){
        $ret = $orderList =(new CardDataModel())->listCardData($uid, null, 1, 20);
        var_dump($ret);die;
    }
    
    /**
     * 优惠券列表
     * @param $type
    */
    public function getUserCouponList($type = 0){
        $m = new CouponModel();
        $ret['data'] = $m->getUserCouponList($this->user_id, $type, $page = 1, $pageCount = 5);
        $this->assign('couponList', $ret);

        $backUrl = "/Index/Index/navigation";
        if($type == "-1") {
            $backUrl = "/Index/Index/getUserCouponList";
        }
        $this->assign('backUrl', $backUrl);
        $this->display('Index:couponList');
    }

    /**
     * 优惠券下拉加载
     *
     */
    public function postUserCouponList(){
        $m = new CouponModel();
        $type = I('post.type');
        $page = I('post.page');
        $ret['data'] = $m->getUserCouponList($this->user_id, $type, $page, $pageCount = 5);
        $ajaxRet = array('status' => 'error');
        if($ret){
            $ajaxRet['status'] = 'ok';
            $ajaxRet['data'] = $ret;
        }
        $this->ajaxReturn($ajaxRet);
    }

    protected function dealOrderData($data, $couponList, $activityId, $subType) {
        $item = array(
            'photo_number' => '',
            'orderno' => '',
            'price' => '',
            'excDate' => '',
            'paidTime' => '',
            'create_time' => '',
            'firstUrl' =>'',
            'secondUrl' => '',
            'img_url' =>'',
        );
        $serverName = $_SERVER['SERVER_NAME'];
        $accessToken = null;
        foreach ($data as $k => $v) {
            $v['pics'] = json_decode($v['pics'], true);

            if($v['sys'] == CardDataModel::FROM_YIN_ZPK) {
                $imgUrl = $this->default_thumb;
            }
            if(in_array($v['sys'],
                array_merge(CardDataModel::$SYS_TYPE['magicAlbumApp'],
                    CardDataModel::$SYS_TYPE['magicAlbum'],
                    CardDataModel::$SYS_TYPE['weChat']) ) )
            {
                $imgUrl = $v['pics'][0]['url'];
                if($v['pics'][0]['type'] == $this->uploadType["wxType"])  {
                    if(empty($accessToken)) {
                        $wxM = new WeixinModel();
                        $accessToken = $wxM->getAccessToken();

                        vendor("curl.function");
                        $c = new \curl();
                        $tstRet = $c->get("https://api.weixin.qq.com/cgi-bin/getcallbackip?access_token=$accessToken");
                        $tstRet = json_decode($tstRet, true);

                        if($tstRet['errcode']) {
                            $accessToken = $wxM->refereshAccessToken();
                        }
                    }
                    $imgUrl =  "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=".$accessToken."&media_id=".$imgUrl;
                }
            }
            $payUrl = "javascript:getPayUrl(".$v['cid'].");";

            $coupon =  $couponList[ $v["coupon_id"] ];
            if($coupon && $v['price'] >= $coupon['least']) {
                $v['price'] = round($v['price'] - $coupon['reduce'], 1);
                if($v['price'] < 0) {
                    $v['price'] = 0;
                }
            }

            $cond = '';
            if($activityId) {
                $cond .= "/activityId/$activityId";
                if($subType) {
                    $cond .= "/subType/$subType";
                }
            }

            $item['secondUrl'] = ($v['paidTime'] != null ? 'http://'.$serverName.'/Index/Index/mailEx?cid='.$v['cid'] : $payUrl);
            $item['firstUrl'] = 'http://'.$serverName.'/Index/Index/orderDetailEx/cid/'.$v['cid'].$cond;
            $item['imgUrl'] = $imgUrl;
            $item['photoNum'] = $v['photo_number'];
            $item['orderno'] = $v['orderno'];
            $item['sys'] = $v['sys'];
            $item['price'] = $v['price'];
            $item['paidTime'] = $v['paidTime'];
            $item['createTime'] = $v['create_time'];
            $item['excDate'] = intval(((strtotime($v['create_time']) + (60 * 60 * 24 * 3)) - strtotime(date("Y-m-d H:i:s"))) / 3600);
            $ret[] = $item;
        }
        return $ret;
    }

    //订单列表
    public function payOrderList($activityId = null, $subType = null,$isPaid = 0){
        $m = new CardDataModel();
        $page = I('post.page', 1);
        $ret['data'] = $m->listCardData($this->user_id, $isPaid, $page, $pagetCount = 5);

        $couponIds = array();
        foreach($ret['data'] as $v) {
            if($v["coupon_id"]) {
                $couponIds[] = $v["coupon_id"];
            }
        }

        if($activityId) {
            $this->assign("activityId",$activityId);
            if($subType) {
                $this->assign("subType",$subType);
            }
        }
        $couponList = array();
        if($couponIds) {
            $couponM = new CouponModel();
            $couponList = $couponM->getCouponListByIds($couponIds);

            $couponListMap = array();
            foreach($couponList as $v) {
                $v['ex_data'] = $couponM->dealCouponExData($v['type'],$v['ex_data']);
                $couponListMap[$v['id']] = array(
                    'reduce' => $v['ex_data']['reduce_cost'] / 100,
                    'least' => $v['ex_data']['least_cost'] / 100,
                );
            }
            $couponList = $couponListMap;
        }

        $ret['data'] = $this->dealOrderData($ret['data'], $couponList, $activityId, $subType);

        if(IS_POST){
            $ajaxRet = array('status' => 'error');
            if($ret){
                $ajaxRet['status'] = 'ok';
                $ajaxRet['data'] = $ret['data'];
            }
            $this->ajaxReturn($ajaxRet);
        } else {
            $this->assign('orderList', $ret);
            $this->assign("navType", "payOrderList");
            $this->display("Index:payOrderList");
        }
    }
}
