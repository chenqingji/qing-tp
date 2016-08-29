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
namespace Think\Storage\Driver;
use Think\Storage;
use Think\Storage\MIME;
use Think\Http;
use Exception;

// OSS存储驱动
class OSS extends Storage {

    private $_http;
    private $_mime;
	
    public function __construct() {

        $this->_http = new Http();
        $this->_mime = new MIME();

    }

    private function _connect( $method, $ctype, $object, $data = '' ) {

    	$location = C( 'OSS_LOCATION' );
    	$id = C( 'OSS_USER' );
    	$key = C( 'OSS_PASSWORD' );
    	$bucket = C( 'OSS_BUCKET' );

        /* get gmdate ... */
        $date = gmdate( 'D, d M Y H:i:s' ) . ' GMT';

        /* let us go travel ... */
        return $this->_http->request( 
            $method, $location . $object, $data, array(
            "Content-Type: $ctype", "Date: $date",
            "Authorization: OSS $id:" . base64_encode( hash_hmac( 'sha1', 
                "$method\n\n$ctype\n$date\n/$bucket/$object", $key, true
                ) ) )
            );

    }

    public function nothing() {

        /* this function is not opened now ... */
        return;

    }

    public function has( $filename ) {

        $ret = $this->_connect( 'HEAD', 'application/x-www-form-urlencoded', $filename );

        /* only status code is useful ... */
        return 200 == $ret[1] ? true : false;

    }

    public function mkdir( $directory ) {

        $ret = $this->connect( 'PUT', 'application/x-www-form-urlencoded', $directory . '/' );

        /* create directory is another way to upload ... */
        if ( 200 == $ret[1] ) return;

        throw new Exception( 'Something wrong when create directory' );

    }

    public function uploadFile( $filename, $local ) {
        /* something not exists ..? */
        if ( ! is_file( $local ) )
        {
            throw new Exception( 'File not found' );
        }

        $content = file_get_contents( $local );
        
        // 重试多次，避免失败
        for ($i = 0; $i < 10; $i++) {
        	$ret = $this->_connect(
        			'PUT', $this->_mime->get_type(
        					pathinfo( $filename, PATHINFO_EXTENSION )
        			), $filename, $content );
        	
        	if( 200 == $ret[1] ) return true;
        	usleep(500);
        }
        
        throw new Exception( 'Something wrong when upload' );

    }

    public function unlink( $filename ) {

        $this->_connect( 'DELETE', 'application/x-www-form-urlencoded', $filename );

    }

}
