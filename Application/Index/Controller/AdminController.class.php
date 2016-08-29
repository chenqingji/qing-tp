<?php
namespace Index\Controller;

use User\Model\UserModel;
use Index\Model\WeixinModel;
use Index\Model\CalculateFeeModel;
use Index\Model\CouponModel;
//use Index\Model\CardDataModel;
use Index\Model\PrintItemModel as CardDataModel;

class AdminController extends BaseController {
    private $userName = "admin";
    private $userPwd = "xl123456";
    private $resetPwd = "XL123456";
    private $addPwd = "XL123456";
    private $pageLimit = 50;

    public function  __construct(){
        parent::__construct();
        $this->assign('static_v',$this->static_v);//  版本号
    }

    public function index($path = 'admin'){
        $this->assign("path",$path);
        $this->assign("resyuming",RES_YUMING);
        $this->display('Admin/index');
    }

    public function login($path = "admin"){
        header("Content-Type:text/html; charset=utf-8");
        if($this->checkLogin(true)) {
            $this->success('登录成功,正跳转至系统首页...', U($path));
        } else {
            $this->error('登录失败,用户名或密码不正确!', U('index'));
        }
    }

    public function statistics() {
        if($this->checkLogin()) {
            $cardModel = (new CardDataModel());

            $this->assign("totalCount",$cardModel->getTotalCardCount());
            $this->assign("notDownloadCount",$cardModel->getNotDownloadCount());

            $orderCnts = array(
                'all' => array('name'=> '所有','payCnt' => array('today'=>0,'yesterday'=>0), 'createCnt' => array('today'=>0,'yesterday'=>0), 'payRadio' => array('today'=>0,'yesterday'=>0)),
            );

            $callFunc = function($type) use (&$orderCnts, $cardModel) {
                $payFuncName = "get". ucfirst($type)."PayOrderCount";
                $ret = $cardModel->$payFuncName();

                $sysName = [
                    'ios' => '微信(ios)',
                    'android' => '微信(android)',
                    'others' => '微信(others)',
                    'moliWeb' => '一键打印',
                    'moliAndroid' => '魔力相册APP(android)',
                    'moliIos' => '魔力相册APP(ios)',
                    'zpk' => '照片卡',
                    'wxs' => '微信书',
                    'all' => '所有',
                ];

                foreach($ret as $value) {
                    if(is_null($orderCnts[$value['sys']])) {
                        $orderCnts[$value['sys']] = array('name' => $sysName[$value['sys']], 'payCnt' => array('today'=>0,'yesterday'=>0), 'createCnt' => array('today'=>0,'yesterday'=>0), 'payRadio' => array('today'=>0,'yesterday'=>0));
                    }
                    $orderCnts[$value['sys']]['payCnt'][$type] = $value['count'];
                    $orderCnts['all']['payCnt'][$type] += $value['count'];
                }
                $createFuncName = "get". ucfirst($type)."CreateOrderCount";
                $ret = $cardModel->$createFuncName();
                foreach($ret as $value) {
                    if(is_null($orderCnts[$value['sys']])) {
                        $orderCnts[$value['sys']] = array('name' => $sysName[$value['sys']], 'payCnt' => array('today'=>0,'yesterday'=>0), 'createCnt' => array('today'=>0,'yesterday'=>0), 'payRadio' => array('today'=>0,'yesterday'=>0));
                    }
                    $orderCnts[$value['sys']]['createCnt'][$type] = $value['count'];
                    $orderCnts[$value['sys']]['payRadio'][$type] = $value['count'] ? round($orderCnts[$value['sys']]['payCnt'][$type]/$value['count'],2) : 0;
                    $orderCnts['all']['createCnt'][$type] += $value['count'];
                }
                $orderCnts['all']['payRadio'][$type] = $orderCnts['all']['createCnt'][$type] ? round($orderCnts['all']['payCnt'][$type]/$orderCnts['all']['createCnt'][$type],2) : 0;
            };

            $callFunc("today");
            $callFunc("yesterday");
            $this->assign("orderCnts",$orderCnts);


//            $ret = $cardModel->getAppYesterdayCardCount();
//            $todayApp = array('moliAndroid' => 0, 'moliIos' => 0,'android' => 0, 'ios' => 0, 'moliWeb'=> 0);
//            foreach($ret as $value) {
//                $todayApp[$value['sys']] = $value['count'];
//            }
//            $this->assign("appYesterdayCount","(android ".$todayApp['android']." ,ios ".$todayApp['ios']." ,moliAndroid ".$todayApp['moliAndroid']." ,moliIos ".$todayApp['moliIos']." ,moliWeb ".$todayApp['moliWeb']." )");
            
            $timeStr = date("Y-m");
            $this->assign("month",$timeStr);
            $this->assign("monthCount",$cardModel->getMonthCardCount());

            list($year, $month) = explode('-', $timeStr);

            --$month;
            if($month < 1) {
                $month = 12;
                --$year;
            }
            $timeStr = sprintf('%d-%02d',$year, $month);

            $this->assign("premonth",$timeStr);
            $this->assign("premonthCount",$cardModel->getPreMonthCardCount($timeStr));

            --$month;
            if($month < 1) {
                $month = 12;
                --$year;
            }
            $timeStr = sprintf('%d-%02d',$year, $month);

            $this->assign("pre2month",$timeStr);
            $this->assign("pre2monthCount",$cardModel->getPreMonthCardCount($timeStr));
            
            $this->assign("resyuming",RES_YUMING);
            $this->display('Admin/statistics');
        } else {
            $this->index("statistics");
        }
    }

