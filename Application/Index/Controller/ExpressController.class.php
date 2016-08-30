<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Index\Controller;

use Index\Controller\ServiceController;

/**
 * Description of ExpressController
 * 打印电子面单服务
 */
class ExpressController extends ServiceController {

    private $sessionKey = "6200d21947d0e3f91abe8cd9948e8ZZba8a27a8a65e05831835837568";  //直接从数据库读取
    private $refresh_token = "6201d218d6ac23b148ad2cadc2e0cegad36dc73e361f5441835837568";
    private $no_pay_push = false;
    private $express_open_off = false;
    private $_opUserId = '';
    private $_theNum = '';

    /**
     * order Model 
     * @var /Index/Model/OrderModel
     */
    private $_orderModel = null;

    public function __construct() {
        parent::__construct();
        $this->_orderModel = new \Index\Model\OrderModel();
    }

    /**
     * 打印电子面单入口
     */
    public function index() {
        $pickId = I("param.pickid");
        $packageModel = new \Index\Model\PackageModel();
        $todayPackNum = $packageModel->getTodayPackageCount();

        if ($pickId) {
            $this->assign("pickId", $pickId);
        }
        $this->assign("resyuming", RES_YUMING);
        $this->assign("appkey", $this->appkey);
        $this->assign("sellid", $this->sellid);
        $this->assign("mailType", $this->mailType);

        $this->assign("maildate", date("Y-m-d", time()));
        $this->assign("todayPackNum", $todayPackNum);
        $this->display('index');
    }

    /**
     * 打印电子面单数据准备
     */
    public function toPrint() {
        $operator = I("post.operator");
        $this->_opUserId = $opUserId = $this->checkOperator($operator);
        $theNum = I("post.the_num", "", "trim");

        $orderInfo = $this->getOrdersByTheNum($theNum);
        if (empty($orderInfo)) {
            $this->ajaxError("打印失败，查不到相应的订单信息");
        }
        $orderNos = $this->checkOrderInfo($orderInfo);
        $packId = $this->getPackageId($theNum);
        $oneOrderInfo = $orderInfo[0];
        $cpCode = $this->getCpCode($oneOrderInfo);
        // 根据订单号获取面单号
        $mailInfo = $this->getMailInfo($oneOrderInfo, $packId, $theNum, $cpCode);
        $mailNo = $this->checkGetMailinfoRes($mailInfo);

        $isReYin = 0;    //是否是重复打印
        if ($mailNo != $oneOrderInfo['mailno']) {
            //@todo 考虑推送，微信推送需要考虑推送后的详情页信息更改
//            $this->pushToWechat($orderInfo);
//            $this->pushToJpush($mailNo, $oneOrderInfo, $orderNos);
        } else {
            $isReYin = 1;
        }

        // 面单号存入数据库
        D("Index/Package")->savePackageMailNo($packId, $mailNo);
        //存入最后一个面单号，方便查询
        if ($orderNos) {
            //目前兼容旧订单使用printItemModel  后续调整使用OrderModel
            $printItemModel = new \Index\Model\PrintItemModel();
            $printItemModel->savePrintItem(array("orderno" => array("in", $orderNos)), array("mailno" => $mailNo));
//            $this->_orderModel->saveOrder(array("orderno" => array("in", $orderNos)), array("mailno" => $mailNo));
        }
        //获取最新的今天快递单号数
        $todayPackNum = D("Index/Package")->getTodayPackageCount();
        $oneOrderInfo['mailno'] = "$mailNo";
        
        if (strpos($this->_theNum, "p") === 0) {
            $this->deletePickListFromCache($this->_theNum);
        }
        if (!$isReYin) {
            $this->orderCheckOut($orderNos);
        }
        // 进行打印操作
        $this->ajaxSuccess(array(
            "printCfg" => array(
                'cp_code' => $cpCode,
            ),
            "orderInfo" => $oneOrderInfo,
            "mailInfo" => json_encode($mailInfo),
            "isReYin" => $isReYin,
            "todayPackNum" => $todayPackNum,
                //"url_type" => $pic['type'],
                //"url" => $pic['url']
        ));
        // TODO 是否需要打印确认接口v1.0        
    }

