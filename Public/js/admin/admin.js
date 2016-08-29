$(function(){
    // 获取图片信息
    var isOpeningDlg = false;

    // 地址选择容器
    var provinceSelect = $("#address .prov"),
        citySelect = $("#address .city"),
        areaSelect = $("#address .area");

    function openDlg(){
        if(isOpeningDlg) {
            return;
        }
        isOpeningDlg = true;

        var urlList = $("#urlList");
        $(".modal-title").html("订单 "+$(this).parent().data("order")+" 详情");
        urlList.data("id",$(this).parent().data("id"));
        urlList.data("order",$(this).parent().data("order"));
        urlList.empty();

        $.ajax({
            url : "/index/admin/getAccessToken",
            success: (function(urlsData,urlList) {
                return function(data) {
                    $("#detailDlg").css("display","block");
                    for(var i=0;i<urlsData.length;++i) {
                        var item = urlsData.eq(i);
                        if(item.data("type") == "wx") {
                            item.data("url","http://file.api.weixin.qq.com/cgi-bin/media/get?access_token="+data['accessToken']+"&media_id="+item.data("id"));
                        }
                        urlList.append("<div>"
                            +"<b>图片"+(i+1)+"(上传来源:"+item.data("type")+")</b><br>"
                            +"<pre class='download-url' data-type='"+item.data("type")+"'>"+item.data("url")+"</pre>"
                        +"</div>");
                    }
                    isOpeningDlg = false;
                }
            })($(this).parent().find("li"),urlList)
        });
    }

    function openDetailDlg() {
        if(isOpeningDlg) {
            return;
        }
        isOpeningDlg = true;

        $.ajax({
            url : "/index/admin/getOrderPayData?cid="+$(this).parent().data("id")+"&uid="+$(this).data("uid"),
            success: function (data) {
                console.log(data);
                if (data['status'] == 'ok') {
                    $("#payPicCount").html(data['data']['payPicCount']);
                    $("#payOriginalPrice").html(data['data']['payOriginalPrice']);
                    $("#payPrice").html(data['data']['payPrice']);
                    $("#couponDes").html(data['data']['couponDes']);
                    $("#payDataDlg").css("display","block");
                } else {
                    alert('获取信息失败:  ' + data['reason']);
                }
                isOpeningDlg = false;
            }
        });
    }

    function openPic(orderid){
        if(typeof orderid == 'undefined'){
            var order_id = $(this).parent().data("order");
        }else{
            var order_id = orderid;
        }

        var that = this;

        var url = 'http://gongchang.molixiangce.com/Bin/admin/list.php?bypass=true&order='+order_id;

        var link = $('#_externalLink');
        if(!link.length){
            var link = $('<a>');
            link.html('external');
            link.attr('id', '_externalLink');
            link.attr('target', '_blank');
            link.css('display','none');
            link.appendTo('body');
        }

        link.attr('href', url);

        link.trigger( "click" );
    }

    function resetSync(){
        var sync = $(this).attr('data-sync');
        var cid = $(this).parent().data("id");
        var that = this;

        if(sync == '5') {
            openPic($(this).parent().data("order"));
            return;
        }

        $.ajax({
            url: "/index/admin/resetSync",
            type: 'POST',
            dataType: 'json',
            data: {
                cid: cid,
                sync: 0,
            },
            success: function (data) {
                if (data['status'] == 'ok') {
                    alert('设置成功,等待下一轮同步');
                    $(that).attr('disabled', "");
                } else {
                    alert('设置失败 ' + data['reason']);
                }
            }
        });
    }

    function openAddressDlg() {
        var btn = $(this)
        $("#addressDlg").css("display","block");
        $("#address").data('id',btn.data('id'));
        $("#address").data('tid',btn.data('tid'));

        var spans = btn.parent().parent().find("td").eq(2).find('span');
        var name = spans.eq(0).html();
        var phone = spans.eq(1).html();
        var contactAddress = spans.eq(2).html();
        var idx = contactAddress.indexOf(" ");
        var street = contactAddress.substr(idx+1);
        var position = contactAddress.substr(0,idx).split("-");

        provinceSelect.val(position[0]);
        $("#name").val(name);
        $("#phone").val(phone);
        $("#street").val(street);
        restCityList();
        citySelect.val(position[1]);
        if(position.length > 2) {
            restAreaList();
            areaSelect.val(position[2]);
        }
    }

    function delOrder(){
        var btn = $(this);
        var cid = btn.data('id');
        var that = this;

        var sync = 127;

        $.ajax({
            url : "/index/admin/resetSync",
            type: 'POST',
            dataType: 'json',
            data: {
                cid:cid,
                sync:sync,
            },
            success: function(data){
                if(data['status'] == 'ok'){
                    alert('删除成功');
                }else{
                    alert('删除失败 '+data['reason']);
                }
            }
        });        
    }

    function byPassAudit(){
        var btn = $(this);
        var cid = btn.data('id');
        var op = btn.data('op');
        var that = this;

        var sync = 1;
        if(op == 'bad_image'){
            sync = 10;
        }

        $.ajax({
            url : "/index/admin/resetSync",
            type: 'POST',
            dataType: 'json',
            data: {
                cid:cid,
                sync:sync,
            },
            success: function(data){
                if(data['status'] == 'ok'){
                    alert('设置成功');
                }else{
                    alert('设置失败 '+data['reason']);
                }
            }
        });
    }

    function openResetDlg(type, that) {

        if(typeof type != 'object'){
            $("#resetPwd").data('type',type);
        }else{
            $("#resetPwd").data('type','default');
        }

        if(typeof that == 'undefined'){
            var that = this;
        }

        $("#resetDlg").css("display","block");
        $("#resetPwd").data('id',$(that).data('id'));
        $("#resetPwd").data('tid',$(that).data('tid'));

        $("#street").val('');
    }

    function cancelBtn(){
        openResetDlg('cancel', this);
    }

    // 重置地址
    $("#changeAddressBtn").on("click",function(){
        if(($("#street").val()+"".trim()) == "" || ($("#name").val()+"".trim()) == "" || ($("#phone").val()+"".trim()) == "") {
            alert("联系方式不完整");
            return false;
        }

        var postData = {
            'id' : $("#address").data('id'),
            'tid' : $("#address").data('tid'),
            'province' : provinceSelect.val(),
            'city' : citySelect.val(),
            'area' : area = areaSelect.val(),
            'street' : $("#street").val(),
            'name' : $("#name").val(),
            'phone' : $("#phone").val()
        };

        $.ajax({
            url: "/Index/Admin/changeAddress",
            type: 'POST',
            data: postData,
            success : function(json) {
                console.log(json);
                if(json.status == "error") {
                    alert(json.reason);
                } else {
                    var span = $("#"+postData['tid']).find("td").eq(2).find("span");
                    span.eq(2).html(postData['province']+"-"+postData['city']+(postData['area']?"-"+postData['area']:"")+" "+postData['street']);
                    span.eq(1).html(postData['phone']);
                    span.eq(0).html(postData['name']);
                }
                $("#addressDlg").css("display","none");
            },
            error: function (){
                alert("服务器错误!");
                $("#addressDlg").css("display","none");
            }
        });
    });

    // 重置订单
    $("#sureResetBtn").on("click",function(){
        var pwdInput = $("#resetPwd");
        var postData = {
            'id' : pwdInput.data('id'),
            'tid' : pwdInput.data('tid'),
            'type': pwdInput.data('type'),
            'pwd' : pwdInput.val()
        };

        $.ajax({
            url: "/Index/Admin/resetOrder",
            type: 'POST',
            data: postData,
            success : function(json) {
                console.log(json);
                if(json.status == "error") {
                    alert(json.reason);
                } else {
                    var td = $("#"+postData['tid']).find("td");
                    td.eq(8).html("");
                    td.eq(9).html("0");
                    alert("重置成功");
                }
                $("#resetDlg").css("display","none");
            },
            error: function (){
                alert("服务器错误!");
                $("#resetDlg").css("display","none");
            }
        });
    });

    $('.detail-btn').on("click",openDlg);
    $('.address-btn').on("click",openAddressDlg);
    $('.reset-btn').on("click",openResetDlg);
    $('.pay-info-btn').on("click",openDetailDlg);

    // 关闭对话框
    $('.close-btn').on("click",function(){
        $(this).parent().parent().parent().parent().css("display","none");
    });

    // 设置下载状态
    $('#setDownloadBtn').on("click",function(){
        var  cid = $("#urlList").data("id"),
            order = $("#urlList").data("order");

        $.ajax({
            url : "/index/admin/setOpStatus",
            type: 'POST',
            dataType: 'json',
            data: {
                cid:cid,
                order:order
            },
            success: function(data) {
                console.log(data);
                $("#"+data["order"]+"").find(".op-status").html(data["desc"]);
                alert("设置下载状态成功");
            },
            error: function() {
                alert("保存状态失败");
            }
        });
    });

    // 批量下载
    $('#downloadPicBtn').on("click",function(){
        var urlList = $("#urlList .download-url"),
            downloadContainer = $('#downloadContainer');
        var  cid = $("#urlList").data("id"),
            order = $("#urlList").data("order");

        function decodeEscapeHtml(text) {
            if (text.length == 0){ return "" };

            var map = {
                '&amp;': '&',
                '&lt;': '<',
                '&gt;': '>',
                '&quot;': '"',
                '&#039;': "'",
                '&nbsp;': ' '
            };

            return text.replace(/(&nbsp;|&amp;|&lt;|&gt;|&quot;|&#039;)/g, function(m) { return map[m]; });
        }

        for(var i=0;i<urlList.length;++i) {
            var item = urlList.eq(i);
            var url = decodeEscapeHtml(item.html());

            if(item.data("type") == "wx") {
                window.open(url);
            } else {
                downloadContainer.attr("href",url);
                downloadContainer.attr("download",url.substring(url.lastIndexOf('/') + 1));
                downloadContainer.trigger("click");
            }
        }
    });

    // 查询
    var searchCond = $("#searchCondition");
    var searchValue = $("#searchValue");
    var pageCount = 0;
    var optionList = {
        "condition" : {
            "callback":function () {
                updateSearch( $(this).val());
            },
            "ref":null,
            "options" : {
                "全部": {
                    check : function () {
                        return true;
                    },
                    url : function(page) {
                        var download = optionList['download']['options'][optionList['download']['ref'].val()];
                        var pay = optionList['pay']['options'][optionList['pay']['ref'].val()];
                        return "/Index/Admin/searchOrder/orderId/0"+"/page/"+page+"/download/"+download+"/pay/"+pay;
                    }
                },
                "微信昵称":{
                    check : function () {
                        var val = (searchValue.val()+"").trim();
                        if(val == "") {
                            alert("查询联系人为空");
                            return false;
                        }
                        return true;
                    },
                    url :function(page) {
                        var download = optionList['download']['options'][optionList['download']['ref'].val()];
                        var pay = optionList['pay']['options'][optionList['pay']['ref'].val()];
                        return "/Index/Admin/searchWx/name/"+searchValue.val()+"/page/"+page+"/download/"+download+"/pay/"+pay;
                    }
                },
                "联系人":{
                    check : function () {
                        var val = (searchValue.val()+"").trim();
                        if(val == "") {
                            alert("查询联系人为空");
                            return false;
                        }
                        return true;
                    },
                    url :function(page) {
                        var download = optionList['download']['options'][optionList['download']['ref'].val()];
                        var pay = optionList['pay']['options'][optionList['pay']['ref'].val()];
                        return "/Index/Admin/searchContact/name/"+searchValue.val()+"/page/"+page+"/download/"+download+"/pay/"+pay;
                    }
                },
                "订单号": {
                    check : function () {
                        var val = (searchValue.val()+"").trim();
                        if(val == "") {
                            alert("查询订单号为空");
                            return false;
                        }
                        return true;
                    },
                    url : function(page) {
                        return "/Index/Admin/searchOrder/orderId/"+searchValue.val()+"/page/"+page+"/download/all/pay/all";
                    }
                },
                "快递号": {
                    check : function () {
                        var val = (searchValue.val()+"").trim();
                        if(val == "") {
                            alert("查询快递号为空");
                            return false;
                        }
                        return true;
                    },
                    url : function(page) {
                        var download = optionList['download']['options'][optionList['download']['ref'].val()];
                        var pay = optionList['pay']['options'][optionList['pay']['ref'].val()];
                        return "/Index/Admin/searchExpress/mailno/"+searchValue.val()+"/page/"+page+"/download/"+download+"/pay/"+pay;
                    }
                },
                "电话号码": {
                    check : function () {
                        var val = (searchValue.val()+"").trim();
                        if(val == "") {
                            alert("查询电话号码为空");
                            return false;
                        }
                        return true;
                    },
                    url : function(page) {
                        var download = optionList['download']['options'][optionList['download']['ref'].val()];
                        var pay = optionList['pay']['options'][optionList['pay']['ref'].val()];
                        return "/Index/Admin/searchPhone/phone/"+searchValue.val()+"/page/"+page+"/download/"+download+"/pay/"+pay;
                    }
                },
                "app 下单": {
                    check : function () {
                        return true;
                    },
                    url : function(page) {
                        var download = optionList['download']['options'][optionList['download']['ref'].val()];
                        var pay = optionList['pay']['options'][optionList['pay']['ref'].val()];
                        return "/Index/Admin/searchAppOrder/page/"+page+"/download/"+download+"/pay/"+pay;
                    }
                },
                "未成功下载图片订单": {
                    check: function () {
                        return true;
                    },
                    url: function (page) {
                        return "/Index/Admin/searchSyncFail/page/" + page;
                    },
                    isSyncFail: true,
                },
                "未通过审核订单": {
                    check: function () {
                        return true;
                    },
                    url: function (page) {
                        return "/Index/Admin/searchAuditFail/page/" + page;
                    },
                    isAuditFail: true,
                }
            }
        },
        "download" : {
            "ref":null,
            "options" : {
                "全部":"all",
                "未下载":"y",
                "下载":"n"
            }
        },
        "pay" : {
            "ref":null,
            "options" : {
                "支付":"y",
                "全部":"all",
                "未支付":"n"
            }
        }
    };

    // 更新搜索条件
    function updateSearch(type) {
        var dType = type;
        var str = "请输入"+dType;

        searchValue.removeAttr('disabled');
        if("app 下单" == dType || "全部" == dType || "未成功下载图片订单" == dType || "未通过审核订单" == dType) {
            searchValue.attr('disabled',"");
            str = "搜索全部"
        }
        optionList["download"]["ref"].show();
        optionList["pay"]["ref"].show();
        if("订单号" == dType || "未成功下载图片订单" == dType || "未通过审核订单" == dType) {
            optionList["download"]["ref"].hide();
            optionList["pay"]["ref"].hide();
        }
        searchValue.attr("placeholder",str);
    }

    // 初始化查询条件列表
    (function(){
        var x;
        for(x in optionList) {
            if(optionList.hasOwnProperty(x)) {
                optionList[x]["ref"] =  $('<select class="form-control"></select>');
                if(optionList[x]["callback"]) {
                    optionList[x]["ref"].bind("change",optionList[x]["callback"]);
                }
                var y;
                var optionHtml = "";
                for(y in optionList[x]["options"]) {
                    if(optionList[x]["options"].hasOwnProperty(y)) {
                        optionHtml += "<option>"+y+"</option>"
                    }
                }
                optionList[x]["ref"].append(optionHtml);
                searchCond.append(optionList[x]["ref"]);
            }
        }
    })();
    updateSearch(optionList['condition']['ref'].val());

    // 节点生成
    function orderItem(orderData, flag) {

        var liListStr = "";
        for(var i=0;i<orderData['pics'].length;++i) {
            liListStr +='<li data-type="'+orderData['pics'][i]["type"]
                            +'" data-id="'+orderData['pics'][i]["id"]
                            +'" data-url="'+orderData['pics'][i]["url"]+'"></li>';
        }

        var btn_text_style = 'style="width: 100%;"';
        if(typeof flag != 'undefined' && flag !== false){
            if(flag == 'sync'){
                var btn_text = '重新同步';
                var btn_set = '';
                if(orderData['is_sync'] == '5'){
                    var btn_text = '查看损坏详情';
                    if(orderData['sys'] == 'zpk'){
                        btn_text_style = 'style="display:none;"';
                        var btn_set = '<button data-id="'+orderData['cid']+'" data-tid="'+orderData['orderno']+'"  style="display:none;" class="btn btn-default all-imgs-btn">所有图片</button>';
                    }else{
                        var btn_set = '<button data-id="'+orderData['cid']+'" data-tid="'+orderData['orderno']+'"  style="width: 100%;" class="btn btn-default all-imgs-btn">所有图片</button>';
                    }
                    btn_set += '<button data-id="'+orderData['cid']+'" data-tid="'+orderData['orderno']+'"  style="width: 100%;" data-op="bad_image" class="btn btn-default bypass-btn">完成处理</button>';
                    btn_set += '<button data-id="'+orderData['cid']+'" data-tid="'+orderData['orderno']+'"  style="width: 100%;" class="btn btn-default reset-btn">重置订单</button>';
                }
                btn_set += '<button data-id="'+orderData['cid']+'" data-tid="'+orderData['orderno']+'"  style="width: 100%;" data-op="del_order" class="btn btn-default del-order-btn">删除订单</button>';
            }
            if(flag == 'audit'){
                var btn_text = '查看图片';
                var btn_set = '<button data-id="'+orderData['cid']+'" data-tid="'+orderData['orderno']+'"  style="width: 100%;" data-op="audit_image" class="btn btn-default bypass-btn">通过审核</button>';
            }
        }else{
            var btn_text = '详情';
            var btn_set = '<button data-id="'+orderData['cid']+'" data-tid="'+orderData['orderno']+'"  style="width: 100%;" class="btn btn-default address-btn">重置地址</button><br>'
               +'<button data-id="'+orderData['cid']+'" data-tid="'+orderData['orderno']+'"  style="width: 100%;" class="btn btn-default reset-btn">重置订单</button>'
               +'<button data-id="'+orderData['cid']+'" data-tid="'+orderData['orderno']+'"  style="width: 100%;" class="btn btn-default cancel-btn">取消订单</button>';
        }

        btn_set += '<button data-id="'+orderData['cid']+'" data-uid="'+orderData['uid']+'"  style="width: 100%;" class="btn btn-default pay-info-btn">查看订单支付详情</button>'

        return '<tr id="'+orderData['orderno']+'">'
            +'<td class="order-name">'+orderData['orderno']+'</td>'
            +'<td><img class="wx-header" src="'+orderData['avatar']+'"><br>'+orderData['nickname']+'</td>'
            +'<td style="max-width:150px; text-align: left;">'
                +'<span class="contact-name text-primary">'+orderData['name']+'</span> @'
                +'<span class="contact-phone text-muted">'+orderData['phone']+'</span> #'
                +'<span class="contact-address text-muted">'+orderData['address']+' '+orderData['street']+'</span>'
            +'</td>'
            +'<td style="max-width:150px;">'+orderData['message']+'</td>'
            +'<td style="max-width:50px;">'+orderData['create_time']+'</td>'
            +'<td style="max-width:50px;">'+orderData['paidTime']+'</td>'
            +'<td>'+orderData['sys']+'</td>'
            +'<td>'+orderData['status']+'</td>'
            +'<td>'+orderData['mailno']+'</td>'
            +'<td>'+orderData['is_pdf']+'</td>'
            +'<td>'+orderData['pdf_file']+'</td>'
            +'<td class="op-status">'+orderData['op_status']+'</td>'
            +'<td data-id="'+orderData['cid']+'" data-order="'+orderData['orderno']+'">'
                +'<ul class="order-detail">'
                    +liListStr
                +'</ul>'
                +'<button data-sync="'+orderData['is_sync']+'" class="detail-btn btn btn-default" '+btn_text_style+'>'+btn_text+'</button><br>'
                +btn_set
            +'</td>'
        +'</tr>'
    }

    // 查询请求
    var isSearch = false;
    function search(newPage) {
        if(isSearch) {
            return;
        }
        var orderList = $("#orderList");
        isSearch = true;

        function reset_all_order(){

            var pwd = prompt("请输入重置密码");

            if (pwd != null) {

                var postData = {
                    'pwd' : pwd
                };

                $.ajax({
                    url: "/Index/Admin/resetAllOrder",
                    type: 'POST',
                    data: postData,
                    success : function(json) {
                        if(json.status == "error") {
                            alert(json.reason);
                        } else {
                            alert("重置成功");
                        }
                    },
                    error: function (){
                        alert("服务器错误!");
                    }
                });
            }
        }

        if(typeof optionList['condition']['options'][optionList['condition']['ref'].val()]['isSyncFail'] != 'undefined'){
            var isSyncFail = true;
            $( "#reset_all_order" ).remove();
            $( '<a style="margin:10px 0;" href="javascript:;" id="reset_all_order" class="btn btn-primary">重置所有订单</a>' ).insertAfter( $('.main .search').eq(0) );
            $( "#reset_all_order" ).on('click',function(){
                reset_all_order();
            });
        }else{
            var isSyncFail = false;
            $( "#reset_all_order" ).remove();
        }

        if(typeof optionList['condition']['options'][optionList['condition']['ref'].val()]['isAuditFail'] != 'undefined'){
            var isAuditFail = true;
        }else{
            var isAuditFail = false;
        }

        $.ajax({
            url: optionList['condition']['options'][optionList['condition']['ref'].val()]['url'](newPage),
            type: 'POST',
            success : function(data) {
                // 更新订单列表
                orderList.empty();
                pageCount = newPage;
                if(data.data) {
                    for(var i=0;i<data.data.length;++i) {
                        if(isSyncFail){
                            orderList.append(orderItem(data['data'][i], 'sync'));
                        }else if(isAuditFail){
                            orderList.append(orderItem(data['data'][i], 'audit'));
                        }else{
                            orderList.append(orderItem(data['data'][i], false));
                        }
                    }
                }

                if(isSyncFail){
                    $('.detail-btn').on("click",resetSync);
                }else if(isAuditFail){
                    $('.detail-btn').on("click",openPic);
                }else{
                    $('.detail-btn').on("click",openDlg);
                }

                $('.address-btn').on("click",openAddressDlg);
                $('.bypass-btn').on("click",byPassAudit);
                $('.reset-btn').on("click",openResetDlg);
                $('.cancel-btn').on("click",cancelBtn);

                $('.all-imgs-btn').on("click",openDlg);
                $('.pay-info-btn').on("click",openDetailDlg);
                $('.del-order-btn').on('click',delOrder);

                // 上一页
                if(data['preBtnDisabled']) {
                    $("#prePage").addClass("disabled");
                }else {
                    $("#prePage").removeClass("disabled");
                }

                // 下一页
                if(data['nextBtnDisabled']) {
                    $("#nextPage").addClass("disabled");
                }else {
                    $("#nextPage").removeClass("disabled");
                }

                isSearch = false;
            },
            error: function (){
                isSearch = false;
            }
        });
    }
    // 查询联系人
    $("#searchBtn").on("click",function(){
        if(!optionList['condition']['options'][optionList['condition']['ref'].val()]['check']()) {
            return;
        }
        search(0);
    });

    // 上一页
    $("#prePage").on("click",function(){
        if($(this).hasClass("disabled")) {
            return;
        }
        search(pageCount-1);
    });

    // 下一页
    $("#nextPage").on("click",function(){
        if($(this).hasClass("disabled")) {
            return;
        }
        search(pageCount+1);
    });

    // 地址选择
    // 重设城市列表
    function restCityList() {
        var provId=provinceSelect.get(0).selectedIndex;
        citySelect.empty();
        areaSelect.empty();

        if(provId<0 || typeof(cityMap[provId].c)=="undefined"){
            citySelect.css("display","none");
            areaSelect.css("display","none");
            return;
        };

        // 遍历赋值市级下拉列表
        tempHtml="";
        $.each(cityMap[provId].c,function(i,city){
            tempHtml+="<option value='"+city.n+"'>"+city.n+"</option>";
        });

        citySelect.html(tempHtml).css("display","");
        restAreaList();
    }

    // 重设区域列表
    function restAreaList() {
        var provId=provinceSelect.get(0).selectedIndex;
        var cityId=citySelect.get(0).selectedIndex;

        areaSelect.empty();

        if(provId<0||cityId<0||typeof(cityMap[provId].c[cityId].a)=="undefined"){
            areaSelect.css("display","none")
            return;
        };

        // 遍历赋值市级下拉列表
        tempHtml="";
        $.each(cityMap[provId].c[cityId].a,function(i,dist){
            tempHtml+="<option value='"+dist.s+"'>"+dist.s+"</option>";
        });
        areaSelect.html(tempHtml).css("display","");
    }

    provinceSelect.bind("change",function(){
        restCityList();
    });

    // 选择市级时发生事件
    citySelect.bind("change",function(){
        restAreaList();
    });

    // 初始化列表
    var tempHtml="";
    $.each(cityMap,function(i,prov){
        tempHtml+="<option value='"+prov.p+"'>"+prov.p+"</option>";
    });
    provinceSelect.html(tempHtml);
    restCityList();
});
