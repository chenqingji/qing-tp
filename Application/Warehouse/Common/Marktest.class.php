<?php

namespace Warehouse\Common;

/**
 * Description of Marktest
 */
class Marktest{

    /**
     * 是否允许写入测试数据  提供线上测试使用
     */
    const TO_WRITE_TEST_DATA = true;

    /**
     * 测试数据写入文件
     */
    const TO_WRITE_TEST_DATA_FILE = "/tmp/warehouse.log";

    /**
     * 写入测试数据
     * @param type $data
     * @param type $append 追加
     */
    public static function writeTestData($data, $append = true) {
        if (self::TO_WRITE_TEST_DATA) {
            $date = date("Y-m-d H:i:s", I("server.REQUEST_TIME", time()));
            $ip = I("server.REMOTE_ADDR");
            $string = $ip . "\t" .
                    $date . "\t" .
                    __ACTION__ . "\n" .
                    print_r($data, true) . "\n";
            if ($append) {
                file_put_contents(self::TO_WRITE_TEST_DATA_FILE, $string, FILE_APPEND);
            } else {
                file_put_contents(self::TO_WRITE_TEST_DATA_FILE, $string);
            }
        }
    }

    /**
     * 获取测试数据
     * @return false|string
     */
    public static function getTestData() {
        $string = '';
        if (file_exists(self::TO_WRITE_TEST_DATA_FILE)) {
            $string = file_get_contents(self::TO_WRITE_TEST_DATA_FILE);
        }
        return $string;
    }

}
