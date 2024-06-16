<?php
namespace MxpDevTools;

if (!defined('WPINC')) {
    die;
}

trait PluginsList {

    public function modify_action_link($actions, $plugin_file, $plugin_data, $context) {
        if (strpos($plugin_file, 'mxp-dev-tools') === 0 && isset($actions['delete'])) {
            unset($actions['delete']);
            return $actions;
        } else {
            return $actions;
        }
    }

    public function mxp_add_plugin_download_link($actions, $plugin_file, $plugin_data, $context) {
        if (!is_super_admin()) {
            return $actions;
        }
        $new_actions = array();
        if (strpos($plugin_file, '/') !== false) {
            $type = 'folder';
        } else {
            $type = 'file';
        }
        $path = '';
        switch ($context) {
        case 'mustuse':
            $path = str_replace('/', DIRECTORY_SEPARATOR, WPMU_PLUGIN_DIR . '/' . $plugin_file);
            break;
        case 'dropins':
            $path = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR . '/' . $plugin_file);
            break;
        case 'all':
        case 'active':
        case 'active':
        case 'inactive':
        case 'recently_activated':
        case 'auto-update-disabled':
        case 'upgrade':
        default:
            $path = str_replace('/', DIRECTORY_SEPARATOR, WP_PLUGIN_DIR . '/' . $plugin_file);
            break;
        }
        if ($path != '') {
            $mxp_download_action_link = admin_url('admin-ajax.php?action=mxp_current_plugin_download&path=' . base64_encode($path) . '&type=' . $type . '&context=' . $context);
            $mxp_download_action_link = add_query_arg('_wpnonce', wp_create_nonce('mxp-download-current-plugins-' . base64_encode($path)), $mxp_download_action_link);
            $download_link            = '<a target="_blank" href="' . esc_url($mxp_download_action_link) . '" class="mxp_plugin_download_link">打包外掛</a>';

            $new_actions['mxp-donwload-current-plugin'] = $download_link;
        }
        return array_merge($new_actions, $actions);
    }

    public function mxp_ajax_install_plugin() {
        $nonce = sanitize_text_field(isset($_POST['nonce']) == true ? $_POST['nonce'] : "");
        if (!wp_verify_nonce($nonce, 'mxp-ajax-nonce-for-plugin-list') && !wp_verify_nonce($nonce, 'mxp-ajax-nonce-for-themeforest-list')) {
            wp_send_json_error(array('status' => false, 'data' => array('msg' => '錯誤的請求來源')));
        }
        $activated = sanitize_text_field(isset($_POST['activated']) == true ? $_POST['activated'] : "");
        $file      = sanitize_text_field(isset($_POST['file']) == true ? $_POST['file'] : "");
        $dlink     = isset($_POST['dlink']) == true ? $_POST['dlink'] : "";
        $slug      = sanitize_text_field(isset($_POST['slug']) == true ? $_POST['slug'] : "");
        $update    = sanitize_text_field(isset($_POST['update']) == true ? $_POST['update'] : "");
        $version   = sanitize_text_field(isset($_POST['version']) == true ? $_POST['version'] : "");
        $name      = sanitize_text_field(isset($_POST['name']) == true ? $_POST['name'] : "");
        if ($activated == "" || $dlink == "" || $slug == "" || $version == "" || $name == "") {
            wp_send_json_error(array('status' => false, 'data' => array('msg' => '錯誤的請求資料')));
        }
        if (empty($update) && ($activated === 'true' || $file != 'false')) {
            wp_send_json_error(array('status' => false, 'data' => array('msg' => '已經安裝')));
        }
        if (!wp_is_file_mod_allowed('mxp_ajax_install_plugin')) {
            wp_send_json_error(array('status' => false, 'data' => array('msg' => '系統禁止檔案操作')));
        }
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        //code reference from wp-admin/includes/ajax-actions.php
        $skin = new \WP_Ajax_Upgrader_Skin();
        $args = array();
        if (!empty($update)) {
            $args = array('overwrite_package' => true);
        }
        $upgrader = new \Plugin_Upgrader($skin);
        $result   = $upgrader->install($dlink, $args);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $status['debug'] = $skin->get_upgrade_messages();
        }
        if (is_wp_error($result)) {
            $status['errorCode']    = $result->get_error_code();
            $status['errorMessage'] = $result->get_error_message();
            wp_send_json_error($status);
        } elseif (is_wp_error($skin->result)) {
            $status['errorCode']    = $skin->result->get_error_code();
            $status['errorMessage'] = $skin->result->get_error_message();
            wp_send_json_error($status);
        } elseif ($skin->get_errors()->get_error_code()) {
            $status['errorMessage'] = $skin->get_error_messages();
            wp_send_json_error($status);
        } elseif (is_null($result)) {
            global $wp_filesystem;
            $status['errorCode']    = 'unable_to_connect_to_filesystem';
            $status['errorMessage'] = __('Unable to connect to the filesystem. Please confirm your credentials.');
            // Pass through the error from WP_Filesystem if one was raised.
            if ($wp_filesystem instanceof WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code()) {
                $status['errorMessage'] = esc_html($wp_filesystem->errors->get_error_message());
            }
            wp_send_json_error($status);
        }
        if (!function_exists('install_plugin_install_status')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }
        $pluginInfo     = install_plugin_install_status(array('name' => $name, 'slug' => $slug, 'version' => $version));
        $status['info'] = json_encode($pluginInfo);
        wp_send_json_success($status);
    }

    public function mxp_ajax_install_plugin_from_url() {
        if (!wp_is_file_mod_allowed('mxp_ajax_install_plugin_from_url')) {
            wp_send_json_error(array('status' => false, 'data' => array('msg' => '系統禁止檔案操作')));
        }
        $nonce = sanitize_text_field(isset($_POST['nonce']) == true ? $_POST['nonce'] : "");
        if (!wp_verify_nonce($nonce, 'mxp-ajax-nonce-for-search-plugins')) {
            wp_send_json_error(array('status' => false, 'data' => array('msg' => '錯誤的請求來源')));
        }

        $dlink = isset($_POST['dlink']) == true ? $_POST['dlink'] : "";
        if ($dlink == "") {
            wp_send_json_error(array('status' => false, 'data' => array('msg' => '錯誤，無帶入下載連結')));
        }
        $slug = sanitize_text_field(isset($_POST['slug']) == true ? $_POST['slug'] : "");
        $name = sanitize_text_field(isset($_POST['name']) == true ? $_POST['name'] : "");
        if ($slug == "" || $name == "") {
            wp_send_json_error(array('status' => false, 'data' => array('msg' => '錯誤的外掛資料')));
        }
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        //code reference from wp-admin/includes/ajax-actions.php
        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result   = $upgrader->install($dlink);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $status['debug'] = $skin->get_upgrade_messages();
        }
        if (is_wp_error($result)) {
            $status['errorCode']    = $result->get_error_code();
            $status['errorMessage'] = $result->get_error_message();
            wp_send_json_error($status);
        } elseif (is_wp_error($skin->result)) {
            $status['errorCode']    = $skin->result->get_error_code();
            $status['errorMessage'] = $skin->result->get_error_message();
            wp_send_json_error($status);
        } elseif ($skin->get_errors()->get_error_code()) {
            $status['errorMessage'] = $skin->get_error_messages();
            wp_send_json_error($status);
        } elseif (is_null($result)) {
            global $wp_filesystem;
            $status['errorCode']    = 'unable_to_connect_to_filesystem';
            $status['errorMessage'] = __('Unable to connect to the filesystem. Please confirm your credentials.');
            // Pass through the error from WP_Filesystem if one was raised.
            if ($wp_filesystem instanceof WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code()) {
                $status['errorMessage'] = esc_html($wp_filesystem->errors->get_error_message());
            }
            wp_send_json_error($status);
        }
        if (!function_exists('install_plugin_install_status')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }
        $pluginInfo     = install_plugin_install_status(array('name' => $name, 'slug' => $slug, 'version' => '0.1'));
        $status['info'] = $pluginInfo;
        if (isset($_POST['active']) && $_POST['active'] == 1) {
            $result = activate_plugin($pluginInfo['file']);
            if (is_wp_error($result)) {
                wp_send_json_error($result);
            }
        }
        wp_send_json_success($status);
    }

    public function mxp_ajax_activate_plugin() {
        $nonce = sanitize_text_field(isset($_POST['nonce']) == true ? $_POST['nonce'] : "");
        if (!wp_verify_nonce($nonce, 'mxp-ajax-nonce-for-plugin-list') && !wp_verify_nonce($nonce, 'mxp-ajax-nonce-for-themeforest-list')) {
            wp_send_json_error(array('activated' => false, 'data' => array('msg' => '錯誤的請求')));
        }
        $name = sanitize_text_field(isset($_POST['name']) == true ? $_POST['name'] : "");
        $file = isset($_POST['file']) ? sanitize_text_field(isset($_POST['file']) == true ? $_POST['file'] : "") : $this->get_plugin_file($name);
        if (!isset($file)) {
            wp_send_json_error(array('activated' => false, 'data' => array('msg' => '找不到啟動來源')));
        }
        activate_plugins($file);
        if (is_plugin_active($file)) {
            wp_send_json_success(array('activated' => true));
        } else {
            wp_send_json_error(array('activated' => false));
        }
    }

    public function mxp_ajax_install_theme() {
        if (!wp_is_file_mod_allowed('mxp_ajax_install_theme')) {
            wp_send_json_error(array('status' => false, 'data' => array('msg' => '系統禁止檔案操作')));
        }
        $nonce = sanitize_text_field(isset($_POST['nonce']) == true ? $_POST['nonce'] : "");
        $dlink = isset($_POST['dlink']) == true ? $_POST['dlink'] : "";
        if (!wp_verify_nonce($nonce, 'mxp-ajax-nonce-for-themeforest-list') || $dlink == "") {
            wp_send_json_error(array('data' => array('msg' => '錯誤的請求')));
        }
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        //code reference from wp-admin/includes/ajax-actions.php
        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Theme_Upgrader($skin);
        $result   = $upgrader->install($dlink);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $status['debug'] = $skin->get_upgrade_messages();
        }
        if (is_wp_error($result)) {
            $status['errorCode']    = $result->get_error_code();
            $status['errorMessage'] = $result->get_error_message();
            wp_send_json_error($status);
        } elseif (is_wp_error($skin->result)) {
            $status['errorCode']    = $skin->result->get_error_code();
            $status['errorMessage'] = $skin->result->get_error_message();
            wp_send_json_error($status);
        } elseif ($skin->get_errors()->get_error_code()) {
            $status['errorMessage'] = $skin->get_error_messages();
            wp_send_json_error($status);
        } elseif (is_null($result)) {
            global $wp_filesystem;
            $status['errorCode']    = 'unable_to_connect_to_filesystem';
            $status['errorMessage'] = __('Unable to connect to the filesystem. Please confirm your credentials.');
            // Pass through the error from WP_Filesystem if one was raised.
            if ($wp_filesystem instanceof WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code()) {
                $status['errorMessage'] = esc_html($wp_filesystem->errors->get_error_message());
            }
            wp_send_json_error($status);
        }
        wp_send_json_success($status);
    }

    public function mxp_ajax_current_plugin_download_action() {
        $check_zip_module = (class_exists('ZipArchive') && method_exists('ZipArchive', 'open')) ? true : false;
        if (!$check_zip_module) {
            die('未安裝/啟用 PHP ZIP 模組，無法呼叫 ZipArchive 方法打包。');
        }
        if (!is_super_admin()) {
            exit('此功能僅限網站最高權限管理人員使用。');
        }
        $path         = sanitize_text_field($_GET['path']);
        $type         = sanitize_text_field($_GET['type']);
        $context      = sanitize_text_field($_GET['context']);
        $exclude_path = isset($_GET['exclude_path']) ? sanitize_text_field($_GET['exclude_path']) : '';
        if (empty($path) || empty($type) || !in_array($type, array('file', 'folder'), true) || empty($context) || !wp_verify_nonce($_GET['_wpnonce'], 'mxp-download-current-plugins-' . $path)) {
            exit('驗證請求失敗，請再試一次。');
        }
        $path = base64_decode($path, true);
        if ($path == false) {
            exit('路徑驗證請求失敗，請再試一次。');
        }
        if (!empty($exclude_path)) {
            $exclude_path = base64_decode($exclude_path, true);
            if ($exclude_path == false) {
                exit('排除路徑驗證請求失敗，請再試一次。');
            }
        }
        $zip           = new \ZipArchive();
        $zip_file_name = '';
        $zip_file_path = '';
        if ($type == 'file') {
            if (!file_exists($path)) {
                exit($path . ' 檔案不存在。');
            }
            $zip_file_name = basename($path) . '.zip';
            $tmp_dir       = rtrim(get_temp_dir(), DIRECTORY_SEPARATOR);
            if ((defined('MDT_TMP_DIR') && MDT_TMP_DIR != 'TMP') || !is_writable($tmp_dir)) {
                $tmp_dir = ABSPATH . DIRECTORY_SEPARATOR . "wp-content" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "MXPDEV";
                if (!file_exists($tmp_dir) && !is_dir($tmp_dir)) {
                    mkdir($tmp_dir, 0777, true);
                }
            }
            $zip_file_path = $tmp_dir . DIRECTORY_SEPARATOR . $zip_file_name;
            $zip->open($zip_file_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if (!$zip) {
                exit('ZIP 壓縮程式執行錯誤');
            }
            $zip->addFromString(basename($path), file_get_contents($path));
        } else {
            if (!file_exists(dirname($path))) {
                exit(dirname($path) . ' 路徑不存在。');
            }
            $split_path    = explode(DIRECTORY_SEPARATOR, $path);
            $zip_file_name = $split_path[count($split_path) - 2] . '.zip';
            $relative_path = realpath(dirname($path) . '/..'); //for support php5.3 up | dirname($path, 2) php7.0 up;
            $tmp_dir       = rtrim(get_temp_dir(), DIRECTORY_SEPARATOR);
            if ((defined('MDT_TMP_DIR') && MDT_TMP_DIR != 'TMP') || !is_writable($tmp_dir)) {
                $tmp_dir = ABSPATH . DIRECTORY_SEPARATOR . "wp-content" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "MXPDEV";
                if (!file_exists($tmp_dir) && !is_dir($tmp_dir)) {
                    mkdir($tmp_dir, 0777, true);
                }
            }
            $zip_file_path = $tmp_dir . DIRECTORY_SEPARATOR . $zip_file_name;
            $zip->open($zip_file_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if (!$zip) {
                exit('ZIP 壓縮程式執行錯誤');
            }
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(dirname($path)),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $file_path = $file->getRealPath();
                    $add_flag  = true;
                    if ($exclude_path != '' && strpos($file_path, $exclude_path) !== false) {
                        $add_flag = false;
                    }
                    $zip_relative_path = str_replace($relative_path . DIRECTORY_SEPARATOR, '', $file_path);
                    if ($add_flag) {
                        $zip->addFile($file_path, str_replace(DIRECTORY_SEPARATOR, '/', $zip_relative_path));
                    }
                }
            }
        }
        $zip->close();
        if ($zip_file_path != '' && file_exists($zip_file_path)) {
            ob_clean();
            header('Content-Description: File Transfer');
            // header('Content-Type: application/octet-stream');
            header('Content-type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_file_name . '"');
            header('Expires: 0');
            header('Cache-Control: no-cache');
            header('Pragma: public');
            header('Content-Length: ' . filesize($zip_file_path));
            header('Set-Cookie:fileLoading=true');
            header("Pragma: no-cache");
            // readfile($zip_file_path);
            echo file_get_contents($zip_file_path);
            unlink($zip_file_path);
            exit;
        }
    }

    public function get_plugin_file($plugin_name) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        foreach ($plugins as $plugin_file => $plugin_info) {
            if ($plugin_info['Name'] == $plugin_name) {
                return $plugin_file;
            }
        }
        return null;
    }

    public function themeforest_page_cb() {

        $this->page_wraper('Themeforest <a href="https://goo.gl/Oh9cK5" target="_blank">授權碼</a>', function () {
            echo '<form name="themeforest" method="get"><input type="hidden" name="page" value="mxp-themeforest-list"><input type="text" name="code" value="" size="40"/><input type="submit" value="送出" class="button action"/></form>';
        });

        if (isset($_GET['code']) && !empty($_GET['code'])) {
            $auth_code = sanitize_text_field($_GET['code']);
            $response  = '';
            $mxp_flag  = false;
            if (strpos($auth_code, 'MXP_') === 0) {
                $mxp_flag = true;
                $response = wp_remote_get('https://api.undo.im/wp-json/mxp_knockers/v1/app/list-purchases', array(
                    'headers' => array('Authorization' => 'Bearer ' . $auth_code),
                ));
            } else {
                $response = wp_remote_get($this->themeforest_api_base_url . '/market/buyer/list-purchases', array(
                    'headers' => array('Authorization' => 'Bearer ' . $auth_code),
                ));
            }
            if (is_array($response) && !is_wp_error($response) && $response['response']['code'] == 200) {
                $resp = json_decode($response['body'], true);
                if ($resp && !isset($resp['error']) && $resp['count'] != '0') {
                    $datas   = $resp['results'];
                    $themes  = [];
                    $plugins = [];
                    $others  = [];
                    for ($i = 0; $i < count($datas); ++$i) {
                        $dlinks = array();
                        if (!$mxp_flag) {
                            $dobj = wp_remote_get($this->themeforest_api_base_url . '/market/buyer/download?item_id=' . $datas[$i]['item']['id'], array(
                                'headers' => array('Authorization' => 'Bearer ' . $auth_code),
                            ));
                            if (is_array($dobj) && !is_wp_error($dobj) && $dobj['response']['code'] == 200) {
                                $dlinks = json_decode($dobj['body'], true);
                                if (!$dlinks) {
                                    echo '<br/>發生錯誤，請回報下列錯誤資訊至：im@mxp.tw<br/><br/><pre>' . esc_html(print_r($dobj, true)) . '</pre>';
                                    wp_die();
                                }
                            } else {
                                echo '<br/>發生錯誤，請回報下列錯誤資訊至：im@mxp.tw<br/><br/><pre>' . esc_html(print_r($dobj, true)) . '</pre>';
                                wp_die();
                            }
                        } else {
                            $dlinks['wordpress_plugin'] = $datas[$i]['item']['wordpress_plugin_metadata']['dlink'];
                        }
                        if (isset($datas[$i]['item']['wordpress_theme_metadata'])) {
                            $tid                                                    = $datas[$i]['item']['id'];
                            $datas[$i]['item']['wordpress_theme_metadata']['id']    = esc_attr($tid);
                            $datas[$i]['item']['wordpress_theme_metadata']['dlink'] = esc_url($dlinks['wordpress_theme']);
                            $datas[$i]['item']['wordpress_theme_metadata']['code']  = $datas[$i]['code'];
                            $themes[]                                               = $datas[$i]['item']['wordpress_theme_metadata'];
                        } else if (isset($datas[$i]['item']['wordpress_plugin_metadata'])) {
                            $tid                                                     = $datas[$i]['item']['id'];
                            $datas[$i]['item']['wordpress_plugin_metadata']['id']    = esc_attr($tid);
                            $datas[$i]['item']['wordpress_plugin_metadata']['dlink'] = esc_url($dlinks['wordpress_plugin']);
                            $datas[$i]['item']['wordpress_plugin_metadata']['code']  = $datas[$i]['code'];
                            $plugins[]                                               = $datas[$i]['item']['wordpress_plugin_metadata'];

                        } else {
                            $datas[$i]['item']['dlink'] = esc_url($dlinks['download_url']);
                            $datas[$i]['item']['code']  = $datas[$i]['code'];
                            $others[]                   = $datas[$i]['item'];
                        }
                    } //end for-loop
                    echo '<h1>主題</h1><br/><table style="text-align:center;"><tr><th>操作</th><th>名稱</th><th>版本</th><th>購買序號</th></tr>';
                    for ($i = 0; $i < count($themes); ++$i) {
                        echo "<tr><td><button class='install_theme button' data-dlink='{$themes[$i]['dlink']}' data-id='{$themes[$i]['id']}'>下載＆安裝</button><button style='display:none;' class='activate_theme button' data-id='{$themes[$i]['id']}'>前往主題頁啟動</button></td><td>" . esc_html($themes[$i]['theme_name']) . "</td><td>" . esc_html($themes[$i]['version']) . "</td><td>" . esc_html($themes[$i]['code']) . "</td>";
                    }
                    echo '</table>';
                    echo '<h1>外掛</h1><br/><table style="text-align:center;"><tr><th>操作</th><th>名稱</th><th>版本</th><th>購買序號</th></tr>';
                    for ($i = 0; $i < count($plugins); ++$i) {
                        $pname = esc_attr($plugins[$i]['plugin_name']);
                        echo "<tr><td><button class='install_plugin button' data-name='{$pname}' data-dlink='{$plugins[$i]['dlink']}' data-id='{$plugins[$i]['id']}'>下載＆安裝</button><button style='display:none;' class='activate_plugin button' data-name='{$pname}' data-dlink='{$plugins[$i]['dlink']}' data-id='{$plugins[$i]['id']}'>啟動</button></td><td>" . esc_html($plugins[$i]['plugin_name']) . "</td><td>" . esc_html($plugins[$i]['version']) . "</td><td>" . esc_html($plugins[$i]['code']) . "</td>";
                    }
                    echo '</table>';
                    echo '<h1>其他（未分類）</h1><br/><table style="text-align:center;"><tr><th>操作</th><th>名稱</th><th>版本</th><th>購買序號</th></tr>';
                    for ($i = 0; $i < count($others); ++$i) {
                        $oname = esc_html($others[$i]['name']);
                        echo "<tr><td><button class='install_other button' data-dlink='{$others[$i]['dlink']}' data-id='{$others[$i]['id']}'>下載手動安裝</button></td><td>{$oname}</td><td>NONE</td><td>" . $others[$i]['code'] . "</td>";
                    }
                    echo '</table>';
                    wp_localize_script($this->plugin_slug . '-themeforest-list', 'Mxp_AJAX', array(
                        'ajaxurl'   => admin_url('admin-ajax.php'),
                        'themesurl' => admin_url('themes.php'),
                        'nonce'     => wp_create_nonce('mxp-ajax-nonce-for-themeforest-list'),
                    ));
                    wp_enqueue_script($this->plugin_slug . '-themeforest-list');
                } else {
                    echo '<br/>若非無購買項目，請回報下列錯誤資訊至：im@mxp.tw<br/><br/><pre>' . print_r($response, true) . '</pre>';
                    wp_die();
                }
            } else {
                echo '<br/>若非授權碼錯誤或無購買項目，請回報下列錯誤資訊至：im@mxp.tw<br/><br/><pre>' . print_r($response, true) . '</pre>';
                wp_die();
            }
        } else {
            echo '<p>請輸入授權碼！</p>';
        }
    }
}