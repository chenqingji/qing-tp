<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Index\Controller;

use Index\Controller\WrapController;

/**
 * 提供给线下操作服务入口
 */
class ServiceController extends WrapController {

    /**
     * 同一个会话的上一次访问时间，防止不必要的刷新
     */
    const SESSION_LAST_ACCESS_TIME = 'last_access_time_';

    /**
     * 同一个会话访问冷却时间 //seconds
     */
    const ACCESS_COOLING_TIME = 30;

    /**
     * authcode加解密key molikuaiyin-print
     */
    const AUTHCODE_PRINT_KEY = 'molikuaiyin-print';

    /**
     * 缓存拣货单信息路径 /tmp/cache_pick_list/
     */
    const CACHE_PICKLIST_PATH = "/tmp/cache_pick_list/";
    /**
     * 打印拣货单形式
     * @var type 
     * default：默认打印可打印的10个拣货单 正常情况
     * pickid：根据pickid打印  只为解决再次拣货
     * orderno：根据订单号打印 只为解决提前拣货
     */
    protected $_pickForm = self::PICK_FORM_DEFAULT;
    /**
     * 模式 默认 default
     */
    const PICK_FORM_DEFAULT = 'default';

    /**
     * 模式 拣货单号 pickid
     */
    const PICK_FORM_PICKID = 'pickid';

    /**
     * 模式 订单号  orderno
     */
    const PICK_FORM_ORDERNO = 'orderno';

    /**
     * 默认 韵达 YUNDA
     * @var type 
     */
    protected $mailType = "YUNDA";

    /**
     * 菜鸟打印 appkey
     * @var type 
     */
    protected $appkey = "23288990";

    /**
     * 菜鸟打印 secret
     * @var type 
     */
    protected $secret = "c6e5214a3c0d7ce81e61020cae1b36f3";

    /**
     * 星罗网络科技的uid    
     * @var type 
     */
    protected $sellid = "1835837568";

    public function __construct() {
        parent::__construct();
    }

    /**
     * index
     */
    public function index() {
        $this->display("index");
    }

    /**
     * 检查并获取操作人id
     * @param string $operator 用户条形码存储复杂字符串
     * @return int 操作人对应的用户id
     */
    protected function checkOperator($operator) {
        return 1;
    }

    /**
     * 访问频率限制
     * 不限制访问时返回false，限制访问时返回还有多长时间可以访问
     * @return boolean|int 
     */
    protected function accessFrequencyLimit() {
        $actionId = __CONTROLLER__ . __ACTION__;
        $lastAccessTime = session(self::SESSION_LAST_ACCESS_TIME . $actionId);
        $curTime = time();
        if (($curTime - $lastAccessTime) < self::ACCESS_COOLING_TIME) {
            $waitTime = ($lastAccessTime + self::ACCESS_COOLING_TIME) - $curTime;
            return $waitTime;
        }
        session(self::SESSION_LAST_ACCESS_TIME . $actionId, $curTime);
        return false;
    }

    /**
     * ajax 请求错误输出
     * @param type $data
     * @param type $status
     */
    protected function ajaxError($data, $status = 0) {
        $this->ajaxReturn(array("status" => $status, "data" => $data));
    }

    /**
     * ajax请求成功输出
     * @param type $data
     * @param type $status
     */
    protected function ajaxSuccess($data, $status = 1) {
        $this->ajaxReturn(array("status" => $status, "data" => $data));
    }

    /**
     * 缓存拣货单信息
     * 一个文件对应一个拣货单信息  文件名为拣货单号
     * @param array $printList
     */
    protected function setPickListToCache($printList) {
        $cachePath = self::CACHE_PICKLIST_PATH;
        if (!file_exists($cachePath)) {
            mkdir($cachePath);
        }
        if (file_exists($cachePath)) {
            foreach ($printList as $one) {
                if (empty($one['i'])) {
                    continue;
                }
                $cacheFile = $cachePath . $one['i'];
                $encryptOneString = authcode(json_encode($one), "encode", self::AUTHCODE_PRINT_KEY);
                file_put_contents($cacheFile, $encryptOneString);
            }
        }
    }

