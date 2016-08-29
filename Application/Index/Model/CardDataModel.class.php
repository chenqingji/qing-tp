<?php
namespace Index\Model;
use Think\Model;

class CardDataModel extends Model {
    
	Protected $autoCheckFields = false;
	//备注：(status:0默认，1提交未付款，10已付款，11已取消，17————不要用)
    public function checkCardId($cid, $uid) {
        try {
            $m = M();
            $cond = array("cid"=>$cid);

            if(!empty($uid)) {
                $cond["uid"] = $uid;
            }

            $exist = $m->table('order_info')
                       ->where($cond)
                       ->find();
            if(!empty($exist)) {
                return true;
            }
            
        } catch (\Exception $ex) {
            E('checkCardId_ERR');
        }

        return false;
    }

    public function getCardInfo($cid,$uid){
        try {
            $m = M();

            return $m->table('order_info')
                     ->where("cid=%d AND uid=%d AND is_del=0", array($cid,$uid))
                     ->find();
        } catch (\Exception $ex) {
            E('getCardInfo_ERR');
        }           
    }

    public function deleteOrder($uid, $cid) {
        $m = M();
        $m->table('order_info')
            ->where("cid=%d AND uid=%d AND is_del=0", array($cid,$uid))
            ->save(array('is_del'=>1, "coupon_id"=>0));
    }

    public function userCancelOrder($uid, $cid) {
        $m = M();
        $m->table('order_info')
            ->where("cid=%d AND uid=%d AND is_del=0", array($cid,$uid))
            ->save(array('status'=>12, "coupon_id"=>0));
    }

    // 获取在上传状态的最后一个订单
    public function getLastUploadCardInfo($uid, $status) {
        try {
            $m = M();

            return $m->table('order_info')
                ->where("status=%d AND uid=%d AND is_del=0 AND sys IN('android','others','ios')", array($status,$uid))
                ->order('cid desc')
                ->find();
        } catch (\Exception $ex) {
            E('getLastUploadCardInfo_ERR');
        }
    }

    public function getCard($cid){
        try {
            $m = M();
            return $m->table('order_info')
                ->where("cid=%d AND is_del=0", array($cid))
                ->find();
        } catch (\Exception $ex) {
            E('getCardInfo_ERR');
        }
    }
    
    public function getCardByOrderNo($orderNo){
    	try {
    		$m = M();
    		$rs = $m->table('order_info')
    		->where(array('orderno'=>$orderNo))
    		->find();
    		return $rs;
    	} catch (\Exception $ex) {
    		E('getCardInfo_ERR');
    	}
    }

    public function updatePhotoNumber($orderno, $success_number){
        try {
            $m = M();
            $res = $m->query("UPDATE order_info SET photo_number = photo_number + ".$success_number." WHERE `orderno` = '".$orderno."'");
            if($res === false){
                $add_info = $orderno.'补充图片失败';
            }else{
                $add_info = $orderno.'补充图片成功';
            }
            $m->query("INSERT INTO admin_log (info, type, uid, orderno) VALUES ('".$add_info."', 'add_pic', '4120', '".$orderno."')");

        } catch (\Exception $ex) {
            E('updatePhotoNumber_ERR');
        }
    }

    public function saveCard($cid, $info) {
        try {
            $m = M();
            $rs = $m->table('order_info')
                ->where("cid=%d", array($cid))
                ->save($info);
            return $rs;
        } catch (\Exception $ex) {
            E('saveCardData_ERR');
        }
    }

    public function saveCardDataCondition($uid, $cid, $info) {
        try {
            $m = M();
            $rs = $m->table('order_info')
              ->where("uid=%d AND cid=%d AND `paidTime` IS NULL", array($uid, $cid))
              ->save($info);

            return $rs;
        } catch (\Exception $ex) {
            E('saveCardDataCondition_ERR');
        }
    }

