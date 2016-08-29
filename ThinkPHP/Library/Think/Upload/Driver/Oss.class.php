<?php
namespace Think\Upload\Driver;
use Think\Storage;

class Oss{
    /**
     * 上传文件根目录
     * @var string
     */
    private $rootPath;

    /**
     * 上传错误信息
     * @var string
     */
    private $error = '';

	public function __construct($root, $config){
		Storage::connect( 'OSS' );
	}

    public function checkRootPath(){
        return true;
    }

	public function checkSavePath($savepath){
		return true;
    }

    public function mkdir($savepath){
    	return true;
    }

    /**
     * 保存指定文件
     * @param  array   $file    保存的文件信息
     * @param  boolean $replace 同名文件是否覆盖
     * @return boolean          保存状态，true-成功，false-失败
     */
    public function save($file, $replace = true) {
        return Storage::uploadFile( $file['savename'], $file['tmp_name'] );
    }

    public function getError(){
        return $this->error;
    }
}
