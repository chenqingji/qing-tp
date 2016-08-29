<?php
namespace Index\Model;
use Think\Model;

class SysInfoModel extends Model {
	Protected $autoCheckFields = false;

    public function getAccessToken() {
        try {
            $m = M();

            $rs = $m->table('sys_info')
                       ->where(array("option"=>"token"))
                       ->find();
            if(!$rs || !$rs['value']){
            	return NULL;
            }
            
            return json_decode($rs['value']);
        } catch (\Exception $ex) {
            E('getAccessToken_ERR');
        }

        return false;
    }
    
    public function saveAccessToken($tokenArray) {
    	try {
    		$m = M();
    
    		$rs = $m->table('sys_info')
    		->where(array("option"=>"token"))
    		->save(array("value"=>json_encode($tokenArray)));
    
    		return $rs;
    	} catch (\Exception $ex) {
    		E('getAccessToken_ERR');
    	}
    
    	return false;
    }
}