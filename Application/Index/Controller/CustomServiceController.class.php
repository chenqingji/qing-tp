<?php
namespace Index\Controller;
use Index\Model\WeixinModel;
use Index\Model\CouponModel;

class CustomServiceController extends BaseController {
    // 无需检验认证的web端页面请求
    private $noCheckActions = array();

    protected $cookie_time = 0;
    protected $auth_time = '1433129516';
    protected $accessTokenTime = 43200;
    private $login_cookie = "ky_login_userid";
    private $display_domain;

    public function __construct() {
        parent::__construct();
        //session('uid', 25);   //测试环境测试使用
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


    public function customerServicesNew() {
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
            $backUrl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
            header('Location: '.$this->getWeixinUrl($backUrl));
            die();
        } else {
            $this->user_id = $uid;
            setcookie($this->login_cookie,$uid,time() + $this->cookie_time,"/",$this->display_domain);
        }
    }
}