    public function saveCardDataConditionWxs($cid, $info) {
        try {
            $m = M();
            $rs = $m->table('order_info')
              ->where("cid=%d AND sys='%s' AND `paidTime` IS NULL", array($cid,'wxs'))
              ->save($info);

            return $rs;
        } catch (\Exception $ex) {
            E('saveCardDataConditionWxs_ERR');
        }
    }

    public function saveCardData($uid, $cid, $info) {
        try {
            $m = M();
            $rs = $m->table('order_info')
              ->where("uid=%d AND cid=%d", array($uid, $cid))
              ->save($info);

            return $rs;
        } catch (\Exception $ex) {
            E('saveCardData_ERR');
        }
    }
    
    /**
     * 更新联系人 联系方式  联系地址信息
     * @param string $uid 用户id
     * @param array $cids 自增id数组
     * @param array $data 更新数据
     * @return type
     */
    public function updateContactData($uid,$cids,$data){
        try {
            $m = M();
            $rs = $m->table('order_info')
              ->where(array("uid"=>$uid,"cid"=>array("in",$cids)))
              ->field("name","phone","province","city","area","street")
              ->save($data);

            return $rs;
        } catch (\Exception $ex) {
            E('saveOrderInfoData_ERR');
        }        
    }

    /**
     * 订单入库
     * @param array $info 订单信息
     * @return int |false
     */
    public function newCardData($info) {
        try {
            return M()->table('order_info')
                     ->data($info)
                     ->add();
        } catch (\Exception $ex) {
            E('newCardData_ERR');
        }
    }
    
    /**
     * order_info中用户电话地址唯一标识
     * @param array $orderInfo 订单信息
     * @return string
     */
    private function generateOrderUnique($orderInfo){
        return md5($orderInfo['uid'].$orderInfo['name'].$orderInfo['phone'].$orderInfo['province'].$orderInfo['city'].$orderInfo['area'].$orderInfo['street']);
    }

    public function getUserConsume($uid) {
        $m = M();
        $ret = $m->table('order_info')
            ->where("uid=$uid AND sys IN('moliAndroid','moliIos') AND status=10")
            ->sum('price');
        return round($ret, 2);
    }

    // 作品列表
    public function listCardData(int $uid, $isPaid=null, $page=1, $pageCount = 100){
        try {
            $condition = "";
            if(!is_null($isPaid)) {
                $condition = " AND paidTime IS" .($isPaid ? " NOT" : "")." NULL";
                if(empty($isPaid)) {
                    $date = date("Y-m-d H:i:s", time() - 86400 * 3);
                    $condition .= " AND create_time>'$date'";
                }
            } else {
                $date = date("Y-m-d H:i:s", time() - 86400 * 3);
                $condition = " AND (create_time>'$date' OR paidTime IS NOT NULL)";
            }
            if(!C("SHOW_ALL_ORDER")) {
                $condition .= " AND sys IN('android','others','ios','wxs','zpk')";
            } else {
                if(empty($isPaid)) {
                    $condition .= " AND sys NOT IN('moliWeb')";
                }
            }
            $m = M();
            $ret = $m->table('order_info')
                        ->where("uid=$uid AND is_del=0 AND status IN('0','1','10') AND photo_number>0".$condition)
                        ->order('cid desc')
                        ->page($page, $pageCount)
                        ->select();
            return $ret;
        } catch (\Exception $ex) {
            E('listCardData_ERR');
        }
    }

