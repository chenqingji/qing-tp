<?php

namespace Index\Controller;

use Index\Model\CardAnalyzeModel;

use Think\Controller;

class WxController extends Controller {
	private $wxToken = "molitoken8799908"; // 微信token
	private $wxName = "moli";			   //来自于哪个公众号
	
	public function __construct() {
		parent::__construct ();
	}
	
	public function run() {
		$this->wxName = I("get.f","moli");
		if ($_SERVER ['REQUEST_METHOD'] == "POST") {
			$this->responseMsg ();
		} else {
			$this->valid ();
		}
	}
	function checkSignature() {
		$signature = I ( 'get.signature', '' );
		$timestamp = I ( 'get.timestamp', '' );
		$nonce = I ( 'get.nonce', '' );
		$token = $this->wxToken;
		$tmpArr = array (
				$token,
				$timestamp,
				$nonce 
		);
		sort ( $tmpArr, SORT_STRING );
		$tmpStr = implode ( $tmpArr );
		$tmpStr = sha1 ( $tmpStr );
		
		if ($tmpStr == $signature) {
			return true;
		} else {
			return false;
		}
	}
	private function valid() {
		$echoStr = I ( "get.echostr" );
		if ($this->checkSignature ()) {
			echo $echoStr;
			exit ();
		}
	}
	
	// $fromUsername = 发送者即OpenID $toUsername = 公众号
	public function responseMsg() {
		$postStr = file_get_contents ( "php://input" );
		if (! empty ( $postStr )) {
			$postObj = simplexml_load_string ( $postStr, 'SimpleXMLElement', LIBXML_NOCDATA );
			$fromUsername = $postObj->FromUserName;
			$toUsername = $postObj->ToUserName;
			$msgId = $postObj -> MsgId;
			//$this->checkRepeatMsg($msgId,$fromUsername,$toUsername);
			
			if ($postObj->MsgType == 'event') {
				if ($postObj->Event == 'subscribe') {
                    $keyword = $postObj->EventKey;
                    if(!empty($keyword)) {
                        $this->sendTextMsg("扫描二维码关注事件(code:$keyword)", $fromUsername, $toUsername);
                    } else {
                        $this->subscribeReply ( $fromUsername, $toUsername );
                    }
				} else if ($postObj->Event == 'unsubscribe') {
					// TODO 取消关注进行的操作
				} else if ($postObj->Event == 'CLICK' || $postObj->Event == 'click') {
					$keyword = $postObj->EventKey;
					$this->keywordReply ( $keyword, $fromUsername, $toUsername );
				} else if ($postObj->Event == 'pic_weixin' || $postObj->Event == 'PIC_WEIXIN') {
					//自定义菜单创建新相册
					$keyword = $postObj->EventKey;
					$this->picUpReply ( $keyword, $fromUsername, $toUsername );
				} else if ($postObj->Event == 'SCAN'|| $postObj->Event == 'scan') {
                    $this->sendTextMsg("扫描二维码进入事件(code:$postObj->EventKey)", $fromUsername, $toUsername);
                }
			} elseif ($postObj->MsgType == 'location') {
				// 用户发送地理位置，暂无特别操作
			} elseif ($postObj->MsgType == 'image') {
				// 用户进行图像发送
				$wx_picurl = $postObj->PicUrl;
				$this->imageReply ( $wx_picurl, $fromUsername, $toUsername );
			} else {
				// 关键词回复
				$keyword = trim ( $postObj->Content );
				$this->keywordReply ( $keyword, $fromUsername, $toUsername );
			}
		} else {
			echo "";
			exit ();
		}
	}
	
	// 被关注自动回复
	function subscribeReply($openid = "demo", $gh = "demo") {
		$this->sendTextMsg("亲，感谢您的关注，给我发照片吧，立即免费为您生成超酷超炫的音乐相册", $openid, $gh);
	}
	
