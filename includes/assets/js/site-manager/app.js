(function($) {
    // 組合顯示網站資訊的表格
    function generateTableFromJSON(data) {
        if (data === '' || data === []) {
            return '';
        }
        let html = '<table id="mxp_table" border="1">';
        const customLabels = {
            "site_url": "Site URL",
            "site_name": "Site Name",
            "admin_email": "Admin Email",
            "ipv4": "IPv4",
            "ipv6": "IPv6",
            "DNS_A": "A Record",
            "DNS_NS": "NS Record",
            "whois": "網域到期時間",
            "action": "操作",
        };
        const keys = Object.keys(customLabels);
        html += '<thead><tr>';
        keys.forEach(key => {
            html += `<th>${customLabels[key]}</th>`;
        });
        html += '</tr></thead><tbody>';
        // Generating table rows
        for (const key in data) {
            html += '<tr>';
            const item = data[key];
            keys.forEach(tableKey => {
                var str = item[tableKey];
                if (tableKey == 'whois') {
                    var expiration = '';
                    if (item['whois'] && item['whois']['data'] !== undefined) {
                        expiration = item['whois']['data']['expiration'] !== undefined ? item['whois']['data']['expiration'] : '';
                    }
                    if (expiration != '') {
                        var date = new Date(expiration * 1000);
                        var year = date.getFullYear();
                        var month = ('0' + (date.getMonth() + 1)).slice(-2); // 月份是從0開始的，需要加1，並補零
                        var day = ('0' + date.getDate()).slice(-2); // 補零
                        str = year + '-' + month + '-' + day;
                    }
                }
                if (tableKey == 'DNS_A') {
                    str = '';
                    var dnsa = item['dns_record']['DNS_A'] !== undefined ? item['dns_record']['DNS_A'] : '';
                    if (dnsa != '') {
                        for (var i = dnsa.length - 1; i >= 0; i--) {
                            str += dnsa[i].ip + ', ';
                        }
                    }
                }
                if (tableKey == 'DNS_NS') {
                    str = '';
                    var dnsa = item['dns_record']['DNS_NS'] !== undefined ? item['dns_record']['DNS_NS'] : '';
                    if (dnsa != '') {
                        for (var i = dnsa.length - 1; i >= 0; i--) {
                            str += dnsa[i].target + ', ';
                        }
                    }
                }
                if (tableKey == 'action') {
                    str = '<button type="button" class="button login" data-site="' + key + '">登入</button> | ';
                    str += '<button type="button" class="button delete" data-site="' + key + '">刪除</button>';
                }
                if (tableKey == 'whois') {
                    var expiration = '';
                    if (item['whois'] && item['whois']['data'] !== undefined) {
                        expiration = item['whois']['data']['expiration'] !== undefined ? item['whois']['data']['expiration'] : '';
                    }
                    if (expiration != '') {
                        html += `<td data-order="${expiration}">${str}</td>`;
                    } else {
                        html += `<td>${str}</td>`;
                    }
                } else if (tableKey == 'site_url') {
                    html += `<td data-search="${key}">${str}</td>`;
                } else {
                    html += `<td>${str}</td>`;
                }
            });
            html += '</tr>';
        }
        html += '</tbody></table>';
        return html;
    }

    function del_site() {
        var self = this;
        var site_key = $(self).data('site');
        if (!confirm('你是否確認要刪除 ' + site_key + ' 的設定資料？')) {
            return;
        }
        if (site_key == '') {
            alert('請求有誤。');
            return;
        }
        var data = {
            'action': 'mxp_ajax_site_mamager',
            'nonce': MXP.nonce,
            'data': site_key,
            'method': 'delete'
        };
        $.post(MXP.ajaxurl, data, function(res) {
            if (res.code == 200) {
                alert('刪除成功！');
                location.reload();
            } else {
                alert(res.msg);
            }
        });
    }

    function login_site() {
        var self = this;
        var site_key = $(self).data('site');
        if (site_key == '') {
            alert('請求有誤。');
            return;
        }
        var data = {
            'action': 'mxp_ajax_site_mamager',
            'nonce': MXP.nonce,
            'data': site_key,
            'method': 'login'
        };
        $.post(MXP.ajaxurl, data, function(res) {
            if (res.code == 200) {
                console.log(res);
                submit_login_form(res.data.mdt_access_token, res.data.hmac, res.data.target_url);
            } else {
                alert(res.msg);
            }
        });
    }
    // 複製匯出資料
    function copyTextToClipboard(text) {
        var input = document.createElement('input');
        input.setAttribute('type', 'text');
        input.setAttribute('value', text);
        input.style.position = 'absolute';
        input.style.left = '-9999px'; // 移到屏幕外使其不可見
        // 將 input 元素加到 body 中
        document.body.appendChild(input);
        // 選取 input 中的內容並複製到剪貼簿
        input.select();
        input.setSelectionRange(0, 99999);
        document.execCommand('copy');
        try {
            navigator.clipboard.writeText(text);
            console.log('Content copied to clipboard');
        } catch (err) {
            console.error('Failed to copy: ', err);
        }
        // 移除 input 元素
        document.body.removeChild(input);
        prompt("複製到剪貼簿：", text);
    }
    // 組合送出的表單
    function submit_login_form(mdt_access_token, hmac, target_url) {
        var form = document.createElement('form');
        form.setAttribute('method', 'POST');
        if (target_url.charAt(target_url.length - 1) !== '/') {
            target_url += '/';
        }
        form.setAttribute('action', target_url);
        form.setAttribute('target', '_blank');
        var input1 = document.createElement('input');
        input1.setAttribute('type', 'hidden');
        input1.setAttribute('name', 'mdt_access_token');
        input1.setAttribute('value', mdt_access_token);
        form.appendChild(input1);
        var input2 = document.createElement('input');
        input2.setAttribute('type', 'hidden');
        input2.setAttribute('name', 'hmac');
        input2.setAttribute('value', hmac);
        form.appendChild(input2);
        document.body.appendChild(form);
        form.submit();
        // 等待表單提交完成後，將表單從 DOM 中移除
        form.addEventListener('submit', function() {
            document.body.removeChild(form);
        });
    }
    $(document).ready(function() {
        console.log(MXP.all_site_info);
        const tableContainer = document.getElementById('site_table');
        if (tableContainer) {
            const tableHTML = generateTableFromJSON(MXP.all_site_info);
            tableContainer.innerHTML = tableHTML;
            if (tableHTML != '') {
                $('#mxp_table').DataTable({
                    "paging": false,
                    "ordering": true,
                    "order": [
                        [7, 'asc']
                    ],
                    "drawCallback": function(settings) {
                        console.log('Table draw!');
                        $('.delete').off('click').on('click', del_site);
                        $('.login').off('click').on('click', login_site);
                    },
                });
            }
        } else {
            console.log('Table container not found.');
        }
        // 重置網站當前的密鑰
        $('#reset_site_passkey').click(function() {
            if (!confirm('你是否確認要重置本站的設定資料？')) {
                return;
            }
            var data = {
                'action': 'mxp_ajax_site_mamager',
                'nonce': MXP.nonce,
                'data': 'current',
                'method': 'reset'
            };
            $.post(MXP.ajaxurl, data, function(res) {
                if (res.code == 200) {
                    alert('重置成功！');
                    location.reload();
                } else {
                    alert(res.msg);
                }
            });
        });
        // 匯入其它網站資訊
        $('#import_site').click(function() {
            let site_info = prompt("請填入網站匯出資料：");
            if (site_info == '' || site_info == null) {
                alert('匯入資料不得為空。');
                return;
            }
            var data = {
                'action': 'mxp_ajax_site_mamager',
                'nonce': MXP.nonce,
                'data': site_info,
                'method': 'import'
            };
            $.post(MXP.ajaxurl, data, function(res) {
                if (res.code == 200) {
                    alert('匯入成功！');
                    location.reload();
                } else {
                    alert(res.msg);
                }
            });
        });
        // 匯出當前網站資訊
        $('#export_site').click(function() {
            var data = {
                'action': 'mxp_ajax_site_mamager',
                'nonce': MXP.nonce,
                'data': 'current',
                'method': 'export'
            };
            $.post(MXP.ajaxurl, data, function(res) {
                if (res.code == 200) {
                    copyTextToClipboard(res.data);
                } else {
                    alert(res.msg);
                }
            });
        });
    });
})(jQuery);