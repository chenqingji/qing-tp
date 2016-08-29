// 更新提醒张数和价格
function refreshPicInfo(){
    var imgsNum = $("#picsGroup .upload-img").length;
    if(imgsNum == 0){
        $("#uploadBtn").html("添加照片");
        $('#submitBtnEx').css('background-color', '#eceded');
    }else{
        $("#uploadBtn").html("继续添加");
        $('#submitBtnEx').css('background-color', '#54D175');
    }
    $("#picInfoNum").html(imgsNum);
    $("#picInfoNumEx").html(imgsNum);
    var price = 0;
    if(imgsNum > 0) {
        //根据单数计算套餐价格
        price = parseFloat((9.9 + parseInt((imgsNum-1)/20)*8.9).toFixed(1));
        $('#picsGroup').removeClass("no-pics");
    } else {
        $('#picsGroup').addClass("no-pics");
    }
    $("#setNum").html(parseInt(imgsNum/20+1));
    $(".payPrice").html((price).toFixed(1));
}

function setCoupon(idx, bindCoupon, init) {
    var couponDes = '',
        couponVal = '',
        reducePriceStr= init? '无可用优惠券' : '不使用优惠券',
        picInfoPriceStr=parseFloat($("#picInfoPrice").html());
    couponId = couponIdx = undefined;
    if(idx != 'none') {
        idx = parseInt(idx);
        coupon = couponData[idx];
        var reduceFee = (coupon['data']['ex_data']['reduce']/100);
        couponDes = coupon['subDes']['des'];
        reducePriceStr = coupon['subDes']['val'];
        picInfoPriceStr = (picInfoPriceStr - (coupon['data']['ex_data']['reduce']/100)).toFixed(1);
        couponIdx = idx;
        couponId = coupon['data']['id'];

        couponVal = '|已优惠￥'+reduceFee;
    }
    $(".coupon-des").html(couponVal);
    $("#payPriceEx").html(picInfoPriceStr);
    $("#reducePrice span").html(reducePriceStr);
    $("#couponDes").html(couponDes);

    //if(bindCoupon) {
    //    var cId =
    //    $.ajax({
    //        url: "/index/index/saveOrderEx",
    //        type: 'POST',
    //        dataType: 'json',
    //        data: orderInfo,
    //        success: function (data) {
    //            if(data.status == 'ok') {
    //            } else {
    //                alert(data.reason);
    //            }
    //        },
    //        error: function() {
    //            alert("保存订单失败");
    //        }
    //    });
    //}
}

;(function(){
    // 优惠卷对话框操作
    $("#openCouponDlg").on('click',function(){
        var htmlStr = "<div class='dont-use-coupon-div canUser' id='coupon_none'>不使用任何优惠券</div>";
        var validList = "";
        var invalidList = "";
        var price=parseFloat($("#picInfoPrice").html());

        for(var i= 0,len=couponData.length;i<len;++i) {
            var coupon = couponData[i];
            //console.log(coupon);return;
            if(coupon['data']['ex_data']['least']/100 <= price) {
                var  markIcon = '';
                if(i == couponIdx) {
                    markIcon = '<img class="mark-icon" src="/Public/Image/base/select.png" />';
                }
                validList += template(coupon, 'canUser', 'coupon_'+i, markIcon);
            } else {
                invalidList += template(coupon, '', 'coupon_'+i, '');
            }
        }
        htmlStr += validList + invalidList;

        $("#couponList").html(htmlStr);
        $("#couponDlg").css("display","block");
        $(".coupon-div.canUser,.dont-use-coupon-div.canUser").on('click',function(){
            setCoupon(idx = $(this).attr("id").substr(7), false, false);
            $("#couponDlg").css("display","none");
        });
    });

    //模板
    function template(data, useClass, useId, markIcon){
        console.log('in');
        var str = '';
        var typeClass;
        var type = data['type'];
        typeClass = (type == 1 ? 'free-coupon' : 'not-free-coupon');
        if(useClass == ''){
            typeClass = 'exc-date';
        }
        str =
            '<div class="coupon-div '+useClass+' '+typeClass+'" id="'+useId+'">'+ //free-coupon  not-free-coupon  exc-date
                '<div class="left-div ">'+
                    '<div>'+
                        '<p>'+data['title']['title']+'</p>'+
                        '<p>'+data['title']['des']+'</p>'+
                    '</div>'+
                '</div>'+
                '<div class="right-div">'+
                    '<p>'+data['des']['title']+'</p>'+
                    '<p>'+data['des']['des']+'</p>'+
                    '<p>'+data['des']['duration']+'</p>'+
                    markIcon+
                '</div>'+
            '</div>';
        return str;
    }

    $('#closeCouponDlg') .on('click',function(){
        $("#couponDlg").css("display","none");
    });
})();