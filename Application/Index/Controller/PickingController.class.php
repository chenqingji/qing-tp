<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Index\Controller;

use Index\Controller\ServiceController;

/**
 * 打印拣货单服务
 * 点击打印最新拣货单提交，跳转并渲染拣货单表格页面，自动检查数据完整性，并调用打印机打印
 */
class PickingController extends ServiceController {

    /**
     * 用户id
     * @var type 
     */
    private $_op_userid = '';

    /**
     * 拣货清单 一级菜单
     * @var type 
     */
    private $_menuCrumbsFirst = "拣货清单";

    public function __construct() {
        parent::__construct();
    }

    /**
     * 拣货单列表
     */
    public function lists() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "拣货单列表", "url" => "/Index/Picking/lists"),
        );
        $this->assign("menuCrumbs", $menuCrumbs);

        $pickModel = new \Index\Model\PicklistModel();
        $list = $pickModel->baseGet(array("is_del" => 0), "*", array("id" => "desc"), 50);
        $this->assign("data", $list);
        $this->display("lists");
    }

    /**
     * 打印拣货单入口
     */
    public function index() {
        $this->assign("cainiao_appkey", $this->appkey);
        $this->assign("cainiao_sellid", $this->sellid);
        $this->assign("msg", $this->getSceneData());
        $this->assign("moliLogo", "http://" . I("server.SERVER_NAME") . "/Public/Image/logo.png");
        $this->display("index");
    }

    /**
     * 同获取拣货单数据 拼单并打印拣货单
     */
    public function ajaxToPrint() {
        $this->toPrint();
    }

    /**
     * 获取拣货单数据(含拼单) 前端打印拣货单
     */
    public function toPrint() {
        $operator = I("post.operator");
        if (!$operator) {
            $this->ajaxError("请扫描工号");
        }
        $this->_op_userid = $this->checkOperator($operator);
        $printList = $this->getPrintOrder();
        if (empty($printList)) {
            $this->ajaxError("没有可打印的已入库订单。");
        }
        $this->setPickListToCache($printList);
        $this->ajaxSuccess($printList);
    }

    /**
     * 生成拣货单号
     * @param int $orderno 
     * @return string
     */
    private function generatePickId($orderno = '') {
        return "p" . date("Ymd") . rand(10000, 99999);
    }

    /**
     * 获取一次拣货单信息（含拼单）
     * 优先指定拣货单号，后订单号
     * @return array |false array(array(
      "i"=>"p2016060612345",
      "o"=>array('0'=>"orderno"...),
      "h"=>array("name"=>'aaa',"phone"=>'aaa',"from_app"=>'aa',"addr"=>"aa"),
      "p"=>array("xc000001"=>array("goods_type"=>'aa',"name"=>'aa',"location"=>"aa","count"=>123)...)
      )...)
     */
    private function getPrintOrder() {
        //姓名、手机号、来源、收货地址 - $headList
        //商品清单（产品编号、产品名称、货架号、数量） - $productList
        $pickId = I("post.pickId", '', 'trim'); //指定拣货单号
        $orderno = I("post.orderno", '', "trim"); //指定订单
        $orsModel = new \Index\Model\OrderRelationShipModel();
        $pickList = array();
        if ($pickId) {
            $this->_pickForm = self::PICK_FORM_PICKID;
            if (strpos($pickId, 'p') === 0) {
                $pickList[] = $this->getPickListByPickId($pickId);
            }
        } elseif ($orderno) {
            $this->_pickForm = self::PICK_FORM_ORDERNO;
            $sameUidUhashProducts = $this->checkOrderProcess($orderno, $orsModel);
            if ($productList = $this->classifiedProduct($sameUidUhashProducts)) {
                $productList['h'] = $this->getPickListHead($orderno);
                $productList['i'] = $this->generatePickId();
                $pickList[] = $productList;
            }
        } else {
            $limit = 100;
            $currentTime = time();
            $latestPickProducts = $orsModel->baseGet(array("process" => \Index\Model\OrderRelationShipModel::PROCESS_PUT_IN, "status" => array("exp", "&0x03=0x03"), "count" => array("gt", 0)), "*", array("create_time"), $limit);
            $pickNum = 0;
            foreach ($latestPickProducts as $pickProduct) {
                $productList = array();
                //入库与上架时间约定间隔pickCoolingTime分钟
                if ($pickProduct && strtotime($pickProduct['update_time']) < ($currentTime - C("PICK_COOLING_TIME"))) {
                    $sameUidUhashProducts = $this->getTheSameUidUhashProducts($pickProduct, $orsModel);
                    if ($productList = $this->classifiedProduct($sameUidUhashProducts)) {
                        $productList['h'] = $this->getPickListHead($pickProduct['orderno']);
                        $productList['i'] = $this->generatePickId();
                        if (!$this->addToPickList($productList)) {
                            continue;
                        }
                        $pickList[] = $productList;
                        $pickNum++;
                        if ($pickNum >= C("PICK_LIST_MAX_COUNT")) {
                            break;
                        }
                    } else {
                        continue;
                    }
                }
            }
        }
        return $pickList;
    }

    /**
     * 写入拣货单表并更新商品进度process为拣货
     * @param array $productList 详见getPrintOrder中$productList的定义
     * @return boolean
     */
    private function addToPickList($productList) {
        $pickListModel = new \Index\Model\PicklistModel();
        $pickListModel->markIsDel($productList['o']);
        $curtime = time();
        foreach ($productList['o'] as $oneOrderNo) {
            $dataList[] = array("op_userid" => $this->_op_userid, "pick_id" => $productList['i'], 'orderno' => $oneOrderNo, "create_time" => $curtime, "update_time" => $curtime);
        }
        if ($pickListModel->baseAddAll($dataList)) {
            if ($productList['o']) {
                $orsModel = new \Index\Model\OrderRelationShipModel();
                $orsModel->updateProcess(array("orderno" => array("in", $productList['o'])), \Index\Model\OrderRelationShipModel::PROCESS_PICKED);
            }
            return true;
        }
        return false;
    }


}
