var CNPrint; //声明为全局变量
var initPrintCfg = null;
var printCfg = null;
var waybillInfo = undefined;
var trade_order_info = undefined;
var consignee_address = undefined;


$(document).ready(function () {
        initPage(false);
        $("#the_operator").val("admin");
        $("#the_pick_id").focus();
        //$("#the_operator").focus();    

        $("#the_pick_id").on("keyup",function (event) {
                if (event.keyCode == 13 || event.which == 13) {
                        //单一商品直接可打印电子面单，其他待检验后手动打印
                        if (barcodeCheck()) {
                                print();
                        }
                }
        });

        $("#the_product_id").on("keyup",function (event) {
                if (event.keyCode == 13 || event.which == 13) {
                        addCheckedProduct();
                }
        });

        $("#preview-product").on("click", "p", function () {
                var curInputChecked = $(this).find("input:checkbox").prop("checked");
                if (!curInputChecked) {
                        $(this).find("input:checkbox").prop("checked", true);
                } else {
                        $(this).find("input:checkbox").removeAttr("checked");
                }
                clickUpdatePreviewHtml(this);
        });
        
        
        $("#check_btn").on("click",function () {
                
                //单一商品直接可打印电子面单，不会进入该触发事件。其他待检验后手动打印
                var res = toCheckPickList();
                if (res.status) {
                        $("div.row").fn_tips("success", "ok", res.msg, 0);
                        if (getMailinfo(0)) {
                                print();
                        }
                } else {
                        $("div.row").fn_tips("warning", "exclamation", res.msg, 0);
                }
        });        

        $("#newDayin").on("click",function () {
                if (window.confirm('确定生成新的包裹号，新的包裹号会申请新的快递单号，如果不是为了重发新包裹，不建议使用。')) {
                        var pickId = $.trim($("#the_pick_id").val());
                        if (isPickId(pickId)) {
                                var res = toCheckPickList();
                                if (!res.status) {
                                        $("div.row").fn_tips("warning", "exclamation", res.msg, 0);
                                        return false;
                                }
                        }
                        $("div.row").fn_tips("success", "ok", "成功校对开始打印。", 0);
                        if (getMailinfo(1)) {
                                print();
                        }
                }
        });
        
        $("#reset_btn").on("click",function () {
                initPage(false);
        });        

        $("#sureBtn, #cancelBtn").on("click", function () {
                $("#warmDlg").hide();
                if (this.id == "sureBtn") {
                        print();
                } else if (this.id == "cancelBtn") {
                        $("div.row").fn_tips("success", "ok", "该订单已取消打印", 0);
                        $("#the_pick_id").focus();
                }
        });
});

function initPrint(cfg){
        printCfg = initPrintCfg = cfg;
}

function initPage(pick) {
        $("#check_btn").hide();
        initPreviewProduct(pick);
        initCheckProduct(pick);
        if (pick) {
                $("#the_product_id_form_group").show();
                $("input#the_product_id").val('');
                $("input#the_pick_id").val('');
        } else {
                $("#the_product_id_form_group").hide();
                $("input#the_pick_id").val('');
        }
        $("#textInfo").html('');
        $("div.alert").remove();
        $("input#the_pick_id").focus();
}

function initPreviewProduct(pick) {
        if (pick) {
                var innerHtml = '<pre><span>名称</span>\t\t\t<span>编号</span>\t<span>预期</span>\t<span>检测</span></pre>';
                $("#preview-product").html(innerHtml).show();
        } else {
                $("#preview-product").hide();
        }
}
function initCheckProduct(pick) {
        if (pick) {
                var innerHtml = '<pre><span>检测到的商品</span></pre>';
                $("#check-product").html(innerHtml).show();
        } else {
                $("#check-product").hide();
        }
}
/**
 * page change to the pick mode
 * @returns {undefined}
 */
function changeToPickMode() {
        initPreviewProduct(true);
        initCheckProduct(true);
        $("#the_product_id_form_group").show();
        $("#check_btn").show();
        $("div.alert").remove();
        $("#textInfo").html('');
        $("input#the_product_id").val('').focus();
}
/**
 * 条形码检测，如果是拣货单号则获取拣货单商品信息用于校验
 * 如果是商品编号则获取电子面单信息，用于打印
 * @returns {Boolean}
 */
