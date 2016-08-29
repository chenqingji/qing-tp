<?php
namespace Index\Controller;

use Think\Controller;

class WxMenuController extends Controller {
	//接入url http://www.molixiangce.com/Index/Wx/Run/?f=moli

	private $wx_appid = 'wx1529637523909e1e';
	private $wx_appsecret = '76d70f010933842a728efb5cadb5c786';
	
	private $c;
	
	public function __construct() {
		parent::__construct ();
		
		vendor("curl.function");
		$this->c = new \curl();
	}
	
	public function create(){
 		$accesstoken = $this->getAccessToken();
 		
 		$creatUrl = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=$accesstoken";
 		$creatJson = $this->post($creatUrl,$this->getMenuData());
 		$creatJson = json_decode($creatJson);
 		if($creatJson -> errcode == 0){
 			echo "success！";
 		}else{
 			echo "fail:".$creatJson->errcode;
 		}
	}
	
	// 获取accesstoken
	public function getAccessToken()
	{
		$_accesstoken  = S(WX_ACCESS_TOKEN);
		if( $_accesstoken )
			return $_accesstoken;
	
		$_accesstoken = $this->refereshAccessToken();
	
		return $_accesstoken;
	}
	
	// 刷新获取accesstoken
	public function refereshAccessToken()
	{
		// 实际获取accesstoken
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->wx_appid."&secret=".$this->wx_appsecret;
		$result = $this->get($url);
	
		if(isset($result->access_token)){
			$_accesstoken = $result->access_token;
		}else{
			S(WX_ACCESS_TOKEN,"unknow",30);   //出现错误，延迟30秒再读取
		}
	
		if( $_accesstoken ){
			S(WX_ACCESS_TOKEN,$_accesstoken,1000);
		}
	
		return $_accesstoken;
	}
	
	// 支持get
	public function get($url)
	{
		$jsonData = $this->c->get($url);
		$result = json_decode($jsonData);
		return $result;
	}
	
	// 支持post
	public function post($url,$data)
	{
		$jsonData = $this->c->post($url, $data);
		$result = json_decode($jsonData);
		return $result;
	}
	
	private function getMenuData() {
		$data = <<<EFO
{
    "button":[
        {
            "type":"view",
            "name":"打印照片",
            "url":"http://yin.molixiangce.com/Index/Index/order"
        },
        {
            "type":"view",
            "name":"我的订单",
            "url":"http://yin.molixiangce.com/Index/Index/orderList"
        },
        {
            "type":"view",
            "name":"联系客服",
            "url":"http://mp.weixin.qq.com/s?__biz=MzA5MTE1OTA3MQ==&mid=401242008&idx=1&sn=9549a9ab16c4e43e6b9ee69f76fec8c2#wechat_redirect"
        }
      ]
 }
EFO;
		/*$data = '{
	     "button":[
			{
		           "name":"制作相册",
		           "sub_button":[
		           {	
		               "type":"pic_weixin",
		               "name":"创建新相册",
		               "key": "new"
		            },
		            {
		               "type":"pic_weixin",
		               "name":"添加照片",
		               "key": "add"
		            }]
		       },
		      {
		          "type":"view",
		          "name":"我的相册",
		          "url":"http://99moli.ygj.com.cn/Index/Index/mycard"
		      }]
		 }';*/
		return $data;
	}
}