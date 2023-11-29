(function($) {
    $(document).ready(function() {
        $('#search_day_from').datepicker({
            showMonthAfterYear: true,
            changeMonth: true,
            changeYear: true,
            yearRange: "-100:+0",
            dateFormat: "yy/mm/dd",
        });
        $('#search_day_to').datepicker({
            showMonthAfterYear: true,
            changeMonth: true,
            changeYear: true,
            yearRange: "-100:+0",
            dateFormat: "yy/mm/dd",
        });
        $('#go_search_day').click(function() {
            if ('URLSearchParams' in window) {
                var searchParams = new URLSearchParams(window.location.search);
                searchParams.set("day_from", $('#search_day_from').val());
                searchParams.set("day_to", $('#search_day_to').val());
                window.location.search = searchParams.toString();
            } else {
                alert('你瀏覽器也太舊了！請手動補上 day_from= & day_to= 選擇的參數吧！');
            }
        });
        // $('.mxp-dissmis-btn').click(function() {
        //     var self = this;
        //     var data = {
        //         'action': 'mxp_dismiss_notice',
        //         'nonce': Mxp_AJAX_dashboard.nonce,
        //         'key': $(this).data().key,
        //     };
        //     $.post(ajaxurl, data, function(res) {
        //         if (res.success) {
        //             $('#' + $(self).data().key).hide();
        //         } else {
        //             //Error? That's my problem...
        //         }
        //     });
        // });
    });
})(jQuery);