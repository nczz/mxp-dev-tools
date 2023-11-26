<?php
/**
 * Plugin Name: Dev Tools - Mxp.TW
 * Plugin URI: https://goo.gl/2gLq18
 * Description: 一介資男の常用外掛整理與常用開發功能整合外掛。
 * Version: 2.9.9.8
 * Author: Chun
 * Author URI: https://www.mxp.tw/contact/
 * License: GPL v3
 */
namespace MxpDevTools;

if (!defined('WPINC')) {
    die;
}

include dirname(__FILE__) . '/includes/plugins-list.php';
include dirname(__FILE__) . '/includes/db-optimize.php';
include dirname(__FILE__) . '/includes/search-replace.php';
include dirname(__FILE__) . '/includes/utility.php';
include dirname(__FILE__) . '/includes/hooks-usage.php';

class MxpDevTools {
    use PluginsList;
    use DatabaseOptimize;
    use SearchReplace;
    use Utility;
    static $VERSION                   = '2.9.9.8';
    private $themeforest_api_base_url = 'https://api.envato.com/v3';
    protected static $instance        = null;
    public $plugin_slug               = 'mxp_wp_dev_tools';
    private $installed_plugins        = null;
    private function __construct() {
        $this->init();
    }

    public function init() {
        // index.php
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
        add_action('admin_init', array($this, 'mxp_init_author_plugins_table'));
        add_action('admin_enqueue_scripts', array($this, 'load_assets'));
        add_action('admin_menu', array($this, 'create_plugin_menu'));
        // 拆至 includes/plugins-list.php
        add_filter('plugin_action_links', array($this, 'modify_action_link'), 11, 4);
        add_filter('plugin_action_links', array($this, 'mxp_add_plugin_download_link'), 11, 4);
        add_action('wp_ajax_mxp_install_plugin', array($this, 'mxp_ajax_install_plugin'));
        add_action('wp_ajax_mxp_install_plugin_from_url', array($this, 'mxp_ajax_install_plugin_from_url'));
        add_action('wp_ajax_mxp_activate_plugin', array($this, 'mxp_ajax_activate_plugin'));
        add_action('wp_ajax_mxp_install_theme', array($this, 'mxp_ajax_install_theme'));
        add_action('wp_ajax_mxp_current_plugin_download', array($this, 'mxp_ajax_current_plugin_download_action'));
        // 拆至 includes/db-optimize.php
        add_action('wp_ajax_mxp_ajax_mysqldump', array($this, 'mxp_ajax_mysqldump'));
        add_action('wp_ajax_mxp_ajax_mysqldump_large', array($this, 'mxp_ajax_mysqldump_large'));
        add_action('wp_ajax_mxp_background_pack', array($this, 'mxp_ajax_background_pack_action'));
        add_action('wp_ajax_mxp_ajax_db_optimize', array($this, 'mxp_ajax_db_optimize'));
        add_action('wp_ajax_mxp_ajax_clean_orphan', array($this, 'mxp_ajax_clean_orphan'));
        add_action('wp_ajax_mxp_ajax_clean_mxpdev', array($this, 'mxp_ajax_clean_mxpdev'));
        add_action('wp_ajax_mxp_ajax_reset_user_metabox', array($this, 'mxp_ajax_reset_user_metabox'));
        add_action('wp_ajax_mxp_ajax_set_autoload_no', array($this, 'mxp_ajax_set_autoload_no'));
        add_action('wp_ajax_mxp_ajax_reset_wp', array($this, 'mxp_ajax_reset_wp'));
        // 拆至 includes/search-replace.php
        add_action('wp_ajax_mxp_ajax_search_replace_db', array($this, 'mxp_ajax_search_replace_db'));
    }

