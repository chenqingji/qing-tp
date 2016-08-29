<?php

namespace Warehouse\Controller;

use Warehouse\Controller\WrapController;

/**
 * 后台权限管理
 */
class AuthruleController extends WrapController {

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
            "second" => array("menuName" => "权限规则列表", "url" => "/Warehouse/Authrule/index"),
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
        $sidx = I("param.sidx", 'pid');
        $sord = I("param.sord", 'asc');
        $where = $this->getSearchCondition();
        $field = "*";
        $authruleModel = new \Warehouse\Model\AuthruleModel();
        if (empty($sidx) || empty($sord)) {
            $sidx = "pid";
            $sord = "asc";
        }
        $order = array($sidx => $sord);
        echo json_encode($authruleModel->baseGetPage($where, $field, $page, $rows, $order), TRUE);
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
     * new a rule
     */
    public function toAdd() {
        $data['pid'] = I("post.pid");
        $data['name'] = I("post.name");
        $data['title'] = I("post.title");
        $data['status'] = I("post.status");
        //@todo name title  status   验证规则
        $authruleModel = new \Warehouse\Model\AuthruleModel();
        if ($this->parentHasParent($authruleModel, $data['pid'])) {
            $this->ajaxError("父节点不允许有子节点");
        }
        if ($authruleModel->baseAdd($data)) {
            $this->ajaxSuccess("成功添加规则：" . $data['title']);
        }
        $this->ajaxError("添加规则失败：" . $data['title'] . " " . $authruleModel->getError());
    }

    /**
     * edit rule
     */
    public function toEdit() {
        $data = [];
        $data['id'] = I("post.id", 0);
        $data['title'] = I("post.title", '', 'htmlspecialchars');
        $data['name'] = I("post.name", '', 'htmlspecialchars');
        $data['pid'] = I("post.pid");
        $data['status'] = I("post.status");
        if ($data['id'] == $data['pid']) {
            $this->ajaxError("父节点不能是自己");
        }
        $authruleModel = new \Warehouse\Model\AuthruleModel();
        if ($this->parentHasParent($authruleModel, $data['pid'])) {
            $this->ajaxError("父节点不允许有子节点");
        }

        $authruleModel->startTrans();
        $res1 = $authruleModel->baseSaveById($data);
        $res2 = true;
        if ($data['pid'] == 0) {
            $res2 = $authruleModel->updateChildrenStatus($data['id'], $data['status']);
        }
        if ($res1 !== false && $res2 !== false) {
            $authruleModel->commit();
            $this->ajaxSuccess("修改规则信息成功：" . $data['title']);
        }
        $authruleModel->rollback();
        $this->ajaxError("修改规则信息失败：" . $data['title'] . " " . $authruleModel->getError());
    }

    /**
     * 父节点是否还有父节点， 目前只允许有两层父子关系
     * @param \Warehouse\Model\AuthruleModel $authruleModel
     * @param type $pid
     */
    private function parentHasParent($authruleModel, $pid) {
        $list = $authruleModel->baseFind(array("id" => $pid));
        if (isset($list['pid']) && $list['pid'] != 0) {
            return true;
        }
        return false;
    }

    /**
     * easy to delete rule  
     * 后续标记删除
     */
    public function toDelete() {
        $ids = I("post.id", '');

        $authruleModel = new \Warehouse\Model\AuthruleModel();
        if (!is_int($ids)) {
            $idArray = implode($ids, ',');
            foreach ($idArray as $oneId) {
                if ($authruleModel->hasChildid($oneId)) {
                    $this->ajaxError("规则" . $oneId . "存在子节点，不允许删除");
                }
            }
        }
        if (false !== $authruleModel->baseDelByIds($ids)) {
            $this->ajaxSuccess("删除规则成功");
        }
        $this->ajaxError("删除规则失败");
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
            case "pid":
                $selectString = $this->getPidSelectData();
                break;
            default:
                break;
        }
        echo rtrim($selectString, ";");
        exit;
    }

    /**
     * 获取所有可能的父节点
     * @return string
     */
    private function getPidSelectData() {
        $authruleModel = new \Warehouse\Model\AuthruleModel();
        $rules = $authruleModel->baseGet(array(), array('id', "title", "pid"));
        $selectString = "0:选择父节点;";
        if ($rules) {
            foreach ($rules as $one) {
                if ($one['pid'] == 0) {
                    $selectString .= $one['id'] . ":" . $one['title'] . ";";
                }
            }
        }
        return $selectString;
    }

}
