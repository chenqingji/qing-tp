<?php
// 微信SDK接口相关模块
namespace Index\Model;
use Think\Model;

class WeixinModel extends Model {
	Protected $autoCheckFields = false;

	//魔力快印
 	private $wx_appid = 'wx1529637523909e1e';
 	private $wx_appsecret = '76d70f010933842a728efb5cadb5c786';
	
	private $weixin_ticketKey;
	private $weixin_accesstokenKey;
	
	public function __construct($name='',$tablePrefix='',$connection='') {
		parent::__construct($name,$tablePrefix,$connection);

		vendor("curl.function");
		$this->c = new \curl();

		//根据不同域名来设置appid和appsecret, 缓存key
		$httpHost = $_SERVER['HTTP_HOST'];
		
//		if(strpos ( $httpHost, "molixiangce.com" ) !== false){
//			// 魔力相册 域名使用  轻展示 接口
//			$this->wx_appid = 'wx24acf51620f63ce6';
//			$this->wx_appsecret = '5219b33e5fdd76a66e4dbb40c8cf2296';
//		}else{
//			// 其他使用时尚社团的 接口
//			$this->wx_appid = 'wx851832daba2badba';
//			$this->wx_appsecret = '941b97264f2456d38e76004b9ca85b7e';
//		}
		
		$this->weixin_ticketKey = "weixin_ticket_".$this->wx_appid;
// 		$this->weixin_accesstokenKey = "weixin_accesstoken_".$this->wx_appid;
		$this->weixin_accesstokenKey = WX_ACCESS_TOKEN;
	}

	public function getAppid(){
		return $this->wx_appid;
	}

	// 获取WX-JSSDK的signature值
	public function getSignature($nonceStr,$time,$url=''){
		$signature = "";
		if($this->checkYuming()){
			$ticket = $this->getTicket();
			if($url == ''){
				$url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			}
			$signature = "jsapi_ticket=$ticket&noncestr=$nonceStr&timestamp=$time&url=$url";
			$signature = sha1($signature);
		}
		return $signature;
	}

	// 获取WX-JSSDK的ticket值
	public function getTicket(){
		$_ticket  = S($this->weixin_ticketKey);
		if( $_ticket )
			return $_ticket;
		
		$_ticket = $this->refereshTicket();
		
		return $_ticket;
	}

	//刷新Ticket
	public function refereshTicket(){
		// 实际获取SDK-Ticket
		$_accesstoken = $this->getAccessToken();
		$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=$_accesstoken&type=jsapi";
		$result = $this->get($url);
		
		if(isset($result->ticket))
		{
			$_ticket = $result->ticket;
		}
		else if( isset($result->errmsg) ){
			S($this->weixin_accesstokenKey,"unknow",1);       // 出现错误，清除accesstoken 
			S($this->weixin_ticketKey,"unknow",30);
			//E("微信ticket返回错误信息:".$result->errmsg);
		}else{
			S($this->weixin_accesstokenKey,"unknow",1);       // 出现错误，清除accesstoken
			S($this->weixin_ticketKey,"unknow",30);
			//E("未知错误");
		}
		
		if( $_ticket )
		{
			S($this->weixin_ticketKey,$_ticket,3600);
		}
		
		return $_ticket;
	}

		// 获取accesstoken
	public function getAccessToken()
	{
		$_accesstoken  = S($this->weixin_accesstokenKey);
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
			S($this->weixin_accesstokenKey,"unknow",30);   //出现错误，延迟30秒再读取
		}
		
		if( $_accesstoken ){
			S($this->weixin_accesstokenKey,$_accesstoken,600);
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
	
	//非特定域名的拿不到接口权限，不需要去读取微信接口
	public function checkYuming(){
		return true;
		//$httpHost = $_SERVER['HTTP_HOST'];
		//return (strpos($httpHost, "haitaoj.com") !== false || strpos($httpHost,"gongzhonghao.cn") !== false);
	}
}