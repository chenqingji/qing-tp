/* 设置要保存的数据  */
// 更新提醒张数和价格
function refreshPicInfo(){
    var imgsNum = $("#picsGroup .upload-img").length;
    if(imgsNum == 0){
        $("#uploadBtn").html("添加照片");
        $("#uploadBtn").removeClass("btn-primary");
        $("#uploadBtn").addClass("btn-success");
    }else{
        $("#uploadBtn").html("继续添加照片");
        $("#uploadBtn").addClass("btn-primary");
        $("#uploadBtn").removeClass("btn-success");
    }
    $("#picInfoNum").html(imgsNum);
}