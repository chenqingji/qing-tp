/**
 * Created by CaoQiSen on 2016/6/22.
 */
(function(){
    if(couponData["data"].length == 0) {
        var couponListEle = $('.coupon-list-div');
        couponListEle.append(noCouponHtmlStr);
    }
    loadData(couponData);
    //加载数据
    function loadData(data){
        var couponListEle = $('.coupon-list-div');
        var str = '';
        $.each(data.data, function(i, v){
            str = template(v);
            couponListEle.before(str);
        })
    }

    //模板
    function template(data){
        var str = '';
        var typeClass;
        var type = data['type'];
        var markIcon = '';
        var href = '#';
        if (couponType != -1) {
            typeClass = (type == 1 ? 'free-coupon' : 'not-free-coupon');
            if(useUrl) {
                href = useUrl;
            }
            if(data['bind']) {
                markIcon = '<img class="mark-icon" src="/Public/Image/base/bind.png" />';
                href = "javascript:bindUrl("+data['order']+")";
            }
        } else {
            $('.banner-title').text('历史优惠券');
            $('.check-coupon-histroy').remove();
            var d = (type == 1 ? 'free-coupon' : 'not-free-coupon');
            typeClass = 'exc-date '+d+'';
            markIcon = '<img class="mark-icon" src="/Public/Image/base/'+(data['used'] == 0 ? "exc-date" : "used")+'.png" />';
        }
        str =
            '<div class="coupon-div '+typeClass+'">'+ //free-coupon  not-free-coupon  exc-date
                '<a href="'+href+'">'+
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
                '</a>'+
            '</div>';
        return str;
    }

    //返回
    $('.back-btn-icon').on('click', function(){
//                var numberOfEntries = window.history.length;
//                if(numberOfEntries > 0){
//                    window.history.back();
//                } else {
//                    window.location.href = 'http://www.baidu.com';
//                }
        window.location.href = backUrl;
    });

    //下拉刷新
    var scrollTopVal;
    var winH = $(window).height(); //页面可视区域高度
    var page = 1; //设置当前页数
    var isAjax = false;

    $(window).scroll(function(event) {
        if(notAjax){
            return;
        }
        var scrollT = $(window).scrollTop(); //滚动条top
        if(isAjax) {
            return;
        }
        scrollTopVal = $(window).scrollTop();//返回 匹配元素的滚动条的垂直位置
        var pageH = $(document.body).height();//浏览器当前窗口文档body的高度
        var aa = (pageH-winH-scrollT)/winH;
        if(aa<0.02 && page != null){
            isAjax = true;
            page = page + 1;
            $.ajax({
                type:'POST',
                url: ajaxUrl,
                data:{
                    type: couponType,
                    page:(page)
                },
                success:function(data){
                    if(data.status != 'error'){
                        if(data.data.data.length != 0){
                            loadData(data.data);
                            isAjax = false;
                        } else {
                            return;
                        }

                    }else {
                        isAjax = false;
                    }
                }
            })
        }
    });
}())