    public function admin(){
        if($this->checkLogin()) {
            $this->assign('preDisable',"disabled");
            $this->assign('orderList',$this->searchAllOrder(0,"","all","y"));
            $this->assign("resyuming",RES_YUMING);
            $this->display('Admin/admin');
        } else {
            $this->index("admin");
        }
    }

    // 处理订单列表
    private function dealOrderList(&$orderList) {
        foreach($orderList as $k=>$v) {
            // 订单状态
            $orderList[$k]["status"] = $this->orderStatus["init"]["desc"];
            foreach($this->orderStatus as $status) {
                if($status["value"] == $v["status"]) {
                    $orderList[$k]["status"] = $status["desc"];
                    break;
                }
            }

            // 地址
            if(empty($v['province'])) {
                $orderList[$k]["name"] = $v['aName'];
                $orderList[$k]["phone"] = $v['aPhone'];
                $orderList[$k]["address"] = $v['aProvince'];
                if($v['aCity']) {
                    $orderList[$k]["address"] .= "-".$v['aCity'];
                }
                if($v['aArea']) {
                    $orderList[$k]["address"] .= "-".$v['aArea'];
                }
                $orderList[$k]["street"] = $v['aStreet'];
            } else {
                $orderList[$k]["address"] = $v['province'];
                if($v['city']) {
                    $orderList[$k]["address"] .= "-".$v['city'];
                }
                if($v['area']) {
                    $orderList[$k]["address"] .= "-".$v['area'];
                }
            }

            // 订单下载状态
            $orderList[$k]["op_status"] = $this->orderOpStatus["init"]["desc"];
            foreach($this->orderOpStatus as $status) {
                if($status["value"] == $v["op_status"]) {
                    $orderList[$k]["op_status"] = $status["desc"];
                    break;
                }
            }

            // 订单号
            if(empty($orderList[$k]["orderno"])) {
                $orderList[$k]["orderno"] = "无";
            }

            // 留言
            if(empty($orderList[$k]["message"])) {
                $orderList[$k]["message"] = "无";
            }

            // 支付时间
            if(empty($orderList[$k]["paidTime"])) {
                $orderList[$k]["paidTime"] = "无";
            }

            //订单图片
            $orderList[$k]["pics"] = json_decode($v["pics"]);
            $pics = array();
            foreach($orderList[$k]["pics"] as $p) {
                $p = (array)$p;
                $pic = array(
                    "type" => $p["type"],
                    "url" => $p["url"],
                    "id" => $p["url"],
                );
                if($p["type"] == $this->uploadType["wxType"]) {
                    $pic["url"] = "";
                } else {
                    $pic["id"] = 0;
                }
                $pics[] = $pic;
            }
            $orderList[$k]["pics"] = $pics;
        }

        if(count($orderList) < $this->pageLimit) {
            $this->assign('nextDisable',"disabled");
        }

        return $orderList;
    }

