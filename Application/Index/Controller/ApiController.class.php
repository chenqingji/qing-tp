<?php
namespace Index\Controller;

use Index\Model\CalculateFeeModel;
use Index\Model\CouponModel;
use Index\Model\PrintItemModel;
use Index\Model\WeixinModel;
//use Index\Model\CardDataModel;
use Index\Model\PrintItemModel as CardDataModel;

//TODO 常用APP版API

class ApiController extends BaseController {
    public $pageLimit = 10;
    public $pageCount = 10;

    // 无需检验认证的请求
    private $noCheckActions = array(
        'register',
        'suggest',
        'getOrderListTaobao',
    	'getOrderDetailNew',
    	'getTraceInfo',
        'couponUseInfo',
        'getFinalPrice',
        'getExpressByArea',
    );

    public function test($orderNo = '14486667621701') {
//        // 查询该订单信息
//        $orderInfo = D ( "Index/CardData" )->getCardByOrderNo ( $orderNo );
//        if (!$orderInfo || intval($orderInfo['status']) === 17) {
//            $this->ajaxReturn ( array (
//                "ret" => - 1,
//                "msg" => "打印失败，查无该订单信息"
//            ) );
//        }
    }

    protected function getToken()
    {
        $token = $_GET['token'];
        if(!$token) {
            $token = $_POST['token'];
        }
        return $token;
    }


    public function __construct(){
        parent::__construct();
        $token = $this->getToken();

//     	$token = '25-'.md5('25-moli-jdui-25');
//     	echo $token;die;

        $needToCheck = true;
        foreach ($this->noCheckActions as $action) {
            if(!strnatcasecmp($action, ACTION_NAME)) {
                $needToCheck = false;
            }
        }
        if($needToCheck) {
            $this->checkIdentify($token);
        }
    }

    protected function toFailed($reason) {
        $this->ajaxReturn(array(
            'status'=>'error',
            'reason'=>$reason
        ));
        exit;
    }

    protected function checkIdentify($token) {
        $uid = $this->decodeUid($token);
        if($uid == null) {
            $this->toFailed('invalid token');
            die();
        }else{
            $this->user_id = $uid;
        }
    }

    public function registerJPush($id,$type) {
        $m = M();
        $uid = $this->user_id;
        $m->query("INSERT INTO jpush_token (userId,token,type) VALUES ($uid,'$id','$type') ON DUPLICATE KEY UPDATE token='$id';");
        $this->ajaxReturn(array('status'=>'ok'));
    }

    // 注册
    public function register()
    {
        $refresh = I('get.refresh', false);
        $unionId = I('get.unionId');

        if (empty($unionId)) {
            $this->toFailed('invalid unionId: ' . $unionId);
            return;
        }
        // 检查是否注册过
        $res = $this->checkReg($unionId);

        if ($res) {
            if($refresh) {
                // 注册过，刷新值
                $info['uid'] = $res['uid'];
                $info['nickname'] = I('get.nickname');
                $info['avatar'] = I('get.avatar');

                $this->refreshInfo($info);
            }
            $webOpenId = $res['webOpenID'];
        } else {
            // 没注册过，写入数据库，获取uid
            $info = array(
                'avatar' => I('get.avatar'),
                'nickname' => I('get.nickname'),
                'unionID' => $unionId
            );
            $res['uid'] = $this->insertInfo($info);
            $webOpenId = "";
        }

        $ret = array(
            'state' => 'ok',
            'uid' => $res['uid'],
        	'webOpenID' => $webOpenId,
        );

        $this->ajaxReturn($ret);
    }

