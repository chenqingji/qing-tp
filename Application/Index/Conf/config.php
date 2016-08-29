<?php
return array(
//	'HTML_CACHE_ON' => false, //true 开启静态缓存
//	'HTML_CACHE_TIME'  => 7200,   // 全局静态缓存有效期（秒）
//	'HTML_PATH' => realpath(APP_PATH).'/Html/',//静态缓存文件目录，HTML_PATH可任意设置，此处设为当前项目下新建的html目录
//	'HTML_FILE_SUFFIX' => '.shtml', // 设置静态缓存文件后缀
//	'HTML_CACHE_RULES' =>  array(  // 定义静态缓存规则
//	    // 定义格式1 数组方式
//	    'Index:play' =>  array('{cid|getStaticFilePrefix}'),
//	),
    "SHOW_ALL_ORDER" => true,
    'ACTIVITY' => array(
        'common' => array(
            array(
                "url" => "#",
                "img" => "/Public/Image/banner/banner-3.jpg",
                "des" => "tt3"
            ),
            array(
                "url" => "#",
                "img" => "/Public/Image/banner/banner-4.jpg",
                "des" => "tt4"
            ),
            array(
                "url" => "#",
                "img" => "/Public/Image/banner/banner-5.jpg",
                "des" => "tt5"
            ),
            array(
                "url" => "#",
                "img" => "/Public/Image/banner/banner-6.jpg",
                "des" => "tt6"
            ),
        ),
        1 => array(
            1 => array(
                array(
                    "url" => "#",
                    "img" => "/Public/Image/banner/banner-1.jpg",
                    "des" => "tt1"
                ),
            ),
            2 => array(
                array(
                    "url" => "#",
                    "img" => "/Public/Image/banner/banner-2.jpg",
                    "des" => "tt2"
                ),
            ),
        )
    ),
    'GOODS' => [
        55 => [
            'id' => 55,
            'category_name' => '7寸红色',
            'unit_price' => 92.00,
            'display_desc' => '{"des":"ttttt","imgUrls":["http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg"]}',
            'img_url' => 'http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg',
            'preferential_price' => 92.00,
        ],
        56 => [
            'id' => 56,
            'category_name' => '9寸红色 --',
            'unit_price' => 12.00,
            'display_desc' => '{"des":"ttttt","imgUrls":["http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg"]}',
            'img_url' => 'http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg',
            'preferential_price' => 11.00,
        ],
        57 => [
            'id' => 57,
            'category_name' => '8寸红色 &&',
            'unit_price' => 1.00,
            'display_desc' => '{"des":"ttttt","imgUrls":["http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg","http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg"]}',
            'img_url' => 'http:\/\/img5.imgtn.bdimg.com\/it\/u=3504299437,2517153506&fm=21&gp=0.jpg',
            'preferential_price' => 1.00,
        ],
    ]
);