    /**
     * 根据实际情况或指定算法获取当前物流类型
     * @param type $orderInfo
     * @return type
     */
    private function getCpCode($orderInfo) {
        if ($this->isEMSAddress($orderInfo)) {
            if (C("CAN_USE_EMS")) {
                return "EMS";
            } else {
                $this->ajaxError("此订单韵达无配送点，请使用EMS发货。");
            }
        }
        return $this->mailType;
    }

    /**
     * 是否发EMS的地址
     * @param type $orderInfo
     * @return boolean
     */
    private function isEMSAddress($orderInfo) {
        $emsMap = array(
            array('青海', '海东地区', '循化县'),
            array('青海', '海东地区', '化隆县'),
            array('青海', '海西州', '乌兰县'),
            array('青海', '海南州', '同德县'),
            array('青海', '海南州', '兴海县'),
            array('青海', '海南州', '贵南县'),
            array('青海', '黄南州', '泽库县'),
            array('青海', '黄南州', '尖扎县'),
            array('青海', '黄南州', '河南县'),
            array('青海', '果洛州', '玛沁县'),
            array('青海', '果洛州', '班玛县'),
            array('青海', '果洛州', '甘德县'),
            array('青海', '果洛州', '达日县'),
            array('青海', '果洛州', '久治县'),
            array('青海', '果洛州', '玛多县'),
            array('青海', '玉树州', '玉树县'),
            array('青海', '玉树州', '杂多县'),
            array('青海', '玉树州', '称多县'),
            array('青海', '玉树州', '治多县'),
            array('青海', '玉树州', '囊谦县'),
            array('青海', '玉树州', '曲麻莱县'),
            array('四川', '阿坝州', '若尔盖县'),
            array('四川', '阿坝州', '红原县'),
            array('四川', '阿坝州', '阿坝县'),
            array('四川', '阿坝州', '黑水县'),
            array('四川', '阿坝州', '壤塘县'),
            array('四川', '阿坝州', '金阳县'),
            array('四川', '阿坝州', '布拖县'),
            array('四川', '凉山州', '雷波县'),
            array('四川', '甘孜州', '巴塘县'),
            array('四川', '甘孜州', '白玉县'),
            array('四川', '甘孜州', '丹巴县'),
            array('四川', '甘孜州', '道孚县'),
            array('四川', '甘孜州', '稻城县'),
            array('四川', '甘孜州', '得荣县'),
            array('四川', '甘孜州', '德格县'),
            array('四川', '甘孜州', '九龙县'),
            array('四川', '甘孜州', '理塘县'),
            array('四川', '甘孜州', '炉霍县'),
            array('四川', '甘孜州', '色达县'),
            array('四川', '甘孜州', '石渠县'),
            array('四川', '甘孜州', '乡城县'),
            array('四川', '甘孜州', '新龙县'),
            array('四川', '甘孜州', '雅江县'),
            array('云南', '怒江州', '福贡县'),
            array('云南', '怒江州', '贡山县'),
            array('西藏', '拉萨市', '林周县'),
            array('西藏', '拉萨市', '达孜县'),
            array('西藏', '拉萨市', '尼木县'),
            array('西藏', '拉萨市', '当雄县'),
            array('西藏', '拉萨市', '曲水县'),
            array('西藏', '拉萨市', '墨竹工卡县'),
            array('西藏', '拉萨市', '堆龙德庆县')
        );

        foreach ($emsMap as $one) {
            if (strrpos($orderInfo['province'], $one[0]) !== FALSE &&
                    strrpos($orderInfo['city'], $one[1]) !== FALSE &&
                    strrpos($orderInfo['area'], $one[2]) !== FALSE) {
                return true;
            }
        }
        if (strrpos($orderInfo['province'], '浙江') !== FALSE &&
                strrpos($orderInfo['city'], '杭州市') !== FALSE &&
                strrpos($orderInfo['area'], '萧山区') !== FALSE &&
                strrpos($orderInfo['street'], '瓜沥') !== FALSE) {
            return true;
        }
        return false;
    }