	// 图片回复
	function imageReply($picUrl, $openid = "demo", $gh = "demo", $type = "photo") {
		if ($picUrl == "") {
			// 没有找到图片
			$this->sendTextMsg("亲，您的图片添加失败，请重新上传一张。", $openid, $gh);
		}
		
		$wxopenId = $this->getWxOpenId($openid);
		
		//将图片转移到右拍云上
		//$upyun = new \Index\Model\UpyunModel();
		//$picUrl = $upyun->upload($wxopenId,$picUrl);
		
		//不走右拍云，直接进行网址替换
		$picUrl = str_replace("http://mmbiz.qpic.cn","",$picUrl);
		if (substr($picUrl,-2) == '/0'){
			$picUrl = substr_replace($picUrl,"",-2);
		}

        $picItem = array(
            'url'=>$picUrl,
            'text'=>'',
            'rotate'=>0
        );
		
		//先得到 wxopenId 最后一个相册,并添加图片
		$cardDataM = D('Index/CardData');
		$lastCard = $cardDataM->getLastCard($wxopenId);
		$cardInfo = array();
		
		if($lastCard){
			$cardAnalyze = new CardAnalyzeModel($lastCard['setting']);
			$cardAnalyze->addPic($picItem);
			$cardInfo['setting'] = $cardAnalyze->getJsonData();
			$uid = $lastCard['uid'];
			$cid = $lastCard['cid'];
			$cardDataM->saveCardData($uid,$cid,$cardInfo);
		}else{
			//创建新的相册
			$cardAnalyze = new CardAnalyzeModel();
			$cardAnalyze->addPic($picItem);
			$cardInfo['wxopenid'] = $wxopenId;
			$cardInfo['setting'] = $cardAnalyze->getJsonData();
			$cardInfo['wxtype'] = $this->wxName;
			$cid = $cardDataM->newCardData($cardInfo);
		}
		
		$picCount = $cardAnalyze->getPicCount();
		
		$wxYuming = 'http://'.$_SERVER['HTTP_HOST'].'/';
		$createUrl = $wxYuming."Index/Index/create/cid/".$cid;
		$msg = "<a href='".$createUrl."'>您已上传".$picCount."张相片，点击这里，开始制作相册</a>";
		
		if(in_array($this->wxName, $this->unAuth)){
			$msg = $msg.'
回复“新相册”创建新相册'.'
回复“我的相册”查看相册';
		}
		
		//$msg = "已收到".$picCount."张相片，继续发送，或者<a href='http://www.baidu.com'>点这里完成制作</a>";
		$this->sendTextMsg($msg, $openid, $gh);
	}
	
	// 关键词回复
	function keywordReply($keyword, $openid = "demo", $gh = "demo") {
		if ($keyword == "新相册") {
			//创建新相册
			$this->createNewCard($openid);
			$msg = "创建成功，请发送新的相片到新相册里面吧。";
			$this->sendTextMsg($msg, $openid, $gh);
		}
		
		if($keyword == "我的相册"){
			$wxYuming = 'http://'.$_SERVER['HTTP_HOST'].'/';
			$myCardUrl = $wxYuming."Index/Index/mycard";
			$msg = "<a href='".$myCardUrl."'>点击进入我的相册</a>";
			$this->sendTextMsg($msg, $openid, $gh);
		}
		
		if(strpos ( $keyword, "商务" ) !== false ||
		   strpos ( $keyword, "合作" ) !== false ||
		   strpos ( $keyword, "联系" ) !== false ||
		   strpos ( $keyword, "广告" ) !== false ||
		   strpos ( $keyword, "推广" ) !== false){
			$msg = "如有合作意愿或想了解更多详情，欢迎加商务QQ： 2382802924 。";
			$this->sendTextMsg($msg, $openid, $gh);
		}
		
		$this->sendTextMsg("亲，给我发照片吧，立即免费为您生成超酷超炫的音乐相册", $openid, $gh);
	}
	
	function picUpReply($keyword, $openid = "demo", $gh = "demo"){
		if($keyword == "new"){
			$this->createNewCard($openid);
		}
	}
	
	private function createNewCard($openid){
		//创建新相册
		$cardDataM = D('Index/CardData');
		$wxopenId = $this->getWxOpenId($openid);
		$cardInfo = array();
		$cardInfo['wxopenid'] = $wxopenId;
		$cardInfo['wxtype'] = $this->wxName;
		$cardDataM->newCardData($cardInfo);
	}
	
