function shareTimeLineOK(){
	//分享到朋友圈
    var request = new XMLHttpRequest();
	request.open('POST', wx_root_url + 'addShare', true);
	request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    request.send("cid="+wx_root_sid+"&aid="+wx_root_aid);
}
 
function sendAppMsgOK(){
	//分享给朋友
    var request = new XMLHttpRequest();
	request.open('POST', wx_root_url + 'addShare', true);
	request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    request.send("cid="+wx_root_sid+"&aid="+wx_root_aid);
}
 
function sendAppMsgCancel(){}

function setsharedata(title,link,img){
	wxConfig.share_title = title;
    if(link != ""){
    	wxConfig.share_link = link;
    }
    if(img != ""){
    	wxConfig.share_img_link = img;
    }
    refreshWxShareData();
}

function refreshWxShareData(){
    // 2.1 监听“分享给朋友”，按钮点击、自定义分享内容及分享结果接口
	var share_title = wxConfig.share_title == ''?"Qing":wxConfig.share_title;
    var send_desc = wxConfig.share_title == '' ? '亲，我在“Qing”打印了一些精美照片，您也来试试吧！' : wxConfig.share_title;
    var share_desc = wxConfig.share_desc == '' ?'亲，我在“Qing”打印了一些精美照片，您也来试试吧！' : wxConfig.share_desc;
    
    wx.onMenuShareAppMessage({
        title   : share_title,
        desc    : share_desc,
        link    : wxConfig.share_link,
        imgUrl  : wxConfig.share_img_link,
        trigger : function (res) {},
        success : function (res) {
            (wxConfig.sendAppMsgOK)();
        },
        cancel  : function (res) {
            (wxConfig.sendAppMsgCancel)();
        },
        fail    : function (res) {
            alert(JSON.stringify(res));
        }
    });
 
    // 2.2 监听“分享到朋友圈”按钮点击、自定义分享内容及分享结果接口
    wx.onMenuShareTimeline({
        title   : send_desc,
        link    : wxConfig.share_link,
        imgUrl  : wxConfig.share_img_link,
        trigger : function (res) {},
        success : function (res) {
            (wxConfig.shareTimeLineOK)();
        },
        cancel  : function (res) {
            (wxConfig.sendAppMsgCancel)();
        },
        fail    : function (res) {
            alert(JSON.stringify(res));
        }
    });
}


wx.error(function (res) {
	//  alert(res.errMsg);
});