<?php
namespace Index\Controller;
use User\Model\UserModel;
use Index\Model\WeixinModel;
use Index\Model\PrintItemModel as CardDataModel;
use Index\Model\CalculateFeeModel;

use Think\Controller;
class ZpkController extends BaseController {

    // 660 * 962(origin_ratio: 1192 * 1736)

    protected $base_player_width = 660;
    protected $base_player_height = 962;

    // 无需检验认证的web端页面请求
    private $noCheckActions = array(
        'OauthCallback',
        'wxPayCallback',
        'clear_cookie'
    );

    protected $static_v = '08091896';

    private $display_domain = '';  //分享域名
    private $editor_domain  = '';  //编辑器域名

    protected $cookie_time  = 0;
    protected $cookie_value = '';
    protected $cookie_str   = '-kaji2kaji-';

    protected $auth_time = '1433129';

    protected $resource_path = '';

    protected function decodeZpkUid($token) {
        $hashValue = explode('-',$token);
        $uid = null;

        if(!empty($hashValue[1])
            && $hashValue[1] == md5($hashValue[0].$this->cookie_str.$hashValue[0])) {

            // 检测用户是否真实存在
            $userInfo = $this->getUserInfo($hashValue[0]);
            if($userInfo) {
                $uid = $hashValue[0];
            }
        }
        return $uid;
    }

