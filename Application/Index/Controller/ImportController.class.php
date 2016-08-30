<?php

namespace Index\Controller;

class ImportController extends WrapController {

    /**
     * 上传根目录
     */
    const UPLOAD_ROOT_PATH = '/tmp/';

    /**
     * 上传相对根目录路径
     */
    const UPLOAD_SAVE_PATH = './orderupload/';

    /**
     * 淘宝订单
     */
    const TYPE_TAOBAO = 'tb';

    /**
     * 微小店订单
     */
    const TYPE_WEIXIAODIAN = 'wd';

//    /**
//     * 第三方订单导入到订单中心前缀
//     */
//    const THIRD_ORDER_PREFIX = "st";

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

    public function index() {
        $this->import();
    }

    /**
     * 导入第三方订单页面
     */
    public function import() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "导入第三方订单", "url" => "/Index/Order/import"),
        );
        $this->assign("menuCrumbs", $menuCrumbs);
        $this->display("import");
    }

    /**
     * 上传文件 一次一个 ajax
     */
    public function toUpload() {
        $uploadPath = self::UPLOAD_ROOT_PATH . self::UPLOAD_SAVE_PATH;
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath);
        }
        try {
            $config = array(
                "maxSize" => 5242880,
                "exts" => array('csv', 'xls'),
                "rootPath" => self::UPLOAD_ROOT_PATH,
                "savePath" => self::UPLOAD_SAVE_PATH,
                'replace' => true,
                'saveName' => '',
            );
            $upload = new \Think\Upload($config, "local"); // 实例化上传类
            // 上传文件 
            $info = $upload->upload();
//            file_put_contents($uploadPath . "upload.log", print_r($info, true), FILE_APPEND);

            if (!$info) {
                // 上传错误提示错误信息   
                $this->ajaxReturn(array("status" => 0, "msg" => $upload->getError()));
//                $this->error($upload->getError());
            } else {
                // 上传成功 获取上传文件信息    ./orderupload/2016-08-01/淘宝订单报表.csv
                $this->ajaxReturn(array("status" => 1, "msg" => $info['orderfile']['savepath'] . $info['orderfile']['savename']));
            }
        } catch (\Exception $e) {
            $this->ajaxReturn(array("status" => 0, "msg" => $e->getMessage()));
        }
    }

    /**
     * 读取csv文件数据，并导入订单数据
     */
    public function toImport() {
        $importName = I("post.importType");
        $fileDir = self::UPLOAD_ROOT_PATH . self::UPLOAD_SAVE_PATH . date("Y-m-d", time());
        if ($importName == self::TYPE_TAOBAO) {
            $fileList = scandir($fileDir);
            if (!in_array("tb1.csv", $fileList)) {
                $this->error("未上传淘宝订单报表：tb1.csv", U("import"));
            } elseif (!in_array("tb2.csv", $fileList)) {
                $this->error("未上传淘宝宝贝报表：tb2.csv", U("import"));
            }
            $this->dealTbOrderCsv();
        } elseif ($importName == self::TYPE_WEIXIAODIAN) {
            $this->dealWxdOrderCsv();
        } else {
            
        }
    }

