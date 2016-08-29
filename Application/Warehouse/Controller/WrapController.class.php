<?php

namespace Warehouse\Controller;

use Warehouse\Model\UserModel;
use Think\Controller;

/**
 * 管理后台基类
 */
class WrapController extends Controller {

    /**
     * 手动设置cookie时间
     */
    const COOKIE_LIFE_TIME = 7200;

    /**
     * super admin uid
     * @var type 
     */
    protected $superAdminUid = "starryadmin";

    /**
     * username 
     * @var string 
     */
    private $_userName = "admin";

    /**
     * user pwd
     * @var string
     */
    private $_userPwd = "chinese";

    /**
     * 操作密码
     * @var type 
     */
    private $_operation_pwd = "XL123456";

    /**
     * 管理后台继承类
     */
    public function __construct() {
        parent::__construct();
        header("Content-Type:text/html; charset=utf-8");
        if ($this->allowAccess() || $this->checkLogin()) {
            $this->assign("user", array('username' => session('adminName'), 'uid' => session('adminUid')));
        } else {
            if (IS_AJAX) {
                $this->ajaxError("<a href='" . U('Warehouse/Admin/login') . "'>请重新登录</a>");
            }
            $this->error('请重新登录', U('Warehouse/Admin/login'));
        }
    }

