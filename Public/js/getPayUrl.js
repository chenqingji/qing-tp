var isGet = false;
function getPayUrl(cid) {
    if(isGet) {
        return;
    }
    isGet = true;
    $.ajax({
        url: "/index/index/getOrderPayUrl/cid/"+cid,
        type: 'GET',
        dataType: 'json',
        success: function (data) {
            isGet = false;
            if(data.status == 'ok') {
                window.location.href = data.data;
            } else {
                alert(data.reason);
            }
        },
        error: function() {
            isGet = false;
            alert("支付请求失败");
        }
    });
}
