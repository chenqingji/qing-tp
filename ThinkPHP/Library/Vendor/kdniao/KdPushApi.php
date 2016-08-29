<?php
//电商ID
defined('EBusinessID') or define('EBusinessID', '1256059');
//电商加密私钥，快递鸟提供，注意保管，不要泄漏
defined('AppKey') or define('AppKey', '6482c25d-2355-4067-a91a-150aee02f988');
//请求url
defined('ReqURL') or define('ReqURL', 'http://api.kdniao.cc/Ebusiness/EbusinessOrderHandle.aspx');


/**
 * Json方式  物流信息订阅
 */
function orderTracesSubByJson($mailno){
	$requestData="{'Code': 'YD','Item': [{'No': '.$mailno.','Bk': ''}]}";
	$datas = array(
        'EBusinessID' => EBusinessID,
        'RequestType' => '1005',
        'RequestData' => urlencode($requestData) ,
        'DataType' => '2',
    );
    $datas['DataSign'] = encrypt($requestData, AppKey);
	$result=sendPost(ReqURL, $datas);	
	
	//根据公司业务处理返回的信息......
	
	return $result;
}

/**
 * XML方式  物流信息订阅
 */
function orderTracesSubByXml(){
	$requestData="<?xml version=\"1.0\" encoding=\"utf-8\" ?>".
				"<Content>".
				"<Code>SF</Code>".
				"<Items>".
				"<Item>".
				"<No>909261024507</No>".
				"<Bk>test</Bk>".
				"</Item>".
				"<Item>".
				"<No>909261024507</No>".
				"<Bk>test</Bk>".
				"</Item>".
				"</Items>".
				"</Content>";	
	
	$datas = array(
        'EBusinessID' => EBusinessID,
        'RequestType' => '1005',
        'RequestData' => urlencode($requestData) ,
        'DataType' => '1',
    );
    $datas['DataSign'] = encrypt($requestData, AppKey);
	$result=sendPost(ReqURL, $datas);	
	
	//根据公司业务处理返回的信息......
	
	return $result;
}

/**
 *  post提交数据 
 * @param  string $url 请求Url
 * @param  array $datas 提交的数据 
 * @return url响应返回的html
 */
function sendPost($url, $datas) {
    $temps = array();	
    foreach ($datas as $key => $value) {
        $temps[] = sprintf('%s=%s', $key, $value);		
    }	
    $post_data = implode('&', $temps);
    $url_info = parse_url($url);
    $httpheader = "POST " . $url_info['path'] . " HTTP/1.0\r\n";
    $httpheader.= "Host:" . $url_info['host'] . "\r\n";
    $httpheader.= "Content-Type:application/x-www-form-urlencoded\r\n";
    $httpheader.= "Content-Length:" . strlen($post_data) . "\r\n";
    $httpheader.= "Connection:close\r\n\r\n";
    $httpheader.= $post_data;
    $fd = fsockopen($url_info['host'], 80);
    fwrite($fd, $httpheader);
    $gets = "";
    while (!feof($fd)) {
        $gets.= fread($fd, 128);
    }
    fclose($fd);    
    
    return $gets;
}

/**
 * 电商Sign签名生成
 * @param data 内容   
 * @param appkey Appkey
 * @return DataSign签名
 */
function encrypt($data, $appkey) {
    return urlencode(base64_encode(md5($data.$appkey)));
}

?>