    // 保存订单
    public function saveOrder()
    {
        ignore_user_abort(true);
        set_time_limit(0);

        $postData = array(
            'name' =>  I('post.name', null),
            'phone' =>  I('post.phone', null),
            'province' =>  I('post.province', null),
            'city' =>  I('post.city', null),
            'area' =>  I('post.area', null),
            'street' =>  I('post.street', null),
            'pics' =>  I('post.pics', null),
            'sys' =>  I('post.sys', null),
        	'print_size' => I('post.print_size',6),
            'cid' => I('post.cid', null)
        );

        $imgType = I('post.imgType', "local");

        if(is_null($postData['name'])) {
            $this->toFailed("invalid name");
        } else if(is_null($postData['phone'])) {
            $this->toFailed("invalid phone");
        } else if(is_null($postData['province'])) {
            $this->toFailed("invalid province");
        } else if(is_null($postData['city'])) {
            $this->toFailed("invalid city");
        } else if(is_null($postData['area'])) {
            $this->toFailed("invalid area");
        } else if(is_null($postData['street'])) {
            $this->toFailed("invalid street");
        } else if(is_null($postData['pics'])) {
            $this->toFailed("invalid pics");
        } else if(is_null($postData['sys'])) {
            $this->toFailed("invalid sys");
        } else {
            $postData['pics'] = json_decode(urldecode(html_entity_decode($postData['pics'])), true);
            $photoNum = count($postData['pics']);
            $picsData = array();
            foreach($postData['pics'] as $value) {
                $iType = $imgType;
                if (preg_match("/media_id=(.+)$/iuU", $value, $matches)) {
                    $value = $matches[1];
                    $iType = "wx";
                }
                $picsData[] = array(
                    "type" => $iType,
                    "url" => $value
                );
            }
            $postData['pics'] = json_encode($picsData);
            $postData['photo_number'] = $photoNum;

            if(I('post.moli', false)) {
                list($postData['photo_fee'],
                    $postData['postage'],
                    $postData['price']) = CalculateFeeModel::getFeeMoli($photoNum, $postData['province'], $postData['city'], $postData['area'], $postData['street']);
            } else {
                list($postData['photo_fee'],
                    $postData['postage'],
                    $postData['price']) = CalculateFeeModel::getFee($photoNum, $postData['province'], $postData['city'], $postData['area'], $postData['street']);
            }

            $date = date("Y-m-d H:i:s");
            $createDate = explode(' ',$date)[0];

            if(is_null($postData['cid'])) {
                $postData['orderno'] = time().rand(1000, 9999);
                $orderNumber = $postData['orderno'];

                // 新建订单
                $postData['status'] = 0;
                $postData['uid'] = $this->user_id;
                $postData['create_time'] = $date;
                $postData['cid'] = (new CardDataModel())->newCardData($postData);
                
                $data['user_id']=$this->user_id;
                $data['orderno']=$postData['orderno'];
                $ret = D("Index/NoPayPush")->addNoPay($data);
            } else {
            	if(empty($postData['name'])) {
            		unset($postData['name']);
            	} else if(empty($postData['phone'])) {
            		unset($postData['phone']);
            	} else if(empty($postData['province'])) {
            		unset($postData['province']);
            	} else if(empty($postData['city'])) {
            		unset($postData['city']);
            	} else if(empty($postData['area'])) {
            		unset($postData['name']);
            	} else if(empty($postData['street'])) {
            		unset($postData['street']);
            	} else if(empty($postData['pics'])) {
            		unset($postData['pics']);
            	} else if(empty($postData['sys'])) {
            		unset($postData['sys']);
            	}
            	
                if($photoNum < $this->minUploadPicCount) {
                    $this->ajaxReturn(array('status'=>"error",'reason'=>'upload min count limit!'));
                    return;
                }
                $postData['status'] = 1;

                // 保存订单
                $m = (new CardDataModel());
                $cardData = $m->getCard($postData['cid']);
                if($cardData['status'] == $this->orderStatus["paid"]["value"]) {
                    $this->toFailed("had paid");
                    die;
                }

                if($cardData['coupon_id']) { // 解绑优惠卷
                    $couponM = new CouponModel();
                    $couponM->setUnlock($this->user_id, $cardData['cid'], $cardData['coupon_id']);
                    $postData['coupon_id'] = 0;
                }

                $orderNumber = $cardData['orderno'];
                $createDate = explode(' ',$cardData['create_time'])[0];
                $m->saveCardData($this->user_id, $postData['cid'], $postData);
            }
            $this->ajaxReturn(array(
                'status'=>"ok",
                'data'=>array(
                    'id'=>$postData['cid'],
                    'orderNumber' => $orderNumber,
                    'picCount' => $photoNum."",
                    'photoFee' => $postData['photo_fee']."",
                    'postage' => $postData['postage']."",
                    'price' =>  $postData['price']."",
                	'printSize'=>$postData['print_size'],
                    'subFolderName' => $createDate."-".$postData['cid']
                )
            ));
        }
    }

    public function saveOrderImg() {
        ignore_user_abort(true);
        set_time_limit(0);

        $postData = array(
            'name' =>  I('post.name', null),
            'phone' =>  I('post.phone', null),
            'province' =>  I('post.province', null),
            'city' =>  I('post.city', null),
            'area' =>  I('post.area', null),
            'street' =>  I('post.street', null),
            'pics' =>  I('post.pics', null),
            'sys' =>  I('post.sys', null),
            'print_size' => I('post.print_size',6),
            'cid' => I('post.cid', null)
        );

        $imgType = I('post.imgType', "local");

        foreach(array( 'pics', 'sys') as $v) {
            if(is_null($postData[$v])) {
                $this->toFailed("invalid $v");
                return;
            }
        }

        $postData['pics'] = json_decode(urldecode(html_entity_decode($postData['pics'])), true);
        $photoNum = count($postData['pics']);
        $picsData = array();
        foreach($postData['pics'] as $value) {
            $iType = $imgType;
            if(preg_match("/media_id=(.+)$/iuU", $value, $matches)) {
                $value = $matches[1];
                $iType = "wx";
            }
            $picsData[] = array(
                "type" => $iType,
                "url" => $value
            );
        }
        $postData['pics'] = json_encode($picsData);
        $postData['photo_number'] = $photoNum;

        if(I('post.moli', false)) {
            list($postData['photo_fee'],
                $postData['postage'],
                $postData['price']) = CalculateFeeModel::getFeeMoli($photoNum, $postData['province'], $postData['city'], $postData['area'], $postData['street']);
        } else {
            list($postData['photo_fee'],
                $postData['postage'],
                $postData['price']) = CalculateFeeModel::getFee($photoNum, $postData['province'], $postData['city'], $postData['area'], $postData['street']);
        }

        $date = date("Y-m-d H:i:s");
        $createDate = explode(' ',$date)[0];

        $ret = ['status' => 'ok', 'data' => []];

        if(is_null($postData['cid'])) {
            $postData['orderno'] = time().rand(1000, 9999);
            $orderNumber = $postData['orderno'];

            // 新建订单
            $postData['status'] = 0;
            $postData['uid'] = $this->user_id;
            $postData['create_time'] = $date;
            $postData['cid'] = (new CardDataModel())->newCardData($postData);

            $data['user_id']=$this->user_id;
            $data['orderno']=$postData['orderno'];
            D("Index/NoPayPush")->addNoPay($data);
        } else {
            foreach(array('name', 'phone', 'province', 'city', 'area', 'street', 'pics', 'sys') as $v) {
                if(empty($postData[$v])) {
                    unset($postData[$v]);
                }
            }

            if($photoNum < $this->minUploadPicCount) {
                $this->ajaxReturn(array('status'=>"error",'reason'=>'upload min count limit!'));
                return;
            }
            $postData['status'] = 1;

            // 保存订单
            $m = (new CardDataModel());
            $cardData = $m->getCard($postData['cid']);
            if($cardData['status'] == $this->orderStatus["paid"]["value"]) {
                $this->toFailed("had paid");
                die;
            }

            if($cardData['coupon_id']) { // 解绑优惠卷
                $couponM = new CouponModel();
                $couponM->setUnlock($this->user_id, $cardData['cid'], $cardData['coupon_id']);
                $postData['coupon_id'] = 0;
                $ret['data']['couponHint'] = '订单发生变化，优惠券已解绑';
            }

            $orderNumber = $cardData['orderno'];
            $createDate = explode(' ',$cardData['create_time'])[0];
            $m->saveCardData($this->user_id, $postData['cid'], $postData);
        }
        $ret['data']['id'] = $postData['cid'];
        $ret['data']['orderNumber'] = $orderNumber;
        $ret['data']['subFolderName'] = $createDate."-".$postData['cid'];

        $this->ajaxReturn($ret);
    }