    /**
     * 是否可以直接访问
     * 在confi配置文件中直接配置
     * @return boolean
     */
    private function allowAccess() {
        $allowAccessModule = C("ALLOW_ACCESS_MODULE");
        if (array_key_exists(CONTROLLER_NAME, $allowAccessModule)) {
            if ($allowAccessModule[CONTROLLER_NAME] == "*") {
                return true;
            }
            if (in_array(ACTION_NAME, $allowAccessModule[CONTROLLER_NAME])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检测是否拥有功能模块权限
     * starryadmin作为超级管理员
     */
    private function checkRightRule() {
        $uid = session("adminUid");
        if ($uid == 'starryadmin') {
            return true;
        }
        $auth = new \Think\Auth();
        $rule_name = MODULE_NAME . '/' . CONTROLLER_NAME . '/' . ACTION_NAME;
        if (!$auth->check($rule_name, $uid)) {
            if (IS_AJAX) {
                $this->ajaxError("目前不具备该功能权限");
            } else {
                $this->error('目前不具备该功能权限', U('Warehouse/Admin/index'));
            }
        }
    }

    /**
     * 检测终端ip是否在指定ip范围内
     * @return boolean
     */
    private function checkAccessIp() {
        $remoteIp = I("server.REMOTE_ADDR");
        $remoteIpInt = ip2long($remoteIp);
        $allowAccessIp = C("ALLOW_ACCESS_IP");
        foreach ($allowAccessIp as $key => $value) {
            if ("section" == $key) {
                foreach ($value as $one) {
                    $startIpInt = ip2long($one['start']);
                    $endIpInt = ip2long($one['end']);
                    if ($remoteIpInt >= $startIpInt && $remoteIpInt <= $endIpInt) {
                        return true;
                    }
                }
            }
            if ("in" == $key && in_array($remoteIp, $value)) {
                return true;
            }
        }
        $this->error($remoteIp . '不在IP范围内', U('Warehouse/Admin/login'));
    }

    /**
     * 检查登录状态
     * @param boolean $writeSession 是否写入session，第一次登录时写入,其他地方暂时不能写入
     * @return boolean
     */
    protected function checkLogin($writeSession = false) {
//        $this->checkAccessIp();
        if ($writeSession) {
            $uid = I('post.uid', I('get.uid'), "trim");
            $pwd = I('post.password', I('get.password'), "trim");
        } else {
            $uid = session('adminUid');
            $pwd = session('adminPwd');
        }
//        if (empty($name) || empty($pwd)) {
//                $name = cookie('adminName');
//                $pwd = cookie('adminPwd');
//        }

        $userModel = new \Warehouse\Model\AuthuserModel();
        $userInfo = $userModel->baseFind(array("uid" => $uid));
        if (!empty($uid) && !empty($pwd) && $this->generatePassword($userInfo['salt'], $pwd) == $userInfo['pwd']) {
            if ($writeSession) {
                //@todo 按理不存储明码  建议编码存储user对象
                session("adminUid", $uid);
                session('adminName', $userInfo['nickname']);
                session('adminPwd', $pwd);
//                $expire = time() + self::COOKIE_LIFE_TIME;
//                setcookie("adminName", $name, $expire, "/");
//                setcookie("adminPwd", $pwd, $expire, "/");
            }
            $this->checkRightRule();
            return true;
        }
        return false;
    }

    /**
     * 获取随机salt
     * @return string
     */
    protected function generateSalt() {
        $type = 0;
        $length = 6;
        $arr = array(1 => "0123456789", 2 => "abcdefghijklmnopqrstuvwxyz", 3 => "ABCDEFGHIJKLMNOPQRSTUVWXYZ", 4 => "~@#$%^&*(){}[]|");
        if ($type == 0) {
            //不需要特殊符号
            array_pop($arr);
            $string = implode("", $arr);
        } elseif ($type == "-1") {
            //所有符号
            $string = implode("", $arr);
        } else {
            //指定符号 1 2 3 4
            $string = $arr[$type];
        }
        $count = strlen($string) - 1;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $string[rand(0, $count)];
        }
        return $code;
    }

    /**
     * user pwd rule
     * @param type $salt
     * @param type $pwd
     * @return type
     */
    protected function generatePassword($salt, $pwd) {
        return md5($salt . $pwd);
    }

    /**
     * user openid rule
     * @param type $uid
     * @return type
     */
    protected function generateOpenId($uid) {
        return md5($uid);
    }

    private function _isMenuOpen($menu) {
        foreach ($menu['submenu'] as $menu) {
            if ($menu['select'] || $this->_isMenuOpen($menu)) {
                return true;
            }
        }
        return false;
    }

    private function _outputMenuHtml($menu) {
        $activeHtml = $menu['select'] ? 'class="active"' : '';
        // 判断是否展开
        $openHtml = '';
        $submenudisplay = '';
        if (!$menu['select']) {
            $openHtml = $this->_isMenuOpen($menu) ? 'class="open"' : '';
            $submenudisplay = empty($openHtml) ? '' : 'style="display:block"';
        }

        $styleHtml = '';
        $str = '<li %s %s %s ><a href="%s" target="%s" class="dropdown-toggle"><i class="%s"></i><span class="menu-text">%s</span>';
        $str = sprintf($str, $openHtml, $activeHtml, $styleHtml, $menu['url'], $menu['target'], $menu['icon'], $menu['title']);
        if (count($menu['submenu']) > 0) {
            // 存在子节点
            $str = $str . '<b class="arrow icon-angle-down"></b></a><ul class="submenu" ' . $submenudisplay . '>';
            foreach ($menu['submenu'] as $submenu) {
                $str = $str . $this->_outputMenuHtml($submenu);
            }
            $str = $str . '</ul></li>';
        } else {
            $str = $str . "</a></li>";
        }
        return $str;
    }

    // 选中某个菜单
    public function selectMenu($path, $menus) {
        // 遍历所有菜单，将指定$title置为选中
        foreach ($menus as $key => $menu) {
            if (count($menus[$key]['submenu']) > 0) {
                $menus[$key]['submenu'] = $this->selectMenu($path, $menus[$key]['submenu']);
            }
            $menus[$key]['select'] = false;
            foreach ($menus[$key]['path'] as $k => $v) {
                /* echo "<pre>";var_dump($v,$path); */
                if (0 == strcasecmp($v, $path)) {
                    $menus[$key]['select'] = true;
                }
            }
            /* if( $menus[$key]['path'] == $path) {
              $menus[$key]['select'] = true;
              } */
        }

        return $menus;
    }

    protected function display($templateFile = '', $charset = '', $contentType = '', $content = '', $prefix = '') {
        // 生成菜单
        $menus = C("MENUS");
        $menus = $this->selectMenu(__ACTION__, $menus);

        $str = '<ul class="nav nav-list">';
        foreach ($menus as $menu) {
            $str = $str . $this->_outputMenuHtml($menu);
        }
        $str = $str . '</ul>';
        $this->assign("menus", $str);

        //$this->assign("bbs",$bbs);
        parent::display($templateFile, $charset, $contentType, $content, $prefix);
    }

    protected function getNavPage($totalCount, $page, $pageLimitCount, $showPageCount) {
        $pageTotalCount = intval(ceil($totalCount / $pageLimitCount));
        $i = 0;
        $pageList = array();
        $pageList[] = $page;

        do {
            ++$i;
            if ($pageTotalCount >= $i + $page && --$showPageCount > 0) {
                $pageList[] = $i + $page;
            }
            if (0 < $page - $i && --$showPageCount > 0) {
                $pageList[] = $page - $i;
            }
        } while (($pageTotalCount >= $i + $page || 0 < $page - $i) && $showPageCount > 0);
        sort($pageList);

        return array(
            $pageTotalCount,
            $pageList
        );
    }

    /**
     * ajax 请求错误输出
     * @param type $data
     * @param type $status
     */
    protected function ajaxError($data, $status = 0) {
        $this->ajaxReturn(array("status" => $status, "data" => $data));
    }

    /**
     * ajax请求成功输出
     * @param type $data
     * @param type $status
     */
    protected function ajaxSuccess($data, $status = 1) {
        $this->ajaxReturn(array("status" => $status, "data" => $data));
    }

    /**
     * 操作错误跳转的快捷方法 与error方法的区别在于：跳转前调用了setSceneData方法
     * @access protected
     * @param string $message 错误信息
     * @param string $jumpUrl 页面跳转地址
     * @param mixed $ajax 是否为Ajax方式 当数字时指定跳转时间
     * @param array $data 场景还原数组，默认param参数
     * @return void
     */
    protected function errorWithScene($message = '', $jumpUrl = '', $ajax = false, $data = array()) {
        if (empty($data)) {
            $data = I("param.");
        }
        $this->setSceneData($data);
        $this->error($message, $jumpUrl, $ajax);
    }

    /**
     * 保存当前会话的临时数据
     * 一般用于页面错误，临时保存表单数据
     * @param mixed $data 数据
     */
    protected function setSceneData($data) {
        if (empty($data)) {
            $data = I("param.");
        }
        session('scene_form_data', json_encode($data));
    }

    /**
     * 获取当前会话中的临时数据
     * 一般用于重新渲染页面时，获取用户填写过的数据
     * @return mixed string|array(object->array)
     */
    protected function getSceneData() {
        $data = json_decode(session("scene_form_data"), true);
        $this->cleanSceneData();
        return $data;
    }

    /**
     * 清除当前会话中的临时数据
     */
    protected function cleanSceneData() {
        session("scene_form_data", null);
    }

    /**
     * 组合所有查询条件
     * @return array
     */
    protected function getSearchCondition() {
        //filters:{"groupOp":"OR","rules":[{"field":"id","op":"eq","data":"1"},{"field":"category_id","op":"ne","data":"123"}]}
        //_search:true
        $condition = array();
        if (I("param._search", '')) {
            $filters = I("param.filters", '', 'htmlspecialchars_decode');
            if (!empty($filters)) {
                $filters = json_decode($filters, true);
                if (in_array($filters['groupOp'], array('AND', 'OR'))) {
                    $condition['_logic'] = $filters['groupOp'];
                    $rules = $filters['rules'];
                    foreach ($rules as $one) {
                        if (!empty($condition[$one['field']])) {
                            //相同字段至少有2个条件以上
                            if (is_array($condition[$one['field']][0])) {
                                $condition[$one['field']][] = $this->getOneCondition($one['op'], $one['data']);
                            } else {
                                //相同字段已经有一个条件
                                $tmpArray = $condition[$one['field']];
                                unset($condition[$one['field']]);
                                $condition[$one['field']][] = $tmpArray;
                                $condition[$one['field']][] = $this->getOneCondition($one['op'], $one['data']);
                            }
                        } else {
                            //相同字段的第一个条件
                            $condition[$one['field']] = $this->getOneCondition($one['op'], $one['data']);
                        }
                    }
                }
            }
        }
        return $condition;
    }

    /**
     * 组合最小查询条件
     * @param string $op and or
     * @param strig $data search string
     * @return array
     */
    protected function getOneCondition($op, $data) {
        $opMap = array(
            "eq" => 'eq', "ne" => "neq", 'lt' => 'lt', 'le' => 'elt', 'gt' => 'gt', 'ge' => 'egt', 'bw' > 'like',
            'bn' => 'notlike', 'ew' => 'like', 'en' => 'notlike', 'in' => 'in', 'ni' => 'not in', 'cn' => 'like', 'nc' => 'notlike'
        );
        switch ($op) {
            case 'bw':
            case 'bn':
                $data = $data . "%";
                break;
            case 'ew':
            case 'en':
                $data = "%" . $data;
                break;
            case 'cn':
            case 'nc':
                $data = "%" . $data . "%";
                break;
            default:
                break;
        }
        return array($opMap[$op], $data);
    }

    /**
     * 获取所有普通品类编号
     * @todo 通用服务归类处理，后期加入缓存机制
     * @return array 
     */
    public function getAllCategoryId() {
        $categoryModel = new \Warehouse\Model\CategoryModel();
        $list = $categoryModel->baseGet(array(), array("category_id", "category_name"));
        $newList = array();
        foreach ($list as $one) {
            $newList[$one['category_id']] = $one['category_name'];
        }
        return $newList;
    }

    /**
     * 获取规格品 自增id 
     * @param string $productId 品类id
     * @return int |false
     */
    protected function getGoodsIdByProductId($productId) {
        $categoryModel = new \Warehouse\Model\CategoryModel();
        $res = $categoryModel->baseFind(array("category_id" => $productId));
        return isset($res['id']) ? $res['id'] : false;
    }

    /**
     * 检查操作密码
     * @param type $pwd
     * @return boolean
     */
    protected function checkOperationPwd($pwd) {
        if ($this->_operation_pwd == trim($pwd)) {
            return true;
        }
        return false;
    }

}