function barcodeCheck() {
        var pickId = $.trim($("#the_pick_id").val());
        if (pickId !== '') {
                if (isPickId(pickId)) {
                        toGetPickList(pickId);
                } else {
                        return getMailinfo(0);
                }
        } else {
                $("div.row").fn_tips("warning", "exclamation", "请填写有效条形码编号", 0);
                $("#the_pick_id").focus();
        }
        return false;
}
/**
 * to jugde the picklist or product
 * @param {type} pickId
 * @returns {Boolean}
 */
function isPickId(pickId) {
        if (pickId.substr(0, 1) == 'p') {
                return true;
        }
        return false;
}


/**
 * 增加检测到商品
 * @returns {Boolean}
 */
function addCheckedProduct() {
        var the_product_id = $.trim($("#the_product_id").val());
        if (the_product_id == '') {
                return false;
        }
        var existsP = $("#" + the_product_id);
        if ($(existsP).length > 0) {
                var labelClass = 'label-primary';
                updatePreviewHtml(existsP, 1);
                $("#check-product").append('<p name="check_' + the_product_id + '"><span class="label label-xlg ' + labelClass + '">' + the_product_id + '</span><a href="javascript:void(0)" onclick="minusCheckedProduct(this,\'' + the_product_id + '\');">×</a></p>');
        } else {
                var labelClass = 'label-danger';
                $("#check-product").prepend('<p name="check_' + the_product_id + '"><span class="label label-xlg ' + labelClass + '">' + the_product_id + '</span><a href="javascript:void(0)" onclick="minusCheckedProduct(this,\'' + the_product_id + '\');">×</a></p>');
        }
        $("#the_product_id").val('');
        $("#the_product_id").focus();
}
/**
 * 消除已检商品
 * @param {type} delAnchor
 * @param {type} the_product_id
 * @returns {undefined}
 */
function minusCheckedProduct(delAnchor, the_product_id) {
        var existsP = $("#" + the_product_id);
        $(delAnchor).parent('p').remove();
        if ($(existsP).length > 0) {
                updatePreviewHtml(existsP, -1);
        }
        $("#the_product_id").val('');
        $("#the_product_id").focus();
}
/**
 * 更新左侧检测结果预览块
 * @param {type} existsP
 * @param {type} type
 * @returns {undefined}
 */
function updatePreviewHtml(existsP, type) {
        var checkCount = parseInt($(existsP).find("em").html());
        var initCount = parseInt($(existsP).find("strong").html());
        var color = "white";
        if (type == 1) {
                checkCount++;
        } else {
                checkCount--;
        }
        $(existsP).find("em").html(checkCount);
        if (checkCount == initCount) {
                $(existsP).find("span").removeClass("label-primary");
                $(existsP).css("background-color", "green");
                $(existsP).find("strong").css({color: color});
                $(existsP).find("em").css({color: color});
                $(existsP).find("input:checkbox").prop("checked", true);
        } else {
                if (checkCount > initCount) {
                        $(existsP).find("span").addClass("label-primary");
                        $(existsP).css("background-color", "");
                        color = 'red';
                } else if (checkCount < initCount) {
                        $(existsP).find("span").addClass("label-primary");
                        $(existsP).css("background-color", "");
                        color = 'darkorange';
                }
                $(existsP).find("strong").css({color: "#333"});
                $(existsP).find("em").css({color: color});
                $(existsP).find("input:checkbox").removeAttr("checked");
        }
}
/**
 * 手动检测确认商品后更新左侧检测结果预览块及右侧检测显示块
 * @param {type} existsP
 * @returns {undefined}
 */
function clickUpdatePreviewHtml(existsP) {
        var the_product_id = $(existsP).prop("id");
        if ($(existsP).find("input:checkbox").prop("checked")) {
                var color = "white";
                $(existsP).find("span").removeClass("label-primary");
                $(existsP).css("background-color", "green");
                $(existsP).find("strong").css({color: color});
                $(existsP).find("em").css({color: color});
                $(existsP).find("em").html($(existsP).find("strong").html());
                var labelClass = 'label-primary';
                $("#check-product").find("p[name='check_" + the_product_id + "']").remove();
                $("#check-product").append('<p name="check_' + the_product_id + '"><span class="label label-xlg ' + labelClass + '">' + the_product_id + '</span><a href="javascript:void(0)" onclick="minusCheckedProduct(this,\'' + the_product_id + '\');">×</a></p>');
        } else {
                color = 'darkorange';
                $(existsP).find("span").addClass("label-primary");
                $(existsP).css("background-color", "");
                $(existsP).find("strong").css({color: "#333"});
                $(existsP).find("em").css({color: color});
                $(existsP).find("em").html(0);
                $("#check-product").find("p[name='check_" + the_product_id + "']").remove();
        }
}

