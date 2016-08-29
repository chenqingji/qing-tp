<?php

return array(
    /**
     * auth权限配置
     */
    'AUTH_CONFIG' => array(
        'AUTH_ON' => true, //认证开关
        'AUTH_TYPE' => 1, // 认证方式，1为时时认证；2为登录认证。
        'AUTH_GROUP' => 'wh_auth_group', //用户组数据表名
        'AUTH_GROUP_ACCESS' => 'wh_auth_user_group', //用户组明细表
        'AUTH_RULE' => 'wh_auth_rule', //权限规则表
        'AUTH_USER' => 'wh_auth_ser', //用户信息表
    ),
    /**
     * 仓储区域zone扩展配置
     */
    'LOAD_EXT_CONFIG' => 'zone',
    /**
     * 是否开启布局
     */
    'LAYOUT_ON' => true,
    /**
     * 布局文件名称
     */
    'LAYOUT_NAME' => 'wrap',
    /**
     * 布局左侧导航栏
     */
    'MENUS' => array(
        array(
            'title' => '仓储区域',
            'icon' => 'icon-home',
            'submenu' => array(
                array(
                    'path' => array('/Warehouse/Zone/index'),
                    'url' => '/Warehouse/Zone/index',
                    'title' => '仓储区域列表'
                ),
                array(
                    'path' => array('/Warehouse/Zone/add'),
                    'url' => '/Warehouse/Zone/add',
                    'title' => '添加仓储区域'
                ),
            )
        ),
        array(
            'title' => '品类管理',
            'icon' => 'icon-reorder',
            'submenu' => array(
                array(
                    'path' => array('/Warehouse/Category/index'),
                    'url' => '/Warehouse/Category/index',
                    'title' => '普通品类列表'
                ),
                array(
                    'path' => array('/Warehouse/Category/add'),
                    'url' => '/Warehouse/Category/add',
                    'title' => '添加普通品类'
                ),
            )
        ),
        array(
            'title' => '定制品仓储',
            'icon' => 'icon-exchange',
            'submenu' => array(
                array(
                    'path' => array('/Warehouse/Custom/index'),
                    'url' => '/Warehouse/Custom/index',
                    'title' => '在仓库定制品列表'
                ),
            )
        ),
        array(
            'title' => '线下服务',
            'icon' => 'icon-tasks',
            'submenu' => array(
                array(
                    'path' => array('/Warehouse/Service/index'),
                    'url' => '/Warehouse/Service/index',
                    'title' => '服务入口',
                    'target' => "_blank"
                ),
                array(
                    'path' => array('/Warehouse/Normal/putIn'),
                    'url' => '/Warehouse/Normal/putIn',
                    'title' => '普通商品入库',
                    'target' => "_blank"
                ),
                array(
                    'path' => array('/Warehouse/Custom/putIn'),
                    'url' => '/Warehouse/Custom/putIn',
                    'title' => '定制品入库',
                    'target' => "_blank"
                ),
                array(
                    'path' => array('/Warehouse/Picking/index'),
                    'url' => '/Warehouse/Picking/index',
                    'title' => '打印拣货单',
                    'target' => "_blank"
                ),
                array(
                    'path' => array('/Warehouse/Checkout/index'),
                    'url' => '/Warehouse/Checkout/index',
                    'title' => '校验及打印电子面单',
                    'target' => "_blank"
                ),
//                array(
//                    'path' => array('/Warehouse/Express/index'),
//                    'url' => '/Warehouse/Express/index',
//                    'title' => '打印电子面单',
//                    'target' => "_blank"
//                ),
                array(
                    'path' => array('/Warehouse/Checkout/checkall'),
                    'url' => '/Warehouse/Checkout/checkall',
                    'title' => '仓储预警扫描',
                    'target' => "_blank"
                ),
                array(
                    'path' => array('/Warehouse/Custom/viewprocess'),
                    'url' => '/Warehouse/Custom/viewprocess',
                    'title' => '查询商品仓储进度',
                    'target' => "_blank"
                ),
            )
        ),
        array(
            'title' => '操作记录管理',
            'icon' => 'icon-bar-chart',
            'submenu' => array(
//                array(
//                    'path' => array('/Warehouse/Oplog/index'),
//                    'url' => '/Warehouse/Oplog/index',
//                    'title' => '其他操作日志'
//                ),

                array(
                    'path' => array('/Warehouse/Oplog/inrecord'),
                    'url' => '/Warehouse/Oplog/inRecord',
                    'title' => '入库记录'
                ),
                array(
                    'path' => array('/Warehouse/Oplog/outrecord'),
                    'url' => '/Warehouse/Oplog/outRecord',
                    'title' => '出库记录'
                ),
                array(
                    'path' => array('/Warehouse/Picking/lists'),
                    'url' => '/Warehouse/Picking/lists',
                    'title' => '拣货单列表'
                ),
                array(
                    'path' => array('/Warehouse/Express/packages'),
                    'url' => '/Warehouse/Express/packages',
                    'title' => '包裹列表'
                ),
            )
        ),
//        array(
//            'title' => '统计报表',
//            'icon' => 'icon-bar-chart',
//            'submenu' => array(
//                array(
//                    'path' => array('/Warehouse/Report/index'),
//                    'url' => '/Warehouse/Report/index',
//                    'title' => '统计报表'
//                )
//            )
//        ),
        array(
            'title' => '订单中心',
            'icon' => 'icon-th',
            'submenu' => array(
                array(
                    'path' => array('/Warehouse/Order/index'),
                    'url' => '/Warehouse/order/index',
                    'title' => '订单列表'
                ),
                array(
                    'path' => array('/Warehouse/Order/products'),
                    'url' => '/Warehouse/order/products',
                    'title' => '订单商品列表'
                ),
                array(
                    'path' => array('/Warehouse/Order/addNormal'),
                    'url' => '/Warehouse/Order/addNormal',
                    'title' => '添加普通订单',
                ),
                array(
                    'path' => array('/Warehouse/Order/addCustom'),
                    'url' => '/Warehouse/Order/addCustom',
                    'title' => '添加定制品订单',
                    "target" => "_blank",
                ),
                array(
                    'path' => array('/Warehouse/Import/import'),
                    'url' => '/Warehouse/Import/import',
                    'title' => '导入第三方订单'
                ),
            )
        ),
        array(
            'title' => '系统用户管理',
            'icon' => 'icon-user',
            'submenu' => array(
                array(
                    'path' => array('/Warehouse/Authuser/index'),
                    'url' => '/Warehouse/Authuser/index',
                    'title' => '用户列表'
                ),
                array(
                    'path' => array('/Warehouse/Authgroup/index'),
                    'url' => '/Warehouse/Authgroup/index',
                    'title' => '用户组列表'
                ),
                array(
                    'path' => array('/Warehouse/Authrule/index'),
                    'url' => '/Warehouse/Authrule/index',
                    'title' => '权限规则列表'
                ),
            )
        ),
//        array(
//            'title' => '系统设置',
//            'icon' => 'icon-gear',
//            'submenu' => array(
//                array(
//                    'path' => array('/Warehouse/Sets/index'),
//                    'url' => '/Warehouse/Sets/index',
//                    'title' => '所有设置'
//                ),
//            )
//        )
    ),
    /**
     * show_page_count
     */
    "SHOW_PAGE_COUNT" => 5,
    /**
     * 允许访问ip段及指定ip
     */
    'ALLOW_ACCESS_IP' => array(
        'section' => array(
            array('start' => '59.57.204.0', 'end' => "59.57.204.255"),
            array('start' => '110.80.61.0', 'end' => "110.80.61.255"),
            array("start" => "120.42.92.0", "end" => "120.42.92.255"),
        ),
        "in" => array('192.168.142.1'),
    ),
    /**
     * 允许直接访问的模块
     */
    "ALLOW_ACCESS_MODULE" => array(
        "Admin" => array("login", "getverifycode", "tologin"),
        "Normal" => array("putin", "toputin"),
        "Custom" => array("putin", "toputin", "viewprocess"),
        "Picking" => array("index", "toprint", "ajaxtoprint"),
        "Checkout" => array("index", "togetpicklist", "checkall", "tocheckcustom", "tochecknormal", "tocheckproduct"),
        "Express" => array("index", "toprint"),
        "Service" => array("index"),
        "Innertest" => "*",
    ),
    /**
     * 商品信息表 goods_type值对应名称
     */
    "PRODUCT_NAMES" => array("2" => "快印照片", "3" => "微信书", "4" => "Lomo卡", "5" => "照片卡"),
    /**
     * 商品进度说明
     */
    "PROCESS_LABLES" => array("未入库", "已入库", "已拣货", "已出库"),
    /**
     * 无指定一次性请求打印拣货单最多单数
     * 10
     */
    "PICK_LIST_MAX_COUNT" => 10,
    /**
     * 打印拣货单时间 当前时间20分钟前 1200s (测试60s)
     * 无条件打印拣货单情况，只对20分钟前入库的商品进行拼单及打印
     */
    "PICK_COOLING_TIME" => 60,
//    "PICK_COOLING_TIME"=>1200,
    /**
     * 是否允许使用ems物流处理打印边远地区电子面单
     */
    "CAN_USE_EMS" => false,
);
