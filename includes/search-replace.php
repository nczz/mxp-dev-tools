<?php
namespace MxpDevTools;

if (!defined('WPINC')) {
    die;
}

trait SearchReplace {
    public function mxp_ajax_search_replace_db() {
        set_time_limit(0);
        ini_set("memory_limit", "-1");
        $dbname       = '';
        $dry_run      = false;
        $tables       = array();
        $nonce        = '';
        $replace_from = array();
        $replace_to   = array();

        if (!isset($_POST['dbname']) || $_POST['dbname'] == '' || !isset($_POST['tables']) || !is_array($_POST['tables']) ||
            !isset($_POST['replace_from']) || !is_array($_POST['replace_from']) ||
            !isset($_POST['replace_to']) || !is_array($_POST['replace_to'])) {
            wp_send_json_error('請求參數有誤！');
        }
        $dbname = sanitize_text_field($_POST['dbname']);
        if (isset($_POST['dry_run']) && $_POST['dry_run'] != '') {
            $dry_run = true;
        }
        $tables       = array_map('sanitize_text_field', $_POST['tables']);
        $replace_from = array_map('sanitize_text_field', $_POST['replace_from']);
        $replace_to   = array_map('sanitize_text_field', $_POST['replace_to']);
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-ajax-nonce-for-db-search-replace-' . $dbname)) {
            wp_send_json_error('請求驗證有誤！');
        }
        include dirname(__FILE__) . '/mxp_srdb.class.php';
        $args = array(
            'dbname'         => $dbname,
            'search'         => $replace_from,
            'replace'        => $replace_to,
            'tables'         => $tables,
            'exclude_tables' => array(),
            'exclude_cols'   => array(),
            'include_cols'   => array(),
            'dry_run'        => $dry_run,
            'regex'          => false,
            'page_size'      => 50000,
            'verbose'        => false,
            'debug'          => false,
        );
        $sr  = new \Mxp_SRDB($args);
        $res = array('report' => $sr->report, 'errors' => $sr->errors);
        wp_send_json_success($res);
    }
}