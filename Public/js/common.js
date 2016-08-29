/* 设置要保存的数据  */
$('#closeWarmBtn').on('click',function(e){
    $('#warmDlg').hide();
});

function showWarmDlg(txt, title) {
    $('#warmTitle').html(title?title:"温馨提示");
    $('#warmTxt').html(txt);
    $('#warmDlg').show();
}

// 图片节点
function newPicItem(url, type, id, upload) {
    // data-type
    // * 值为 wx 表示微信上传
    // * 值为 file 表示本地上传
    // data-id
    // * 对于微信上传的图片 data-id 即使 wx 上传到服务器的 id.
    // * 对于本地上传的图片 data-id 为 fileArray 的索引
    // data-upload
    // * 是否已上传过.
    var div = $('<div><img class="del-img" src="/Public/Image/base/delete.png"/></div>');
    var img = $('<img class="upload-img" data-type="'+type+'" data-id="'+id+'" data-upload="'+upload+'" data-download="0"/>');

    var noPicsMask = $("#noPicsMask");
    if(noPicsMask) {
        noPicsMask.remove();
    }

    // 删除图片
    div.find('.del-img').on("click",function(e) {
        var picGroup = $(this).parent().parent();
        $(this).parent().remove();
        refreshPicInfo();
        picGroup.scroll();

        var warmImg = picGroup.find('.warm-img');
        if(warmImg.length > 0) {
            $("#warnDes").show();
        } else {
            $("#warnDes").hide();
        }

        // 防止冒泡
        e.stopImmediatePropagation();
        e.stopPropagation();
        e.preventDefault();
        return false;
    });

    // 预览图片
    div.on('click',function(){
        var imgs = $("#picsGroup .upload-img"),
            offset = $(this).index(),
            urls = [];
        for(var i=0,len=imgs.length;i<len;++i) {
            //urls.push(imgs.eq((i+offset)%len).attr("src"));
            urls.push(imgs.eq(i).attr("src"));
        }
        wx.previewImage({
            current: imgs.eq(offset).attr("src"), // 当前显示图片的http链接
            urls: urls // 需要预览的图片http链接列表
        });
    });

    div.prepend(img);
    if(url) {
        img.error(function(){
            img.parent().remove();
            refreshPicInfo();
        });
    }
    img.attr("src", url);

    return div;
}

var toOpenImgChoose = false;
function choosePics() {
    var uploadCount = $(".upload-img").length;
    if(uploadCount < maxSelectPicCount) {
        //if(/Android/i.test(navigator.userAgent)) {
        // android 系统
            if(wx != undefined && isInit && !toOpenImgChoose) {
                toOpenImgChoose = true;
                setTimeout(function(){
                    toOpenImgChoose = false;
                }, 800);
                wx.chooseImage({
                    count: Math.min(maxSelectPicCount - uploadCount,9), // 默认 9
                    sizeType: ['original'],
                    sourceType: ['album'],
                    success: function (res) {
                        var localIds = res.localIds;
                        var picsGroup = $('#picsGroup');
                        for(var idx=0;idx<localIds.length;++idx) {
                            var pItem = newPicItem(localIds[idx],uploadCfg["wxLocalType"],0,0);
                            picsGroup.prepend(pItem);
                        }
                        $("#picsGroup")[0].scrollTop = 0;
                        refreshPicInfo();
                    }
                });
            }
        //} else {
        //   // 其他系统 ios 平台判断 ## /iPhone|iPad|iPod/i.test(navigator.userAgent);
        //   $('#upload').eq(0).trigger('click');
        //}
    } else {
        showWarmDlg("您已达到最大上传图片限制("+maxSelectPicCount+"张)了!");
    }
}

