/* 设置要保存的数据  */
;(function(){
    upyun.set('bucket','molikuaiyin');
    upyun.set('form_api_secret', 'J8t3ar7et40tzqRFDmk2iX0aF/Q=');

    bad_image_src = [];
    bad_image_obj = [];
    error_image_src = [];
    error_image_obj = [];
    success_image_src = [];

    var defaultSelect = {
        "province" : "请选择省",
        "city" : "请选择市",
        "area" : "请选择区"
    };

    var maxSelectPicCount = 500;
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
        div.prepend(img);
        return div;
    }

    // 图片添加
    $('#uploadBtn').on('click', function () {
        var uploadCount = $(".upload-img").length;
        if(uploadCount < maxSelectPicCount) {
            $('#picsGroup input').eq(0).trigger('click');
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

    // 关闭对话框
    $('.close-btn').on("click",function(){
        $(this).parent().parent().parent().parent().css("display","none");
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
            if(province == defaultSelect["province"]) {
                provinceSelect.parent().addClass("has-error")
            }
            orderInfo["province"] = province;
        }
        var city = citySelect.val();
        if(city) {
            if(city == defaultSelect["city"]) {
                citySelect.parent().addClass("has-error")
            }
            orderInfo["city"] = city;
        }
        var area = areaSelect.val();
        if(area) {
            if(area == defaultSelect["area"]) {
                areaSelect.parent().addClass("has-error")
            }
            orderInfo["area"] = area;
        }

        if(check && $(".has-error").length > 0) {
            alert("请您把订单信息填写完整.");
            return false;
        }
        return true;
    }

    // 上传图片
    function upLoadImgs(callback,check) {
        // 检查图片信息
        var imgs = $("#picsGroup .upload-img");
        if(check && (imgs.length == 0)) {
            alert("您还未选择任何照片，请先添加照片。");
            return false;
        }
        if(imgs.length == 0) {
            callback(0);
            return true;
        }

        var uploadIdx = 0;

        function uploadOnceEnd() {
            if(++uploadIdx < imgs.length) {
                uploadImg();
            } else {
                $("#cover").hide();
                fileArray = [];
                callback(imgs.length);
            }
        }

        function uploadImg() {
            var img = imgs.eq(uploadIdx);
            $("#coverPicNum").html(uploadIdx+1);

            if(img.data("upload") == 1){
                orderInfo["pics"][uploadIdx] = {
                    "type": img.data("type"),
                    "url" : img.data("type") == uploadCfg["wxType"] ? img.data("id") : img.attr("src")
                };
                uploadOnceEnd();
            } else if (img.data("type") == uploadCfg['uploadType']) {

                function progressCallbackFunc (progress) {
                    console.log(progress);
                    $("#coverProNum").html("("+progress+"%)");
                }

                function successCallback(err, response, image) {

                    var deal_image_src = imgs.eq(uploadIdx).attr('src');

                    if (!err && image.code === 200 && image.message === 'ok') {

                        imgs.eq(uploadIdx).attr('src', image.absUrl);
                        imgs.eq(uploadIdx).data("upload",1);

                        success_image_src.push(image.absUrl);

                        orderInfo["pics"][uploadIdx] = {
                            "type": uploadCfg['uploadType'],
                            "url" : image.absUrl
                        };
                        uploadOnceEnd();
                    }else{

                        // 判断是坏图还是上传失败

                        var name = fileArray[imgs.eq(uploadIdx).data("id")].name;

                        if (err || image.code === 500) {
                            // 失败
                            error_image_src.push(deal_image_src);
                            error_image_obj[deal_image_src] = name;
                        }else{
                            // 坏图
                            bad_image_src.push(deal_image_src);
                            if(window['old_order'] === false){
                                orderInfo["pics"][uploadIdx] = {
                                    "type": 'badImg',
                                    "url" : 'image.absUrl'
                                };
                            }
                            bad_image_obj[deal_image_src] = name;
                        }
                        imgs.eq(uploadIdx).parent().parent().remove(); // remove 不影响 imgs 集合
                        uploadOnceEnd();
                        // callback(-1);
                    }
                }

                var imgName = 'tempp10'+(new Date()).valueOf()+"_"+Math.floor(Math.random()*10000)+'.jpeg';
                var file = fileArray[img.data("id")];

                if(typeof directUpload != 'undefined' && directUpload === true){

                    var post_host = 'http://gongchang.molixiangce.com';
                    // var post_host = 'http://molipdf.com';

                    var post_url = post_host + '/Bin/upload.php';

                    var xhr = new XMLHttpRequest();

	                if (xhr.upload) {

		                // start upload
		                xhr.open("POST", post_url, true);
		                xhr.setRequestHeader("X_FILENAME", imgName);
                        xhr.setRequestHeader("CUSTOM_ORDERNO", uploadCfg["orderno"]);
                        xhr.setRequestHeader("CUSTOM_SHOW_URL", post_host);

                        // Error event
                        xhr.addEventListener('error', function(err) {
                          return successCallback(err);
                        }, false);

                        // when server response
                        xhr.addEventListener('load', function(result) {
                          var statusCode = result.target.status;

                          if (statusCode !== 200)
                            return successCallback(new Error(result.target.status), result.target);
                          try {
                            // trying to parse JSON
                            var image = JSON.parse(this.responseText);
                            image.absUri = image.absUrl;
                            return successCallback(null, result.target, image);
                          } catch (err) {
                            return successCallback(err);
                          }
                        }, false);

                        // the upload progress monitor
                        xhr.upload.addEventListener('progress', function(pe) {
                          if (!pe.lengthComputable || typeof progressCallbackFunc!== 'function')
                            return;
                          progressCallbackFunc(Math.round(pe.loaded / pe.total * 100));
                        });

                        xhr.send(file);

	                }else{
                        alert('无法上传');
                        return;
                    }

                }else{

                    upyun.upload(
                        uploadCfg["cid"],
                        file,
                        imgName,
                        successCallback,
                        progressCallbackFunc
                    );
                }

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

        var old_order = $('#old_order').val();
        window['old_order'] = false;
        if(old_order != ''){
            window['old_order'] = old_order;
        }

        if(getFormData(check) || window['old_order'] !== false) {
            orderInfo["pics"] = {};
            orderInfo["cid"] = uploadCfg["cid"];
            orderInfo["uid"] = userId;  // to do

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
                orderInfo["pwd"] = $("#surePwd").val();

                if(window['old_order'] !== false){
                    // 补充订单
                    orderInfo["orderno"] = uploadCfg["orderno"] = window['old_order'];

                    $.ajax({
                        url: "/index/admin/getCardSingle",
                        type: 'POST',
                        dataType: 'json',
                        data: {'orderno': orderInfo["orderno"]},
                        success: function (data) {
                            if( parseInt(data['cardData']['is_sync']) == 5 ){
                                upLoadImgs(postSaveOrder,check);
                            }else{
                                alert('该订单不允许进行补充图片操作');
                            }
                        },
                        error: function() {
                            alert("处理失败");
                        }
                    });
                }else{
                    $.ajax({
                        url: "/index/admin/saveOrder",
                        type: 'POST',
                        dataType: 'json',
                        data: orderInfo,
                        success: function (data) {
                            if(data["cid"] == null) {
                                alert(data['error']);
                                return;
                            }

                            orderInfo["orderno"] = uploadCfg["orderno"] = data['orderno'];

                            orderInfo["cid"] = uploadCfg["cid"] = data['cid'];
                            if(parseInt(data["aid"])) {
                                uploadCfg["aid"] = parseInt(data["aid"]);
                            }
                            delete orderInfo["pwd"];
                            upLoadImgs(postSaveOrder,check);
                        },
                        error: function() {
                            alert("保存订单失败");
                        }
                    });
                }

            } else {
                upLoadImgs(postSaveOrder,check);
            }
        }
    }

    // 提交订单保存请求
    function postSaveOrder(success_number){

        function generateErrorTips(){

            var badimg_html = '';
            bad_image_src.forEach(function(src) {
                badimg_html += '<img class="deal-img" src="'+src+'">';
                badimg_html += '<div>文件名: '+bad_image_obj[src]+'</div>';
            });
            $('#badImgGroupBox').prepend(badimg_html);
            $('#badImgView').css('display','block');

            var errimg_html = '';
            error_image_src.forEach(function(src) {
                errimg_html += '<img class="deal-img" src="'+src+'">';
                errimg_html += '<div>文件名: '+error_image_obj[src]+'</div>';
            });
            $('#errorImgGroupBox').prepend(errimg_html);
            $('#errorImgView').css('display','block');

        }

        function successCallBack(data){

            generateErrorTips();

            var alert_info = '';
            if(bad_image_src.length > 0){
                alert_info += '上传的图片中存在损坏的图片 ';
            }
            if(error_image_src.length > 0){
                alert_info += '上传的过程中存在上传失败的图片 ';
            }
            if(alert_info == ''){
                alert_info = '保存订单成功';
                alert(alert_info);
                window.location.href = "/Index/Admin/admin";
                orderInfo = {};
                return true;
            }else{
                alert_info += '具体图片信息在页面底部显示 ';
            }

            alert(alert_info);
        }

        if(success_image_src.length > 0){
            if(window['old_order'] !== false){
                // 补图完成后更新photo_number
                $.ajax({
                    url: "/index/admin/updatePhotoNumber",
                    type: 'POST',
                    dataType: 'json',
                    data: {'orderno': window['old_order'],'num': success_image_src.length + bad_image_src.length},
                    success: successCallBack,
                    error: function () {
                        alert("处理失败");
                    }
                });
                return true;
            }else{

                orderInfo["status"] = 10;

                // 区分有无坏图
                if(bad_image_src.length > 0 || error_image_src.length > 0){
                    orderInfo["is_sync"] = 5;
                }

                $.ajax({
                    url: "/index/admin/saveOrder",
                    type: 'POST',
                    dataType: 'json',
                    data: orderInfo,
                    success: successCallBack,
                    error: function() {
                        alert("保存订单失败");
                    }
                });
            }
        }

        /*
        if(success_number < 0 && window['old_order'] !== false){
            // 上传失败，删除之前上传的图
            var post_host = 'http://gongchang.molixiangce.com';
            var post_url = post_host + '/Bin/upload.php';

            var xhr = new XMLHttpRequest();

            xhr.open("POST", post_url, true);
            xhr.setRequestHeader("X_FILENAME", 'deleteTmpImgs');
            xhr.setRequestHeader("CUSTOM_ORDERNO", window['old_order']);
            xhr.setRequestHeader("CUSTOM_SHOW_URL", post_host);
            xhr.send('deleteTmpImgs');
        }
        */
    }

    // 提交订单
    $("#submitBtn").on('click',function(){
        //saveOrderData(true);
        if(success_image_src.length || bad_image_src.length || error_image_src.length){
            window.location.reload();
            return true;
        }else{
            $("#submitDlg").css("display","block");
        }
    });

    // 确认提交订单
    $("#sureBtn").on('click',function(){
        $("#submitDlg").css("display","none");
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

    provinceSelect.bind("change",function(){
        var province = $(this).val();
        if($(this).val() != defaultSelect["province"]) {
            $(this).parent().removeClass("has-error")
        }
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
})();