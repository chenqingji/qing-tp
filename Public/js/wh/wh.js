jQuery.fn.extend({
        /**
         * 
         * @param {type} type   success/info/warning/danger
         * @param {type} icon_type      ok/remove/exclamation -font awesome etc.
         * @param {type} msg message
         * @param {type} time animation时长，0表示么有animation
         * @returns {undefined}
         */
        fn_tips: function (type, icon_type, msg, time) {
                var tipsDom = '<div class="alert alert-' + type + '"><i class="icon-' + icon_type + '"></i>&nbsp;&nbsp;' + msg + '<button class="close" data-dismiss="alert"><i class="icon-remove"></i></button></div>';
                if ("undefined" == typeof time) {
                        time = 10000;
                }
                if(0 ===  time){
                        $("div.alert").remove();
                }
                $(this).prepend(tipsDom);
                if (time) {
                        $("div.alert").fadeOut(time);
                }
                $(tipsDom).focus();
        }
}); 