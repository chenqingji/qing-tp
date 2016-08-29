<?php

namespace Index\Controller;

use Think\Controller;
use Index\Model\WeixinModel;
//use Index\Model\CardDataModel;
use Index\Model\PrintItemModel as CardDataModel;

class DayinController extends Controller {
	private $mailType = "YUNDA"; // 全峰快递 QFKD 韵达 YUNDA
	private $appkey = "23288990";
	private $secret = "c6e5214a3c0d7ce81e61020cae1b36f3";
	private $sellid = "1835837568"; // 星罗网络科技的uid
	private $sessionKey = "6200d21947d0e3f91abe8cd9948e8ZZba8a27a8a65e05831835837568";		//直接从数据库读取
	private $refresh_token = "6201d218d6ac23b148ad2cadc2e0cegad36dc73e361f5441835837568";
	private $no_pay_push = false;
	private $express_open_off = true;
	public function __construct() {
		parent::__construct ();
	}
	public function index() {
		$todayPackNum = D ( "Index/Package" )->getTodayPackageCount ();
		
		$this->assign ( "resyuming", RES_YUMING );
		$this->assign ( "appkey", $this->appkey );
		$this->assign ( "sellid", $this->sellid );
		$this->assign ( "mailType", $this->mailType );
		
		$this->assign ( "maildate", date ( "Y-m-d", time () ) );
		$this->assign( "todayPackNum", $todayPackNum);
		$this->display ( 'Dayin/index' );
	}

