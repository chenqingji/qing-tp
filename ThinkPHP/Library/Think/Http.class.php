<?php
namespace Think;

class Http {

    public function request( $method, $url, $contents = '', array $header = array() ) {

        /* init cURL handler ... */
        $curl = curl_init();

        /* set options ... */
        curl_setopt_array( $curl, array(
            CURLOPT_URL             =>  $url, 
            CURLOPT_USERAGENT       =>  $this->_http_config['useragent'],
            CURLOPT_CUSTOMREQUEST   =>  $method,
            CURLOPT_RETURNTRANSFER  =>  1, 
            CURLOPT_HEADER          =>  $this->_http_config['header'],
            CURLOPT_NOBODY          =>  $this->_http_config['nobody'],
            CURLOPT_POSTFIELDS      =>  $contents,
            CURLOPT_HTTPHEADER      =>  $header
            ) );

        /* run the query ... */
        $ret = curl_exec( $curl );

        /* get query information ... */
        $info = curl_getinfo( $curl, CURLINFO_HTTP_CODE ); 

        /* do not forget take url down when finished ... */
        curl_close( $curl );

        /* send page contents and http code back ... */
        return array( $ret, $info );

    }

}