    public function listCardDataPage(int $uid, int $isPaid, int $page, int $pageCount)
    {
        try {

			return M()->table('order_info')
			    		->field(array("cid","uid","aid","pics","message","create_time","photo_fee","postage","price","name","phone","province","city","area","street","orderno","mailno","photo_number","paidTime"))
			    		->where("uid=$uid AND paidTime IS" .($isPaid ? " NOT" : "")." NULL AND sys NOT IN('moliWeb','android','others','ios') AND is_del=0")
			    		->order('cid desc')
			    		->page($page, $pageCount)
			    		->select();
        } catch (\Exception $ex) {
            E('listCardData_ERR');
        }
    }
    public function listCardDataPageNew(int $uid, int $status, int $type, int $sort, $wx, $getCoupon, int $pageOrIndex, int $pageCount)
    {
    	try {
    		$m = M();
    		//前三天
    		$date=date("Y-m-d 00:00:00",strtotime("-3 day"));
    		$m->query("UPDATE order_info SET status = 11 WHERE status = 1 AND `uid` = '".$uid."' AND create_time < '".$date."' ");
			if($status == 200){
				$select = "uid=$uid AND status IN('1','10','11','17','12') AND is_del=0";
			}else{
				$select = "uid=$uid AND status=$status AND is_del=0";
			}
            $sort = "cid desc";
            if($sort == "pay") {
                $sort = "paidTime desc";
            }
            if(intval($wx) == 0) {
                $select .= " AND sys NOT IN('moliWeb','android','others','ios','wxs','zpk')";
            } else {
                $select .= " AND sys NOT IN('moliWeb','wxs','zpk')";
            }
            if(!$getCoupon) {
                $select .=" AND coupon_id=0";
            }
			if($type == 1){
    			return $m->table('order_info')
    			->field(array("cid","uid","aid","pics","message","create_time","photo_fee","postage","price","name","phone","province","city","area","street","orderno","mailno","photo_number","paidTime","status","pay_type","print_size","coupon_id"))
    			->where($select)
    			->order($sort)
    			->limit($pageOrIndex, $pageCount)
    			->select();
    		}else{
    			return $m->table('order_info')
    			->field(array("cid","uid","aid","pics","message","create_time","photo_fee","postage","price","name","phone","province","city","area","street","orderno","mailno","photo_number","paidTime","status","pay_type","print_size","coupon_id"))
    			->where($select)
    			->order('cid desc')
    			->page($pageOrIndex, $pageCount)
    			->select();
    		}
    		 
    	} catch (\Exception $ex) {
    		E('listCardData_ERR');
    	}
    }
    
    //获取wxopenid下最新一个相册
    public function getLastCard($uid){
    	try {
    		return M()->table('order_info')
                ->where("uid='%d'", array($uid))
                ->order('cid desc')
                ->find();
    	} catch (\Exception $ex) {
    		E('lastCardData_ERR');
    	}
    }

    public function getField() {
        return array(
            // order info
            'order_info.cid'=>'cid',
	        'order_info.uid'=>'uid',
            'order_info.orderno'=>'orderno',
            'order_info.name'=>'name',
            'order_info.phone'=>'phone',
            'order_info.province'=>'province',
            'order_info.city'=>'city',
            'order_info.area'=>'area',
            'order_info.street'=> 'street',
            'order_info.message'=>'message',
            'order_info.create_time'=>'create_time',
            'order_info.paidTime'=>'paidTime',
            'order_info.sys'=>'sys',
            'order_info.status'=>'status',
        	'order_info.mailno'=>'mailno',
        	'order_info.is_pdf'=>'is_pdf',
            'order_info.is_sync'=>'is_sync',
        	'order_info.pdf_file'=>'pdf_file',
            'order_info.op_status'=>'op_status',
            'order_info.pics'=>'pics',
            // user info
            'user_info.avatar' => 'avatar',
            'user_info.nickname' => 'nickname'
        );
    }

