<?php
/**
 * Plugin Name: Dev Tools: Snippets - Mxp.TW
 * Plugin URI: https://tw.wordpress.org/plugins/mxp-dev-tools/
 * Description: 整合 GitHub 中常用的程式碼片段。請注意，並非所有網站都適用全部的選項，有進階需求可以透過設定 wp-config.php 中此外掛預設常數，啟用或停用部分功能。
 * Version: 2.9.2
 * Author: Chun
 * Author URI: https://www.mxp.tw/contact/
 * License: GPL v3
 */
namespace MxpDevTools;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
// 是否顯示此外掛於外掛清單上
if (!defined('MDT_SNIPPETS_DISPLAY')) {
    define('MDT_SNIPPETS_DISPLAY', true);
}
// 接收網站發生錯誤時的通知信收件人
if (!defined('MDT_RECOVERY_MODE_EMAIL')) {
    define('MDT_RECOVERY_MODE_EMAIL', get_option('admin_email'));
}
// 影像大小限制，預設 500kb
if (!defined('MDT_IMAGE_SIZE_LIMIT')) {
    define('MDT_IMAGE_SIZE_LIMIT', 500);
}
// 預設不刪除 xmlrpc.php 檔案
if (!defined('MDT_DELETE_XMLRPC_PHP')) {
    define('MDT_DELETE_XMLRPC_PHP', false);
}
// 預設刪除 install.php 檔案
if (!defined('MDT_DELETE_INSTALL_PHP')) {
    define('MDT_DELETE_INSTALL_PHP', true);
}
// 停用縮圖機制
if (!defined('MDT_DISABLE_IMAGE_SIZE')) {
    define('MDT_DISABLE_IMAGE_SIZE', true);
}
// 上傳圖片補上 meta
if (!defined('MDT_ADD_IMAGE_CONTENT')) {
    define('MDT_ADD_IMAGE_CONTENT', true);
}
// 留言隱藏留言人網址
if (!defined('MDT_HIDE_COMMENT_URL')) {
    define('MDT_HIDE_COMMENT_URL', true);
}
// 停用自己 ping 自己網站的功能
if (!defined('MDT_DISABLE_SELF_PING')) {
    define('MDT_DISABLE_SELF_PING', true);
}
// 停用 xmlrpc.php 功能
if (!defined('MDT_XMLRPC_DISABLE')) {
    define('MDT_XMLRPC_DISABLE', true);
}
// 停用 REST API 首頁顯示 API 功能
if (!defined('MDT_DISABLE_REST_INDEX')) {
    define('MDT_DISABLE_REST_INDEX', true);
}
// 停用沒授權的存取 REST API Users API 功能
if (!defined('MDT_DISABLE_NO_AUTH_ACCESS_REST_USER')) {
    define('MDT_DISABLE_NO_AUTH_ACCESS_REST_USER', true);
}
// 啟用安全性 HTTP 標頭功能
if (!defined('MDT_ENABLE_SECURITY_HEADERS')) {
    define('MDT_ENABLE_SECURITY_HEADERS', true);
}
// 隱藏前端作者連結
if (!defined('MDT_HIDE_AUTHOR_LINK')) {
    define('MDT_HIDE_AUTHOR_LINK', true);
}
// 隱藏前端作者名稱
if (!defined('MDT_HIDE_AUTHOR_NAME')) {
    define('MDT_HIDE_AUTHOR_NAME', true);
}
// 隱藏前端作者名稱的預設顯示名
if (!defined('MDT_AUTHOR_DISPLAY_NAME')) {
    define('MDT_AUTHOR_DISPLAY_NAME', '小編');
}
// 關閉全球大頭貼功能
if (!defined('MDT_DISABLE_AVATAR')) {
    define('MDT_DISABLE_AVATAR', true);
}
// 最佳化主題相關功能
if (!defined('MDT_ENABLE_OPTIMIZE_THEME')) {
    define('MDT_ENABLE_OPTIMIZE_THEME', true);
}
// 關閉網站狀態工具功能
if (!defined('MDT_DISABLE_SITE_HEALTH')) {
    define('MDT_DISABLE_SITE_HEALTH', false);
}
// 預設不啟用全部信件轉寄功能
if (!defined('MDT_OVERWRITE_EMAIL')) {
    define('MDT_OVERWRITE_EMAIL', false);
}
// 全部信件轉寄給指定信箱
if (!defined('MDT_OVERWRITE_EMAIL_RECEIVER')) {
    define('MDT_OVERWRITE_EMAIL_RECEIVER', '');
}

