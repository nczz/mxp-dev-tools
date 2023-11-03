<?php
/*
 * plugin should create a file named ‘uninstall.php’ in the base plugin folder. This file will be called, if it exists,
 * during the uninstall process bypassing the uninstall hook.
 * ref: https://developer.wordpress.org/reference/functions/register_uninstall_hook/
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
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
$option_prefix = 'mxp_dev_zipfile_';
$key           = $option_prefix . '%';
$sql           = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
$del           = $wpdb->query($wpdb->prepare($sql, $key));
$option_prefix = 'mxp_dev_mysqldump_file_';
$key           = $option_prefix . '%';
$sql           = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
$del           = $wpdb->query($wpdb->prepare($sql, $key));
// 清除超連結目錄中的檔案
$mxpdev_folder = WP_CONTENT_DIR . '/uploads/MXPDEV/';
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
