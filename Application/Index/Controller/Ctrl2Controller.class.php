<?php
namespace Index\Controller;

use User\Model\UserModel;
use Index\Model\WeixinModel;

//TODO 常用APP版API

class Ctrl2Controller extends BaseController {
	
    /* 微信接口 */
    public function oauth() {
        $code = I('get.code');

        if($code == ''){
            json_encode(array('ret'=>-1));
	    echo "no code";
            exit();
        }

        $appId = "wx9e5c53fce4bacff1";
        $appSecret = "4cfeabcd52bff1c3f7c51277da5333a4";
        $accessUrl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appId&secret=$appSecret&code=$code&grant_type=authorization_code";

        vendor("curl.function");
        $c = new \curl();
        $accessJson = $c->get($accessUrl);
        $accessJson = json_decode($accessJson,true); // true will return array

        if (array_key_exists('errcode', $accessJson)) {
            json_encode(array('ret'=>-1));
	    echo "has errcode";
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
}