class MDTSnippets {
    public function __construct() {
        // 註冊程式碼片段的勾點
        $this->add_hooks();
    }

    public function add_hooks() {
        add_filter('plugin_action_links', array($this, 'modify_action_link'), 11, 4);
        // 隱藏 Freemius 的擾人通知
        if (class_exists('Freemius')) {
            remove_all_actions('admin_notices');
            remove_all_actions('network_admin_notices');
            remove_all_actions('all_admin_notices');
            remove_all_actions('user_admin_notices');
        }
        add_action('pre_current_active_plugins', array($this, 'plugin_display_none'));
        add_filter('site_transient_update_plugins', array($this, 'disable_this_plugin_updates'));
        if (MDT_ENABLE_OPTIMIZE_THEME) {
            // 主題相關最佳化
            add_action('after_setup_theme', array($this, 'optimize_theme_setup'));
        }
        if (MDT_DISABLE_IMAGE_SIZE) {
            // 取消縮圖機制
            add_filter('wp_get_attachment_metadata', array($this, 'remove_attachment_metadata_size'), 11, 2);
            add_filter('intermediate_image_sizes', '__return_empty_array');
            add_filter('intermediate_image_sizes_advanced', '__return_empty_array');
            add_filter('fallback_intermediate_image_sizes', '__return_empty_array');
            // 禁用 WC 背景縮圖功能
            add_filter('woocommerce_background_image_regeneration', '__return_false');
        }
        // 取消預設 2560 寬高限制
        add_filter('big_image_size_threshold', '__return_false');
        if (MDT_ADD_IMAGE_CONTENT) {
            // 上傳檔案時判斷為圖片時自動加上標題、替代標題、摘要、描述等內容
            add_action('add_attachment', array($this, 'set_image_meta_upon_image_upload'));
        }
        // 修改「網站遭遇技術性問題」通知信收件人
        add_filter('recovery_mode_email', array($this, 'change_recovery_mode_email'), 11, 2);
        // 去除有管理權限之外人的通知訊息
        add_action('admin_head', array($this, 'hide_update_msg_non_admins'), 1);
        // 開啟隱私權頁面修改權限
        add_filter('map_meta_cap', array($this, 'add_privacy_page_edit_cap'), 10, 4);
        // Contact Form 7 強化功能
        add_action('admin_init', array($this, 'cf7_optimize'));
        // 刪除文章前的防呆提醒機制
        add_action('admin_footer', array($this, 'delete_post_confirm_action'));
        // 限制上傳大小以及轉正JPG影像（如果有EXIF資訊的話）https://www.mxp.tw/9318/
        add_filter('wp_handle_upload_prefilter', array($this, 'image_size_and_image_orientation'));
        // 預設不直接更新大版本，僅安全性版本更新
        add_filter('allow_major_auto_core_updates', '__return_false');
        // 每次更新完後檢查 xmlrpc.php | install.php 還在不在，在就砍掉，避免後患
        add_action('upgrader_process_complete', array($this, 'check_files_after_upgrade_action'), 10, 2);
        if (MDT_HIDE_COMMENT_URL) {
            // 遮蔽所有留言中留言人提供的網址
            add_filter('get_comment_author_url', array($this, 'hide_comment_author_url'), 11, 3);
        }
        // 使用者登入後轉址回指定位置
        add_action('template_redirect', array($this, 'redirect_to_after_login'), -1);
        if (MDT_DISABLE_SELF_PING) {
            // 停用自己站內的引用
            add_action('pre_ping', array($this, 'disable_self_ping'), 11, 3);
        }
        if (MDT_XMLRPC_DISABLE) {
            //預設關閉 XML_RPC
            add_filter('xmlrpc_enabled', '__return_false');
        }
        if (MDT_ENABLE_SECURITY_HEADERS) {
            // 輸出 X-Frame-Options HTTP Header
            add_action('send_headers', 'send_frame_options_header', 10, 0);
            // 輸出安全性的 HTTP 標頭
            add_filter('wp_headers', array($this, 'add_security_headers'));
        }
        // 關閉 HTTP Header 中出現的 Links
        add_filter('oembed_discovery_links', '__return_null');
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('template_redirect', 'rest_output_link_header', 11);
        remove_action('template_redirect', 'wp_shortlink_header', 11);
        if (MDT_DISABLE_REST_INDEX) {
            // 關閉 wp-json 首頁顯示的 API 清單
            add_filter('rest_index', '__return_empty_array');
        }
        // 沒登入的使用者都無法呼叫 wp/users 這隻 API。不建議完全封鎖掉，會導致有些後台功能運作失靈
        if (function_exists('is_user_logged_in') && !is_user_logged_in() && MDT_DISABLE_NO_AUTH_ACCESS_REST_USER) {
            add_filter('rest_user_query', '__return_empty_array');
            add_filter('rest_prepare_user', '__return_empty_array');
        }
        if (MDT_HIDE_AUTHOR_LINK) {
            // 預設前端作者的連結都不顯示
            add_filter('author_link', array($this, 'hide_author_link'), 3, 100);
        }
        if (MDT_HIDE_AUTHOR_NAME) {
            add_filter('the_author_posts_link', array($this, 'hide_author_name'), 11, 1);
        }
        if (MDT_DISABLE_AVATAR) {
            // 取消站內的全球大頭貼功能，全改為預設大頭貼
            add_filter('pre_get_avatar_data', array($this, 'empty_avatar_data'), PHP_INT_MAX, 2);
        }
        // 內對外請求管制方法
        add_filter("pre_http_request", array($this, "block_external_request"), 11, 3);
        if (MDT_DISABLE_SITE_HEALTH) {
            // 關閉 site health 檢測功能
            add_filter('site_status_tests', '__return_empty_array', 100, 1);
            add_filter('debug_information', '__return_empty_array', 100, 1);
        }
        if (MDT_OVERWRITE_EMAIL) {
            // 全部信件轉寄功能
            add_filter('wp_mail', array($this, 'overwrite_wp_mail_receiver'), 11, 1);
        }
    }

