DROP TABLE IF EXISTS `wh_auth_rule`;

CREATE TABLE `wh_auth_rule` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` INT(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '父级id',
  `name` CHAR(80) NOT NULL DEFAULT '' COMMENT '规则唯一标识',
  `title` CHAR(20) NOT NULL DEFAULT '' COMMENT '规则中文名称',
  `status` TINYINT(1) NOT NULL DEFAULT '1' COMMENT '状态：为1正常，为0禁用',
  `type` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1',
  `condition` CHAR(100) NOT NULL DEFAULT '' COMMENT '规则表达式，为空表示存在就验证，不为空表示按照条件验证',
  `create_time` INT(10) NOT NULL DEFAULT '0',
  `update_time` INT(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=INNODB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8 COMMENT='规则表';


DROP TABLE IF EXISTS `wh_auth_user`;

CREATE TABLE `wh_auth_user` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键id',
  `uid` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '用户id',
  `pwd` CHAR(32) NOT NULL DEFAULT '' COMMENT 'pwd',
  `salt` CHAR(8) NOT NULL DEFAULT '' COMMENT 'salt',
  `type` TINYINT(3) UNSIGNED NOT NULL DEFAULT '1' COMMENT '类型',
  `nickname` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '第三方昵称',
  `head_img` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像',
  `openid` VARCHAR(40) NOT NULL DEFAULT '' COMMENT '第三方用户id',
  `access_token` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'access_token token',
  `create_time` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=INNODB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COMMENT='用户表';

/*Data for the table `wh_auth_user` */

INSERT  INTO `wh_auth_user`(`id`,`uid`,`pwd`,`salt`,`type`,`nickname`,`head_img`,`openid`,`access_token`,`create_time`,`update_time`) VALUES (4,'admin','770297c4d77312fc75b21732c63ad725','Aw39MN',1,'admin管理员','','21232f297a57a5a743894a0e4a801fc3','',1472182873,1472376590),(5,'starryadmin','203569d78d97be3019c659226ec43ccc','tsing',1,'超级管理员','','7a99c5ad1021edac56e3adc6bea71598','',1472195957,1472195957);

DROP TABLE IF EXISTS `wh_auth_user_group`;

CREATE TABLE `wh_auth_user_group` (
  `uid` VARCHAR(64) NOT NULL COMMENT '用户id',
  `group_id` INT(11) UNSIGNED NOT NULL COMMENT '用户组id',
  UNIQUE KEY `uid_group_id` (`uid`,`group_id`),
  KEY `uid` (`uid`),
  KEY `group_id` (`group_id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 COMMENT='用户组关联表';

/*Data for the table `wh_auth_user_group` */

INSERT  INTO `wh_auth_user_group`(`uid`,`group_id`) VALUES ('admin',5);

DROP TABLE IF EXISTS `wh_auth_group`;

CREATE TABLE `wh_auth_group` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` CHAR(100) NOT NULL DEFAULT '' COMMENT '组名',
  `status` TINYINT(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `rules` TEXT NOT NULL COMMENT '规则id',
  `create_time` INT(10) NOT NULL DEFAULT '0',
  `update_time` INT(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=INNODB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COMMENT='用户组表';

/*Data for the table `wh_auth_group` */

INSERT  INTO `wh_auth_group`(`id`,`title`,`status`,`rules`,`create_time`,`update_time`) VALUES (3,'禁用组',0,'',1472128136,1472375556),(5,'普通管理员',1,'1,25,26,27,28,2,6,24,3,4,5,7,8,9,54,55,56,57,58,59,60,61,62,63,64,10,13,11,14,15,16,17,12,18,19,20,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,21,22,23,29,30,31,32,33,34',1472182884,1472373371);
