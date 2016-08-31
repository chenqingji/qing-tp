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
                    'path' => array('/Index/Zone/index'),
                    'url' => '/Index/Zone/index',
                    'title' => '仓储区域列表'
                ),
                array(
                    'path' => array('/Index/Zone/add'),
                    'url' => '/Index/Zone/add',
                    'title' => '添加仓储区域'
                ),
            )
        ),
        array(
            'title' => '品类管理',
            'icon' => 'icon-reorder',
            'submenu' => array(
                array(
                    'path' => array('/Index/Category/index'),
                    'url' => '/Index/Category/index',
                    'title' => '普通品类列表'
                ),
                array(
                    'path' => array('/Index/Category/add'),
                    'url' => '/Index/Category/add',
                    'title' => '添加普通品类'
                ),
            )
        ),
        array(
            'title' => '定制品仓储',
            'icon' => 'icon-exchange',
            'submenu' => array(
                array(
                    'path' => array('/Index/Custom/index'),
                    'url' => '/Index/Custom/index',
                    'title' => '在仓库定制品列表'
                ),
            )
        ),
        array(
            'title' => '线下服务',
            'icon' => 'icon-tasks',
            'submenu' => array(
                array(
                    'path' => array('/Index/Service/index'),
                    'url' => '/Index/Service/index',
                    'title' => '服务入口',
                    'target' => "_blank"
                ),
                array(
                    'path' => array('/Index/Normal/putIn'),
                    'url' => '/Index/Normal/putIn',
                    'title' => '普通商品入库',
                    'target' => "_blank"
                ),
                array(
                    'path' => array('/Index/Custom/putIn'),
                    'url' => '/Index/Custom/putIn',
                    'title' => '定制品入库',
                    'target' => "_blank"
                ),
                array(
                    'path' => array('/Index/Picking/index'),
                    'url' => '/Index/Picking/index',
                    'title' => '打印拣货单',
                    'target' => "_blank"
                ),
                array(
                    'path' => array('/Index/Checkout/index'),
                    'url' => '/Index/Checkout/index',
                    'title' => '校验及打印电子面单',
                    'target' => "_blank"
                ),
//                array(
//                    'path' => array('/Index/Express/index'),
//                    'url' => '/Index/Express/index',
//                    'title' => '打印电子面单',
//                    'target' => "_blank"
//                ),
                array(
                    'path' => array('/Index/Checkout/checkall'),
                    'url' => '/Index/Checkout/checkall',
                    'title' => '仓储预警扫描',
                    'target' => "_blank"
                ),
                array(
                    'path' => array('/Index/Custom/viewprocess'),
                    'url' => '/Index/Custom/viewprocess',
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
//                    'path' => array('/Index/Oplog/index'),
//                    'url' => '/Index/Oplog/index',
//                    'title' => '其他操作日志'
//                ),

                array(
                    'path' => array('/Index/Oplog/inrecord'),
                    'url' => '/Index/Oplog/inRecord',
                    'title' => '入库记录'
                ),
                array(
                    'path' => array('/Index/Oplog/outrecord'),
                    'url' => '/Index/Oplog/outRecord',
                    'title' => '出库记录'
                ),
                array(
                    'path' => array('/Index/Picking/lists'),
                    'url' => '/Index/Picking/lists',
                    'title' => '拣货单列表'
                ),
                array(
                    'path' => array('/Index/Express/packages'),
                    'url' => '/Index/Express/packages',
                    'title' => '包裹列表'
                ),
            )
        ),
//        array(
//            'title' => '统计报表',
//            'icon' => 'icon-bar-chart',
//            'submenu' => array(
//                array(
//                    'path' => array('/Index/Report/index'),
//                    'url' => '/Index/Report/index',
//                    'title' => '统计报表'
//                )
//            )
//        ),
        array(
            'title' => '订单中心',
            'icon' => 'icon-th',
            'submenu' => array(
                array(
                    'path' => array('/Index/Order/index'),
                    'url' => '/Index/order/index',
                    'title' => '订单列表'
                ),
                array(
                    'path' => array('/Index/Order/products'),
                    'url' => '/Index/order/products',
                    'title' => '订单商品列表'
                ),
                array(
                    'path' => array('/Index/Order/addNormal'),
                    'url' => '/Index/Order/addNormal',
                    'title' => '添加普通订单',
                ),
                array(
                    'path' => array('/Index/Order/addCustom'),
                    'url' => '/Index/Order/addCustom',
                    'title' => '添加定制品订单',
                    "target" => "_blank",
                ),
                array(
                    'path' => array('/Index/Import/import'),
                    'url' => '/Index/Import/import',
                    'title' => '导入第三方订单'
                ),
            )
        ),
        array(
            'title' => '系统用户管理',
            'icon' => 'icon-user',
            'submenu' => array(
                array(
                    'path' => array('/Index/Authuser/index'),
                    'url' => '/Index/Authuser/index',
                    'title' => '用户列表'
                ),
                array(
                    'path' => array('/Index/Authgroup/index'),
                    'url' => '/Index/Authgroup/index',
                    'title' => '用户组列表'
                ),
                array(
                    'path' => array('/Index/Authrule/index'),
                    'url' => '/Index/Authrule/index',
                    'title' => '权限规则列表'
                ),
            )
        ),
        array(
            'title' => '在线工具',
            'icon' => 'icon-gear',
            'submenu' => array(
                array(
                    'path' => array('/Index/Tools/fetchPic'),
                    'url' => '/Index/Tools/fetchPic',
                    'title' => '解析并下载微信图片'
                ),
            )
        )
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
    "PRODUCT_NAMES" => array("2" => "Qing照片", "3" => "微信书", "4" => "Lomo卡", "5" => "照片卡"),
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
