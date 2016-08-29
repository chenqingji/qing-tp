<?php
namespace Index\Model;

use Think\Model;

class UserModel extends Model
{
    Protected $autoCheckFields = false;

    public function getUserInfo($uid)
    {
        try {
            $m = M();
            return $m->table('user_info')
                ->where("uid=%d", array($uid))
                ->find();
        } catch (\Exception $ex) {
            E('getUserInfo_ERR');
        }
    }

    public function checkReg($unionID)
    {
        try {
            $m = M();
            $exist = $m->table('user_info')
                ->where("unionID='%s'", array($unionID))
                ->find();

            if (empty($exist)) {
                return false;
            } else {
                return $exist;
            }
        } catch (\Exception $ex) {
            E('checkReg_ERR');
        }
    }

    public function refreshInfo($info)
    {
        try {
            $m = M();
            return $m->table('user_info')
                ->where("uid='%s'", array($info['uid']))
                ->save($info);
        } catch (\Exception $ex) {
            E('refreshInfo_ERR');
        }
    }

    public function insertInfo($info)
    {
        try {
            $m = M();
            return $m->table('user_info')->data($info)->add();
        } catch (\Exception $ex) {
            E('insertInfo_ERR');
        }
    }

    //根据 unionID 存储 weboOenID, 仅存储原本为空的
    public function saveWebOpenId($unionID, $webOpenId)
    {
        if (!$webOpenId) {
            return false;
        }
        try {
            $m = M();
            return $m->table('user_info')->where("unionID='%s' AND webOpenID = ''", array($unionID))->save(array("webOpenID" => $webOpenId));
        } catch (\Exception $ex) {
            E('saveWebOpenId_ERR');
        }
    }
}