    /**
     * 出库 定制品仓位解绑 规格品库存更新 及记录出库记录
     * @param array $orderNos
     */
    private function orderCheckOut($orderNos) {
        $orsModel = new \Index\Model\OrderRelationShipModel();
        $products = $orsModel->baseGet(array("orderno" => array("in", $orderNos), "count" => array("gt", 0)));
        $productCounts = array();
        $ids = array();
        $outRecordModel = new \Index\Model\OutrecordModel();
        $outRecordModel->startTrans();
        foreach ($products as $oneProduct) {
            if ($oneProduct['goods_type'] == \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL) {
                $productCounts['normal'][$oneProduct['product_id']] +=$oneProduct["count"];
                $type = \Index\Model\OutrecordModel::TYPE_NORMAL;
            } else {
                $productCounts['custom'][$oneProduct['product_id']] += $oneProduct["count"];
                $type = \Index\Model\OutrecordModel::TYPE_CUSTOM;
            }
            $ids[] = $oneProduct['id'];
            if (!$outRecordModel->baseAdd(array(
                        "op_userid" => $this->_opUserId,
                        "op_time" => time(),
                        "type" => $type,
                        "category_id" => $oneProduct['product_id'],
                        "count" => $oneProduct['count'],
                        "pick_id" => $this->_theNum)
                    )) {
                $outRecordModel->rollback();
            }
        }
        $res1 = $orsModel->updateProcess(array("id" => array("in", $ids)), \Index\Model\OrderRelationShipModel::PROCESS_PUT_OUT);
        $res2 = $this->customOrderCheckOut($productCounts['custom']);
        $res3 = $this->normalOrderCheckOut($productCounts['normal']);
        if ($res1!==false && $res2 && $res3) {
            $outRecordModel->commit();
        }
        $outRecordModel->rollback();
    }

    /**
     * 定制品解绑仓位
     * @param type $customProductCounts
     * @return type
     */
    private function customOrderCheckOut($customProductCounts) {
        $customModel = new \Index\Model\CustomModel();
        $productIds = array_keys($customProductCounts);
        return $customModel->emptyByProductId($productIds);
    }

