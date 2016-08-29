<?php
namespace Warehouse\Controller;

use User\Model\UserModel;

use Think\Controller;
class BaseController extends Controller {
    protected $user_id = 0;
    protected $static_v = '92';
    protected $minUploadPicCount = 1;

    protected $orderStatus = array(
        "init" => array(value => 0, desc => "未提交"), // 初始状态未提交并且未支付
        "submit" => array(value => 1, desc => "<span style='color:red'>未付款</span>"), // 订单已提交但是未支付
        "paid" => array(value => 10, desc => "<span style='color:green'>已付款</span>"), // 已支付
    );

    protected $orderOpStatus = array(
        "init" => array(value => 0, desc => "<span style='color:red'>未下载</span>"), // 初始状态未提交并且未支付
        "download" => array(value => 1, desc => "<span style='color:green'>已下载</span>"), // 订单已提交但是未支付
    );

    protected $uploadType = array(
        "wxType" => "wx",
        "uploadType" => "upyun",
        "wxLocalType" => "wxLocal"
    );

    public function __construct(){
        parent::__construct();
        date_default_timezone_set('PRC');
    }

    // web端微信认证 url
    protected function getWeixinUrl(){
        $backUrl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        cookie('backUrl',$backUrl,3600);
        $backUrl = 'http://'.$_SERVER['HTTP_HOST'];
    	return "https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx1529637523909e1e&redirect_uri=http%3A%2F%2Fyin.molixiangce.com%2FIndex%2FCtrl%2Foauth&response_type=code&scope=snsapi_userinfo&state=".$backUrl."#wechat_redirect";
    }
    
    protected function decodeUid($token) {
        $hashValue = explode('-',$token);
        $uid = null;
    
        if(!empty($hashValue[1])
            && $hashValue[1] == md5($hashValue[0].'-moli-jdui-'.$hashValue[0])) {

            // 检测用户是否真实存在
            $userInfo = $this->getUserInfo($hashValue[0]);
            if($userInfo) {
                $uid = $hashValue[0];
            }   
        }
        return $uid;
    }

    protected function checkReg($unionid){
        return D('Index/User')->checkReg($unionid);
    }

    protected function getUserInfo($uid){
        return D('Index/User')->getUserInfo($uid);
    }

    protected function refreshInfo($info){
        D('Index/User')->refreshInfo($info);
    }

    protected function insertInfo($info){
        return D('Index/User')->insertInfo($info);
    }
}