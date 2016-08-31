<?php

namespace Index\Controller;

class ToolsController extends WrapController {

    private $_menuCrumbsFirst = '在线工具';

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
        $this->ajaxError("无");
    }

    /**
     * 解析获取微信图片
     */
    public function fetchPic() {
        $menuCrumbs = array(
            "first" => $this->_menuCrumbsFirst,
            "second" => array("menuName" => "解析获取微信图片", "url" => "/Index/Tools/fetchPic"),
        );
        $this->assign("menuCrumbs", $menuCrumbs);
        $this->display("fetchPic");
    }

    /**
     * 解析获取微信图片逻辑
     */
    public function toFetchPic() {
        $type = I("post.type");
        vendor("QL.QueryList");
        $url = I("post.url", "", 'htmlspecialchars_decode');
//        $url = 'http://mp.weixin.qq.com/s?src=3&timestamp=1472605926&ver=1&signature=wuHKelj7G-MRUpQ4Rhwh56okkwzlUydmmjXyVPLWfrc-uc1*-LKFdFdh-ayvjSLuDDXNvDVbTBN2Xwjae1Yt5hJENj37dHlYNP7-V-K-nkRnJZiRa2ML6QQentQKpl5gGOPmqX2-0udmEKzTIRzJ-9IViftX66ZDHqdkaXWcKkc=';
        if (empty($url)) {
            $this->error("URL地址为空");
        }

        //去除微信页面源码头部干扰代码
        $opts = array("http" => array("timeout" => 30));
        $html = file_get_contents($url, 0, stream_context_create($opts));
        $html = str_replace("<!--headTrap<body></body><head></head><html></html>-->", "", $html);

        if ($type == 'video') {
            $rules = array(
                "title" => array("title", "text"),
                "url" => array("iframe.video_iframe", "data-src"),
            );
            $data = \QL\QueryList::Query($html, $rules)->getData();
            $videoUrls = array();
            $title = '';
            if ($data) {
                foreach ($data as $one) {
                    if ($one['title']) {
                        $title = $one['title'];
                    }
                    $videoUrls[] = $one['url'];
                }
            }
            $this->assign("urls", $videoUrls);
            $this->assign("title", $title);
            $this->display("showVideo");            
        } else {
            $rules = array(
                "title" => array("title", "text"),
                "img-src" => array("img", "src"),
                "img-data-src" => array("img", "data-src"),
            );
            $data = \QL\QueryList::Query($html, $rules)->getData();
//        print_r($data);exit;
            $newImages = array();
            $title = '';
            foreach ($data as $one) {
                if ($one['title']) {
                    $title = $one['title'];
                }
                if (empty($one['img-src']) && empty($one['img-data-src'])) {
                    continue;
                }
                if ($one['img-data-src']) {
                    $src = $one['img-data-src'];
                } elseif ($one['img-src']) {
                    $src = str_replace("webp", "jpg", $one['img-src']);
                }
                //通过QQ阅读作为跳板获取微信公众号图片
                $newImages[] = "http://read.html5.qq.com/image?src=forum&q=5&r=0&imgflag=7&imageUrl=" . $src;
            }

            $this->assign("imgs", $newImages);
            $this->assign("title", $title);
            $this->display("showPic");
        }
    }

}
