<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Warehouse\Controller;

/**
 * Description of InnertestController
 *
 * @author
 */
class InnertestController extends ServiceController {

    /**
     * 输出测试数据
     */
    public function mark() {
        print_r(\Warehouse\Common\Marktest::getTestData());
        exit;
    }

    /**
     * 解析加密后的拣货单信息
     */
    public function decodePickList() {
        $encodeString = I("post.encode");
        if ($encodeString) {
            print_r(json_decode(authcode($encodeString, "DECODE", self::AUTHCODE_PRINT_KEY), true));
        }else{
            exit("十年抗湖，毁于易帝");
        }
    }

}
