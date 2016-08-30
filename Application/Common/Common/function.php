<?php 
// 配置图像服务器
use Think\Log;
use User\Model\UserModel;
use User\Model\UserAuthorizeModel;
use Message\Model\QueueModel;


define('PIC_SERVER','http://ww.cdn.com');
define('PIC_WX','http://www.cdn.com');
define('MAIN_YUMING', ''); //资源域名，静态CDN
define('RES_YUMING', '/'); //资源域名，静态CDN
define("WX_ACCESS_TOKEN", "weixin_accesstoken_kuaiyin");


// 检测是否在微信下
function is_weixin(){
	if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
			return true;
	}	
	return false;
}

// 检测是否是合法的手机号码
function is_phone( $phonenum ) {

	return preg_match( '/^1[34578]{1}\d{9}$/', $phonenum ) ? true : false;

}

// 生成指定长度的随机的字符串
function random_string( $length, $chars = 
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
    ) {

    /* initialize generator ... */
    for ( $max = strlen( $chars ) - 1, $ret = '', 
        $i = 0; $i < $length; ++ $i ) $ret .= 
            $chars[mt_rand( 0, $max )];

    return $ret;
}

// 获取当前登录的用户名
function get_login_name()
{
	try {
		$m = new UserModel();
		$userInfo  = $m->getUserInfo();
		return $userInfo['username'];
	} catch (Exception $e) {
		return false;
	}
}

/**
 * 缓存管理
 * @param mixed $name 缓存名称，如果为数组表示进行缓存设置
 * @param mixed $groupname 缓存分组
 * @param mixed $value 缓存值
 * @param mixed $options 缓存参数
 * @return mixed
 */
function S2($name,$groupName='',$value='',$options=null) {
	static $cache   =   '';
	if(is_array($options) && empty($cache)){
		// 缓存操作的同时初始化
		$type       =   isset($options['type'])?$options['type']:'';
		$cache      =   Think\Cache::getInstance($type,$options);
	}elseif(is_array($name)) { // 缓存初始化
		$type       =   isset($name['type'])?$name['type']:'';
		$cache      =   Think\Cache::getInstance($type,$name);
		return $cache;
	}elseif(empty($cache)) { // 自动初始化
		$cache      =   Think\Cache::getInstance();
	}
	
	if(''=== $value){ // 获取缓存
		$name = $groupName."_".$name;
		return $cache->get($name);
	}elseif(is_null($value)) { // 删除缓存
		if($groupName!='' && $name =='')
		{
			// 删除所有的分组缓存
			$ss = S("Group_".$groupName);
			foreach ($ss as $key=>$s) {
				$cache->rm($key);
			}
			S("Group_".$groupName,null);
			return true;
		}
		else
		{
			if( $groupName!='' )
			{
				$name = $groupName."_".$name;
			}
			return $cache->rm($name);
		}
	}else { // 缓存数据
		if(is_array($options)) {
			$expire     =   isset($options['expire'])?$options['expire']:NULL;
		}else{
			$expire     =   is_numeric($options)?$options:NULL;
		}
		
		if( $groupName!='' )
		{
			// MORE 将组缓存设置为组内最大的超时时间;
			$name = $groupName."_".$name;
			if( !is_array( S("Group_".$groupName) ) )
			{
				$cache->set("Group_".$groupName,array());
			}
			$groupValue = (array)$cache->get("Group_".$groupName) + array($name=>false);
			$cache->set("Group_".$groupName,$groupValue);
		}
		
		return $cache->set($name, $value, $expire);
	}
}

function unescape_cookie($str) {
  $str = rawurldecode($str);
  preg_match_all("/(%u.{4})/",$str,$r);
  $ar = $r[0];
  foreach($ar as $k=>$v) {
    if(substr($v,0,2) == "%u" && strlen($v) == 6){
      $tmp = substr($v,-4);
      $tmp = '\\u'.$tmp;
      $ar[$k] = $tmp;
    }
  }
  return str_replace($r[0],$ar,$str);
}

// 设置时区
function timezone_set($timeoffset = 8) { 
	if(function_exists('date_default_timezone_set')) {
		@date_default_timezone_set('Etc/GMT'.($timeoffset > 0 ? '-' : '+').(abs($timeoffset))); 
	}
}

// 时间格式互相转换 XX : XX - XX : XX
function convert_time( $start_end ){

    $start = 0; 
    $end   = 0;

    if( count($start_end) == 2 ){

      $s = trim( $start_end[0] );  // start_time XX:XX

      $hour_min = explode(':', $s);
      if( count($hour_min) == 2 ){
        $hour  = trim( $hour_min[0] );
        $min   = trim( $hour_min[1] );
        $start = intval( ( $hour * 60 ) + $min );
        if( $start > 1440 || $start < 0 ){
            $start = 0;
        }
      }

      $e = trim( $start_end[1] );  // end_time XX:XX

      $hour_min = explode(':', $e);
      if( count($hour_min) == 2 ){
        $hour = trim( $hour_min[0] );
        $min  = trim( $hour_min[1] );
        $end = intval( ( $hour * 60 ) + $min );
        if( $end > 1440 || $end < 0 ){
            $end = 0;
        }
      }                  
    }

    return array('start'=>$start , 'end'=>$end);
}

