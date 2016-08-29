<?php
namespace Think;

class Message {

    public function send( $receiver, $message ) {

        foreach( C('MESSAGE_TEMPLATE') + array( false ) as $key => $val ) 
            if ( preg_match( "/^$val$/i", $message, $parameter ) ) break;

        if ( empty( $key ) ) {
            // TODO send sms without tempalte
            E("NOT_SUPPORT");
        } else {
            // php 5.3 compatible
            $class = new Message\Driver\Template;
            return $class->send( $receiver, $key, $parameter );
        }
    }

}
