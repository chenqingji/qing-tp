<?php
namespace Index\Model;
use Think\Model;

class TraceInfoModel extends Model {
	Protected $autoCheckFields = false;
    /*
     * 保存物流信息
     */
    public function saveTraceInfo($info) {
    	try {
    		$m = M();
    		$ret = $m->table('traces_info')->where("LogisticCode=".$info['LogisticCode'])->find();
    		if($ret){
    			//更新
    			$rs = $m->table('traces_info')
    			->where("LogisticCode=".$info['LogisticCode'])
    			->save($info);
    			return $rs;
    		}else{
    			//新增
    			$rs = $m->table('traces_info')
    			->data($info)
    			->add();
    			return $rs; 
    		}

    	} catch (\Exception $ex) {
    		E('saveTraceInfo_ERR');
    	}
    }
    
    public function getTraceInfo($mailno) {
    	try {
    		$m = M();
    		return $m->table('traces_info')->where("LogisticCode=".$mailno)->find();
    	
    	} catch (\Exception $ex) {
    		E('getTraceInfo_ERR');
    	}
    }
}