    // 获取下载和支付条件
    private function getDownloadAndPayCondition($download,$pay,&$condition) {
        switch($download) {
            case "y":
                $download = "op_status = '".$this->orderOpStatus["init"]["value"]."'";
                break;
            case "n":
                $download = "op_status = '".$this->orderOpStatus["download"]["value"]."'";
                break;
            default:
                $download = "";
                break;
        }
        if(!empty($download)) {
            $condition[] = $download;
        }
        switch($pay) {
            case "y":
                $pay = "status = '".$this->orderStatus["paid"]["value"]."'";
                break;
            case "n":
                $pay = "status != '".$this->orderStatus["paid"]["value"]."'";
                break;
            default:
                $pay = "";
                break;
        }
        if(!empty($pay)) {
            $condition[] = $pay;
        }
    }

    // 搜索订单列表
    private function searchAllOrder($page, $orderId, $download, $pay) {
        $condition = array();
        if(!empty($orderId)) {
            if(!preg_match("/[^0-9]/i", $orderId)) {
                $orderId = "orderno = '".$orderId."'";
                $condition[] = $orderId;
            }
        } else {
            $this->getDownloadAndPayCondition($download, $pay, $condition);
        }
        return $this->dealOrderList((new CardDataModel())->searchCardData($page,$this->pageLimit,implode(" AND ",$condition)));
    }

    // 搜索用户名
    public function searchContact(int $page, $name, $download, $pay) {
        $condition = array();
        $this->getDownloadAndPayCondition($download, $pay, $condition);
        $data = null;

        if(!empty($name)) {
            $data = $this->dealOrderList((new CardDataModel())->searchContactData($page,$this->pageLimit,$name,implode(" AND ",$condition)));
        }

        $this->ajaxReturn(array(
            'data'=> $data,
            'preBtnDisabled' => ($page < 1? true : false),
            'nextBtnDisabled' => (count($data) < $this->pageLimit ? true : false)
        ));
    }

    public function searchAppOrder(int $page, $download, $pay) {
        $data = null;
        $condition = array();
        $this->getDownloadAndPayCondition($download, $pay, $condition);
        $data = $this->dealOrderList((new CardDataModel())->searchAppData($page,$this->pageLimit,implode(" AND ",$condition)));

        $this->ajaxReturn(array(
            'data'=> $data,
            'preBtnDisabled' => ($page < 1? true : false),
            'nextBtnDisabled' => (count($data) < $this->pageLimit ? true : false)
        ));
    }

    // 搜索图片同步失败订单
    public function searchSyncFail(int $page) {
        $data = null;

        $data = $this->dealOrderList((new CardDataModel())->searchSyncFailData($page,$this->pageLimit));

        $this->ajaxReturn(array(
            'data'=> $data,
            'preBtnDisabled' => ($page < 1? true : false),
            'nextBtnDisabled' => (count($data) < $this->pageLimit ? true : false)
        ));
    }

    // 搜索未审核通过订单
    public function searchAuditFail(int $page) {

        $condition = array();
        $data = null;

        $data = $this->dealOrderList((new CardDataModel())->searchAuditFailData($page,$this->pageLimit,'4'));

        $this->ajaxReturn(array(
            'data'=> $data,
            'preBtnDisabled' => ($page < 1? true : false),
            'nextBtnDisabled' => (count($data) < $this->pageLimit ? true : false)
        ));
    }

    // 搜索 wx 号
    public function searchWx(int $page, $name, $download, $pay) {
        $condition = array();
        $this->getDownloadAndPayCondition($download, $pay, $condition);
        $data = null;

        if(!empty($name)) {
            $data = $this->dealOrderList((new CardDataModel())->searchWxData($page,$this->pageLimit,$name,implode(" AND ",$condition)));
        }

        $this->ajaxReturn(array(
            'data'=> $data,
            'preBtnDisabled' => ($page < 1? true : false),
            'nextBtnDisabled' => (count($data) < $this->pageLimit ? true : false)
        ));
    }