    public static function get_instance() {
        if (!isset(self::$instance) && is_super_admin()) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function load_assets() {
        wp_register_script($this->plugin_slug . '-plugins-list', plugin_dir_url(__FILE__) . 'includes/assets/js/plugins-list/app.js', array('jquery'), self::$VERSION, false);
        wp_register_script($this->plugin_slug . '-themeforest-list', plugin_dir_url(__FILE__) . 'includes/assets/js/themeforest-list/app.js', array('jquery'), self::$VERSION, false);
        wp_register_script($this->plugin_slug . '-search-plugins', plugin_dir_url(__FILE__) . 'includes/assets/js/search-plugins/app.js', array('jquery'), self::$VERSION, false);
        wp_register_script($this->plugin_slug . '-db-search-replace', plugin_dir_url(__FILE__) . 'includes/assets/js/search-replace/app.js', array('jquery'), self::$VERSION, false);
        wp_register_script($this->plugin_slug . '-db-optimize', plugin_dir_url(__FILE__) . 'includes/assets/js/db-optimize/app.js', array('jquery'), self::$VERSION, false);
        wp_register_script($this->plugin_slug . '-dashboard', plugin_dir_url(__FILE__) . 'includes/assets/js/dashboard/app.js', array('jquery'), self::$VERSION, false);
    }

    public function create_plugin_menu() {
        add_menu_page('Mxp.TW 開發常用工具箱', '開發工具箱', 'administrator', $this->plugin_slug, array($this, 'main_page_cb'), 'dashicons-admin-generic');
        add_submenu_page($this->plugin_slug, 'Themeforest List', 'Themeforest List', 'administrator', 'mxp-themeforest-list', array($this, 'themeforest_page_cb'));
        add_submenu_page($this->plugin_slug, '調整內容作者工具', '調整內容作者工具', 'administrator', 'mxp-change-post-owner', array($this, 'changepostowner_page_cb'));
        add_submenu_page($this->plugin_slug, '作者外掛列表', '作者外掛列表', 'administrator', 'mxp-author-plugins', array($this, 'listauthorplugin_page_cb'));
        add_submenu_page($this->plugin_slug, '外掛搜尋工具', '外掛搜尋工具', 'administrator', 'mxp-search-plugins', array($this, 'searchplugin_page_cb'));
        add_submenu_page($this->plugin_slug, '主題打包工具', '主題打包工具', 'administrator', 'mxp-theme-archive', array($this, 'themearchive_page_cb'));
        add_submenu_page($this->plugin_slug, '查看網站各項設定', '查看網站各項設定', 'administrator', 'mxp-get-wp-config', array($this, 'getwpconfig_page_cb'));
        if (function_exists('phpinfo')) {
            add_submenu_page($this->plugin_slug, '主機 PHP 設定', '主機 PHP 設定', 'administrator', 'mxp-get-php-info', array($this, 'getphpinfo_page_cb'));
        }
        add_submenu_page($this->plugin_slug, '資料庫檢視與匯出', '資料庫檢視與匯出', 'administrator', 'mxp-db-op-methods', array($this, 'dbopmethods_page_cb'));
        add_submenu_page($this->plugin_slug, '資料庫關鍵字取代', '資料庫關鍵字取代', 'administrator', 'mxp-db-replace-methods', array($this, 'dbreplacemethods_page_cb'));
        add_submenu_page($this->plugin_slug, 'WP 資料庫最佳化', 'WP 資料庫最佳化', 'administrator', 'mxp-wpdb-optimize-methods', array($this, 'wpdboptimizemethods_page_cb'));
        add_submenu_page($this->plugin_slug, '查看最近變更檔案', '查看最近變更檔案', 'administrator', 'mxp-recently-mod_files', array($this, 'recentlymodfiles_page_cb'));
    }

    public function add_action_links($links) {
        $mxp_links = array(
            '<a href="' . admin_url('admin.php?page=mxp_wp_dev_tools') . '"><font color=red>點此設定</font></a>',
        );
        return array_merge($links, $mxp_links);
    }

    public function main_page_cb() {
        $this->page_wraper('開發常用外掛', function () {
            require_once dirname(__FILE__) . '/includes/class_plugins_list_table.php';

            $plugins_list = new \Mxp_Plugins_List_Table();
            $plugins_list->prepare_items();
            $plugins_list->display();
            wp_localize_script($this->plugin_slug . '-plugins-list', 'Mxp_AJAX', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('mxp-ajax-nonce-for-plugin-list'),
            ));

            wp_enqueue_script($this->plugin_slug . '-plugins-list');

        });
    }
    public function mxp_init_author_plugins_table($args) {
        add_filter('install_plugins_table_api_args_mxp-plugins', function ($args) {
            global $paged;
            return [
                'page'     => $paged,
                'per_page' => 100,
                'locale'   => get_user_locale(),
                'author'   => 'mxp',
            ];
        });
        add_filter('install_plugins_nonmenu_tabs', function ($tabs) {
            $tabs[] = 'mxp-plugins';
            return $tabs;
        });
    }

