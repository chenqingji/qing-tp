<?php
// +----------------------------------------------------------------------
// | TOPThink [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace Think\Message\Driver;
use Think\Http;
use Think\Log;

class Template {

    private $_http;

    public function __construct() {
        $this->_http = new Http();
    }

    private function _get_access_token() {

        /* get access token via app config ... */
        return $this->_http->request( 
            'POST', 'https://oauth.api.189.cn/emp/oauth2/v3/access_token',
            http_build_query( array(
                'grant_type'        =>  'client_credentials',
                'app_id'            =>  C( 'SMS_189_KEY' ),
                'app_secret'        =>  C( 'SMS_189_SECRET' )
                ) ) );

    }

    public function send( $number, $template, array $parameter = array() ) {

        $buffer = array();
        foreach( $parameter as $key => $val )
            if ( ! empty( $key ) ) $buffer["$key"] = $val;

        /* send request to sms gateway ... */
        $ret = $this->_http->request(
            'POST', 'http://api.189.cn/v2/emp/templateSms/sendSms', 
            http_build_query( array(
                'acceptor_tel'      =>  $number,
                'template_id'       =>  $template,
                'template_param'    =>  json_encode( $buffer ),
                'app_id'            =>  C( 'SMS_189_KEY' ),
                'access_token'      =>  C( 'SMS_189_ACCESSTOKEN' ),
                'timestamp'         =>  date('Y-m-d H:i:s')
            ) ) );

        /* write log ... */
        $log_path = LOG_PATH . 'message/';
        is_dir( $log_path ) || mkdir( $log_path, 0777 );
        Log::write( 
            "tel:{$number} ### status:{$ret[1]} ### return:{$ret[0]}", 
            'W_INFO', 
            '', 
            $log_path . 'send.log' 
        );
        
        var_dump($ret);

        return 200 == $ret[1] && '0' == json_decode( $ret[0] )->res_code;

    }

}
