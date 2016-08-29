<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Common\Model;

/**
 * Description of BaseModel
 */


class BaseModel extends \Think\Model  {
    protected $autoCheckFields = false;

    /**
     * 架构函数
     * 取得DB类的实例对象 字段检查
     * @access public
     * @param string $name 模型名称
     * @param string $tablePrefix 表前缀
     * @param mixed $connection 数据库连接信息
     */
    public function __construct($name='',$tablePrefix='',$connection='') {
        parent::__construct($name, $tablePrefix, $connection);
    }

    /** 色号在索引
     * @param string $indexName
     * @return object
     */
    public function index($indexName) {
        $this->options['forceIndex'] = trim($indexName);
        return $this;
    }

    /**
     * 分析表达式
     * @access protected
     * @param array $options 表达式参数
     * @return array
     */
    protected function _parseOptions($options=array()) {
        $options = parent::_parseOptions($options);
        if(isset($options['forceIndex'])) {
            if(!empty($options['forceIndex'])) {
                $options['table'] .= ' USE INDEX('.$options['forceIndex'].')';
            }
            unset($options['forceIndex']);
        }
        return $options;
    }
    
    /**
     * 通过当前页码和每页数据条数，获取查询的起始及终止位置
     * @param int $page
     * @param int $rows
     * @return array
     */
    protected function getPageLimit($page = 1, $rows = 10) {
        $limitStart = ($page - 1) * $rows;
        $limitEnd = $page * $rows;
        return array($limitStart, $limitEnd);
    }
}
