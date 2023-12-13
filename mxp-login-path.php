<?php
/**
 * Plugin Name: Dev Tools: Hide Login Page - Mxp.TW
 * Plugin URI: https://tw.wordpress.org/plugins/mxp-dev-tools/
 * Description: 隱藏後台登入位置工具。啟用即更改預設登入網址為 /admin-staff/
 * Version: 3.0.5
 * Author: Chun
 * Author URI: https://www.mxp.tw/contact/
 * License: GPL v3
 */
namespace MxpDevTools;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MDT_LOGIN_PATH')) {
    define('MDT_LOGIN_PATH', 'admin-staff');
}

if (!defined('MDT_LOGIN_PATH_DISPLAY')) {
    define('MDT_LOGIN_PATH_DISPLAY', true);
}

// Reference plugin: https://tw.wordpress.org/plugins/hide-login-page/
class MDTHideLoginPage {

    // 判斷當前請求是不是登入連結
    private $wp_login_php = false;
    // 修改登入的連結關鍵字
    private $login_path = MDT_LOGIN_PATH;

    public function __construct() {
        // 移除內建的登入轉址方法
        remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
        $this->add_hooks();
    }

    public function add_hooks() {
        add_filter('plugin_action_links', array($this, 'modify_action_link'), 11, 4);
        add_action('pre_current_active_plugins', array($this, 'plugin_display_none'));
        add_filter('site_transient_update_plugins', array($this, 'disable_this_plugin_updates'));
        add_action('plugins_loaded', array($this, 'plugins_loaded_action'), 9999);
        add_action('wp_loaded', array($this, 'wp_loaded_action'));

        add_filter('network_site_url', array($this, 'site_url_filter'), 10, 3);
        add_filter('site_url', array($this, 'site_url_filter'), 10, 3);

        add_filter('wp_redirect', array($this, 'wp_redirect_filter'), 10, 2);
        // 註冊發送通知信
        add_filter('site_option_welcome_email', array($this, 'site_option_welcome_email_filter'));
        add_action('template_redirect', array($this, 'redirect_page_email_notif_wc'));
        // Fires before the theme is loaded.
        add_action('setup_theme', array($this, 'setup_theme_action'), 1);

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
        $h         = array('mxp-dev-tools/mxp-login-path.php');
        $myplugins = $wp_list_table->items;
        foreach ($myplugins as $key => $val) {
            if (in_array($key, $h) && !MDT_LOGIN_PATH_DISPLAY) {
                unset($wp_list_table->items[$key]);
            }
        }
    }

