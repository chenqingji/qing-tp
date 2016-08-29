var fn_info_tips = function (type, icon_type, msg) {
        var tipsDom = '<div class="alert alert-' + type + '"><i class="icon-' + icon_type + '"></i>' + msg + '<button class="close" data-dismiss="alert"><i class="icon-remove"></i></button></div>';
        $("div.alert").remove();
        $("#page_content_div").prepend(tipsDom);
//        $(".alert").fadeOut(10000);
};
var fn_addSubmit = function (response, postData) {
        var responseObj = JSON.parse(response.responseText);
        if (responseObj.status === 1) {
                //增加
//                                        jQuery("#grid-table").jqGrid('getGridParam', "new", {
//                                                reloadAfterSubmit : false
//                                        });
                //提示
                fn_info_tips("success", "ok", responseObj.data);
        } else {
                fn_info_tips("danger", "remove", responseObj.data);
        }
        return [true, ""];
};
var fn_editOneSubmit = function (response, postData) {
        var responseObj = JSON.parse(response.responseText);
        if (responseObj.status === 1) {
                //更新
                var gr = jQuery("#grid-table").jqGrid('getGridParam', 'selrow');
                if (gr != null) {
//                                                jQuery("#grid-table").jqGrid('editGridRow', gr, {
//                                                        reloadAfterSubmit : false
//                                                });
                        //提示
                        fn_info_tips("success", "ok", responseObj.data);
                } else {
                        fn_info_tips("warning", "remove", "Please Select Row to update!");
//                        console.log("Please Select Row to update!");
                }
        } else {
                fn_info_tips("danger", "remove", responseObj.data);
        }
        return [true, ""];
};
var fn_beforeEditSubmit = function (){
  if(confirm("确定修改吗")){
          return [true,""];
  }      
  return false;
};
var fn_editSubmit = function (response, postData) {
        var responseObj = JSON.parse(response.responseText);
        if (responseObj.status === 1) {
                //更新
                var gr = jQuery("#grid-table").jqGrid('getGridParam', 'selrow');
                if (gr != null) {
//                                                jQuery("#grid-table").jqGrid('editGridRow', gr, {
//                                                        reloadAfterSubmit : false
//                                                });
                        //提示
                        fn_info_tips("success", "ok", responseObj.data);
                } else {
                        fn_info_tips("warning", "remove", "Please Select Row to update!");
//                        console.log("Please Select Row to update!");
                }
        } else {
                fn_info_tips("danger", "remove", responseObj.data);
        }
        return [true, ""];
};
var fn_delSubmit = function (response, postData) {
        var responseObj = JSON.parse(response.responseText);
        if (responseObj.status === 1) {
                //删除
                var gr = jQuery("#grid-table").jqGrid('getGridParam', 'selrow');
                if (gr != null) {
//                                                jQuery("#grid-table").jqGrid('delGridRow', gr, {
//                                                        reloadAfterSubmit : false
//                                                });
                        //提示
                        fn_info_tips("success", "ok", responseObj.data);
                } else {
                        fn_info_tips("warning", "remove", "Please Select Row to delete!");
//                        console.log("Please Select Row to delete!");
                }
        } else {
                fn_info_tips("danger", "remove", responseObj.data);
        }
        return [true, ""];
};

function style_edit_form(form,validate_rules) {
        //update buttons classes
        var buttons = form.next().find('.EditButton .fm-button');
        buttons.addClass('btn btn-sm').find('[class*="-icon"]').remove();//ui-icon, s-icon
        buttons.eq(0).addClass('btn-primary').prepend('<i class="icon-ok"></i>');
        buttons.eq(1).prepend('<i class="icon-remove"></i>')

        buttons = form.next().find('.navButton a');
        buttons.find('.ui-icon').remove();
        buttons.eq(0).append('<i class="icon-chevron-left"></i>');
        buttons.eq(1).append('<i class="icon-chevron-right"></i>');
//        console.log(form);
        form.validate(validate_rules);
}

