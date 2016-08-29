var couponID = null;
function initCouponWebviewData(couponData, totalPrice, currentCouponID){
    // 优惠卷对话框操作
    couponData = JSON.parse(couponData);
    couponID = currentCouponID;
    appendEle();
    function appendEle(){
        var htmlStr = "<div class='dont-use-coupon-div canUser' id='coupon_none'>不使用任何优惠券</div>";
        var validList = "";
        var invalidList = "";

        for(var i= 0,len=couponData.length;i<len;++i) {
            var coupon = couponData[i];
            //console.log(coupon);return;
            if(coupon['data']['ex_data']['least']/100 <= totalPrice) {
                var  markIcon = '';
                if(coupon['data']['id'] == couponID) {
                    markIcon = '<img class="mark-icon" src="/Public/Image/base/select.png" />';
                }
                validList += template(coupon, 'canUser', 'coupon_'+i, markIcon);
            } else {
                invalidList += template(coupon, '', 'coupon_'+i, '');
            }
        }
        htmlStr += validList + invalidList;

        $("#couponList").html(htmlStr);
        $(".coupon-div.canUser,.dont-use-coupon-div.canUser").on('click',function(){
            //setCoupon(idx = $(this).attr("id").substr(7), false);
            couponID = $(this).data('id');
            var couponPrice = $(this).data('reduce') / 100;
            var payPrice = totalPrice - couponPrice;
            var couponTitle = $(this).data('title');
            var couponDes = $(this).data('des');
            if (couponID == undefined) {
                couponID = 0;
                couponPrice = 0;
                couponTitle = '无';
                payPrice = totalPrice;
                couponDes = '';
            }
            $('.coupon-div').removeClass('canUser');
            $('.mark-icon').remove();
            $(this).find('.right-div').append('<img class="mark-icon" src="/Public/Image/base/select.png" />');
            $(this).addClass('canUser');
            if(typeof android != "undefined") {
                if(typeof android.chooseCoupon != "undefined") {
                    android.chooseCoupon(couponID, couponPrice, couponTitle, payPrice, couponDes);
                }
            }
            if(typeof chooseCoupon != "undefined") {
                chooseCoupon({
                    currentCouponID : couponID + '',
                    couponPrice : couponPrice + '',
                    couponDes: couponTitle + '',
                    payPrice : payPrice + '',
                    couponDesDetail : couponDes
                });
            }
        });
    }

    // var android = {
    //     chooseCoupon: function (currentCouponID, couponPrice, couponDes, payPrice){
    //         console.log(currentCouponID, couponPrice, couponDes, payPrice);
    //     }
    // }

    //模板
    function template(data, useClass, useId, markIcon){
        //console.log(data);
        var str = '';
        var typeClass;
        var type = data['type'];
        typeClass = (type == 1 ? 'free-coupon' : 'not-free-coupon');
        if(useClass == ''){
            typeClass = 'exc-date';
        }
        str =
            '<div class="coupon-div '+useClass+' '+typeClass+'" data-title="'+data['subDes']['val']+'" data-des="'+data['subDes']['des']+'" data-reduce="'+data['data']['ex_data']['reduce']+'" data-id="'+data['data']['id']+'" id="'+useId+'">'+ //free-coupon  not-free-coupon  exc-date
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
};