    public function submitOrder() {
        ignore_user_abort(true);
        set_time_limit(0);

        $postData = array(
            'name' =>  I('post.name', null),
            'phone' =>  I('post.phone', null),
            'province' =>  I('post.province', null),
            'city' =>  I('post.city', null),
            'area' =>  I('post.area', null),
            'street' =>  I('post.street', null),
            'cid' => I('post.cid', null),
            'express_type' => I('post.expressId', null)
        );
        $couponId = I("post.couponId", null);

        foreach($postData as $k => $v) {
            if(empty($postData[$k])) {
                $this->toFailed("invalid $k");
                return;
            }
        }

        $m = (new CardDataModel());
        $cardData = $m->getCard($postData['cid']);
        if($cardData['status'] == $this->orderStatus["paid"]["value"]) {
            $this->toFailed("had paid");
            return;
        }
        if($cardData['photo_number'] < $this->minUploadPicCount) {
            $this->toFailed("upload min count limit!");
            return;
        }

        if($couponId != $cardData['coupon_id']) {
            $couponM = new CouponModel();
            if($couponId) {
                $coupon = $couponM->getCoupon($this->user_id, $couponId);
                if($coupon) {
                    if($coupon['type'] == CouponModel::$REDUCE_TYPE && $coupon['ex_data']['least_cost'] <= $cardData['price']) {
                        $this->toFailed("no meet least cost!");
                        return;
                    }

                    $postData['coupon_id'] = $couponId;
                    $lockTime = date("Y-m-d H:i:s",strtotime($cardData['create_time']) + 259200/* 60*60*24*3 */);
                    $couponM->setUnlock($this->user_id, $cardData['cid'],$cardData['coupon_id']);
                    $couponM->setLock($this->user_id, $couponId, $cardData['cid'], $lockTime);
                }else {
                    $this->toFailed("invalid couponId:$couponId");
                    return;
                }
            } else {
                $couponM->setUnlock($this->user_id, $cardData['cid'], $cardData['coupon_id']);
                $postData['coupon_id'] = 0;
            }
        }
        // 保存订单
        $postData['status'] = 1;
        $m->saveCardData($this->user_id, $postData['cid'], $postData);

        $this->ajaxReturn(array(
            'status'=>"ok",
            'data'=>array(
                'id'=>$postData['cid']
            )
        ));
    }

    public function saveGoods() {
        $oid = I('post.oid', 1);
        $goodList = html_entity_decode(urldecode(I('post.goodsList', null) ) );

        if(!$oid) {
            $this->toFailed('invaild order id');
        }
        $m = new CardDataModel();
        $m->resetGoods($oid);
        // 删除所有物品关系
//        $goodList = '{"goodsArray":[{"goodsId":56,"goodsNum":50}]}';

        if($goodList) {
            //更新物品关系
            $goodList = json_decode($goodList, true);
            if(is_null($goodList)) {
                $this->toFailed('invaild goodList');
            }
            if($goodList['goodsArray']) {
                foreach($goodList['goodsArray'] as $v) {
                    $m->addGoods($oid, $v['goodsId'], $v['goodsNum']);
                }
            }
        }
        $this->ajaxReturn(['status'=>'ok']);
    }

    public function getAppOrderDetail ($oid) {
        $orderData = (new CardDataModel())->appGetCard($oid, $this->user_id);
        if($orderData) {
            if(0 == $orderData['status'] || 1 == $orderData['status']){
                $token = $_GET['token'];
                if(!$token) {
                    $token = $_POST['token'];
                }
                $data = array(
                    'id'=> $oid.'',
                    'orderNumber' => $orderData['orderno']."",
                    'picCount' => $orderData['photo_number']."",
                    'des' => $orderData['photo_number']."张6寸照片",
                    "photoFee" => $orderData['photo_fee']."",
                    "postageFee" => $orderData['postage']."",
                    "primeFee" => $orderData['price']."",
                    "coupon" => array(
                        "id" => '0',
                        "des" => "无",
                        "des2" => "",
                    ),
                    "couponUrl" => 'http://'.$_SERVER['HTTP_HOST']."/Index/Api/canUseCouponList?token=$token",
                    "preferentialPrice" => "0",
                    "payPrice" => $orderData['price']."",
                    "couponData" => "",
                    'goodsList' => [
                        'goodsTotalPrice'=> $orderData['goodsPrice'],
                        'goodsArray' => []
                    ],
                    'isPostageFree' => (CalculateFeeModel::$express[$orderData['express_type']]['fee'] > 0 ? 0 : 1),
                    'expressId' => $orderData['express_type'],
                    'expressName' => CalculateFeeModel::$express[$orderData['express_type']]['name'],
                    //'payHint' => '',
                );

                if(in_array($data['sys'], array_merge(
                    PrintItemModel::$SYS_TYPE['magicAlbumApp'],
                    PrintItemModel::$SYS_TYPE['weChat']) ) )
                {
                    $data["chooseGoodsUrl"] = 'http://'.$_SERVER['HTTP_HOST']."/Index/Api/choseBuyGoods?token=$token&cid=$oid";
                }

                foreach($orderData['goods'] as $v) {
                    $data['goodsList']['goodsArray'][] = [
                        'goodsId' => $v['id'],
                        'goodsNum' => $v['count'],
                        'goodsPrice' => $v['preferential_price'] * $v['count'],
                        'goodsName' => $v['category_name'],
                    ];
                }

                $data['goodsList'] = json_encode($data['goodsList']);

                $m = new CouponModel();
                $couponList = $m->getCouponList($this->user_id, $orderData['cid']);
                $couponList = $m->dealCouponData($couponList);
                $couponId = null;
                if($orderData['coupon_id']) {
                    foreach($couponList as $coupon) {
                        if($orderData['coupon_id'] == $coupon['data']['id'] && $coupon['data']['ex_data']['least'] <= intval($orderData['price'] * 100)) {
                            $couponId = $data['coupon']['id'];
                            $data['coupon']['id'] = $orderData['coupon_id']."";
                            $data['coupon']['des'] = $coupon['subDes']['val'];
                            $data['preferentialPrice'] = ($coupon['data']['ex_data']['reduce'] / 100)."";
                            $data['payPrice'] = (round(($orderData['price'] * 100 -  $coupon['data']['ex_data']['reduce']) / 100, 2))."";
                        }
                    }
                    if(is_null($couponId)) { // 解绑优惠卷
                        $couponM = new CouponModel();
                        $couponM->setUnlock($this->user_id, $orderData['cid'], $orderData['coupon_id']);
                    }
                }

                if(is_null($couponId)) {
                    $idx = null; // 价格计算
                    foreach($couponList as $k => $coupon) {
                        if($coupon['data']['ex_data']['least'] <= intval($orderData['price'] * 100)) {
                            if($idx === null || (
                                $couponList[$idx]['data']['ex_data']['reduce'] < $coupon['data']['ex_data']['reduce']
                                && abs($orderData['price'] - $coupon['data']['ex_data']['reduce'] / 100) < abs($orderData['price'] - $couponList[$idx]['data']['ex_data']['reduce'] / 100)
                            )) {
                                $idx = $k;
                            }
                        }
                    }
                    if(!is_null($idx)) {
                        $coupon = $couponList[$idx];
                        $data['coupon']['id'] = $coupon['data']['id']."";
                        $data['coupon']['des'] = $coupon['subDes']['val'];
                        $data['coupon']['des2'] = $coupon['subDes']['des'];

                        $data['preferentialPrice'] = ($coupon['data']['ex_data']['reduce'] / 100)."";
                        $data['payPrice'] = round(($orderData['price'] * 100 -  $coupon['data']['ex_data']['reduce']) / 100, 2)."";
                    }
                }
                $data['couponData'] = json_encode($couponList, JSON_UNESCAPED_UNICODE);
                $this->ajaxReturn(array('status'=>"ok",'data'=>$data));
            } else {
                $this->toFailed("invalid oid");
            }

        } else {
            $this->toFailed("invalid oid");
        }
    }