function mb_unserialize($serial_str) {
    $serial_str= preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $serial_str );
    $serial_str= str_replace("\r", "", $serial_str);      
    return unserialize($serial_str);
}

//时间json拼接为字符串,$id为sid or iid的值，$time 为json时间串
function convert_str_time($id,$json){

	$res=json_decode(htmlspecialchars_decode($json),true);
			foreach ($res as $key => $value) {

				$val=implode(',',array_merge(array($id),$value['week'],array($value['tid'])));
				$strJoin.=",($val)";
				
			}
	$strJoin=ltrim($strJoin,',');
	return $strJoin;
}

	
// 发送邮件
// $receiver 收件人，支持多个收件人，请用分号隔开
// 
function send_mail($receiver,$title,$body,$from='waimaid@mail.55zhe.net',$fromname='管理员',$api_user="postmaster@wmd.sendcloud.org") {
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch, CURLOPT_URL, 'https://sendcloud.sohu.com/webapi/mail.send.json');
	curl_setopt($ch, CURLOPT_POSTFIELDS,

	array(
			'api_user' => $api_user,
			'api_key' => 'NNMGY89hA0HxRVjq',
			'from' => $from,
			'fromname' => $fromname,
			'to' => $receiver,
			'subject' => $title,
			'html' => $body));
		
	for ($i = 0; $i < 10; $i++) {
		$result = curl_exec($ch);
		
		if($result !== false)
		{
			break;
		}
	}

	if($result === false)
	{
		E("MAIL_SERVER_FAILD");
	}

	curl_close($ch);

	return $result;
}

// 发送验证码
function send_verify_code($to,$code,$username='')
{
	vendor('ytx.sendSms');
	return sendTemplateSMS($to,array($username,$code),2945);
}

// 发送新订单提醒
function send_order_notify($to,$orderinfo)
{
	vendor('ytx.sendSms');
	// debug 模板审核通过后需要更换2216编号
	return sendTemplateSMS($to,array(",订单号:".$orderinfo),2946);
}


// 穷举法攻击预防
// $params 标识用参数
// $maxTimes 最大次数
// $inPerion 周期（单位秒）
function attack_prevention($params,$maxTimes=30,$inPerion=100)
{
	$tag = sha1(json_encode($params));

    $lock = S("attack_lock_$tag"); // 判断锁
    if(!empty($lock)){
        E("您操作太频繁，请稍候再试"); // 已被加锁
    }else{
        S("attack_lock_$tag",'lock',10); // 未被加锁，上锁
    }

    /*** 读写文件操作 ***/
	$times = S("attack_$tag");
	$times = intval($times);
	$times = $times+1;
	S("attack_$tag",$times,$inPerion);
	/*** 读写文件操作 ***/

    S("attack_lock_$tag",null); // 解锁

	if( $times>$maxTimes )
	{
		E("您操作太频繁，请稍候再试");
	}
	return false;
}

function qLog($msg,$type='event')
{
	Log::write( $msg,'MANUAL_RECORD', '', LOG_PATH . 'log_'.$type.'.log' );
}

// 触发异步事件
function aync_event($tag,$params)
{
	$m = new QueueModel();
	$messageBody = json_encode(array(
		'tag' => $tag,
		'params' => $params
	));
	qLog($messageBody);
	$result = $m->send($messageBody);
	return $result;
}

// 验证是否有权限访问
function isAuth($actionPath,$params=array())
{
	// 判断当前登录帐号是否有权限
	$m = new UserAuthorizeModel();
	return $m->authorize($actionPath, $params);
}

// 输出到打印机
function send_print($device,$content)
{
	$content = iconv( "UTF-8", "gb2312//IGNORE" ,$content);
	$redis = new Redis();
	$redis->connect('42.121.81.210',6379);
	// 获取上一个packid，如果packid小于
	$packid = 1000;
	$queuename = "print_task_".$device;

	// $redis->del($queuename);
	$keys = $redis->hkeys($queuename);

	$len = count($keys);
	if( $len>0 )
	{
		// 获取最后一个值的packid
		$lastkey = $keys[$len-1];
		$packid = intval($lastkey)+1;
		if( $packid>9999 )
			$packid = 1000;
	}
	$redis->hset($queuename,$packid,$content);
}