	function sendTextMsg($content, $openid, $gh){
		$content = $this->getTextXml ( $content );
		$content = $this->getWxContent ( $content, $openid, $gh, time () );
		echo $content;
		exit();
	}
	
	function getWxOpenId($openId){
		return $this->wxName."_".$openId;
	}
	
	function checkRepeatMsg($msgId, $openid = "demo", $gh = "demo"){
		if(!$msgId){
			return "";
		}
		$msgKey = "moliWxRealy_".$openid."_".$gh;
		$curMsgs = S($msgKey);
		
		if(empty($curMsgs) || !is_array($curMsgs)){
			$curMsgs = array();
		}
		
		if(in_array("$msgId",$curMsgs)){
			echo "";
			exit();
		}
		
		array_push($curMsgs,"$msgId");
		S($msgKey,$curMsgs,30);
	}
	
	// --------------------- 微信接口信息 ------------------------
	/**
	 * 获得文本XML
	 * 
	 * @param 内容 $content        	
	 * @return string
	 */
	function getTextXml($content) {
		$textTpl = "<xml>
    	<wxhead></wxhead>
    	<MsgType><![CDATA[text]]></MsgType>
    	<Content><![CDATA[$content]]></Content>
    	<FuncFlag>0</FuncFlag>
    	</xml>";
		return $textTpl;
	}
	/**
	 * 获得图文XML
	 * @param  $newsArray Array new(title,description,picurl,url)
     * @return string
	 */
	function getNewsXml($newsArray) {
		$len = count ( $newsArray );
		$newsTpl = "<xml>
    	<wxhead></wxhead>
    	<MsgType><![CDATA[news]]></MsgType>
    	<ArticleCount>$len</ArticleCount>
    	<Articles>";
		for($i = 0; $i < $len; $i ++) {
			$title = $newsArray [$i] ['title'];
			$description = $newsArray [$i] ['description'];
			$picurl = $newsArray [$i] ['picurl'];
			$url = $newsArray [$i] ['url'];
			$itemXml = "<item>
    		<Title><![CDATA[$title]]></Title>
    		<Description><![CDATA[$description]]></Description>
    		<PicUrl><![CDATA[$picurl]]></PicUrl>
    		<Url><![CDATA[$url]]></Url>
    		</item>";
			$newsTpl = $newsTpl . $itemXml;
		}
		$newsTpl = $newsTpl . "</Articles>
    	<FuncFlag>1</FuncFlag>
			 </xml>";
		return $newsTpl;
	}
	
	/**
	 * 获得语音XML
     * @param  $title
     * @param  $description
     * @param  $music_url
     * @param  $hq_music_url
     * @return string
	 */
	function getMusicXml($title, $description, $music_url, $hq_music_url) {
		$musicTpl = "<xml>
    	<wxhead></wxhead>
    	<MsgType><![CDATA[music]]></MsgType>
    	<Music>
    	<Title><![CDATA[$title]]></Title>
    	<Description><![CDATA[$description]]></Description>
    	<MusicUrl><![CDATA[$music_url]]></MusicUrl>
    	<HQMusicUrl><![CDATA[$hq_music_url]]></HQMusicUrl>
    	</Music>
    	<FuncFlag>0</FuncFlag>
    	</xml>";
		return $musicTpl;
	}
	
	/**
	 * 获得多客服XML
	 * (title,description,music_url,hq_music_url)
     * @return string
	 */
	function getKefuXml() {
		$kefuXML = "<xml>
    		<wxhead></wxhead>
    		<MsgType><![CDATA[transfer_customer_service]]></MsgType>
			</xml>";
		return $kefuXML;
	}
	
	/**
	 * 获取最终微信消息
     * @param  $content
     * @param  $openid
     * @param  $gh
     * @param  $ctime
     * @return string
	 */
	function getWxContent($content, $openid, $gh, $ctime) {
		$headContent = "<ToUserName><![CDATA[$openid]]></ToUserName>
	    <FromUserName><![CDATA[$gh]]></FromUserName>
	    <CreateTime>$ctime</CreateTime>";
		$newcontent = str_replace ( '<wxhead></wxhead>', $headContent, $content );
		$newcontent = str_replace ( 'openid=fromuserid', 'openid=' . $openid, $newcontent );
		return $newcontent;
	}
}