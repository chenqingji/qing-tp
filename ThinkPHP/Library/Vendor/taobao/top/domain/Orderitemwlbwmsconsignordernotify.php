<?php

/**
 * 订单商品信息
 * @author auto create
 */
class Orderitemwlbwmsconsignordernotify
{
	
	/** 
	 * 商品成交价格=销售价格-优惠金额
	 **/
	public $actual_price;
	
	/** 
	 * 商品优惠金额
	 **/
	public $discount_amount;
	
	/** 
	 * 订单商品拓展属性数据
	 **/
	public $extend_fields;
	
	/** 
	 * 库存类型
	 **/
	public $inventory_type;
	
	/** 
	 * 交易平台商品编码
	 **/
	public $item_ext_code;
	
	/** 
	 * ERP商品ID
	 **/
	public $item_id;
	
	/** 
	 * 商品名称
	 **/
	public $item_name;
	
	/** 
	 * 销售价格
	 **/
	public $item_price;
	
	/** 
	 * 商品数量
	 **/
	public $item_quantity;
	
	/** 
	 * ERP订单明细行号ID
	 **/
	public $order_item_id;
	
	/** 
	 * 平台交易编码
	 **/
	public $order_source_code;
	
	/** 
	 * 货主ID 代销情况下货主ID和卖家ID不同
	 **/
	public $owner_user_id;
	
	/** 
	 * 货主名称
	 **/
	public $owner_user_name;
	
	/** 
	 * 平台子交易编码
	 **/
	public $sub_source_code;
	
	/** 
	 * 卖家ID,一般情况下，货主ID和卖家ID相同
	 **/
	public $user_id;
	
	/** 
	 * 卖家名称(销售店铺名称)
	 **/
	public $user_name;	
}
?>