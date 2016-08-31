<?php
return array(
		'DEFAULT_MODULE'                => 'Index', 	//默认模块
		'DEFAULT_CONTROLLER'            => 'Admin', 	//默认控制器
		'URL_MODEL'                     => '2', 		//URL模式
		'SESSION_AUTO_START'            => true, 		//是否开启session

		'DB_TYPE' 						=> 'pdo',		// 指定数据库类型
		'DB_USER'                       => 'root', 		// 用户名
	    'DB_PWD'                        => 'chinese', // 密码
        'DB_DSN'					    => 'mysql:host=127.0.0.1;port=3306;dbname=ms_qing;charset=utf8',
		'PAGE_LIST'						=> '10',	//分页条数
		'URL_ROUTER_ON'                 => true,				// 开启路由支持
		'URL_CASE_INSENSITIVE'          => true,			    // 忽略路径大小写

		'DEFAULT_THEME'                 => 'Default', 			//设置默认主题
		
		'LOG_RECORD'					=>	true, // 开启日志记录
		'LOG_LEVEL'						=>	'ERR,W_ERR', // 允许记录的错误等级

		'URL_PARAMS_BIND'       =>  true,	// 支持url参数自动绑定
		'URL_PARAMS_BIND_TYPE'  =>  0,		// URL变量绑定的类型 0 按变量名绑定 1 按变量顺序绑定

//		// 缓存配置
		'DATA_CACHE_TYPE' => 'file',		// 缓存方式 // @TODO 使用本地缓存，上线更改为apc，如果有多台更改为OSS
		'DATA_CACHE_PREFIX' =>'ky',		    // 缓存前缀
		'DATA_CACHE_TIME' => 86400,			// 缓存时间,0为永久缓存

//		 'DATA_CACHE_TYPE'=>'Redis',//默认动态缓存为Redis
//	     'REDIS_HOST'=>'127.0.0.1', // IP地址
//	     'REDIS_HOST'=>'714c594e0c944674.m.cnqda.kvstore.aliyuncs.com', // IP地址
//	     'REDIS_PORT'=>'6379',//端口号
//	     'DATA_CACHE_TIMEOUT'=>'300',//超时时间
//	     'REDIS_PERSISTENT'=>false,//是否长连接 false=短连接
//	     'REDIS_AUTH'=>'test123',//AUTH 认证密码
		
		'FILE_UPLOAD_TYPE'    =>  'OSS',   // 指定文件上传方式使用OSS,可选local

        /**
         * 普通规格品库存警告最少数量
         */
        "CUR_LEAST_COUNT" => 100,
);
