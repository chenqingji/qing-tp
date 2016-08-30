<?php

namespace Index\Controller;

use Index\Model\PrintItemModel as CardDataModel;

class OrderController extends WrapController {

    /**
     * 系统后台默认下单uid
     */
    const DEFAULT_SYS_UID = 4120;

    /**
     * 订单中心 一级菜单
     * @var type 
     */
    private $_menuCrumbsFirst = "订单中心";

    /**
     * construct
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * index
     */
    public function index() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "订单列表", "url" => "/Index/Order/index"),
        );

        $this->assign("menuCrumbs", $menuCrumbs);
        $this->display("index");
    }

    /**
     * 获取列表数据
     */
    public function getListData() {
        $page = I("param.page", 1);
        $rows = I("param.rows", 10);
        $sidx = I("param.sidx", 'create_time');
        $sord = I("param.sord", 'desc');
        $where = $this->getSearchCondition();
//        $field = "*";
        $field = array(
            "id", "order.uid" => "uid", "orderno", "from", "price", "pay_type",
            "paidTime", "name", "phone", "province", "city", "create_time", "update_time",
            "area", "street", "mailno", "ext", "status", "coupon_id", "nickname"
        );
        $orderModel = new \Index\Model\OrderModel();
        if (empty($sidx) || empty($sord)) {
            $sidx = "create_time";
            $sord = "desc";
        }
        $order = array($sidx => $sord);
        echo json_encode($orderModel->baseGetPage($where, $field, $page, $rows, $order), TRUE);
        exit;
    }

    /**
     * 订单操作分发入口
     */
    public function orderOperation() {
        $oper = I("post.oper", null);
        if (!empty($oper)) {
            switch ($oper) {
                case 'add':
                    $this->ajaxError("未完善" . I("post.oper") . "操作");
                    $this->toAdd();
                    break;
                case 'edit':
                    $this->toEditOrder();
                    break;
                case 'del':
                    $this->ajaxError("未完善" . I("post.oper") . "操作");
                    $this->toDelete();
                    break;
                default:
                    $this->ajaxError("未完善" . I("post.oper") . "操作");
                    exit;
                    break;
            }
        }
    }

    /**
     * 修改订单信息，目前主要用于修改订单联系人、联系地址、联系方式、收件人
     */
    private function toEditOrder() {
        $data = array();
        $id = I("post.id", 0);
        $data['name'] = I("post.name", '', array('htmlspecialchars', 'trim'));
        $data['phone'] = I("post.phone", 0);
        $data['province'] = I("post.province", '', array('htmlspecialchars', 'trim'));
        $data['city'] = I("post.city", '', array('htmlspecialchars', 'trim'));
        $data['area'] = I("post.area", '', array('htmlspecialchars', 'trim'));
        $data['street'] = I("post.street", '', array('htmlspecialchars', 'trim'));

        if (empty($data['province'] . $data['city'] . $data['area'] . $data['street'])) {
            $this->ajaxError("联系地址不能为空");
        }
        if (empty($data['name']) || empty($data['phone'])) {
            $this->ajaxError("收件人/联系方式不能为空");
        }

        $paidFlag = true;
        $noPickedFlag = true;
        $cids = $orsIds = array();
        $orsModel = new \Index\Model\OrderRelationShipModel();
        $productList = $orsModel->baseGet(array("order_id" => $id));
        if ($productList) {
            foreach ($productList as $one) {
                $oldUhash = $one['uhash'];
                $uid = $one['uid'];
                if (!($one['status'] & \Index\Model\OrderRelationShipModel::ORDER_STATUS_PAID)) {
                    $paidFlag = false;
                    break;
                }
                if ($one['process'] >= \Index\Model\OrderRelationShipModel::PROCESS_PICKED) {
                    $noPickedFlag = false;
                    break;
                }
                $orsIds[] = $one['id'];
            }

            $orsModel->startTrans();
            $orderInfoRes = $uhashRes = true;
            if ($paidFlag && $noPickedFlag) {
                //已支付：未拣货，允许修改联系地址、联系方式，调整所有商品的uhash
                $newUhash = $orsModel->addressMd5(array($data['name'], $data['phone'], $data['province'], $data['city'], $data['area'], $data['street']));
                //更新ors 的所有uhash
                if ($newUhash != $oldUhash) {
                    $uhashRes = $orsModel->baseSave(array("id" => array("in", $orsIds)), array("uhash" => $newUhash));
                }
            } elseif ($paidFlag) {
                //已支付：已拣货，不支持修改地址
                $this->ajaxError("订单已经拣货出库，不支持更改信息");
            } else {
                //未支付：任意修改联系地址、联系方式，不调整其他数据
            }
            //更新order中联系地址 联系人 联系方式信息
            $orderModel = new \Index\Model\OrderModel();
            $orderRes = $orderModel->saveOrder(array("id" => $id), $data);
            //如果是定制品，更新order_info中的联系地址及联系人、联系方式
            foreach ($productList as $one) {
                if ($one['goods_type'] != \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL) {
                    $cids[] = $one['goods_id'];
                }
            }
            if ($cids) {
                $orderInfoModel = new \Index\Model\CardDataModel();
                $orderInfoRes = $orderInfoModel->updateContactData($uid, $cids, $data);
            }
            if ($uhashRes && $orderRes && $orderInfoRes) {
                $orsModel->commit();
                $this->ajaxSuccess("成功修改订单联系信息：" . $id);
            } else {
                $orsModel->rollback();
                $this->ajaxError("同步修改订单\商品信息失败，请重新尝试或联系管理员");
            }
        } else {
            $this->ajaxError("订单异常不存在商品信息，不可更改");
        }
    }

    /**
     * 取消订单
     * 取消订单=》取消订单所有商品=》取消定制品生成pdf及生成
     */
    public function cancelOrder() {
        $id = I("post.id");
        $uid = I("post.uid");
        $orderno = I("post.orderno");
        if (empty($id) || empty($uid) || empty($orderno)) {
            $this->ajaxError("取消失败，id,uid,orderno为必要数据");
        }

        $orderModel = new \Index\Model\OrderModel();
        $orderModel->startTrans();
        $orderRes = $orderModel->saveOrder(array("id" => $id, "uid" => $uid, "orderno" => $orderno), array("status" => \Index\Model\OrderModel::STATUS_CANCEL));
        $orsModel = new \Index\Model\OrderRelationShipModel();
        $orsRes = $orsModel->baseSave(array("uid" => $uid, "orderno" => $orderno, "order_id" => $id), array("status" => array("exp", 'status&' + \Index\Model\OrderRelationShipModel::ORDER_STATUS_PAID)));
        if ($orderRes && $orsRes !== false) {
            $orderModel->commit();
        } else {
            $this->ajaxError("取消订单" . $orderno . "失败，事务同步失败，请重新操作或联系管理员");
            $orderModel->rollback();
        }

        //更新定制品在order_info的取消信息
        $list = $orsModel->baseGet(array("order_id" => $id, "uid" => $uid, "orderno" => $orderno));
        if ($list) {
            foreach ($list as $one) {
                if ($one['goods_type'] != \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL) {
                    (new CardDataModel())->cancelCardData($one['goods_id'], $one['product_id']);
                }
            }
        }
        $this->ajaxSuccess("取消商品" . $orderno . "成功");
    }

    /**
     * 获取各类下拉框数据
     */
    public function getSelectData() {
        $type = I("post.type", "", "strtolower");
        $selectString = "";
        switch ($type) {
            case "from":
                $selectString = ":选择来源;";
                $fromArray = array(1 => "Qing平台", 2 => "淘宝", 3 => "微店");
                foreach ($fromArray as $index => $one) {
                    $selectString .= $index . ":" . $one . ";";
                }
                break;
            case "paytype":
                $selectString = ":选择支付方式;";
                $paytypeArray = array(0 => "未知", 1 => "支付宝", 2 => "微信");
                foreach ($paytypeArray as $index => $one) {
                    $selectString .= $index . ":" . $one . ";";
                }
                break;
            case "goods":
                $selectString = ":商品类型;";
                $goodsTypeArray = array(1 => "普通规格品", 2 => "打印定制品", 3 => "微信书", 4 => "Lomo卡", 5 => "照片卡");
                foreach ($goodsTypeArray as $index => $one) {
                    $selectString .= $index . ":" . $one . ";";
                }
                break;
            case "process":
                $selectString = ":仓储进度;";
                $processArray = array(0 => "未入库", 1 => "已入库", 2 => "已拣货", 3 => "已出库");
                foreach ($processArray as $index => $one) {
                    $selectString .= $index . ":" . $one . ";";
                }
                break;
            case "status":
                $selectString = ":商品状态;";
                $statusArray = array(0 => "已取消", 1 => "未支付", 2 => "已支付并取消", 3 => "已支付");
                foreach ($statusArray as $index => $one) {
                    $selectString .= $index . ":" . $one . ";";
                }
                break;
            case "o_status":
                $selectString = ":订单状态;";
                $statusArray = array(0 => "正常", 1 => "已取消");
                foreach ($statusArray as $index => $one) {
                    $selectString .= $index . ":" . $one . ";";
                }
                break;
            default:
                break;
        }
        echo rtrim($selectString, ";");
        exit;
    }

    /**
     * 通过uid及订单id获取该订单的所有商品信息
     * 订单列表查看订单详情
     */
    public function getOrderProducts() {
        $orderId = I("post.id", "", "trim");
        $uid = I("post.uid", "", "trim");
        $orsModel = new \Index\Model\OrderRelationShipModel();
        $products = $orsModel->baseGet(array("order_id" => $orderId, "uid" => $uid), "*");
        $trHtml = '';
        $processLabels = C("PROCESS_LABLES");
        foreach ($products as $one) {
            if ($one['goods_type'] == \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL) {
                $categoryModel = new \Index\Model\CategoryModel();
                $categoryInfo = $categoryModel->baseFind(array("category_id" => $one['product_id']));
                $productName = $categoryInfo['category_name'];
            } else {
                $productNames = C("PRODUCT_NAMES");
                $productName = $productNames[$one['goods_type']];
            }
            $trHtml .= "<tr><td>" . $one['id'] . "</td><td>" . $one['product_id'] . "</td><td>" . $productName . "</td><td>" . $one['count'] . "</td><td>" . $processLabels[$one['process']] . "</td></tr>";
        }
        $this->ajaxReturn(array("status" => 1, "data" => $trHtml));
    }

    /**
     * 订单商品关系列表
     */
    public function products() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "商品列表", "url" => "/Index/Order/products"),
        );
        $this->assign("menuCrumbs", $menuCrumbs);
        $orderId = I("param.id", '', "trim");
        if ($orderId) {
            $this->assign("order_id", $orderId);
            //{"groupOp":"AND","rules":[{"field":"order_id","op":"eq","data":"12345"}]}
//            $this->assign("filters", json_encode(array("groupOp" => "AND", "rules" => array(array("field" => "order_id", "op" => "eq", "data" => $orderId)))));
        }
        $this->display("products");
    }

    /**
     * 获取订单商品列表数据
     */
    public function getProductListData() {
        $page = I("param.page", 1);
        $rows = I("param.rows", 10);
        $sidx = I("param.sidx", 'create_time');
        $sord = I("param.sord", 'desc');
        //special: 从订单列表跳转过来特殊请求，模拟jqgrid的搜索请求参数
        $orderId = I("get.fromorderid");
        if ($orderId) {
            $_POST['filters'] = json_encode(array("groupOp" => "AND", "rules" => array(array("field" => "order_id", "op" => "eq", "data" => $orderId))));
        }

        $where = $this->getSearchCondition();
//        $field = "*";
        $field = array(
            "id", "order_id", "goods_type", "goods_id", "orderno",
            "count", "product_id", "process", "uhash", "status",
            "uid", "create_time", "update_time",
        );
        $orsModel = new \Index\Model\OrderRelationShipModel();
        if ($sidx && $sord) {
            $order = array($sidx => $sord);
        }
        echo json_encode($orsModel->baseGetPage($where, $field, $page, $rows, $order), TRUE);
        exit;
    }

    /**
     * 商品操作分发入口
     */
    public function productOperation() {
        $oper = I("post.oper", null);
        if (!empty($oper)) {
            switch ($oper) {
                case 'add':
                    $this->ajaxError("未完善" . I("post.oper") . "操作");
                    $this->toAddProduct();
                    break;
                case 'edit':
                    $this->ajaxError("未完善" . I("post.oper") . "操作");
                    $this->toEditProduct();
                    break;
                case 'del':
                    $this->ajaxError("未完善" . I("post.oper") . "操作");
                    $this->toDeleteProduct();
                    break;
                default:
                    $this->ajaxError("未完善" . I("post.oper") . "操作");
                    exit;
                    break;
            }
        }
    }

    /**
     * 取消商品
     * @todo 暂时没有做事务处理，且在处理order_info及优惠券环节处理的不好
     */
    public function cancelProduct() {
        $id = I("post.id");
        $uid = I("post.uid");
        $productId = I("post.product_id");
        if (empty($id) || empty($uid) || empty($productId)) {
            $this->ajaxError("取消失败，id,uid,product_id为必要数据");
        }

        $orsModel = new \Index\Model\OrderRelationShipModel();
        $list = $orsModel->baseFind(array("id" => $id, "uid" => $uid, "product_id" => $productId));
        if ($list) {
            if ($list['status'] & \Index\Model\OrderRelationShipModel::ORDER_STATUS_USE) {
                //保持支付状态同时将有效状态置为0即无效
                $res1 = $orsModel->baseSave(array("id" => $id, "uid" => $uid, "product_id" => $productId), array("status" => ($list['status'] & \Index\Model\OrderRelationShipModel::ORDER_STATUS_PAID)));
                (new CardDataModel())->cancelCardData($list['goods_id'], $productId);
                //$res2  = (new CardDataModel())->cancelCardData($id, $tid);                
            }
        } else {
            $this->ajaxError("取消失败，商品数据异常或不存在商品数据信息");
        }
        $this->ajaxSuccess("取消商品" . $list['product_id'] . "成功");
    }

    /**
     * 重置商品
     * 所有商品都可以重置  如果是普通规格品process默认为1   其他默认为0
     * 如果是定制品 ，重置order_info表数据
     * package可能以orderno存在的记录
     */
    public function resetProduct() {
        $id = I("post.id");
        $uid = I("post.uid");
        $productId = I("post.product_id");
        if (empty($id) || empty($uid) || empty($productId)) {
            $this->ajaxError("重置失败，id,uid,product_id为必要数据");
        }

        $orsModel = new \Index\Model\OrderRelationShipModel();
        $list = $orsModel->baseFind(array("id" => $id, "uid" => $uid, "product_id" => $productId));
        if ($list) {
            $packageRes = $orderInfoRes = true;
            //所有商品都可以重置  如果是普通规格品process默认为1   其他默认为0
            $orsModel->startTrans();
            if ($list['goods_type'] == \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL) {
                $initProcess = \Index\Model\OrderRelationShipModel::PROCESS_PUT_IN;
            } else {
                $initProcess = \Index\Model\OrderRelationShipModel::PROCESS_INIT;
                //如果是定制品 ，重置order_info表数据及package可能以orderno存在的记录
                $orderInfoModel = new \Index\Model\CardDataModel();
                $orderInfoRes = $orderInfoModel->resetOrderInfo($list['goods_id'], $list['product_id']);
            }
            $processRes = $orsModel->baseSave(array("id" => $id, "uid" => $uid, "product_id" => $productId), array("process" => $initProcess));

            $packageModel = new \Index\Model\PackageModel();
            $packageId = $packageModel->getPackageId($list['product_id']);
            if ($packageId) {
                $packageRes = $packageModel->resetPackage($list['product_id']);
            }

            if ($processRes !== false && $orderInfoRes !== false && $packageRes !== false) {
                $orsModel->commit();
                $this->ajaxSuccess("重置商品" . $list['product_id'] . "成功");
            } else {
                $orsModel->rollback();
                $this->ajaxError("重置失败【ors/order_info/package】");
            }
        } else {
            $this->ajaxError("重置失败，商品数据异常或不存在商品数据信息");
        }
    }

    /**
     * 添加普通订单入口页面
     */
    public function addNormal() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "添加普通订单", "url" => "/Index/Order/addNormal"),
        );

        //@todo  商品编号，后期加入前端autocomplete组件
        $list = $this->getAllCategoryId();
        $this->assign("list", $list);
        $this->assign("listjson", json_encode($list));
        $this->assign("menuCrumbs", $menuCrumbs);
        $this->display("addNormal");
    }

    /**
     * 添加普通订单逻辑
     */
    public function toAddNormal() {
        $postData = I("post.");
        foreach ($postData as $key => $value) {
            $$key = trim($value);
        }
        if (!$this->checkOperationPwd($pwd)) {
            $this->ajaxError("操作密码错误，请重新提交");
        }

        if (isset($uprice) && (!is_numeric($uprice) || $uprice > 99999)) {
            $this->ajaxError("单价要求为不大于99999的数字");
        }
        if (empty($name) || empty($phone) || empty($province) || empty($city) || empty($street)) {
            $this->ajaxError("请确认填写连联系人、联系方式及联系地址");
        }
        if ($old_order) {
            //取出订单
            //往订单中插入新的商品
        } else {
            $price = 0;
            $products = html_entity_decode($products);
            $productArray = json_decode($products, true);

            foreach ($productArray as $one) {
                $price += ($one['uprice'] * $one['count']);
            }
            $orderFromArray = array("tb" => \Index\Model\OrderModel::FROM_TAOBAO, "wd" => \Index\Model\OrderModel::FROM_WEIDIAN);
            if (array_key_exists($orderfrom, $orderFromArray)) {
                $dbOrderFrom = $orderFromArray[$orderfrom];
            } else {
                $dbOrderFrom = 0;
            }
            $orderData = array(
                "uid" => self::DEFAULT_SYS_UID,
                'orderno' => $orderfrom . time() . rand(1000, 9999),
                'create_time' => date("Y-m-d H:i:s", time()),
                'update_time' => date("Y-m-d H:i:s", time()),
                'from' => $dbOrderFrom,
                'price' => $price > 0 ? $price : 0,
                'pay_type' => \Index\Model\OrderModel::PAY_TYPE_UNKNOW,
                'paidTime' => date("Y-m-d H:i:s", time()),
                'name' => $name,
                'phone' => $phone,
                'province' => $province,
                'city' => $city,
                'area' => $area,
                'street' => $street,
//                'ext' => $orderfrom,
            );
            $orderModel = new \Index\Model\OrderModel();
            $orderModel->startTrans();
            $orderData['id'] = $orderModel->addOrder($orderData);
            $addAllRes = $this->addProducts($productArray, $orderData, $errorMsg);
            if ($addAllRes && $orderData['id']) {
                $orderModel->commit();
            } else {
                $orderModel->rollback();
                if (!$errorMsg) {
                    $errorMsg = "订单及商品未同时成功写入，数据已经回滚，请重新添加";
                }
                $this->ajaxReturn(array("status" => 0, "data" => $errorMsg));
            }
            $this->ajaxReturn(array("status" => 1, "data" => "下单成功"));
        }
    }

    /**
     * 添加订单商品数据
     * @param type $products
     * @param type $orderData
     * @param string $errorMsg
     * @return boolean
     */
    private function addProducts($products, $orderData, &$errorMsg = '') {
        $orsModel = new \Index\Model\OrderRelationShipModel();
        $uhash = \Index\Model\OrderRelationShipModel::addressMd5(array($orderData['name'], $orderData['phone'], $orderData['province'], $orderData['city'], $orderData['area'], $orderData['street']));
        $datas = array();
        foreach ($products as $productId => $one) {
            $goodsId = $this->getGoodsIdByProductId($productId);
            if ($goodsId) {
                $datas[] = array(
                    "orderno" => $orderData['orderno'],
                    "order_id" => $orderData['id'],
                    "goods_type" => \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL,
                    "goods_id" => $goodsId,
                    "count" => $one['count'],
                    "product_id" => $productId,
                    "process" => \Index\Model\OrderRelationShipModel::PROCESS_PUT_IN,
                    "uhash" => $uhash,
                    "status" => \Index\Model\OrderRelationShipModel::ORDER_STATUS_USE_PAID,
                    "uid" => $orderData['uid'],
                    "create_time" => date("Y-m-d H:i:s", time()),
                    "update_time" => date("Y-m-d H:i:s", time()),
                );
            } else {
                $errorMsg = "没有在仓储品类中找到商家编码：" . $productId;
                return false;
            }
        }
        return $orsModel->baseAddAll($datas);
    }

    /**
     * 添加定制品订单 （Qing内部下单入口）
     */
    public function addCustom() {
        header("Location:" . U("/Index/Admin/newOrder"));
    }

}
