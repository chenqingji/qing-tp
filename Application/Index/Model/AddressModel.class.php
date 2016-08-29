<?php
/**
 * Created by PhpStorm.
 * User: kzangv
 * Date: 2015/11/30
 * Time: 15:12
 */

namespace Index\Model;
use Think\Model;

class AddressModel extends Model {
	Protected $autoCheckFields = false;

    public function addAddressData($data) {
        try {
            $m =  M();
            $ret = $m ->table('address_info')
                       ->data($data)
                       ->add();
            return $ret;
        } catch (\Exception $ex) {
            E('addAddressData_ERR');
        }
    }

    public function saveAddressData($id, $data) {
        try {
            $m = M();
            $rs = $m->table('address_info')
                     ->where("id=%d", array($id))
                     ->save($data);
            return $rs;
        } catch (\Exception $ex) {
            E('addAddressData_ERR');
        }
    }

    public function deleteAddressData($id) {
        $this->saveAddressData($id, array("is_del" => 1));
    }

    public function getAddressData($id,$uid) {
        try {
            $rs = M()->table('address_info')
                     ->where("id=%d AND uid=%d AND is_del=0", array($id,$uid))
                     ->find();
            return $rs;
        } catch (\Exception $ex) {
            E('addAddressData_ERR');
        }
    }

    public function getLastAddressData($uid) {
        try {
            $rs = M()->table('address_info')
                ->where("uid=%d AND is_del=0", array($uid))
                ->order('id desc')
                ->find();
            return $rs;
        } catch (\Exception $ex) {
            E('addAddressData_ERR');
        }
    }

    public function getAddressList($uid) {
        try {
            $rs = M()->table('address_info')
                ->where("uid=%d AND is_del=0", array($uid))
                ->order('id desc')
                ->select();
            return $rs;
        } catch (\Exception $ex) {
            E('getAddressList_ERR');
        }
    }

    public function getDefaultAddress($uid) {
        try {
            $m= M();
            $rs = $m->table('user_info')
                ->where("uid=%d",array($uid))
                ->find();

            if($rs) {
                $rs = $rs['aid'];
            } else {
                $rs = null;
            }
            return $rs;
        } catch (\Exception $ex) {
            E('getAddressList_ERR');
        }
    }

    public function setDefaultAddress($uid, $aid) {
        try {
            $rs = M()->table('user_info')
                ->where("uid='%s'",array($uid))
                ->save(array('aid' => $aid));
            return $rs;
        } catch (\Exception $ex) {
            E('getAddressList_ERR');
        }
    }
} 