    protected function checkIdentify($no_redirect = false) {

        $uid = session('uid');

        // 是否有会话
        if(empty($uid)) {
            $uid = $this->decodeZpkUid($this->cookie_value);
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

        if($uid == null){
        	//授权完跳转回当前页面继续操作
            header('Location: '.$this->getWeixinUrl()); // backurl cookie 内部自动设置
            die();
        } else {
            $this->user_id = $uid;
        }
    }

    public function __call($name, $args){
        return call_user_func_array($this->$name, $args);
    }

    public function __construct() {

        if($_GET['ua'] === 'justme') {
            session('uid', 26);
        }

        // uid : 210
        //session('uid', 26);   //测试环境测试使用

        parent::__construct();

        // 七牛信息
        $upyun_bucket = 'kaji-2';
        $upyun_form_api_secret = 'lgXGCwmHy3YcZz6jrK5yhf5Zs2w=';
        $this->assign('upyun_bucket',$upyun_bucket);//  版本号
        $this->assign('upyun_form_api_secret',$upyun_form_api_secret);//  版本号

        define('ZPK_PIC_SERVER','http://7xoa8x.com2.z0.glb.qiniucdn.com');
        $this->pic_server = constant("ZPK_PIC_SERVER");
        $this->assign('ZPK_PIC_SERVER',$this->pic_server);

        if( constant("APP_DEBUG") === true ){
            // 本地
            $this->resource_path = '/../../new_2.0/molika/resource/'; // APP_PATH ...
        }else{
            // 线上
            $this->resource_path = '/../Public/zpk/resource/';
        }

        $this->server_url = 'http://'.$_SERVER['HTTP_HOST'].'/';
        $this->assign("server_url", $this->server_url);

        $this->resource_url = $this->server_url.'Public/zpk/';

        $this->display_domain = "test.com"; // 分享域名不带 http, 编辑器域名带 http
        $this->assign('display_domain',$this->display_domain);

        $this->editor_domain = $this->server_url;

        $this->cookie_time = (1 * 365 * 24 * 60 * 60);
        $this->cookie_value = cookie('hash_value');

        $uid = session('uid');

        if(!empty($uid) && $uid>0){
            // 读取到 session
            $this->user_id = $uid;
        }else{
            // 尝试读取 cookie
            $uid = $this->decodeZpkUid($this->cookie_value);
            if(!empty($uid) && $uid>0){
                $this->user_id = $uid;
            }
        }

        // 验证是否强制实效
        if(!empty($this->user_id)){
            $userInfo = $this->getUserInfo($this->user_id);
            if($userInfo) {
                if($userInfo['webLastUpdate'] < $this->auth_time){
                    $this->user_id = null;
                    // 清理cookie 与 session
                    $this->cookie_value = null;
                    session(null);
                }
            }else{
                $this->user_id = null;
            }
        }

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

        if(!empty($_GET['cv'])){
            // 有cv值
            $this->assign('cv',$_GET['cv']);
        }

        $is_wx = is_weixin() ? 'wx':'';
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

        if(!empty($this->user_id)){
            $this->assign( 'tmp_card_hash', $this->user_id."_".time()."_".random_string(4) );
        }

    }

    public function index() {
        die('index');
    }

    public function clear_cookie(){
        cookie('hash_value', null);
        session(null);
    }

    public function generate_cookie($uid){

        return $uid.'-'.md5($uid.$this->cookie_str.$uid);
    }

    // 循环检查是否有微信图片还未上传替换
    public function iteration_array($arr, $matches, $condition, $targetKey='', $level=0){

        foreach ($arr as $key => $value) {

            if($targetKey != ''){
                $key_arr = explode('/', $targetKey);
                $key_arr[$level] = $key;
                $targetKey = implode('/', $key_arr);
            }else{
                $targetKey = $key;
            }

            if(is_array($value)){
                $matches = $this->iteration_array($arr[$key], $matches, $condition, $targetKey, $level+1);
            }else{
                if(is_string($condition) && $condition != '' && strpos($condition,':') !== false){
                    $condition_arr = explode(':', $condition);
                    if(count($condition_arr) == 3) {
                        if ($condition_arr[0] == 'check') {
                            if ($key == $condition_arr[1]){
                                if($condition_arr[2] == 'wx'){ // 获取所有微信本地图片
                                    if(strpos($value,'wxLocalResource') !== false || strpos($value,'resourceid') !== false){
                                        if(!isset($matches[$value])){
                                            $matches[$value] = array();
                                            $matches['maxlength'] = $matches['maxlength'] + 1;
                                        }
                                        if(!isset($matches[$value]['target'])){
                                            $matches[$value]['target'] = array($targetKey);
                                        }else{
                                            array_push($matches[$value]['target'], $targetKey);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $matches;
    }

    public function getCardNumber($arr){
        $number = 0;
        foreach ($arr as $key => $value) {
            if(strpos($key,'Card_') !== false){
                $number++;
            }
        }
        return $number;
    }

    public function play($cid, $isedit = false){

        $card_model = D('Index/ZpkData');

        if($cid == 0){
            $this->assign('cid',$cid);  // 新建

            // get user zpk count
            $card_result = D('Index/ZpkData')->listCardData($this->user_id);
            $card_number = count($card_result);

            if(empty($card_number)){
                // show new user info
                $this->assign('new_user','1');
            }

        }else{


            $result = $card_model->getCardInfo($cid);

            if(count($result)>0) {

                if(!empty($result['card_hash'])){
                    $this->assign('card_hash',$result['card_hash']);
                }

                // 分享域名随机
                $shareUrl = "http://x".$cid.".".$this->display_domain."/Index/Zpk/play/cid/".$cid;
                $this->assign("shareUrl",$shareUrl);

                $this->assign('cid',$cid);
                $this->assign('json_data',$result['data']);

                $this->checkisValid($result);

            }else{
                die('照片卡不存在');
            }
        }

        // 标题设置
        if(empty($result['title'])){
            $result['title'] = '我的照片卡作品';
        }
        $this->assign('titleName',$result['title']);

        // 分享语
        if(empty($result['share_text'])){
            $result['share_text'] = ' ';
        }
        $this->assign('shareDesc',$result['share_text']);

        // 分享缩略图
        $shareImg = $result['share_img'];
        if(empty($shareImg)){
            $shareImg = 'http://'.$_SERVER['HTTP_HOST'].'/Public/Image/list-hover.png';
        }
        $this->assign("shareImg",$shareImg);

        if($isedit){
            $this->assign('editable','editable');
            $this->assign('uid',$this->user_id);
        }else{
            $this->assign('uid','0');
        }

        if($isedit){

            //$this->assign('stageWidth','800');
            //$this->assign('stageHeight','1260');
            //$this->assign('playerx','80');
            //$this->assign('playery','90');
            //$this->assign('playerWidth','640');
            //$this->assign('playerHeight','1008');

            $this->assign('stageWidth','750');
            $this->assign('stageHeight','1206');
            $this->assign('playerx','45');
            $this->assign('playery','50');
            $this->assign('playerWidth', (string)$this->base_player_width);
            $this->assign('playerHeight', (string)$this->base_player_height);

        }else{

            $this->assign('stageWidth','640');
            $this->assign('stageHeight','1008');
            $this->assign('playerx','0');
            $this->assign('playery','0');
            $this->assign('playerWidth','640');
            $this->assign('playerHeight','1008');

        }

        $this->assign('resyuming',$this->resource_url);
        $this->assign('resyumingn',$this->resource_url);
        $this->display('Zpk:play');
    }

    public function removeCard($cid){

        $card_model = D('Index/ZpkData');
        $result = $card_model->getCardInfo($cid);

        $ret = array(
            'status' => 'ok',
            'result' => null
        );

        if(count($result)>0) {

            if($card_model->delCardData($this->user_id, $cid) === false){
                $ret['status'] = 'error';
                $ret['result'] = '删除失败,请重试。';
                $this->ajaxReturn($ret);
            }

        }else{
            $ret['status'] = 'error';
            $ret['result'] = '照片卡不存在';
        }

        $this->ajaxReturn($ret);
    }

    public function filterEmoji($text){
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text); // Match Emoticons
        $text = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $text); // Match Miscellaneous Symbols and Pictographs
        $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $text); // Match Transport And Map Symbols
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text);   // Match Miscellaneous Symbols
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text);   // Match Dingbats
        return $text;
    }

    public function checkLock($result){
        if($result['is_sync'] == 3){
	        return true;
        }else{
            return false;
        }
    }

    public function checkisValid($result){
        $padding = 259200; // 3 days
        $now = time();
        $create_time = strtotime($result['create_time']);
        if($now - $create_time > $padding || $result['is_sync'] == 5){
            if($result['is_sync'] != 5){
                $card_info_m = M();
                $card_info_m->table('zpk_card_info')->where("cid=%s", array($result['cid']))->save(array('is_sync'=>'5'));
            }
	        return false;
        }else{
            return true;
        }
    }

    public function calculatePrice($photo_number){
        $price = 15;
        if($photo_number > 10){
            $overflow = $photo_number - 10;
            $price += $overflow;
        }
        return $price;
    }

    public function saveCardData() {

        $card_model = D('Index/ZpkData');

        $cid = intval($_POST['cid']);
        $json_data = $_POST['json_data'];
        $card_hash = $_POST['card_hash'];
        $card_number = intval($_POST['card_number']);

        // 过滤特殊文字符号.
        $json_data = $this->filterEmoji($json_data);

        $uid = $this->user_id;

        $data = array(
            'uid' => $uid,
            'data' => $json_data,
            'card_hash' => $card_hash,
            'card_number' => $card_number
        );

        $ret = array();

        $result = $card_model->getCardInfo($cid);

        if(count($result)>0) {

            // 失效的不能保存
            if(!$this->checkisValid($result)){
                $ret['status']  = 0;
                $ret['content'] = '您的照片卡已失效,请重新建立。';
                $ret['cid'] = null;
                $this->ajaxReturn($ret);
            }

            // 支付等待回调的不能保存
            /*
            if($this->checkLock($result)){
                $ret['status']  = 0;
                $ret['content'] = '您的作品正在支付状态中,暂不支持编辑,请稍后再试。';
                $ret['cid'] = null;
                $this->ajaxReturn($ret);
            }
            */

            if(intval($uid) === intval($result['uid'])){  // 保存
                if($card_model->saveCardData($uid,$cid,$data) !== false){
                    $ret['status']  = 1;
                    $ret['content'] = '保存成功';
                    $ret['cid'] = $cid;
                }else{
                    $ret['status']  = 0;
                    $ret['content'] = '保存失败';
                    $ret['cid'] = null;
                }
            }else{
                $ret['status']  = 0;
                $ret['content'] = '该照片卡不属于当前用户';
                $ret['cid'] = null;
            }

        }else{

            $cid = $card_model->newCardData($data);
            if($cid){
                $ret['status']  = 1;
                $ret['content'] = '保存成功';
                $ret['cid'] = $cid;
            }else{
                $ret['status']  = 0;
                $ret['content'] = '新建失败';
                $ret['cid'] = null;
            }

        }

        $this->ajaxReturn($ret);

    }

    public function refreshOrderno($cid = null, $writeToDb = false){
        $orderno = time().rand(1000, 9999);
        if($writeToDb && !empty($cid)){
            $card_model = D('Index/ZpkData');
            $data = array(
                'orderno' => $orderno,
            );
            $card_model->saveCardData($this->user_id,$cid,$data);
        }
        return $orderno;
    }

    public function prePay($cid){

        $card_model = D('Index/ZpkData');

        if(empty($cid)){
            $cid = intval($_GET['cid']);
        }

        if($cid != 0){

            $result = $card_model->getCardInfo($cid);

            if(count($result)>0) {

                $this->assign("card_number",$result['card_number']);
                $this->assign("cid",$result['cid']);

                // $this->assign('card_hash',$result['card_hash']);
                $contactData = D("Index/Address")->getLastAddressData($this->user_id);
                $this->assign("contactData",$contactData);

            }else{
                die('照片卡不存在');
            }

        }else{
            die('照片卡不存在');
        }

        $this->assign('resyuming',$this->resource_url);
        $this->assign('resyumingn',$this->resource_url);
        $this->display('Zpk:prepay');
    }

    public function saveAddressInfo(){

        $card_model = D('Index/ZpkData');

        $cid = intval($_POST['cid']);

        $uid = $this->user_id;

        $ret = array();

        $result = $card_model->getCardInfo($cid);

        if(count($result)>0) {
            if(intval($uid) === intval($result['uid'])){

                // 失效的不能保存
                if(!$this->checkisValid($result)){
                    $ret['status']  = 0;
                    $ret['content'] = '您的照片卡已失效,请重新建立。';
                    $this->ajaxReturn($ret);
                }

                // 支付等待回调的不能保存
                /*
                if($this->checkLock($result)){
                    $ret['status']  = 0;
                    $ret['content'] = '您的作品正在支付状态中,暂不支持编辑,请稍后再试。';
                    $this->ajaxReturn($ret);
                }
                */

                $orderno = $this->refreshOrderno();

                $data = json_decode($result['data'], true);
                if(is_array($data)){
                	if(empty($data['Card_total_num'])){
                        $picCount = $this->getCardNumber($data['Cards']);
                    }else{
                        $picCount = $data['Card_total_num'];
                    }

                    $price = $this->calculatePrice($picCount);

                    $data = array(
                        'name' => $_POST['name'],
                        'phone' => $_POST['phone'],
                        'province' => $_POST['province'],
                        'city' => $_POST['city'],
                        'area' => $_POST['area'],
                        'street' => $_POST['street'],
                        'zipcode' => $_POST['zipcode'],
                        'orderno' => $orderno,
                        'price' => $price
                    );

                    if($card_model->saveCardData($uid,$cid,$data) !== false){
                        $ret['status']  = 1;
                        $ret['content'] = '保存成功';
                    }else{
                        $ret['status']  = 0;
                        $ret['content'] = '保存失败';
                    }
                }else{
                    $ret['status']  = 0;
                    $ret['content'] = '照片卡数据错误';
                }

            }else{
                $ret['status']  = 0;
                $ret['content'] = '用户不匹配,保存失败';
            }
        }else{
            $ret['status']  = 0;
            $ret['content'] = '照片卡不存在,保存失败';
        }

        $this->ajaxReturn($ret);
    }

    // 复制作品到订单
    public function copyOrder($result, $orderno) {

        $postData = array (
            'uid' => $this->user_id,
            'orderno' => $orderno,
            'pics' => $result['data'],
            'photo_fee' => $result['price'],
            'price' => $result['price'],
            'postage' => 0,
            'status' => 1,
            'sys' => 'zpk',
            'name' => trim($result['name']),
            'phone' => trim($result['phone']),
            'province' => trim($result['province']),
            'city' => trim($result['city']),
            'area' => trim($result['area']),
            'street' => trim($result['street']),
            'photo_number' => $result['card_number'],
            'print_size' => 6
        );

        // 新建订单
        return (new CardDataModel())->newCardData($postData);
    }

    public function pay(){

        $cid = intval($_GET['cid']);
        //var_dump($cid);

        // 正常载入cid
        $card_model = D('Index/ZpkData');
        $result = $card_model->getCardInfo($cid);

        if(count($result)>0) {

            $data = json_decode($result['data'], true);
            if(is_array($data)){

                // $data['Cards']['Card_96']['assets']['Image_120']['url'] = 'wxLocalResource:sdf';
                // $data['Cards']['Card_96']['assets']['Image_111']['url'] = 'wxLocalResource:sdffds';
                // $data['Cards']['Card_96']['assets']['Image_129']['url'] = 'wxLocalResource:sdf';
                $res = array('maxlength'=>0);
                $res = $this->iteration_array($data['Cards'], $res, 'check:url:wx');
                // echo '<pre>';var_dump($res);
                if($res['maxlength'] != 0){
                    die('您的订单照片未全部上传成功,可返回继续上传');
                }

                $uid = $result['uid'];
                $userInfo = $this->getUserInfo($uid);
                $openid = $userInfo['webOpenID'];

                if(!$this->checkisValid($result)){
                    die('该照片卡已过期');
                }

                /*
                if($this->checkLock($result)){
                    die('该作品正在支付状态中');
                }
                */

                $orderno = $this->refreshOrderno();

                // 获取打印图片张数
                if(empty($data['Card_total_num'])){
                    $result['picCount'] = $this->getCardNumber($data['Cards']);
                }else{
                    $result['picCount'] = $data['Card_total_num'];
                }

                $total_fee= round($result['price']*100);

                // 拷贝数据
                $order_cid = $this->copyOrder($result, $orderno);

                if(empty($order_cid)){
                    die('生成订单失败,请重试。');
                }

                //回调返回函数
                $notifyUrl = 'http://'.$_SERVER['HTTP_HOST']."/Index/Wxpay/payCallBack";

                //成功跳转地址
                $successUrl = 'http://'.$_SERVER['HTTP_HOST']."/Index/Index/orderDetailEx/cid/".$order_cid;

                //失败跳转地址
                $errorUrl = 'http://'.$_SERVER['HTTP_HOST']."/Index/Index/orderDetailEx/cid/".$order_cid;

                $query = array(
                    "wxOpenId"=> $openid,
                    "wxOrderId"=> $orderno,
                    "fee"=> $total_fee,
                    "notifyUrl"=> $notifyUrl,
                    "successUrl"=> $successUrl,
                    "errorUrl"=> $errorUrl
                );

                $retUrl = "http://".$_SERVER['HTTP_HOST']."/Index/Wxpay/payInterface?t=".urlencode(authcode(http_build_query($query),'ENCODE'));

                header('Location: '.$retUrl);
                die;

            }else{
                die('照片卡数据错误');
            }

        }else{
            die('照片卡不存在');
        }
    }

    public function orderDetail($cid, $paylock = 0){

        /*
        if($paylock == 1){
            D('Index/ZpkData')->lockOrder($cid);
        }

        if($paylock == 2){
            D('Index/ZpkData')->unLockOrder($cid);
        }
        */

        $this->assign('resyuming',$this->resource_url);

        // 正常载入cid
        $card_model = D('Index/ZpkData');
        $result = $card_model->getCardInfo($cid);

        if(count($result)>0) {

            //地址
            $address = $result['province'];
            if($result['city']) {
                $address .= "-".$result['city'];
            }
            if($result['area']) {
                $address .= "-".$result['area'];
            }
            if($result['street']) {
                $address .= "-".$result['street'];
            }

            $this->assign('is_sync',$result['is_sync']);
            $this->assign("orderno",$result['orderno']);
            $this->assign("price",$result['price']);
            $this->assign("paidTime",$result['paidTime']);
            $this->assign("name",$result['name']);
            $this->assign("phone",$result['phone']);
            $this->assign("address",$address);

            if(!empty($_GET['fromlist'])){
                $this->assign('fromlist',true);
            }

            $this->display("Zpk/orderdetail");

        }else{
            die('照片卡不存在');
        }
    }

    public function wxPayCallback(){

        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        vendor("wxpay.WxPayPubHelper");
        $notify = new \Notify_pub();
        $notify->saveData($xml);

        //验证签名，并回应微信。
        //对后台通知交互时，如果微信收到商户的应答不是成功或超时，微信认为通知失败，
        //微信会通过一定的策略（如30分钟共8次）定期重新发起通知，
        //尽可能提高通知的成功率，但微信不保证通知最终能成功。
        if($notify->checkSign() == FALSE){
            $notify->setReturnParameter("return_code","FAIL");//返回状态码
            $notify->setReturnParameter("return_msg","签名失败");//返回信息
        }else{
            $notify->setReturnParameter("return_code","SUCCESS");//设置返回码

            //TODO 成功后的各类操作
            $out_trade_no = $notify->getData()["out_trade_no"]; // 获取订单号
            $openId = $notify->getData()["openid"]; // 微信 open id

            //根据订单号获取订单信息
            $orderInfo = D("Index/ZpkData")->getCardByOrderNo($out_trade_no);

            if($orderInfo){

                $cid = $orderInfo['cid'];

                D("Index/ZpkData")->saveCard($cid,array(
                    "pay_state" => $this->orderStatus["paid"]["value"],
                    "paidTime" => date("Y-m-d H:i:s")
                ));

                // D('Index/ZpkData')->unLockOrder($cid);

                vendor("curl.function");
                $c = new \curl();
                $price = $notify->getData()["total_fee"] / 100; // 总金额
                $accessToken = (new WeixinModel())->getAccessToken();
                $data = json_decode($orderInfo['data'], true);
                $picCount = $this->getCardNumber($data['Cards']);
                $accessUrl = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$accessToken";

                $accessJson = $c->post($accessUrl,json_encode(array(
                    "touser" => $openId,
                    "template_id" => "vX4Z3HFPc-vO0Icw2cTZXxZ6Cr75aXGzhiu2-LlrHlU",
                    "url" =>  'http://'.$_SERVER['HTTP_HOST'].'/Index/Zpk/orderDetail/cid/'.$cid,
                    "data" => array(
                        "first" => array(
                            "value" => "您好，您的订单已支付成功，感谢您对魔力快印的支持哦！",
                            "color" => "#173177"
                        ),
                        "keyword1" => array(
                            "value" => "$price 元",
                            "color" => "#173177"
                        ),
                        "keyword2" => array(
                            "value"=>"相片打印",
                            "color"=>"#173177"
                        ),
                        "keyword3" => array(
                            "value" => "照片 $picCount 张",
                            "color" => "#173177"
                        ),
                        "remark" => array(
                            "value" => "照片将会在72小时内发货，韵达快递包邮哦！",
                            "color" => "#173177"
                        )
                    ))));
            }else{
                try {
                    D("Index/ZpkData")->savePayError("payback", $xml);
                }catch (Exception $e) {
                    // error
                }
            }
        }
        $returnXml = $notify->returnXml();
        echo $returnXml;
    }

    public function show(){
        $this->assign('resyuming',$this->resource_url);
        $this->display("Zpk/show");
    }

    public function orderlist(){

        // 有效的作品 失效的作品
        // 显示生成时间
        // 时间倒序
        // 缩略图

        $this->assign('resyuming',$this->resource_url);

        $ret = array();

        $result = D('Index/ZpkData')->listCardData($this->user_id);
        //$result = false;
        if($result !== false && !empty(count($result))) {
            // echo '<pre>';var_dump($result);die;
            $this->assign('data',$result);
            $this->display("Zpk/orderlist");
        }else{
            $this->display("Zpk/emptylist");
        }
    }

    public function customerServices(){

        // 联系客服

        $userData = $this->getUserInfo($this->user_id);
        $uid = "zpk_".$this->user_id;
        $name = $userData['nickname'];
        $avatar  = $userData['avatar'];

        define("TOP_SDK_WORK_DIR", TEMP_PATH);
        Vendor('taobaoIM.TopSdk');
        $c = new \TopClient;
        $token = md5("$uid-zpk-$uid");
        $c->appkey = '23345070';
        $c->secretKey = 'e6fd5af6c78ea80e8b26f82b4c63dc72';
        $req = new \OpenimUsersAddRequest;

        $req->setUserinfos("{\"userid\":\"$uid\",\"password\":\"$token\",\"nick\":\"$name\",\"icon_url\":\"$avatar\"}");
        $c->execute($req);

        $this->assign("uid",$uid);
        $this->assign("pwd",$token);
        $this->assign("avatar",$avatar);
        $this->display("Zpk:communicate");
    }

    public function mail($oid){

        // 14647394068129 -1
        // 14490294747605 1
        // 14640825026965 0

        $this->assign('resyuming',$this->resource_url);

        $orderno = $oid;
        // echo $orderno;

        $check_url = 'http://yin.molixiangce.com/index/index/getMail/orderno/'.$orderno;

        $opts = array(
          'http'=>array(
            'method'=>"GET",
            'timeout'=>30
          )
        );
        $txt = file_get_contents(
            $check_url,
            false,
            stream_context_create($opts)
        );
        $txt = json_decode(trim($txt),true);

        $status = '';
        $company = '无';
        $trace = NULL;
        if(intval($txt['status']) === 1){
            $status = '已发货';  // 查询到
            $company = $txt['data']['express']['name'];
            $trace = $txt['data']['traces'];
        }elseif (intval($txt['status']) === -1){
            die('该订单号不存在');
        }else{
            $status = '准备发货中';  // 发货中
        }

        $this->assign('oid',$orderno);
        $this->assign('trace',$trace);
        $this->assign('status',$status);
        $this->assign('company',$company);
        $this->display("Zpk/mail");
    }

    /* 前端接口相关部分 */

    public function getCardJson($cid){

        $ret = array(
            'status' => 'ok',
            'result' => null
        );

        // 正常载入cid
        $card_model = D('Index/ZpkData');
        $result = $card_model->getCardInfo($cid);

        if(count($result)>0) {
            $ret['result'] = $result['data'];
        }else{
            $ret['status'] = 'error';
        }

        $this->ajaxReturn($ret);
    }

    public function checkTemplate(){

        $resource_root = realpath(APP_PATH).$this->resource_path;
        $resource_path = $resource_root.'muban_js';
        $sucai_path = $resource_root.'images/sucai';
        $theme_card_js = $resource_root.'theme_card.js';
        $res_js = $resource_root.'egret_res_array.js';

        $res_js_content = file_get_contents($res_js);
        $res_js_regex = '/^var\s+egret_res_array\s+=\s+(.*)\s?;?\s?$/uims';

        if(preg_match($res_js_regex, $res_js_content, $matches)){
            $res_js_data = json_decode($matches[1], true);
            if(is_array($res_js_data)){
                // $res_js_data
            }else{
                die('res_js_regex_error2');
            }
        }else{
            die('res_js_regex_error');
        }

        $content = file_get_contents($theme_card_js);
        $get_card_regex = '/^var\s+test_json\s*=\s*(.*)\s*;*\s*$/uims';

        function collect_img($sucai_path,$res){
            $files = glob($sucai_path.'/*');
            foreach($files as $file) {
                if(is_dir($file)){
                    $res = collect_img($file,$res);
                }else{
                    $res[] = realpath($file);
                }
            }
            return $res;
        }
        $all_resource_array = collect_img($sucai_path,array());

        if (preg_match('/^var\s+theme_card_json\s+=\s+(.*)\s?;?\s?$/uims', $content, $matches)) {
            $data = json_decode($matches[1], true);
            if(is_array($data)){
                // echo '<pre>';var_dump($data);die;
                // 检查主题里的card是否存在
                foreach($data['Themes'] as $theme_cate){
                    foreach($theme_cate as $theme) {
                        foreach($theme['cards'] as $cards) {
                            foreach($cards as $card) {
                                if(!isset($data['Cards'][$card])){
                                    die('error:检查主题里的card是否存在');
                                }
                            }
                        }
                    }
                }
                // 检查cards里的每一个模版
                foreach($data['Cards'] as $card){
                    //echo '<pre>';var_dump($card);echo '<br />';
                    if (preg_match('/^n(\d+)$/uims', $card['type'], $matches)) {
                        $type_number = $matches[1];
                        $card_file = $resource_root.$card['path'];
                        // echo '<pre>';var_dump($card_file);echo '<br />';continue;
                        $card_json_content = file_get_contents($card_file);
                        if (preg_match($get_card_regex, $card_json_content, $matches)) {
                            $json_data = json_decode($matches[1], true);
                            if(is_array($json_data)){
                                $card_key = $json_data['Cards']['CardIndex'];
                                $json_data = $json_data['Cards'][$card_key];
                                $template_image_cnt = 0;
                                foreach ($json_data['assets'] as $asset_name => $asset){
                                    if (strpos($asset_name, "Image") !== false) {
                                        if(isset($asset['url']) && $asset['url'] != ''){
                                            if(strpos($asset['url'],'51kaji') !== false){
                                                die($card['path'].'/resource 51kaji error');
                                            }
                                            $url_info = parse_url($asset['url']);
                                            if(strpos($url_info['path'],'/resource') !== 0){
                                                die($card['path'].'/resource error');
                                            }
                                            $url_path = substr($url_info['path'],10);
                                            $url_realpath = realpath($resource_root.$url_path);
                                            if(!in_array($url_realpath,$all_resource_array)){
                                                var_dump($card['path'].'--'.$url_realpath.'   url error');echo '<br />';
                                                // echo 'overlay '.$resource_root.$res_js_data[$asset['overlay']]['url'];echo '<br />';
                                            }
                                        }
                                    }
                                    if (strpos($asset_name, "Image") !== false && $asset['isTemplate'] === true) {
                                        // echo '<pre>';var_dump($asset_name);echo '<br />';
                                        $template_image_cnt++;
                                        if(isset($asset['overlay']) && $asset['overlay'] != ''){
                                            $check_overlay = realpath($resource_root.$res_js_data[$asset['overlay']]['url']);
                                            if(!in_array($check_overlay,$all_resource_array)){
                                                var_dump($check_overlay.'   overlay');echo '<br />';
                                                // echo 'overlay '.$resource_root.$res_js_data[$asset['overlay']]['url'];echo '<br />';
                                            }
                                        }
                                        if(isset($asset['bottomborder']) && $asset['bottomborder'] != ''){
                                            $check_bottomborder = realpath($resource_root.$res_js_data[$asset['bottomborder']]['url']);
                                            if(!in_array($check_bottomborder,$all_resource_array)){
                                                var_dump($check_bottomborder.'   bottomborder');echo '<br />';
                                                // echo 'bottomborder '.$resource_root.$res_js_data[$asset['bottomborder']]['url'];echo '<br />';
                                            }
                                        }
                                    }
                                }
                                if($template_image_cnt != $type_number){
                                    echo $card['path'];echo '<br />';
                                }
                                // echo $template_image_cnt;echo '<br />';
                                // echo '<pre>';var_dump($json_data);echo '<br />';
                            }else{
                                die($card_file.'   json不规范2');
                            }
                        }else{
                            die($card['path'].'   json不规范1');
                        }
                    }else{
                        die($card['path'].'   type不规范');
                    }
                }
                die('all_ok');
            }else{
                die('error2');
            }
        }else{
            die('error');
        }
    }

    public function getTemplateCard(){

        $ret = array(
            'status' => 'ok',
            'result' => null
        );

        $cards = $_POST['cards'];
        $resource_path = realpath(APP_PATH).$this->resource_path;
        $theme_card_js = $resource_path.'theme_card.js';
        $content = file_get_contents($theme_card_js);

        $get_card_regex = '/^var\s+test_json\s*=\s*(.*)\s*;*\s*$/uims';

        if (preg_match('/^var\s+theme_card_json\s+=\s+(.*)\s?;?\s?$/uims', $content, $matches)) {
            $data = json_decode($matches[1], true);
            if(is_array($data)){

                $getSingleCardJson = function ($card, $only_data = false) use ($resource_path, $data, $get_card_regex) {
                    $ret = array();
                    $content_file = $resource_path.$data['Cards'][$card]['path'];
                    $card_json_content = file_get_contents($content_file);
                    if (preg_match($get_card_regex, $card_json_content, $matches)) {
                        $json_data = json_decode($matches[1], true);
                        if(is_array($json_data)){
                            $card_key = $json_data['Cards']['CardIndex'];
                            $json_data = $json_data['Cards'][$card_key];
                            //echo '<pre>';var_dump($json_data);die;
                            $ret['status'] = 'ok';
                            $ret['result'] = $json_data;
                        }else{
                            $ret['status'] = 'error';
                            $ret['result'] = 'E1_card_json_parse_error_'.$card;
                        }
                    }else{
                        $ret['status'] = 'error';
                        $ret['result'] = 'E2_card_json_regex_error_'.$card;
                    }
                    if($only_data){
                        return $ret['result'];
                    }else{
                        return $ret;
                    }
                };

                foreach ($cards as $card){
                    if(is_array($card)){
                        // 多张卡片
                        $cards_result = array();
                        foreach($card as $photo_number => $card_set){
                            $cards_result[$photo_number] = array();
                            foreach ($card_set as $single_card_cid){
                                $single_card_json = $getSingleCardJson($single_card_cid,true);
                                if(is_string($single_card_json)){
                                    $ret['status'] = 'error';
                                    $ret['result'] = $single_card_json;
                                    $this->ajaxReturn($ret); // 错误
                                }
                                $cards_result[$photo_number][$single_card_cid] = $single_card_json;
                            }
                        }
                        $ret['result'] = $cards_result;
                    }else{
                        // 单张卡片
                        $ret = $getSingleCardJson($card);
                    }
                }
            }else{
                $ret['status'] = 'error';
                $ret['result'] = 'json_parse_error';
            }
        }else{
            $ret['status'] = 'error';
            $ret['result'] = 'json_regex_error';
        }

        $this->ajaxReturn($ret);
    }

    public function cv($c_value){
        header('Content-Type: text/javascript');
        // generate js code
        $uid = $this->decodeZpkUid($c_value);
        if(!empty($uid)){
            echo '(function () {var img = new Image(1, 1);img.src = "http://www.'.$this->display_domain.'/index/index/cvimg/c_value/'.$c_value.'";})()';
        }
    }

    public function cvimg($c_value){

        // open the file in a binary mode
        $name = realpath(APP_PATH).'/../Public/Image/app.gif';
        $fp = fopen($name, 'rb');

        // send the right headers
        header("Content-Type: image/png");
        header("Content-Length: " . filesize($name));

        $uid = $this->decodeZpkUid($c_value);

        if(!empty($uid)){

            header('P3P: CP="NOI DSP COR CURa ADMa DEVa PSAa PSDa OUR IND UNI PUR NAV"'); // cnzz
            // header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
            // header("P3P: policyref=\"http://www.example.com/w3c/p3p.xml\", CP=\"CURa ADMa DEVa CONo HISa OUR IND DSP ALL COR\"");

            header('Access-Control-Allow-Origin: http://www.'.$this->display_domain);
            header("Access-Control-Allow-Credentials: true");
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

            session('uid', $uid);

            $c_t = $this->cookie_time;
            $c_v = $this->generate_cookie($uid);

            setcookie('hash_value', $c_v, time() + $c_t, '/', '.'.$this->display_domain);
        }

        // dump the picture and stop the script
        fpassthru($fp);
        exit;
    }

    // 转换一键打印数据至照片卡
    public function convertZpk(){

        $json = '{"post":[{"background":"http://static.molixiangce.com/Public/Image/m55/print/bg.jpg","assets":[],"imgs":[{"x":58.44999999999993,"y":412.965,"width":1052.1000000000001,"height":736.47,"mask":null,"border":{"url":"http://7xo7gt.media1.z0.glb.clouddn.com/picture/m55_border.jpg","width":"20"},"url":"http://7xo7y1.com3.z0.glb.qiniucdn.com/mmbiz/mrgk7p8icpjL4aExwqXVC9TiaapDWiba6eibbepRKOZYeNQfUJVhicRtJQibK6nCyRWpTh5OpeZKemPFSwJe52x1KD8Q/640"}]},{"background":"http://static.molixiangce.com/Public/Image/m55/print/bg.jpg","assets":[],"imgs":[{"x":175.35000000000002,"y":322.6797656250001,"width":818.3,"height":1090.6404687499999,"mask":null,"border":{"url":"http://7xo7gt.media1.z0.glb.clouddn.com/picture/m55_border.jpg","width":"20"},"url":"http://7xo7y1.com3.z0.glb.qiniucdn.com/mmbiz/mrgk7p8icpjL4aExwqXVC9TiaapDWiba6eibNYQnfg1K0Qpt7pvficTPFppwvUvicIPfCNEqhnj72huORXfCtXjRgzfA/640"}]},{"background":"http://static.molixiangce.com/Public/Image/m55/print/bg.jpg","assets":[],"imgs":[{"x":175.35,"y":206.30717187500005,"width":549.43,"height":733.1456562499999,"mask":null,"border":{"url":"http://7xo7gt.media1.z0.glb.clouddn.com/picture/m55_border.jpg","width":"20"},"url":"http://7xo7y1.com3.z0.glb.qiniucdn.com/mmbiz/mrgk7p8icpjL4aExwqXVC9TiaapDWiba6eibNqcJa3dXwHfEobS4Ob7qn1A0ORoTOUI8cu5iaAa7dbAwiawYib1RSOPbQ/640"},{"x":467.6,"y":813.907171875,"width":526.0500000000001,"height":935.3826562500001,"mask":null,"border":{"url":"http://7xo7gt.media1.z0.glb.clouddn.com/picture/m55_border.jpg","width":"20"},"url":"http://7xo7y1.com3.z0.glb.qiniucdn.com/mmbiz/mrgk7p8icpjL4aExwqXVC9TiaapDWiba6eibhQGtVOsn0LvcJ09HiaEIIKq1diaACENLHc4sRZcqc2Oic43SnPaOzzEdw/640"}]},{"background":"http://static.molixiangce.com/Public/Image/m55/print/bg.jpg","assets":[],"imgs":[{"x":175.35000000000002,"y":322.6797656250001,"width":818.3,"height":1090.6404687499999,"mask":null,"border":{"url":"http://7xo7gt.media1.z0.glb.clouddn.com/picture/m55_border.jpg","width":"20"},"url":"http://7xo7y1.com3.z0.glb.qiniucdn.com/mmbiz/mrgk7p8icpjL4aExwqXVC9TiaapDWiba6eibvkzRX27yct4Ga3K618qRs6iaTXo2HVMgJp8phlO2ORcPTzjT1DjicCiaQ/640"}]},{"background":"http://static.molixiangce.com/Public/Image/m55/print/bg.jpg","assets":[],"imgs":[{"x":58.44999999999993,"y":255.14999999999992,"width":1052.1000000000001,"height":1052.1000000000001,"mask":null,"border":{"url":"http://7xo7gt.media1.z0.glb.clouddn.com/picture/m55_border.jpg","width":"20"},"url":"http://7xo7y1.com3.z0.glb.qiniucdn.com/mmbiz/mrgk7p8icpjL4aExwqXVC9TiaapDWiba6eibRD1IYC9iaJiczpiamxAZeTUchrXX0BYZm8pP321XibicwS7NAt0xqqXkkkw/640"}]}],"horizontal":[],"portrait ":[]}';

        $json = json_decode($json, true);

        // echo '<pre>';var_dump($json);die;

        $cards = $json['post'];

        function error_msg($info){
        	var_dump($info);die;
        }

        foreach ($cards as $key => $card) {
        	if(empty($card['background'])){
        		error_msg('缺少背景');
        	}
        	foreach ($card['assets'] as $asset_key => $asset_value) {
        		if(empty($asset_value['x'])){
        			error_msg('assets 缺少x');
        		}
        		if(empty($asset_value['y'])){
        			error_msg('assets 缺少y');
        		}
        		if(empty($asset_value['url'])){
        			error_msg('assets 缺少url');
        		}
        		if(empty($asset_value['width'])){
        			error_msg('assets 缺少width');
        		}
        		if(empty($asset_value['height'])){
        			error_msg('assets 缺少height');
        		}
        	}
        	foreach ($card['imgs'] as $asset_key => $asset_value) {
        		if(empty($asset_value['x'])){
        			error_msg('imgs 缺少x');
        		}
        		if(empty($asset_value['y'])){
        			error_msg('imgs 缺少y');
        		}
        		if(empty($asset_value['url'])){
        			error_msg('imgs 缺少url');
        		}
        		if(empty($asset_value['width'])){
        			error_msg('imgs 缺少width');
        		}
        		if(empty($asset_value['height'])){
        			error_msg('imgs 缺少height');
        		}
        	}
        }

        $max_id = 1000;
        $max_id_start = $max_id;
        $card_index = 'Card_'.$max_id_start;
        $json_obj = array(
        	"ver" => "1.0",
        	"dirty" => 1,
        	"Cards" => array(
        		"CardIndex" => $card_index,
        		"CardAnimte" => array(
        			"name" => "Animate_fly",
        			"direction" => "upDown"
        		),
        		"Maxid" => 0
        	),
        	"Card_total_num" => count($cards)
        );

        function convert_data($asset_value, &$max_id, &$idx, &$temp_obj){

        	$border_width = 0;

            $data_base_width = 1169;
            $data_base_height = 1736;

            // 1169 1736
            // 1169 1704（zpk）

            $convert_size = array('x','y','width','height');
            foreach ($convert_size as $size_item){
                $asset_value[$size_item] = $asset_value[$size_item] / 2;
            }

        	if(!empty($asset_value['border']) && !empty($asset_value['border']['url'])){

        		$max_id++;
        		$idx++;

        		$temp_obj['assets']['Image_'.$max_id] = array(
        			"visible" => true,
        			"alpha" => 1,
        			"assets" => array(),
        			"events" => array(),
        			"isTemplate" => false,
        			"i" => $idx,
        			"url" => $asset_value['border']['url'],
        			"width" => $asset_value['width'],
        			"height" => $asset_value['height'],
        			"rotation" => 0,
        		);

        		if(!empty($asset_value['rotation'])){
        			$temp_obj['assets']['Image_'.$max_id]['x'] = $asset_value['x'] - $asset_value['width']/2;
        			$temp_obj['assets']['Image_'.$max_id]['y'] = $asset_value['y'] - $asset_value['height']/2;
        			$temp_obj['assets']['Image_'.$max_id]['rotation'] = $asset_value['rotation'];
        		}else{
        			$temp_obj['assets']['Image_'.$max_id]['x'] = $asset_value['x'];
        			$temp_obj['assets']['Image_'.$max_id]['y'] = $asset_value['y'];
        		}

        		if(empty($asset_value['border']['width'])){
        			$border_width = 10;
        		}else{
        			$border_width = $asset_value['border']['width']/2;
        		}
        	}

        	$max_id++;
        	$idx++;

        	$real_width = $asset_value['width'] - $border_width * 2;
        	$real_height = $asset_value['height'] - $border_width * 2;

        	$temp_obj['assets']['Image_'.$max_id] = array(
        		"visible" => true,
        		"alpha" => 1,
        		"assets" => array(),
        		"events" => array(),
        		"isTemplate" => true,
        		"i" => $idx,
        		"url" => $asset_value['url'],
        		"width" => $real_width,
        		"height" => $real_height,
        		"rotation" => 0,
        	);

        	if(!empty($asset_value['mask'])){
        		$temp_obj['assets']['Image_'.$max_id]['overlay'] = $asset_value['mask'];
        	}

        	if(!empty($asset_value['rotation'])){
        		$temp_obj['assets']['Image_'.$max_id]['x'] = $asset_value['x'] - $real_width/2;
        		$temp_obj['assets']['Image_'.$max_id]['y'] = $asset_value['y'] - $real_height/2;
        		$temp_obj['assets']['Image_'.$max_id]['rotation'] = $asset_value['rotation'];
        	}else{
        		$temp_obj['assets']['Image_'.$max_id]['x'] = $asset_value['x'] + $border_width;
        		$temp_obj['assets']['Image_'.$max_id]['y'] = $asset_value['y'] + $border_width;
        	}
        }

        $last_maxid = null;
        foreach ($cards as $key => $card) {
        	$json_obj['Cards']["Card_".$max_id] = array();
        	$temp_obj = &$json_obj['Cards']["Card_".$max_id];
        	if($max_id == $max_id_start){
                $temp_obj['prev'] = null;
            }else{
                $temp_obj['prev'] = 'Card_'.$last_maxid;
            }
            $last_maxid = $max_id;

            $temp_obj['assets'] = array();

            $max_id++;
            $temp_obj['assets']['Background_'.$max_id] = array(
        		"assets" => array(),
        		"events" => array(),
        		"isTemplate" => false,
        		"i" => 0,
        		"url" => $card['background'],
        		"x" => 0,
        		"y" => 0,
        		"scaleX" => 1,
        		"scaleY" => 1,
        		"rotation" => 0,
        		"width" => $this->base_player_width,
        		"height" => $this->base_player_height,
        		"visible" => true,
        		"alpha" => 1
        	);

        	$idx = 0;
        	$idx_plus = 0;

        	foreach ($card['assets'] as $asset_key => $asset_value) {
        		convert_data($asset_value, $max_id, $idx, $temp_obj);
        	}

        	foreach ($card['imgs'] as $asset_key => $asset_value) {
        		convert_data($asset_value, $max_id, $idx, $temp_obj);
        	}

            $max_id++;

        	$temp_obj['next'] = 'Card_'.$max_id;
        }

        $temp_obj['next'] = null;

        $json_obj['Cards']['Maxid'] = $max_id;

        echo json_encode($json_obj);die;
        echo '<pre>';var_dump($json_obj);

        // 照片卡遮罩与边框底支持直接填入url TODO
    }

    public function checkPayOrder($cid) {

        $card_model = D('Index/ZpkData');
        $result = $card_model->getCardInfo($cid);

        $ret = array(
            'status' => 'ok',
            'result' => null
        );

        if(count($result)>0) {

            foreach(array('name', 'phone', 'street', 'province', 'city', 'area') as $v) {
                $testVal = trim($result[$v]);
                if(empty($testVal)) {
                    $ret['status'] = 'error';
                    $ret['result'] = '联系方式不完整';
                    $this->ajaxReturn($ret);
                }
            }

            $data = json_decode($result['data'], true);
            if(is_array($data)){
                $res = array('maxlength'=>0);
                $res = $this->iteration_array($data['Cards'], $res, 'check:url:wx');
                if($res['maxlength'] != 0){
                    $ret['status'] = 'error';
                    $ret['result'] = '还有图片未全上传完成';
                    $this->ajaxReturn($ret);
                }
            }else{
                $ret['status'] = 'error';
                $ret['result'] = '照片卡数据错误';
                $this->ajaxReturn($ret);
            }

        }else{
            $ret['status'] = 'error';
            $ret['result'] = '照片卡不存在';
        }

        $this->ajaxReturn($ret);
    }

}