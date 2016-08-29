<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Warehouse\Controller;

use Warehouse\Controller\WrapController;

/**
 * 仓储区域管理
 */
class ZoneController extends WrapController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * 仓储区域情况
     */
    public function index() {
        $menuCrumbs = array(
            "first" => "仓储区域",
            "second" => array("menuName" => "仓储区域列表", "url" => "/Warehouse/Zone/index"),
        );
        $this->assign("menuCrumbs", $menuCrumbs);

        $normalZone = C("NORMAL_ZONE");
        $customZone = C("CUSTOM_ZONE");
        $this->assign("normal", $normalZone);
        $this->assign("custom", $customZone);
        $this->display("index");
    }

    public function add() {
        $menuCrumbs = array(
            "first" => "仓储区域",
            "second" => array("menuName" => "添加仓储区域", "url" => "/Warehouse/Zone/add"),
        );
        $this->assign("menuCrumbs", $menuCrumbs);

        $normalZone = C("NORMAL_ZONE");
        $customZone = C("CUSTOM_ZONE");
        $this->assign("normal", $normalZone);
        $this->assign("custom", $customZone);
        $this->display("add");        
    }
    
}