    // 搜索订单
    public function searchOrder(int $page, $orderId, $download, $pay) {
        if($page < 0) {
            $page = 0;
        }
        $data = $this->searchAllOrder($page, $orderId, $download, $pay);
        $this->ajaxReturn(array(
            'data'=> $data,
            'preBtnDisabled' => ($page < 1? true : false),
            'nextBtnDisabled' => (count($data) < $this->pageLimit ? true : false)
        ));
    }

    // 搜索快递号
    public function searchExpress(int $page, $mailno, $download, $pay) {
        $condition = array();
        $this->getDownloadAndPayCondition($download, $pay, $condition);
        $data = null;

        if(!empty($mailno)) {
            $data = $this->dealOrderList((new CardDataModel())->searchExpressData($page,$this->pageLimit,$mailno,implode(" AND ",$condition)));
        }

        $this->ajaxReturn(array(
            'data'=> $data,
            'preBtnDisabled' => ($page < 1? true : false),
            'nextBtnDisabled' => (count($data) < $this->pageLimit ? true : false)
        ));
    }

    // 搜索电话号码
    public function searchPhone(int $page, $phone, $download, $pay) {
        $condition = array();
        $this->getDownloadAndPayCondition($download, $pay, $condition);
        $data = null;

        if(!empty($phone)) {
            $data = $this->dealOrderList((new CardDataModel())->searchPhoneData($page,$this->pageLimit,$phone,implode(" AND ",$condition)));
        }

        $this->ajaxReturn(array(
            'data'=> $data,
            'preBtnDisabled' => ($page < 1? true : false),
            'nextBtnDisabled' => (count($data) < $this->pageLimit ? true : false)
        ));
    }

    // 设置下载状态
    public function setOpStatus() {
        if($this->checkLogin()) {
            $cid = I('post.cid');
            if(is_null($cid)) {
                $this->ajaxReturn(array(
                    "status" => "error",
                    "reason" => "no cid"
                ));
            }
            (new CardDataModel())->saveCard($cid,array(
                "op_status" => $this->orderOpStatus["download"]["value"]
            ));
            $this->ajaxReturn(array(
                "status" => "ok",
                "cid" => $cid,
                "order" => I('post.order'),
                "desc" => $this->orderOpStatus["download"]["desc"],
            ));
        } else {
            $this->ajaxReturn(array(
                "status" => "error",
                "reason" => "no login"
            ));
        }
    }

    // 获取 access token
    public function getAccessToken() {
        $this->ajaxReturn(array( "accessToken" => (new WeixinModel())->getAccessToken()));
    }

    public function resetSync(){
        if($this->checkLogin()) {
            $cid = I('post.cid');
            $sync = intval(I('post.sync'));
            if($sync == 10){
                // 完成坏图处理，要为5的才能执行，否则记录执行失败
                $cdm = (new CardDataModel());
                $cardDataNow = $cdm->getCard($cid);
                $m = M();
                if(!empty($cardDataNow['is_sync']) && $cardDataNow['is_sync'] == 5){
                    $m->query("INSERT INTO admin_log (info, type, uid, orderno) VALUES ('".$cid."完成处理', 'finish_5', '4120', '".$cid."')");
                }else{
                    // $sync = $cardDataNow['is_sync'];
                    $m->query("INSERT INTO admin_log (info, type, uid, orderno) VALUES ('".$cid."未完成处理', 'finish_5', '4120', '".$cid."')");
                    $this->ajaxReturn(array(
                        "status" => "error",
                        "reason" => "no cid"
                    ));
                }
                if($cardDataNow['sys'] == 'zpk'){
                    $sync = 0;
                }
            }
            if($sync == 127){
                // 删除订单处理
                $m = M();
                $m->query("INSERT INTO admin_log (info, type, uid, orderno) VALUES ('".$cid."删除订单', 'set_127', '4120', '".$cid."')");
            }
            if(is_null($cid)) {
                $this->ajaxReturn(array(
                    "status" => "error",
                    "reason" => "no cid"
                ));
            }
            (new CardDataModel())->saveCard($cid,array(
                "is_sync" => $sync
            ));
            $this->ajaxReturn(array(
                "status" => "ok",
                "cid" => $cid
            ));
        } else {
            $this->ajaxReturn(array(
                "status" => "error",
                "reason" => "no login"
            ));
        }
    }

