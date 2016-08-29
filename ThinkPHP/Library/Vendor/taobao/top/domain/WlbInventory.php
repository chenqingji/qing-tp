<?php

/**
 * 库存详情对象。其中包括货主ID，仓库编码，库存，库存类型等属性
 * @author auto create
 */
class WlbInventory
{
	
	/** 
	 * 商品ID
	 **/
	public $item_id;
	
	/** 
	 * 冻结(锁定)数量，用来跟踪库存的中间状态，比如前台销售了1件商品，这时lock加1，当商品出库的时候lock再减回去
	 **/
	public $lock_quantity;
	
	/** 
	 * 库存数量(有效数量)
	 **/
	public $quantity;
	
	/** 
	 * 仓库编码，关联到仓库类型服务的编码非托管库存(卖家自己管理的库存，物流宝不可见又称自有库存)的所在仓库编码: STORE_SYS_PRIVATE
	 **/
	public $store_code;
	
	/** 
	 * VENDIBLE--可销售库存
FREEZE--冻结库存
ONWAY--在途库存
DEFECT--残次品库存
	 **/
	public $type;
	
	/** 
	 * 货主ID
	 **/
	public $user_id;	
}
?>