    public function setPay($oid) {
        $m = (new CardDataModel());

        $cardData = $m->getCard($oid);
        if($cardData['status'] == $this->orderStatus["paid"]["value"]) {
            $this->ajaxReturn(array('status'=>"error",'reason'=>"had paid"));
            die;
        }
        $couponId = $cardData['coupon_id'];

        $couponM = new CouponModel();
        if($couponId) {
            $coupon = $couponM->getBindCoupon($this->user_id, $couponId, $cardData['cid'], false);
            if($coupon) {
                if($coupon['type'] == CouponModel::$REDUCE_TYPE) {
                    if($coupon['ex_data']['least_cost'] > $cardData["price"]) {
                        $this->ajaxReturn( array('status'=>"error",'reason'=>"no meet least cost") );
                    }
                    if(intval(($cardData["price"] - $coupon['ex_data']['reduce_cost'])) > 0) {
                        $this->ajaxReturn(array('status'=>"error",'reason'=>"need to pay the fee"));
                    }

                    if(!$couponM->setUsed($this->user_id, $couponId, $cardData['cid'])) {
                        $this->ajaxReturn(array('status'=>"error",'reason'=>"优惠卷已被使用"));
                    }

                    //设置订单为已支付
                    $m->saveCardData($this->user_id, $cardData['cid'], array(
                        'status' => $this->orderStatus["paid"]["value"],
                        'paidTime' => date("Y-m-d H:i:s")
                    ));

                    if($coupon['ex_data']['backUrl']) {
                        vendor("curl.function");
                        $c = new \curl();
                        $c->get($coupon['ex_data']['backUrl']);
                    }
                    $this->ajaxReturn(array('status'=>"ok") );
                } else if($coupon['type'] == CouponModel::$FREE_TYPE) {
                    if($coupon['ex_data']['free_pic_cnt'] >= $cardData['photo_number']) {
                        //设置优惠卷已使用.
                        if(!$couponM->setUsed($this->user_id, $couponId, $cardData['cid'])) {
                            $this->ajaxReturn(array('status'=>"error",'reason'=>"优惠卷已被使用"));
                        }

                        //设置订单为已支付
                        $m->saveCardData($this->user_id, $cardData['cid'], array(
                            'status' => $this->orderStatus["paid"]["value"],
                            'paidTime' => date("Y-m-d H:i:s")
                        ));

                        if($coupon['ex_data']['backUrl']) {
                            vendor("curl.function");
                            $c = new \curl();
                            $c->get($coupon['ex_data']['backUrl']);
                        }
                        $this->ajaxReturn(array('status'=>"ok") );
                    }
                } else {
                    $this->ajaxReturn(array('status'=>"error",'reason'=>"invalid coupon type"));
                }
            } else {
                $this->ajaxReturn(array('status'=>"error",'reason'=>"no coupon id"));
            }
        } else {
            $this->ajaxReturn(array('status'=>"error",'reason'=>"no coupon id"));
        }
    }