	public function dayinStart() {
// 		echo A('Index/Admin')->checkLogin();
// 		exit();
	
		$orderNo = I ( "post.orderNo", "" );
		$newPack = I ( "post.newPack", 0 );
		
// 		$orderNo = "14491151742287"; // 测试数据，TODO 去除
// 		$orderNo = "14490294747604"; // 测试数据2
// 		14490347454075 // 线上 - 刘洋可测试数据
// 		14499758799702 // 线上 - 普通用户测试
		                             
		// 查询该订单信息
		$orderInfo = D ( "Index/CardData" )->getCardByOrderNo ( $orderNo );

		if (!$orderInfo) {
			$this->ajaxReturn ( array (
					"ret" => - 1,
					"msg" => "打印失败，查无该订单信息" 
			) );
		}

		/*
        if (strrpos($orderInfo['province'],'新疆') !== FALSE && (strrpos($orderInfo['city'],'库尔勒') !== FALSE || strrpos($orderInfo['area'],'库尔勒') !== FALSE) ) {
            $this->ajaxReturn ( array (
                "ret" => - 1,
                "msg" => $orderInfo['province']."-".$orderInfo['city']."-".$orderInfo['area'].", 该地区暂时不支持派送!"
            ) );
        }
		*/

        $ems_map = array(
            array('青海','海东地区','循化县'),
            array('青海','海东地区','化隆县'),
            array('青海','海西州','乌兰县'),
            array('青海','海南州','同德县'),
            array('青海','海南州','兴海县'),
            array('青海','海南州','贵南县'),
            array('青海','黄南州','泽库县'),
            array('青海','黄南州','尖扎县'),
            array('青海','黄南州','河南县'),
            array('青海','果洛州','玛沁县'),
            array('青海','果洛州','班玛县'),
            array('青海','果洛州','甘德县'),
            array('青海','果洛州','达日县'),
            array('青海','果洛州','久治县'),
            array('青海','果洛州','玛多县'),
            array('青海','玉树州','玉树县'),
            array('青海','玉树州','杂多县'),
            array('青海','玉树州','称多县'),
            array('青海','玉树州','治多县'),
            array('青海','玉树州','囊谦县'),
            array('青海','玉树州','曲麻莱县'),
            array('四川','阿坝州','若尔盖县'),
            array('四川','阿坝州','红原县'),
            array('四川','阿坝州','阿坝县'),
            array('四川','阿坝州','黑水县'),
            array('四川','阿坝州','壤塘县'),
            array('四川','阿坝州','金阳县'),
            array('四川','阿坝州','布拖县'),
            array('四川','凉山州','雷波县'),
            array('四川','甘孜州','巴塘县'),
            array('四川','甘孜州','白玉县'),
            array('四川','甘孜州','丹巴县'),
            array('四川','甘孜州','道孚县'),
            array('四川','甘孜州','稻城县'),
            array('四川','甘孜州','得荣县'),
            array('四川','甘孜州','德格县'),
            array('四川','甘孜州','九龙县'),
            array('四川','甘孜州','理塘县'),
            array('四川','甘孜州','炉霍县'),
            array('四川','甘孜州','色达县'),
            array('四川','甘孜州','石渠县'),
            array('四川','甘孜州','乡城县'),
            array('四川','甘孜州','新龙县'),
            array('四川','甘孜州','雅江县'),
            array('云南','怒江州','福贡县'),
            array('云南','怒江州','贡山县'),
            array('西藏','拉萨市','林周县'),
            array('西藏','拉萨市','达孜县'),
            array('西藏','拉萨市','尼木县'),
            array('西藏','拉萨市','当雄县'),
            array('西藏','拉萨市','曲水县'),
            array('西藏','拉萨市','墨竹工卡县'),
            array('西藏','拉萨市','堆龙德庆县')
        );

        $check_ems = function($addr) use ($ems_map) {
            foreach ($ems_map as $ems_var){
                if(strrpos( $addr['province'], $ems_var[0] ) !== FALSE &&
                   strrpos( $addr['city'], $ems_var[1] ) !== FALSE &&
                   strrpos( $addr['area'], $ems_var[2] ) !== FALSE ){
                    return true;
                    break;
                }
            }
            if(strrpos( $addr['province'], '浙江' ) !== FALSE &&
               strrpos( $addr['city'], '杭州市' ) !== FALSE &&
               strrpos( $addr['area'], '萧山区' ) !== FALSE &&
               strrpos( $addr['street'], '瓜沥' ) !== FALSE ){
                return true;
            }
            return false;
        };

        $check_info = array(
            'province' => $orderInfo['province'],
            'city' => $orderInfo['city'],
            'area' => $orderInfo['area']
        );
        if ( $check_ems($check_info) ) {
            $this->ajaxReturn ( array (
                "ret" => - 1,
                "msg" => "此订单韵达无配送点，请使用EMS发货。"
            ) );
        }

        if( strrpos( $check_info['province'], '浙江' ) !== FALSE && strrpos( $check_info['city'], '杭州市' ) !== FALSE ){
			$this->ajaxReturn ( array (
			    "ret" => - 1,
			    "msg" => "杭州市订单暂不发货，9月7号再发货。"
			) );
        }
		
		if (intval($orderInfo['status']) === 17) {
			$this->ajaxReturn ( array (
					"ret" => - 1,
					"msg" => "订单已经取消，请勿寄出"
			) );
		}

        // 库尔勒
		
		if($newPack == 1){
			// 新增一个包裹号
			$packId = D ( "Index/Package" )->addPackageId ( $orderNo );
		}else{
			// 获取包裹单号
			$packId = D ( "Index/Package" )->getLastPackageId ( $orderNo );
		}
		
 		//测试返回包裹号
// 		$this->ajaxReturn ( array (
// 				"ret" => - 1,
// 				"msg" => $packId
// 		) );
		
		// 根据订单号获取面单号
		$mailInfo = $this->getMailInfo ($orderInfo , $packId);
		$mailNo = $mailInfo->waybill_apply_new_cols->waybill_apply_new_info->waybill_code->__toString();
		
		if (! $mailNo) {
			if ($mailInfo->code == 27) {
				$this->ajaxReturn(array (
					"ret" => - 1,
					"msg" => "打印失败，授权已经过期，请联系 星罗开发人员，手机：18050047093" 
			    ) );
			}
			$this->ajaxReturn ( array (
					"ret" => - 1,
					"msg" => $mailInfo->code . $mailInfo->msg 
			) );
		}

		$isReYin = 0;				//是否是重复打印
        if($mailNo != $orderInfo['mailno']) {
        		vendor("curl.function");
        		$c = new \curl();
        		
        		$userInfo = D('Index/User')->getUserInfo($orderInfo['uid']);
        		$accessToken = (new WeixinModel())->getAccessToken();
        		$accessUrl = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$accessToken";
        		
        		// 推送微信消息
        		$accessJson = $c->post($accessUrl,json_encode(array(
        				"touser" => $userInfo['webOpenID'],
        				"template_id" => "BWrIrKgMiryB9VAtpJG46fkmIUfVBDzDHflPYON8Kw8",
        				"url" =>  'http://'.$_SERVER['HTTP_HOST'].'/Index/Index/orderDetail/cid/'.$orderInfo['cid'],
        				"data" => array(
        						"first" => array(
        								"value" => "您好，您的订单已出库发货！",
        								"color" => "#173177"
        						),
        						"keyword1" => array(
        								"value" => $orderNo,
        								"color" => "#173177"
        						),
        						"keyword2" => array(
        								"value"=> date("Y-m-d"),
        								"color"=>"#173177"
        						),
        						"remark" => array(
        								"value" => "请耐心等待！点击查看订单信息！",
        								"color" => "#173177"
        						)
        				))));
        		
        		$pics = json_decode($orderInfo['pics']);
        		$pic = (array)$pics[0];
        	if($this->express_open_off){
        		//发货的时候在快递鸟订阅物流信息
        		$this->kdniaoDy($mailNo);
        		
        		// 推送 JPush 消息
        		try {
        			Vendor('JPush.JPush');
        			$notifyRet = M()->table("jpush_token")->where("userId=".$orderInfo['uid'])->select();
        			foreach($notifyRet as $value) {
        				if(!$value['token']){
        					continue;
        				}
        				//,"url_type"=>$pic['type'],"url"=>$pic['url']
        				$data = array("type"=>"5", "orderId"=>$orderInfo['cid'], "courierId"=>"$mailNo","orderno"=>$orderInfo['orderno']);
        				$client = new \JPush('0c4f8e0a723bac74999de4f2', 'a670146d863e77440328cca3');
        				$client->push()
                            ->setPlatform('ios', 'android')
                            ->addAndroidNotification('物流已发货', "您的订单号为 ".$orderInfo['orderno']." 已发货, 快递单号为 $mailNo", 1, $data)
                            ->addIosNotification("物流已发货", 'iOS sound', '+1', true, 'iOS category', $data)
                            ->addIosNotification("您的订单号为 ".$orderInfo['orderno']." 已发货, 快递单号为 $mailNo", null, null, true, null, $data)
                            ->addRegistrationId($value['token'])
                            ->setOptions(null,null,null,true)
                            ->send();
        			}
        		} catch(Exception $e) {
        		}
        	}

        }else{
        	$isReYin = 1;
        }
        
		// 面单号存入数据库
		D("Index/Package")->savePackageMailNo ($packId,$mailNo);
		
		//存入最后一个面单号，方便查询
        (new CardDataModel())->saveCard($orderInfo['cid'],array("mailno"=>"$mailNo"));
		
		//获取最新的今天快递单号数
		$todayPackNum = D ( "Index/Package" )->getTodayPackageCount ();

        $orderInfo['mailno'] = "$mailNo";
		
		// 进行打印操作
		$this->ajaxReturn ( array (
				"orderInfo" => $orderInfo,
				"mailInfo" => json_encode ( $mailInfo ),
				"isReYin" => $isReYin,
				"todayPackNum" => $todayPackNum,
				"url_type"=>$pic['type'],
				"url"=>$pic['url']
		) );
		
		// TODO 是否需要打印确认接口v1.0
	}
	