/**
 * 获取拣货单信息
 * @param {type} pickId
 * @returns {Boolean}
 */
function toGetPickList(pickId) {
        var getRes = true;
        var operator = $("#the_operator").val();
        if ($.trim(operator) == '') {
                $("div.row").fn_tips("warning", "exclamation", "工号不能为空", 0);
                return false;
        }
        $.ajax({
                type: 'post',
                url: '/Warehouse/Checkout/toGetPickList',
                data: 'operator=' + operator + '&pick_id=' + pickId,
                dataType: 'json',
                async: false,
                error: function (res) {
                        $("div.row").fn_tips("warning", "exclamation", "请求失败，请重新操作或联系管理员", 0);
                        getRes = false;
                },
                success: function (res) {
                        if (res.status === 0) {
                                $("div.row").fn_tips("warning", "exclamation", res.data, 0);
                                getRes = false;
                        } else if (res.status === 1) {
                                changeToPickMode();
                                if (res.data) {
                                        var list = res.data;
                                        var pHtml = '';
                                        for (pid in list) {
                                                pHtml = '<p id="' + pid + '"><span class="label label-xlg label-primary">' + list[pid]['name'] + '</span><span class="label label-xlg label-primary">' + pid + '</span><strong>' + list[pid]['count'] + '</strong><em style="color:darkorange;">0</em><input type="checkbox" name="checkout-checkbox"></p>';
                                                $("#preview-product").append(pHtml);
                                        }
                                }
                        }
                }
        });
        return getRes;
}
/**
 * 最后确认检测结果并打印电子面单
 * @returns {toCheckPickList.whprintAnonym$9|toCheckPickList.whprintAnonym$11|toCheckPickList.whprintAnonym$10}
 */
function toCheckPickList() {
        var msg = '校对拣货单：';
        var status = true;
        if ($("#check-product p").length <= 0) {
                return {status: false, msg: "校对失败：尚未检测到商品。"}
        }
        if ($("#preview-product p").length <= 0) {
                return {status: false, msg: "校对失败：不存在拣货单商品。"}
        }
        $("#preview-product p").each(function (i) {
//                                        var pid = $(this).find("span").html();
                var initCount = parseInt($(this).find("strong").html());
                var checkCount = parseInt($(this).find("em").html());
                if (initCount > checkCount) {
                        status = false;
                        msg += "<b>" + this.id + "</b>" + "缺少" + (initCount - checkCount) + "件；";
                } else if (initCount < checkCount) {
                        status = false;
                        msg += "<b>" + this.id + "</b>" + "多了" + (checkCount - initCount) + "件；";
                }
        });
        $("#check-product p span.label-danger").each(function (i) {
                status = false;
                msg += "<b>" + $(this).html() + "</b>" + "不在拣货单中；";
        });
        if (status == true) {
                msg = "成功校对拣货单，商品数量与拣货单数据一致。";
        }
        return {status: status, msg: msg};
}

/**
 * get物流快递信息
 * @param {type} newPack
 * @returns {Boolean}
 */
function getMailinfo(newPack) {
        var postRes = true;
        var operator = $("#the_operator").val();
        var the_num = $("#the_pick_id").val();
        
        if (the_num[0] == 'p') {
                var msg = "拣货单：";
        } else {
                var msg = "商品";
        }
        msg += the_num + " 开始打印电子面单……</br>";
        $("#textInfo").html(msg);
//        $("div.row").fn_tips("warning", "exclamation", msg, 10);
        $.ajax({
                url: "/Warehouse/Express/toPrint",
                type: "post",
                data: "the_num=" + the_num + "&newPack=" + newPack + "&operator=" + operator,
                async: false,
                error: function (e) {
                        $("#textInfo").html(msg + "打印电子面单失败，请重新打印或联系管理员");
                        postRes = false;
                },
                success: function (res) {
                        if (res.status == 0) {
                                msg = msg + "<span style='font-size: 35px;font-weight: 700;color:red; border-top: solid 2px red; border-bottom: solid 2px red;'>" + res.data + "</span></br>";
                                $("#textInfo").html(msg);
                                postRes = false;
                        }
                        var mailInfo = res.data.mailInfo;
                        //mailInfo = $.parseJSON(mailInfo);  //zepto 需要
                        mailInfo = jQuery.parseJSON(mailInfo);
                        //alert(mailInfo);
                        //alert(mailInfo.waybill_apply_new_cols.waybill_apply_new_info.waybill_code);
                        //return;

                        waybillInfo = mailInfo.waybill_apply_new_cols.waybill_apply_new_info;
                        trade_order_info = waybillInfo.trade_order_info;
                        consignee_address = trade_order_info.consignee_address;
//                        console.log(res.data.printCfg);
                        $.extend(printCfg,initPrintCfg,res.data.printCfg);
//                        console.log(printCfg);
                        

                        var order = res.data.orderInfo;
                        msg = msg + "快递号：" + waybillInfo.waybill_code + "</br>";
                        msg = msg + "收货人：" + order.name + "</br>";
                        msg = msg + "电话：" + order.phone + "</br>";
                        msg = msg + "地址：" + order.province + order.city + order.area + order.street + "</br>";
                        $("#textInfo").html(msg);
                        $("#todayPackNum").html(res.data.todayPackNum);

                        if (!newPack && res.data.isReYin == 1) {
                                //重复打印进行提示
                                $("#warmMsg").html("该拣货单/商品是重复打印，是否继续打印?");
                                $("#warmDlg").show();
                                postRes = false;
                        }
                }
        });
        return postRes;
}

