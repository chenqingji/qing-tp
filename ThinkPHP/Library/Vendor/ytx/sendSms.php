<?php
include_once ("CCPRestSDK.php");

/**
 * 发送模板短信
 * 
 * @param
 *        	to 手机号码集合,用英文逗号分开
 * @param
 *        	datas 内容数据 格式为数组 例如：array('Marry','Alon')，如不需替换请填 null
 * @param $tempId 模板Id        	
 */
function sendTemplateSMS($to, $datas, $tempId) {
	// 主帐号
	$accountSid = '8a48b5514700da2f0147137619b6042e';
	
	// 主帐号Token
	$accountToken = 'ec2e3b6cf35840a789fe304157e13579';
	
	// 应用Id
	$appId = 'aaf98f8947861daa01478bb4755002a9';
	
	// 请求地址
	$serverIP = 'app.cloopen.com';
	
	$serverPort = '8883';
	
	// REST版本号
	$softVersion = '2013-12-26';
	
	if( empty($to) )
	{
		return false;
	}
	
	// 初始化REST SDK
	$rest = new REST ( $serverIP, $serverPort, $softVersion );
	$rest->setAccount ( $accountSid, $accountToken );
	$rest->setAppId ( $appId );
	
	// 重试10次，避免发送短信失败，发送模板短信
	for ($i = 0; $i < 10; $i++) {
		$result = $rest->sendTemplateSMS ( $to, $datas, $tempId );
		if( $result == NULL || $result->statusCode != 0 )
		{
			continue;
		}
		else
		{
			break;
		}
	}
	
	if ($result == NULL) {
		return false;
	}
	if ($result->statusCode != 0) {
		return false;
	}
	
	return true;
}