<?php
namespace Index\Model;
use Think\Model;

class NoPayPushModel extends Model {
	Protected $autoCheckFields = false;
    
    public function addNoPay($info) {
    	try {
    		$m = M();
    		$rs = $m->table('nopay_push')
    				->data($info)
    				->add();
    		return $rs;
    	} catch (\Exception $ex) {
    		E('saveCardData_ERR');
    	}
    }
    
    public function delNoPayPush($orderno) {
    	try {
    		$m = M();
    		$rs = $m->table('nopay_push')->where('orderno='.$orderno)->delete(); 
    		return $rs;
    	} catch (\Exception $ex) {
    		E('saveCardData_ERR');
    	}
    }
}