    // 登出
    public function logOut(){
        session('adminName',null);
        session('adminPwd',null);
        $this->success('退出成功！', U('Index/login'));
    }

    // 检查登入
    public function checkLogin($writeSession = false){
        header("Content-Type:text/html; charset=utf-8");
        $name = I('post.name');
        $pwd = I('post.password');
        if(empty($name) || empty($pwd)) {
            $name = session('adminName');
            $pwd = session('adminPwd');
        }
        if(!empty($name) && !empty($pwd) && $name == $this->userName && $pwd == $this->userPwd) {
            if($writeSession) {
                session('adminName',$name);
                session('adminPwd',$pwd);
            }
            return true;
        }
        return false;
    }

    // 更改订单地址
    public function changeAddress() {
        $id = I("post.id", null);
        $tid = I("post.tid", null);
        $province = I("post.province", null);
        $city = I("post.city", null);
        $area = I("post.area", null);
        $street = I("post.street", null);
        $phone = I("post.phone", null);
        $name = I("post.name", null);

        $ret = array('status' => 'error');
        if(is_null($id)) {
            $ret['reason'] = "the id is invalid";
        } else if(is_null($tid)) {
            $ret['reason'] = "the tid is invalid";
        } else if(is_null($phone)) {
            $ret['reason'] = "the phone is invalid";
        } else if(is_null($name)) {
            $ret['reason'] = "the name is invalid";
        } else if(is_null($province)) {
            $ret['reason'] = "the province is invalid";
        } else if(is_null($city)) {
            $ret['reason'] = "the city is invalid";
        } else if(is_null($area)) {
            $ret['reason'] = "the area is invalid";
        } else if(is_null($street)) {
            $ret['reason'] = "the street is invalid";
        } else {
            $mRet = (new CardDataModel())->updateCardData($id,array(
                'province' => $province ,
                'city' => $city,
                'area' => $area,
                'street' => $street,
                'name' => $name,
                'phone' => $phone
            ));
            if(intval($mRet) > 0) {
                $ret['status'] = "ok";
                $ret['data'] = $tid;
            } else {
                $ret['reason'] = "更新失败,订单可能已经发件";
            }
        }
        $this->ajaxReturn($ret);
    }

    public function resetAllOrder(){

        $pwd = I("post.pwd", null);

        $ret = array('status' => 'error');
        if(is_null($pwd) || $pwd !== $this->resetPwd) {
            $ret['reason'] = "the password is invalid";
        } else {
            (new CardDataModel())->resetAllOrder();
            $ret['status'] = "ok";
        }
        $this->ajaxReturn($ret);
    }

    // 重置订单
    public function resetOrder() {
        $pwd = I("post.pwd", null);
        $id = I("post.id", null);
        $tid = I("post.tid", null);
        $type = I("post.type", null);

        $ret = array('status' => 'error');
        if(is_null($id)) {
            $ret['reason'] = "the id is invalid";
        } else if(is_null($tid)) {
            $ret['reason'] = "the tid is invalid";
        } else if(is_null($pwd) || $pwd !== $this->resetPwd) {
            $ret['reason'] = "the password is invalid";
        } else {
            if($type == 'default'){
                (new CardDataModel())->resetCardData($id, $tid);
            }elseif($type == 'cancel'){
                $limit_flag = (new CardDataModel())->cancelCardData($id, $tid);
                /*
                if($limit_flag){
                    $ret['reason'] = "无法重置";
                    $this->ajaxReturn($ret);
                }
                */
            }

            $ret['status'] = "ok";
            $ret['data'] = $tid;
        }
        $this->ajaxReturn($ret);
    }

