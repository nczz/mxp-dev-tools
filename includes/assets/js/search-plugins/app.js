document.mxp_install_plugins = MXP.install_plugins;
jQuery(document).ready(function() {
    jQuery('#search_iframe').html('<iframe id="mxp-plugins-search" src="https://live.mxp.tw/wp-plugins/" width="1200" height="768"></iframe>');
    jQuery('#mxp-plugins-search').load(function() {
        document.getElementById('mxp-plugins-search').contentWindow.postMessage({
            key: 'mxp_dev_tools',
            value: MXP.install_plugins
        }, '*');
    });
});
var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
var eventer = window[eventMethod];
var messageEvent = eventMethod === "attachEvent" ? "onmessage" : "message";
eventer(messageEvent, function(e) {
    // if (e.origin !== "*") return;
    //if (e.data === "myevent" || e.message === "myevent")
    // console.log("input", e);
    if (e.data.slug !== undefined) {
        if (document.mxp_install_plugins.includes(e.data.slug)) {
            alert('已安裝過此外掛。');
            return;
        }
        var data = {
            "action": "mxp_install_plugin_from_url",
            "nonce": MXP.nonce,
            "dlink": e.data.download_link,
            "slug": e.data.slug,
            "name": e.data.name
        };
        if (document.mxp_install_lock == 1) {
            alert('外掛安裝中，請稍候！');
            return;
        }
        document.mxp_install_lock = 1;
        jQuery.post(MXP.ajaxurl, data, function(res) {
            console.log(res);
            document.mxp_install_lock = 0;
            if (res.success !== undefined && res.success == true) {
                document.mxp_install_plugins.push(e.data.slug);
                document.getElementById('mxp-plugins-search').contentWindow.postMessage({
                    key: 'mxp_dev_tools',
                    value: MXP.install_plugins
                }, '*');
                document.getElementById('mxp-plugins-search').contentWindow.postMessage({
                    key: 'install_successfully',
                    value: e.data.slug
                }, '*');
                alert('安裝成功！');
            } else if (res.success == false) {
                alert('安裝失敗： CODE: ' + res.data.errorCode + '\n' + 'MSG: ' + res.data.errorMessage);
                document.getElementById('mxp-plugins-search').contentWindow.postMessage({
                    key: 'active_button',
                    value: e.data.slug
                }, '*');
            }
        });
    }
});