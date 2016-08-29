<?php
namespace Index\Controller;

use User\Model\UserModel;
use Index\Model\WeixinModel;

//TODO 常用APP版API

class CtrlController extends BaseController {
	
    /* 微信接口 */
    public function oauth() {
        $code = I('get.code');
        if($code == ''){
            json_encode(array('ret'=>-1));
            exit();
        }

        $appId = "wx1529637523909e1e";
        $appSecret = "76d70f010933842a728efb5cadb5c786";
        $accessUrl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appId&secret=$appSecret&code=$code&grant_type=authorization_code";

        vendor("curl.function");
        $c = new \curl();
        $accessJson = $c->get($accessUrl);
        $accessJson = json_decode($accessJson,true); // true will return array

        if (array_key_exists('errcode', $accessJson)) {
            json_encode(array('ret'=>-1));
            exit();
        }
        
//      S(WX_ACCESS_TOKEN,$accessJson['access_token']);

        $a = urlencode($accessJson['access_token']);
        $b = urlencode($accessJson['expires_in']);
        $c = urlencode($accessJson['refresh_token']);
        $d = urlencode($accessJson['openid']);
        $e = urlencode($accessJson['scope']);
        $f = urlencode($accessJson['unionid']);

        $callbackHost =  'http://yin.molixiangce.com';
        $state = I('get.state');
        if(!empty($state)){
            $parse_url = parse_url($state);
            $callbackHost = "http://".$parse_url['host']."/";
            $state = urlencode($state);
        }else{
            $state = 'null';
        }

        $url = $callbackHost.'/Index/Index/OauthCallback?access_token='.$a.'&expires_in='.$b.'&refresh_token='.$c.'&openid='.$d.'&scope='.$e.'&unionid='.$f.'&state='.$state;
        header("location: $url");
    }


    /* 微信接口 */
    public function oauthOpenId() {
        $code = I('get.code');
        if($code == ''){
            json_encode(array('ret'=>-1));
            exit();
        }

        $appId = "wx1529637523909e1e";
        $appSecret = "76d70f010933842a728efb5cadb5c786";
        $accessUrl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appId&secret=$appSecret&code=$code&grant_type=authorization_code";

        vendor("curl.function");
        $c = new \curl();
        $accessJson = $c->get($accessUrl);
        $accessJson = json_decode($accessJson,true); // true will return array

        if (array_key_exists('errcode', $accessJson)) {
            json_encode(array('ret'=>-1));
            exit();
        }

        $a = urlencode($accessJson['access_token']);
        $b = urlencode($accessJson['expires_in']);
        $c = urlencode($accessJson['refresh_token']);
        $d = urlencode($accessJson['openid']);
        $e = urlencode($accessJson['scope']);
        $f = urlencode($accessJson['unionid']);

        $callbackHost =  'http://yin.molixiangce.com';
        $state = I('get.state');
        if(!empty($state)){
            $parse_url = parse_url($state);
            $callbackHost = "http://".$parse_url['host']."/";
            $state = urlencode($state);
        }else{
            $state = 'null';
        }

        $url = $callbackHost.'/Index/Ctrl/OauthCallback?access_token='.$a.'&expires_in='.$b.'&refresh_token='.$c.'&openid='.$d.'&scope='.$e.'&unionid='.$f.'&state='.$state;
        header("location: $url");
    }

    public function OauthCallback() {
        $openId = I('get.openid');
        $backUrl = cookie('backUrl');
        if(strpos($backUrl,"?") === false) {
            if($backUrl[mb_strlen($backUrl) - 1] != "/") {
                $backUrl.="/";
            }
            $backUrl.="openid/$openId";
        } else {
            $backUrl.="&openid=$openId";
        }
        header("location: $backUrl");die;
    }

    public function show() {
		var_dump(S(WX_ACCESS_TOKEN));
    }
    
    public function clear() {
    	S(WX_ACCESS_TOKEN,"unknow",1);
    }

    public function test() {
        echo "in test";die;
    	//D("Index/User")->saveWebOpenId('oOI4quBWRCAgwwr9niZl1xL35v_U','oH1g_uJ5GZzIz_ynu4GwbCkjbkz4');
    	
        exit();
        $accessToken = (new WeixinModel())->getAccessToken();

        vendor("curl.function");
        $c = new \curl();
        $accessUrl = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$accessToken";
        $sceneId = 11111;

        $c->proxy = "http://192.168.199.250:8888";

        $retJson = $c->post($accessUrl,json_encode(array(
            "expire_seconds" => 604800,
            "action_name" => "QR_SCENE",
            "action_info" => array(
                "scene" => array(
                    "scene_id" => $sceneId
            ))
        )));

        $retJson = (array)json_decode($retJson);

        if (array_key_exists('errcode', $retJson)) {
            echo "error";
            exit();
        }

        var_dump($retJson);
    }
}