    public function modify_action_link($actions, $plugin_file, $plugin_data, $context) {
        if (strpos($plugin_file, 'mxp-dev-tools') === 0 && isset($actions['delete'])) {
            unset($actions['delete']);
            return $actions;
        } else {
            return $actions;
        }
    }

    public function plugin_display_none() {
        global $wp_list_table;
        $h         = array('mxp-dev-tools/mxp-snippets.php');
        $myplugins = $wp_list_table->items;
        foreach ($myplugins as $key => $val) {
            if (in_array($key, $h) && !MDT_SNIPPETS_DISPLAY) {
                unset($wp_list_table->items[$key]);
            }
        }
    }

    public function disable_this_plugin_updates($value) {
        $pluginsNotUpdatable = [
            'mxp-dev-tools/mxp-snippets.php',
        ];
        if (isset($value) && is_object($value)) {
            foreach ($pluginsNotUpdatable as $plugin) {
                if (isset($value->response[$plugin])) {
                    unset($value->response[$plugin]);
                }
            }
        }
        return $value;
    }

    //最佳化主題樣式相關
    public function optimize_theme_setup() {
        //整理head資訊
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        add_filter('the_generator', '__return_false');
        //管理員等級的角色不要隱藏 admin bar
        $user          = wp_get_current_user();
        $allowed_roles = array('editor', 'administrator', 'author');
        if (!array_intersect($allowed_roles, $user->roles)) {
            add_filter('show_admin_bar', '__return_false');
        }
        remove_action('wp_head', 'feed_links_extra', 3);

        add_filter('style_loader_src', array($this, 'remove_version_query'), 999);
        add_filter('script_loader_src', array($this, 'remove_version_query'), 999);
        add_filter('widget_text', 'do_shortcode');
        // 讓主題支援使用 WC 的方法
        if (class_exists('WooCommerce')) {
            add_theme_support('woocommerce');
            add_theme_support('wc-product-gallery-zoom');
            add_theme_support('wc-product-gallery-lightbox');
            add_theme_support('wc-product-gallery-slider');
        }
    }