// 发送App通知
// $uid 指定推送给某个用户，如果=0则推送给所有用户
function send_app($title,$content,$custom=array(),$activity='',$uid=0)
{
	vendor("Xinge.XingeApp");
	$push = new \XingeApp(2100040358, 'e78628879aafb7461927a5fcb695ca82');
	$mess = new \Message();
	$mess->setTitle($title);
	$mess->setContent($content);
	$mess->setType(\Message::TYPE_NOTIFICATION);
	$mess->setCustom($custom);
	$style = new \Style(0);
	#含义：样式编号0，响铃，震动，不可从通知栏清除，不影响先前通知
	$style = new \Style(0,1,1,1);
	$action = new \ClickAction();
	$action->setActionType(\ClickAction::TYPE_ACTIVITY);
	$action->setActivity($activity);
	$mess->setStyle($style);
	$mess->setAction($action);
	if( $uid==0)
	{
		$ret = $push->PushAllDevices(\XingeApp::DEVICE_ANDROID, $mess);
	}
	else
	{
		$ret = $push->PushSingleAccount(0, "uid".$uid, $mess);
	}
	return $ret;
}

// 设置静态缓存前缀
function getStaticFilePrefixNameArr() {
	/*
		以下值以 2 进制表示
		jpg 有效位 0, webp 有效位 1
		pc 有效位  10,无 pc 有效位 00
		android 有效位 100, 无android 有效位 000
		所以:
		000  => play_jpg_
		001  => play_webp_
		010  => play_jpg_pc_
		011  => play_webp_pc_
		100  => play_jpg_android
		101  => play_webp_android
		110  => play_jpg_pc_android
		111  => play_webp_pc_android
	*/
	return array(
		0x0 => 'play_jpg_',
        0x1 => 'play_webp_',
        0x2 => 'play_jpg_pc_',
        0x3 => 'play_webp_pc_',
        0x4 => 'play_jpg_android_',
        0x5 => 'play_webp_android_',
        0x6 => 'play_jpg_pc_android_',
        0x7 => 'play_webp_pc_android_'
    );
}

function getStaticFilePrefix() {
	$fileSuffix = getStaticFilePrefixNameArr();

	$prefix = 0x0;
    $regex_match = array();
    preg_match("/(chrome|android \d\.\d)/i", $_SERVER['HTTP_USER_AGENT'],$regex_match);

    if(!empty($regex_match[0])) {
        if (strpos($regex_match[0], "Android") === false) {
            // chrome
            $prefix |= 0x1;
        } else {
            if(floatval(substr($regex_match[0],-3,3)) > 4.2) {
                // android版本 大于 4.2
                $prefix |= 0x1;
            }
        }
    }

	$regex_match="/(nokia|iphone|ipad|android|motorola|^mot\-|softbank|foma|docomo|kddi|up\.browser|up\.link|";
    $regex_match.="htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|";
    $regex_match.="blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|bunjalloo|maui|";
    $regex_match.="symbian|smartphone|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\-|longcos|pantech|gionee|^sie\-|portalmmm|";
    $regex_match.="jig\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\s*mobi|opera\*mini|320x320|240x320|176x220";
    $regex_match.=")/i";
    $isMoblie = (isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE']) or preg_match($regex_match, strtolower($_SERVER['HTTP_USER_AGENT']))); //如果UA中存在上面的关键词则返回真。

    if(!$isMoblie){
    	$prefix |= 0x2;
    }

   	$android = $_GET['android'];
    if($android != '') {
    	$prefix |= 0x4;
    }

    return $fileSuffix[$prefix].$_GET['cid'];
}


function authcode($string, $operation = 'DECODE', $key = 'moli_kuaiyin', $expiry = 0) {
    $ckey_length = 4;
    $key = md5($key ? $key : UC_KEY);
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
    $cryptkey = $keya.md5($keya.$keyc);
    $key_length = strlen($cryptkey);
    $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
    $string_length = strlen($string);
    $result = '';
    $box = range(0, 255);
    $rndkey = array();
    for($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }
    for($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }
    for($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }
    if($operation == 'DECODE') {
        if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        return $keyc.str_replace('=', '', base64_encode($result));
    }
}



/**
 * 引用js文件,主要是为了js修改后能及时更新客户端浏览器的缓存。
 * @param mixed $src 引用路径
 * @param string $version 版本
 * @return void
 */
function includeJs($src, $version = '?v=2')
{
	if (is_array($src)) {

		foreach ($src as $val) {
			echo '<script type="text/javascript" src="' . $val . $version .'"></script>';
		}

	} else {

		echo '<script type="text/javascript" src="' . $src . $version .'"></script>';

	}

}

/**
 * 引用css文件,主要是为了css修改后能及时更新客户端浏览器的缓存。
 * @param mixed $src 引用路径
 * @param string $version 版本
 * @return void
 */
function includeCss($src, $version = '?v=2')
{

	if (is_array($src)) {
		foreach ($src as $val) {
			echo '<link media="screen" rel="stylesheet" href="' . $val . $version . '" />';
		}

	} else {

		echo '<link media="screen" rel="stylesheet" href="' . $src . $version . '" />';

	}

}
?>