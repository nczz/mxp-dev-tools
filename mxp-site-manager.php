<?php
/**
 * Plugin Name: Dev Tools: Site Manager - Mxp.TW
 * Plugin URI: https://tw.wordpress.org/plugins/mxp-dev-tools/
 * Description: 管理多個 WordPress 站點的工具。
 * Version: 2.9.9.7
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
if (!defined('MDT_SITEMANAGER_DISPLAY')) {
    if (defined('MDT_DISALLOW_FILE_MODS') && MDT_DISALLOW_FILE_MODS == true) {
        define('MDT_SITEMANAGER_DISPLAY', false);
    } else {
        define('MDT_SITEMANAGER_DISPLAY', true);
    }
}

class MDTSiteManager {
    public function __construct() {
        // 註冊程式碼片段的勾點
        $this->add_hooks();
    }

    public function add_hooks() {
        add_filter('plugin_action_links', array($this, 'modify_action_link'), 11, 4);
        add_action('pre_current_active_plugins', array($this, 'plugin_display_none'));
        add_filter('site_transient_update_plugins', array($this, 'disable_this_plugin_updates'));
        // 新增「設定」中的外掛選單
        if (MDT_SITEMANAGER_DISPLAY && is_super_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
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
        $h         = array('mxp-dev-tools/mxp-site-manager.php');
        $myplugins = $wp_list_table->items;
        foreach ($myplugins as $key => $val) {
            if (in_array($key, $h) && !MDT_SITEMANAGER_DISPLAY) {
                unset($wp_list_table->items[$key]);
            }
        }
    }

    public function admin_menu() {
        add_options_page(
            'Site Manager',
            'Site Manager',
            'manage_options',
            'mxp-site-manager',
            array(
                $this,
                'settings_page',
            )
        );
    }

    /**
     * Settings page display callback.
     */
    public function settings_page() {
        echo date('Y-m-d H:i:s', self::get_current_time());
    }

    public function disable_this_plugin_updates($value) {
        $pluginsNotUpdatable = [
            'mxp-dev-tools/mxp-site-manager.php',
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

    public static function get_current_time_via_http() {
        $response = wp_remote_get('http://google.com',
            array(
                'timeout'     => 3,
                'redirection' => 0,
                'httpversion' => '1.1',
            )
        );
        if (!is_wp_error($response)) {
            $header = wp_remote_retrieve_headers($response);
            if (isset($header['date'])) {
                return array('status' => 200, 'success' => true, 'msg' => '', 'data' => strtotime($header['date']));
            } else {
                return array('status' => 500, 'success' => false, 'msg' => 'Header not found.');
            }
        } else {
            $error_message = $response->get_error_message();
            return array('status' => 500, 'success' => false, 'msg' => $error_message);
        }
    }

    public static function get_current_time_via_ntp() {
        if (!function_exists('socket_create') || !function_exists('socket_strerror') || !function_exists('socket_last_error') || !function_exists('socket_sendto') || !function_exists('socket_strerror') || !function_exists('socket_recvfrom') || !function_exists('socket_close')) {
            return array('status' => 500, 'success' => false, 'msg' => 'socket method not found.');
        }
        $ntpServer = 'time.google.com';
        $ntpPort   = 123; // NTP伺服器的端口號
        // NTP Packet結構
        $ntpPacket = "\x1b" . str_repeat("\0", 47); // 設定NTP Header
        // 建立UDP Socket連接
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            // 無法建立Socket連接
            return array('status' => 500, 'success' => false, 'msg' => socket_strerror(socket_last_error()));
        }
        // 發送NTP請求
        if (!socket_sendto($socket, $ntpPacket, strlen($ntpPacket), 0, $ntpServer, $ntpPort)) {
            // 發送失敗
            return array('status' => 500, 'success' => false, 'msg' => socket_strerror(socket_last_error()));
        }
        // 接收回應
        $fromNtpServer = '';
        socket_recvfrom($socket, $fromNtpServer, 1024, 0, $ntpServer, $ntpPort);
        socket_close($socket);
        // 分析NTP時間
        $timestamp = ord($fromNtpServer[40]) * pow(2, 24) +
        ord($fromNtpServer[41]) * pow(2, 16) +
        ord($fromNtpServer[42]) * pow(2, 8) +
        ord($fromNtpServer[43]) - 2208988800;
        return array('status' => 200, 'success' => true, 'msg' => '', 'data' => $timestamp);
    }

    public static function get_current_time() {
        $current = self::get_current_time_via_ntp();
        if ($current['success']) {
            return $current['data'];
        } else {
            $current = self::get_current_time_via_http();
            if ($current['success']) {
                return $current['data'];
            } else {
                return time();
            }
        }
    }

    public static function activated() {

    }

    public static function deactivated() {

    }
}

$mxp_site_manager = new MDTSiteManager();
// register_activation_hook(__FILE__, array($mxp_site_manager, 'activated'));
// register_deactivation_hook(__FILE__, array($mxp_site_manager, 'deactivated'));
