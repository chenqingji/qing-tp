<?php
namespace Index\Controller;
use Index\Model\WeixinModel;
use Index\Model\CalculateFeeModel;
//use Index\Model\CardDataModel;
use Index\Model\PrintItemModel as CardDataModel;
use Index\Model\CouponModel;


class WxpayController extends BaseController {
    public $keyMap = array(
        'moliApp' => "yuydsoij763njhjdskj8439jsdhjAJHJ",
        'moliWeb' => "dhjds76437689jk009uyureiui874376"
    );

    public function __construct() {
        parent::__construct ();
    }

    public function test($wxOrderId='www-1',$notifyUrl='1', $fee='1' ,$successUrl='http://baidu.com', $errorUrl='http://baidu.com') {
        $query = http_build_query(array(
            "wxOrderId"=>$wxOrderId,
            "fee"=>$fee,
            "notifyUrl"=>$notifyUrl,
            "successUrl"=>$successUrl,
            "errorUrl"=>$errorUrl));
        $t = urlencode(authcode($query,'ENCODE'));
        header("location: http://".$_SERVER['HTTP_HOST']."/Index/Wxpay/payInterface?t=$t");
    }

    public function redirectPay($wxOrderId, $notifyUrl, $fee, $successUrl, $errorUrl) {
        $query = http_build_query(array(
            "wxOpenId" => I("get.openid"),
            "wxOrderId"=>$wxOrderId,
            "fee"=>$fee,
            "notifyUrl"=>$notifyUrl,
            "successUrl"=>$successUrl,
            "errorUrl"=>$errorUrl));
        $t = urlencode(authcode($query,'ENCODE'));
        header("location: http://".$_SERVER['HTTP_HOST']."/Index/Wxpay/payInterface?t=$t");
    }

    public function payInterface($t) {
        parse_str(authcode($t), $params);

        foreach(array("wxOrderId","fee","notifyUrl") as $keyName) {
            if(empty($params[$keyName])) {
                die("the $keyName arg is empty!");
            }
        }

        if(!isset($params['wxOpenId'])) {
            // 重定向
            $backUrl = 'http://'.$_SERVER['HTTP_HOST']."/Index/Wxpay/redirectPay?".http_build_query($params);
            cookie('backUrl', $backUrl);
            $wxM = new WeixinModel();
            $appId = $wxM->getAppid();
            header("location: https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appId&redirect_uri=http%3A%2F%2Fyin.molixiangce.com%2FIndex%2FCtrl%2FoauthOpenId&response_type=code&scope=snsapi_base&state=".'http://'.$_SERVER['HTTP_HOST']."#wechat_redirect");
        }

        //使用jsapi接口
        vendor("wxpay.WxPayPubHelper");
        $jsApi = new \JsApi_pub();
        //使用统一支付接口
        $unifiedOrder = new \UnifiedOrder_pub();
        //设置统一支付接口参数, 设置必填参数
        $unifiedOrder->setParameter("openid",$params['wxOpenId']);//用户openid
        $unifiedOrder->setParameter("body","魔力快印-".$params['wxOrderId']);//商品描述
        $unifiedOrder->setParameter("out_trade_no",$params['wxOrderId']);//商户订单号
        $unifiedOrder->setParameter("total_fee",$params['fee']);//总金额
        $unifiedOrder->setParameter("notify_url",$params['notifyUrl']);//通知地址
        $unifiedOrder->setParameter("trade_type","JSAPI");    //交易类型
        $unifiedOrder->setParameter("device_info","WEB");//设备号
        $prepay_id = $unifiedOrder->getPrepayId();

        //=========步骤3：使用jsapi调起支付============
        $jsApi->setPrepayId($prepay_id);
        $jsApiParameters = $jsApi->getParameters();

        $this->assign("jsApiParameters",$jsApiParameters);

        $this->assign("successUrl",$params['successUrl']);
        $this->assign("errorUrl",$params['errorUrl']);
        $this->display("Index/payInterface");
    }
    public function pay($cid){
        $orderData = (new CardDataModel())->getCard($cid);
        if(count($orderData) > 0) {
            $pics = json_decode($orderData['pics']);
            foreach($pics as $val) {
                $val = (array)$val;
                if($val["type"] == "wxLocal") {
                    $this->assign("resyuming",RES_YUMING);
                    $this->assign("url","/Index/Index/order/cid/$cid");
                    $this->assign("text","您的订单照片未全部上传成功");
                    $this->assign("btnText","返回订单");
                    $this->display("Index/overdue");
                    die;
                }
            }

            $uid = $orderData['uid'];
            $userInfo = $this->getUserInfo($uid);
            $openid = $userInfo['webOpenID'];

            if($orderData['status'] == $this->orderStatus["paid"]["value"]) {
                header('Location: '."/Index/Index/orderDetail/cid/".$cid);
                die;
            }

            // 获取打印图片张数
            $orderData['picCount'] = count($pics);
            //使用jsapi接口
            vendor("wxpay.WxPayPubHelper");
            $jsApi = new \JsApi_pub();

            //=========步骤2：使用统一支付接口，获取prepay_id============
            $contactData = array(
                'name' => $orderData['name'],
                'phone' => $orderData['phone'],
                'province' => $orderData['province'],
                'city' => $orderData['city'],
                'area' => $orderData['area'],
                'street' => $orderData['street'],
            );

            if(empty($orderData["province"])) {
                // 若订单无地址,冲地址簿去取地址
                $contactData = D("Index/Address")->getAddressData($orderData["aid"],$orderData['uid']);
            }

            $total_fee= round($orderData['price']*100);
            //$total_fee= round(1);
            $body="魔力快印-".$orderData['orderno'];

            //回调返回函数
            $notifyUrl = 'http://'.$_SERVER['HTTP_HOST']."/Index/Wxpay/payCallBack";

            //使用统一支付接口
            $unifiedOrder = new \UnifiedOrder_pub();

            //设置统一支付接口参数
            //设置必填参数
            //noncestr已填,商户无需重复填写
            //sign已填,商户无需重复填写
            $unifiedOrder->setParameter("openid","$openid");//用户openid
            $unifiedOrder->setParameter("body",$body);//商品描述
            //自定义订单号，此处仅作举例
            $unifiedOrder->setParameter("out_trade_no",$orderData['orderno']."-$total_fee");//商户订单号
            $unifiedOrder->setParameter("total_fee","$total_fee");//总金额
            $unifiedOrder->setParameter("notify_url","$notifyUrl");//通知地址
            $unifiedOrder->setParameter("trade_type","JSAPI");    //交易类型
            $unifiedOrder->setParameter("device_info","WEB");//设备号
            $prepay_id = $unifiedOrder->getPrepayId();

            //=========步骤3：使用jsapi调起支付============
            $jsApi->setPrepayId($prepay_id);
            $jsApiParameters = $jsApi->getParameters();

            //地址
            $contactData["address"] = $contactData['province'];
            if($contactData['city']) {
                $contactData["address"] .= "-".$contactData['city'];
            }
            if($contactData['area']) {
                $contactData["address"] .= "-".$contactData['area'];
            }

            // 订单数据
            $successUrl = "/Index/Index/orderDetail/cid/".$cid;
            $this->assign("jsApiParameters",$jsApiParameters);
            $this->assign("contactData",$contactData);
            $this->assign("orderData",$orderData);
//            $this->assign("postExceptArea",implode("、",CalculateFeeModel::$postExceptArea));
            $this->assign("resyuming",RES_YUMING);
            $this->assign("successurl",$successUrl);
            $this->display("Index/wxpay");
        } else {
            echo "该订单不存在";
        }
    }

