<?php

// 创建guid
function create_guid() {
	$charid = strtoupper(md5(uniqid(mt_rand(), true)));
	$hyphen = chr(45);// "-"
	$uuid = chr(123)// "{"
	.substr($charid, 0, 8).$hyphen
	.substr($charid, 8, 4).$hyphen
	.substr($charid,12, 4).$hyphen
	.substr($charid,16, 4).$hyphen
	.substr($charid,20,12)
	.chr(125);// "}"
	return $uuid;
}

// 获取临时cookie
function getContextCookie()
{
	$cookiefilename = '';
	if( isset($_COOKIE['ygjsn']) )
	{
		$cookiefilename = $_COOKIE['ygjsn'];
	}
	if( empty($cookiefilename) )
	{
		$cookiefilename = create_guid();
		setcookie('ygjsn',$cookiefilename);
	}
	$cookie_file = "temp".DIRECTORY_SEPARATOR.$cookiefilename.'.php';
	return $cookie_file;
}

// 网页抓取操作类
class curl {
	public 
	$proxy,	// 设置本地代理
	$webproxy,	// 设置web代理
	$sck;// 设置web代理密钥
	
	var $headers;
	var $user_agent;
	var $compression;
	var $cookie_file;
	var $cookiefilename;
	
	function curl($cookies = false, $cookie = '', $compression = 'gzip') {
		$this->headers [] = 'Accept: */*;';
		$this->headers [] = 'Connection: Keep-Alive';
		$this->headers [] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
		$this->user_agent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)';
		$this->compression = $compression;
		$this->cookies = $cookies;
		if ($this->cookies == TRUE)
			$this->setCookieFile ( $cookie );
	}
	
	// 检验web代理服务器是否工作正常,返回false则代表服务器异常，返回true代表服务器是正常的
	function checkWebProxy()
	{
		$check_url  = "http://www.ygj.com.cn/pp/verify.php?verify_ygj=1";
		$content = $this->get($check_url);
		if( $content=="yunguanjia" )
			return true;
		return false;
	}
	
	function setCookieFile($cookie_file='') {
		if( empty($cookie_file) )
			$cookie_file = getContextCookie();
		$this->cookies = true;
		$cookie_file = dirname(__FILE__).DIRECTORY_SEPARATOR.$cookie_file;
		if (file_exists ( $cookie_file )) {
			$this->cookie_file = $cookie_file;
		} else {
			$handler = fopen ( $cookie_file, 'w' ) or $this->error ( 'The cookie file could not be opened. Make sure this directory has the correct permissions' );
			$this->cookie_file = $cookie_file;
			fclose (  $handler );
		}
	}
	
	function get($url,$getFinalUrl=false,$follow = true,$withHeader=false,$refer=false) {
		if( $this->webproxy )
		{
			$url = $this->webproxy."?url=".urlencode($url)."&sck=".$this->sck;
		}
		$process = curl_init ( $url );
		curl_setopt ( $process, CURLOPT_HTTPHEADER, $this->headers );
		curl_setopt ( $process, CURLOPT_HEADER, $withHeader );
		curl_setopt ( $process, CURLOPT_USERAGENT, $this->user_agent );
		if ($this->cookies == TRUE)
			curl_setopt ( $process, CURLOPT_COOKIEFILE, $this->cookie_file );
		if ($this->cookies == TRUE)
			curl_setopt ( $process, CURLOPT_COOKIEJAR, $this->cookie_file );
		curl_setopt ( $process, CURLOPT_ENCODING, $this->compression );
		curl_setopt ( $process, CURLOPT_TIMEOUT, 3 );
		if ($this->proxy)
			curl_setopt ( $process, CURLOPT_PROXY, $this->proxy );
		curl_setopt ( $process, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $process, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $process, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $process, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt( $process, CURLOPT_FOLLOWLOCATION, $follow);
		if( $refer )
			curl_setopt($process, CURLOPT_REFERER, $refer);
		$return = curl_exec ( $process );
		if( $getFinalUrl )
		{
			$info = curl_getinfo($process,CURLINFO_EFFECTIVE_URL);
			curl_close ( $process );
			return $info;
		}
		curl_close ( $process );
		return $return;
	}
	function post($url, $data,$refer=false,$getFinalUrl=false) {
		if( $this->webproxy )
		{
			$url = $this->webproxy."?url=".urlencode($url)."&sck=".$this->sck;
		}
		$process = curl_init ( $url );
		curl_setopt ( $process, CURLOPT_HTTPHEADER, $this->headers );
		// curl_setopt ( $process, CURLOPT_HEADER, 1 );
		curl_setopt ( $process, CURLOPT_USERAGENT, $this->user_agent );
		if ($this->cookies == TRUE)
			curl_setopt ( $process, CURLOPT_COOKIEFILE, $this->cookie_file );
		if ($this->cookies == TRUE)
			curl_setopt ( $process, CURLOPT_COOKIEJAR, $this->cookie_file );
		curl_setopt ( $process, CURLOPT_ENCODING, $this->compression );
		curl_setopt ( $process, CURLOPT_TIMEOUT, 3 );
		if ($this->proxy)
			curl_setopt ( $process, CURLOPT_PROXY, $this->proxy );
		curl_setopt ( $process, CURLOPT_POSTFIELDS, $data );
		curl_setopt ( $process, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $process, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $process, CURLOPT_POST, 1 );
		if( $refer )
			curl_setopt($process, CURLOPT_REFERER, $refer);
		curl_setopt ( $process, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $process, CURLOPT_SSL_VERIFYHOST, 0);
		$return = curl_exec ( $process );
		if( $getFinalUrl )
		{
			$info = curl_getinfo($process,CURLINFO_EFFECTIVE_URL);
			curl_close ( $process );
			return $info;
		}
		curl_close ( $process );
		return $return;
	}
	function error($error) {
		echo "<center><div style='width:500px;border: 3px solid #FFEEFF; padding: 3px; background-color: #FFDDFF;font-family: verdana; font-size: 10px'><b>cURL Error</b><br>$error</div></center>";
		die ();
	}
}

?>