    //移除css, js資源載入時的版本資訊
    public function remove_version_query($src) {
        if (strpos($src, 'ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }
    //阻止縮圖浪費空間
    public function remove_attachment_metadata_size($data, $attachment_id) {
        if (isset($data['sizes'])) {
            // 清空設定的大小，強迫輸出原圖
            $data['sizes'] = array();
        }
        return $data;
    }
    //上傳檔案時判斷為圖片時自動加上標題、替代標題、摘要、描述等內容
    public function set_image_meta_upon_image_upload($post_ID) {

        if (wp_attachment_is_image($post_ID)) {
            $my_image_title = get_post($post_ID)->post_title;
            $my_image_title = preg_replace('%\s*[-_\s]+\s*%', ' ', $my_image_title);
            $my_image_title = ucwords(strtolower($my_image_title));
            $my_image_meta  = array(
                'ID'           => $post_ID,
                'post_title'   => $my_image_title,
                'post_excerpt' => $my_image_title,
                'post_content' => $my_image_title,
            );
            update_post_meta($post_ID, '_wp_attachment_image_alt', $my_image_title);
            wp_update_post($my_image_meta);
        }
    }
    //修改「網站遭遇技術性問題」通知信收件人
    public function change_recovery_mode_email($email, $url) {
        $email['to'] = MDT_RECOVERY_MODE_EMAIL; //收件人
        // $email['subject'] //主旨
        // $email['message'] //內文
        // $email['headers'] //信件標頭
        return $email;
    }
    // 去除有管理權限之外人的通知訊息
    public function hide_update_msg_non_admins() {
        $user = wp_get_current_user();
        if (!in_array('administrator', (array) $user->roles)) {
            // non-admin users
            echo '<style>#setting-error-tgmpa>.updated settings-error notice is-dismissible, .update-nag, .updated { display: none; }</style>';
        }
        // 隱藏非管理人員的更新通知
        if (!current_user_can('update_core')) {
            remove_action('admin_notices', 'update_nag', 3);
        }
    }
    // 開啟隱私權頁面修改權限
    public function add_privacy_page_edit_cap($caps, $cap, $user_id, $args) {
        if ('manage_privacy_options' === $cap) {
            $manage_name = is_multisite() ? 'manage_network' : 'manage_options';
            $caps        = array_diff($caps, [$manage_name]);
        }
        return $caps;
    }
    // Contact Form 7 強化功能
    public function cf7_optimize() {
        add_filter('wpcf7_form_elements', 'do_shortcode');
        add_filter('wpcf7_autop_or_not', '__return_false');
    }

    // 刪除文章前的防呆提醒機制
    public function delete_post_confirm_action() {
        ?>
    <script>
jQuery(document).ready(function(){
    jQuery(".submitdelete").click(function() {
        if (!confirm("確定要刪除嗎？")){
            return false;
        }
    });
    jQuery("#doaction").click(function(){
        var top_action = jQuery("#bulk-action-selector-top").val();
        if ("trash"==top_action){
            if (!confirm("確定要刪除嗎？")){
                return false;
            }
        }
    });
    jQuery("#doaction2").click(function(){
        var bottom_action = jQuery("#bulk-action-selector-bottom").val();
        if ("trash"==bottom_action){
            if (!confirm("確定要刪除嗎？")){
                return false;
            }
        }
    });
    jQuery("#delete_all").click(function(){
        if (!confirm("確定要清空嗎？此動作執行後無法回復。")){
            return false;
        }
    });
});
</script>
<?php
}
    // 限制上傳大小以及轉正JPG影像（如果有EXIF資訊的話）https://www.mxp.tw/9318/
    public function image_size_and_image_orientation($file) {
        $limit = MDT_IMAGE_SIZE_LIMIT; // 500kb 上限
        $size  = $file['size'] / 1024;
        if (!version_compare(get_bloginfo('version'), '5.3', '>=')) {
            // v5.3 後已經內建 https://developer.wordpress.org/reference/classes/wp_image_editor_imagick/maybe_exif_rotate/
            $this->apply_new_orientation($file['tmp_name']);
        }
        $is_image = strpos($file['type'], 'image') !== false;
        if ($is_image && $size > $limit) {
            $file['error'] = "上傳的影像大小請小於 {$limit}kb";
        }
        return $file;
    }

    public function apply_new_orientation($path_to_jpg) {
        // 使用 GD 函式庫，沒的話就算了不處理
        if (!extension_loaded('gd') ||
            !function_exists('gd_info') ||
            !function_exists('exif_imagetype') ||
            !function_exists('imagecreatefromjpeg') ||
            !function_exists('exif_read_data') ||
            !function_exists('imagerotate') ||
            !function_exists('imagejpeg') ||
            !function_exists('imagedestroy')) {
            return false;
        }
        if (exif_imagetype($path_to_jpg) == IMAGETYPE_JPEG) {
            $image = @imagecreatefromjpeg($path_to_jpg);
            $exif  = exif_read_data($path_to_jpg);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                case 3:
                    $image = imagerotate($image, 180, 0);
                    break;
                case 6:
                    $image = imagerotate($image, 90, 0);
                    break;
                case 8:
                    $image = imagerotate($image, -90, 0);
                    break;
                }
            }
            if ($image) {
                imagejpeg($image, $path_to_jpg);
                imagedestroy($image);
            }
        }
    }
    // 每次更新完後檢查 xmlrpc.php | install.php 還在不在，在就砍掉，避免後患
    public function check_files_after_upgrade_action($upgrader_object, $options) {
        // if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        //     foreach ($options['plugins'] as $each_plugin) {
        //         if ($each_plugin == $current_plugin_path_name) {
        //             // 比對當前外掛的更新
        //         }
        //     }
        // }
        if (file_exists(ABSPATH . 'xmlrpc.php') && MDT_DELETE_XMLRPC_PHP) {
            unlink(ABSPATH . 'xmlrpc.php');
        }
        if (file_exists(ABSPATH . 'wp-admin/install.php') && MDT_DELETE_INSTALL_PHP) {
            unlink(ABSPATH . 'wp-admin/install.php');
        }
    }
    // 遮蔽所有留言中留言人提供的網址提供的網址
    public function hide_comment_author_url($url, $id, $comment) {
        return "";
    }
    // 使用者登入後轉址回指定位置
    public function redirect_to_after_login() {
        if (!is_user_logged_in()) {
            $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : '';
            if (strpos($redirect_to, get_site_url()) === 0) {
                setcookie('mxp_redirect_to', $redirect_to);
                setcookie('mxp_redirect_to_count', 0);
            }
        } else {
            if (isset($_COOKIE['mxp_redirect_to']) && $_COOKIE['mxp_redirect_to'] != '' && isset($_COOKIE['mxp_redirect_to_count']) && $_COOKIE['mxp_redirect_to_count'] != 0) {
                setcookie("mxp_redirect_to", "", time() - 3600);
                setcookie('mxp_redirect_to_count', 1);
                wp_redirect($_COOKIE['mxp_redirect_to']);
                exit;
            }
        }
    }
    // 停用自己站內的引用
    public function disable_self_ping(&$post_links, &$pung, $post_id) {
        $home = get_option('home');
        foreach ($post_links as $l => $link) {
            if (0 === strpos($link, $home)) {
                unset($post_links[$l]);
            }
        }
    }
    // 輸出安全性的 HTTP 標頭
    public function add_security_headers($headers) {
        $headers['X-XSS-Protection']                  = '1; mode=block';
        $headers['X-Content-Type-Options']            = 'nosniff';
        $headers['X-Content-Security-Policy']         = "default-src 'self'; script-src 'self'; connect-src 'self'";
        $headers['X-Permitted-Cross-Domain-Policies'] = "none";
        $headers['Strict-Transport-Security']         = 'max-age=31536000; includeSubDomains; preload';
        return $headers;
    }
    // 預設作者的連結都不顯示
    public function hide_author_link($link, $author_id, $author_nicename) {
        return '#';
    }
    // 預設不顯示出系統輸出的作者連結與頁面，避免資安問題
    public function hide_author_name($link) {
        return MDT_AUTHOR_DISPLAY_NAME;
    }
    // 取消站內的全球大頭貼功能，全改為預設大頭貼
    public function empty_avatar_data($args, $id_or_email) {
        //email md5 wordpress.gravatar@mxp.tw
        if (is_ssl()) {
            $url = 'https://secure.gravatar.com/avatar/fcb68cd8e48d96e2bc306b17422e34ea?s=192&d=mm&r=g';
        } else {
            $url = 'http://0.gravatar.com/avatar/fcb68cd8e48d96e2bc306b17422e34ea?s=192&d=mm&r=g';
        }
        $args['url'] = $url;
        return $args;
    }
    // 內對外請求管制方法
    public function block_external_request($preempt, $parsed_args, $url) {
        $block_urls = apply_filters('mxp_dev_block_urls', array());
        $block_urls = array_map('strtolower', $block_urls);
        $allow_urls = apply_filters('mxp_dev_allow_urls', array(
            "api.wordpress.org",
            "downloads.wordpress.org",
        ));
        $allow_urls     = array_map('strtolower', $allow_urls);
        $localhost      = strtolower(parse_url(get_home_url(), PHP_URL_HOST));
        $allow_urls[]   = $localhost;
        $allow_urls[]   = 'localhost';
        $allow_urls[]   = '127.0.0.1';
        $request_domain = strtolower(parse_url($url, PHP_URL_HOST));
        if (count($block_urls) == 1 && $block_urls[0] == '*') {
            if (!in_array($request_domain, $allow_urls, true)) {
                return new \WP_Error('http_request_block', '不允許的對外請求路徑' . "\n:: {$url}", $url);
            }
        } else {
            if (in_array($request_domain, $block_urls, true) && !in_array($request_domain, $allow_urls, true)) {
                return new \WP_Error('http_request_block', '不允許的對外請求路徑' . "\n:: {$url}", $url);
            }
        }
        return $preempt;
    }
    // 給發信標題全都加上請勿回覆字樣
    public function overwrite_wp_mail_receiver($atts) {
        if (MDT_OVERWRITE_EMAIL_RECEIVER != '' && filter_var(MDT_OVERWRITE_EMAIL_RECEIVER, FILTER_VALIDATE_EMAIL)) {
            $atts['to'] = MDT_OVERWRITE_EMAIL_RECEIVER;
        }
        return $atts;
    }
}

new MDTSnippets();