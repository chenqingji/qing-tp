<?php

namespace Index\Controller;

use Index\Controller\WrapController;

/**
 * 系统用户组管理
 */
class AuthgroupController extends WrapController {

    /**
     * 系统用户组管理 一级菜单
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
            "second" => array("menuName" => "用户组列表", "url" => "/Index/Authgroup/index"),
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
        $field = "*";
        $authgroupModel = new \Index\Model\AuthgroupModel();
        if (empty($sidx) || empty($sord)) {
            $sidx = "create_time";
            $sord = "desc";
        }
        $order = array($sidx => $sord);
        echo json_encode($authgroupModel->baseGetPage($where, $field, $page, $rows, $order), TRUE);
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
     * new a user group
     */
    public function toAdd() {
        $data['title'] = I("post.title");
        $data['status'] = I("post.status");
        //@todo title  status   验证规则

        $authgroupModel = new \Index\Model\AuthgroupModel();
        if ($authgroupModel->baseAdd($data)) {
            $this->ajaxSuccess("成功添加用户组：" . $data['title']);
        }
        $this->ajaxError("添加用户组失败：" . $data['title'] . " " . $authgroupModel->getError());
    }

    /**
     * edit user group info
     */
    public function toEdit() {
        $data = [];
        $data['id'] = I("post.id", 0);
        $data['title'] = I("post.title", '', 'htmlspecialchars');
        $data['status'] = I("post.status");
        $authgroupModel = new \Index\Model\AuthgroupModel();
        if (false !== $authgroupModel->baseSaveById($data)) {
            $this->ajaxSuccess("修改用户组信息成功：" . $data['title']);
        }
        $this->ajaxError("修改用户组信息失败：" . $data['title'] . " " . $authgroupModel->getError());
    }

    /**
     * easy to delete user groups
     * 后续标记删除
     */
    public function toDelete() {
        $ids = I("post.id", '');
        $authgroupModel = new \Index\Model\AuthgroupModel();
        if (false !== $authgroupModel->baseDelByIds($ids)) {
            $this->ajaxSuccess("删除用户组成功");
        }
        $this->ajaxError("删除用户组失败");
    }

    /**
     * 获取各类下拉框数据
     */
    public function getSelectData() {
        $type = I("post.type", "", "strtolower");
        $selectString = "";
        switch ($type) {
            case "status":
//                $selectString = ":状态;";
                $fromArray = array(1 => "开启", 0 => "禁用");
                foreach ($fromArray as $index => $one) {
                    $selectString .= $index . ":" . $one . ";";
                }
                break;
            case "title":
                $selectString = ":用户组;";
                $authgroupModel = new \Index\Model\AuthgroupModel();
                $lists = $authgroupModel->baseGet(array(), array("id", "title"));
                foreach ($lists as $one) {
                    $selectString .= $one['id'] . ":" . $one['title'] . ";";
                }
                break;
            default:
                break;
        }
        echo rtrim($selectString, ";");
        exit;
    }

    /**
     * 获取所有权限规则
     */
    public function getAllRules() {
        $id = I("post.id");
        if ($id) {
            $authruleModel = new \Index\Model\AuthruleModel();
            $rules = $authruleModel->baseGet();
            $newRules = array();
            if ($rules) {
                $authgroupModel = new \Index\Model\AuthgroupModel();
                $groupInfo = $authgroupModel->baseFind(array("id" => $id));
                $groupRules = explode(',', $groupInfo['rules']);
                foreach ($rules as $one) {
                    if (in_array($one['id'], $groupRules)) {
                        $one['checked'] = true;
                    } else {
                        $one['checked'] = false;
                    }
                    if ($one['pid'] == 0) {
                        $newRules[$one['id']]['self'] = $one;
                    } else {
                        $newRules[$one['pid']]['list'][] = $one;
                    }
                }
            }
            $this->ajaxSuccess($newRules);
        }
        $this->ajaxError("用户组不存在");
    }

    /**
     * update group's rules
     */
    public function updateRules() {
        $data['id'] = I("post.id");
        $data['rules'] = rtrim(I("post.rules"), ',');
        if (!empty($data['rules'])) {
            $authgroupModel = new \Index\Model\AuthgroupModel();
            if ($authgroupModel->baseSaveById($data)) {
                $this->ajaxSuccess("成功更新权限");
            }
        }
        $this->ajaxError("更新权限失败");
    }

}
