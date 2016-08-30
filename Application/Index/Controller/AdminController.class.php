<?php

namespace Index\Controller;

class AdminController extends WrapController {

    /**
     * home path Zone/index
     * @var type 
     */
    private $_homePath = "Zone/index";

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
        $this->success('登录成功,正跳转至系统首页...', U($this->_homePath));
    }

    /**
     * 登录页面
     */
    public function login() {
        $this->display("Admin/login");
    }

    /**
     * 登录逻辑
     * @param string $path 跳转路径
     */
    public function toLogin() {
        $this->checkVerifyCode();
        if ($this->checkLogin(true)) {
            $this->success('登录成功,正跳转至系统首页...', U($this->_homePath));
        } else {
            $this->error('登录失败,用户名或密码不正确!', U('login'));
        }
    }

    /**
     * 获取验证码
     */
    public function getVerifyCode() {
        $verify = new \Think\Verify();
        $verify->entry();
    }

    /**
     * 检验验证码
     * @param string $code 验证码字符串
     * @return boolean
     */
    private function checkVerifyCode($code = '') {
        if (empty($code)) {
            $code = I("post.code", "");
        }
        $verify = new \Think\Verify();
        if (!$verify->check($code)) {
            $this->error('验证码错误', U("login"));
        }
    }

    /**
     * 登出系统
     * @todo 清除当前会话所有session变量
     */
    public function logOut() {
//        session('adminName', null);
//        session('adminPwd', null);
        session(null);
//        cookie("adminName", null);
//        cookie("adminPwd", null);
        $this->success('成功退出系统', U('login'));
    }

}
