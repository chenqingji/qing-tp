<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Index\Controller;

use Index\Controller\ServiceController;
use Index\Model\CategoryModel;

/**
 * 普通商品出入库
 */
class NormalController extends ServiceController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * 普通品入库页面
     */
    public function putIn() {
        $categoryModel = new CategoryModel();
        $categoryIds = $categoryModel->baseGet(array(), array("category_id", "category_name"), array('category_id' => "ASC"));
        $this->assign("categoryIds", $categoryIds);

        $sceneData = $this->getSceneData();
        if ($sceneData) {
            $this->assign("sceneData", $sceneData);
            $this->assign("putin_div_html", $this->getInfoByCid($sceneData['cid'], false));
        }
        $this->display("putin");
    }

    /**
     * 普通商品入库操作
     * @todo op_userid 前端第一时间验证
     */
    public function toPutIn() {
//        $data['op_userid'] =$this->getUseridByBarCode(I("post.operator",''));
        $data['op_userid'] = 1;
        $data['op_time'] = time();
        $data['category_id'] = I("post.cid", '');
        $data['count'] = I("post.incount", 0, "trim");
        $data['desc'] = I("post.desc", "", "htmlspecialchars");
        //检测员工
        $data['op_userid'] = $this->checkOperator(I("post.operator", ''));
        if (!$data['op_userid']) {
            $this->errorWithScene("请确认该操作人条形码是否过期", U('putIn'));
        }
        //检测品类编号
        $categoryModel = new CategoryModel();
        $categoryInfo = $this->checkCategory($data, $categoryModel);
        \Index\Common\Marktest::writeTestData($categoryInfo, false);
        $data['location'] = $categoryInfo['shelf_no'];
        //更新品类现有库存、累计入库、最后更新时间
        $categoryModel->startTrans();
        $res1 = $categoryModel->baseSaveById(array(
            "id" => $categoryInfo['id'],
            "cur_count" => ($categoryInfo['cur_count'] + $data['count']),
            'total_in_count' => ($categoryInfo['total_in_count'] + $data['count']))
        );
        \Index\Common\Marktest::writeTestData($data);
        //记录更新员工、品类编号、入库数量、时间
        $inRecordModel = new \Index\Model\InrecordModel();
        $res2 = $inRecordModel->baseAdd($data);
        //提示成功并返回入库入口
        if ($res1!==false && $res2) {
            $categoryModel->commit();
            $this->success("品类【" . $categoryInfo['category_name'] . "】入库" . $data['count'] . "成功", U("putIn"));
        } else {
            $categoryModel->rollback();
            $this->errorWithScene("品类数据更新事务失败，请重新尝试或联系管理员", U('putIn'));
        }
    }

    /**
     * 入库前对品类相关信息验证
     * @param array $data
     * @param \Index\Model\CategoryModel $categoryModel
     * @return array 品类信息
     */
    private function checkCategory($data, $categoryModel) {
        //检测品类编号
        $categoryInfo = array_shift($categoryModel->baseGet(array('category_id' => $data['category_id'])));
        if (empty($categoryInfo)) {
            $this->errorWithScene("商品品类不存在", U('putIn'));
        }
        //检测存储空间
        $availableCount = $categoryInfo['max_count'] - $categoryInfo['cur_count'];
        if ($data['count'] > $availableCount) {
            $this->errorWithScene("该品类存储空间不足，剩余" . $availableCount . "空间", U('putIn'));
        }
        return $categoryInfo;
    }

    /**
     * 通过品类id获取品类html信息
     * @param string $cid 品类编号id
     * @param boolean $output 是否要输出
     * @return string
     */
    public function getInfoByCid($cid = '', $output = true) {
        if (empty($cid) && $output) {
            $cid = I("post.cid", '');
        }
        if ($cid) {
            $categoryModel = new CategoryModel();
            $data = $categoryModel->baseGet(array("category_id" => $cid), array('id', 'category_id', 'category_name', 'unit_price', 'weight', 'cur_count', 'max_count', 'shelf_no', 'zone_start', 'desc'));
            $this->assign("data", $data[0]);
            if ($output) {
                echo $this->fetch('putin_div');
                exit;
            } else {
                return $this->fetch('putin_div');
            }
        } else {
            if ($output) {
                $this->error("非法请求", U("putin"), true);
            } else {
                return '';
            }
        }
    }

}
