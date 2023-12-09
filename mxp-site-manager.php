<?php
/**
 * Plugin Name: Dev Tools: Site Manager - Mxp.TW
 * Plugin URI: https://tw.wordpress.org/plugins/mxp-dev-tools/
 * Description: 管理多個 WordPress 站點的工具。
 * Version: 2.9.9.12
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

if (!defined('MDT_SITE_PASSKEY')) {
    define('MDT_SITE_PASSKEY', MDTSiteManager::activated());
}

// 紀錄在哪個欄位的名稱
if (!defined('MDT_SITES_INFO_KEY')) {
    define('MDT_SITES_INFO_KEY', 'mxp_dev_sites_info_db');
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

    public function generate_login_request_data($site_key = '') {
        if ($site_key == '') {
            return false;
        }
        $site_info = $this->get_site_info($site_key);
        if (empty($site_info)) {
            return false;
        }
        $data = array(
            'target_url'       => $site_info['site_url'],
            'hmac'             => '',
            'mdt_access_token' => '',
        );
        $passkey                  = $site_info['passkey'];
        $current_timestamp        = intval($this->get_current_time());
        $mdt_access_token         = self::encryp('MDT_SITE_LOGIN_REQUEST|' . $current_timestamp, $passkey);
        $hmac                     = bin2hex(hash_hmac('sha256', $mdt_access_token, $passkey, true));
        $data['mdt_access_token'] = $mdt_access_token;
        $data['hmac']             = $hmac;
        return $data;
    }

    // 驗證請求並給予登入
    public function verify_login_request() {
        if (!isset($_POST['mdt_access_token']) || $_POST['mdt_access_token'] == '' || !isset($_POST['hmac']) || $_POST['hmac'] == '') {
            return;
        }
        $mdt_access_token = sanitize_text_field($_POST['mdt_access_token']);
        $client_hmac      = sanitize_text_field($_POST['hmac']);
        $server_hmac      = hash_hmac('sha256', hex2bin($mdt_access_token), MDT_SITE_PASSKEY, true);
        if (!hash_equals($client_hmac, $server_hmac)) {
            return;
        }
        $decryp_msg = self::decryp($mdt_access_token);
        $msg_parts  = explode('|', $decryp_msg);
        if (count($msg_parts) != 2 || $msg_parts[0] != 'MDT_SITE_LOGIN_REQUEST' || is_numeric($msg_parts[1])) {
            return;
        }
        $timestamp         = intval($msg_parts[1]);
        $current_timestamp = intval(self::get_current_time());
        if (abs($current_timestamp - $timestamp) >= 15) {
            return;
        }
        // 以上驗證都過，就可以登入了！
        $user_ids = get_users(array('login__in' => get_super_admins(), 'fields' => 'ID'));
        if (count($user_ids) == 0) {
            return;
        }
        if (is_user_logged_in()) {
            wp_clear_auth_cookie();
        }
        wp_set_auth_cookie($user_ids[0], true, '', '');
        wp_set_current_user($user_ids[0]);
        wp_redirect(admin_url());
        exit;
    }

    // 匯出網站資訊
    public function get_current_site_info() {
        $site_url = get_site_url();
        $info     = array(
            'site_url'    => $site_url,
            'site_name'   => get_option('blogname'),
            'admin_email' => get_option('admin_email'),
            'ipv4'        => self::get_server_ipv4(),
            'ipv6'        => self::get_server_ipv6(),
            'dns_record'  => '',
            'whois'       => $this->get_whois($site_url),
        );
        $dns_record = array();
        if ($info['whois'] !== false && isset($info['whois']['data']['domain']) && $info['whois']['data']['domain'] != '' && isset($info['whois']['data']['registrar']) && $info['whois']['data']['registrar'] != 'localhost') {
            $dns_record['DNS_NS'] = dns_get_record($info['whois']['data']['domain'], DNS_NS);
            $domain               = strtolower(parse_url($site_url, PHP_URL_HOST));
            $dns_record['DNS_A']  = dns_get_record($domain, DNS_A);
            $info['dns_record']   = $dns_record;
        }

        return MDT_SITE_PASSKEY . '$@$' . self::encryp(json_encode($info));
    }

    // 不輸入 key 值就回傳全部資訊
    public function get_site_info($site_key = '') {
        $all_site_info = get_site_option(MDT_SITES_INFO_KEY, '');
        if ($site_key == '') {
            return $all_site_info == '' ? array() : $all_site_info;
        }
        if (!isset($all_site_info[$site_key])) {
            return array();
        }
        return $all_site_info[$site_key];
    }

    // 匯入網站資訊
    public function update_site_info($site = '') {
        $site_info = explode('$@$', $site);
        if (count($site_info) != 2) {
            return false;
        }
        $passkey = $site_info[0];
        $info    = json_decode(self::decryp($site_info[1], $passkey), true);
        if (json_last_error() !== JSON_ERROR_NONE || count($info) < 5) {
            return false;
        }
        $info_key        = parse_url($info['site_url'], PHP_URL_HOST);
        $info['passkey'] = $passkey;
        $all_site_info   = get_site_option(MDT_SITES_INFO_KEY, '');
        if ($all_site_info == '') {
            $data            = array();
            $data[$info_key] = $info;
            return update_site_option(MDT_SITES_INFO_KEY, $data);
        } else {
            $all_site_info[$info_key] = $info;
            return update_site_option(MDT_SITES_INFO_KEY, $all_site_info);
        }
    }

    public function delete_site_info($site_key = '') {
        $all_site_info = get_site_option(MDT_SITES_INFO_KEY, '');
        if ($site_key == '' || $all_site_info == '' || !isset($all_site_info[$site_key])) {
            return false;
        }
        unset($all_site_info[$site_key]);
        return update_site_option(MDT_SITES_INFO_KEY, $all_site_info);
    }

    public static function get_server_ipv4() {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // error was suppressed with the @-operator
            if (0 === error_reporting()) {
                return false;
            }
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        $ipv4 = 'NONE';
        if (function_exists('socket_create') && function_exists('socket_connect') && function_exists('socket_getsockname') && function_exists('socket_close')) {
            $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            try {
                socket_connect($sock, "8.8.8.8", 53);
                socket_getsockname($sock, $qname);
                socket_close($sock);
                $ipv4 = $qname;
            } catch (\Exception $ex) {
                $ipv4 = 'NONE';
            }
        }
        if (function_exists('fsockopen') && 'NONE' === $ipv4 && function_exists('stream_socket_get_name')) {
            try {
                $fp = fsockopen('tcp://8.8.8.8', 53, $errno, $errstr, 5);
                if (!$fp) {
                    $ipv4 = "NONE";
                } else {
                    $local_endpoint = stream_socket_get_name($fp, false); // 拿到本機請求的 socket 資源
                    $ip_parts       = explode(':', $local_endpoint);
                    $ipv4           = current($ip_parts);
                    fclose($fp);
                }
            } catch (\Exception $ex) {
                $ipv4 = 'NONE';
            }
        }
        restore_error_handler();
        return $ipv4;
    }

    public function get_whois($domain) {
        $args = array(
            'headers'   => array(
                'Authorization' => 'Bearer MXP_DEV:' . self::get_current_time(),
            ),
            'sslverify' => false,
            'timeout'   => 5,
        );
        $response = wp_remote_post('https://api.undo.im/wp-json/mxp_knockers/v1/app/whois?site_url=' . $domain, $args);
        if (!is_wp_error($response)) {
            if (200 == wp_remote_retrieve_response_code($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $body;
                } else {
                    return false;
                }
            }
        } else {
            $error_message = $response->get_error_message();
            return false;
        }
    }

    public static function get_server_ipv6() {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // error was suppressed with the @-operator
            if (0 === error_reporting()) {
                return false;
            }
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        $ipv6 = 'NONE';
        // 如果有 v4 那就來問問看 v6
        if (function_exists('socket_create') && function_exists('socket_connect') && function_exists('socket_getsockname') && function_exists('socket_close')) {
            $sock = socket_create(AF_INET6, SOCK_DGRAM, SOL_UDP);
            try {
                //cloudflare ipv6 dns
                socket_connect($sock, "2606:4700:4700::1111", 53);
                socket_getsockname($sock, $qname);
                socket_close($sock);
                $ipv6 = $qname;
            } catch (\Exception $ex) {
                $ipv6 = 'NONE';
            }
        }
        if (function_exists('fsockopen') && 'NONE' === $ipv6 && function_exists('stream_socket_get_name')) {
            try {
                $fp = fsockopen('tcp://[2606:4700:4700::1111]', 53, $errno, $errstr, 5);
                if (!$fp) {
                    $ipv6 = "NONE";
                } else {
                    $local_endpoint = stream_socket_get_name($fp, false); // 拿到本機請求的 socket 資源
                    if (preg_match('/\[(.*?)\]/', $local_endpoint, $matches)) {
                        $ipv6 = $matches[1];
                    }
                    fclose($fp);
                }
            } catch (\Exception $ex) {
                $ipv6 = 'NONE';
            }
        }
        restore_error_handler();
        return $ipv6;
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

    // 產生 32 bytes 的隨機密碼
    public static function generate_random_password() {
        $length = 32;
        return bin2hex(random_bytes($length));
    }

    // 加密
    public static function encryp($message, $password = MDT_SITE_PASSKEY) {
        // 使用 AES-256-CBC 加密算法加密訊息
        $iv        = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc')); // 產生初始化向量
        $encrypted = openssl_encrypt($message, 'aes-256-cbc', $password, 0, $iv); // 加密訊息
        $result    = base64_encode($encrypted . '::' . $iv); // 將加密結果和初始化向量以 base64 編碼返回
        return $result;
    }

    // 解密
    public static function decryp($message, $password = MDT_SITE_PASSKEY) {
        list($encryptedData, $iv) = explode('::', base64_decode($message), 2);
        $decrypted                = openssl_decrypt($encryptedData, 'aes-256-cbc', $password, 0, $iv);
        return $decrypted;
    }

    public function reset_site_passkey() {
        delete_site_option('mxd_dev_site_passkey');
    }

    public static function activated() {
        $site_passkey = get_site_option('mxd_dev_site_passkey', '');
        if ($site_passkey == '') {
            $site_passkey = self::generate_random_password();
            update_site_option('mxd_dev_site_passkey', $site_passkey);
        }
        return $site_passkey;
    }

    public static function deactivated() {

    }
}

$GLOBALS['mxp_site_manager'] = $mxp_site_manager = new MDTSiteManager();
// register_activation_hook(__FILE__, array($mxp_site_manager, 'activated'));
// register_deactivation_hook(__FILE__, array($mxp_site_manager, 'deactivated'));