    /**
     * 从缓存中获取拣货单信息
     * @param type $pickId
     * @return array 
     */
    protected function getPickListFromCache($pickId) {
        $cacheFile = self::CACHE_PICKLIST_PATH . $pickId;
        $pickList = array();
        if (is_file($cacheFile)) {
            $cacheInfo = file_get_contents($cacheFile);
            $pickList = json_decode(authcode($cacheInfo, "DECODE", self::AUTHCODE_PRINT_KEY), true);
        }
        return $pickList;
    }

    /**
     * 删除拣货单信息缓存
     * @param type $pickId
     */
    protected function deletePickListFromCache($pickId) {
        $cacheFile = self::CACHE_PICKLIST_PATH . $pickId;
        if (is_file($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * 通过pickId拣货单号获取拣货单-订单-产品信息
     * @param type $pickId
     */
    protected function getPickListByPickId($pickId) {
        $pickList = $this->getPickListFromCache($pickId);
        if (empty($pickList)) {
            $pickList = array();
            $pickListModel = new \Index\Model\PicklistModel();
            $pickRecords = $pickListModel->baseGet(array("pick_id" => $pickId, "is_del" => 0), array("orderno"));
            foreach ($pickRecords as $one) {
                $orderNos[] = $one['orderno'];
            }
            $orsModel = new \Index\Model\OrderRelationShipModel();
            $sameUidUhashProducts = $orsModel->baseGet(array("orderno" => array("in", $orderNos), "count" => array("gt", 0)));
            if ($productList = $this->classifiedProduct($sameUidUhashProducts)) {
                $productList['h'] = $this->getPickListHead($orderNos[0]);
                $productList['i'] = $pickId;
                $pickList[] = $productList;
            }
            $this->setPickListToCache($pickList);
        }
        return $pickList;
    }

    /**
     * 检查订单process进度  0未入库 1已入库 2已拣货 3已出库
     * @param string $orderno
     * @param \Index\Model\OrderRelationShipModel $orsModel
     */
    protected function checkOrderProcess($orderno, $orsModel) {
        $errorMsg = '';
        $pickProducts = $orsModel->baseGet(array("orderno" => $orderno));
        if ($pickProducts) {
            $names = C("PRODUCT_NAMES");
            foreach ($pickProducts as $one) {
                switch ($one['process']) {
                    case 0:$errorMsg .= "订单" . $orderno . " 商品" . $one['product_id'] . $names[$one['goods_type']] . "尚未入库；";
                        break;
                    case 2:$errorMsg .= "订单" . $orderno . " 商品" . $one['product_id'] . $names[$one['goods_type']] . "已经打印拣货清单；";
                        break;
                    case 3:$errorMsg .= "订单" . $orderno . " 商品" . $one['product_id'] . $names[$one['goods_type']] . "已经出库；";
                        break;
                    default:
                        break;
                }
            }
        } else {
            $errorMsg = "订单" . $orderno . "不存在商品或异常";
        }
        if ($errorMsg) {
            $this->ajaxError($errorMsg);
        }
        return $pickProducts;
    }

    /**
     * 获取已支付未拣货与指定商品记录相同uid及uhash的商品记录
     * @param array $pickProduct
     * @param \Index\Model\OrderRelationShipModel $orsModel
     * @return array
     */
    protected function getTheSameUidUhashProducts($pickProduct, $orsModel) {
        $where = array("process" => array("lt", \Index\Model\OrderRelationShipModel::PROCESS_PICKED), "uhash" => $pickProduct['uhash'], "uid" => $pickProduct['uid'], "status" => array("exp", "&0x03=0x03"), "count" => array("gt", 0));
        $sameUidUhashProducts = $orsModel->baseGet($where, "*", array("create_time"));
        return $sameUidUhashProducts;
    }

    /**
     * 根据订单信息，归类拼单后的商品信息（类型、数量等）
     * @param array $sameUidUhashProducts order_relationship 记录
     * @return boolean|array
     * array(
      "o"=>array('cid'=>"orderno"...),
      "h"=>array("name"=>'aaa',"phone"=>'aaa',"from_app"=>'aa',"addr"=>"aa"),
      "p"=>array("xc000001"=>array("goods_type"=>'aa',"name"=>'aa',"location"=>"aa","count"=>123)...)
     */
    protected function classifiedProduct($sameUidUhashProducts) {
        $productList = array();
        $orderNos = array();
        foreach ($sameUidUhashProducts as $one) {
            //单独为拣货单号模式打印放开限制，商品任何进度下、任何时间都可以打印
            if (($this->_pickForm != self::PICK_FORM_PICKID)) {
                if ($one['process'] != \Index\Model\OrderRelationShipModel::PROCESS_PUT_IN) {
                    return false;
                }
                if (strtotime($pickProduct['update_time']) > ($currentTime - C("PICK_COOLING_TIME"))) {
                    return false;
                }
            }
            $orderNos[] = $one['orderno'];
            $productId = $one['product_id'];
            $productList[$productId]['goods_type'] = $one['goods_type'];
            if ($one['goods_type'] == \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL) {
                $productList[$productId]['count'] += $one['count'];
            } else {
                $productList[$productId]['count'] += 1; //定制品统一数量为1
            }
        }
        foreach ($productList as $productId => $one) {
            $productInfo = $this->getProductInfo($one['goods_type'], $productId);
            $productList[$productId] = array_merge($productList[$productId], $productInfo);
            $this->warningCurCount($productId);
        }
        return array("o" => array_unique($orderNos), "p" => $productList);
    }

    /**
     * 获取拣货单清单头部信息
     * @param string $orderNo 订单号
     * @return array
     */
    protected function getPickListHead($orderNo) {
        $head = array();
        $orderModel = new \Index\Model\OrderModel();
        $orderInfo = $orderModel->findOrder(array("orderno" => $orderNo), array("name", "phone", "province", "city", "area", "street", "from"));
        if ($orderInfo) {
            $head = array(
                "name" => $orderInfo['name'],
                "phone" => $orderInfo['phone'],
                "from_app" => $this->getFromAppName($orderInfo['from']),
                'addr' => $orderInfo['province'] . $orderInfo['city'] . $orderInfo['area'] . $orderInfo['street']
            );
        }
        return $head;
    }

    /**
     * 获取商品信息 目前只要name和location（货架号或仓储位置）
     * @param type $goodsType 订单类型
     * @param type $productId 商品编号（品类编号）
     * @return array array("name"=>'',"location"=>'')
     */
    protected function getProductInfo($goodsType, $productId) {
        $names = C("PRODUCT_NAMES");
        if ($goodsType == \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL) {
            $categoryModel = new \Index\Model\CategoryModel();
            $info = $categoryModel->baseFind(array('category_id' => $productId), array("category_name" => "name", "shelf_no" => "location"));
        } else {
            $customModel = new \Index\Model\CustomModel();
            $info = $customModel->baseFind(array("product_id" => $productId), array("location"));
            $info['name'] = $names[$goodsType];
        }
        return $info;
    }

    /**
     * 获取来源平台名称
     * @param string $fromApp 1 2 3 4
     * @return string
     */
    protected function getFromAppName($fromApp) {
        $names = array(1 => "魔力快印", 2 => "淘宝", 3 => "微店", 4 => "京东");
        if (!array_key_exists($fromApp, $names)) {
            $fromApp = 1;
        }
        return $names[$fromApp];
    }

    /**
     * 对普通品类库存检查及预警通知
     * CUR_LEAST_COUNT 最少预警库存
     * @param string $productId 商品品类id
     */
    protected function warningCurCount($productId) {
        return;
        $outRecordModel = new \Index\Model\OutrecordModel();
        $categoryInfo = $outRecordModel->baseFind(array("category_id" => $productId));
        if ($categoryInfo['cur_count'] <= C("CUR_LEAST_COUNT")) {
            //@todo 发出库存预警  邮件  短信  微信推送等
        }
    }

}