function style_delete_form(form) {
        var buttons = form.next().find('.EditButton .fm-button');
        buttons.addClass('btn btn-sm').find('[class*="-icon"]').remove(); //ui-icon, s-icon
        buttons.eq(0).addClass('btn-danger').prepend('<i class="icon-trash"></i>');
        buttons.eq(1).prepend('<i class="icon-remove"></i>');
}

function style_search_filters(form) {
        form.find('.delete-rule').val('X');
        form.find('.add-rule').addClass('btn btn-xs btn-primary');
        form.find('.add-group').addClass('btn btn-xs btn-success');
        form.find('.delete-group').addClass('btn btn-xs btn-danger');
}
function style_search_form(form) {
        var dialog = form.closest('.ui-jqdialog');
        var buttons = dialog.find('.EditTable');
        buttons.find('.EditButton a[id*="_reset"]').addClass('btn btn-sm btn-info').find('.ui-icon').attr('class', 'icon-retweet');
        buttons.find('.EditButton a[id*="_query"]').addClass('btn btn-sm btn-inverse').find('.ui-icon').attr('class', 'icon-comment-alt');
        buttons.find('.EditButton a[id*="_search"]').addClass('btn btn-sm btn-purple').find('.ui-icon').attr('class', 'icon-search');
}

function beforeDeleteCallback(e) {
                                var form = $(e[0]);
                                if (form.data('styled')) return false;
                                form.closest('.ui-jqdialog').find('.ui-jqdialog-titlebar').wrapInner('<div class="widget-header" />')
                                style_delete_form(form);
                                form.data('styled', true);
}

function beforeEditCallback(e) {
                                var form = $(e[0]);
                                form.closest('.ui-jqdialog').find('.ui-jqdialog-titlebar').wrapInner('<div class="widget-header" />')
                                style_edit_form(form);
}



//it causes some flicker when reloading or navigating grid
//it may be possible to have some custom formatter to do this as the grid is being created to prevent this
//or go back to default browser checkbox styles for the grid
function styleCheckbox(table) {
        /**
         $(table).find('input:checkbox').addClass('ace')
         .wrap('<label />')
         .after('<span class="lbl align-top" />')
         
         
         $('.ui-jqgrid-labels th[id*="_cb"]:first-child')
         .find('input.cbox[type=checkbox]').addClass('ace')
         .wrap('<label />').after('<span class="lbl align-top" />');
         */
}


//unlike navButtons icons, action icons in rows seem to be hard-coded
//you can change them like this in here if you want
function updateActionIcons(table) {
        /**
         var replacement = 
         {
         'ui-icon-pencil' : 'icon-pencil blue',
         'ui-icon-trash' : 'icon-trash red',
         'ui-icon-disk' : 'icon-ok green',
         'ui-icon-cancel' : 'icon-remove red'
         };
         $(table).find('.ui-pg-div span.ui-icon').each(function(){
         var icon = $(this);
         var $class = $.trim(icon.attr('class').replace('ui-icon', ''));
         if($class in replacement) icon.attr('class', 'ui-icon '+replacement[$class]);
         })
         */
}

//replace icons with FontAwesome icons like above
function updatePagerIcons(table) {
        var replacement =
                {
                        'ui-icon-seek-first': 'icon-double-angle-left bigger-140',
                        'ui-icon-seek-prev': 'icon-angle-left bigger-140',
                        'ui-icon-seek-next': 'icon-angle-right bigger-140',
                        'ui-icon-seek-end': 'icon-double-angle-right bigger-140'
                };
        $('.ui-pg-table:not(.navtable) > tbody > tr > .ui-pg-button > .ui-icon').each(function () {
                var icon = $(this);
                var $class = $.trim(icon.attr('class').replace('ui-icon', ''));
                if ($class in replacement)
                        icon.attr('class', 'ui-icon ' + replacement[$class]);
        });
}

function enableTooltips(table) {
        $('.navtable .ui-pg-button').tooltip({container: 'body'});
        $(table).find('.ui-pg-div').tooltip({container: 'body'});
}