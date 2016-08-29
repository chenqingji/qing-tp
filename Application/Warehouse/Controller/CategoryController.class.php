<?php

namespace Warehouse\Controller;

use Warehouse\Model\CategoryModel;

/**
 * 普通品类管理
 */
class CategoryController extends WrapController {

    /**
     * 页面面包屑第一级文字
     * @var string 
     */
    private $_menuCrumbsFirst = "品类管理";

    /**
     * construct
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * empty action
     */
    public function _empty() {
        $this->error("操作有误，请联系管理员");
    }

    /**
     * 品类列表
     */
    public function index() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "普通品类列表", "url" => "/Warehouse/Category/index"),
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
        $field = array(
            "id", "category_id" => "code", "category_name" => "name", "unit_price" => "price", "weight", "shelf_no" => "shelf",
            "zone_start" => "start", "desc", "max_count" => "max", "cur_count" => "cur", "total_in_count" => "incount",
            "total_out_count" => "outcount", "create_time" => "create", "update_time" => "update", "offline",
        );
        $categoryModel = new CategoryModel();
        if (empty($sidx) || empty($sord)) {
            $sidx = "create_time";
            $sord = "desc";
        }
        $order = array($sidx => $sord);
        echo json_encode($categoryModel->baseGetPage($where, $field, $page, $rows, $order), TRUE);
        exit;
    }

    /**
     * 获取货架编号数据供下拉框使用
     * @param type $isAjax 
     * @return type
     */
    public function getShelfs($isAjax = TRUE) {
        $categoryModel = new CategoryModel();
        $shelfs = $categoryModel->baseGet(array(), array("shelf_no"));
        $newShelfs = array();
        foreach ($shelfs as $key => $one) {
            $newShelfs[] = $one['shelf_no'];
        }
        $zoneList = C("NORMAL_ZONE");

        if ($isAjax) {
            $shelfString = ":选择编号;";
            foreach ($zoneList as $shelfNo => $one) {
                $shelfString .= $shelfNo . ":" . $shelfNo . ";";
//                if (!in_array($shelfNo, $newShelfs)) {
//                    $shelfString .= $shelfNo . ":" . $shelfNo . ";";
//                }
            }
            echo rtrim($shelfString, ";");
            exit;
        } else {
            foreach ($zoneList as $shelfNo => $one) {
                if (in_array($shelfNo, $newShelfs)) {
                    unset($zoneList[$shelfNo]);
                }
            }
            return $zoneList;
        }
//        echo ":选择编号;001:001;002:002;003:003;004:004";exit;
    }

    /**
     * 增删改 操作
     */
    public function operation() {
        $oper = I("post.oper", null);
        if (!empty($oper)) {
            switch ($oper) {
                case 'add':
                    $this->toAdd(TRUE);
                    break;
                case 'edit':
                    $this->toEdit(TRUE);
                    break;
                case 'del':
                    $this->toDelete(TRUE);
                    break;
                default:
                    echo "other";
                    exit;
                    break;
            }
        }
    }

    /**
     * 新增品类页面
     */
    public function add() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "新增普通品类", "url" => "/Warehouse/Category/add"),
        );
        $this->assign("zoneList", $this->getShelfs(false));
        $sceneData = $this->getSceneData();
        if ($sceneData) {
            $this->assign("sceneData", $sceneData);
        }
        $this->assign("menuCrumbs", $menuCrumbs);
        $this->display("add");
    }

    /**
     * 添加品类逻辑
     */
    public function toAdd($isAjax = false) {
        $data = array();
        $data['category_id'] = I("post.code", '', 'htmlspecialchars');
        $data['category_name'] = I("post.name", '', "htmlspecialchars");
        $data['unit_price'] = I("post.price", 0);
        $data['weight'] = I("post.weight", 0);
        $data['shelf_no'] = I("post.shelf", '');
        $data['zone_start'] = I("post.start", '');
        $data['max_count'] = I("post.max", 0);
        $data['desc'] = I("post.desc", '', 'htmlspecialchars');

        $categoryModel = new CategoryModel();
        $data['offline'] = 1;//默认下架状态
        $res = $categoryModel->baseAdd($data);
        if ($res) {
            $this->success("添加品类成功", U("index"), $isAjax);
        } else {
            if (!$isAjax) {
                $this->setSceneData(I("post."));
            }
            $this->error("添加品类失败 " . $categoryModel->getError(), U("add", array("r" => 1)), $isAjax);
        }
    }

    /**
     * 更新品类页面
     * @todo 下拉选择货架编号，自动生成货架编号起始位置
     */
    public function edit($id = 0) {
        $id = $id ? $id : I("request.id", 0);
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "编辑普通品类", "url" => "/Warehouse/Category/edit"),
        );
        if ($sceneData = $this->getSceneData()) {
            $this->assign("sceneData", $sceneData);
        } elseif ($id) {
            $categoryModel = new CategoryModel();
            $res = $categoryModel->getByIds($id);
            $list = null;
            if (is_array($res)) {
                $list = array_shift($res);
            }
            $this->assign("sceneData", $list);
        } else {
            $this->error("品类记录不存在", U("index"));
        }
        $this->assign("menuCrumbs", $menuCrumbs);
        $this->display("edit");
    }

    /**
     * 更新品类逻辑
     */
    public function toEdit($isAjax = false) {
        $data = array();
        $data['id'] = I("post.id", 0);
        $data['category_id'] = I("post.code", '', 'htmlspecialchars');
        $data['category_name'] = I("post.name", '', 'htmlspecialchars');
        $data['unit_price'] = I("post.price", 0);
        $data['weight'] = I("post.weight", 0);
        $data['shelf_no'] = I("post.shelf", '');
        $data['zone_start'] = I("post.start", '');
        $data['max_count'] = I("post.max", 0);
        $data['offline'] = I("post.offline", 0);
        $data['desc'] = I("post.desc", '', 'htmlspecialchars');
        $categoryModel = new CategoryModel();
        $res = $categoryModel->baseSaveById($data);
        if ($res !== false) {
            $this->success("编辑品类成功", U("index"), $isAjax);
        } else {
            if (!$isAjax) {
                $this->setSceneData(I("post."));
            }
            $this->error("编辑品类失败 " . $categoryModel->getError(), U("edit", array("r" => 1)), $isAjax);
        }
    }

    /**
     * 品类删除 暂时屏蔽不再是用
     * @param boolean $isAjax
     * @todo 判断品类是否有存在库存等关联信息
     */
//    public function toDelete($isAjax = false) {
//        $ids = I("post.id", '');
//        $categoryModel = new CategoryModel();
//        //@todo判断品类是否有存在库存等关联信息
//        $res = $categoryModel->baseDelByIds($ids);
//        if (FALSE !== $res) {
//            $this->success("删除成功", U("index"), $isAjax);
//        } else {
//            $this->error("删除失败 " . $categoryModel->getError(), U("index"), $isAjax);
//        }
//    }
    
    /**
     * 品类 上架或下架操作
     */
    public function toSetLine(){
        $id = I("post.id", '');
        $cid = I("post.cid", '');
        $action = I("post.action");
        $categoryModel = new CategoryModel();
        //@todo判断品类是否有存在库存等关联信息
        $res = $categoryModel->setLine($id,$cid,$action);
        $label = array("0"=>"上架","1"=>"下架");
        if (!empty($res)) {
            $this->ajaxSuccess($label[$action]."成功");
        } else {
            $this->ajaxError($label[$action]."失败".$categoryModel->getError());
        }        
    }

}
