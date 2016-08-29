<?php
namespace Index\Model;
use Think\Model;

class PayTypeModel extends Model {
	Protected $autoCheckFields = false;
    
    public function getPayType($uid){
        try {
            $m = M();
            return $m->table('user_paytype')
                     ->where("uid=%d", array($uid))
                     ->find();
        } catch (\Exception $ex) {
            E('getCardInfo_ERR');
        }           
    }

    public function savePayType($uid, $info) {
    	try {
    		//查找该用户
    		$m = M();
    		$ret = $m->table('user_paytype')->where("uid=%d", array($uid))->find();
    		if($ret){
    			//更新
    			$rs = $m->table('user_paytype')
    			->where("uid=%d", array($uid))
    			->save($info);
    			return $rs;
    		}else{
    			//新增
    			$rs = $m->table('user_paytype')
    			->data($info)
    			->add();
    			return $rs;
    		}
    		
    		
    	} catch (\Exception $ex) {
    		E('saveCardData_ERR');
    	}
    }
}