/**
 * Plugin for using queue for multiple ajax requests.
 *
 * @autor Pavel Máca
 * @github https://github.com/PavelMaca
 * @license MIT
 * @Gist https://gist.github.com/pavelmaca/942485
 */
(function($) {
    var AjaxQueue = function(options) {
        this.options = options || {};
        var oldComplete = options.complete || function() {};
        var completeCallback = function(XMLHttpRequest, textStatus) {
            (function() {
                oldComplete(XMLHttpRequest, textStatus);
            })();
            $.ajaxQueue.currentRequest = null;
            $.ajaxQueue.startNextRequest();
        };
        this.options.complete = completeCallback;
    };
    AjaxQueue.prototype = {
        options: {},
        perform: function() {
            $.ajax(this.options);
        }
    }
    $.ajaxQueue = {
        queue: [],
        currentRequest: null,
        stopped: false,
        stop: function() {
            $.ajaxQueue.stopped = true;
        },
        run: function() {
            $.ajaxQueue.stopped = false;
            $.ajaxQueue.startNextRequest();
        },
        clear: function() {
            $.ajaxQueue.queue = [];
            $.ajaxQueue.currentRequest = null;
        },
        addRequest: function(options) {
            var request = new AjaxQueue(options);
            $.ajaxQueue.queue.push(request);
            $.ajaxQueue.startNextRequest();
        },
        startNextRequest: function() {
            if ($.ajaxQueue.currentRequest) {
                return false;
            }
            var request = $.ajaxQueue.queue.shift();
            if (request) {
                $.ajaxQueue.currentRequest = request;
                request.perform();
            }
        }
    }
})(jQuery);
(function($) {
    function escape(htmlStr) {
        return htmlStr.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }

    function get_select_tables() {
        var get_select_tables = [];
        $("input.select_tables:checked").each(function() {
            get_select_tables.push(this.value);
        });;
        return get_select_tables;
    }
    $(document).ready(function() {
        $('#check_all').click(function() {
            var checkBoxes = $("input.select_tables");
            checkBoxes.prop("checked", !checkBoxes.prop("checked"));
        });
        $('#select_dbname').change(function() {
            if ('URLSearchParams' in window) {
                var searchParams = new URLSearchParams(window.location.search);
                searchParams.set("dbname", $(this).val());
                window.location.search = searchParams.toString();
            } else {
                alert('你瀏覽器也太舊了！請手動補上 dbname= 選擇的參數吧！');
            }
        });
        $('#replace_preview').click(function() {
            var dbname = $('#select_dbname').val();
            var tables = get_select_tables();
            var replace_from = $('#replace_from').val().split('\n');
            var replace_to = $('#replace_to').val().split('\n');
            console.log(tables, replace_from, replace_to);
            if (tables.length == 0) {
                alert('必須至少選一個資料表！');
                return;
            }
            if (replace_from.length != replace_to.length) {
                alert('搜尋與取代的數量必須一致！');
                return;
            }
            var err = '';
            for (var i = replace_from.length - 1; i >= 0; i--) {
                if (replace_from[i] == replace_to[i]) {
                    err += '第 ' + (i + 1) + ' 筆的搜尋與取代不得相同！\n';
                }
            }
            if (err != '') {
                alert(err);
                return;
            }
            // preview_result 
            $('#preview_result').html('<strong><font color="blue">執行中，如果修改的資料表數量很多，需要花費多一點時間，請耐心等待。</font></strong');
            $('#replace_preview').prop('disabled', true);
            //重置「操作」資訊
            $('.table_result').each(function(i, e) {
                $(e).html('');
            });
            // 從 table 開始拆
            for (var t_index = tables.length - 1; t_index >= 0; t_index--) {
                for (var r_index = replace_from.length - 1; r_index >= 0; r_index--) {
                    var ajax_options = {
                        method: "POST",
                        url: MXP.ajaxurl,
                        cache: false,
                        context: {
                            t_index: t_index,
                            r_index: r_index,
                            table: tables[t_index],
                            replace_from: replace_from[r_index],
                            replace_to: replace_to[r_index],
                            total_replace_count: replace_from.length
                        },
                        error: function(res) {
                            console.log('Error', res);
                        },
                        success: function(res) {
                            var _self = this;
                            if (res.success) {
                                console.log(_self, res.data);
                                var str = '';
                                var changed = [];
                                var changed_str = '';
                                for (var i = res.data.report.length - 1; i >= 0; i--) {
                                    changed[i] = [];
                                    var current_report = res.data.report[i];
                                    str += '資料表（' + _self.table + '）第 ' + (_self.r_index + 1) + ' 組的（' + _self.replace_from + ' -> ' + _self.replace_to + '）預覽：' + current_report.change + '/' + current_report.rows + ' 筆數即將改變。耗時：' + (Math.round((current_report.end - current_report.start) * 100) / 100) + ' 秒。</br>\n';
                                    var tables = current_report.table_reports;
                                    Object.keys(tables).forEach(function(key) {
                                        $('#' + key).html('資料表（' + _self.table + '）第 ' + (_self.r_index + 1) + ' 組 <font color="blue">' + tables[key].change + ' 筆資料即將改變（預覽）</font></br>' + $('#' + key).html());
                                        if (tables[key].change > 0 && tables[key].changes.length > 0) {
                                            var show_change = tables[key].changes[0];
                                            console.log(key, show_change);
                                            changed[i].push('<strong>取代變化（預覽一筆）</strong></br>欄位：' + show_change.column + '</br>改變前：<pre><code>' + escape(show_change.from) + '</code></pre></br>改變後：<pre><code>' + escape(show_change.to) + '</code></pre></br>\n');
                                        }
                                    });
                                    if (changed[i][0] !== undefined) {
                                        changed_str += changed[i][0];
                                    }
                                }
                                str += changed_str;
                                $('#preview_result').html('<font color="blue">' + str + '</font>' + $('#preview_result').html());
                                if ($('#' + _self.table).html() == '') {
                                    $('#' + _self.table).html('<strong><font color="red">此資料表無法進行取代的作業。</font></strong>');
                                }
                            } else {
                                console.log(res.data);
                            }
                            if (_self.r_index == 0) {
                                $('#replace_preview').prop('disabled', false);
                            }
                        },
                        data: {
                            'action': 'mxp_ajax_search_replace_db',
                            'nonce': MXP.nonce,
                            'tables': [tables[t_index]],
                            'replace_from': [replace_from[r_index]],
                            'replace_to': [replace_to[r_index]],
                            'dbname': dbname,
                            'dry_run': true
                        }
                    };
                    $.ajaxQueue.addRequest(ajax_options);
                }
            }
        });
        $('#go_replace').click(function() {
            var yes = confirm('接下來會進行真正的資料庫搜尋與取代操作，確保有備份好資料再進行！');
            if (yes == false) {
                return;
            }
            var dbname = $('#select_dbname').val();
            var tables = get_select_tables();
            var replace_from = $('#replace_from').val().split('\n');
            var replace_to = $('#replace_to').val().split('\n');
            console.log(tables, replace_from, replace_to);
            if (tables.length == 0) {
                alert('必須至少選一個資料表！');
                return;
            }
            if (replace_from.length != replace_to.length) {
                alert('搜尋與取代的數量必須一致！');
                return;
            }
            var err = '';
            for (var i = replace_from.length - 1; i >= 0; i--) {
                if (replace_from[i] == replace_to[i]) {
                    err += '第 ' + (i + 1) + ' 筆的搜尋與取代不得相同！\n';
                }
            }
            if (err != '') {
                alert(err);
                return;
            }
            $('#replace_result').html('<strong><font color="red">執行中，如果修改的資料表數量很多，需要花費多一點時間，請耐心等待。</font></strong');
            $('#go_replace').prop('disabled', true);
            $('.table_result').each(function(i, e) {
                $(e).html('');
            });
            // 從 table 開始拆
            for (var t_index = tables.length - 1; t_index >= 0; t_index--) {
                for (var r_index = replace_from.length - 1; r_index >= 0; r_index--) {
                    var ajax_options = {
                        method: "POST",
                        url: MXP.ajaxurl,
                        cache: false,
                        context: {
                            t_index: t_index,
                            r_index: r_index,
                            table: tables[t_index],
                            replace_from: replace_from[r_index],
                            replace_to: replace_to[r_index],
                            total_replace_count: replace_from.length
                        },
                        error: function(res) {
                            console.log('Error', res);
                        },
                        success: function(res) {
                            var _self = this;
                            if (res.success) {
                                console.log(_self, res.data);
                                var str = '';
                                var changed = [];
                                var changed_str = '';
                                for (var i = res.data.report.length - 1; i >= 0; i--) {
                                    changed[i] = [];
                                    var current_report = res.data.report[i];
                                    str += '資料表（' + _self.table + '）第 ' + (_self.r_index + 1) + ' 組的（' + _self.replace_from + ' -> ' + _self.replace_to + '）：' + current_report.change + '/' + current_report.rows + ' 筆數改變。耗時：' + (Math.round((current_report.end - current_report.start) * 100) / 100) + ' 秒。</br>\n';
                                    var tables = current_report.table_reports;
                                    Object.keys(tables).forEach(function(key) {
                                        $('#' + key).html('資料表（' + _self.table + '）第 ' + (_self.r_index + 1) + ' 組 <font color="red">' + tables[key].change + ' 筆資料已改變</font></br>' + $('#' + key).html());
                                        if (tables[key].change > 0 && tables[key].changes.length > 0) {
                                            var show_change = tables[key].changes[0];
                                            console.log(key, show_change);
                                            changed[i].push('<strong>取代變化（預覽一筆）</strong></br>欄位：' + show_change.column + '</br>改變前：<pre><code>' + escape(show_change.from) + '</code></pre></br>改變後：<pre><code>' + escape(show_change.to) + '</code></pre></br>\n');
                                        }
                                    });
                                    if (changed[i][0] !== undefined) {
                                        changed_str += changed[i][0];
                                    }
                                }
                                str += changed_str;
                                $('#replace_result').html('<font color="red">' + str + '</font>' + $('#replace_result').html());
                                if ($('#' + _self.table).html() == '') {
                                    $('#' + _self.table).html('<strong><font color="red">此資料表無法進行取代的作業。</font></strong>');
                                }
                            } else {
                                console.log(res.data);
                            }
                            if (_self.r_index == 0) {
                                $('#replace_result').prop('disabled', false);
                            }
                        },
                        data: {
                            'action': 'mxp_ajax_search_replace_db',
                            'nonce': MXP.nonce,
                            'tables': [tables[t_index]],
                            'replace_from': [replace_from[r_index]],
                            'replace_to': [replace_to[r_index]],
                            'dbname': dbname,
                            'dry_run': true
                        }
                    };
                    $.ajaxQueue.addRequest(ajax_options);
                }
            }
        });
    });
})(jQuery);