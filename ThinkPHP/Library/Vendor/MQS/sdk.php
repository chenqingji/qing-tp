<?php
/**
 * File:        mqs.sdk.class.php
 * Date:        2014-07-20 11:03:06
 * Author:      xiaohui lam( xiaohui.lam@e.hexdata.cn )
 * Note:        阿里云消息队列服务SDK
 */

Class mqs{
    private static $accessHost       = null;
    private static $accessRegion     = null;
    private static $accessKeyId      = null;
    private static $accessKeySecret  = null;
    private static $accessOwnerId    = null;
    private static $accessQueue      = null;
    private static $mqsVersion       = "2014-07-08";
    private static $retryTime        = 5;       // 若操作失败, 重试次数
    private static $sleepSecond      = 5;       // 若操作失败, 休眠5秒后重试
    private static $serverErrCode    = null;    // 伺服器错误的HTTP Code, 当响应Code匹配上, 则认为服务器暂时未处理请求, 然后重试预设次

    function mqs( $data ){
        /* init */
        self::$accessKeyId      = isset($data['accessKeyId'])     ?$data['accessKeyId']      :"";
        self::$accessKeySecret  = isset($data['accessKeySecret']) ?$data['accessKeySecret']  :"";
        self::$accessOwnerId    = isset($data['accessOwnerId'])   ?$data['accessOwnerId']    :"";
        self::$accessQueue      = isset($data['accessQueue'])     ?$data['accessQueue']      :"";
        self::$accessRegion     = isset( $data['accessRegion'] )  ?$data['accessRegion']     :'cn-hangzhou';
        self::$accessHost       = self::$accessOwnerId . ".mqs-" .self::$accessRegion . ".aliyuncs.com";
        self::$serverErrCode    = array(
            500, // Server Internal Error
            501, // Can'T excution Error
            502, // Bad Gateway
            503, // Service unavailable
            504, // Proxy time out

        );
        return $this;
    }

    private static function _array2xml( $array ){
        require_once dirname( __FILE__ ) . "/array2xml.lib.class.php";
        $array['@attributes'] = array( 'xmlns' => 'http://mqs.aliyuncs.com/doc/v1/' );
        return Array2XML::createXML('Message', $array)->saveXML();

    }

    private static function _xml2array( $xml ){
        if( !$xml || $xml == "" ) return array();
        require_once dirname( __FILE__ ) . "/xml2array.lib.class.php";
        return XML2Array::createArray($xml);
    }

    private static function _errorHandle( $headers ){
        preg_match('/HTTP\/[\d]\.[\d] ([\d]+) /', $headers, $code);
        if($code[1]){
            if( $code[1] / 100 > 1 && $code[1] / 100 < 4 ) return false;
            else return $code[1];
        }
    }

    private static function _getGMTDate(){
        date_default_timezone_set("UTC");
        return date('D, d M Y H:i:s', time()) . ' GMT';
    }

    private static function _getContentType(){
        return 'text/xml;utf-8';
    }

    private static function _getVersion(){
        return self::$mqsVersion;
    }

    private static function _getProtocol( $https = false ){
        return ($https) ? 'https://' : 'http://';
    }

    private static function _getAccessHost(){
        return self::$accessHost;
    }

    private static function _requestCore( $request_uri, $request_method, $request_header, $request_body = "" ){
        if( $request_body != "" ){
            $request_header['Content-Length'] = strlen( $request_body );
        }
        $_headers = array(); foreach( $request_header as $name => $value )$_headers[] = $name . ": " . $value;
        $request_header = $_headers;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_uri);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_header);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = explode( "\r\n\r\n", $res );
        $_try_error = self::_errorHandle( $data[0] );
        if( $_try_error ){
            return $_try_error;
        }
        return self::_xml2array($data[1]);
    }

    private static function _getSignature( $VERB, $CONTENT_MD5, $CONTENT_TYPE, $GMT_DATE, $CanonicalizedMQSHeaders = array(), $CanonicalizedResource = "/" ){
        $order_keys = array_keys( $CanonicalizedMQSHeaders );
        sort( $order_keys );
        $x_mqs_headers_string = "";
        foreach( $order_keys as $k ){
            $x_mqs_headers_string .= join( ":", array( strtolower($k), $CanonicalizedMQSHeaders[ $k ] . "\n" ) );
        }
        $string2sign = sprintf(
            "%s\n%s\n%s\n%s\n%s%s",
            $VERB,
            $CONTENT_MD5,
            $CONTENT_TYPE,
            $GMT_DATE,
            $x_mqs_headers_string,
            $CanonicalizedResource
        );

        $sig = base64_encode( hash_hmac('sha1', $string2sign, self::$accessKeySecret, true ) );
        return "MQS " . self::$accessKeyId . ":" . $sig;
    }

    public static function sendMessage( $data ){
        /*
        $data = array(
            'MessageBody' => '123',
            'DelaySeconds' => 0,
            'Priority' => 8
        );
         */
        $VERB = "POST";
        $CONTENT_BODY = self::_array2xml( $data );
        $CONTENT_MD5 = base64_encode( md5( $CONTENT_BODY ) );
        $CONTENT_TYPE = self::_getContentType();
        $GMT_DATE = self::_getGMTDate();
        $CanonicalizedMQSHeaders = array(
            'x-mqs-version' => self::_getVersion()
        );
        $RequestResource = "/" . self::$accessQueue . "/messages";
        $sign = self::_getSignature( $VERB, $CONTENT_MD5, $CONTENT_TYPE, $GMT_DATE, $CanonicalizedMQSHeaders, $RequestResource );
        $headers = array(
            'Host' => self::_getAccessHost(),
            'Date' => $GMT_DATE,
            'Content-Type' => $CONTENT_TYPE,
            'Content-MD5' => $CONTENT_MD5
        );
        foreach( $CanonicalizedMQSHeaders as $k => $v){
            $headers[ $k ] = $v;
        }
        $headers['Authorization'] = $sign;

        $request_uri = self::_getProtocol() . self::_getAccessHost() . $RequestResource;
        $res = self::_requestCore( $request_uri, $VERB, $headers, $CONTENT_BODY );
        if( in_array($res, self::$serverErrCode ) ){
            if( self::$retryTime >  0 ){
                self::$retryTime--;
                usleep( $sleepSecond * 1000000 );
                $res = self::sendMessage( $data );
            }else{
                return false; // 添加队列失败...
            }
        }
        
        return $res;
    }

    public static function receiveMessage( $data = null ){
        /*
         $data = array();
         */

        $VERB = "GET";
        $CONTENT_BODY = "";
        $CONTENT_MD5 = base64_encode( md5( $CONTENT_BODY ) );
        $CONTENT_TYPE = self::_getContentType();
        $GMT_DATE = self::_getGMTDate();
        $CanonicalizedMQSHeaders = array(
            'x-mqs-version' => self::_getVersion()
        );
        $RequestResource = "/" . self::$accessQueue . "/messages";
        $sign = self::_getSignature( $VERB, $CONTENT_MD5, $CONTENT_TYPE, $GMT_DATE, $CanonicalizedMQSHeaders, $RequestResource );
        $headers = array(
            'Host' => self::_getAccessHost(),
            'Date' => $GMT_DATE,
            'Content-Type' => $CONTENT_TYPE,
            'Content-MD5' => $CONTENT_MD5
        );
        foreach( $CanonicalizedMQSHeaders as $k => $v){
            $headers[ $k ] = $v;
        }
        $headers['Authorization'] = $sign;

        $request_uri = self::_getProtocol() . self::_getAccessHost() . $RequestResource;
        return self::_requestCore( $request_uri, $VERB, $headers, $CONTENT_BODY );
    }

    public static function deleteMessage( $data ){
        /*
        $data = array(
           'ReceiptHandle' => '1-ODU4OTkzNDU5My0xNDA1ODQ4OTUwLTItOA=='
        );
         */
        $VERB = "DELETE";
        $CONTENT_BODY = "";
        $CONTENT_MD5 = base64_encode( md5( $CONTENT_BODY ) );
        $CONTENT_TYPE = self::_getContentType();
        $GMT_DATE = self::_getGMTDate();
        $CanonicalizedMQSHeaders = array(
            'x-mqs-version' => self::_getVersion()
        );
        $RequestResource = "/" . self::$accessQueue . "/messages?" . http_build_query( $data );
        $sign = self::_getSignature( $VERB, $CONTENT_MD5, $CONTENT_TYPE, $GMT_DATE, $CanonicalizedMQSHeaders, $RequestResource );
        $headers = array(
            'Host' => self::_getAccessHost(),
            'Date' => $GMT_DATE,
            'Content-Type' => $CONTENT_TYPE,
            'Content-MD5' => $CONTENT_MD5
        );
        foreach( $CanonicalizedMQSHeaders as $k => $v){
            $headers[ $k ] = $v;
        }
        $headers['Authorization'] = $sign;

        $request_uri = self::_getProtocol() . self::_getAccessHost() . $RequestResource;
        return self::_requestCore( $request_uri, $VERB, $headers, $CONTENT_BODY );
    }
}