;(function(){
    upyun.set('bucket','molikuaiyin');
    upyun.set('form_api_secret', 'J8t3ar7et40tzqRFDmk2iX0aF/Q=');

    var isCreating = false;

    var uploadMap = {};

    $("#feeDes").on('click',function(e){
        showWarmDlg("每20张一组,第一组9.9元,第二组起每组8.9元(全部包邮)", "价格介绍");
    });

    var defaultSelect = {
        "province" : "请选择省",
        "city" : "请选择市",
        "area" : "请选择区"
    };

    var updateFrequent = 1;
    var fileArray = [];
    var orderInfo = {};
    var orderNecessaryInfo = [
        "#name",
        "#phone",
        "#street"
    ];

    // 地址选择容器
    var provinceSelect = $("#address .prov"),
        citySelect = $("#address .city"),
        areaSelect = $("#address .area");

    // 图片添加
    $('#uploadBtn,#uploadInitBtn').on('click', choosePics);

    // 本地上传图片
    $('#upload').on('change',function(e){
        var file = $(this)[0];
        var picsGroup = $('#picsGroup');
        var uploadCount = $(".upload-img").length;

        for(var idx= 0,len=Math.min(file.files.length,maxSelectPicCount - uploadCount);idx<len;++idx) {
            window.URL = window.URL || window.webkitURL;
            fileArray.push(file.files[idx]);
            picsGroup.prepend(newPicItem(window.URL.createObjectURL(file.files[idx]),uploadCfg['uploadType'],fileArray.length - 1,0));
        }

        $(file).parent()[0].reset();
        refreshPicInfo();
    });

    // 输入检查
    function onInputBlur(e) {
        var input= $(this);
        if((input.val()+"").trim() == "") {
            input.parent().addClass("has-error");
        } else {
            input.parent().removeClass("has-error");
        }
    }
    for(var i=0;i<orderNecessaryInfo.length;++i) {
        $(orderNecessaryInfo[i]).on("blur",onInputBlur);
    }

    // 获取表单信息
    function getFormData(check) {
        // 检查表单信息
        for(var i=0;i<orderNecessaryInfo.length;++i) {
            var input = $(orderNecessaryInfo[i]);
            var value = (input.val()+"").trim();
            if(value == ""){
                input.parent().addClass("has-error");
            }
            orderInfo[input.attr('id')] = value;
        }

        // 判断电话号码是否为纯数子
        if(orderInfo["phone"].length != 11 || orderInfo["phone"].search(/[^0-9]/) != -1) {
            showWarmDlg("请输入正确的手机号码!");
            return false;
        }

        // 获取留言
        input = $("#message");
        orderInfo[input.attr('id')] = (input.val()+"").trim();

        // 获取省市区
        var province = provinceSelect.val();
        if(province) {
            if(province == defaultSelect["province"] || province == "") {
                provinceSelect.parent().addClass("has-error")
            }
            orderInfo["province"] = province;
        }
        var city = citySelect.val();
        if(city) {
            if(city == defaultSelect["city"] || city == "") {
                citySelect.parent().addClass("has-error")
            }
            orderInfo["city"] = city;
        }
        var area = areaSelect.val();
        if(area) {
            if(area == defaultSelect["area"] || area == "") {
                areaSelect.parent().addClass("has-error")
            }
            orderInfo["area"] = area;
        }

        if(province == defaultSelect["province"] || province == "") {
            showWarmDlg("请选择收货地址的省份");
            return false;
        }
        if(city == defaultSelect["city"] || city == "") {
            showWarmDlg("请选择收货地址的城市");
            return false;
        }
        if(area == defaultSelect["area"] || area == "") {
            showWarmDlg("请选择收货地址的地区");
            return false;
        }

        if(check && $(".has-error").length > 0) {
            showWarmDlg("请您把订单信息填写完整.");
            return false;
        }
        return true;
    }

    // 上传图片
    function upLoadImgs(imgs, callback) {
        // 检查图片信息
        var uploadIdx = 0;
        orderInfo["status"] = 0;
        var errNum = 0;
        uploadMap = {};

        function uploadOnceEnd(save) {
            function saveImgData(func) {
                $.ajax({
                    url: "/index/index/saveOrderEx",
                    type: 'POST',
                    dataType: 'json',
                    data: orderInfo,
                    success: function (data) {
                        if(data.status == 'ok') {
                            func(data);
                        } else {
                            showWarmDlg(data.reason);
                            $("#cover").hide();
                        }
                    },
                    error: function() {
                        showWarmDlg("保存订单失败");
                    }
                });
            }
            if(++uploadIdx < imgs.length) {
                // 定时保存订单
                if(save) {
                    if(uploadIdx % updateFrequent == 0 && uploadIdx > 0) {
                        saveImgData(function(){ uploadImg(); });
                    } else {
                        uploadImg();
                    }
                } else {
                    uploadImg();
                }
            } else {
                if($(".upload-img[data-upload='0']").length == 0) {
                    orderInfo["status"] = 1;
                    orderInfo["nocheck"] = 1;
                }

                saveImgData(function(){
                    $("#cover").hide();
                    fileArray = [];
                    if(errNum) {
                        $("#updateWarmTxt").html("上传成功 "+(imgs.length-errNum)+" 张, 未上传 "+errNum+" 张.")
                        $("#updateWarmDlg").show();
                    } else {
                        callback();
                    }
                });
            }
        }

        function uploadImg() {
            var img = imgs.eq(uploadIdx);
            $("#coverPicNum").html(uploadIdx+1);
            $("#coverProNum").html("");

            if(img.data("upload") == 1){
                orderInfo["pics"][uploadIdx] = {
                    "type": img.data("type"),
                    "url" : img.data("id")
                };
                uploadOnceEnd(false);
            } else if(img.data("type") == uploadCfg["wxLocalType"] && wx != undefined) {

                if(!uploadMap[img.attr("src")]) {
                    wx.uploadImage({
                        localId: img.attr("src"),
                        isShowProgressTips: 0,
                        success: function (res) {
                            var serverId = res.serverId;
                            uploadMap[img.attr("src")] = serverId;
                            orderInfo["pics"][uploadIdx] = {
                                "type": uploadCfg["wxType"],
                                "url" : serverId
                            };
                            imgs.eq(uploadIdx).data("type",uploadCfg["wxType"]);
                            imgs.eq(uploadIdx).data("upload",1);
                            imgs.eq(uploadIdx).data("id",serverId);
                            uploadOnceEnd(true);
                        },
                        fail: function (res){
                            ++errNum;
                            orderInfo["pics"][uploadIdx] = {
                                "type": img.data("type"),
                                "url" : img.attr("src")
                            };
                            imgs.eq(uploadIdx).parent().append('<img class="warm-img" style="width: 1.5rem; height: 1.5rem; position: absolute; top: .6rem; right: .5rem;" src="/Public/Image/base/warm.png"/>');
                            uploadOnceEnd(false);
                        }
                    });
                } else { // 已经上传过的直接读取 server id
                    orderInfo["pics"][uploadIdx] = {
                        "type": uploadCfg["wxType"],
                        "url" : uploadMap[img.attr("src")]
                    };

                    imgs.eq(uploadIdx).data("type",uploadCfg["wxType"]);
                    imgs.eq(uploadIdx).data("upload",1);
                    imgs.eq(uploadIdx).data("id",uploadMap[img.attr("src")]);
                    uploadOnceEnd(true);
                }
            } else if (img.data("type") == uploadCfg['uploadType']) {
                var imgName = '10'+(new Date()).valueOf()+"_"+Math.floor(Math.random()*10000)+'.jpeg';
                var file = fileArray[img.data("id")];

                upyun.upload(
                    uploadCfg["cid"],
                    file,
                    imgName,
                    function(err, response, image) {
                        if (!err && image.code === 200 && image.message === 'ok') {
                            imgs.eq(uploadIdx).attr('src', image.absUrl);
                            imgs.eq(uploadIdx).data("id", image.absUrl);
                            imgs.eq(uploadIdx).data("upload",1);

                            orderInfo["pics"][uploadIdx] = {
                                "type": uploadCfg['uploadType'],
                                "url" : image.absUrl
                            };
                            uploadOnceEnd(true);
                        } else {
                            imgs.eq(uploadIdx).parent().remove();
                            showWarmDlg("上传照片失败");
                        }
                    },
                    function (progress) {
                        console.log(progress);
                        $("#coverProNum").html("("+progress+"%)");
                    }
                );
            }
        }

        $("#cover").show();
        uploadImg();
        return true;
    }

    //function upLoadImgs(imgs, callback) {
    //    for(var i = 0, len=imgs.length; i< len; ++i) {
    //        var img = imgs.eq(i);
    //        orderInfo["pics"][i] = {
    //            "type": img.data("type"),
    //            "url" : img.data("type") == uploadCfg["wxType"] ? img.data("id") : img.attr("src")
    //        };
    //    }
    //
    //    $.ajax({
    //        url: "/index/index/saveOrderEx",
    //        type: 'POST',
    //        dataType: 'json',
    //        data: orderInfo,
    //        success: function (data) {
    //            if(data.status == 'ok') {
    //                callback();
    //            } else {
    //                alert(data.reason);
    //                $("#cover").hide();
    //            }
    //        },
    //        error: function() {
    //            alert("保存订单失败");
    //        }
    //    });
    //}

    // 保存表单
    function saveOrderData(check) {
        if(getFormData(check)) {
            delete orderInfo["nocheck"];
            $.ajax({
                url: "/index/index/saveOrderEx"+(couponId?"?couponId="+couponId:""),
                type: 'POST',
                dataType: 'json',
                data: orderInfo,
                success: function (data) {
                    if(data.status == 'ok') {
                        data = data.data;
                        uploadCfg["cid"] = data['cid'];
                        if(parseInt(data["aid"])) {
                            uploadCfg["aid"] = parseInt(data["aid"]);
                        }
                        if(data["payUrl"]) {
                            var url = data["payUrl"];
                            document.location.href = url;
                        } else {
                            showWarmDlg("保存订单成功");
                        }
                    } else {
                        showWarmDlg(data.reason);
                        $("#cover").hide();
                    }
                    orderInfo = {};
                },
                error: function() {
                    showWarmDlg("保存订单失败");
                }
            });
        }
    }

    function showAddressView() {
        var price = 0;
        var imgsNum = $("#picsGroup .upload-img").length;
        if(imgsNum > 0) {
            //根据单数计算套餐价格
            price = parseFloat((9.9 + parseInt((imgsNum-1)/20)*8.9).toFixed(1));
        }
        $(".picInfoPrice").html(price);
        var couponIdxTemp = undefined, coupon = undefined;
        var i= 0,len=couponData.length;
        if(len > 0) {
            for(;i<len;++i) {
                coupon = couponData[i];
                if(coupon['data']['ex_data']['least']/100 <= price) {
                    if(undefined == couponIdxTemp || parseInt(coupon['data']['ex_data']['reduce']) > parseInt(couponData[couponIdxTemp]['data']['ex_data']['reduce'])) {
                        couponIdxTemp = i;
                    }
                }
            }
        } else { // 隐藏优惠券选择列
            if(couponData.length == 0) {
                $(".content.third").hide();
            }
        }
        if(couponIdx != couponIdxTemp) {
            couponIdx = couponIdxTemp;
        }
        setCoupon(couponIdx == undefined? "none" : couponIdx, false, true);
        $("#addressView").css('top','0%');
    }

    function addressView(check){
        // 上传图片
        orderInfo["pics"] = {};
        orderInfo["cid"] = uploadCfg["cid"];
        if(uploadCfg["aid"] != null) {
            orderInfo["aid"] = uploadCfg["aid"];
        }
        var imgs = $("#picsGroup .upload-img");
        var uploadImgCount = imgs.length;
        // 图片效验
        if(uploadImgCount < minUploadPicsCount) {
            showWarmDlg(" 请继续上传");
            return false;
        }
        if(uploadImgCount < needWarmCount && check) {
            $("#alterInfo").html("您只添加了 "+uploadImgCount+" 张照片, 未满 "+warmCount+" 张, 是否返回继续添加照片呢?");
            $("#detailDlg").css("display","block");
            return false;
        }

        for(var i= 0,len=imgs.length;i<len;++i) {
            var img = imgs.eq(i);
            if(img.data("upload") == 1){
                orderInfo["pics"][i] = {
                    "type": img.data("type"),
                    "url": img.data("id")
                }
            } else {
                orderInfo["pics"][i] = {
                    "type": img.data("type"),
                    "url": img.attr("src")
                };
            }
        }

        if(uploadCfg["cid"] == null) {
            delete orderInfo["cid"];
            orderInfo['sys'] = "others";
            if(/Android/i.test(navigator.userAgent)) {
                orderInfo['sys'] = "android";
            } else if(/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                orderInfo['sys'] = "ios";
            }
            orderInfo["status"] = 0;

            if(isCreating) {
                return;
            }
            isCreating = true;
            $.ajax({
                url: "/index/index/saveOrderEx",
                type: 'POST',
                dataType: 'json',
                data: orderInfo,
                success: function (data) {
                    if(data.status == 'ok') {
                        data = data.data;
                        orderInfo["cid"] = uploadCfg["cid"] = data['cid'];
                        if(parseInt(data["aid"])) {
                            uploadCfg["aid"] = parseInt(data["aid"]);
                        }
                        upLoadImgs(imgs, showAddressView);
                    } else {
                        showWarmDlg(data.reason);
                    }
                    isCreating = false;
                },
                error: function() {
                    showWarmDlg("保存订单失败");
                    isCreating = false;
                }
            });
        } else {
            upLoadImgs(imgs, showAddressView);
        }
    }

    $('#closeUpdateWarmBtn').on('click', function(){
        $("#warnDes").show();
        $('#updateWarmDlg').hide();
    });

    $('#sureUpdateWarmBtn').on('click', function(){
        $(".warm-img").parent().remove();
        $('#updateWarmDlg').hide();
        refreshPicInfo();

        setTimeout(function(){
            var imgs = $("#picsGroup .upload-img");
            // 图片效验
            if(imgs.length < minUploadPicsCount) {
                showWarmDlg(" 请继续上传");
                return false;
            }
            orderInfo["pics"] = {};
            upLoadImgs(imgs, showAddressView);
        }, 500)
    });

    $("#submitBtnEx").on('click',function(){addressView(true)});

    $('#closeAddressView').on('click',function(){ $("#addressView").css('top','100%'); });

    // 弹出框的继续添加按钮
    $("#cancelBtn").on("click",function(){
        $("#detailDlg").css("display","none");
    });

    // 弹出框的确认提交按钮
    $("#continueBtn").on("click",function(){
        $("#detailDlg").css("display","none");
        addressView(false);
    });

    // 提交订单
    $("#submitBtn").on('click',function(){
        saveOrderData(true);
    });

    // 地址选择
    // 重设城市列表
    function restCityList() {
        var provId=provinceSelect.get(0).selectedIndex - 1;
        citySelect.empty();
        areaSelect.empty();

        if(provId<0 || typeof(cityMap[provId].c)=="undefined"){
            citySelect.css("display","none");
            areaSelect.css("display","none");
            return;
        }

        // 遍历赋值市级下拉列表
        tempHtml="<option value='"+defaultSelect["city"]+"'>"+defaultSelect["city"]+"</option>";
        $.each(cityMap[provId].c,function(i,city){
            tempHtml+="<option value='"+city.n+"'>"+city.n+"</option>";
        });

        citySelect.html(tempHtml).css("display","");
        restAreaList();
    }

    // 重设区域列表
    function restAreaList() {
        var provId=provinceSelect.get(0).selectedIndex - 1;
        var cityId=citySelect.get(0).selectedIndex - 1;

        areaSelect.empty();

        if(provId<0||cityId<0||typeof(cityMap[provId].c[cityId].a)=="undefined"){
            areaSelect.css("display","none");
            return;
        }

        // 遍历赋值市级下拉列表
        tempHtml="<option value='"+defaultSelect["area"]+"'>"+defaultSelect["area"]+"</option>";
        $.each(cityMap[provId].c[cityId].a,function(i,dist){
            tempHtml+="<option value='"+dist.s+"'>"+dist.s+"</option>";
        });
        areaSelect.html(tempHtml).css("display","");
    }

    // 选择省份时发生事件
    function updateTipStatus(province) {
        var tip = $("#exceptAreaTip");
        tip.hide();
    }

    provinceSelect.bind("change",function(){
        var province = $(this).val();
        if($(this).val() != defaultSelect["province"]) {
            $(this).parent().removeClass("has-error")
        }
        updateTipStatus(province);
        restCityList();
    });

    function noSelect(defaultVal){
        return function() {
            if($(this).val() == defaultVal) {
                $(this).parent().addClass("has-error")
            }
        }
    }
    provinceSelect.bind("blur",noSelect(defaultSelect["province"]));
    citySelect.bind("blur",noSelect(defaultSelect["city"]));
    areaSelect.bind("blur",noSelect(defaultSelect["area"]));

    // 选择市级时发生事件
    citySelect.bind("change",function(){
        if($(this).val() != defaultSelect["city"]) {
            $(this).parent().removeClass("has-error")
        }
        restAreaList();
    });

    // 选择区
    areaSelect.bind("change",function(){
        if($(this).val() != defaultSelect["area"]) {
            $(this).parent().removeClass("has-error")
        }
    });

    // 初始化列表
    var tempHtml="<option value='"+defaultSelect["province"]+"'>"+defaultSelect["province"]+"</option>";
    $.each(cityMap,function(i,prov){
        tempHtml+="<option value='"+prov.p+"'>"+prov.p+"</option>";
    });
    provinceSelect.html(tempHtml);
    if(uploadCfg.province) {
        provinceSelect.val(uploadCfg.province);
        restCityList();
        if(uploadCfg.city) {
            citySelect.val(uploadCfg.city);
            restAreaList();
            if(uploadCfg.area) {
                areaSelect.val(uploadCfg.area);
            }
        }
    } else {
        restCityList();
    }
    updateTipStatus(provinceSelect.val());
})();