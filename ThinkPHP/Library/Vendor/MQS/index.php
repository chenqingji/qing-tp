<?php
require_once( 'mqs.sdk.class.php' );
$mqs = new mqs(
        array(
            'accessKeyId'       => 'WPjLSOiK3tGltY5z',
            'accessKeySecret'   => '9GyR4T6R1CAtUzckY7xC3fIpMZTawd',
            'accessOwnerId'     => 'hpxtr7smmy',
            'accessQueue'       => 'waimaid',
            'accessRegion'      => 'cn-qingdao'
        )
    );


// Push
$do = $mqs->sendMessage(
    array(
        'MessageBody' => '12.'.time(),
        'DelaySeconds' => 0,
        'Priority' => 8
    )
);
var_dump( $do );
echo "\r\n";
echo "\r\n";
echo "\r\n";

// Read    读取
$read = $mqs->receiveMessage();
var_dump($read);
echo "\r\n";
echo "\r\n";
echo "\r\n";

// Delete  移除
$do = $mqs->deleteMessage(
    array(
        // 'ReceiptHandle' => $read['Message']['ReceiptHandle']
        // 'ReceiptHandle' => '1-ODU4OTkzNDU5NS0xNDA1ODQ5Mjc5LTItOA=='
    )
);
var_dump( $do );
echo "\r\n";
echo "\r\n";
echo "\r\n";

