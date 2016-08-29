<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Warehouse\Controller;

/**
 * Description of OplogController
 *
 * @author jm
 */
class OplogController extends WrapController {

    /**
     * 页面面包屑第一级文字
     * @var string 
     */
    private $_menuCrumbsFirst = "操作记录管理";

    public function __construct() {
        parent::__construct();
    }

    /**
     * 入库记录
     */
    public function inRecord() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "入库记录", "url" => "/Warehouse/Category/inRecord"),
        );
        $this->assign("menuCrumbs", $menuCrumbs);

        $inRecordModel = new \Warehouse\Model\InrecordModel();
        $data = $inRecordModel->baseGet(array(), "*", array("create_time" => "desc"));
        $this->assign('data', $data);
        $this->display("inrecord");
    }

    /**
     * 出库记录
     */
    public function outRecord() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "出库记录", "url" => "/Warehouse/Category/outRecord"),
        );
        $this->assign("menuCrumbs", $menuCrumbs);

        $outRecordModel = new \Warehouse\Model\OutrecordModel();
        $data = $outRecordModel->baseGet(array(), "*", array("create_time" => "desc"));
        $this->assign('data', $data);
        $this->display("outrecord");
    }

}
