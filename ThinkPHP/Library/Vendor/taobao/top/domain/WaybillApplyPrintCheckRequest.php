<?php

/**
 * 打印请求
 * @author auto create
 */
class WaybillApplyPrintCheckRequest
{
	
	/** 
	 * TOP平台请求的ISV APPKEY
	 **/
	public $app_key;
	
	/** 
	 * 物流服务商Code
	 **/
	public $cp_code;
	
	/** 
	 * 打印面单详细信息
	 **/
	public $print_check_info_cols;
	
	/** 
	 * 申请者编码
	 **/
	public $seller_id;	
}
?>