	private function getMailInfo($orderInfo,$packId) {
		vendor ( "taobao.TopSdk" );
		date_default_timezone_set ( 'Asia/Shanghai' );
		
		$c = new \TopClient ();
		$c->appkey = $this->appkey;
		$c->secretKey = $this->secret;
		$req = new \WlbWaybillIGetRequest ();
		$waybill_apply_new_request = new \WaybillApplyNewRequest ();
		$waybill_apply_new_request->cp_code = $this->mailType; // 全峰快递 QFKD 韵达 YUNDA
		$shipping_address = new \WaybillAddress ();
		$shipping_address->province = "福建省";
		$shipping_address->city = "厦门市";
		$shipping_address->area = "海沧区";
		// $shipping_address->town="八里庄";
		// $shipping_address->address_detail="海沧东孚浦头路9号"; //全峰快递的
		$shipping_address->address_detail = "海沧东孚浦头路9号吉宏股份"; // 韵达
		$waybill_apply_new_request->shipping_address = $shipping_address;
		$trade_order_info_cols = new \TradeOrderInfo ();
		$trade_order_info_cols->consignee_name = $orderInfo ['name'];
		$trade_order_info_cols->order_channels_type = "OTHERS";
		$trade_order_info_cols->trade_order_list = $orderInfo ["orderno"]; // 订单列表,可以塞多个订单号一个包裹？
		$trade_order_info_cols->consignee_phone = $orderInfo ['phone'];
		$consignee_address = new \WaybillAddress ();
		$consignee_address->province = $orderInfo ['province'];
		$consignee_address->city = $orderInfo ['city'];
		$consignee_address->area = $orderInfo ['area'];
		// $consignee_address->town="八里庄";
		$consignee_address->address_detail = $orderInfo ['province'] . $orderInfo ['city'] . $orderInfo ['area'] . $orderInfo ['street'];
		
		$trade_order_info_cols->consignee_address = $consignee_address;
		$trade_order_info_cols->send_phone = "15960812280";
		// $trade_order_info_cols->weight="1"; //TODO 重量？
		$trade_order_info_cols->send_name = "魔力快印官方店";
		$package_items = new \PackageItem ();
		$package_items->item_name = "印刷品";
		$package_items->count = "1";
		$trade_order_info_cols->package_items = $package_items;
		// $logistics_service_list = new \LogisticsService();
		// $logistics_service_list->service_value4_json="{ \"value\":
		// \"100.00\",\"currency\": \"CNY\",\"ensure_type\": \"0\"}";
		// $logistics_service_list->service_code="SVC-DELIVERY-ENV";
		// $trade_order_info_cols->logistics_service_list =
		// $logistics_service_list;
		$trade_order_info_cols->product_type = "STANDARD_EXPRESS";
		$trade_order_info_cols->real_user_id = $this->sellid; // 星罗网络科技的uid
		                                                    // $trade_order_info_cols->volume="1";
		                                                    // //体积数，可选
		$trade_order_info_cols->package_id = $packId; // 电子面单由订单号_包裹号生成
		$waybill_apply_new_request->trade_order_info_cols = $trade_order_info_cols;
		$req->setWaybillApplyNewRequest ( json_encode ( $waybill_apply_new_request ) );
		
// 		$rs = D("Index/SysInfo")->getAccessToken();    //从数据库读取的代码，已放弃
// 		$sessionKey = $rs->access_token; 
		
		$sessionKey = $this->sessionKey;
		$resp = $c->execute ( $req, $sessionKey );
		                                              
		return $resp;
	}
	