function print() {
//        console.log(printCfg);
        CNPrint = getCaiNiaoPrint(document.getElementById('CaiNiaoPrint_OB'), document.getElementById('CaiNiaoPrint_EM'));
        CNPrint.PRINT_INITA(printCfg.top, printCfg.left, printCfg.width, printCfg.height, printCfg.task_name);  //菜鸟电子面单打印任务
        CNPrint.SET_PRINT_IDENTITY("AppKey=" + printCfg.appkey + "&Seller_ID=" + printCfg.seller_id);
        CNPrint.SET_PRINT_MODE("CAINIAOPRINT_MODE", "CP_CODE="+printCfg.cp_code+"&CONFIG=" + waybillInfo.print_config);//加载模板及模板辅助信息
        CNPrint.SET_PRINT_CONTENT("ali_waybill_product_type", printCfg.product_type);//单据类型
        CNPrint.SET_PRINT_CONTENT("ali_waybill_short_address", waybillInfo.short_address);
        CNPrint.SET_PRINT_CONTENT("ali_waybill_package_center_name", waybillInfo.package_center_name);//集散地名称
        CNPrint.SET_PRINT_CONTENT("ali_waybill_package_center_code", waybillInfo.package_center_code);//集散地条码
        CNPrint.SET_PRINT_CONTENT("ali_waybill_waybill_code", waybillInfo.waybill_code);
        CNPrint.SET_PRINT_CONTENT("ali_waybill_consignee_name", trade_order_info.consignee_name);
        CNPrint.SET_PRINT_CONTENT("ali_waybill_consignee_phone", trade_order_info.consignee_phone);
        CNPrint.SET_PRINT_CONTENT("ali_waybill_consignee_address", consignee_address.address_detail);//收件人地址

        // -------------- 未设置好部分  -----------------
        CNPrint.SET_PRINT_CONTENT("ali_waybill_send_name", printCfg.send_name);
        CNPrint.SET_PRINT_CONTENT("ali_waybill_send_phone", printCfg.send_phone);
        CNPrint.SET_PRINT_CONTENT("ali_waybill_shipping_address", printCfg.shipping_address);

        CNPrint.SET_PRINT_CONTENT("ali_waybill_shipping_branch_name", waybillInfo.shipping_branch_name); // 网点名称
        CNPrint.SET_PRINT_CONTENT("ali_waybill_shipping_branch_code", waybillInfo.shipping_branch_code); //发件网点代码
        CNPrint.SET_PRINT_CONTENT("ali_waybill_ext_send_date", printCfg.ext_send_date); //发件日期

        CNPrint.SET_PRINT_CONTENT("ali_waybill_ext_sf_biz_type", printCfg.ext_sf_biz_type);	  //业务类型，暂时使用韵达的
        CNPrint.SET_PRINT_CONTENT("ali_waybill_shipping_address_city", printCfg.shipping_address_city);

        CNPrint.SET_PRINT_STYLEA("ali_waybill_cp_logo_up", "PreviewOnly", 1);  //签收物流logo,1 只预览不打印
        CNPrint.SET_PRINT_STYLEA("ali_waybill_cp_logo_down", "PreviewOnly", 1);  //留存物流logo

        CNPrint.PRINT_DESIGN();   //打印预览
        //CNPrint.PRINT_SETUP();   //启动打印设置页面
        //CNPrint.PRINT();	//直接打印
}