    /**
     * 规格品更新库存
     * @param type $normalProductCounts
     * @return boolean
     */
    private function normalOrderCheckOut($normalProductCounts) {
        $categoryModel = new \Index\Model\CategoryModel();
        foreach ($normalProductCounts as $categoryId => $count) {
            if (!$categoryModel->decCurCount(array("category_id" => $categoryId), (int) $count)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 依据商品编号或拣货单号获取订单信息
     * @param type $theNum 商品编号或拣货单号 p开头为拣货单号
     * @return array 二维数组
     */
    private function getOrdersByTheNum($theNum) {
        $orderNoArr = array();
        $orderInfo = array();
        $names = C("PRODUCT_NAMES");
        $errorMsg = '';
        $orsModel = new \Index\Model\OrderRelationShipModel();
        $this->_theNum = $theNum;
        if (strpos($theNum, "p") === 0) {
            $pickListModel = new \Index\Model\PicklistModel();
            $pickList = $pickListModel->baseGet(array("pick_id" => $this->_theNum, "is_del" => 0));
            foreach ($pickList as $onePick) {
                $orderNoArr[] = $onePick["orderno"];
            }
            $products = $orsModel->baseGet(array("orderno" => array("in", $orderNoArr), "count" => array("gt", 0)), array("orderno", "product_id", "goods_type", "status"));
            foreach ($products as $one) {
                if (($one['status'] & \Index\Model\OrderRelationShipModel::ORDER_STATUS_USE_PAID) != \Index\Model\OrderRelationShipModel::ORDER_STATUS_USE_PAID) {
                    $errorMsg .="订单" . $one['orderno'] . "商品编号" . $one['product_id'] . $names[$one['goods_type']] . "已经取消或未成功支付；";
                }
                $orderNoArr[] = $one["orderno"];
            }
        } elseif (strlen($theNum) > 10) {
            $productInfo = $orsModel->baseFind(array("product_id" => $theNum, "goods_type" => array("neq", \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL)));
            if ($productInfo) {
                if (($productInfo['status'] & \Index\Model\OrderRelationShipModel::ORDER_STATUS_USE_PAID) != \Index\Model\OrderRelationShipModel::ORDER_STATUS_USE_PAID) {
                    $errorMsg .="订单" . $productInfo['orderno'] . "商品编号" . $productInfo['product_id'] . $names[$one['goods_type']] . "已经取消或未成功支付；";
                }
                $orderNoArr = array($productInfo['orderno']);
            }
        }
        if (!empty($errorMsg)) {
            $this->ajaxError("部分订单商品已经取消，请勿寄出：" . $errorMsg);
        }
        if (!empty($orderNoArr)) {
            $orderInfo = array_unique($orderInfo);
            if (count($orderNoArr) == 1) {
                $orderInfo = $this->_orderModel->findOrderList(array("orderno" => array_shift($orderNoArr)));
            } else {
                $orderInfo = $this->_orderModel->findOrderList(array("orderno" => array("in", $orderNoArr)));
            }
        }
        return $orderInfo;
    }

    /**
     * 检查订单级别信息
     * @param array $orderInfo 多条记录
     * @return array 订单号数组
     */
    private function checkOrderInfo($orderInfo) {
        if (empty($orderInfo)) {
            $this->ajaxError("打印失败，查不到相应的订单信息");
        }
        //uid\name\phone\province\city\area\street common order info
//        $isKuerleCity = (strrpos($orderInfo[0]['province'], '新疆') !== FALSE && strrpos($orderInfo[0]['city'], '库尔勒') !== FALSE);
//        $isKuerleArea = strrpos($orderInfo[0]['area'], '库尔勒') !== FALSE;
//        if ($isKuerleCity || $isKuerleArea) {
//            $this->ajaxError($orderInfo[0]['province'] . "-" . $orderInfo[0]['city'] . "-" . $orderInfo[0]['area'] . ", 该地区暂时不支持派送!");
//        }
        $orderNos = array();
        foreach ($orderInfo as $one) {
            $orderNos[] = $one['orderno'];
        }
        return $orderNos;
    }

    /**
     * 获取包裹号 （可能是商品编号或拣货单号对应一个包裹单号）
     * 注：newpack用于处理可能单个商品编号或拣货单号，分两次申请快递号并发快递
     * @param type $theNum
     * @return int
     */
    private function getPackageId($theNum) {
        $newPack = I("post.newPack", 0);
        $packageModel = new \Index\Model\PackageModel();
        if ($newPack == 1) {
            // 新增一个包裹号
            $packId = $packageModel->addPackageId($theNum);
        } else {
            // 获取包裹单号
            $packId = $packageModel->getLastPackageId($theNum);
        }
        return $packId;
    }

    /**
     * 检查获取电子面单结果
     * @param string 正确返回电子面单号
     */
    private function checkGetMailinfoRes($mailInfo) {
        if (isset($mailInfo->waybill_apply_new_cols)) {
            return $mailInfo->waybill_apply_new_cols->waybill_apply_new_info->waybill_code->__toString();
        } else {
            if ($mailInfo->code == 27) {
                $this->ajaxError("打印失败，授权已经过期，请联系 星罗开发人员，手机：18050047093");
            }
            $this->ajaxError($mailInfo->code . ":" . $mailInfo->msg . "," . $mailInfo->sub_code . "," . $mailInfo->sub_msg);
        }
    }

    /**
     * 推送通知到用户微信
     * @todo 查看详情url调整
     * @param array $orderInfo
     */
    private function pushToWechat($orderInfo) {
        vendor("curl.function");
        $c = new \curl();

        $userInfo = D('Index/User')->getUserInfo($orderInfo[0]['uid']);
        $accessToken = (new WeixinModel())->getAccessToken();
        $accessUrl = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$accessToken";
        foreach ($orderInfo as $oneOrderInfo) {
            // 推送微信消息
            $accessJson = $c->post($accessUrl, json_encode(array(
                "touser" => $userInfo['webOpenID'],
                "template_id" => "BWrIrKgMiryB9VAtpJG46fkmIUfVBDzDHflPYON8Kw8",
                "url" => 'http://' . $_SERVER['HTTP_HOST'] . '/Index/Index/index', //@todo 待调整
                //"url" => 'http://' . $_SERVER['HTTP_HOST'] . '/Index/Index/orderDetailExx/orderno/' . $oneOrderInfo['orderno'],
                "data" => array(
                    "first" => array(
                        "value" => "您好，您的订单已出库发货！",
                        "color" => "#173177"
                    ),
                    "keyword1" => array(
                        "value" => $oneOrderInfo['orderno'],
                        "color" => "#173177"
                    ),
                    "keyword2" => array(
                        "value" => date("Y-m-d"),
                        "color" => "#173177"
                    ),
                    "remark" => array(
                        "value" => "请耐心等待！点击查看订单信息！",
                        "color" => "#173177"
                    )
            ))));
        }
    }

    /**
     * 推送到Jpush
     * @param string $mailNo 电子面单号
     * @param array $oneOrderInfo  一条订单信息
     * @param array $orderNos 所有订单号
     */
    private function pushToJpush($mailNo, $oneOrderInfo, $orderNos) {
        if ($this->express_open_off) {
            //发货的时候在快递鸟订阅物流信息
            $this->kdniaoDy($mailNo);

            // 推送 JPush 消息
            try {
                Vendor('JPush.JPush');
                $notifyRet = M()->table("jpush_token")->where("userId=" . $oneOrderInfo['uid'])->select();
                foreach ($notifyRet as $value) {
                    if (!$value['token']) {
                        continue;
                    }
                    //,"url_type"=>$pic['type'],"url"=>$pic['url']
                    $data = array("type" => "5", "orderId" => $oneOrderInfo['id'], "courierId" => "$mailNo", "orderno" => $oneOrderInfo['orderno']);
                    $client = new \JPush('0c4f8e0a723bac74999de4f2', 'a670146d863e77440328cca3');
                    $client->push()
                            ->setPlatform('ios', 'android')
                            ->addAndroidNotification('物流已发货', "您的订单号为 " . implode("/", $orderNos) . " 已发货, 快递单号为 $mailNo", 1, $data)
                            ->addIosNotification("物流已发货", 'iOS sound', '+1', true, 'iOS category', $data)
                            ->addIosNotification("您的订单号为 " . implode("/", $orderNos) . " 已发货, 快递单号为 $mailNo", null, null, true, null, $data)
                            ->addRegistrationId($value['token'])
                            ->setOptions(null, null, null, true)
                            ->send();
                }
            } catch (Exception $e) {
                
            }
        }
    }

    /**
     * 获取电子面单号
     * @param array $orderInfo 一条订单信息
     * @param int $packId 包裹号
     * @param string $theNum 商品编号或拣货单号
     * @param string $cpCode 物流类型详见菜鸟打印电子面单模版基础类型
     * @return type
     */
    private function getMailInfo($orderInfo, $packId, $theNum, $cpCode) {
        vendor("taobao.TopSdk");
        date_default_timezone_set('Asia/Shanghai');

        $c = new \TopClient ();
        $c->appkey = $this->appkey;
        $c->secretKey = $this->secret;
        $req = new \WlbWaybillIGetRequest ();
        $waybill_apply_new_request = new \WaybillApplyNewRequest ();
        $waybill_apply_new_request->cp_code = $cpCode; // 全峰快递 QFKD 韵达 YUNDA EMS
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
        $trade_order_info_cols->trade_order_list = $theNum; // 商品编号或拣货单号-订单列表,可以塞多个订单号一个包裹？
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
        $req->setWaybillApplyNewRequest(json_encode($waybill_apply_new_request));

// 		$rs = D("Index/SysInfo")->getAccessToken();    //从数据库读取的代码，已放弃
// 		$sessionKey = $rs->access_token; 

        $sessionKey = $this->sessionKey;
        $resp = $c->execute($req, $sessionKey);

        return $resp;
    }

    /**
     * 快递鸟订阅测试
     * @param type $mailno
     */
    public function kdniaoDy($mailno) {
        vendor("kdniao.function");
        $requestData = "{'Code': 'YD','Item': [{'No': '" . $mailno . "','Bk': ''}]}";
        $datas = array(
            'EBusinessID' => EBusinessID,
            'RequestType' => '1005',
            'RequestData' => urlencode($requestData),
            'DataType' => '2',
        );
        $datas['DataSign'] = encrypt($requestData, AppKey);
        $result = sendPost(ReqURL, $datas);
        echo $result;
        exit;
    }

    public function refreshToken() {
        $url = "https://oauth.taobao.com/token";
        $data = array(
            'grant_type' => "refresh_token",
            'client_id' => $this->appkey,
            'client_secret' => $this->secret,
            "refresh_token" => $this->refresh_token
        );
        $rs = $this->postTaobaoData($url, $data);
        var_dump($rs);
    }

    /**
     * packages 列表
     */
    public function packages() {
        $menuCrumbs = array(
            "first" => "包裹列表",
            "second" => array("menuName" => "包裹列表", "url" => "/Index/Express/packages"),
        );
        $this->assign("menuCrumbs", $menuCrumbs);

        $packageModel = new \Index\Model\PackageModel();
        $lists = $packageModel->order(array("id" => "desc"))->limit(50)->select();
        $this->assign("data", $lists);
        $this->display("packages");
    }

}
