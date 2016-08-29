/* 设置要保存的数据  */
;(function(){
    upyun.set('bucket','molikuaiyin');
    upyun.set('form_api_secret', 'J8t3ar7et40tzqRFDmk2iX0aF/Q=');

    var defaultSelect = {
        "province" : "请选择省",
        "city" : "请选择市",
        "area" : "请选择区"
    }

    var maxSelectPicCount = 500;
    var updateFrequent = 1;
    var fileArray = [];
    var orderInfo = {}
    var orderNecessaryInfo = [
        "#name",
        "#phone",
        "#street"
    ];

    // 地址选择容器
    var provinceSelect = $("#address .prov"),
        citySelect = $("#address .city"),
        areaSelect = $("#address .area");

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
        var img = $('<img class="upload-img" src="'+ url +'" data-type="'+type+'" data-id="'+id+'" data-upload="'+upload+'"/>');
        // 图片加载后回调
        img.on("load",function(){
            var imgItem = $(this)[0];
            //alert(imgItem.naturalWidth+","+imgItem.naturalHeight);
            if(imgItem.naturalWidth < 960 && imgItem.naturalHeight < 960) {
                //alert("图片像素过低");
                imgItem.onload = undefined;
            }
        });
        img.on("error",function(){
            var imgItem = $(this);
            if(imgItem.data("type") == uploadCfg["wxLocalType"]) {
                imgItem.parent().remove();
                refreshPicInfo();
            }
        });
        div.prepend(img);
        return div;
    }

    // 图片添加
    $('#uploadBtn').on('click', function () {
        var uploadCount = $(".upload-img").length;
        if(uploadCount < maxSelectPicCount) {
            //if(/Android/i.test(navigator.userAgent)) {
            //    // android 系统
                if(wx != undefined) {
                    wx.chooseImage({
                        count: Math.min(maxSelectPicCount - uploadCount,9), // 默认 9
                        sizeType: ['original'],
                        sourceType: ['album'],
                        success: function (res) {
                            var localIds = res.localIds;
                            var picsGroup = $('#picsGroup');
                            for(var idx=0;idx<localIds.length;++idx) {
                                var pItem = newPicItem(localIds[idx],uploadCfg["wxLocalType"],0,0);
                                picsGroup.append(pItem);
                            }
                            refreshPicInfo();
                        }
                    });
                }
            //} else {
            //    // 其他系统 ios 平台判断 ## /iPhone|iPad|iPod/i.test(navigator.userAgent);
            //    $('#picsGroup input').eq(0).trigger('click');
            //}
        } else {
            alert("您已达到最大上传图片限制("+maxSelectPicCount+"张)了!");
        }
    });

    // 本地上传图片
    $('#picsGroup input').on('change',function(e){
        var file = $(this)[0];
        var picsGroup = $('#picsGroup');
        var uploadCount = $(".upload-img").length;

        for(var idx= 0,len=Math.min(file.files.length,maxSelectPicCount - uploadCount);idx<len;++idx) {
            window.URL = window.URL || window.webkitURL;
            fileArray.push(file.files[idx]);
            picsGroup.append(newPicItem(window.URL.createObjectURL(file.files[idx]),uploadCfg['uploadType'],fileArray.length - 1,0));
        }

        $(file).parent()[0].reset();
        refreshPicInfo();
    });

    // 删除图片
    $(document).on("click",".del-img",function(){
        $(this).parent().remove();
        refreshPicInfo();
    });
    
    // 更新提醒张数和价格
    function refreshPicInfo(){
    	var imgsNum = $("#picsGroup .upload-img").length;
    	if(imgsNum == 0){
    		$("#picInfo").hide();
            $("#uploadBtn").html("添加照片");
            $("#uploadBtn").removeClass("btn-primary");
            $("#uploadBtn").addClass("btn-success");
    	}else{
    		$("#picInfo").show();
    		$("#picInfoNum").html(imgsNum);
    		$("#picInfoPrice").html(countOrderPrice(imgsNum));
            $("#uploadBtn").html("继续添加照片");
            $("#uploadBtn").addClass("btn-primary");
            $("#uploadBtn").removeClass("btn-success");
    	}
    }
    
    //根据单数计算套餐价格
    function countOrderPrice(imgsNum){
    	var price = (9.9 + parseInt((imgsNum-1)/20)*8.9).toFixed(1);
    	return price;
    }
    
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
        if(orderInfo["phone"].search(/[^0-9]/) != -1) {
            alert("输入的电话号码只能包含数字");
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
            alert("请选择收货地址的省份");
            return false;
        }
        if(city == defaultSelect["city"] || city == "") {
            alert("请选择收货地址的城市");
            return false;
        }
        if(area == defaultSelect["area"] || area == "") {
            alert("请选择收货地址的地区");
            return false;
        }

        if(check && $(".has-error").length > 0) {
            alert("请您把订单信息填写完整.");
            return false;
        }

        var uploadImgCount = $(".upload-img").length;

        // 图片效验
        if(uploadImgCount < minUploadPicsCount) {
            alert("上传图片张数过少, 请继续上传照片");
            return false;
        }

        if(check && uploadImgCount < 10) {
            $("#alterInfo").html("您只添加了 "+uploadImgCount+" 张照片, 未满20张, 是否返回继续添加照片呢?");
            $("#detailDlg").css("display","block");
            return false;
        }

        return true;
    }

    // 弹出框的继续添加按钮
    $("#cancelBtn").on("click",function(){
        $("#detailDlg").css("display","none");
    });

    // 弹出框的确认提交按钮
    $("#continueBtn").on("click",function(){
        $("#detailDlg").css("display","none");
        saveOrderData(false);
    });

    // 上传图片
    function upLoadImgs(callback,check) {
        // 检查图片信息
        var imgs = $("#picsGroup .upload-img");
        if(check && (imgs.length == 0)) {
            alert("您还未选择任何照片，请先添加照片。");
            return false;
        }
        if(imgs.length == 0) {
            callback();
            return true;
        }

        var uploadIdx = 0;

        function uploadOnceEnd() {
            if(++uploadIdx < imgs.length) {
	    	// 定时保存订单
                if(uploadIdx % updateFrequent == 0 && uploadIdx > 0) {
                    orderInfo["status"] = 0;
                    $.ajax({
                        url: "/index/index/saveOrder",
                        type: 'POST',
                        dataType: 'json',
                        data: orderInfo,
                        success: function (data) {
                            if(data.status == 'ok') {
                                data = data.data;
                                uploadImg();
                            } else {
                                alert(data.reason);
                                $("#cover").hide();
                            }
                        },
                        error: function() {
                            alert("保存订单失败");
                        }
                    });
                } else {
                    uploadImg();
                }
            } else {
                $("#cover").hide();
                fileArray = [];
                callback();
            }
        }

        function uploadImg() {
            var img = imgs.eq(uploadIdx);
            $("#coverPicNum").html(uploadIdx+1);
            $("#coverProNum").html("");

            if(img.data("upload") == 1){
                orderInfo["pics"][uploadIdx] = {
                    "type": img.data("type"),
                    "url" : img.data("type") == uploadCfg["wxType"] ? img.data("id") : img.attr("src")
                };
                uploadOnceEnd();
            } else if(img.data("type") == uploadCfg["wxLocalType"] && wx != undefined) {
                wx.uploadImage({
                    localId: img.attr("src"),
                    isShowProgressTips: 1,
                    success: function (res) {
                        var serverId = res.serverId;
                        orderInfo["pics"][uploadIdx] = {
                            "type": uploadCfg["wxType"],
                            "url" : serverId
                        };
                        imgs.eq(uploadIdx).data("upload",1);
                        imgs.eq(uploadIdx).data("id",serverId);
                        uploadOnceEnd();
                    }
                });
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
                            imgs.eq(uploadIdx).data("upload",1);

                            orderInfo["pics"][uploadIdx] = {
                                "type": uploadCfg['uploadType'],
                                "url" : image.absUrl
                            };
                            uploadOnceEnd();
                        } else {
                            imgs.eq(uploadIdx).parent().parent().remove();
                            alert("上传照片失败");
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

    // 保存表单
    function saveOrderData(check) {
        if(uploadCfg["aid"] != null) {
            orderInfo["aid"] = uploadCfg["aid"];
        }

        if(getFormData(check)) {
            orderInfo["pics"] = {};
            orderInfo["cid"] = uploadCfg["cid"];

            // 获取本地图片信息
            var imgs = $("#picsGroup .upload-img");
            for(var i= 0,len=imgs.length;i<len;++i) {
                var img = imgs.eq(i);
                if(img.data("upload") == 1){
                    orderInfo["pics"][i] = {
                        "type": img.data("type"),
                        "url": img.data("type") == uploadCfg["wxType"] ? img.data("id") : img.attr("src")
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

                $.ajax({
                    url: "/index/index/saveOrder",
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
                            upLoadImgs(postSaveOrder,check);
                        } else {
                            alert(data.reason);
                        }
                    },
                    error: function() {
                        alert("保存订单失败");
                    }
                });
            } else {
                upLoadImgs(postSaveOrder,check);
            }
        }
    }

    // 提交订单保存请求
    function postSaveOrder(){
        orderInfo["status"] = 1;

        $.ajax({
            url: "/index/index/saveOrder",
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
                    if(data["playUrl"]) {
                        document.location.href = data["playUrl"];
                    } else {
                        alert("保存订单成功");
                    }
                } else {
                    alert(data.reason);
                    $("#cover").hide();
                }
                orderInfo = {};
            },
            error: function() {
                alert("保存订单失败");
            }
        });
    }

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
        };

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
            areaSelect.css("display","none")
            return;
        };

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
        for(var i=0;i<excepArea.length;++i) {
            if(excepArea[i] == province) {
                tip.show();
                break;
            }
        }
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
    tempHtml="<option value='"+defaultSelect["province"]+"'>"+defaultSelect["province"]+"</option>";
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

    // 添加图片节点
    (function() {
        var picGroup = $("#picsGroup");
        for(var i= 0,len=imgData.length;i<len;++i) {
            picGroup.append(newPicItem(imgData[i].url,imgData[i].type,imgData[i].id,imgData[i].upload));
        }
        refreshPicInfo();
    })();
})();