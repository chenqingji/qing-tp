<?php

namespace Warehouse\Controller;

use Warehouse\Controller\WrapController;

/**
 * 系统用户管理
 */
class AuthuserController extends WrapController {

    /**
     * 系统用户管理 一级菜单
     * @var type 
     */
    private $_menuCrumbsFirst = "系统用户管理";

    /**
     * construct
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * index
     */
    public function index() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "用户列表", "url" => "/Warehouse/Authuser/index"),
        );

        $this->assign("menuCrumbs", $menuCrumbs);
        $this->display("index");
    }

    /**
     * 获取列表数据
     */
    public function getListData() {
        $page = I("param.page", 1);
        $rows = I("param.rows", 10);
        $sidx = I("param.sidx", 'create_time');
        $sord = I("param.sord", 'desc');
        $where = $this->getSearchCondition();
        $field = array("id", "uid", "salt", "openid", "nickname" => "name", "pwd" => "password", "create_time", "update_time");
        $authuserModel = new \Warehouse\Model\AuthuserModel();
        if (empty($sidx) || empty($sord)) {
            $sidx = "create_time";
            $sord = "desc";
        }
        $order = array($sidx => $sord);
        $res = $authuserModel->baseGetPage($where, $field, $page, $rows, $order);
        $list = $res['rows'];
        foreach ($list as $key => $one) {
            $authgroupModel = new \Warehouse\Model\AuthgroupModel();
            $groupInfo = $authgroupModel->getGroupInfoByUid($one['uid']);
            if ($groupInfo) {
                $list[$key]['group_id'] = $groupInfo['group_id'];
                $list[$key]['group_status'] = $groupInfo['status'];
            }
        }
        $res['rows'] = $list;
        echo json_encode($res, TRUE);
        exit;
    }

    /**
     * 操作分发入口
     */
    public function operation() {
        $oper = I("post.oper", null);
        if (!empty($oper)) {
            switch ($oper) {
                case 'add':
                    $this->toAdd();
                    break;
                case 'edit':
                    $this->toEdit();
                    break;
                case 'del':
                    $this->ajaxError("未完善" . I("post.oper") . "操作");
                    $this->toDelete();
                    break;
                default:
                    $this->ajaxError("未完善" . I("post.oper") . "操作");
                    exit;
                    break;
            }
        }
    }

    /**
     * new a user
     */
    public function toAdd() {
        $data['uid'] = I("post.uid");
        $data['nickname'] = I("post.name");
        $data['pwd'] = I("post.password");
        $data['group_id'] = I("post.group_id");
        //@todo uid  nickname pwd  验证规则

        $data['salt'] = $this->generateSalt();
        $data['pwd'] = $this->generatePassword($data['salt'], $data['pwd']);
        $data['openid'] = $this->generateOpenId($data['uid'], $data['pwd']);

        $authuserModel = new \Warehouse\Model\AuthuserModel();
        $authuserModel->startTrans();
        $res1 = true;
        if ($data['group_id']) {
            $authusergroupModel = new \Warehouse\Model\AuthusergroupModel();
            $res1 = $authusergroupModel->baseAdd(array("uid" => $data['uid'], "group_id" => $data['group_id']));
        }
        $res2 = $authuserModel->baseAdd($data);
        if ($res1 && $res2) {
            $authuserModel->commit();
            $this->ajaxSuccess("成功添加用户：" . $data['uid']);
        }
        $authuserModel->rollback();
        $this->ajaxError("添加用户失败：" . $data['uid'] . " " . $authuserModel->getError());
    }

    /**
     * edit user info
     */
    public function toEdit() {
        $data = [];
        $data['id'] = I("post.id", 0);
        $data['uid'] = I("post.uid", '', 'htmlspecialchars');
        $data['group_id'] = I("post.group_id");
        //@todo 密码修改暂时有问题，待调整
        $data['pwd'] = I("post.password", '', 'htmlspecialchars');
        $data['nickname'] = I("post.name", 0);
        //@todo uid nickname pwd 验证规则
        $this->noOperateSuperAdmin($data['uid']);

        $authuserModel = new \Warehouse\Model\AuthuserModel();
        $userInfo = $authuserModel->baseFind(array("id" => $data['id']));
        if ($data['pwd'] == $userInfo['pwd']) {
            unset($data['pwd']);
        } else {
            $data['salt'] = $this->generateSalt();
            $data['pwd'] = $this->generatePassword($data['salt'], $data['pwd']);
        }
        $authuserModel->startTrans();
        $res1 = true;
        if ($data['group_id']) {
            $authusergroupModel = new \Warehouse\Model\AuthusergroupModel();
            $usergroupInfo = $authusergroupModel->baseFind(array("uid" => $data['uid']));
            if ($usergroupInfo) {
                $res1 = $authusergroupModel->saveRow($data['uid'], $data['group_id']);
                $res1 = ($res1 === 0) ? true : false;
            } else {
                $res1 = $authusergroupModel->baseAdd(array("uid" => $data['uid'], "group_id" => $data['group_id']));
            }
        }
        $res2 = $authuserModel->baseSaveById($data);
        if ($res1 && $res2 !== false) {
            $authuserModel->commit();
            $this->ajaxSuccess("修改用户信息成功：" . $data['uid']);
        }
        $authuserModel->rollback();
        $this->ajaxError("修改用户信息失败：" . $data['uid'] . " " . $authuserModel->getError());
    }

    /**
     * easy to delete  users
     * 后续标记删除，加禁用功能
     */
    public function toDelete() {
        $ids = I("post.id", '');
        $authuserModel = new \Warehouse\Model\AuthuserModel();
        $superAdminInfo = $authuserModel->baseFind(array('uid'=>$this->superAdminUid));
        if($superAdminInfo){
            $idArr = explode(',', $ids);
            if(in_array($superAdminInfo['id'], $idArr)){
                $this->noOperateSuperAdmin($superAdminInfo['uid']);
            }
        }
        if (false !== $authuserModel->baseDelByIds($ids)) {
            $this->ajaxSuccess("删除用户成功");
        }
        $this->ajaxError("删除用户失败");
    }
    
    /**
     * 不允许操作超级管理员任何信息
     * @param type $uid
     */
    private function noOperateSuperAdmin($uid){
        if(trim($uid) == $this->superAdminUid){
            $this->ajaxError("操作不允许，请联系管理员");
        }
    }

}
