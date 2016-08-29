<?php

/**
 * 面单申请
 * @author auto create
 */
class WaybillApplyNewRequest
{
	
	/** 
	 * TOP  appkey
	 **/
	public $app_key;
	
	/** 
	 * 物流服务商编码
	 **/
	public $cp_code;
	
	/** 
	 * --
	 **/
	public $cp_id;
	
	/** 
	 * 使用者ID
	 **/
	public $real_user_id;
	
	/** 
	 * 商家ID
	 **/
	public $seller_id;
	
	/** 
	 * 发货地址
	 **/
	public $shipping_address;
	
	/** 
	 * 面单详细信息
	 **/
	public $trade_order_info_cols;	
}
?>