    public function deleteOrder($oid) {
        ignore_user_abort(true);
        set_time_limit(0);

        $m = (new CardDataModel());
        $cardData = $m->getCard($oid);
        if($cardData) {
            if($cardData['status'] != $this->orderStatus["paid"]["value"]) {// 未支付
                $couponId = $cardData['coupon_id'];
                $couponM = new CouponModel();
                if($couponId) {
                    $couponM->setUnlock($this->user_id, $cardData['cid'], $couponId);
                }
            } else {// 已经支付
                if($cardData['mailno']) {
                    vendor("kdniao.function");
                    $kuaidi = null;
                    $curlRet = getOrderTracesByJson('YD', $cardData['mailno']);
                    $kuaidi = json_decode ($curlRet);

                    if (!$kuaidi
                        || $kuaidi->Success == 0
                        || count($kuaidi->Traces) == 0
                        || $kuaidi->State != "3"){
                        $this->ajaxReturn(array('status'=>"error","reason"=>"交易尚未完成，不可删除") );
                    }
                } else {
                    $this->ajaxReturn(array('status'=>"error","reason"=>"交易尚未完成，不可删除") );
                }
            }
            $m->deleteOrder($this->user_id, $oid);
        }
        $this->ajaxReturn(array('status'=>"ok") );
    }

    public function cancelOrder($oid) {
        ignore_user_abort(true);
        set_time_limit(0);

        $m = (new CardDataModel());
        $cardData = $m->getCard($oid);
        if($cardData) {
            if($cardData['status'] != $this->orderStatus["paid"]["value"]) {
                $couponId = $cardData['coupon_id'];
                $couponM = new CouponModel();
                if($couponId) {
                    $couponM->setUnlock($this->user_id, $cardData['cid'], $couponId);
                }
                $m->userCancelOrder($this->user_id, $oid);
		        $this->ajaxReturn(array('status'=>"ok"));
            } else {
                $this->ajaxReturn(array('status'=>"error","reason"=>"已支付无法取消") );
            }
        }
        $this->ajaxReturn(array('status'=>"error","reason"=>"取消订单失败") );
    }

    public function getConsume() {
        $m = new CardDataModel();
        $this->ajaxReturn(array(
            'status'=>"ok",
            "consume"=>$m->getUserConsume($this->user_id)
        ));
    }

    public function getNewCoupon() {
        $m = new CouponModel();
        $count = $m->getUnReadCounponCount($this->user_id);
        if(empty($count)) {
            $count = 0;
        }
        $token = $this->getToken();
        $hasCoupon = $m->hasCoupon($this->user_id);
        $preUrl = 'http://'.$_SERVER['HTTP_HOST']."/Index/Api";

        $this->ajaxReturn(array('status'=>"ok",'data'=>array(
            'newCouponCount' => $count,
            'isHaveCoupon' => $hasCoupon,
            'newCouponUrl' => "$preUrl/unreadCoupon?token=$token",
            'couponUrl' => "$preUrl/couponList?token=$token",
        )));
    }

    public function hasCoupon() {
        $m = new CouponModel();
        $hasCoupon = $m->hasCoupon($this->user_id);
        $this->ajaxReturn(array('status'=>"ok",'data'=>array(
            'isHaveCoupon' => $hasCoupon
        )));
    }

    protected function deleteLocalPics(&$orderData) {
        if($orderData['pics']) {
            $pics = json_decode($orderData['pics'], true);
            $picsData = array();
            $needToSave = false;
            foreach ($pics as $k => $v) {
                if ($v['type'] == $this->uploadType["wxLocalType"]) {
                    $needToSave = true;
                    continue;
                } else {
                    $picsData[] = $v;
                }
            }
            if($needToSave) {
                $orderData['pics'] = json_encode($picsData);
                $orderData['photo_number'] = count($picsData);

                list( $orderData['photo_fee'],
                    $orderData['postage'],
                    $orderData['price']) = CalculateFeeModel::getFee($orderData['photo_number'] ,$orderData['province'], $orderData['city'], $orderData['area'], $orderData['street']);

                $m = (new CardDataModel());
                //设置订单为已支付
                $m->saveCardData($this->user_id, $orderData['cid'], array(
                    "pics" => $orderData['pics'],
                    "photo_number" => $orderData['photo_number'],
                    "photo_fee" => $orderData['photo_fee'],
                    "postage" => $orderData['postage'],
                    "price" => $orderData['price']
                ));

                $orderData['price'] = $orderData['price'] + $orderData['goodsPrice'];
            }
        }
    }

    protected function dealOrderPics(&$orderData) {
        if(!$orderData['pics']) {
            $orderData['pics'] = array();
        } else {
            $orderData['orderType'] = (in_array($orderData['sys'], CardDataModel::$SYS_TYPE['magicAlbum']) ? 1 : 0);
            $pics = json_decode($orderData['pics'], true);
            $picsData = array();
            foreach ($pics as $k => $v) {
                if($v['type'] == $this->uploadType["wxType"]) {
                    if(empty($accessToken)) {
                        $wxM = new WeixinModel();
                        $accessToken = $wxM->getAccessToken();
                        vendor("curl.function");
                        $c = new \curl();
                        $tstRet = $c->get("https://api.weixin.qq.com/cgi-bin/getcallbackip?access_token=$accessToken");
                        $tstRet = json_decode($tstRet, true);
                        if($tstRet['errcode']) {
                            $accessToken = $wxM->refereshAccessToken();
                        }
                    }
                    $picsData[] = array(
                        'type' => $v['type'],
                        'url' => "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=".$accessToken."&media_id=".$v['url']
                    );
                } else if ($v['type'] == $this->uploadType["wxLocalType"]) {
                    continue;
                } else {
                    $picsData[] = $v;
                }
            }
            $orderData['pics'] = json_encode($picsData);
            $orderData['photo_number'] = count($picsData);

            list( $orderData['photo_fee'],
                $orderData['postage'],
                $orderData['price']) = CalculateFeeModel::getFee($orderData['photo_number'],$orderData['province'], $orderData['city'], $orderData['area'], $orderData['street']);

            $orderData['price'] = $orderData['price'] + $orderData['goodsPrice'];

            $orderData['expressId'] = $orderData['express_type'];
            $orderData['expressName'] = CalculateFeeModel::$express[$orderData['express_type']]['name'];

            $goods = [];
            foreach($orderData['goods'] as $v) {
                $goods[] = [
                    "goodsName" => $v['category_name'],
                    "goodsPrice" => $v['count'] * $v['preferential_price'],
                    "goodsCount" => $v['count'],
                    "goodsImage" =>  $v['img_url']
                ];
            }
            $orderData['goods'] = $goods;
        }
    }

