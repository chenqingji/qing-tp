<?php
namespace Index\Controller;
//use Index\Model\CardDataModel;
use Index\Model\CouponModel;
use Index\Model\PrintItemModel as CardDataModel;

class AlipayController extends BaseController {
    public function __construct() {
        parent::__construct ();
    }

    public function payCallBack()
    {
        vendor("Alipay.Notify");
        $alipay_config['partner'] = '2088111088609464';
        $alipay_config['seller_id'] = 'xingluo2013@gmail.com';
        $alipay_config['private_key_path'] = VENDOR_PATH.'pem/zf_private_key.pem';//HFwGaAOoxixsvlCpjLbeXaqjICA==';
        $alipay_config['ali_public_key_path'] = VENDOR_PATH.'pem/zf_public_key.pem';
        $alipay_config['sign_type'] = strtoupper('RSA');
        $alipay_config['input_charset']= strtolower('utf-8');
        $alipay_config['cacert']    = VENDOR_PATH.'pem/cacert.pem';
        $alipay_config['transport']    = 'http';

        //计算得出通知验证结果
        $alipayNotify = new \AlipayNotify($alipay_config);
        $verify_result = $alipayNotify->verifyNotify();

        if($verify_result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代

            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            //商户订单号
            $out_trade_no = explode("-",$_POST["out_trade_no"])[0];
            //支付宝交易号
            $trade_no = $_POST['trade_no'];
            //交易状态
            $trade_status = $_POST['trade_status'];

            $price = $_POST['price'];
            $orderInfo = (new CardDataModel())->getCardByOrderNo($out_trade_no);
            if($_POST['trade_status'] == 'TRADE_FINISHED' || $_POST['trade_status'] == 'TRADE_SUCCESS') {
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //如果有做过处理，不执行商户的业务程序
                //根据订单号获取订单信息
                if($orderInfo){
                    $cid = $orderInfo['cid'];
                    $uid = $orderInfo['uid'];
                    $orderNo = $orderInfo['orderno'];
                    $orderPrice = $orderInfo['price']* 100;

                    //设置优惠卷已使用.
                    $couponM = new CouponModel();
                    $coupon = $couponM->getBindCoupon($orderInfo['uid'], $orderInfo['coupon_id'], $orderInfo['cid'], false);
                    if($coupon) { // 设置价格
                        $orderPrice = $orderPrice  - $coupon['ex_data']['reduce_cost'];
                    }

//		    		if($price*100 === $orderPrice) {
                        (new CardDataModel())->saveCard($cid,array(
                            "status" => $this->orderStatus["paid"]["value"],
                            "paidTime" => date("Y-m-d H:i:s"),
                            "pay_type"=>'alipay'
                        ));
                        $data['uid'] = $uid;
                        $data['paytype']='alipay';

                        if($coupon) {
                            if($coupon['ex_data']['backUrl']) {
                                vendor("curl.function");
                                $c = new \curl();
                                $c->get($coupon['ex_data']['backUrl']);
                            }
                            $couponM->setUsed($orderInfo['uid'], $orderInfo['coupon_id'], $orderInfo['cid']);
                        }
                        D("Index/PayType")->savePayType($uid,$data);
                        //根据订单号删除nopay_push表数据
                        D("Index/NoPayPush")->delNoPayPush($orderNo);
//		    		}else{
//		    			D("Index/CardData")->savePayError("payback","alipay error".$orderNo);
//                        D("Index/CardData")->savePayError("payback",json_encode($_POST));
//                        echo "fail";
//                        exit();
//		    		}
                }
            }
            echo "success";		//请不要修改或删除
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        }
        else {

            //sleep(1);
            //验证失败
            $oid = !empty($_POST['out_trade_no'])? $_POST['out_trade_no'] : 'null';
            D("Index/CardData")->savePayError("payback","verify error".$oid);
            echo "fail";
            //调试用，写文本函数记录程序运行情况是否正常
            //logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
        }
    }
}