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
    var search_op = [];
    $(document).ready(function() {
        $('.autoload_off_btn').click(function() {
            var yes = confirm('注意！接下來會進行對資料庫的實際操作，無法還原，請確保有備份好資料再進行！');
            if (yes == false) {
                return;
            }
            var self = $(this);
            var data = {
                'action': 'mxp_ajax_set_autoload_no',
                'nonce': MXP.nonce,
                'name': self.data('option_name'),
            };
            $.post(MXP.ajaxurl, data, function(res) {
                if (res.success) {
                    console.log(res.data);
                    self.text('已取消');
                    self.prop('disabled', true);
                }
            });
        });
        $('#go_clean_orphan_postmeta').click(function() {
            var yes = confirm('注意！接下來會進行對資料庫的實際操作，無法還原，請確保有備份好資料再進行！');
            if (yes == false) {
                return;
            }
            var data = {
                'action': 'mxp_ajax_clean_orphan',
                'nonce': MXP.nonce,
                'type': 'post',
            };
            $.post(MXP.ajaxurl, data, function(res) {
                if (res.success) {
                    console.log(res.data);
                    $('#go_clean_orphan_postmeta').text('完成清除！');
                    $('#go_clean_orphan_postmeta').prop('disabled', true);
                }
            });
        });
        $('#go_clean_orphan_commentmeta').click(function() {
            var yes = confirm('注意！接下來會進行對資料庫的實際操作，無法還原，請確保有備份好資料再進行！');
            if (yes == false) {
                return;
            }
            var data = {
                'action': 'mxp_ajax_clean_orphan',
                'nonce': MXP.nonce,
                'type': 'comment',
            };
            $.post(MXP.ajaxurl, data, function(res) {
                if (res.success) {
                    console.log(res.data);
                    $('#go_clean_orphan_commentmeta').text('完成清除！');
                    $('#go_clean_orphan_commentmeta').prop('disabled', true);
                }
            });
        });
        $('#go_reset_user_metabox').click(function() {
            var yes = confirm('注意！接下來會進行對資料庫的實際操作，無法還原，請確保有備份好資料再進行！');
            if (yes == false) {
                return;
            }
            var data = {
                'action': 'mxp_ajax_reset_user_metabox',
                'nonce': MXP.nonce,
            };
            $.post(MXP.ajaxurl, data, function(res) {
                if (res.success) {
                    console.log(res.data);
                    $('#go_reset_user_metabox').text('完成清除！');
                    $('#go_reset_user_metabox').prop('disabled', true);
                }
            });
        });
        $('#go_clean_options').click(function() {
            var yes = confirm('注意！接下來會進行對資料庫的實際操作，確保有備份好資料再進行！\n此操作針對沒在使用的外掛與主題設定進行清除。\n如果有確認不使用的外掛或主題，請停用並刪除，加速比對清除作業。');
            if (yes == false) {
                return;
            }
            $(this).text('作業中！請耐心等待，勿重新整理此頁面！');
            var step = $('#go_clean_options').data('step');
            $('#go_clean_options').prop('disabled', true);
            if (step == 1) {
                var data = {
                    'action': 'mxp_ajax_db_optimize',
                    'nonce': MXP.nonce,
                    'step': step,
                };
                $.post(MXP.ajaxurl, data, function(res) {
                    if (res.success) {
                        console.log(res.data);
                        var str = '<table><thead><tr><th colspan="3">希望清除的欄位名稱，執行順序 1.刪除 2.強制刪除 </th></tr></thead><tbody>';
                        str += '<tr><td><input type="checkbox" id="del_all" name="del_all" checked><label for="del_all">全部刪除</label>｜<input type="checkbox" id="force_all" name="force_all" ><label for="force_all">全部強制刪除</label></td><td><span id="res_">全域選項設定</span></td></tr>';
                        if (res.data !== undefined && res.data.length > 0) {
                            for (var i = 0; i < res.data.length; i++) {
                                // str += '<span id="res_' + res.data[i].replaceAll('/', '_').replaceAll('.','_') + '">' + res.data[i] + '</span></br>';
                                str += '<tr><td><input type="checkbox" class="del_chk" id="del_' + res.data[i] + '" name="del_' + res.data[i] + '" checked><label for="del_' + res.data[i] + '">刪除欄位</label>｜<input type="checkbox" class="force_chk" id="force_' + res.data[i] + '" name="force_' + res.data[i] + '" ><label for="force_' + res.data[i] + '">強制刪除</label></td><td><span id="res_' + res.data[i] + '">' + res.data[i] + '</span></td></tr>';
                            }
                            str += '</tbody></table>';
                        }
                        if (str != '') {
                            search_op = res.data;
                            $('#go_clean_options_result').html('</br>' + str);
                            $('#del_all').click(function() {
                                var checkBoxes = $("input.del_chk");
                                checkBoxes.prop("checked", !checkBoxes.prop("checked"));
                            });
                            $('#force_all').click(function() {
                                var checkBoxes = $("input.force_chk");
                                checkBoxes.prop("checked", !checkBoxes.prop("checked"));
                            });
                            $('#go_clean_options').data('step', 2);
                            $('#go_clean_options').text('一鍵清除');
                        } else {
                            $('#go_clean_options_result').html('很棒！系統是乾淨的～');
                        }
                    } else {
                        console.log(res.data);
                    }
                    $('#go_clean_options').prop('disabled', false);
                });
            }
            if (step == 2) {
                // for (var i = search_op.length - 1; i >= 0; i--) {
                for (var i = 0; i < search_op.length; i++) {
                    var del = $('#del_' + $.escapeSelector(search_op[i])).is(":checked");
                    var force = $('#force_' + $.escapeSelector(search_op[i])).is(":checked");
                    if (del) {
                        var tmp = [];
                        tmp.push(search_op[i]);
                        var ajax_options = {
                            method: "POST",
                            url: MXP.ajaxurl,
                            cache: false,
                            error: function(res) {
                                console.log('Error', res);
                            },
                            success: function(res) {
                                if (res.success) {
                                    console.log('success', res.data);
                                    if (res.data !== undefined && res.data.length > 0) {
                                        for (var i = 0; i < res.data.length; i++) {
                                            var force = $('#force_' + $.escapeSelector(res.data[i])).is(":checked");
                                            var action = force == true ? '強制' : '嘗試';
                                            $('#res_' + $.escapeSelector(res.data[i])).html('<del>' + res.data[i] + '</del><font color="red"> 》已' + action + '清除！</font>');
                                        }
                                    } else {
                                        var get_slug = this.data.split('=');
                                        var el_id = get_slug[get_slug.length - 1];
                                        $('#res_' + $.escapeSelector(el_id)).html('<del>' + el_id + '</del><font color="blue"> 》使用中的參數，不清除。</font>');
                                    }
                                }
                            },
                            data: {
                                'action': 'mxp_ajax_db_optimize',
                                'nonce': MXP.nonce,
                                'step': step,
                                'force': force == true ? '1' : '0',
                                'search_op': tmp
                            }
                        };
                        $.ajaxQueue.addRequest(ajax_options);
                        $('#res_' + $.escapeSelector(search_op[i])).html(search_op[i] + '<font color="blue"> (<strong>作業中！請耐心等待，勿重新整理此頁面！</strong>)</font>');
                    } else {
                        $('#res_' + $.escapeSelector(search_op[i])).html(search_op[i] + '<font color="orange"> (<strong>本次作業不處理！</strong>)</font>');
                    }
                }
                $.ajaxQueue.run();
                $('#go_clean_options').text('清除中...請勿重新整理頁面。');
                // $('#go_clean_options').prop('disabled', false);
            }
        });
        $('#go_clean_postmeta').click(function() {
            var yes = confirm('注意！接下來會進行對資料庫的實際操作，確保有備份好資料再進行！\n此操作針對 _postmeta 資料表中，重複的 meta_key 紀錄進行清除。');
            if (yes == false) {
                return;
            }
            $(this).text('作業中！請耐心等待，勿重新整理此頁面！');
            var step = $('#go_clean_postmeta').data('step');
            $('#go_clean_postmeta').prop('disabled', true);
            if (step == 1) {
                var data = {
                    'action': 'mxp_ajax_db_optimize_postmeta',
                    'nonce': MXP.nonce,
                    'step': step,
                };
                $.post(MXP.ajaxurl, data, function(res) {
                    if (res.success) {
                        console.log(res.data);
                        var str = '';
                        if (res.data !== undefined && res.data.length > 0) {
                            str = '<table><thead><tr><th colspan="4">清除重複的 Post Meta 資料，預設清除較舊的那筆。</th></tr></thead><tbody>';
                            str += '<tr><td><input type="checkbox" id="del_all_postmeta" name="del_all_postmeta" checked><label for="del_all_postmeta">全部刪除/Meta ID</label></td><td>Post ID</td><td>Meta Key</td><td>Meta Value</td></tr>';
                            for (var i = 0; i < res.data.length; i++) {
                                str += '<tr><td><input type="checkbox" class="del_postmeta_id_chk" id="del_meta_id_' + res.data[i].meta_id + '" value="' + res.data[i].meta_id + '" checked>' + res.data[i].meta_id + '</td><td>' + res.data[i].post_id + '</td><td>' + res.data[i].meta_key + '</td><td>' + res.data[i].meta_value + '<span id="res_postmeta_' + res.data[i].meta_id + '"></span></td></tr>';
                            }
                            str += '</tbody></table>';
                        }
                        if (str != '') {
                            $('#go_clean_postmeta_result').html('</br>' + str);
                            $('#del_all_postmeta').click(function() {
                                var checkBoxes = $("input.del_postmeta_id_chk");
                                checkBoxes.prop("checked", !checkBoxes.prop("checked"));
                            });
                            $('#go_clean_postmeta').data('step', 2);
                            $('#go_clean_postmeta').text('刪除勾選項目');
                            $('#go_clean_postmeta').prop('disabled', false);
                        } else {
                            $('#go_clean_postmeta').text('無需清除紀錄');
                            $('#go_clean_postmeta').prop('disabled', true);
                        }
                    } else {
                        console.log(res.data);
                        $('#go_clean_postmeta').prop('disabled', false);
                    }
                });
            }
            if (step == 2) {
                var meta_ids = [];
                $('input.del_postmeta_id_chk:checked').each(function() {
                    meta_ids.push($(this).val());
                });
                console.log(meta_ids);
                for (var i = 0; i < meta_ids.length; i++) {
                    var del = $('#del_meta_id_' + meta_ids[i]).is(":checked");
                    if (del) {
                        var tmp = [];
                        tmp.push(meta_ids[i]);
                        var ajax_options = {
                            method: "POST",
                            url: MXP.ajaxurl,
                            cache: false,
                            error: function(res) {
                                console.log('Error', res);
                            },
                            success: function(res) {
                                if (res.success) {
                                    console.log('success', res.data);
                                    if (res.data !== undefined && res.data.length > 0) {
                                        for (var i = 0; i < res.data.length; i++) {
                                            $('#res_postmeta_' + $.escapeSelector(res.data[i])).html('<font color="red"> 》已清除！</font>');
                                        }
                                    }
                                }
                            },
                            data: {
                                'action': 'mxp_ajax_db_optimize_postmeta',
                                'nonce': MXP.nonce,
                                'step': step,
                                'meta_ids': tmp
                            }
                        };
                        $.ajaxQueue.addRequest(ajax_options);
                        $('#res_postmeta_' + $.escapeSelector(meta_ids[i])).html(meta_ids[i] + '<font color="blue"> (<strong>作業中！請耐心等待，勿重新整理此頁面！</strong>)</font>');
                    } else {
                        $('#res_postmeta_' + $.escapeSelector(meta_ids[i])).html(meta_ids[i] + '<font color="orange"> (<strong>本次作業不處理！</strong>)</font>');
                    }
                }
                $.ajaxQueue.run();
                $('#go_clean_postmeta').text('清除中...請勿重新整理頁面。');
            }
        });
        $('#go_clean_usermeta').click(function() {
            var yes = confirm('注意！接下來會進行對資料庫的實際操作，確保有備份好資料再進行！\n此操作針對 _usermeta 資料表中，重複的 meta_key 紀錄進行清除。');
            if (yes == false) {
                return;
            }
            $(this).text('作業中！請耐心等待，勿重新整理此頁面！');
            var step = $('#go_clean_usermeta').data('step');
            $('#go_clean_usermeta').prop('disabled', true);
            if (step == 1) {
                var data = {
                    'action': 'mxp_ajax_db_optimize_usermeta',
                    'nonce': MXP.nonce,
                    'step': step,
                };
                $.post(MXP.ajaxurl, data, function(res) {
                    if (res.success) {
                        console.log(res.data);
                        var str = '';
                        if (res.data !== undefined && res.data.length > 0) {
                            str = '<table><thead><tr><th colspan="4">清除重複的 User Meta 資料，預設清除較舊的那筆。</th></tr></thead><tbody>';
                            str += '<tr><td><input type="checkbox" id="del_all_usermeta" name="del_all_usermeta" checked><label for="del_all_usermeta">全部刪除/Meta ID</label></td><td>User ID</td><td>Meta Key</td><td>Meta Value</td></tr>';
                            for (var i = 0; i < res.data.length; i++) {
                                str += '<tr><td><input type="checkbox" class="del_usermeta_id_chk" id="del_meta_id_' + res.data[i].umeta_id + '" value="' + res.data[i].umeta_id + '" checked>' + res.data[i].umeta_id + '</td><td>' + res.data[i].user_id + '</td><td>' + res.data[i].meta_key + '</td><td>' + res.data[i].meta_value + '<span id="res_usermeta_' + res.data[i].umeta_id + '"></span></td></tr>';
                            }
                            str += '</tbody></table>';
                        }
                        if (str != '') {
                            $('#go_clean_usermeta_result').html('</br>' + str);
                            $('#del_all_usermeta').click(function() {
                                var checkBoxes = $("input.del_usermeta_id_chk");
                                checkBoxes.prop("checked", !checkBoxes.prop("checked"));
                            });
                            $('#go_clean_usermeta').data('step', 2);
                            $('#go_clean_usermeta').text('刪除勾選項目');
                            $('#go_clean_usermeta').prop('disabled', false);
                        } else {
                            $('#go_clean_usermeta').text('無需清除紀錄');
                            $('#go_clean_usermeta').prop('disabled', true);
                        }
                    } else {
                        console.log(res.data);
                        $('#go_clean_usermeta').prop('disabled', false);
                    }
                });
            }
            if (step == 2) {
                var meta_ids = [];
                $('input.del_usermeta_id_chk:checked').each(function() {
                    meta_ids.push($(this).val());
                });
                console.log(meta_ids);
                for (var i = 0; i < meta_ids.length; i++) {
                    var del = $('#del_meta_id_' + meta_ids[i]).is(":checked");
                    if (del) {
                        var tmp = [];
                        tmp.push(meta_ids[i]);
                        var ajax_options = {
                            method: "POST",
                            url: MXP.ajaxurl,
                            cache: false,
                            error: function(res) {
                                console.log('Error', res);
                            },
                            success: function(res) {
                                if (res.success) {
                                    console.log('success', res.data);
                                    if (res.data !== undefined && res.data.length > 0) {
                                        for (var i = 0; i < res.data.length; i++) {
                                            $('#res_usermeta_' + $.escapeSelector(res.data[i])).html('<font color="red"> 》已清除！</font>');
                                        }
                                    }
                                }
                            },
                            data: {
                                'action': 'mxp_ajax_db_optimize_usermeta',
                                'nonce': MXP.nonce,
                                'step': step,
                                'meta_ids': tmp
                            }
                        };
                        $.ajaxQueue.addRequest(ajax_options);
                        $('#res_usermeta_' + $.escapeSelector(meta_ids[i])).html(meta_ids[i] + '<font color="blue"> (<strong>作業中！請耐心等待，勿重新整理此頁面！</strong>)</font>');
                    } else {
                        $('#res_usermeta_' + $.escapeSelector(meta_ids[i])).html(meta_ids[i] + '<font color="orange"> (<strong>本次作業不處理！</strong>)</font>');
                    }
                }
                $.ajaxQueue.run();
                $('#go_clean_usermeta').text('清除中...請勿重新整理頁面。');
            }
        });
        $('#go_reset_wp').click(function() {
            var yes = confirm('你確定知道自己正在將網站內容全部清除重新建置嗎？');
            if (yes == false) {
                return;
            }
            $('#go_reset_wp').text('網站重置中... 請勿離開此畫面，稍後片刻！');
            $('#go_reset_wp').prop('disabled', true);
            var del_uploads = confirm('是否清除上傳目錄資料夾？');
            var clean_all_tables = confirm('是否清除其他非 WordPress 內建資料表？');
            var keep_options_reset = confirm('是否保留網站系統設定？');
            var password = prompt("請輸入新的管理員密碼。（留空預設為使用當前使用者密碼）", "");
            var domain = prompt("最後確認，請輸入本網站網域名稱 " + window.location.hostname, "");
            if (domain != window.location.hostname) {
                alert('比對錯誤，中斷執行。');
                $('#go_reset_wp').prop('disabled', false);
                return;
            }
            var data = {
                'action': 'mxp_ajax_reset_wp',
                'nonce': MXP.nonce,
                'del_uploads': del_uploads == true ? '1' : '0',
                'clean_all_tables': clean_all_tables == true ? '1' : '0',
                'keep_options_reset': keep_options_reset == true ? '1' : '0',
                'password': password
            };
            $.post(MXP.ajaxurl, data, function(res) {
                if (res.success) {
                    console.log(res.data);
                    if (res.data.password != '') {
                        var newpassword = prompt("重置網站成功！新密碼如下，複製與確認後將會跳轉登入", res.data.password);
                    } else {
                        alert('重置網站成功！接下來會跳轉登入頁面，請使用原密碼登入網站。');
                    }
                    location.href = res.data.url + '/wp-admin/';
                } else {
                    console.log(res.data);
                    alert('重置網站失敗！')
                }
                $('#go_reset_wp').prop('disabled', false);
            });
        });
        var mysqldump_step = 1;
        var dump_dbname = '';
        var dump_file_name = '';
        var dump_file_path = '';
        $('.mxp_mysqldump').click(function() {
            mysqldump_step = parseInt($(this).data('step'));
            if (MXP.mysqldump_process.length != 0 && mysqldump_step == 1) {
                if (confirm('資料庫背景打包執行中，如要繼續將會中斷前次操作，確定嗎？') == false) {
                    return;
                }
            }
            var self = this;
            if ($(this).data('database') == '') {
                alert('尚未選擇匯出的資料庫！');
                return;
            } else {
                dump_dbname = $(this).data('database');
            }
            $('.mxp_mysqldump').prop('disabled', true);
            if (mysqldump_step == 1) {
                var ajax_options = {
                    method: "POST",
                    dataType: "json",
                    url: MXP.ajaxurl,
                    cache: false,
                    error: function(res) {
                        console.log('Error', res);
                    },
                    success: function(res) {
                        if (res.success) {
                            console.log('success', res.msg);
                            dump_file_name = res.data['dump_file_name'];
                            dump_file_path = res.data['dump_file_path'];
                            $(self).text('背景匯出與打包中！');
                            mysqldump_step += 1;
                            $(self).data('step', mysqldump_step);
                            $(self).data('dump_file_name', dump_file_name);
                            $(self).data('dump_file_path', dump_file_path);
                            $(self).prop('disabled', false);
                            $(self).trigger('click');
                        } else {
                            console.log('success', res);
                            $(self).text(res.msg);
                        }
                    },
                    data: {
                        'action': 'mxp_ajax_mysqldump_large',
                        'database': dump_dbname,
                        'step': 1,
                        'nonce': MXP.nonce,
                    }
                };
                $.ajaxQueue.addRequest(ajax_options);
            }
            if (mysqldump_step == 2) {
                dump_file_name = $(self).data('dump_file_name');
                dump_file_path = $(self).data('dump_file_path');
                var ajax_options = {
                    method: "POST",
                    dataType: "json",
                    url: MXP.ajaxurl,
                    cache: false,
                    timeout: 30000,
                    error: function(res) {
                        console.log('Error', res);
                        $(self).prop('disabled', false);
                        $(self).trigger('click');
                    },
                    success: function(res) {
                        if (res.success) {
                            console.log('success', res.msg);
                            $(self).text('下載中！( ' + res.data['filesize'] + ' MB)');
                            location.href = res.data['download_link'];
                        } else {
                            console.log('success', res);
                            $(self).text(res.msg + '(' + res.data.progress + ')');
                            $(self).prop('disabled', false);
                            $(self).trigger('click');
                        }
                    },
                    data: {
                        'action': 'mxp_ajax_mysqldump_large',
                        'database': dump_dbname,
                        'dump_file_name': dump_file_name,
                        'dump_file_path': dump_file_path,
                        'step': 2,
                        'nonce': MXP.nonce,
                    }
                };
                $.ajaxQueue.addRequest(ajax_options);
            }
        });
        var wait_lock = 0;
        var batch_break = 0; //還沒處理被中斷的情況
        var batch_list = [];
        var zip_file_name = '';
        var zip_file_path = '';
        $('.pack_wp_content_batch_mode').click(function() {
            if (MXP.background_process != '0' && wait_lock == 0) {
                if (confirm('背景打包執行中，如要繼續將會中斷前次操作，確定嗎？') == false) {
                    return;
                }
            }
            $('.cleanup_mxpdev').prop('disabled', true);
            var self = this;
            $('button').prop('disabled', true);
            $(self).text('準備中...');
            if (wait_lock == 0) {
                var ajax_options = {
                    method: "POST",
                    dataType: "json",
                    url: MXP.ajaxurl,
                    cache: false,
                    error: function(res) {
                        console.log('Error', res);
                    },
                    success: function(res) {
                        if (res.success) {
                            console.log('success', res.msg);
                            wait_lock += 1;
                            $(self).prop('disabled', false);
                            $(self).trigger('click');
                        } else {
                            console.log('error', res);
                            $(self).text(res.msg);
                        }
                    },
                    data: {
                        'action': 'mxp_background_pack_batch_mode',
                        'step': 0,
                        'nonce': $(this).data('nonce'),
                        'path': $(this).data('path'),
                        'type': 'folder',
                        'context': 'wp-content',
                        'exclude_path': $(this).data('exclude_path')
                    }
                };
                $.ajaxQueue.addRequest(ajax_options);
            }
            if (wait_lock == 1) {
                var ajax_options = {
                    method: "POST",
                    dataType: "json",
                    url: MXP.ajaxurl,
                    cache: false,
                    error: function(res) {
                        console.log('Error', res);
                    },
                    success: function(res) {
                        if (res.success) {
                            console.log('success', res.msg);
                            wait_lock += 1;
                            batch_list = res.data['option_keys'];
                            console.log(batch_list);
                            zip_file_name = res.data['zip_file_name'];
                            zip_file_path = res.data['zip_file_path'];
                            $(self).text('準備就緒，點此開始打包檔案');
                            $(self).prop('disabled', false);
                            $(self).trigger('click');
                        } else {
                            console.log('error', res);
                            $(self).text(res.msg);
                            $(self).prop('disabled', false);
                            $(self).trigger('click');
                        }
                    },
                    data: {
                        'action': 'mxp_background_pack_batch_mode',
                        'step': 1,
                        'nonce': $(this).data('nonce'),
                        'path': $(this).data('path'),
                        'type': 'folder',
                        'context': 'wp-content',
                        'exclude_path': $(this).data('exclude_path')
                    }
                };
                $.ajaxQueue.addRequest(ajax_options);
            }
            if (wait_lock == 2) {
                for (var i = 0; i < batch_list.length; i++) {
                    if (batch_list[i]) {
                        option_key = batch_list[i];
                    }
                    var ajax_options = {
                        method: "POST",
                        dataType: "json",
                        url: MXP.ajaxurl,
                        cache: false,
                        error: function(res) {
                            console.log('Error', res);
                        },
                        success: function(res) {
                            if (res.success) {
                                console.log('success', res.msg);
                                if (res.data[0] == res.data[1]) {
                                    $(self).prop('disabled', false);
                                    $(self).text('點此下載檔案');
                                    wait_lock += 1;
                                    $(self).trigger('click');
                                } else {
                                    $(self).prop('disabled', true);
                                    $(self).text('壓縮進度：' + Math.round((res.data[0] / res.data[1]) * 100) + '% （請勿關閉此頁面）');
                                }
                            } else {
                                console.log('success', res);
                                $(self).text(res.msg);
                            }
                        },
                        data: {
                            'action': 'mxp_background_pack_batch_mode',
                            'step': 2,
                            'zip_file_name': zip_file_name,
                            'zip_file_path': zip_file_path,
                            'option_key': option_key,
                            'nonce': $(this).data('nonce'),
                            'path': $(this).data('path'),
                            'type': 'folder',
                            'context': 'wp-content',
                            'exclude_path': $(this).data('exclude_path')
                        }
                    };
                    $.ajaxQueue.addRequest(ajax_options);
                }
            }
            if (wait_lock == 3) {
                var ajax_options = {
                    method: "POST",
                    dataType: "json",
                    url: MXP.ajaxurl,
                    cache: false,
                    error: function(res) {
                        console.log('Error', res);
                    },
                    success: function(res) {
                        console.log('Success', res);
                        $(self).text('下載中！( ' + res.data['filesize'] + ' MB)');
                        location.href = res.data['download_link'];
                    },
                    data: {
                        'action': 'mxp_background_pack_batch_mode',
                        'step': 3,
                        'zip_file_name': zip_file_name,
                        'zip_file_path': zip_file_path,
                        'nonce': $(this).data('nonce'),
                        'path': $(this).data('path'),
                        'type': 'folder',
                        'context': 'wp-content',
                        'exclude_path': $(this).data('exclude_path')
                    }
                };
                $.ajaxQueue.addRequest(ajax_options);
            }
        });
        $('.pack_wp_content').click(function() {
            if (MXP.background_process != '0' && wait_lock == 0) {
                if (confirm('背景打包執行中，如要繼續將會中斷前次操作，確定嗎？') == false) {
                    return;
                }
            }
            $('.cleanup_mxpdev').prop('disabled', true);
            var self = this;
            $('button').prop('disabled', true);
            $(self).text('準備中...');
            if (wait_lock == 0) {
                var ajax_options = {
                    method: "POST",
                    dataType: "json",
                    url: MXP.ajaxurl,
                    cache: false,
                    error: function(res) {
                        console.log('Error', res);
                    },
                    success: function(res) {
                        if (res.success) {
                            console.log('success', res.msg);
                            wait_lock += 1;
                            $(self).prop('disabled', false);
                            $(self).trigger('click');
                        } else {
                            console.log('error', res);
                            $(self).text(res.msg);
                        }
                    },
                    data: {
                        'action': 'mxp_background_pack',
                        'step': 0,
                        'nonce': $(this).data('nonce'),
                        'path': $(this).data('path'),
                        'type': 'folder',
                        'context': 'wp-content',
                        'exclude_path': $(this).data('exclude_path')
                    }
                };
                $.ajaxQueue.addRequest(ajax_options);
            }
            if (wait_lock == 1) {
                var ajax_options = {
                    method: "POST",
                    dataType: "json",
                    url: MXP.ajaxurl,
                    cache: false,
                    error: function(res) {
                        console.log('Error', res);
                    },
                    success: function(res) {
                        if (res.success) {
                            wait_lock += 1; //離開當前這步驟狀態
                            if (res.data.status == 'download') {
                                $(self).text('下載中！( ' + res.data['filesize'] + ' MB)');
                                location.href = res.data['download_link'];
                            } else {
                                $(self).text(res.msg);
                            }
                            // $(self).prop('disabled', false);
                            // $(self).trigger('click');
                        } else {
                            console.log('error', res);
                            $(self).prop('disabled', false);
                            $(self).trigger('click');
                            if (res.data.status == 'addfile') {
                                var files = res.data.file_paths === undefined ? [] : res.data.file_paths;
                                for (var i = files.length - 1; i >= 0; i--) {
                                    var item = files[i][1] === undefined ? '...' : files[i][1];
                                    console.log('打包中', item);
                                    $(self).text('打包檔案(' + item + ')');
                                }
                            } else if (res.data.status == 'finish') {
                                const now = new Date();
                                const currentMinute = String(now.getMinutes()).padStart(2, '0');
                                const currentSecond = String(now.getSeconds()).padStart(2, '0');
                                const currentHour = String(now.getHours()).padStart(2, '0');
                                const period = now.getHours() >= 12 ? 'PM' : 'AM';
                                $(self).text('(' + currentHour + ':' + currentMinute + ':' + currentSecond + ' ' + period + ') 準備下載連結中...');
                            } else {
                                $(self).text(res.msg);
                            }
                        }
                    },
                    data: {
                        'action': 'mxp_background_pack',
                        'step': 1,
                        'nonce': $(this).data('nonce'),
                        'path': $(this).data('path'),
                        'type': 'folder',
                        'context': 'wp-content',
                        'exclude_path': $(this).data('exclude_path')
                    }
                };
                $.ajaxQueue.addRequest(ajax_options);
            }
        });
        $('.cleanup_mxpdev').click(function() {
            if (MXP.background_process != '0') {
                if (confirm('背景打包執行中，如要繼續將會中斷所有操作，確定嗎？') == false) {
                    return;
                }
            }
            var self = this;
            $(self).prop('disabled', true);
            var ajax_options = {
                method: "POST",
                dataType: "json",
                url: MXP.ajaxurl,
                cache: false,
                error: function(res) {
                    console.log('Error', res);
                },
                success: function(res) {
                    console.log('Success', res);
                    if (res.success) {
                        $(self).text(res.msg);
                    } else {
                        $(self).text(res.msg);
                        $(self).prop('disabled', false);
                    }
                },
                data: {
                    'action': 'mxp_ajax_clean_mxpdev',
                    'nonce': MXP.nonce,
                }
            };
            $.ajaxQueue.addRequest(ajax_options);
        });
    });
})(jQuery);