    // 新建订单
    public function newOrder() {
        $this->assign("userId",4120);   	//魔力快印客服_欢欢
        $this->assign("wxType",$this->uploadType["wxType"]);
        $this->assign("wxLocalType",$this->uploadType["wxLocalType"]);
        $this->assign("uploadType",$this->uploadType["uploadType"]);
        $this->assign("resyuming",RES_YUMING);
        $this->display("Admin/newOrder");
    }

    public function getCardSingle(){
        $postData = I('post.');
        $m = (new CardDataModel());
        $cardData = $m->getCardByOrderNo($postData['orderno']);

        $ret = array('cardData' => $cardData);
        $this->ajaxReturn($ret);
    }

    public function updatePhotoNumber(){

        $ret = array('info' => 'success');

        $postData = I('post.');
        $success_number = intval($postData['num']);
        if($success_number !== 0){
            $m = (new CardDataModel());
            $order_no = $postData['orderno'];
            $m->updatePhotoNumber($order_no, $success_number);
        }
        $this->ajaxReturn($ret);
    }

    // 保存订单
    public function saveOrder() {
        $postData = I('post.');

        $photoNum = count($postData['pics']);
        $postData['photo_number'] = $photoNum;

        list($postData['photo_fee'],
            $postData['postage'],
            $postData['price']) = CalculateFeeModel::getFee($photoNum, $postData['province'], $postData['city'], $postData['area'], $postData['street']);

        $postData['pics'] = json_encode($postData['pics']);

        //$postData['status'] = 10;
        if(!is_null($postData['status'])) {
            $postData['status'] = intval($postData['status']);
            if($postData['status'] == $this->orderStatus["paid"]["value"]) {
                $postData['paidTime'] = date("Y-m-d H:i:s");
            }
        }

        if(is_null($postData['cid'])) {
            // 新建订单效验
            $postData['orderno'] = time().rand(1000, 9999);
            $last_orderno = $postData['orderno'];

            if(is_null($postData['pwd']) || $postData['pwd'] != $this->addPwd) {
                $ret = array('error' => '密码不对!');
                $this->ajaxReturn($ret);
                return;
            }
            unset($postData['pwd']);

            $postData['cid'] = (new CardDataModel())->newCardData($postData);
        } else {
            // 保存订单
            $last_orderno = $postData['orderno'];

            $m = (new CardDataModel());
            $cardData = $m->getCard($postData['cid']);
            $m->saveCardDataCondition($postData["uid"],$postData['cid'],$postData);
        }

        $ret = array('cid' => $postData['cid'],'orderno'=> $last_orderno);
        $this->ajaxReturn($ret);
    }

    // 获取订单详情
    public function getOrderPayData($cid, $uid) {
        $m = (new CardDataModel());
        $cardData = $m->getCard($cid);
        if($cardData) {
            $ret = array(
                "payPicCount" => $cardData['photo_number'],
                "payOriginalPrice" => $cardData['price'],
                "payPrice" => $cardData['price'],
                "couponDes" => "无"
            );
            if($cardData['coupon_id']) {
                $couponM = new CouponModel();
                $couponData = $couponM->getBindCoupon($uid, $cardData['coupon_id'], $cardData['cid'],($cardData['status'] == 10));
                if($couponData) {
                    $ret['payPrice'] -= ($couponData['ex_data']['reduce_cost'] / 100);
                    if($couponData['type'] == CouponModel::$FREE_TYPE) {
                        $ret['couponDes'] = '(免费打印'.$couponData['ex_data']['free_pic_cnt'].'张)';
                    } else if($couponData['type'] == CouponModel::$REDUCE_TYPE) {
                        $ret['couponDes'] = "(满 ".($couponData['ex_data']['least_cost'] / 100)." 减 ".($couponData['ex_data']['reduce_cost'] / 100).")";
                    }
                }
            }
            $this->ajaxReturn(array("status"=>"ok","data"=>$ret));
        } else {
            $this->ajaxReturn(array("status"=>"error","reason"=>"查无此订单"));
        }
    }
}