    // 控制台获取订单列表
    public function searchCardData(int $page,int $limit, $condition) {
        try {
            return  M()->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page,$limit)
                ->join('LEFT JOIN user_info ON order_info.uid = user_info.uid')
                ->field($this->getField())
                ->select();
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    // 搜索联系人
    public function searchContactData(int $page,int $limit, $name, $condition) {
        try {
            $m = M();

            if(!empty($condition)) {
                $condition .= " AND ";
            }
            $condition.= "name='$name'";

            $ret = $m->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page,$limit)
                ->join( 'LEFT JOIN user_info ON order_info.uid = user_info.uid')
                ->field($this->getField())
                ->select();

            return $ret;
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    public function searchAppData(int $page,int $limit, $condition) {
        try {
            $m = M();

            if(!empty($condition)) {
                $condition .= " AND ";
            }
            $condition.= 'sys IN (\'moliAndroid\', \'moliIos\')';

            $ret = $m->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page, $limit)
                ->join(array( 'LEFT JOIN user_info ON order_info.uid = user_info.uid'))
                ->field($this->getField())
                ->select();

            return $ret;

        } catch (\Exception $ex) {
            E('searchSyncFailData_ERR');
        }
    }

    public function searchSyncFailData(int $page,int $limit) {
        try {
            $m = M();

            $ret = $m->table('order_info')
                ->where('is_sync=2 OR is_sync=5')
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page,$limit)
                ->join(
                    array(
                        'LEFT JOIN address_info ON order_info.aid = address_info.id',
                        'LEFT JOIN user_info ON order_info.uid = user_info.uid'
                    )
                )
                ->field($this->getField())
                ->select();

            return $ret;

        } catch (\Exception $ex) {
            E('searchSyncFailData_ERR');
        }
    }