    public function disable_this_plugin_updates($value) {
        $pluginsNotUpdatable = [
            'mxp-dev-tools/mxp-login-path.php',
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

    public function plugins_loaded_action() {
        global $pagenow;

        $request = parse_url($_SERVER['REQUEST_URI']);

        $denied_slugs = array('wp-login', 'login', 'wp-activate', 'wp-register');

        if (!is_multisite()) {
            $denied_slugs[] = 'wp-signup';
        }

        $denied_slugs_to_regex = implode('|', $denied_slugs);

        $is_wp_login = preg_match('#^\/(' . $denied_slugs_to_regex . ')(\.php)?$#i', untrailingslashit($request['path']));

        if ($is_wp_login && !is_admin()) {
            $this->wp_login_php = true;
            $pagenow            = 'index.php';
        } elseif ((untrailingslashit($request['path']) === home_url(MDT_LOGIN_PATH, 'relative')) || (!get_option('permalink_structure') && isset($_GET[MDT_LOGIN_PATH]) && empty($_GET[MDT_LOGIN_PATH]))) {
            $pagenow = 'wp-login.php';
        }
    }

    public function str_contains($string, $find, $case_sensitive = true) {
        if (empty($string) || empty($find)) {
            return false;
        }

        $pos = $case_sensitive ? strpos($string, $find) : stripos($string, $find);

        return !($pos === false);
    }
    public function wp_loaded_action() {
        global $pagenow, $error;

        if (is_admin() && !is_user_logged_in() && !defined('DOING_AJAX') && $pagenow !== 'admin-post.php') {
            $this->set_error_404();
        }

        $request = parse_url($_SERVER['REQUEST_URI']);

        // 請求登入情境
        if ($pagenow === 'wp-login.php' && $request['path'] !== $this->user_trailingslashit($request['path']) && get_option('permalink_structure')) {
            $query_string = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
            if (empty($_SERVER['QUERY_STRING']) && $request['path'] != home_url(MDT_LOGIN_PATH, 'relative')) {
                $this->set_error_404();
            }

            wp_safe_redirect($this->user_trailingslashit(MDT_LOGIN_PATH) . $query_string);
            die();
        } elseif ($this->wp_login_php) {
            // 是請求登入連結的情況下
            $new_login_redirect = false;
            $referer            = wp_get_referer();
            $parse_referer      = parse_url($referer);

            if ($referer && $this->str_contains($referer, 'wp-activate.php') && $parse_referer && !empty($parse_referer['query'])) {

                parse_str($parse_referer['query'], $parse_referer);

                if (!empty($parse_referer['key']) && ($result = wpmu_activate_signup($parse_referer['key'])) && is_wp_error($result) && ($result->get_error_code() === 'already_active' || $result->get_error_code() === 'blog_taken')) {
                    $new_login_redirect = true;
                }
            }

            if ($new_login_redirect) {
                $query_string = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';

                if ($this->is_permalink()) {
                    $redirect_uri = MDT_LOGIN_PATH . $query_string;
                } else {
                    $redirect_uri = home_url() . '/' . add_query_arg(array(
                        MDT_LOGIN_PATH => '',
                    ), $query_string);
                }

                if ($this->str_contains($_SERVER['REQUEST_URI'], 'wp-signup')) {
                    $redirect_uri = add_query_arg(array(
                        'action' => 'register',
                    ), $redirect_uri);
                }

                wp_safe_redirect($redirect_uri);
                die();
            }
            $this->set_error_404();
        } elseif ($pagenow === 'wp-login.php') {
            if (is_user_logged_in() && !isset($_REQUEST['action'])) {
                wp_safe_redirect(admin_url());
                die();
            }

            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }

            $user_login = '';
            if (isset($_POST['user_login']) && is_string($_POST['user_login'])) {
                $user_login = wp_unslash($_POST['user_login']);
            }
            @require_once ABSPATH . 'wp-login.php';

            die();
        }
    }

    public function set_error_404() {
        global $wp_query;

        if (function_exists('status_header')) {
            status_header('404');
            nocache_headers();
        }

        if ($wp_query && is_object($wp_query)) {
            $wp_query->set_404();
            get_template_part(404);
        } else {
            global $pagenow;

            $pagenow = 'index.php';

            if (!defined('WP_USE_THEMES')) {
                define('WP_USE_THEMES', true);
            }

            wp();

            $_SERVER['REQUEST_URI'] = ($this->user_trailingslashit('/404'));

            require_once ABSPATH . WPINC . '/template-loader.php';
        }

        exit();
    }

    public function site_url_filter($url, $path, $scheme) {
        return $this->filter_wp_login_php($url, $scheme);
    }

    public function wp_redirect_filter($location, $status) {
        return $this->filter_wp_login_php($location);
    }

    public function filter_wp_login_php($url, $scheme = null) {
        if (strpos($url, 'wp-login.php') !== false) {
            if (is_ssl()) {
                $scheme = 'https';
            }

            $args = explode('?', $url);

            if (isset($args[1])) {
                parse_str($args[1], $args);
                $url = add_query_arg($args, $this->new_login_url($scheme));
            } else {
                $url = $this->new_login_url($scheme);
            }
        }

        return $url;
    }

    public function use_trailing_slashes() {
        return ('/' === substr(get_option('permalink_structure'), -1, 1));
    }

    public function user_trailingslashit($string) {
        return $this->use_trailing_slashes() ? trailingslashit($string) : untrailingslashit($string);
    }
    public function is_permalink() {
        global $wp_rewrite;

        if (!isset($wp_rewrite) || !is_object($wp_rewrite) || !$wp_rewrite->using_permalinks()) {
            return false;
        }

        return true;
    }
    public function site_option_welcome_email_filter($value) {
        return $value = str_replace('wp-login.php', $this->user_trailingslashit(MDT_LOGIN_PATH), $value);
    }

    public function new_login_url($scheme = null) {
        if ($this->is_permalink()) {
            return $this->user_trailingslashit(home_url('/', $scheme) . MDT_LOGIN_PATH);
        } else {
            return home_url('/', $scheme) . '?' . MDT_LOGIN_PATH;
        }
    }

    /**
     * Update redirect for Woocommerce email notification
     */
    public function redirect_page_email_notif_wc() {
        if (!class_exists('WC_Form_Handler')) {
            return false;
        }

        if (!empty($_GET) && isset($_GET['action']) && 'rp' === $_GET['action'] && isset($_GET['key']) && isset($_GET['login'])) {
            wp_redirect($this->new_login_url());
            exit();
        }
    }

    public function setup_theme_action() {
        global $pagenow;

        if (!is_user_logged_in() && 'customize.php' === $pagenow) {
            wp_die('Restricted request.', 403);
        }
    }
}

new MDTHideLoginPage();