	/**
	 * 作用：将xml转为array
	 */
	private function xmlToArray($xml) {
		// 将XML转为array
		$array_data = json_decode ( json_encode ( simplexml_load_string ( $xml, 'SimpleXMLElement', LIBXML_NOCDATA ) ), true );
		return $array_data;
	}
	
	public function taobaotest() {
// 		$resp = $this->getMailInfo ();
// 		// $resp = "<xml><wlb_waybill_i_get_response>
// 		// <waybill_apply_new_cols>
// 		// <waybill_apply_new_info>
// 		// <short_address>hello world</short_address>
// 		// <trade_order_info>
// 		// <consignee_name>张三</consignee_name>
// 		// <order_channels_type>TB</order_channels_type>
// 		// <trade_order_list>
// 		// <string>12321321</string>
// 		// <string>12321321</string>
// 		// </trade_order_list>
// 		// <consignee_phone>13242422352</consignee_phone>
// 		// <consignee_address>
// 		// <area>朝阳区</area>
// 		// <province>北京</province>
// 		// <town>八里庄</town>
// 		// <address_detail>朝阳路高井，财满街，财经中心9号楼21单元6013</address_detail>
// 		// <city>北京市</city>
// 		// </consignee_address>
// 		// <send_phone>13242422352</send_phone>
// 		// <weight>123</weight>
// 		// <send_name>李四</send_name>
// 		// <package_items>
// 		// <package_item>
// 		// <item_name>衣服</item_name>
// 		// <count>123</count>
// 		// </package_item>
// 		// </package_items>
// 		// <logistics_service_list>
// 		// <logistics_service>
// 		// <service_value4_json>{ &quot;value&quot;:
// 		// &quot;100.00&quot;,&quot;currency&quot;:
// 		// &quot;CNY&quot;,&quot;ensure_type&quot;:
// 		// &quot;0&quot;}</service_value4_json>
// 		// <service_code>SVC-DELIVERY-ENV</service_code>
// 		// </logistics_service>
// 		// </logistics_service_list>
// 		// <product_type>STANDARD_EXPRESS</product_type>
// 		// <real_user_id>123232</real_user_id>
// 		// <volume>123</volume>
// 		// <package_id>E12321321-1234567</package_id>
// 		// </trade_order_info>
// 		// <waybill_code>hello world</waybill_code>
// 		// <package_center_code>123321</package_center_code>
// 		// <package_center_name>杭州余杭</package_center_name>
// 		// <print_config>SDFASFAFSAFSADF</print_config>
// 		// <shipping_branch_code>123132</shipping_branch_code>
// 		// <consignee_branch_name>余杭一部</consignee_branch_name>
// 		// <shipping_branch_name>西湖二部</shipping_branch_name>
// 		// <consignee_branch_code>123132</consignee_branch_code>
// 		// </waybill_apply_new_info>
// 		// </waybill_apply_new_cols>
// 		// </wlb_waybill_i_get_response></xml>";
		
// 		// print_r($resp);
		
// 		// $postObj = $this->xmlToArray($resp);
// 		// var_dump($postObj);
// 		// var_dump($resp["wlb_waybill_i_get_response"]["waybill_apply_new_cols"]["waybill_apply_new_info"]["short_address"]);
// 		print_r ( $resp->request_id );
// 		print_r ( $resp->waybill_apply_new_cols->waybill_apply_new_info->waybill_code );
// 		echo json_encode ();
	}
	