    public function searchAuditFailData(int $page, int $limit, int $is_sync) {
        try {

            $m = M();

            $ret = $m->table('order_info')
                ->where('is_sync='.$is_sync)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page,$limit)
                ->join(
                    array(
                        'LEFT JOIN address_info ON order_info.aid = address_info.id',
                        'LEFT JOIN user_info ON order_info.uid = user_info.uid'
                    )
                )
                ->field($this->getField())
                ->select();

            return $ret;

        } catch (\Exception $ex) {
            E('searchAuditFailData_ERR');
        }
    }

    // 搜索微信号
    public function searchWxData(int $page,int $limit, $name, $condition) {
        try {
            $m = M();

            $ids = $m->table('user_info')
                ->where("nickname='%s'", array($name))
                ->select();
            foreach($ids as $k=>$v) {
                $ids[$k] = $v['uid'];
            }

            if(!empty($condition)) {
                $condition .= " AND ";
            }
            $condition.= "`order_info`.`uid` in(".implode(",",$ids).")";

            $ret = $m->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit*$page,$limit)
                ->join('LEFT JOIN user_info ON order_info.uid = user_info.uid')
                ->field($this->getField())
                ->select();

            return $ret;
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    // 搜索快递号
    public function searchExpressData(int $page,int $limit, $mailno, $condition) {
        try {
            $m = M();

            if(!empty($condition)) {
                $condition .= " AND ";
            }
            $condition.= "mailno='$mailno'";

            $ret = $m->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit * $page, $limit)
                ->join( 'LEFT JOIN user_info ON order_info.uid = user_info.uid')
                ->field($this->getField())
                ->select();

            return $ret;
        } catch (\Exception $ex) {
            E('searchWxData_ERR');
        }
    }

    // 搜索电话号码
    public function searchPhoneData(int $page,int $limit, $phone, $condition) {
        try {
            $m = M();

            if(!empty($condition)) {
                $condition .= " AND ";
            }
            $condition.= "phone='$phone'";

            $ret = $m->table('order_info')
                ->where($condition)
                ->order(array('op_status ASC','paidTime DESC'))
                ->limit($limit * $page, $limit)
                ->join( 'LEFT JOIN user_info ON order_info.uid = user_info.uid')
                ->field($this->getField())
                ->select();

            return $ret;
        } catch (\Exception $ex) {
            E('searchWxData_ERR');
        }
    }

    // 获取订单总数
    public function getTotalCardCount() {
        try {
            return  M()->table('order_info')
                ->where("paidTime IS NOT NULL")
                ->count();
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    // 获取订单总数
    public function getOrderCount($time, $type) {
        try {
            $today = date("Y-m-d", $time);
            return M()->table('order_info')
                ->field('count(sys) as count, sys')
                ->where("$type BETWEEN '$today 00:00:00' AND '$today 23:59:59'")
                ->group('sys')
                ->select();
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    public function getTodayPayOrderCount() {
       return $this->getOrderCount(time(), "paidTime");
    }
    public function getTodayCreateOrderCount() {
        return $this->getOrderCount(time(), "create_time");
    }
    public function getYesterdayPayOrderCount() {
        $now = time();
        return $this->getOrderCount($now - ($now % 86400) - 86400, "paidTime");
    }
    public function getYesterdayCreateOrderCount() {
        $now = time();
        return $this->getOrderCount($now - ($now % 86400) - 86400 , "create_time");
    }
    
    // 获取本月订单总数
    public function getMonthCardCount() {
    	try {
    		$firstday = date('Y-m-01');					//当前月第一天
    		$lastday = date('Y-m-t');					//当前月最后一天
    		return  M()->table('order_info')
    		->where("paidTime BETWEEN '".$firstday." 00:00:00' AND '".$lastday." 23:59:59'")
    		->count();
    	} catch (\Exception $ex) {
    		E('getMonthCardCount_ERR');
    	}
    }
    
    //获取上月订单总数
    public function getPreMonthCardCount($timeStr){
    	try {
    		$firstday = date('Y-m-01', strtotime($timeStr));			//上月第一天
    		$lastday = date('Y-m-t', strtotime($timeStr));				//上月最后一天
    		return  M()->table('order_info')
        		->where("paidTime BETWEEN '".$firstday." 00:00:00' AND '".$lastday." 23:59:59'")
        		->count();
    	} catch (\Exception $ex) {
    		E('getReMonthCardCount_ERR');
    	}
    }

    // 获取订单总数
    public function getNotDownloadCount() {
        try {
            return  M()->table('order_info')
                ->where("op_status = 0 AND paidTime IS NOT NULL")
                ->count();
        } catch (\Exception $ex) {
            E('listAllCardData_ERR');
        }
    }

    // 更新订单地址信息
    public function updateCardData($cid, $info) {
        try {
            $m = M();
            $rs = $m->table('order_info')
                ->where("mailno IS NULL AND cid=%d", array($cid))
                ->save($info);

            return $rs;
        } catch (\Exception $ex) {
            E('saveCardData_ERR');
        }
    }

    public function resetAllOrder(){
        try {
            $rs = 0;
            $m = M();

            $result = $m->query("UPDATE order_info SET is_sync=0, is_pdf=0, mailno=NULL WHERE is_sync=5 OR is_sync=2");
            if($result === false){
                $reset_info = '重置所有订单失败';
            }else{
                $reset_info = '重置所有订单成功';
            }
            $m->query("INSERT INTO admin_log (info, type, uid, orderno) VALUES ('".$reset_info."', 'reset_order', '4120', '0')");

            return $rs;
        } catch (\Exception $ex) {
            E('resetAllOrder_ERR');
        }
    }

    // 重置订单
    public function resetCardData($cid,$oid) {
        try {
            $rs = 0;
            $m = M();
            $order = $m->table('order_info')
                ->where("cid=$cid AND orderno=$oid")
                ->find();

            if($order) {
                $result = $m->query("UPDATE order_info SET is_sync=0, is_pdf=0, mailno=NULL WHERE cid=$cid");
                if($result === false){
                    $reset_info = $oid.'重置订单失败';
                }else{
                    $reset_info = $oid.'重置订单成功';
                }
                $m->query("INSERT INTO admin_log (info, type, uid, orderno) VALUES ('".$reset_info."', 'reset_order', '4120', '".$oid."')");

                $packageM = new PackageModel();
                $rs = $packageM->resetPackage($oid);
            }

            return $rs;
        } catch (\Exception $ex) {
            E('saveCardData_ERR');
        }
    }
    
    /**
     * 单纯重置订单 is_sync=0, is_pdf=0, mailno=NULL
     * @param type $cid
     * @param type $orderno
     * @return type
     */
    public function resetOrderInfo($cid,$orderno){
            $m = M();
            return $m->execute("UPDATE order_info SET is_sync=0, is_pdf=0, mailno=NULL WHERE cid=$cid and orderno='".$orderno."'");
    }

    // 取消订单
    public function cancelCardData($cid,$oid) {
        try {
            $rs = 0;
            $m = M();
            $order = $m->table('order_info')
                ->where("cid=$cid AND orderno=$oid")
                ->find();

            if($order['coupon_id']) {
                $m->table('user_coupon_info USE INDEX(order_id)')
                    ->where("order_id=$cid")
                    ->save(array(
                        'order_id'=>0,
                        'used' => 0,
                        'unlock_time'=>0
                    ));
            }

            if($order) {
                /*
                if($order['is_sync'] != 0){
                    return 1;
                }else{
                    $m->query("UPDATE order_info SET status=17 WHERE cid=$cid");
                }
                */
                $m->query("UPDATE order_info SET status=17,coupon_id=0 WHERE cid=$cid");
            }

            return $rs;
        } catch (\Exception $ex) {
            E('cancelCardData_ERR');
        }
    }
    
    //保存错误信息
    public function savePayError($type,$msg){
    	return M()->table('pay_error')
    	->data(array("type"=>$type,"msg"=>$msg))
    	->add();
    }

    public function appGetCard($cid,$uid){
        try {
            $m = M();

            return $m->table('order_info')
                ->where("cid=%d AND uid=%d", array($cid,$uid))
                ->field(array("cid","uid","aid","pics","message","create_time","photo_fee","sys","postage","price","name","phone","province","city","area","street","orderno","status","mailno","photo_number","paidTime","pay_type","print_size","coupon_id"))
                ->find();
        } catch (\Exception $ex) {
            E('getCardInfo_ERR');
        }
    }
    
    public function appGetCardNew($cid){
    	try {
    		$m = M();
    
    		return $m->table('order_info')
    		->where("cid=%d", array($cid))
    		->field(array("cid","uid","aid","pics","message","create_time","photo_fee","postage","price","name","phone","province","city","area","street","orderno","status","mailno","photo_number","paidTime","pay_type","print_size","coupon_id"))
    		->find();
    	} catch (\Exception $ex) {
    		E('getCardInfo_ERR');
    	}
    }
    
    /**
     * 获取指定条件的一条记录
     * @param array $where 条件
     * @param array $field * | array
     * @param string $order id=>dsc,do=>asc,name 默认asc
     * @return false|null|array 一维数组
     */
    public function baseFind($where = array(), $field = "*", $order = array()) {
        try {
            $m = M("order_info");
            $m->field($field)->where($where);
            if (!empty($order)) {
                $m->order($order);
            }
            return $m->find();
        } catch (\Exception $ex) {
            E('baseFind ERR.');
        }
    }    
    
    /**
     * 获取指定条件的记录
     * @param array $where 条件
     * @param array $field * | array
     * @param string $order id=>dsc,do=>asc,name 默认asc
     * @param string $limit '0,10' 10
     * @return false|null|array 二维数组
     */
    public function baseGet($where = array(), $field = "*", $order = array(),$limit='') {
        try {
            $m = M("order_info");
            $m->field($field)->where($where);
            if (!empty($order)) {
                $m->order($order);
            }
            if(!empty($limit)){
                $m->limit($limit);
            }
            return $m->select();
        } catch (\Exception $ex) {
            E('baseGet ERR.');
        }
    }    
    
    /**
     * 订单详情
     * @return string
     */
   /* public function dealOrderInfoData($cid,$uid){
        $orderData = $this->getCardInfo();
    }*/
    
}