    public function recentlymodfiles_page_cb() {
        $this->page_wraper('查詢最近變更的檔案', function () {
            wp_enqueue_script('jquery-ui-datepicker-google', '//ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js');
            wp_enqueue_script('jquery-ui-datepicker-google-i18n', '//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/i18n/jquery-ui-i18n.js');
            wp_enqueue_style('jquery-ui-datepicker-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.css');

            wp_localize_script($this->plugin_slug . '-dashboard', 'Mxp_AJAX_dashboard', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('mxp-ajax-nonce-for-recently_mod_files'),
            ));
            wp_enqueue_script($this->plugin_slug . '-dashboard');

            $day_from = date('Y/m/d', strtotime("-1 days"));
            $day_to   = date('Y/m/d', time());
            if (isset($_GET['day_from']) && $_GET['day_from'] != '') {
                $day_from = sanitize_text_field($_GET['day_from']);
            }
            if (isset($_GET['day_to']) && $_GET['day_to'] != '') {
                $day_to = sanitize_text_field($_GET['day_to']);
            }
            echo '<input id="search_day_from" type="text" value="' . esc_attr($day_from) . '" placeholder="查詢起始日"/><input id="search_day_to" type="text" value="' . esc_attr($day_to) . '" placeholder="查詢結束日"/><button type="button" class="button button-primary" id="go_search_day">送出</button></br>';
            $raw_data = $this->get_recently_mod_files($day_from, $day_to);
            foreach ($raw_data as $type => $rows) {
                if (count($rows) > 0) {
                    $mod_rows  = array();
                    $type_name = '';
                    switch ($type) {
                    case 'dot_files':
                        $type_name = '作業系統隱藏檔案';
                        break;
                    case 'core_files':
                        $type_name = 'WordPress 核心系統檔案';
                        break;
                    case 'uploads_files':
                        $type_name = '使用者上傳目錄檔案';
                        break;
                    case 'uncategorized_files':
                        $type_name = '未分類檔案';
                        break;
                    case 'theme_files':
                        $type_name = '主題檔案';
                        break;
                    case 'plugin_files':
                        $type_name = '外掛檔案';
                        break;
                    default:
                        break;
                    }
                    if ($type == 'dot_files') {
                        echo '<h3>所有的 <font color="red">' . $type_name . '</font></h3>';
                    } else {
                        echo '<h3>' . $day_from . ' ~ ' . $day_to . ' 內有修改紀錄的 <font color="red">' . $type_name . '</font></h3>';
                    }
                    usort($rows, function ($item1, $item2) {
                        if ($item1['mod_time'] == $item2['mod_time']) {
                            return 0;
                        }
                        return $item1['mod_time'] > $item2['mod_time'] ? -1 : 1;
                    });
                    foreach ($rows as $key => $row) {
                        $full_path     = $row['full_path'];
                        $relative_path = explode(DIRECTORY_SEPARATOR, $row['relative_path']);
                        if (count($relative_path) > 1) {
                            $relative_path[0] = '<strong><font color="orange">' . $relative_path[0] . '</font></strong>';
                            $relative_path    = implode(DIRECTORY_SEPARATOR, $relative_path);
                        } else {
                            $relative_path = $relative_path[0];
                        }
                        $_file = pathinfo($row['name']);

                        $mod_time = date('Y-m-d H:i:s', $row['mod_time']);
                        if (isset($_file['extension']) && in_array(strtoupper($_file['extension']), array('PHP', 'PHP4', 'PHP5', 'CGI', 'PL', 'EXE', 'SH', 'ICO'))) {
                            $name = '<strong><font color="coral">' . esc_html($row['name']) . '</font></strong>';
                        } else {
                            $name = '<font color="blue">' . esc_html($row['name']) . '</font>';
                        }
                        $mxp_download_action_link = admin_url('admin-ajax.php?action=mxp_current_plugin_download&path=' . base64_encode($full_path) . '&type=file&context=recently_mod_file');
                        $mxp_download_action_link = add_query_arg('_wpnonce', wp_create_nonce('mxp-download-current-plugins-' . base64_encode($full_path)), $mxp_download_action_link);
                        $download_link            = '<a target="_blank" href="' . esc_url($mxp_download_action_link) . '" class="mxp_plugin_download_link" class="button">下載</a>';
                        $mod_rows[]               = array(
                            '路徑'   => $relative_path,
                            '檔案名稱' => $name,
                            '修改日期' => $mod_time,
                            '操作'   => $download_link,
                        );
                    }
                    echo $this->build_table($mod_rows);
                }
            }

        });
    }

    public function wpdboptimizemethods_page_cb() {
        $this->page_wraper('WordPress 資料庫最佳化相關功能', function () {
            wp_localize_script($this->plugin_slug . '-db-optimize', 'MXP', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('mxp-ajax-nonce-for-db-optimize'),
            ));
            wp_enqueue_script($this->plugin_slug . '-db-optimize');
            global $wpdb;
            $big_options = $wpdb->get_results("SELECT option_name AS `Option Name`, LENGTH(option_value) AS `Size` FROM {$wpdb->options} WHERE autoload='yes' ORDER BY length(option_value) DESC LIMIT 25", ARRAY_A);
            foreach ($big_options as $key => $option) {
                $option['Size']    = round($option['Size'] / 1024, 2) . ' KB';
                $option['操作']  = '<button type="button" class="autoload_off_btn button button-secondary" data-option_name="' . esc_attr($option['Option Name']) . '">取消 Autoload</button>';
                $big_options[$key] = $option;
            }
            echo '<h3>資料庫排名前 25 筆耗資源紀錄</h3>';
            echo $this->build_table($big_options);
            echo '<h3>清除未使用的主題、外掛設定</h3>';
            echo '<p>許多外掛、主題安裝啟用測試後會在設定資料表中留下紀錄，如果外掛沒有自行清除就會累積，造成網站載入變慢的問題。</p>';
            echo '<button id="go_clean_options" data-step="1" type="button" class="button button-primary">取得清除清單</button></br>';
            echo '<div id="go_clean_options_result"></div></br>';
            echo '<h3>清除孤立的 Post/Comment Meta 資料</h3>';
            echo '<p>不論是手動刪除或是外掛刪除內容，可能沒連帶刪除的 Meta 關聯資料，堆積在資料庫裡變成垃圾。</p>';
            $orphan_postmeta_count    = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL");
            $orphan_commentmeta_count = $wpdb->get_var("SELECT COUNT(*) as row_count FROM {$wpdb->commentmeta} WHERE comment_id NOT IN (SELECT comment_id FROM {$wpdb->comments})");
            echo '<p><button id="go_clean_orphan_postmeta" type="button" class="button button-primary">清除 ' . $orphan_postmeta_count . ' 筆 Post 孤立資料</button></p>';
            echo '<p><button id="go_clean_orphan_commentmeta" type="button" class="button button-primary">清除 ' . $orphan_commentmeta_count . ' 筆 Comment 孤立資料</button></p>';
            echo '<div id="go_clean_orphan_result"></div></br>';
            echo '<h3>重置全部使用者 Metabox 設定位置</h3>';
            $user_metabox_count = $wpdb->get_var("SELECT COUNT(umeta_id) FROM {$wpdb->prefix}usermeta WHERE meta_key LIKE 'meta-box-order_%'");
            echo '<p><button id="go_reset_user_metabox" type="button" class="button button-primary">清除 ' . $user_metabox_count . ' 筆資料</button></p>';
            echo '<h3>建立資料表索引</h3>';
            echo '請搭配安裝外掛： <a href="https://tw.wordpress.org/plugins/index-wp-mysql-for-speed/" target="_blank">Index WP MySQL For Speed</a> 與 <a href="https://tw.wordpress.org/plugins/index-wp-users-for-speed/" target="_blank">Index WP Users For Speed</a> 使用。';
            echo '<h3>最佳化使用者角色資料庫讀取效能</h3>';
            echo '請搭配安裝外掛： <a href="https://github.com/humanmade/roles-to-taxonomy" target="_blank">Roles to Taxonomy</a> - 將預設使用者角色的讀寫機制改使用 Taxonomy 機制處理進行最佳化。';
            echo '<h3>修正資料庫 Collation</h3>';
            echo '請搭配安裝外掛： <a href="https://tw.wordpress.org/plugins/database-collation-fix/" target="_blank">Database Collation Fix</a> - 修正有些時候外掛沒指定資料集的時候，資料庫使用預設資料集建立，導致轉換資料庫的過程發生不相容的問題。';
            echo '<h3>重置使用者角色與權限</h3>';
            echo '請搭配安裝外掛： <a href="https://tw.wordpress.org/plugins/reset-roles-and-capabilities/" target="_blank">Reset Roles and Capabilities</a> - 安裝與啟用外掛當下就可以重置使用者角色與權限的工具。';
            echo '<h3>重新安裝 WordPress</h3>';
            echo '<p>砍掉重練，也是一種最佳化的必經之路。<font color="red">注意，這項操作不可逆。請小心使用！</font></p>';
            echo '<p><button id="go_reset_wp" type="button" class="button button-primary">重置網站</button></p>';

        });
    }
    public function dbreplacemethods_page_cb() {
        $this->page_wraper('資料庫關鍵字取代', function () {
            global $wpdb;
            echo '當前資料庫:';
            $dbs        = $wpdb->get_results("SHOW DATABASES", ARRAY_A);
            $current_db = $wpdb->dbname;
            if (isset($_GET['dbname']) && $_GET['dbname'] != '') {
                $current_db = sanitize_text_field($_GET['dbname']);
            }
            wp_localize_script($this->plugin_slug . '-db-search-replace', 'MXP', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('mxp-ajax-nonce-for-db-search-replace-' . $current_db),
            ));
            wp_enqueue_script($this->plugin_slug . '-db-search-replace');
            echo '<select id="select_dbname">';
            $check_db_exists = false;
            foreach ($dbs as $db) {
                if ($db['Database'] != 'information_schema') {
                    echo '<option value="' . esc_attr($db['Database']) . '" ' . selected($current_db, $db['Database']) . '>' . esc_html($db['Database']) . '</option>';
                    if ($db['Database'] == $current_db) {
                        $check_db_exists = true;
                    }
                }
            }
            if (!$check_db_exists) {
                $current_db = $wpdb->dbname;
            }
            echo '</select></br>';
            $tables = $wpdb->get_results("SHOW FULL TABLES FROM `{$current_db}`", ARRAY_A);
            echo '1. 選擇要取代內文的資料表:</br>';
            $tables_arr   = array();
            $tables_arr[] = array('勾選' => '<input type="checkbox" id="check_all" class="check_all" name="check_all" value="ALL">', '資料表' => '全部資料表', '操作結果' => '');
            echo '<fieldset>';
            foreach ($tables as $key => $table) {
                $table_name = $table['Tables_in_' . $current_db];
                $table_type = $table['Table_type'];
                if ($table_type != 'BASE TABLE') {
                    continue;
                }
                // $break_line = '</br>';
                // echo '<label><input type="checkbox" class="select_tables" name="table[]" value="' . esc_attr($table_name) . '">' . esc_html($table_name) . '<span id="' . $table_name . '"></span></label>' . $break_line;
                $tables_arr[] = array('勾選' => '<input type="checkbox" class="select_tables" name="table[]" value="' . esc_attr($table_name) . '">', '資料表' => esc_html($table_name), '操作結果' => '<div class="table_result" id="' . esc_attr($table_name) . '"></div>');
            }
            echo $this->build_table($tables_arr);
            echo '</fieldset>';
            echo '2. 設定搜尋的字串(一行一組對應，注意換行前後空白將會被視為搜尋或取代的字元):';
            echo '<fieldset>';
            $demo = 'http://www.mxp.tw' . PHP_EOL . 'http:\/\/www.mxp.tw' . PHP_EOL . 'mxp.tw';
            echo '<textarea id="replace_from" rows="5" cols="50">' . $demo . '</textarea>';
            echo '</fieldset>';
            echo '3. 設定取代的字串(一行一組對應，注意換行前後空白將會被視為搜尋或取代的字元):';
            echo '<fieldset>';
            $demo2 = 'https://' . $_SERVER['HTTP_HOST'] . PHP_EOL . 'https:\/\/' . $_SERVER['HTTP_HOST'] . PHP_EOL . $_SERVER['HTTP_HOST'];
            echo '<textarea id="replace_to" rows="5" cols="50">' . $demo2 . '</textarea>';
            echo '</fieldset>';
            echo '<fieldset>';
            echo '4. 預覽計算影響的資料筆數';
            echo '</br><button type="button" class="button button-secondary" id="replace_preview">預覽</button></br>';
            echo '<div id="preview_result"></div>';
            echo '</fieldset>';
            echo '<fieldset>';
            echo '5. 確認執行取代';
            echo '</br><button type="button" class="button button-primary" id="go_replace">取代</button></br>';
            echo '<div id="replace_result"></div>';
            echo '</fieldset>';
        });
        // echo $this->build_table($tables);

    }
    public function dbopmethods_page_cb() {
        $this->page_wraper('資料庫檢視與匯出', function () {
            global $wpdb;
            $table        = $wpdb->options;
            $column       = 'option_name';
            $key_column   = 'option_id';
            $value_column = 'option_value';

            if (is_multisite()) {
                $table        = $wpdb->sitemeta;
                $column       = 'meta_key';
                $key_column   = 'meta_id';
                $value_column = 'meta_value';
            }
            $option_prefix      = 'mxp_dev_zipfile_';
            $step_0_option_name = 'mxp_dev_packfile_step0';
            $key                = $option_prefix . '%';

            $sql = '
            SELECT COUNT(*)
            FROM ' . $table . '
            WHERE ' . $column . ' LIKE %s
            ORDER BY ' . $key_column . ' ASC
            ';
            $total_batch_count       = $wpdb->get_var($wpdb->prepare($sql, $key));
            $mysqldump_option_prefix = 'mxp_dev_mysqldump_file_';
            $key                     = $mysqldump_option_prefix . '%';
            $sql                     = '
            SELECT *
            FROM ' . $table . '
            WHERE ' . $column . ' LIKE %s
            ORDER BY ' . $key_column . ' ASC
            ';
            $total_mysqldump_count = $wpdb->get_results($wpdb->prepare($sql, $key), ARRAY_A);
            $key                   = $step_0_option_name . '%';
            $sql                   = '
            SELECT COUNT(*)
            FROM ' . $table . '
            WHERE ' . $column . ' LIKE %s
            ORDER BY ' . $key_column . ' ASC
            ';
            $total_packing_count = $wpdb->get_var($wpdb->prepare($sql, $key));

            wp_localize_script($this->plugin_slug . '-db-optimize', 'MXP', array(
                'ajaxurl'            => admin_url('admin-ajax.php'),
                'nonce'              => wp_create_nonce('mxp-ajax-nonce-for-db-optimize'),
                'background_process' => $total_packing_count,
                'mysqldump_process'  => $total_mysqldump_count,
            ));
            wp_enqueue_script($this->plugin_slug . '-db-optimize');
            $dump_db = array();
            foreach ($total_mysqldump_count as $key => $mydump) {
                $total_mysqldump_ops = get_site_option($mydump[$column], '');
                if ($total_mysqldump_ops != '') {
                    $db = $total_mysqldump_ops['db'];
                    if (!isset($dump_db[$db])) {
                        $dump_db[$db] = array();
                    }
                    $dump_db[$db]['status']   = $total_mysqldump_ops['status'];
                    $dump_db[$db]['filename'] = $total_mysqldump_ops['filename'];
                    $dump_db[$db]['filepath'] = $total_mysqldump_ops['filepath'];
                }
            }
            if (isset($_GET['database']) && $_GET['database'] != '') {
                $database_name = sanitize_text_field($_GET['database']);
                $tbs           = $wpdb->get_results(
                    $wpdb->prepare("SELECT TABLE_NAME AS Table_Name, ENGINE AS Engine, TABLE_TYPE AS Table_Type, TABLE_ROWS AS Table_Rows, CREATE_TIME AS Create_Time, TABLE_COLLATION AS Collation, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s", $database_name), ARRAY_A);
                $wp_tbs        = array_values($wpdb->tables());
                $dropdown_list = array('全部資料表' => '');
                foreach ($tbs as $key => $tabls_info) {
                    $sql_dump_link                           = admin_url('admin-ajax.php?action=mxp_ajax_mysqldump&database=' . $database_name . '&table=' . $tbs[$key]['Table_Name']);
                    $sql_dump_link                           = add_query_arg('_wpnonce', wp_create_nonce('mxp-mysqldump-' . $database_name . '-' . $tbs[$key]['Table_Name']), $sql_dump_link);
                    $download_link                           = '<a target="_blank" href="' . esc_url($sql_dump_link) . '" class="mxp_mysqldump_link button">匯出</a>';
                    $tbs[$key]['操作']                     = $download_link;
                    $dropdown_list[$tbs[$key]['Table_Name']] = $sql_dump_link;
                    if (in_array($tbs[$key]['Table_Name'], $wp_tbs)) {
                        $tbs[$key]['Table_Name'] = '<strong><font color="blue">' . $tbs[$key]['Table_Name'] . '</font></strong>';
                    }
                }
                $sql_dump_link                    = admin_url('admin-ajax.php?action=mxp_ajax_mysqldump&database=' . $database_name . '&table=ALL');
                $sql_dump_link                    = add_query_arg('_wpnonce', wp_create_nonce('mxp-mysqldump-' . $database_name . '-ALL'), $sql_dump_link);
                $dropdown_list['全部資料表'] = $sql_dump_link;
                $table                            = $this->build_table($tbs);
                echo '<a href="' . admin_url("admin.php?page=mxp-db-op-methods") . '">回上一頁</a></br><hr></br>';
                echo '<select id="mxp_dump_select">';
                foreach ($dropdown_list as $table_name => $link) {
                    echo '<option value="' . esc_attr($link) . '">' . esc_html($table_name) . '</option>';
                }
                echo '</select>';
                echo '<button type="button" class="button" id="mxp_dump_btn">匯出</button></br>';
                echo '<script>jQuery("#mxp_dump_btn").click(function(){window.open(jQuery("#mxp_dump_select").val(), "_blank").focus();});</script>';
                echo $table;
            } else {
                $dbs       = $wpdb->get_results("SHOW DATABASES;", ARRAY_A);
                $colls_set = array();
                $colls     = $wpdb->get_results("SHOW COLLATION", ARRAY_A);
                foreach ($colls as $colls_index => $row) {
                    if ($row["Default"]) {
                        $colls_set[$row["Charset"]][-1] = $row["Collation"];
                    } else {
                        $colls_set[$row["Charset"]][] = $row["Collation"];
                    }
                }
                ksort($colls_set);
                foreach ($colls_set as $key => $val) {
                    asort($colls_set[$key]);
                }
                $filter_dbs = [];
                foreach ($dbs as $key => $db) {
                    $database_name = $db['Database'];
                    if ($database_name != 'information_schema') {
                        $collection = $wpdb->get_results(
                            "SHOW CREATE DATABASE {$database_name}"
                            , ARRAY_A);
                        $col        = '';
                        $collection = $collection[0]['Create Database'];
                        if (preg_match('~ COLLATE ([^ ]+)~', $collection, $match)) {
                            $col = $match[1];
                        } elseif (preg_match('~ CHARACTER SET ([^ ]+)~', $collection, $match)) {
                            // default collation
                            $col = $colls_set[$match[1]][-1];
                        }
                        $mod_database_name = $database_name;
                        if ($database_name == $wpdb->dbname) {
                            $mod_database_name = '<strong><font color="blue">
                        ' . $database_name . '</font></strong>';
                        }
                        if (isset($dump_db[$database_name])) {
                            $filter_dbs[] = array('DB Name' => $mod_database_name, 'Collation' => esc_html($col), '操作' => '<a class="button" href="' . admin_url("admin.php?page=mxp-db-op-methods&database=" . $database_name) . '">查看資料表</a>', '匯出資料庫' => '<button type="button" class="mxp_mysqldump  button" data-database="' . $database_name . '" data-step="2" data-dump_file_name="' . $dump_db[$database_name]['filename'] . '" data-dump_file_path="' . $dump_db[$database_name]['filepath'] . '">下載</button>');
                        } else {
                            $filter_dbs[] = array('DB Name' => $mod_database_name, 'Collation' => esc_html($col), '操作' => '<a class="button" href="' . admin_url("admin.php?page=mxp-db-op-methods&database=" . $database_name) . '">查看資料表</a>', '匯出資料庫' => '<button type="button" class="mxp_mysqldump  button" data-database="' . $database_name . '" data-step="1">執行</button>');
                        }
                    }
                }
                $db_server_info = $wpdb->get_results("SHOW VARIABLES like '%version%'", ARRAY_A);
                $table          = $this->build_table($filter_dbs);
                $table2         = $this->build_table($db_server_info);
                echo $table;
                echo '</br>';
                echo $table2;
                echo '</br>';
                $wp_content_dir                          = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR . '/' . 'index.php');
                $wp_content_upload_dir                   = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR . '/uploads/');
                $wp_mu_plugins_dir                       = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR . '/mu-plugins/index.php');
                $mxp_download_wp_content_with_uploads    = admin_url('admin-ajax.php?action=mxp_current_plugin_download&path=' . base64_encode($wp_content_dir) . '&type=folder&context=wp-content');
                $mxp_download_wp_content_with_uploads    = add_query_arg('_wpnonce', wp_create_nonce('mxp-download-current-plugins-' . base64_encode($wp_content_dir)), $mxp_download_wp_content_with_uploads);
                $mxp_download_wp_content_without_uploads = admin_url('admin-ajax.php?action=mxp_current_plugin_download&path=' . base64_encode($wp_content_dir) . '&type=folder&context=wp-content&exclude_path=' . base64_encode($wp_content_upload_dir));
                $mxp_download_wp_content_without_uploads = add_query_arg('_wpnonce', wp_create_nonce('mxp-download-current-plugins-' . base64_encode($wp_content_dir)), $mxp_download_wp_content_without_uploads);

                $mxp_download_mu_plugins = admin_url('admin-ajax.php?action=mxp_current_plugin_download&path=' . base64_encode($wp_mu_plugins_dir) . '&type=folder&context=mu-plugins');
                $mxp_download_mu_plugins = add_query_arg('_wpnonce', wp_create_nonce('mxp-download-current-plugins-' . base64_encode($wp_mu_plugins_dir)), $mxp_download_mu_plugins);
                $check_mu_plugins        = '';
                $mu_plugins_dir          = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR . '/mu-plugins/');
                if (!file_exists($mu_plugins_dir)) {
                    $check_mu_plugins        = 'disabled';
                    $mxp_download_mu_plugins = '#';
                }
                $abspath                = str_replace('/', DIRECTORY_SEPARATOR, ABSPATH);
                $mxp_download_wp_config = admin_url('admin-ajax.php?action=mxp_current_plugin_download&path=' . base64_encode($abspath . 'wp-config.php') . '&type=file&context=wp-config');
                $mxp_download_wp_config = add_query_arg('_wpnonce', wp_create_nonce('mxp-download-current-plugins-' . base64_encode($abspath . 'wp-config.php')), $mxp_download_wp_config);

                $download_link = '<button type="button" class="button pack_wp_content" data-path="' . base64_encode($wp_content_dir) . '" data-nonce="' . wp_create_nonce('mxp-download-current-plugins-' . base64_encode($wp_content_dir)) . '" data-exclude_path="" >打包 wp-content 目錄（包含 uploads）</button> | <button type="button" class="button pack_wp_content" data-path="' . base64_encode($wp_content_dir) . '" data-nonce="' . wp_create_nonce('mxp-download-current-plugins-' . base64_encode($wp_content_dir)) . '" data-exclude_path="' . base64_encode($wp_content_upload_dir) . '">打包 wp-content 目錄（不含 uploads）</button> | <button type="button" class="button cleanup_mxpdev">清除外掛暫存目錄與設定</button> | <a href="' . $mxp_download_mu_plugins . '" ' . $check_mu_plugins . ' class="button ">打包 mu-plugins 目錄</a> | <a href="' . $mxp_download_wp_config . '" class="button ">打包 wp-config.php 檔案</a>';
                echo $download_link;
            }
        });
    }

    public function show_highlight_string($string) {
        if (function_exists('highlight_string')) {
            highlight_string($string);
        } else {
            echo '<pre>' . $string . '</pre>';
        }
    }

    public function getwpconfig_page_cb() {
        $this->page_wraper('查看網站各項設定', function () {
            echo '<h2>網路資訊</h2></br>';
            $response = wp_remote_get('https://undo.im/json?v=' . self::$VERSION . '&from=' . get_site_url(), array('sslverify' => false, 'timeout' => 5));
            if (!is_wp_error($response)) {
                if (200 == wp_remote_retrieve_response_code($response)) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    $ipv4 = '';
                    $ipv6 = '';
                    $ip   = explode('.', $body['IP']);
                    // 找不到 IPv6 的話會噴一個警告，設定這個處理捕捉警告，就不會這麼難看惹 Ref: https://stackoverflow.com/questions/1241728/can-i-try-catch-a-warning
                    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                        // error was suppressed with the @-operator
                        if (0 === error_reporting()) {
                            return false;
                        }
                        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
                    });

                    if (count($ip) == 4) {
                        $ipv4 = $body['IP'];
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
                                $ipv6 = '無 IPv6 資源！';
                            }
                        }
                        if (function_exists('fsockopen') && 'NONE' === $ipv6 && function_exists('stream_socket_get_name')) {
                            try {
                                $fp = fsockopen('tcp://[2606:4700:4700::1111]', 53, $errno, $errstr, 5);
                                if (!$fp) {
                                    $ipv6 = "fsockopen get IPv6 error: $errstr ($errno)";
                                } else {
                                    $local_endpoint = stream_socket_get_name($fp, false); // 拿到本機請求的 socket 資源
                                    if (preg_match('/\[(.*?)\]/', $local_endpoint, $matches)) {
                                        $ipv6 = $matches[1];
                                    }
                                    fclose($fp);
                                }
                            } catch (\Exception $ex) {
                                $ipv6 = '無 IPv6 資源！';
                            }
                        }
                    } else {
                        $ipv6 = $body['IP'];
                        $ipv4 = 'NONE';
                        // 反之，如果有 v6 那就來問問看 v4
                        if (function_exists('socket_create') && function_exists('socket_connect') && function_exists('socket_getsockname') && function_exists('socket_close')) {
                            $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                            try {
                                socket_connect($sock, "8.8.8.8", 53);
                                socket_getsockname($sock, $qname);
                                socket_close($sock);
                                $ipv4 = $qname;
                            } catch (\Exception $ex) {
                                $ipv4 = '無 IPv4 資源！';
                            }
                        }
                        if (function_exists('fsockopen') && 'NONE' === $ipv4 && function_exists('stream_socket_get_name')) {
                            try {
                                $fp = fsockopen('tcp://8.8.8.8', 53, $errno, $errstr, 5);
                                if (!$fp) {
                                    $ipv4 = "fsockopen get IPv4 error: $errstr ($errno)";
                                } else {
                                    $local_endpoint = stream_socket_get_name($fp, false); // 拿到本機請求的 socket 資源
                                    $ipv4           = current(explode(':', $local_endpoint));
                                    fclose($fp);
                                }
                            } catch (\Exception $ex) {
                                $ipv4 = '無 IPv4 資源！';
                            }
                        }
                    }
                    restore_error_handler();
                    $UA             = isset($body['UA']) ? $body['UA'] : '';
                    $asn            = isset($body['CF']['asn']) ? $body['CF']['asn'] : '';
                    $asOrganization = isset($body['CF']['asOrganization']) ? $body['CF']['asOrganization'] : '';
                    $country        = isset($body['CF']['country']) ? $body['CF']['country'] : '';
                    $city           = isset($body['CF']['city']) ? $body['CF']['city'] : '';
                    $timezone       = isset($body['CF']['timezone']) ? $body['CF']['timezone'] : '';
                    // $headers = wp_remote_retrieve_headers( $response );
                    $html = '<table><thead><tr><th colspan="2">當前主機資源</th></tr></thead><tbody>';
                    $html .= '<tr><td><strong>IPv4</strong></td><td>' . $ipv4 . "</td></tr>";
                    $html .= '<tr><td><strong>IPv6</strong></td><td>' . $ipv6 . "</td></tr>";
                    $html .= '<tr><td><strong>User Agent</strong></td><td>' . $UA . "</td></tr>";
                    $html .= '<tr><td><strong>ASN</strong></td><td>' . $asn . "</td></tr>";
                    $html .= '<tr><td><strong>Organization</strong></td><td>' . $asOrganization . "</td></tr>";
                    $html .= '<tr><td><strong>Country</strong></td><td>' . $country . "</td></tr>";
                    $html .= '<tr><td><strong>City</strong></td><td>' . $city . "</td></tr>";
                    $html .= '<tr><td><strong>TimeZone</strong></td><td>' . $timezone . "</td></tr>";
                    $html .= '</tbody></table>';
                    echo $html;
                } else {
                    $error_message = wp_remote_retrieve_response_message($response);
                    echo "查詢異常: " . $error_message . "<br>" . PHP_EOL;
                }
            } else {
                $error_message = $response->get_error_message();
                echo "請求異常: " . $error_message . "<br>" . PHP_EOL;
            }
            echo '<hr></br>';
            $this->show_highlight_string(file_get_contents(ABSPATH . 'wp-config.php'));
            echo '<hr></br>';
            echo '頁面功能參考外掛：<a href="https://tw.wordpress.org/plugins/system-dashboard/">System Dashboard</a></br>';
            echo $this->build_table($this->get_wp_all_contants());
            echo '<hr></br>';
            echo '<h2>.htaccess 檔案內容</h2></br>';
            if (file_exists(ABSPATH . '.htaccess')) {
                $this->show_highlight_string(file_get_contents(ABSPATH . '.htaccess'));
            } else {
                echo '檔案不存在。';
            }
            echo '<hr></br>';
            echo '<h2>.user.ini 檔案內容</h2></br>';
            if (file_exists(ABSPATH . '.user.ini')) {
                $this->show_highlight_string(file_get_contents(ABSPATH . '.user.ini'));
            } else {
                echo '檔案不存在。';
            }
        });
    }

    public function getphpinfo_page_cb() {
        $this->page_wraper('查看主機 PHP 設定', function () {
            ob_start();
            phpinfo();
            $info_arr   = array();
            $info_lines = explode("\n", strip_tags(ob_get_clean(), "<tr><td><h2>"));
            $cat        = "General";
            foreach ($info_lines as $line) {
                // new cat?
                preg_match("~<h2>(.*)</h2>~", $line, $title) ? $cat = $title[1] : null;
                if (preg_match("~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~", $line, $val)) {
                    $info_arr[$cat][$val[1]] = $val[2];
                } elseif (preg_match("~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~", $line, $val)) {
                    $info_arr[$cat][$val[1]] = array("local" => $val[2], "master" => $val[3]);
                }
            }
            $this->phpinfo_print_r($info_arr);
        });
    }

    public function themearchive_page_cb() {
        $this->page_wraper('主題打包工具', function () {
            $all_themes = wp_get_themes();
            $themes_arr = array();
            foreach ($all_themes as $theme_slug => $theme_info) {
                $child_theme = '';
                if ($child_theme = $theme_info->parent()) {
                    $child_theme = $child_theme->display('Name');
                } else {
                    $child_theme = '無';
                }
                $path                     = str_replace('/', DIRECTORY_SEPARATOR, $theme_info->get_stylesheet_directory() . '/style.css');
                $type                     = 'folder';
                $context                  = 'themes';
                $mxp_download_action_link = admin_url('admin-ajax.php?action=mxp_current_plugin_download&path=' . base64_encode($path) . '&type=' . $type . '&context=' . $context);
                $mxp_download_action_link = add_query_arg('_wpnonce', wp_create_nonce('mxp-download-current-plugins-' . base64_encode($path)), $mxp_download_action_link);
                $download_link            = '<a class="button" target="_blank" href="' . esc_url($mxp_download_action_link) . '" class="mxp_plugin_download_link">打包主題</a>';
                // echo "<li>" . $child_theme . " " . $theme_info->display('Name') . "(" . $theme_info->display('Version') . ") ->" . $theme_info->display('Status') . " By " . $theme_info->display('Author') . " | " . $download_link . "</li>";
                $themes_arr[] = array('名稱' => $theme_info->display('Name'), '上層主題' => $child_theme, '作者' => $theme_info->display('Author'), '操作' => $download_link);
            }
            echo $this->build_table($themes_arr);
        });
    }
    public function searchplugin_page_cb() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $slugs       = array();
        foreach ($all_plugins as $key => $info) {
            $slug    = explode('/', $key);
            $slugs[] = $slug[0];
        }
        wp_localize_script($this->plugin_slug . '-search-plugins', 'MXP', array(
            'ajaxurl'         => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('mxp-ajax-nonce-for-search-plugins'),
            'install_plugins' => $slugs,
        ));

        wp_enqueue_script($this->plugin_slug . '-search-plugins');
        echo '<div id="search_iframe"></div>';
    }

    public function listauthorplugin_page_cb() {
        require_once ABSPATH . 'wp-admin/includes/class-wp-plugin-install-list-table.php';
        $transient = 'mxp-plugins';
        $cached    = get_transient($transient);
        if (false !== $cached) {
            echo $cached;
            return;
        }
        $_POST['tab'] = 'mxp-plugins';
        ob_start();
        $table = new \WP_Plugin_Install_List_Table();
        $table->prepare_items();
        $table->display();
        $content = ob_get_clean();
        set_transient($transient, $content, DAY_IN_SECONDS);
        echo $content;
    }

    public function changepostowner_page_cb() {
        $this->page_wraper('修改全站內容權限', function () {
            $ps     = get_post_types(array('exclude_from_search' => false), 'names', 'or');
            $select = '<p>選擇內容類型： <select name="mxp_dev_post_type"><option value="">All</option>';
            foreach ($ps as $key => $value) {
                $select .= '<option value="' . $value . '">' . $value . '</option>';
            }
            $select .= '</select></p>';
            echo "<form name='post_owner' method='POST'>" . $select . "<p>選擇使用者：" . wp_dropdown_users(array('name' => 'mxp_dev_post_author', 'selected' => 1, 'echo' => false)) . "</p><input type='hidden' name='nonce' value='" . wp_create_nonce('mxp-dev-change-owner-page') . "'><input type='submit' value='送出' class='button action'/></form>";
        });
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'mxp-dev-change-owner-page') && isset($_POST['mxp_dev_post_author'])) {
            global $wpdb;
            $uid  = 1;
            $type = empty($_POST['mxp_dev_post_type']) ? "" : $_POST['mxp_dev_post_type'];
            if (is_numeric($_POST['mxp_dev_post_author'])) {
                $uid = $_POST['mxp_dev_post_author'];
            }
            if ($type == "") {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}posts SET post_author = %s", $uid));
            } else {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}posts SET post_author = %s WHERE post_type = %s", $uid, $type));
            }
            echo "<p>更新成功！</p>";
        } else if (!empty($_POST)) {
            echo "<p>錯誤的操作！</p>";
        }
    }

}

add_action('plugins_loaded', array('\MxpDevTools\MxpDevTools', 'get_instance'));