    public function getOrderListNew($status, $pageOrIndex, $type, $sort="create", $wx = 0, $getCoupon=0)
    {
        $wx = C("SHOW_ALL_ORDER")? $wx : 0;
    	$orderList = (new CardDataModel())->listCardDataPageNew($this->user_id,$status,$type,$sort,$wx,$getCoupon,$pageOrIndex,$this->pageLimit);

        $couponIds = array();
    	foreach($orderList as $k => $v) {
    		if(!$v['mailno']) {
    			$orderList[$k]['mailno'] = "";
    		}
            $this->dealOrderPics($orderList[$k]);
    		if(!$v['aid']) {
    			$orderList[$k]['aid'] = "";
    		}
            if($v["coupon_id"]) {
                $couponIds[] = $v["coupon_id"];
            }
            $orderList[$k]['preferentialPrice'] = 0;
            $orderList[$k]['couponDes'] = '';
    	}

        if($couponIds) {
            $couponM = new CouponModel();
            $couponList = $couponM->getCouponListByIds($couponIds);
            $couponListMap = array();
            foreach($couponList as $v) {
                $v['ex_data'] = $couponM->dealCouponExData($v['type'],$v['ex_data']);
                $couponListMap[$v['id']] = array(
                    'reduce' => $v['ex_data']['reduce_cost'] / 100,
                    'least' => $v['ex_data']['least_cost'] / 100,
                    'freePicCnt' => $v['ex_data']['free_pic_cnt'],
                    'type' => $v['type']
                );
            }

            foreach($orderList as $k => $v) {
                if($orderList[$k]["coupon_id"]) {
                    $coupon =  $couponListMap[ $v["coupon_id"] ];
                    if($coupon) {
                        $orderList[$k]['preferentialPrice'] = $coupon['reduce'];
                        $orderList[$k]['price'] = round($orderList[$k]['price'] - $coupon['reduce'], 1);
                        if($orderList[$k]['price'] < 0) {
                            $orderList[$k]['price'] = 0;
                        }
                        if($coupon['type'] == CouponModel::$FREE_TYPE) {
                            $orderList[$k]['couponDes'] = "(免费打印".$coupon['freePicCnt']."张)";
                        } else if ($coupon['type'] == CouponModel::$REDUCE_TYPE) {
                            $orderList[$k]['couponDes'] = "(满".($coupon['least']).'元减'.($coupon['reduce'])."元)";
                        }
                    } else {
                        $orderList[$k]['coupon_id'] = 0;
                    }
                }
            }
        }

    	$this->ajaxReturn(array('status'=>"ok", 'data'=>$orderList));
    }
    
    public function getOrderListTaobao($uid,$isPaid) {
        Vendor("curl.function");
        $c = new \curl();

        $ret = $c->get("http://www.molixiangce.com/Index/Api/getOrderListTaobao?uid=$uid&isPaid=$isPaid");
        //$ret = $c->get("http://local.moli.com/Index/Api/getOrderListTaobao?uid=$uid&isPaid=$isPaid");

        $this->ajaxReturn($ret = json_decode($ret,true));
    }

    // 添加地址项
    public function addAddress()
    {
        $postData = array(
            'name' =>  I('post.name', null),
            'phone' =>  I('post.phone', null),
            'province' =>  I('post.province', null),
            'city' =>  I('post.city', null),
            'area' =>  I('post.area', null),
            'street' =>  I('post.street', null),
        );

        if(is_null($postData['name'])) {
            $this->toFailed("invalid name");
        } else if(is_null($postData['phone'])) {
            $this->toFailed("invalid phone");
        } else if(is_null($postData['province'])) {
            $this->toFailed("invalid province");
        } else if(is_null($postData['city'])) {
            $this->toFailed("invalid city");
        } else if(is_null($postData['area'])) {
            $this->toFailed("invalid area");
        } else if(is_null($postData['street'])) {
            $this->toFailed("invalid street");
        } else {
            $postData['uid'] =  $this->user_id;
            $ret = array(
                'status' => 'ok',
                'data' => D("Index/Address")->addAddressData($postData),
            );

            $this->ajaxReturn($ret);
        }
    }

    // 修改地址项
    public function changeAddress()
    {
        $postData = array(
            'aid' =>  I('post.aid', null),
            'name' =>  I('post.name', null),
            'phone' =>  I('post.phone', null),
            'province' =>  I('post.province', null),
            'city' =>  I('post.city', null),
            'area' =>  I('post.area', null),
            'street' =>  I('post.street', null),
        );

        if(is_null($postData['name'])) {
            $this->toFailed("invalid name");
        } else if(is_null($postData['phone'])) {
            $this->toFailed("invalid phone");
        } else if(is_null($postData['aid'])) {
            $this->toFailed("invalid aid");
        } else if(is_null($postData['province'])) {
            $this->toFailed("invalid province");
        } else if(is_null($postData['city'])) {
            $this->toFailed("invalid city");
        } else if(is_null($postData['area'])) {
            $this->toFailed("invalid area");
        } else if(is_null($postData['street'])) {
            $this->toFailed("invalid street");
        } else {
            $postData['uid'] =  $this->user_id;
            $aid = $postData['aid'];
            unset($postData['aid']);

            D("Index/Address")->saveAddressData($aid,$postData);
            $ret = array(
                'status' => 'ok',
            );
            $this->ajaxReturn($ret);
        }
    }

    // 删除地址项
    public function delAddress($aid)
    {
        D("Index/Address")->deleteAddressData($aid);
        $ret = array(
            'status' => 'ok',
        );
        $this->ajaxReturn($ret);
    }