    public function payCallBack(){
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
            $out_trade_no = explode("-",$notify->getData()["out_trade_no"]) ; // 获取订单号
            $openId = $notify->getData()["openid"]; // 微信 open id

            //根据订单号获取订单信息
            $orderInfo = (new CardDataModel())->getCardByOrderNo($out_trade_no[0]);

            if($orderInfo){
                $cid = $orderInfo['cid'];
                $orderPrice = round($orderInfo['price']*100);

                $couponM = new CouponModel();
                $coupon = $couponM->getBindCoupon($orderInfo['uid'], $orderInfo['coupon_id'], $orderInfo['cid'], false);
                if($coupon) { // 获取价格
                    $orderPrice -= $coupon['ex_data']['reduce_cost'];
                }

                if($out_trade_no[1] && intval($out_trade_no[1]) != $orderPrice) {  // 记入错误日志
                	(new CardDataModel())->savePayError("payback",$xml);
                }
                
                (new CardDataModel())->saveCard($cid,array(
                    "status" => $this->orderStatus["paid"]["value"],
                    "paidTime" => date("Y-m-d H:i:s"),
                    "pay_type"=>'wx'
                ));

                if($coupon) {//设置优惠卷已使用.
                    if($coupon['ex_data']['backUrl']) {
                        vendor("curl.function");
                        $c = new \curl();
                        $c->get($coupon['ex_data']['backUrl']);
                    }
                    $couponM->setUsed($orderInfo['uid'], $orderInfo['coupon_id'], $orderInfo['cid']);
                }

                vendor("curl.function");
                $c = new \curl();
                $price = $notify->getData()["total_fee"] / 100; // 总金额
                $accessToken = (new WeixinModel())->getAccessToken();
                $picCount = count(json_decode($orderInfo['pics']));

                if($orderInfo['sys'] == 'zpk' || $orderInfo['sys'] == 'wxs'){
                    $picCount = $orderInfo['photo_number'];
                }

                $accessUrl = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$accessToken";

                $accessJson = $c->post($accessUrl,json_encode(array(
                    "touser" => $openId,
                    "template_id" => "vX4Z3HFPc-vO0Icw2cTZXxZ6Cr75aXGzhiu2-LlrHlU",
                    "url" =>  'http://'.$_SERVER['HTTP_HOST'].'/Index/Index/orderDetail/cid/'.$cid,
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
                (new CardDataModel())->savePayError("payback",$xml);
            }
        }
        $returnXml = $notify->returnXml();
        echo $returnXml;
    }

    public function moliAppPayCallBack()
    {
        $this->otherPayCallBack("moliApp");
    }

    public function moliWebPayCallBack()
    {
        //$this->otherPayCallBack("moliWeb");
        $this->payCallBack();
    }

    public function otherPayCallBack($type)
    {
        $xml = file_get_contents('php://input');

        vendor("wxpay.WxPayPubHelper");
        $notify = new \Notify_pub();
        $notify->saveData($xml);

        //验证签名，并回应微信。
        //对后台通知交互时，如果微信收到商户的应答不是成功或超时，微信认为通知失败，
        //微信会通过一定的策略（如30分钟共8次）定期重新发起通知，
        //尽可能提高通知的成功率，但微信不保证通知最终能成功。
        if($notify->checkSignEx($this->keyMap[$type]) == FALSE){
            $notify->setReturnParameter("return_code","FAIL");//返回状态码
            $notify->setReturnParameter("return_msg","签名失败");//返回信息
        }else{
            $notify->setReturnParameter("return_code","SUCCESS");//设置返回码
            //TODO 成功后的各类操作
            $out_trade_no = explode("-",$notify->getData()["out_trade_no"]) ; // 获取订单号
            
            //根据订单号获取订单信息
            $orderInfo = (new CardDataModel())->getCardByOrderNo($out_trade_no[0]);

            if($orderInfo){
                $cid = $orderInfo['cid'];
                $orderPrice = round($orderInfo['price']*100);

                $couponM = new CouponModel();
                $coupon = $couponM->getBindCoupon($orderInfo['uid'], $orderInfo['coupon_id'], $orderInfo['cid'], false);
                if($coupon) { // 获取价格
                    $orderPrice -= $coupon['ex_data']['reduce_cost'];
                }

                if($out_trade_no[1] && intval($out_trade_no[1]) != $orderPrice) { // 记入错误日志
                    (new CardDataModel())->savePayError("payback",$xml);
                }

                if($coupon) { //设置优惠卷已使用.
                    if($coupon['ex_data']['backUrl']) {
                        vendor("curl.function");
                        $c = new \curl();
                        $c->get($coupon['ex_data']['backUrl']);
                    }
                    $couponM->setUsed($orderInfo['uid'], $orderInfo['coupon_id'], $orderInfo['cid']);
                }

                (new CardDataModel())->saveCard($cid,array(
                    "status" => $this->orderStatus["paid"]["value"],
                    "paidTime" => date("Y-m-d H:i:s"),
                    "pay_type"=>'wx'
                ));
                $data['uid'] = $orderInfo['uid'];
                $data['paytype']='wx';
                D("Index/PayType")->savePayType($orderInfo['uid'],$data);
                //根据订单号删除nopay_push表数据
                D("Index/NoPayPush")->delNoPayPush($out_trade_no[0]);
                
            }else{
                (new CardDataModel())->savePayError("payback",$xml);
            }
        }
        $returnXml = $notify->returnXml();
        echo $returnXml;
    }

    
    /*
     * @gaoqifa
    * 获取微信用户openId
    */
    public function getWxOpenId(){
    	$cid  = I("get.cid",null);
    	$openid  = I("get.openid",null);
    	$back  = I("get.back",null);
    	$unionid = I("get.unionid",null);
    	if(!isset($openid) && !isset($back)){
    		$backUrl = 'http://'.$_SERVER['HTTP_HOST']."/Index/Wxpay/getWxOpenId?back=1&cid=".$cid."&unionid=".$unionid;
    		cookie('backUrl', $backUrl);
    		$wxM = new WeixinModel();
    		$appId = $wxM->getAppid();
    		header("location: https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appId&redirect_uri=http%3A%2F%2Fyin.molixiangce.com%2FIndex%2FCtrl%2FoauthOpenId&response_type=code&scope=snsapi_base&state=".'http://'.$_SERVER['HTTP_HOST']."#wechat_redirect");
    		die;
    	}
    	
    	if(isset($unionid)){
    		//保存openId到用户的数据库中
    		D("Index/User")->saveWebOpenId($unionid,$openid);
    	}
    	
    	//header("location:http://m.tuideli.com/Index/Index/goPrintMb?cid=".$cid."&openid=".$openid);   //线上的
    	header("location:http://99moli.ygj.com.cn/Index/Index/goPrintMb?cid=".$cid."&openid=".$openid);   //测试环境的
    	die;
    }
}