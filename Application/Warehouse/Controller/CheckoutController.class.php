<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Warehouse\Controller;

use Warehouse\Controller\ServiceController;

/**
 * Description of CheckoutController
 * 检查及预警服务
 * @author jm
 */
class CheckoutController extends ServiceController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * 校验拣货单及实际拣货物品
     * 扫描拣货单条形码、扫描所有拣货商品条形码，比对各种品类商品数量是否一致
     * 基础规范：支持的快递公司cp_code：顺丰(SF)、EMS(标准快递:EMS; 经济快件:EYB)、宅 急 送(Z JS)、圆 通(YTO)、
      中通(ZTO)、百 世 汇 通(HTKY)、优 速(UC)、申 通(STO)、天 天 快 递 (TTKDEX)、全 峰 (QFKD)、快 捷(FAST)、
      邮政小包(POSTB)、国通(GTO)、韵达 (YUNDA)、德邦快递（DBKD）
     */
    public function index() {
        $packageModel = new \Index\Model\PackageModel();
        $todayPackNum = $packageModel->getTodayPackageCount();
        $printCfg = array(
            'top' => "2mm",
            'left' => 0,
            'width' => "100mm",
            'height' => "180mm",
            'task_name' => "菜鸟电子面单打印任务",
            'appkey' => $this->appkey,
            'seller_id' => $this->sellid,
            'cp_code' => $this->mailType,
            'product_type' => "标准快件",
            'send_name' => "魔力快印官方店",
            'send_phone' => "15960812280",
            'shipping_address' => "福建省厦门市海沧区海沧东孚浦头路9号",
            'ext_send_date' => date("Y-m-d", time()),
            'ext_sf_biz_type' => "标准快件",
            'shipping_address_city' => "厦门市",
        );

        $this->assign("printCfg", json_encode($printCfg));
        $this->assign("resyuming", RES_YUMING);
        $this->assign("todayPackNum", $todayPackNum);
        $this->display("print");
//        $this->display("index");
    }

    /**
     * 获取拣货单数据信息
     */
    public function toGetPickList() {
        $pickId = I("post.pick_id");
        $this->_pickForm = self::PICK_FORM_PICKID;
        if ($pickId) {
            if ($pickList = $this->getPickListByPickId($pickId)) {
                $pickList = $pickList['p'];
//                $pickList['xc123123'] = array("count"=>2);
                $this->ajaxSuccess($pickList);
            }
        }
        $this->ajaxError($pickId . "拣货单号异常或不存在拣货单");
    }

    /**
     * 主动检查商品状态、空闲仓位、普通品库存等仓储状态信息页面
     */
    public function checkAll() {
        $this->display("checkall");
    }

    /**
     * 检查定制区域长期空闲的仓位及已入库一定时间内未出库记录
     */
    public function toCheckCustom() {
        if ($re = $this->accessFrequencyLimit()) {
            $this->ajaxError("由于频率限制，请再过" . $re . "秒后再申请检查");
        }
        $expiredTime = time() - (24 * 3600);
        $sevenExpiredTime = time() - (7 * 24 * 3600);

        $customMadeModel = new \Warehouse\Model\CustomModel();
        $customLoctions = $customMadeModel->baseGet(array("update_time" => array("between", array($sevenExpiredTime, $expiredTime))));
        foreach ($customLoctions as $key => $value) {
            $value['update_time'] = date("Y-m-d H:i:s", $value['update_time']);
            $customLoctions[$key] = $value;
        }
        $this->ajaxSuccess($customLoctions);
    }

    /**
     * 检查普通品类库存情况，显示低于库存预警值的品类信息
     */
    public function toCheckNormal() {
        if ($re = $this->accessFrequencyLimit()) {
            $this->ajaxError("由于频率限制，请再过" . $re . "秒后再申请检查");
        }
        $categoryModel = new \Warehouse\Model\CategoryModel();
        $categoryInfo = $categoryModel->baseGet(array("cur_count" => array("elt", C('CUR_LEAST_COUNT'))));
        foreach ($categoryInfo as $key => $value) {
            $value['create_time'] = date("Y-m-d H:i:s", $value['create_time']);
            $value['update_time'] = date("Y-m-d H:i:s", $value['update_time']);
            $categoryInfo[$key] = $value;
        }
        $this->ajaxSuccess($categoryInfo);
    }

    /**
     * 检查有效已支付，但未出库的商品信息
     */
    public function toCheckProduct() {
        if ($re = $this->accessFrequencyLimit()) {
            $this->ajaxError("由于频率限制，请再过" . $re . "秒后再申请检查");
        }
        $expiredTime = time() - (24 * 3600);
        $expiredDate = date("Y-m-d H:i:s", $expiredTime);
        $sevenExpiredTime = time() - (7 * 24 * 3600);
        $sevenExpiredDate = date("Y-m-d H:i:s", $sevenExpiredTime);

        $orsModel = new \Index\Model\OrderRelationShipModel();
        $products = $orsModel->baseGet(array("update_time" => array("between", array($sevenExpiredDate, $expiredDate)), "status" => array("exp", "&0x03=0x03"), "process" => array("lt", \Index\Model\OrderRelationShipModel::PROCESS_PUT_OUT)));
        $processInfo = array(0 => "未入库", 1 => "已入库", 2 => "已拣货", 3 => "已出库");
        $goodsType = array(1 => "普通规格品", 2 => "打印定制", 3 => "微信书", 4 => "Lomo卡", 5 => "照片卡");
        foreach ($products as $key => $value) {
            $value['goods_type'] = $goodsType[$value['goods_type']];
            $value['process'] = $processInfo[$value['process']];
            $products[$key] = $value;
        }
        $this->ajaxSuccess($products);
    }

}