    // 获取地址列表
    public function getAddressList()
    {
        $m = D("Index/Address");
        $address = D("Index/Address")->getAddressList($this->user_id);
        $defaultId = $m->getDefaultAddress($this->user_id);

        foreach ($address as $key => $value) {
        	if(!$defaultId){
        		$defaultId = $value['id'];
        	}
        	
            if($value['id'] == $defaultId) {
                $temp = $address[0];
                $address[0] = $address[$key];
                $address[$key] = $temp;
                break;
            }
        }

        if(empty($defaultId)) {
            $defaultId = '';
        }

        $this->ajaxReturn(array('status'=>"ok",'data'=> $address, 'defaultId' => $defaultId));
    }

    // 获取订单详情
    public function getOrderDetail($cid, $delLocalPics=0)
    {
        $orderData = (new CardDataModel())->appGetCard($cid, $this->user_id);
        if($orderData) {
            if($orderData['status'] == 10){
                if($orderData['op_status'] == 0 && $orderData['is_pdf'] == 0) {
                    $orderData['op_desc'] = '已付款，订单等待受理';
                } else {
                    $orderData['op_desc'] = '订单已受理';
                }
            } else {
                $orderData['op_desc'] = '未付款';
                if($delLocalPics) {
                    $this->deleteLocalPics($orderData);
                }
            }

            $this->dealOrderPics($orderData);
            if(!$orderData['aid']) {
                $orderData['aid'] = "";
            }

            $orderData['couponDes'] = '';
            $orderData['preferentialPrice'] = 0;
            $coupon = null;

            $orderData['orderType'] = (in_array($orderData['sys'], CardDataModel::$SYS_TYPE[magicAlbum]) ? 1 : 0);

            if($orderData['coupon_id']) {
                $couponM = new CouponModel();
                $couponData = $couponM->getBindCoupon($this->user_id, $orderData['coupon_id'], $orderData['cid'], ($orderData['status'] == 10));
                $reduceFee = 0;
                if($couponData) {
                    $reduceFee = ($couponData['ex_data']['reduce_cost'] / 100);
                }
                if($reduceFee) {
                    if($couponData['type'] == CouponModel::$FREE_TYPE) {
                        $orderData['couponDes'] = "(免费打印".$couponData['ex_data']['free_pic_cnt']."张)";
                    } else if ($couponData['type'] == CouponModel::$REDUCE_TYPE) {
                        $orderData['couponDes'] = "(满".($couponData['ex_data']['least_cost'] / 100).'元减'.($couponData['ex_data']['reduce_cost'] / 100)."元)";
                    }

                    $orderData['preferentialPrice'] = $reduceFee;
                    $orderData['price'] = round($orderData['price'] - $reduceFee, 1);
                    if($orderData['price'] <= 0) {
                        $orderData['price'] = 0.01;
                    }
                }
                $orderData['order_id'] = 0;
            }
            $this->ajaxReturn(array('status'=>"ok",'data'=>$orderData));
        } else {
            $this->toFailed("invalid cid");
        }
    }

    // 设置默认地址
    public function setDefaultAddress($aid)
    {
        D("Index/Address")->setDefaultAddress( $this->user_id, $aid);
        $this->ajaxReturn(array('status'=>"ok"));
    }
    
    //
    public function getPayType()
    {
    	$paytype_info = D("Index/PayType")->getPayType($this->user_id);
    	if($paytype_info){
    		$this->ajaxReturn(array('status'=>"ok",'data'=>$paytype_info['paytype']));
    	}else{
    		$this->ajaxReturn(array('status'=>"ok",'data'=>'wx'));
    	}
    }
    
    public function getCardInfoByAid($aid)
    {
    	$cardInfo = (new CardDataModel())->getCardInfoByAid($aid);
    	$this->ajaxReturn($cardInfo);
    }
    
    public function nopayJpushRegister($orderno){
    	$data['user_id']=$this->user_id;
    	$data['orderno']=$orderno;
    	$ret = D("Index/NoPayPush")->addNoPay($data);
    	$this->ajaxReturn(array('status'=>"ok"));
    }
    
    // 获取订单详情
    public function getOrderDetailNew($cid)
    {
    	$orderData = (new CardDataModel())->appGetCardNew($cid);
    	if($orderData) {
    		if($orderData['status'] == 10){
    			if($orderData['op_status'] == 0 && $orderData['is_pdf'] == 0) {
    				$orderData['op_desc'] = '已付款，订单等待受理';
    			} else {
    				$orderData['op_desc'] = '订单已受理';
    			}
    		} else {
    			$orderData['op_desc'] = '未付款';
    		}
    		if(!$orderData['pics']) {
    			$orderData['pics'] = array();
    		}
    		if(!$orderData['aid']) {
    			$orderData['aid'] = "";
    		}
    		$this->ajaxReturn(array('status'=>"ok",'data'=>$orderData));
    	} else {
    		$this->toFailed("invalid cid");
    	}
    }

    //卡券列表页
    public function couponList($type = 0) {
        $m = new CouponModel();
        $ret['data'] = $m->getUserCouponList($this->user_id, $type, 1, $this->pageCount);
        $this->assign('couponList', $ret);
        $token = $this->getToken();
        $this->assign("token",$token);
        $this->assign('static_v',$this->static_v);//  版本号
        $this->display('App:couponList');
    }

    //ajax请求卡券列表
    public function ajaxGetCouponList(){
        $m = new CouponModel();
        $type = I('post.type', 0);
        $page = I('post.page', 1);
        $ret['data'] = $m->getUserCouponList($this->user_id, $type, $page, $this->pageCount);
        $ajaxRet = array('status' => 'error');
        if($ret){
            $ajaxRet['status'] = 'ok';
            $ajaxRet['data'] = $ret;
        }
        $this->ajaxReturn($ajaxRet);
    }

    //可用卡券 (支付页)
    public function canUseCouponList(){
        // 优惠卷列表
		$this->assign("couponList","[]");
        $this->assign('static_v',$this->static_v);//  版本号
        $this->display('App:canUseCouponList');
    }

    public function unreadCoupon() {
        $couponM = new CouponModel();
        $couponList = $couponM->getUnReadCounponList($this->user_id);
        if($couponList) {
            $couponList = $couponM->dealCouponData($couponList);
            $this->assign("couponList",array('data'=>$couponList));
        } else {
            $this->assign("couponList",array('data'=>array()));
        }

        $couponM->setUnreadCoupon($this->user_id);
        $this->assign('static_v',$this->static_v);//  版本号
        $this->display('App:unreadCoupon');
    }

