<?php
namespace Index\Model;
use Think\Model;
class AddImageModel extends Model {
	Protected $autoCheckFields = false;
	
	private $wx_appid="wx24acf51620f63ce6";
	private $wx_appsecret = "5219b33e5fdd76a66e4dbb40c8cf2296";
	
	private $image_mediaid = "njNyT4dxOM-dD-gViEbO7g9hdlBSeo4RTOuUK-qF76E";	//图片的media_id
	
	public function __construct() {
		parent::__construct();
	
	}
	
	//获得永久图文素材
	public function getMaterialNews($media_id){
	    //$materianews_mediaid = "njNyT4dxOM-dD-gViEbO7g1qcIzYU28JBLvUx3f6bXk";//永久图文素材的media_id
		$url = "https://api.weixin.qq.com/cgi-bin/material/get_material?access_token=".$this->token();
		$json = '{"media_id":"'.$media_id.'"}';
		$ret = $this->https_request($url, $json);
		$ret1 = json_decode($ret);
		foreach($ret1->news_item as $row) {
			/* $arr = array(
					'url' => ".$row->url",
					"time"	=> date("h:i:s",time()),
					'name' => '轻展示1'
			); */
			$url = $row->url;
			$wx_name = 'qingzhanshi2';
			$url = str_replace("#rd", "#wechat_redirect", $url);
			$arr = array(
			 		'url' => $url,
					"time"	=> date("h:i:s",time()),
					'name' => $wx_name
			); 
			$m = M("moli_wxat_info");
			$m->data($arr)->add();
			
			// 设置缓存
			S($wx_name,$url);
			
			echo "success";
		} 
	}
	
	//新增永久图文素材
	public function MaterialAddNews(){
		$access_token=$this->token();
    	$url="https://api.weixin.qq.com/cgi-bin/material/add_news?access_token={$access_token}";
    	$thum_media_id = $this->image_mediaid;
     	$content = "<div class='rich_media_content' id='js_content'>";
    	$content .= "<p style='white-space: normal; line-height: 2em;'><span style='color: rgb(255, 0, 0); font-size: 24px;'>";
    	$content .= "<strong><img class='rich_media_thumb' data-src='http://mmbiz.qpic.cn/mmbiz/D6DbHxfkvN1dgeicydXASLtOCDFbZxOTiat0gFIyexU9ojfA1a04IRPob4Yoicp1pALu3bM8XSON4CdMOURfUMOMg/0?wx_fmt=gif&amp;tp=webp&amp;wxfrom=5' data-ratio='0.27435387673956263' data-w='' src='http://mmbiz.qpic.cn/mmbiz/D6DbHxfkvN1dgeicydXASLtOCDFbZxOTiat0gFIyexU9ojfA1a04IRPob4Yoicp1pALu3bM8XSON4CdMOURfUMOMg/0?wx_fmt=gif&amp;wxfrom=5&amp;tp=webp&amp;wx_lazy=1' style='width: auto !important; visibility: visible !important; height: auto !important;'></strong>";
    	$content .= "</span></p><p style='white-space: normal; line-height: 2em;'><span style='color: rgb(255, 0, 0); font-size: 24px;'>";
    	$content .= "<strong><img data-type='gif' data-src='http://mmbiz.qpic.cn/mmbiz/4dobpG7aXYSeD8vRdYbsy2MP9RAk2Ciaoqc7rbD3P0vQicqOvc2xzEnEF7Po8k5VPerrcS9Zr2FicQW4BYxqUHvicw/0?wx_fmt=gif' data-ratio='0.536779324055666' data-w='' url='http://mmbiz.qpic.cn/mmbiz/4dobpG7aXYSeD8vRdYbsy2MP9RAk2Ciaoqc7rbD3P0vQicqOvc2xzEnEF7Po8k5VPerrcS9Zr2FicQW4BYxqUHvicw/0?wx_fmt=gif' file_id='208312835' format='gif' source='upload' src='http://mmbiz.qpic.cn/mmbiz/4dobpG7aXYSeD8vRdYbsy2MP9RAk2Ciaoqc7rbD3P0vQicqOvc2xzEnEF7Po8k5VPerrcS9Zr2FicQW4BYxqUHvicw/0?wx_fmt=gif&amp;tp=webp&amp;wxfrom=5&amp;wx_lazy=1' style='width: auto !important; visibility: visible !important; height: auto !important;'>";
    	$content .= "<br></strong></span><strong style='color: rgb(255, 0, 0); font-size: 24px; line-height: 2em;'>制作专属于你的音乐相册！</strong></p><p style='white-space: normal; line-height: 1.5em;'>";
    	$content .="<span style='font-size: 18px;'>关注生活灵感，微信号：</span><strong>";
    	$content .="<span style='color: rgb(255, 0, 0); font-size: 18px;'>lglg365</span>";        
    	$content .="</strong></p>
                        <p style='white-space: normal;'>
                        生活灵感，用心灵一隅记录下那些美好的时光
                        </p>
                    </div>"; 
     	$news[] = array(
     			"thumb_media_id"=>$thum_media_id,
     			"author"=>"", 
     			"title"=>"制作音乐相册↓↓↓", 
     			"content_source_url"=>"moli.com",
     			"content"=>$content, 
     			"digest"=>"摘要",
     			 "show_cover_pic"=>"0"	//是否显示封面，0为false，即不显示，1为true，即显示
     			);
     	//防止中文乱码
     	foreach ($news as &$item){
     		foreach ($item as $k=>$v){
     			$item[$k] = urlencode($v);
     		}
     	}
    	$json = json_encode(array("articles"=>$news));
    	$json= urldecode($json);
    	$json= htmlspecialchars_decode($json);
    	
    	$row=$this->post($url, $json);
    	$media_id =  $row->media_id;
    	$this->getMaterialNews($media_id);
		//return $row->media_id;
	}
	
	
	//新增永久图片素材，返回media_id
	public function addImage(){
		$file_info=array(
				'filename'=>'/Public/Image/m1/head/1.jpg',  
				'content-type'=>'image/jpg',  
				'filelength'=>'11011'         
		);
		$real_path="{$_SERVER['DOCUMENT_ROOT']}{$file_info['filename']}";
		$url = "http://api.weixin.qq.com/cgi-bin/material/add_material?access_token=".$this->token()."&type=image";
		$josn=array('media'=>"@{$real_path}");
		$row=$this->post($url, $josn);
		echo "media_id:".$row->media_id;
	}
	// 支持post
	public function post($url,$data)
	{
		$jsonData = $this->https_request($url,$data);
		$result = json_decode($jsonData);
		return $result;
	}
	//请求
	function https_request($url, $data = null) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		if(!empty($data)) {
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($curl);
		curl_close($curl);
		return $output;
	}
	//获取token
	function token() {
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->wx_appid."&secret=".$this->wx_appsecret;
		$data = json_decode(file_get_contents($url), true);
		if($data['access_token']) {
			return $data['access_token'];
		} else {
			echo "Error";
			exit();
		}
	}
	
   
}