	// 淘宝的一些配套信息
	public function taobaoinfo() {
		vendor ( "taobao.TopSdk" );
		date_default_timezone_set ( 'Asia/Shanghai' );
		
		$appkey = $this->appkey;
		$secret = $this->secret;
		$sessionKey = $this->sessionKey;
		
		$c = new \TopClient ();
		$c->appkey = $appkey;
		$c->secretKey = $secret;
		
		// 获取物流产品类型
		$req = new \WlbWaybillIProductRequest ();
		$waybill_product_type_request = new \WaybillProductTypeRequest ();
		$waybill_product_type_request->cp_code = "YUNDA";
		$req->setWaybillProductTypeRequest ( json_encode ( $waybill_product_type_request ) );
		$resp = $c->execute ( $req, $sessionKey );
		
		var_dump ( $resp );
	}
	
	public function auth(){
		$redirect_uri = urlencode("http://yin.molixiangce.com/Index/Dayin/token");
		$url = "https://oauth.taobao.com/authorize?response_type=code&client_id=".$this->appkey."&redirect_uri=".$redirect_uri;
		header("location:".$url);
	}
	
	public function token() {
		$code = I("get.code","");
		if(!$code){
			die("没有code参数");
		}
		
		$url = 'https://oauth.taobao.com/token';
		$postfields = array (
				'grant_type' => 'authorization_code',
				'client_id' => $this->appkey,
				'client_secret' => $this->secret,
				'code' => $code,
				'redirect_uri' => 'http://yin.molixiangce.com/Index/Dayin/showcode' 
		);
		
		$rs = $this->postTaobaoData($url, $postfields);
// 		$rs = '{"taobao_user_nick":"%E6%98%9F%E7%BD%97%E7%BD%91%E7%BB%9C%E7%A7%91%E6%8A%80","re_expires_in":86400,"expires_in":86400,"expire_time":1450842739563,"r1_expires_in":86400,"w2_valid":1450842739563,"w2_expires_in":86400,"taobao_user_id":"1835837568","w1_expires_in":86400,"r1_valid":1450842739563,"r2_valid":1450842739563,"w1_valid":1450842739563,"r2_expires_in":86400,"token_type":"Bearer","refresh_token":"6200625b550bf36746034cbb058c0c2f0ZZ3b8db32984751835837568","refresh_token_valid_time":1450842739563,"access_token":"6201325f95baf715e899fc9cae52d2848ZZf44bd2b985df1835837568"}';
		print_r($rs);
		$this->saveTokenInfo($rs);
	}
	
