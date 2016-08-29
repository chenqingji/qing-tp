<?php
namespace Index\Model;
use Think\Model;

class ZpkDataModel extends Model {

	Protected $autoCheckFields = false;

    public function lockOrder($cid){
        try {
            $info = array(
                'is_sync' => '3'
            );
            $m = M();
            $rs = $m->table('zpk_card_info')
              ->where("cid=%d", array($cid))
              ->save($info);
            //echo $m->getLastSql();
            return $rs;
        } catch (\Exception $ex) {
            E('lockOrder_ERR');
        }
    }

    public function unLockOrder($cid){
        try {
            $info = array(
                'is_sync' => '0'
            );
            $m = M();
            $rs = $m->table('zpk_card_info')
              ->where("cid=%d", array($cid))
              ->save($info);
            //echo $m->getLastSql();
            return $rs;
        } catch (\Exception $ex) {
            E('unLockOrder_ERR');
        }
    }

    public function listCardData($uid){
        try {
            return M()->field('cid,create_time,pay_state,price,name,phone,province,city,area,street,zipcode,orderno,paidTime,is_sync,card_number')
                      ->table('zpk_card_info')
                      ->where("uid=%d AND isdel=0 AND card_number <> 0", array($uid))
                      ->order('cid desc')
                      ->select();
        } catch (\Exception $ex) {
            E('listCardData_ERR');
        }
    }

    public function getCardInfo($cid){
        try {
            $m = M();

            return $m->table('zpk_card_info')
                     ->where("cid=%d AND isdel=0", array($cid))
                     ->find();
        } catch (\Exception $ex) {
            E('getCardInfo_ERR');
        }
    }

    public function saveCardData($uid, $cid, $info) {
        try {
            $m = M();
            $rs = $m->table('zpk_card_info')
              ->where("uid=%d AND cid=%d", array($uid, $cid))
              ->save($info);
            //echo $m->getLastSql();
            return $rs;
        } catch (\Exception $ex) {
            E('saveCardData_ERR');
        }
    }

    public function checkCardId($cid, $uid) {
        try {
            $m = M();
            $cond = array("cid"=>$cid);

            if(!empty($uid)) {
                $cond["uid"] = $uid;
            }

            $exist = $m->table('zpk_card_info')
                       ->where($cond)
                       ->find();
            if(!empty($exist)) {
                return true;
            }

        } catch (\Exception $ex) {
            E('checkCardId_ERR');
        }

        return false;
    }

    public function getCardLike($query_map, $order, $limit, $field){
        try {
            return M()->field($field)
                ->table('zpk_card_info')
                ->where($query_map)
                ->order($order)
                ->limit($limit)
                ->select();
        } catch (\Exception $ex) {
            E('getCardLike_ERR');
        }
    }

    public function getCardByOrderNo($orderNo){
    	try {
    		$m = M();
    		$rs = $m->table('zpk_card_info')
    		->where(array('orderno'=>$orderNo))
    		->find();
    		return $rs;
    	} catch (\Exception $ex) {
    		E('getCardByOrderNo_ERR');
    	}
    }

    //保存错误信息
    public function savePayError($type,$msg){
    	return M()->table('zpk_pay_error')
    	->data(array("type"=>$type,"msg"=>$msg))
    	->add();
    }

    public function saveCard($cid, $info) {
        try {
            $m = M();
            $rs = $m->table('zpk_card_info')
                ->where("cid=%d", array($cid))
                ->save($info);
            return $rs;
        } catch (\Exception $ex) {
            E('saveCard_ERR');
        }
    }

    public function newCardData($info) {
        try {
            $m = M();

            return $m->table('zpk_card_info')
                     ->data($info)
                     ->add();
        } catch (\Exception $ex) {
            E('newCardData_ERR');
        }
    }

    public function delCardData($uid, $cid) {
        try {

            $ret = M()->table('zpk_card_info')
                      ->where("uid=%d AND cid=%d", array($uid, $cid))
                      ->save(array("isdel"=>1));
            return $ret;

        } catch (\Exception $ex) {
            E('delCardData_ERR');
        }
    }

    public function copyCardData($uid, $cid) {
        try {
            $m = M();
            $ret =  $m->table('zpk_card_info')->where("uid=%d AND cid=%d AND isdel=0", array($uid, $cid))
                      ->find();

            // 复制数据内容
            if($ret != 0 && $ret != false) {
                unset($ret["cid"]);
                $ret = $m->table('zpk_card_info')->add($ret);
            }

            return $ret;

        } catch (\Exception $ex) {
            E('copyCardData_ERR');
        }
    }
}