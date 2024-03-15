<?php
/*
 * plugin should create a file named ‘uninstall.php’ in the base plugin folder. This file will be called, if it exists,
 * during the uninstall process bypassing the uninstall hook.
 * ref: https://developer.wordpress.org/reference/functions/register_uninstall_hook/
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}
if (defined('MDT_DISALLOW_DELETE_PLUGIN') && MDT_DISALLOW_DELETE_PLUGIN == true) {
    exit('This plugin cannot be deleted.');
}
// 刪除 options 設定
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
$keys = array(
    'mxp_dev_zipfile_%',
    'mxp_dev_mysqldump_file_%',
    'mxp_dev_packfile_step0',
    'mxp_dev_sites_info_db',
    'mxd_dev_site_passkey',
);
foreach ($keys as $index => $key) {
    $sql = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
    $del = $wpdb->query($wpdb->prepare($sql, $key));
}
// 清除超連結目錄中的檔案
$mxpdev_folder = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR . '/uploads/MXPDEV/');
if (is_dir($mxpdev_folder)) {
    $folder = opendir($mxpdev_folder);
    while (($file = readdir($folder)) !== false) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $mxpdev_folder . $file;
            unlink($filePath);
        }
    }
    closedir($folder);
}
// 清除自動更新的設定
$asset        = 'mxp-dev-tools/index.php';
$option       = 'auto_update_plugins';
$auto_updates = (array) get_site_option($option, array());
$key          = array_search($asset, $auto_updates);
if ($key !== false) {
    unset($auto_updates[$key]);
    update_site_option($option, array_values($auto_updates));
}