	public function refreshToken() {
		$url = "https://oauth.taobao.com/token";
		$data = array (
				'grant_type' => "refresh_token",
				'client_id' => $this->appkey,
				'client_secret' => $this->secret,
				"refresh_token"=> $this->refresh_token
		);
		$rs = $this->postTaobaoData($url, $data);
		var_dump($rs);
	}
	
	private function postTaobaoData($url,$postfields){
		$post_data = '';
		foreach ( $postfields as $key => $value ) {
			$post_data .= "$key=" . urlencode ( $value ) . "&";
		}
		
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		
		// 指定post数据
		curl_setopt ( $ch, CURLOPT_POST, true );
		
		// 添加变量
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, substr ( $post_data, 0, - 1 ) );
		$output = curl_exec ( $ch );
		curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
		curl_close ( $ch );
		return $output;
	}
	
	private function saveTokenInfo($rs){
		$rs = json_decode($rs);
		if($rs && $rs->access_token && $rs->taobao_user_id == $this->sellid){	//只有星罗的号才存进去
			$tokenData = array("access_token"=>$rs->access_token,
					"refresh_token"=>$rs->refresh_token,
					"date"=>date("Y-m-d H:i:s",time()));
			D("Index/SysInfo")->saveAccessToken($tokenData);
		}
	}
	
	public function noPayOrderNotify(){
		//删除一天以上的表数据
 		$date=date("Y-m-d H:i:s",strtotime("-1 day"));
		$del_ret = M()->table("nopay_push")->where("createTime <'".$date."'")->delete();
		
		//当前时间 
 	/*	$timenow=date('Y-m-d H:i:s',time()); */
		//一小时内 
		$timed = time() - 3600;
 		$timeb=date('Y-m-d H:i:s',$timed);
		//查找两个小时内为支付的订单/* and '".$timenow."' */
		$noPayOrderList = M()->table("nopay_push")->where("createTime < '".$timeb."' ")->select(); 
		//推送消息
		if($this->no_pay_push){
			if($noPayOrderList){
				Vendor('JPush.JPush');
				try {
					foreach ($noPayOrderList as $nopaypush){
						//根据user_id查找出用户设备号
						$jpush_token = M()->table("jpush_token")->where("userId= '".$nopaypush['user_id']."'")->select();
						$cid = M()->table("order_info")->where("orderno= '".$nopaypush['orderno']."'")->getField('cid');
			
			
						foreach ($jpush_token as $value){
							// 推送 JPush 消息
							$data = array("type"=>"6","orderId"=>$cid,"isPay"=>false);
							$client = new \JPush('0c4f8e0a723bac74999de4f2', 'a670146d863e77440328cca3');
							$client->push()
							->setPlatform('ios', 'android')
							->addAndroidNotification('订单未支付', "您的订单号为 ".$nopaypush['orderno']." 未支付 ", 1, $data)
							->addIosNotification("订单未支付", 'iOS sound', '+1', true, 'iOS category', $data)
							->addRegistrationId($value['token'])
							->send();
							//删除数据
							M()->table("nopay_push")->where("orderno=".$nopaypush['orderno'])->delete();
						};
							
			
					}
						
				} catch(Exception $e) {
						
				}
			}
		}
	}

	public function kdniaoPush(){
	
		vendor("kdniao.function");
		if(!empty($_POST["RequestData"])){
			//接收推送信息
			$data = $_POST["RequestData"];
			//解析JSON内容
			$result = json_decode($data, true);
			//推送信息
			foreach($result["Data"] as $expressInfo){
				$EBusinessID = $expressInfo["EBusinessID"];
				$LogisticCode = $expressInfo["LogisticCode"];
				$State = $expressInfo["State"];
				$Traces = json_encode($expressInfo["Traces"]);
				$info['EBusinessID'] = $EBusinessID;
				$info['ShipperCode'] = 'YD';
				$info['Success'] = $expressInfo["Success"];
				$info['LogisticCode'] = $LogisticCode;
				$info['State'] = $State;
				$info['Traces']=$Traces;
				$isPaiSong = false;
				//
				//"EBusinessID": "1256059", "ShipperCode": "YD", "Success": true, "LogisticCode": "3909171326194", "State": "3", "Traces":
				D("Index/TraceInfo")->saveTraceInfo($info);
				$paiSong = '\u6d3e\u9001';
				if(strpos($Traces,$paiSong) === false){     //使用绝对等于
					$isPaiSong = false;
				}else{
				    $isPaiSong = true;
				}
		
 				//根据物流单号  查找到对应的token
 				$orderno = M()->table("package")->where("mailno=".$LogisticCode)->getField('orderno');
				$uid = M()->table("order_info")->where("orderno=".$orderno)->getField('uid');
				$cid = M()->table("order_info")->where("orderno=".$orderno)->getField('cid');
				$token = M()->table("jpush_token")->where("userId=".$uid)->getField('token');
				if($token){
					Vendor('JPush.JPush');
					// 初始化  推送到我的手机
					$j_data = array("type"=>"5", "orderId"=>$cid, "courierId"=>"$LogisticCode","orderno"=>$orderno);
					$online = (bool)$online;
					$client = new \JPush('0c4f8e0a723bac74999de4f2', 'a670146d863e77440328cca3');
					if($State == "3"){
						$result = $client->push()
						->setPlatform('ios', 'android')
						->addAndroidNotification('订单已签收', $orderno.'订单已签收，感谢您的购买', 1, $j_data)
						->addIosNotification("订单已签收", 'iOS sound', '+1', true, 'iOS category', $j_data)
						->addRegistrationId($token)		//'160a3797c804b8798a5'
						->setOptions(null,null,null,$online)
						->send();
					}else if($State == "2"){
						//2-在途中,3-签收,4-问题件
						if($isPaiSong){
							$result = $client->push()
							->setPlatform('ios', 'android')
							->addAndroidNotification('派件中', '您的订单'.$orderno.'正在派件中，请注意查收！', 1, $j_data)
							->addIosNotification("派件中", 'iOS sound', '+1', true, 'iOS category', $j_data)
							->addRegistrationId($token)		//'160a3797c804b8798a5'
							->setOptions(null,null,null,$online)
							->send();
						}
					}else if($State == "4"){
						//您的订单xxxx收货信息有误无法送达，请及时联系客服。
						$result = $client->push()
						->setPlatform('ios', 'android')
						->addAndroidNotification('问题件', '您的订单'.$orderno.'收货信息有误无法送达，请及时联系客服', 1, $j_data)
						->addIosNotification("问题件", 'iOS sound', '+1', true, 'iOS category', $j_data)
						->addRegistrationId($token)		//'160a3797c804b8798a5'
						->setOptions(null,null,null,$online)
						->send();
					}		
				}	

			}
			//返回成功结果
			$returnContent = '{"EBusinessID": '.EBusinessID.'," UpdateTime": "'.date('Y-m-d h:i:s',time()).'"," Success": true," Reason":""}';
			echo $returnContent;
		
			//日志记录
			//file_put_contents('kdniao_push.log', "----------------------------".date('Y-m-d h:i:s',time())."----------------------------"."\r\n接收内容: ".$_POST["RequestData"]."\r\n返回结果: ".$_POST["RequestData"]."\r\n\r\n", FILE_APPEND);
		}
		else{
			//返回失败结果
			$returnContent = '{"EBusinessID": '.EBusinessID.'," UpdateTime": "'.date('Y-m-d h:i:s',time()).'"," Success": false," Reason":"缺少RequestData参数"}';
			echo $returnContent;
		
			//记录日志
			//file_put_contents('kdniao_push.log', "----------------------------".date('Y-m-d h:i:s',time())."----------------------------"."\r\n接收内容: None\r\n返回结果: ".$_POST["RequestData"]."\r\n\r\n", FILE_APPEND);
		}
	}


	
	/*
	 * 快递鸟订阅测试
	 */
	public function kdniaoDy($mailno){
		vendor("kdniao.function");
		$requestData="{'Code': 'YD','Item': [{'No': '".$mailno."','Bk': ''}]}";
		$datas = array(
				'EBusinessID' => EBusinessID,
				'RequestType' => '1005',
				'RequestData' => urlencode($requestData) ,
				'DataType' => '2',
		);
		$datas['DataSign'] = encrypt($requestData, AppKey);
		$result=sendPost(ReqURL, $datas);
		echo $result;
		exit;
	}
	/**
	 * 本地模拟快递鸟推送
	 */
	function postTest(){
		
		vendor("kdniao.function");
		$requestData='{
					    "EBusinessID":"1256059",
					    "Count":"9",
					    "PushTime":"2016/12/21 14:23:19",
					    "Data":[
					        {
					            "EBusinessID":"1256059",
					            "OrderCode":"",
					            "ShipperCode":"YD",
					            "LogisticCode":"3909171490081",
					            "Success":true,
					            "Reason":"",
					            "State":"2",
					            "Traces":[
					                {
					                    "AcceptTime":"2016-07-19 22:46:27",
					                    "AcceptStation":"在福建厦门岛外公司进行到件扫描",
					                    "Remark":""
					                },
					                {
					                    "AcceptTime":"2016-07-19 23:36:25",
					                    "AcceptStation":"在福建厦门岛外公司进行下级地点扫描，将发往：广东东莞网点包",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-19 23:52:07",
					                    "AcceptStation":"在福建厦门岛外公司进行到件扫描",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-19 23:52:46",
					                    "AcceptStation":"在福建厦门岛外公司进行发出扫描，将发往：福建晋江分拨中心",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-20 01:32:40",
					                    "AcceptStation":"在分拨中心福建晋江分拨中心进行称重扫描",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-20 01:33:25",
					                    "AcceptStation":"在福建晋江分拨中心进行装车扫描，即将发往：广东东莞分拨中心",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-20 15:10:27",
					                    "AcceptStation":"在分拨中心广东东莞分拨中心进行卸车扫描",
					                    "Remark":""
					                },
					                {
					                    "AcceptTime":"2016-07-20 15:33:32",
					                    "AcceptStation":"从广东东莞分拨中心发出，本次转运目的地：广东东莞塘厦公司",
					                    "Remark":""
					                },
					                {
					                    "AcceptTime":"2016-07-21 08:39:48",
					                    "AcceptStation":"到达目的地网点广东东莞塘厦公司，快件将很快进行派送",
					                    "Remark":""
					                },
					                {
					                    "AcceptTime":"2016-07-21 08:41:22",
					                    "AcceptStation":"到达目的地网点广东东莞塘厦公司，快件将很快进行派送",
					                    "Remark":""
					                }
					            ],
					            "CallBack":""
					        },
					        {
					            "EBusinessID":"1256059",
					            "OrderCode":"",
					            "ShipperCode":"EMS",
					            "LogisticCode":"3909171490921",
					            "Success":true,
					            "Reason":"",
					            "State":"3",
					            "Traces":[
					                {
					                    "AcceptTime":"2016-07-19 22:38:37",
					                    "AcceptStation":"在福建厦门岛外公司进行到件扫描",
					                    "Remark":""
					                },
					                {
					                    "AcceptTime":"2016-07-19 23:35:55",
					                    "AcceptStation":"在福建厦门岛外公司进行下级地点扫描，将发往：浙江绍兴分拨中心",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-19 23:56:04",
					                    "AcceptStation":"在福建厦门岛外公司进行到件扫描",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-19 23:56:11",
					                    "AcceptStation":"在福建厦门岛外公司进行装车扫描，即将发往：浙江杭州分拨中心",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-20 19:48:24",
					                    "AcceptStation":"在分拨中心浙江杭州分拨中心进行卸车扫描",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-20 19:48:24",
					                    "AcceptStation":"在浙江杭州分拨中心进行装车扫描，即将发往：浙江绍兴分拨中心",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-21 04:09:31",
					                    "AcceptStation":"在分拨中心浙江绍兴分拨中心进行卸车扫描",
					                    "Remark":""
					                },
									{
					                    "AcceptTime":"2016-07-21 04:19:21",
					                    "AcceptStation":"从浙江绍兴分拨中心发出，本次转运目的地：浙江绍兴县钱清公司",
					                    "Remark":""
					                }
				
					            ],
					            "CallBack":""
					        }
					        
					       
					    ]
					}';
		$datas = array(
				'EBusinessID' => EBusinessID,
				'RequestType' => '1005',
				'RequestData' => urlencode($requestData) ,
				'DataType' => '2',
		);
		$datas['DataSign'] = encrypt($requestData, AppKey);
		
		$result=sendPost("http://88yin.ygj.com.cn/Index/Dayin/kdniaoPush", $datas);
		echo $result;
		exit;
	}
}