//    public function test() {
//        $this->dealTbOrderCsv();
//    }

    /**
     * 处理导入的tb订单及宝贝csv文件数据
     */
    private function dealTbOrderCsv() {
        $productRows = $this->getTbProductRows();
        $fileDir = self::UPLOAD_ROOT_PATH . self::UPLOAD_SAVE_PATH . date("Y-m-d", time()) . "/";
        $orderData = array();
        $row = 0;
        $handle = fopen($fileDir . "tb1.csv", "r");
        $errorData = array();
        while ($data = fgetcsv($handle, 1000, ",")) {
            $row++;
            if ($row == 1) {
                continue;
            }
            $data = eval('return ' . iconv('gbk', 'utf-8', var_export($data, true)) . ';');

            $orderModel = new \Index\Model\OrderModel();
            $data[0] = number_format($data[0], 0, '', '');
            $orderNo = self::TYPE_TAOBAO . $data[0];
            $orderExists = $orderModel->findOrder(array('orderno' => $orderNo), array("id"));
            if ($orderExists) {
                $errorData[] = array('d' => $data[0], "e" => "订单已经存在");
                continue;
            }

            $uid = $this->getUid($data[1], self::TYPE_TAOBAO);
            if (!$uid) {
                $errorData[] = array('d' => $data[0], "e" => "无法生成用户id");
                continue;
            }

            $address = (!empty(trim($data[36]))) ? trim($data[36]) : trim($data[13]);
            list($province, $city, $area, $street) = explode(" ", $address,4);
            $orderData = array(
                "uid" => $uid,
                "orderno" => $orderNo,
                "create_time" => date("Y-m-d H:i:s", time()),
                "update_time" => date("Y-m-d H:i:s", time()),
                "from" => \Index\Model\OrderModel::FROM_TAOBAO,
                "price" => trim($data[8]),
                "pay_type" => \Index\Model\OrderModel::PAY_TYPE_ALI,
                "paidTime" => trim($data[18]),
                "name" => trim($data[12]),
                "phone" => trim($data[16]),
                "province" => $province,
                "city" => $city,
                "area" => $area,
                "street" => $street,
                "ext" => trim($data[0]),
            );

            $orderModel->startTrans();
            $orderData['id'] = $orderModel->addOrder($orderData);
            $pRes = $this->dealTbProductCsv($productRows[$data[0]], $orderData, $errorMsg);
            if ($pRes && $orderData['id']) {
                $orderModel->commit();
            } else {
                $orderModel->rollback();
                if (!$pRes) {
                    $errorData[] = array('d' => $data[0], "e" => "处理淘宝宝贝异常：" . $errorMsg);
                    continue;
                }
                $errorData[] = array('d' => $data[0], "e" => "订单及商品未同时导入成功，数据已经回滚，请重新导入");
                continue;
            }
//            if ($row == 15) {
//                break;
//            }
        }
        $errMsg = '';
        if ($errorData) {
            foreach ($errorData as $err) {
                $errMsg .= "<br />" . $err['d'] . ":" . $err['e'] . "<br />";
            }
        }
        fclose($handle);
        unlink($fileDir . "tb1.csv");
        unlink($fileDir . "tb2.csv");
        $this->success("导入结束 " . $errMsg, U("index"), 20);
    }

    /**
     * 获取导入的tb宝贝数据
     * @return array array("tb订单编号1"=>array(array("a","b"),array("c",'d')),"tb订单编号2"=>array(array("a","b"),array("c",'d')))
     */
    private function getTbProductRows() {
        $fileDir = self::UPLOAD_ROOT_PATH . self::UPLOAD_SAVE_PATH . date("Y-m-d", time()) . "/";
        $row = 0;
        $handle = fopen($fileDir . "tb2.csv", "r");
        $products = array();
        while ($data = fgetcsv($handle, 1000, ',')) {
            $row++;
            if ($row == 1) {
                continue;
            }
            $data[0] = number_format($data[0], 0, "", "");
            $products[$data[0]][] = eval('return ' . iconv('gbk', 'utf-8', var_export($data, true)) . ';');
        }
        return $products;
    }

    /**
     * 批量处理某个tb订单下的所有商品数据
     * @param array $rows 商品记录 
     * @param array $orderData 订单数据
     * @return int|false
     */
    private function dealTbProductCsv($rows, $orderData, &$errorMsg = '') {
        $orsModel = new \Index\Model\OrderRelationShipModel();
        $uhash = \Index\Model\OrderRelationShipModel::addressMd5(array($orderData['name'], $orderData['phone'], $orderData['province'], $orderData['city'], $orderData['area'], $orderData['street']));
        $datas = array();
        if ($rows) {
            foreach ($rows as $one) {
                $productId = trim($one[9]);
//            $productId = "xc00012";
                $goodsId = $this->getGoodsIdByProductId($productId);
                if ($goodsId) {
                    $datas[] = array(
                        "orderno" => $orderData['orderno'],
                        "order_id" => $orderData['id'],
                        "goods_type" => \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL,
                        "goods_id" => $goodsId,
                        "count" => trim($one[3]),
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
        } else {
            $errorMsg = "没有在宝贝中找到相关商品数据";
            return false;
        }
        return $orsModel->baseAddAll($datas);
    }

    /**
     * 处理微小店的csv文件数据并导入订单数据
     * 微店 一个订单一个商品
     */
    private function dealWxdOrderCsv() {
        $importFilename = I("post.importFilename");
//        $importFilename = "./orderupload/2016-08-04/order.csv";
        $filePath = self::UPLOAD_ROOT_PATH . ltrim($importFilename, './');
        $pathinfo = pathinfo($filePath);
        if ($pathinfo['extension'] != 'csv') {
            $this->error("目前只支持csv文件，请转换成csv文件后导入", U("index"));
        }
        if (!file_exists($filePath)) {
            $this->error("未找到上传的微小店订单文件：" . $importFilename, U("index"));
        }

        $orderData = array();
        $row = 0;
        $successCount = 0;
        $handle = fopen($filePath, "r");
        $errorData = array();
        while ($data = fgetcsv($handle, 1000, ",")) {
            $row++;
            if ($row == 1) {
                continue;
            }
            $data = eval('return ' . iconv('gbk', 'utf-8', var_export($data, true)) . ';');

            $orderModel = new \Index\Model\OrderModel();
            $data[1] = json_decode(trim($data[1], "?"), false, 512, JSON_BIGINT_AS_STRING);
//            print_r($data[1]);exit;
//            $data[1] = number_format(trim($data[1], "?"), 0, '', '');
            $data[13] = number_format($data[13], 0, '', '');

            $orderNo = self::TYPE_WEIXIAODIAN . $data[1];
            $orderExists = $orderModel->findOrder(array('orderno' => $orderNo), array("id"));
            if ($orderExists) {
                $errorData[] = array('d' => $data[1], "e" => "订单已经存在");
                continue;
            }

            $uid = $this->getUid(trim($data[10]), self::TYPE_WEIXIAODIAN);
            if (!$uid) {
                $errorData[] = array('d' => $data[1], "e" => "无法生成用户id");
                continue;
            }

            $orderData = array(
                "uid" => $uid,
                "orderno" => $orderNo,
                "create_time" => date("Y-m-d H:i:s", time()),
                "update_time" => date("Y-m-d H:i:s", time()),
                "from" => \Index\Model\OrderModel::FROM_WEIDIAN,
                "price" => trim($data[9]),
                "pay_type" => \Index\Model\OrderModel::PAY_TYPE_WX,
                "paidTime" => date("Y-m-d H:i:s", strtotime($data[0])),
                "name" => trim($data[12]),
                "phone" => trim($data[13]),
                "province" => '',
                "city" => '',
                "area" => '',
                "street" => trim($data[14]),
                "ext" => trim($data[1]),
            );

            $orderModel->startTrans();
            $orderData['id'] = $orderModel->addOrder($orderData);

            $orsModel = new \Index\Model\OrderRelationShipModel();
            $uhash = \Index\Model\OrderRelationShipModel::addressMd5(array($orderData['name'], $orderData['phone'], $orderData['province'], $orderData['city'], $orderData['area'], $orderData['street']));
            $products = array();
            $productId = trim($data[4]);
//            $productId = "xc00012";
            $goodsId = $this->getGoodsIdByProductId($productId);
            if ($goodsId) {
                $products = array(
                    "orderno" => $orderData['orderno'],
                    "order_id" => $orderData['id'],
                    "goods_type" => \Index\Model\OrderRelationShipModel::GOODS_TYPE_NORMAL,
                    "goods_id" => $goodsId,
                    "count" => trim($data[6]),
                    "product_id" => $productId,
                    "process" => \Index\Model\OrderRelationShipModel::PROCESS_PUT_IN,
                    "uhash" => $uhash,
                    "status" => \Index\Model\OrderRelationShipModel::ORDER_STATUS_USE_PAID,
                    "uid" => $orderData['uid'],
                    "create_time" => date("Y-m-d H:i:s", time()),
                    "update_time" => date("Y-m-d H:i:s", time()),
                );
                $orsRes = $orsModel->baseAdd($products);
                $successCount++;
            } else {
                $orderModel->rollback();
                $errorData[] = array('d' => $data[1], "e" => "没有在仓储品类中找到商品条码：" . $productId);
                continue;
            }

            if ($orsRes && $orderData['id']) {
                $orderModel->commit();
            } else {
                $orderModel->rollback();
                $errorData[] = array('d' => $data[1], "e" => "订单及商品未同时导入成功，数据已经回滚，请重新导入");
                continue;
            }
//            if ($row == 5) {
//                break;
//            }
        }
        $errMsg = '';
        if ($errorData) {
            foreach ($errorData as $err) {
                $errMsg .= "<br />" . $err['d'] . ":" . $err['e'] . "<br />";
            }
        }
        fclose($handle);
        unlink($filePath);
        $this->success("导入结束，成功导入" . $successCount . "个订单。" . $errMsg, U("index"), 20);
    }

    /**
     * 获取外部订单在订单中心的用户uid  有则获取无则新增
     * 注意：unionID与微信的unionID无关，只为做唯一标记
     * 淘宝用户使用会员名做为唯一标记
     * @param array $nickname 昵称
     * @return type tb wd...
     */
    private function getUid($nickname, $type = "tb") {
        $unionID = $type . md5($nickname);
        $userModel = new \Index\Model\UserModel();
        if ($user = $userModel->checkReg($unionID)) {
            return $user['uid'];
        } else {
            return $userModel->insertInfo(array("unionID" => $unionID, "nickname" => $nickname, "sex" => 0, "country" => "中国"));
        }
    }

}