    // 测试用添加优惠卷
    public function testAddCoupon($type) {
        $m = M();
        if($type == 1) {
            $ret = $m->table("user_coupon_info")->add(array(
                'uid' => $this->user_id,
                'type' => $type,
                'ex_data' => '{"free_pic_cnt":20}'
            ));
        }
        if($type == 2) {
            $ret = $m->table("user_coupon_info")->add(array(
                'uid' => $this->user_id,
                'type' => $type,
                'ex_data' => '{"least_cost":2000,"reduce_cost":500}'
            ));
            $ret = $m->table("user_coupon_info")->add(array(
                'uid' => $this->user_id,
                'type' => $type,
                'ex_data' => '{"least_cost":5000,"reduce_cost":1500}'
            ));
            $ret = $m->table("user_coupon_info")->add(array(
                'uid' => $this->user_id,
                'type' => $type,
                'ex_data' => '{"least_cost":10000,"reduce_cost":3000}'
            ));
            $ret = $m->table("user_coupon_info")->add(array(
                'uid' => $this->user_id,
                'type' => $type,
                'ex_data' => '{"least_cost":15000,"reduce_cost":5000}'
            ));
        }

        $this->ajaxReturn(array("ret"=>$ret));
    }

    public function getTraceInfo($mailno)
    {
    	$traceInfo = D("Index/TraceInfo")->getTraceInfo($mailno);
    	if($traceInfo){
    		$this->ajaxReturn(array('status'=>"ok",'data'=>$traceInfo));
    	}else{
    		$this->ajaxReturn(array('status'=>"fali",'data'=>'没有此快递单号！'));
    	}
    }

    public function ajaxCheckOrder($cid) {
        if(C("SHOW_ALL_ORDER")) {
            $this->ajaxReturn(array('status'=>'ok'));
        } else {
            $orderData = (new CardDataModel())->appGetCard($cid, $this->user_id);
            if($orderData['sys'] == "moliAndroid" || $orderData['sys'] == "moliIos") {
                $this->ajaxReturn(array('status'=>'ok'));
            } else {
                $this->ajaxReturn(array('status'=>'error'));
            }
        }
    }

    public function addUserCoupon($templateId, $count)
    {
        $m = M();
        $couponTemplate = $m->table('set_coupon')->where(['id'=>$templateId])->find();

        if($couponTemplate) {
            for($idx = 0, $count = intval($count);$idx < $count; ++$idx) {
                $m->table("user_coupon_info")->add(array(
                    'uid' => $this->user_id,
                    'type' => $couponTemplate['couponType'],
                    'sub_type' => 0,
                    'expiration' => date("Y-m-d H:i:s", time() + $couponTemplate['expiryDate']),
                    'ex_data' => $couponTemplate['couponValue']
                ));
            }
            $this->ajaxReturn(array("status"=>'ok'));
        }
        $this->ajaxReturn(array("status"=>'error', 'reason'=>'优惠卷模板查找失败!'));
    }

    //优惠券使用说明页
    public function couponUseInfo(){
        $this->display("App:couponUseInfo");
    }

    /**
     * 商品详情
     * @param integer $goodsId  商品id
     * @param integer $goodsNum  商品数量
     * @param string $specification  商品规格
    */
    public function goodsDetail($goodsId, $goodsNum, $specification){
        $model = new \Warehouse\Model\CategoryModel();
        $config = C("GOODS")[$goodsId];
        $data = [
            'goodsId' => $config['id'],
            'iconUrl' => $config['img_url'],
            'title' => $config['category_name'] ,
            'originalPrice' => $config['unit_price'],
            'price' => $config['preferential_price'],
            'desc' => $config['display_desc'],
        ];

        $data['specification'] = $specification;
        $data['goodsNum'] = $goodsNum;
        if($data) {
            $goodsDetail = json_decode($data['desc'], true);
            unset($data['desc']);
            $data['goodsDes'] = $goodsDetail['des'];
            $data['bannerImgArry'] = $goodsDetail['imgUrls'];
        }

        $this->assign('data', $data);
        $this->display('App:goodsDetail');
    }

    //商品选购
    public function choseBuyGoods($cid)
    {
        $config = C("GOODS");
        $data = [];
        foreach($config as $k => $v) {
            $data[$k] = [
                'goodsId' => $v['id'],
                'iconUrl' => $v['img_url'],
                'title' => $v['category_name'] ,
                'originalPrice' => $v['unit_price'],
                'price' => $v['preferential_price'],
                'goodsNum' => 0
            ];
        }
        $totalPrice = 0;
        $orderM = new CardDataModel();
        $order = $orderM->appGetCardNew($cid);

        if($order) {
            foreach ($order['goods'] as $v) {
                $data[$v['id']]['goodsNum'] = $v['count'];
                $totalPrice += $data[$v['id']]['price'] * $v['count'];
            }
        }

        $token = $this->getToken();
        $this->assign("token",$token);
        $this->assign('data', $data);
        $this->assign('totalPrice', $totalPrice);
        $this->display('App:choseBuyGoods');
    }

    public function getFinalPrice($ver, $sys, $mainPrice, $postage, $goodsPrice, $couponPrice, $deliveryPayPrice)
    {
        $finalPrice = round($mainPrice+$postage+$goodsPrice-$couponPrice+$deliveryPayPrice, 2);
        $this->ajaxReturn([
           'status' => 'ok',
            'data' => [
                'finalPrice' => $finalPrice,
                'integralHint' => '下单可获得'.intval($finalPrice).'积分',
            ]
        ]);
    }

    public function getExpressByArea($province, $city, $city, $area, $street)
    {
        $this->ajaxReturn([
            "status" => "ok",
            "data" => CalculateFeeModel::getPostDetail($province, $city, $area, $street)
        ]);
    }
}