<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Index\Controller;

/**
 * 定制品仓储
 */
class CustomController extends ServiceController {

    /**
     * 页面面包屑第一级文字
     * @var string 
     */
    private $_menuCrumbsFirst = '定制品仓储';

    public function __construct() {
        parent::__construct();
    }

    /**
     * 定制仓储列表
     */
    public function index() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "在仓库定制品列表", "url" => "/Index/Custom/index"),
        );
        $this->assign("menuCrumbs", $menuCrumbs);

        $customModel = new \Index\Model\CustomModel();
        $data = $customModel->baseGet(array("product_id" => array("neq", "")), array("id", "location", "orderno", "product_id", "update_time"), array("id" => "asc"));

        $this->assign('data', $data);
        $this->display("index");
    }

    /**
     * 定制品入库页面
     */
    public function putIn() {
        $sceneData = $this->getSceneData();
        if ($sceneData) {
            $this->assign("sceneData", $sceneData);
        }
        $this->display("putin");
    }

    /**
     * 定制品入库处理
     */
    public function toPutIn() {
        $opUserId = $this->checkOperator(I("post.operator"));
        $data['op_userid'] = $opUserId;
        $data['product_id'] = I("post.product_id", "", "trim");

        $orsModel = new \Index\Model\OrderRelationShipModel();
        $curProductInfo = $orsModel->baseFind(array("product_id" => $data['product_id']));
        $customModel = new \Index\Model\CustomModel();
        $this->isCustomAlloted($curProductInfo, $customModel);

        $hasSameUidUhashProduct = $orsModel->hasSameUidUhashProdut($curProductInfo['uid'], $curProductInfo['uhash']);
//        echo $hasSameUidUhashProduct;exit;
        if ($hasSameUidUhashProduct <= 1) {
            //单一定制品直接走中转区
            $location = "单一定制品中转区";
            $sRes = $orsModel->updateProcess(array("id" => $curProductInfo['id']), \Index\Model\OrderRelationShipModel::PROCESS_PICKED);
        } else {
            //留着拼单分配仓位
            $oneEmptyRecord = $customModel->baseFind(array("product_id" => "", "orderno" => ''), '*', array("id" => "asc"));
            $customModel->startTrans();
            if ($oneEmptyRecord) {
                $location = $this->allotOldLocation($data, $curProductInfo, $oneEmptyRecord, $customModel);
            } else {
                $location = $this->allotNewLocation($data, $curProductInfo, $customModel);
            }
            $sRes = $orsModel->updateProcess(array("id" => $curProductInfo['id']), \Index\Model\OrderRelationShipModel::PROCESS_PUT_IN);
            if ($location && $sRes) {
                $customModel->commit();
            } else {
                $customModel->rollback();
                $this->ajaxError("订单更新事务失败 " . $customModel->getError() . "，请重新尝试或联系管理员[toPutIn]");
//                $this->errorWithScene("订单更新事务失败 " . $customModel->getError() . "，请重新尝试或联系管理员[toPutIn]", U("putin"), 5);
            }
        }
        $this->ajaxSuccess("已分配了仓位：" . $location);
//        $this->setSceneData(array("l" => $location));
//        $this->success("已分配了仓位：" . $location, U("putin"), 5);
    }

    /**
     * 商品编号是否已经分配入库
     * @param array $curProductInfo
     * @param \Index\Model\CustomModel $customModel
     * @return boolean
     */
    private function isCustomAlloted($curProductInfo, $customModel) {
        if ($curProductInfo) {
            $msg = "订单号：" . $curProductInfo['orderno'] . " | 商品编号：" . $curProductInfo['product_id'];
            if (($curProductInfo['status'] & \Index\Model\OrderRelationShipModel::ORDER_STATUS_USE_PAID) != \Index\Model\OrderRelationShipModel::ORDER_STATUS_USE_PAID) {
                $msg = "商品所在订单：" . $curProductInfo['orderno'] . "尚未成功支付或已经取消订单。不予分配入库！";
            } elseif ($curProductInfo['process'] < \Index\Model\OrderRelationShipModel::PROCESS_PUT_IN) {
                return false;
            } elseif ($curProductInfo['process'] <= \Index\Model\OrderRelationShipModel::PROCESS_PICKED) {
                $existsCustom = $customModel->baseFind(array("product_id" => $curProductInfo['product_id']), array("location", "update_time"));
                if ($existsCustom) {
                    $msg .= "于" . date("Y-m-d H:i:s") . "入库到" . $existsCustom['location'] . "。请前往仓位再次确认。";
                } else {
                    $msg .= "可能在单一成品中转区拣货中。";
                }
            } elseif ($curProductInfo['process'] == \Index\Model\OrderRelationShipModel::PROCESS_PUT_OUT) {
                $msg .=" 已经出库。";
            }
        } else {
            $msg = "不存在该商品编号。";
        }
        $this->ajaxError($msg);
//        $this->setSceneData(array("l" => $msg));
//        $this->error($msg, U("putin"), 3);
    }

    /**
     * 分配已有空间
     * @todo  并发时注意读写锁表问题，暂忽略
     * @param array $data
     * @param array $curProductInfo 当前商品信息* 
     * @param array $oneEmptyRecord
     * @param \Index\Model\CustomModel $customModel
     * @return string
     */
    private function allotOldLocation($data, $curProductInfo, $oneEmptyRecord, $customModel) {
        $data['id'] = $oneEmptyRecord['id'];
        $res1 = $customModel->baseSaveById($data);
        $inRecordModel = new \Index\Model\InrecordModel();
        $res2 = $inRecordModel->baseAdd(array(
            "op_userid" => $data['op_userid'],
            "op_time" => time(),
            "type" => \Index\Model\InrecordModel::TYPE_CUSTOM,
            "category_id" => $curProductInfo['product_id'],
            "count" => 1,
            "location" => $oneEmptyRecord['location'],
//            "desc" => $data['desc']
                )
        );
        if ($res1 && $res2) {
            return $oneEmptyRecord['location'];
        } else {
            $customModel->rollback();
            $this->ajaxError("分配更新事务失败 " . $customModel->getError() . $inRecordModel->getError() . "，请重新尝试或联系管理员[allotOldLocation]");
        }
    }

    /**
     * 分配新空间
     * @todo  并发时注意读写锁表问题，暂忽略
     * //加1，层满，层+1 所有层满，寻找下一个货架
     * @param array $data 请求数据数组
     * @param array $curProductInfo 当前商品信息   
     * @param \Index\Model\CustomModel $customModel
     * @return string 
     */
    private function allotNewLocation($data, $curProductInfo, $customModel) {
        //如果没有则查询目前Id最大的分配空间位置
        $lastRecord = $customModel->baseFind(array(), array("location", "shelf", "layer", "col"), array("id" => "desc"));
        $nextLocation = $this->getNextLocation($lastRecord);
        if (is_array($nextLocation)) {
            if ($nextLocation['has']) {
                $addData = array_merge($nextLocation, array("orderno" => $curProductInfo['orderno'], "product_id" => $curProductInfo['product_id']));
                $res1 = $customModel->baseAdd($addData);
                $inRecordModel = new \Index\Model\InrecordModel();
                $res2 = $inRecordModel->baseAdd(array(
                    "op_userid" => $data['op_userid'],
                    "op_time" => time(),
                    "type" => \Index\Model\InrecordModel::TYPE_CUSTOM,
                    "category_id" => $curProductInfo['product_id'],
                    "count" => 1,
                    "location" => $nextLocation,
//                    "desc" => $data['desc']
                        )
                );
                if ($res1 && $res2) {
                    return $nextLocation['location'];
                } else {
                    $customModel->rollback();
                    $this->ajaxError("分配新增事务失败 " . $customModel->getError() . $inRecordModel->getError() . "，请重新尝试或联系管理员[allotNewLocation]");
                }
            } else {
                $this->ajaxError("仓位不足，分配位置：" . $nextLocation['location'] . "不存在");
            }
        } else {
            $this->ajaxError("定制品入库分配失败，请重新尝试或联系管理员【可能由于zone配置导致】");
        }
    }

    /**
     * 根据当前空间位置获取下一个顺序空间位置
     * @param array $curCustomRecord array("location"=>"","shelf"=>"","layer"=>'',"col"=>'')
     * @return boolean|array 
     */
    private function getNextLocation($curCustomRecord) {
        $customZone = C("CUSTOM_ZONE");
        if (empty($customZone)) {
            return false;
        }
        list($curShelf, $curLayer, $curCol) = $this->getCurLocation($curCustomRecord, $customZone);

        $maxLayer = array_pop($customZone[$curShelf]['rows']);
        $maxCol = $customZone[$curShelf]['col'];

        $nextShelf = $curShelf;
        $nextLayer = $curLayer;
        $nextCol = intval($curCol) + 1;
        $hasLocation = true;
        if ($nextCol > $maxCol) {
            $nextCol = 1;
            $nextLayer = chr(ord($curLayer) + 1);
            if (ord($nextLayer) > ord($maxLayer)) {
                if(($nextShelf[1]+1)>9){
                    $nextShelf[0] =chr(ord($nextShelf[0]) + 1);
                    $nextShelf = $nextShelf[0]."0";
//                $nextShelf = chr(ord($curShelf) + 1);
                }else{
                    $nextShelf = $nextShelf[0].($nextShelf[1]+1);
                }
                
                if (array_key_exists($nextShelf, $customZone)) {
                    $nextLayer = array_shift($customZone[$nextShelf]['rows']);
                } else {
                    $hasLocation = false;
                }
            }
        }
        $colLength = strlen($customZone[$nextShelf]['col']);
        $nextCol = str_pad($nextCol, $colLength, "0", STR_PAD_LEFT);
//        $nextLocation  = $nextShelf.$nextLayer.$nextCol;
        return array(
            "has" => $hasLocation, //标识是否空间不足
            "location" => $nextShelf . $nextLayer . $nextCol,
            "shelf" => $nextShelf, //货架编号
            "layer" => $nextLayer, //层
            "col" => $nextCol, //列
        );
    }

    /**
     * 初始化获取当前仓储位置，包含货架编号、层、列
     * @param array $curCustomRecord 已经存在可使用的定制区仓位记录
     * @param array $customZone 定制区zone配置
     * @return array
     */
    private function getCurLocation($curCustomRecord, $customZone) {
        if (!empty($curCustomRecord)) {
            $curShelf = $curCustomRecord['shelf'];
            $curLayer = $curCustomRecord['layer'];
            $curCol = $curCustomRecord['col'];
        } else {
            $shelfs = array_keys($customZone);
            $curShelf = array_shift($shelfs);
            $curLayer = array_shift($customZone[$curShelf]['rows']);
            $curCol = str_pad(0, strlen($customZone[$curShelf]['col']), "0", STR_PAD_LEFT);
        }
        return array($curShelf, $curLayer, $curCol);
    }

    /**
     * 查看商品仓储进度详情
     */
    public function viewProcess() {
        if ($re = $this->accessFrequencyLimit()) {
            $this->ajaxError("由于频率限制，请再过" . $re . "秒后再申请查询");
        }        
        if (!isset($_POST['operator'])) {
            $menuCrumbs = array(
                "first" => $this->_menuCrumbsFirst,
                "second" => array("menuName" => "定制品进度查询", "url" => "/Index/Custom/viewprocess"),
            );
            $this->assign("menuCrumbs", $menuCrumbs);
            $this->display("viewprocess");
        } else {
            $template = '';
            $productId = I("post.productId", "", "trim");
            $list = array();
            $theNumForExpress = $productId;
            if (strlen($productId) > 10) {
                //ors信息，process == 0 未入库
                //process小于3未出库，通过入库记录获取入库时间、入库操作人、入库位置
                //process == 2 通过拣货单表获取拣货记录时间、拣货单号、操作人
                //process == 3 已经出库，通过出库记录表获取出库时间、出库操作人，通过包裹列表获取包裹号和电子面单号
                $orsModel = new \Index\Model\OrderRelationShipModel();
                $productList = $orsModel->baseFind(array("product_id" => $productId, "goods_type" => array("neq", \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL)));
                if ($productList) {
                    if ($productList['process'] == 0) {
                        $list['process'] = "未入库";
                    } else {
                        if ($productList['process'] >= 1) {
                            $list['process'] = "已入库";
                            $inRecordModel = new \Index\Model\InrecordModel();
                            $record = $inRecordModel->baseFind(array('category_id' => $productId, "type" => \Index\Model\InrecordModel::TYPE_CUSTOM),"*",array("id"=>"desc"));
                            if ($record) {
                                $list['location'] = $record['location'];
                                $list['inTime'] = date("Y-m-d H:i:s", $record['op_time']);
                                $list['inCount'] = $record['count'];
                                $list['inOpUid'] = $record['op_userid'];
                            }
                            //没有仓储位置的默认为中转区
                        }
                        if ($productList['process'] >= 2) {
                            $list['process'] = "已拣货";
                            $pickModel = new \Index\Model\PicklistModel();
                            $pickList = $pickModel->baseFind(array("orderno" => $productId, "is_del" => 0),"*",array("id"=>"desc"));
                            if ($pickList) {
                                $theNumForExpress = $list['pickId'] = $pickList['pick_id'];
                                $list['pickOpUid'] = $pickList['op_userid'];
                                $list['pickTime'] = date("Y-m-d H:i:s", $pickList['create_time']);
                            }
                            //没有拣货单的默认为中转区
                        }
                        if ($productList['process'] >= 3) {
                            $list['process'] = "已出库";
                            $packageModel = new \Index\Model\PackageModel();
                            $packageList = $packageModel->getPackages($theNumForExpress);
                            if ($packageList) {
                                foreach ($packageList as $one) {
                                    $list['packageIds'] += $one['id'] . ";";
                                    $list['mailno'] += $one['mailno'] . ";";
//                                    $list['outTime'] = $one['date'];
                                }
                                $list['packageIds'] = rtrim($list['packageIds'], ";");
                                $list['mailno'] = rtrim($list['mailno'], ";");

                                $outRecordModel = new \Index\Model\OutrecordModel();
                                $outRecord = $outRecordModel->baseFind(array("category_id" => $productId, "type" => array("eq", \Index\Model\OutrecordModel::TYPE_CUSTOM)),"*",array("id"=>"desc"));
                                if ($outRecord) {
                                    $list['outOpUid'] = $outRecord['op_userid'];
                                    $list['outTime'] = date("Y-m-d H:i:s", $outRecord['op_time']);
                                    $list['outCount'] = $outRecord['count'];
                                }
                            }
                        }
                    }
                    $template = "<tr>"
                            . "<td>" . $productId . "</td><td>" . $list['process'] . "</td><td>" . $list['inTime'] . "</td><td>" . $list['location'] . "</td>"
                            . "<td>" . $list['inOpUid'] . "</td><td>" . $list['pickTime'] . "</td><td>" . $list['pickId'] . "</td><td>" . $list['pickOpUid'] . "</td>"
                            . "<td>" . $list['outTime'] . "</td><td>" . $list['mailno'] . "</td><td>" . $list['outOpUid'] . "</td>"
                            . "</tr>";
                }
            }
            $this->ajaxSuccess($template);
        }
    }

}
