<?php
namespace Index\Model;
use Think\Model;

class PackageModel extends Model {
	Protected $autoCheckFields = false;

	/**
	 * 获取最新的包裹订单id,不存在就新增一条
	 */
    public function getLastPackageId($orderNo) {
        try {
            $m = M();
            $rs = $m->table('package')
                       ->where(array("orderno"=>$orderNo))->order("id desc")
                       ->find();
            
            if($rs && $rs['reset']==0){
            	return $rs['id'];
            }
            
            return $this->addPackageId($orderNo);
        } catch (\Exception $ex) {
            E('getLastPackageId_ERR');
        }

        return false;
    }
    
    /**
     * 获取最新的包裹订单id,不存在返回0
     */
    public function getPackageId($orderNo) {
    	try {
    		$m = M();
    		$rs = $m->table('package')
    		->where(array("orderno"=>$orderNo))->order("id desc")
    		->find();
    
    		if($rs){
    			return $rs['id'];
    		}
    
    		return 0;
    	} catch (\Exception $ex) {
    		E('getPackageId_ERR');
    	}
    
    	return false;
    }
    
    //新增一个包裹号
    public function addPackageId($orderNo){
    	$rsId = M()->table('package')->add(array("orderno"=>$orderNo));
        return $rsId;
    }
    
    /**
     * 创建新的包裹订单号
     */
    public function savePackageMailNo($packId,$mailNo) {
    	try {
    		$m = M();
    		$rs = $m->table('package')
    		->where(array("id"=>$packId))
    		->save(array("mailNo"=>"$mailNo"));
    
    		return $rs;
    	} catch (\Exception $ex) {
    		E('savePackageMailNo_ERR');
    	}
    
    	return false;
    }
    
    //获取订单所有包裹信息(未测试)
    public function getPackages($orderNo){
    	$m = M();
    	$rs = $m->table('package')
    	->where(array("orderno"=>$orderNo))->order("id desc")
    	->select();
    	return $rs;
    }

    // 重置订单
    public function resetPackage($orderNo) {
        try {
            $id = $this->getPackageId($orderNo);
            if($id){
            	$ret = M()->table('package')
            	->where(array("id"=>$id))
            	->save(array("reset"=>1));
            }
            return $ret;
        } catch (\Exception $ex) {
            E('getLastPackageId_ERR');
        }
        return 0;
    }
    
    //计算今天新生成订单号
    public function getTodayPackageCount() {
        try {
            $today = date("Y-m-d");

            return M()->table('package')
                ->where("date BETWEEN '".$today." 00:00:00' AND '".$today." 23:59:59' AND reset = 0")
                ->count();
        } catch (\Exception $ex) {
            E('getTodayPackageCount_ERR');
        }
    }
}