<?php
namespace MxpDevTools;

if (!defined('WPINC')) {
    die;
}

trait DatabaseOptimize {
    public function mxp_ajax_mysqldump() {
        $check_zip_module = (class_exists('ZipArchive') && method_exists('ZipArchive', 'open')) ? true : false;
        if (!$check_zip_module) {
            die('未安裝/啟用 PHP ZIP 模組，無法呼叫 ZipArchive 方法打包。');
        }
        set_time_limit(0);
        ini_set("memory_limit", "-1");
        if (!isset($_REQUEST['database']) || $_REQUEST['database'] == '') {
            die('沒指定匯出資料庫！');
        }
        if (!isset($_REQUEST['table']) || $_REQUEST['table'] == '') {
            die('沒指定匯出資料表！');
        }
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'mxp-mysqldump-' . $_REQUEST['database'] . '-' . $_REQUEST['table'])) {
            die('請求驗證有誤！');
        }
        $export_database = sanitize_text_field($_REQUEST['database']);
        $export_table    = sanitize_text_field($_REQUEST['table']);
        global $wpdb;
        $sql_name = $export_database . '-' . $export_table . '-' . date('Y-m-d-H-i-s') . '.sql';
        $tmp_dir  = rtrim(get_temp_dir(), DIRECTORY_SEPARATOR);
        if ((defined('MDT_TMP_DIR') && MDT_TMP_DIR != 'TMP') || !is_writable($tmp_dir)) {
            $tmp_dir = ABSPATH . DIRECTORY_SEPARATOR . "wp-content" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "MXPDEV";
            if (!file_exists($tmp_dir) && !is_dir($tmp_dir)) {
                mkdir($tmp_dir, 0777, true);
            }
        }
        $sql_full_path = $tmp_dir . DIRECTORY_SEPARATOR . $sql_name;
        $zip_file_name = $sql_name . '.zip';
        $zip_file_path = $tmp_dir . DIRECTORY_SEPARATOR . $zip_file_name;
        // error_log($sql_full_path);
        $fp = fopen($sql_full_path, 'w');
        fwrite($fp, '-- MXP MySQL Dump' . PHP_EOL);
        fwrite($fp, '-- version 1.0' . PHP_EOL);
        fwrite($fp, '-- https://www.mxp.tw/' . PHP_EOL);
        fwrite($fp, '-- 產生時間： ' . date('Y-m-d H:i:s') . PHP_EOL);
        fwrite($fp, '-- 伺服器版本： ' . $wpdb->db_version() . PHP_EOL);
        fwrite($fp, '-- PHP 版本： ' . phpversion() . PHP_EOL . PHP_EOL);
        fwrite($fp, 'SET SQL_MODE  = "NO_AUTO_VALUE_ON_ZERO";' . PHP_EOL);
        fwrite($fp, 'SET foreign_key_checks = 0;' . PHP_EOL);
        // fwrite($fp, 'SET sql_big_selects = 1;' . PHP_EOL);
        fwrite($fp, 'SET time_zone = "+00:00";' . PHP_EOL);
        fwrite($fp, '/*!40101 SET NAMES utf8mb4 */;' . PHP_EOL . PHP_EOL);
        $tables      = $wpdb->get_results("SHOW FULL TABLES FROM {$export_database}", ARRAY_A);
        $dump_tables = array();
        foreach ($tables as $tables_index => $_table) {
            $dump_tables[$_table['Tables_in_' . $export_database]] = $_table['Table_type'];
        }
        $tables_info = array();
        foreach ($dump_tables as $table_name => $table_type) {
            $results                  = $wpdb->get_results("DESC `{$export_database}`.`{$table_name}`");
            $tables_info[$table_name] = array();
            foreach ($results as $row) {
                // print_r("Field: " . $row->Field . PHP_EOL);
                // print_r("Type: " . $row->Type . PHP_EOL);
                $tables_info[$table_name][/*$row->Field*/] = strtoupper(current(explode('(', $row->Type)));
            }
        }
        $login_user = $wpdb->get_results("SELECT USER()", ARRAY_A);
        if ($export_table != "ALL") {
            $new_dump_tables = array();
            if (isset($dump_tables[$export_table])) {
                $new_dump_tables[$export_table] = $dump_tables[$export_table];
            }
            $dump_tables = $new_dump_tables;
        }
        foreach ($dump_tables as $table => $table_type) {
            if (strpos($table_type, 'TABLE') !== false) {
                $table_type = 'TABLE';
            }
            $schema     = $wpdb->get_results("SHOW CREATE {$table_type} `{$export_database}`.`{$table}`;", ARRAY_A);
            $schema_str = '';
            foreach ($schema[0] as $key => $query) {
                if (strpos($key, 'Create') === 0) {
                    $schema_str = $query;
                    if (in_array(strtoupper($table_type), array('VIEW', 'EVENTS', 'TRIGGERS', 'PROCEDURE', 'FUNCTION'))) {
                        $split      = explode(' ', $query);
                        $schema_str = '';
                        foreach ($split as $split_index => $query_part) {
                            if (strpos($query_part, 'DEFINER=') !== 0) {
                                $schema_str .= $query_part . ' ';
                            }
                        }
                        // $schema_str = preg_replace('~^([A-Z =]+) DEFINER=`' . preg_replace('~@(.*)~', '`@`(%|\1)', '.*@' . $wpdb->dbhost) . '`~', '\1', $query);
                    }
                }
            }
            fwrite($fp, "DROP {$table_type} IF EXISTS {$table};" . PHP_EOL . PHP_EOL);
            fwrite($fp, $schema_str . ";" . PHP_EOL . PHP_EOL);
            $offset = 0;
            while (true && $table_type == 'TABLE') {
                $inserts = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM `$export_database`.`{$table}` LIMIT %d, 50;", $offset)
                    , ARRAY_A);
                $count       = count($inserts);
                $fields_type = $tables_info[$table];
                foreach ($inserts as $index => $row) {
                    // $fields   = array_map(array($wpdb, '_real_escape'), array_keys($row));
                    // $data_set = array_map(array($wpdb, '_real_escape'), array_values($row));
                    $fields   = array_keys($row);
                    $data_set = array();
                    if ((strpos($table, "\r") !== false) || (strpos($table, "\n") !== false)) {
                        $table = str_replace(["\r", "\n"], ['\\r', '\\n'], $table);
                    }
                    $table = str_replace("`", "``", $table);
                    $query = "INSERT INTO `{$table}` (";
                    foreach ($fields as $field_index => $field) {
                        if ((strpos($field, "\r") !== false) || (strpos($field, "\n") !== false)) {
                            $field = str_replace(["\r", "\n"], ['\\r', '\\n'], $field);
                        }
                        $field = str_replace("`", "``", $field);
                        $query .= "`{$field}`";
                        if (count($fields) > $field_index + 1) {
                            $query .= ", ";
                        }
                        $data_set[] = $row[$field];
                    }
                    $query .= ") VALUES (";
                    foreach ($data_set as $data_set_index => $data) {
                        if (is_null($data)) {
                            $query .= 'NULL';
                        } else {
                            $field_type = $fields_type[$data_set_index];
                            if (strpos($field_type, 'BINARY') !== false) {
                                $field_type = 'BINARY';
                            }
                            if (strpos($field_type, 'BLOB') !== false) {
                                $field_type = 'BLOB';
                            }
                            switch ($field_type) {
                            case 'GEOMETRY':
                            case 'POINT':
                            case 'LINESTRING':
                            case 'POLYGON':
                            case 'MULTIPOINT':
                            case 'MULTILINESTRING':
                            case 'MULTIPOLYGON':
                            case 'GEOMETRYCOLLECTION':
                            case 'BLOB':
                            case 'BINARY':
                                $hexEncoded = bin2hex($data);
                                $data       = "x'$hexEncoded'";
                                $query .= $data;
                                break;
                            default:
                                $data = str_replace("\n", "\\n", addslashes($data));
                                $query .= "'{$data}'";
                                break;
                            }
                            // $data = $wpdb->remove_placeholder_escape($data);
                        }
                        if (count($data_set) > $data_set_index + 1) {
                            $query .= ", ";
                        }
                    }
                    $query .= ");";
                    fwrite($fp, $query . PHP_EOL);
                }
                if ($count < 50) {
                    break;
                }
                $offset += 50;
            }
            fwrite($fp, PHP_EOL);
        }
        fclose($fp);
        if (file_exists($sql_full_path)) {
            $zip           = new \ZipArchive();
            $zip_file_name = $sql_name . '.zip';
            $tmp_dir       = rtrim(get_temp_dir(), DIRECTORY_SEPARATOR);
            if ((defined('MDT_TMP_DIR') && MDT_TMP_DIR != 'TMP') || !is_writable($tmp_dir)) {
                $tmp_dir = ABSPATH . DIRECTORY_SEPARATOR . "wp-content" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "MXPDEV";
                if (!file_exists($tmp_dir) && !is_dir($tmp_dir)) {
                    mkdir($tmp_dir, 0777, true);
                }
            }
            $zip_file_path = $tmp_dir . DIRECTORY_SEPARATOR . $zip_file_name;
            $zip->open($zip_file_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($zip) {
                $zip->addFile($sql_full_path, $export_database . '-' . $export_table . "/" . $sql_name);
                // Ref: https://www.php.net/manual/en/zip.constants.php
                // if (method_exists($zip, 'setCompressionIndex')) {
                //     $zip->setCompressionName($sql_name, ZipArchive::CM_DEFLATE);
                // }
                $zip->close();
            }
            ob_clean();
            if (file_exists($zip_file_path)) {
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
                unlink($sql_full_path);
                exit;
            } else {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $sql_name . '"');
                header('Expires: 0');
                header('Cache-Control: no-cache');
                header('Pragma: public');
                header('Content-Length: ' . filesize($sql_full_path));
                header('Set-Cookie:fileLoading=true');
                header("Pragma: no-cache");
                // readfile($sql_full_path);
                echo file_get_contents($sql_full_path);
                unlink($sql_full_path);
                exit;
            }
        } else {
            die('匯出資料庫檔案不存在！');
        }
    }

    public function mxp_ajax_mysqldump_large() {
        $step = sanitize_text_field($_REQUEST['step']);
        if (empty($step)) {
            wp_send_json(array('success' => false, 'data' => array(), 'msg' => '缺少 step 階段的參數'));
            exit;
        }
        if (!isset($_REQUEST['database']) || $_REQUEST['database'] == '') {
            wp_send_json(array('success' => false, 'data' => array(), 'msg' => '沒指定匯出的資料庫！'));
            exit;
        }
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-ajax-nonce-for-db-optimize')) {
            wp_send_json(array('success' => false, 'data' => array(), 'msg' => '驗證請求失敗，請再試一次。'));
            exit;
        }
        //準備資料表資訊
        global $wpdb, $option_prefix, $dump_file_name, $dump_file_path, $database;
        $database       = sanitize_text_field($_REQUEST['database']);
        $dump_file_name = $database . '-' . date('Y-m-d-H-i-s') . '.sql';
        $tmp_dir        = rtrim(get_temp_dir(), DIRECTORY_SEPARATOR);
        if ((defined('MDT_TMP_DIR') && MDT_TMP_DIR != 'TMP') || !is_writable($tmp_dir)) {
            $tmp_dir = ABSPATH . DIRECTORY_SEPARATOR . "wp-content" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "MXPDEV";
            if (!file_exists($tmp_dir) && !is_dir($tmp_dir)) {
                mkdir($tmp_dir, 0777, true);
            }
        }
        $dump_file_path = $tmp_dir . DIRECTORY_SEPARATOR . $dump_file_name;
        $table          = $wpdb->options;
        $column         = 'option_name';
        $key_column     = 'option_id';
        $value_column   = 'option_value';

        if (is_multisite()) {
            $table        = $wpdb->sitemeta;
            $column       = 'meta_key';
            $key_column   = 'meta_id';
            $value_column = 'meta_value';
        }
        $option_prefix = 'mxp_dev_mysqldump_file_';
        //第一階段開始打包資料庫
        if ($step == 1) {
            set_time_limit(0);
            ini_set("memory_limit", "-1");
            //先清除原本的 options 紀錄
            $key = $option_prefix . '%';
            $sql = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
            $del = $wpdb->query($wpdb->prepare($sql, $key));
            if ($del === false) {
                wp_send_json(array('success' => false, 'data' => array(), 'msg' => '清除資料庫中 options 失敗，請再試一次。'));
                exit;
            }
            $mxpdev_folder = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR . '/uploads/MXPDEV/');
            // 清除超連結目錄中的檔案
            if (is_dir($mxpdev_folder)) {
                $folder = opendir($mxpdev_folder);
                // 逐一讀取資料夾內的檔案
                while (($file = readdir($folder)) !== false) {
                    // 忽略 "." 和 ".." 這兩個特殊目錄
                    if ($file !== '.' && $file !== '..') {
                        $filePath = $mxpdev_folder . $file;
                        // 刪除檔案
                        unlink($filePath);
                    }
                }
                // 關閉資料夾處理器
                closedir($folder);
            }
            include dirname(__FILE__) . '/Mysqldump.php';
            $dumpSettings = array(
                'compress' => \Ifsnop\Mysqldump\Mysqldump::NONE,
                // 'lock-tables'        => false,
                // 'single-transaction' => true,
            );
            if (function_exists('gzopen')) {
                $dumpSettings['compress'] = \Ifsnop\Mysqldump\Mysqldump::GZIP;
                $dump_file_name .= '.gz';
                $dump_file_path .= '.gz';
            }
            // if (function_exists('bzopen')){
            //     $dumpSettings['compress']=\Ifsnop\Mysqldump\Mysqldump::BZIP2;
            // }
            //準備中斷連線背景處理
            $detect = "NONE";
            $server = $_SERVER['SERVER_SOFTWARE'];
            if (preg_match('/nginx/i', $server)) {
                $detect = "METHOD_A";
            } else if (preg_match('/apache/i', $server)) {
                $detect = "METHOD_B";
            } else {
                $detect = "WTF";
            }
            // function errHandler() {
            //     file_put_contents(__DIR__ . '/shutdown.txt', $e->getTraceAsString(), FILE_APPEND | LOCK_EX);
            // }
            // register_shutdown_function('errHandler');
            ignore_user_abort(true);
            switch ($detect) {
            case 'METHOD_A':
                ob_end_clean();
                header("HTTP/1.1 200 OK\r\n");
                header("Connection: close\r\n");
                ob_start();
                echo json_encode(array('success' => true, 'data' => array('dump_file_name' => $dump_file_name, 'dump_file_path' => $dump_file_path), 'msg' => '打包資料中，請稍候。'));
                $size = ob_get_length();
                header("Content-Length: $size\r\n");
                header("Content-Encoding: application/json\r\n");
                ob_end_flush();
                ob_get_length() && ob_flush();
                flush();
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                break;
            case 'METHOD_B':
            case 'WTF':
            default:
                ob_end_clean();
                header("HTTP/1.1 200 OK\r\n");
                header("Connection: close\r\n");
                ob_start();
                echo json_encode(array('success' => true, 'data' => array('dump_file_name' => $dump_file_name, 'dump_file_path' => $dump_file_path), 'msg' => '打包資料中，請稍候。'));
                $size = ob_get_length();
                header("Content-Length: $size\r\n");
                header("Content-Encoding: application/json\r\n");
                ob_end_flush();
                ob_get_length() && ob_flush();
                flush();
                if (ob_get_length() > 0) {
                    ob_end_clean();
                }
                if (function_exists('apache_response_headers')) {
                    $headers = apache_response_headers();
                    header("Connection: close\r\n");
                    if (isset($headers['Content-Length'])) {
                        // header('Content-Length: ' . $headers['Content-Length'] . "\r\n");
                    }
                    if (ob_get_level() > 0) {
                        ob_end_flush();
                    }
                    flush();
                }
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                break;
            }
            session_write_close();
            $report = array(
                'progress' => 'init',
                'status'   => 'start',
                'filename' => $dump_file_name,
                'filepath' => $dump_file_path,
                'db'       => $database,
            );
            update_site_option($option_prefix . $dump_file_name, $report);
            try {
                $dump = new \Ifsnop\Mysqldump\Mysqldump('mysql:host=' . DB_HOST . ';dbname=' . $database, DB_USER, DB_PASSWORD, $dumpSettings);
                $dump->setInfoHook(function ($object, $info) {
                    if ($object === 'table') {
                        global $option_prefix, $dump_file_name, $dump_file_path, $database;
                        $check = \get_site_option($option_prefix . $dump_file_name, '');
                        if ($check !== '') {
                            // \error_log($dump_file_name . ' => ' . $info['name'] . ' ->' . $info['rowCount'] . PHP_EOL);
                            $report = array(
                                'progress' => $info['name'] . ' ->' . $info['rowCount'],
                                'status'   => 'progress',
                                'filename' => $dump_file_name,
                                'filepath' => $dump_file_path,
                                'db'       => $database,
                            );
                            \update_site_option($option_prefix . $dump_file_name, $report);
                        } else {
                            //被中斷作業了，暫停更新資訊
                            // \error_log('被中斷作業了，暫停更新資訊');
                        }
                    }
                });
                $dump->start($dump_file_path);
            } catch (\Exception $e) {
                $report = array(
                    'progress' => 'mysqldump error: ' . $e->getMessage(),
                    'status'   => 'error',
                    'filename' => $dump_file_name,
                    'filepath' => $dump_file_path,
                    'db'       => $database,
                );
                update_site_option($option_prefix . $dump_file_name, $report);
                exit;
            }
            //完成打包
            $report = array(
                'progress' => 'ALL DONE!',
                'status'   => 'done',
                'filename' => $dump_file_name,
                'filepath' => $dump_file_path,
                'db'       => $database,
            );
            update_site_option($option_prefix . $dump_file_name, $report);
            exit;
        }
        //檢查是否匯出打包完成
        if ($step == 2) {
            if (!isset($_REQUEST['dump_file_name']) || $_REQUEST['dump_file_name'] == '' || !isset($_REQUEST['dump_file_path']) || $_REQUEST['dump_file_path'] == '') {
                wp_send_json(array('success' => false, 'data' => array(), 'msg' => '缺少 step2 階段的打包檔案參數'));
                exit;
            }
            $dump_file_name = sanitize_text_field($_REQUEST['dump_file_name']);
            $dump_file_path = sanitize_text_field($_REQUEST['dump_file_path']);
            $check          = get_site_option($option_prefix . $dump_file_name, '');
            if ($check === '') {
                wp_send_json(array('success' => false, 'data' => array('dump_file_name' => $dump_file_name, 'dump_file_path' => $dump_file_path), 'msg' => '錯誤階段，尚未開始匯出打包作業。'));
                exit;
            }
            if (!isset($check['status']) || $check['status'] == '') {
                wp_send_json(array('success' => false, 'data' => array('dump_file_name' => $dump_file_name, 'dump_file_path' => $dump_file_path), 'msg' => '狀態資料格式錯誤'));
                exit;
            }
            switch ($check['status']) {
            case 'start':
                wp_send_json(array('success' => false, 'data' => array('progress' => $check['progress'], 'dump_file_name' => $dump_file_name, 'dump_file_path' => $dump_file_path), 'msg' => '初始化作業中'));
                exit;
                break;
            case 'progress':
                wp_send_json(array('success' => false, 'data' => array('progress' => $check['progress'], 'dump_file_name' => $dump_file_name, 'dump_file_path' => $dump_file_path), 'msg' => '匯出作業中'));
                exit;
                break;
            case 'done':
                if (connection_aborted()) {
                    wp_send_json(array('success' => false, 'data' => array(), 'msg' => '請求超時，重新確認中'));
                    exit;
                }
                //清除設定暫存
                $key = $option_prefix . '%';
                $sql = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
                $del = $wpdb->query($wpdb->prepare($sql, $key));
                if ($del !== false) {
                    if ($dump_file_path != '' && file_exists($dump_file_path)) {
                        // 檔案存在，建立超連結提供前端下載
                        $letters = 'abcdefghijklmnopqrstuvwxyz';
                        srand((double) microtime() * 1000000);
                        $salt = '';
                        for ($i = 1; $i <= rand(4, 12); $i++) {
                            $q    = rand(1, 24);
                            $salt = $salt . $letters[$q];
                        }
                        $new_file_name  = $salt . '-' . $dump_file_name;
                        $mxpdev_folder  = str_replace('/', DIRECTORY_SEPARATOR, '/uploads/MXPDEV/');
                        $wp_content_dir = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR);
                        $download_dir   = $wp_content_dir . $mxpdev_folder . $new_file_name;
                        if (!file_exists($wp_content_dir . $mxpdev_folder) && !is_dir($wp_content_dir . $mxpdev_folder)) {
                            mkdir($wp_content_dir . $mxpdev_folder, 0777, true);
                        }
                        $index_file = $wp_content_dir . $mxpdev_folder . 'index.html';
                        if (!file_exists($index_file)) {
                            touch($index_file);
                        }
                        if (is_link($download_dir)) {
                            unlink($download_dir);
                        }
                        $filesize = filesize($dump_file_path); // bytes
                        $filesize = round($filesize / 1024 / 1024, 1); // megabytes with 1 digit
                        if (function_exists('rename')) {
                            rename($dump_file_path, $download_dir);
                        } else {
                            if (function_exists('symlink')) {
                                symlink($dump_file_path, $download_dir);
                            } else {
                                wp_send_json(array('success' => false, 'data' => array(), 'msg' => '請聯絡網站伺服器管理員開放 symlink 或 rename 至少其中一個方法。'));
                                exit;
                            }
                        }
                        $upload_dir    = wp_upload_dir();
                        $download_link = $upload_dir['baseurl'] . "/MXPDEV/" . $new_file_name;
                        wp_send_json(array('success' => true, 'data' => array('download_link' => $download_link, 'filesize' => $filesize), 'msg' => '下載檔案中'));
                        exit;
                    } else {
                        wp_send_json(array('success' => false, 'data' => array(), 'msg' => '檔案不存在'));
                        exit;
                    }
                } else {
                    wp_send_json(array('success' => false, 'data' => array(), 'msg' => '刪除 options 失敗！'));
                    exit;
                }
                wp_send_json(array('success' => false, 'data' => array(), 'msg' => '完全不知道失敗在哪的錯誤！？'));
                exit;
                break;
            case 'error':
                wp_send_json(array('success' => false, 'data' => array('progress' => $check['progress'], 'dump_file_name' => $dump_file_name, 'dump_file_path' => $dump_file_path), 'msg' => '發生錯誤，匯出發生錯誤作業中斷'));
                exit;
                break;
            default:
                wp_send_json(array('success' => false, 'data' => array('progress' => $check['progress'], 'dump_file_name' => $dump_file_name, 'dump_file_path' => $dump_file_path), 'msg' => '未知的處理狀態'));
                exit;
                break;
            }
        }
        // 沒有第三階段了！！
        wp_send_json(array('success' => false, 'data' => array(), 'msg' => '沒有這一步喔！'));
        exit;
    }

    public function mxp_ajax_background_pack_action_batch_mode() {
        $check_zip_module = (class_exists('ZipArchive') && method_exists('ZipArchive', 'open')) ? true : false;
        if (!$check_zip_module) {
            echo json_encode(array('success' => false, 'data' => array(), 'msg' => '未安裝/啟用 PHP ZIP 模組，無法呼叫 ZipArchive 方法打包。'));
            exit;
        }
        if (!is_super_admin()) {
            echo json_encode(array('success' => false, 'data' => array(), 'msg' => '此功能僅限網站最高權限管理人員使用！'));
            exit;
        }
        $step = sanitize_text_field($_REQUEST['step']);
        if ($step == '') {
            echo json_encode(array('success' => false, 'data' => array(), 'msg' => '缺少 step 階段的參數'));
            exit;
        }
        //準備資料表資訊
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
        //第一階段先取得所有要打包的檔案資訊
        if ($step == 0) {
            $path         = sanitize_text_field($_REQUEST['path']);
            $type         = sanitize_text_field($_REQUEST['type']);
            $context      = sanitize_text_field($_REQUEST['context']);
            $exclude_path = sanitize_text_field($_REQUEST['exclude_path']);
            //僅接受資料夾格式的打包
            if (empty($path) || empty($type) || !in_array($type, array('folder'), true) || empty($context) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-download-current-plugins-' . $path)) {
                echo json_encode(array('success' => false, 'data' => array(), 'msg' => '驗證請求失敗，請再試一次。'));
                exit;
            }
            $path = base64_decode($path, true);
            if ($path == false) {
                echo json_encode(array('success' => false, 'data' => array(), 'msg' => '路徑驗證請求失敗，請再試一次。'));
                exit;
            }
            if (!empty($exclude_path)) {
                $exclude_path = base64_decode($exclude_path, true);
                if ($exclude_path == false) {
                    echo json_encode(array('success' => false, 'data' => array(), 'msg' => '排除路徑驗證請求失敗，請再試一次。'));
                    exit;
                }
            }
            set_time_limit(0);
            ini_set("memory_limit", "-1");
            //準備中斷連線背景處理
            $detect = "NONE";
            $server = $_SERVER['SERVER_SOFTWARE'];
            if (preg_match('/nginx/i', $server)) {
                $detect = "METHOD_A";
            } else if (preg_match('/apache/i', $server)) {
                $detect = "METHOD_B";
            } else {
                $detect = "WTF";
            }
            ignore_user_abort(true);
            switch ($detect) {
            case 'METHOD_A':
                ob_end_clean();
                header("HTTP/1.1 200 OK\r\n");
                header("Connection: close\r\n");
                ob_start();
                echo json_encode(array('success' => true, 'data' => array('step' => 0), 'msg' => '準備打包資料中，請稍候。'));
                $size = ob_get_length();
                header("Content-Length: $size\r\n");
                header("Content-Encoding: application/json\r\n");
                ob_end_flush();
                ob_get_length() && ob_flush();
                flush();
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                break;
            case 'METHOD_B':
            case 'WTF':
            default:
                ob_end_clean();
                header("HTTP/1.1 200 OK\r\n");
                header("Connection: close\r\n");
                ob_start();
                echo json_encode(array('success' => true, 'data' => array('step' => 0), 'msg' => '準備打包資料中，請稍候。'));
                $size = ob_get_length();
                header("Content-Length: $size\r\n");
                header("Content-Encoding: application/json\r\n");
                ob_end_flush();
                ob_get_length() && ob_flush();
                flush();
                if (ob_get_length() > 0) {
                    ob_end_clean();
                }
                if (function_exists('apache_response_headers')) {
                    $headers = apache_response_headers();
                    header("Connection: close\r\n");
                    if (isset($headers['Content-Length'])) {
                        // header('Content-Length: ' . $headers['Content-Length'] . "\r\n");
                    }
                    if (ob_get_level() > 0) {
                        ob_end_flush();
                    }
                    flush();
                }
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                break;
            }
            session_write_close();
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(dirname($path)),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            $batch_array      = [];
            $it_count         = 0;
            $total_file_count = iterator_count($files);
            $zip_file_name    = '';
            $zip_file_path    = '';
            $split_path       = explode(DIRECTORY_SEPARATOR, $path);
            $zip_file_name    = $split_path[count($split_path) - 2] . '.zip';
            $relative_path    = realpath(dirname($path) . '/..'); //for support php5.3 up | dirname($path, 2) php7.0 up;
            $tmp_dir          = rtrim(get_temp_dir(), DIRECTORY_SEPARATOR);
            if ((defined('MDT_TMP_DIR') && MDT_TMP_DIR != 'TMP') || !is_writable($tmp_dir)) {
                $tmp_dir = ABSPATH . DIRECTORY_SEPARATOR . "wp-content" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "MXPDEV";
                if (!file_exists($tmp_dir) && !is_dir($tmp_dir)) {
                    mkdir($tmp_dir, 0777, true);
                }
            }
            $zip_file_path = $tmp_dir . DIRECTORY_SEPARATOR . $zip_file_name;
            //清除當前打包檔案
            if (file_exists($zip_file_path)) {
                unlink($zip_file_path);
            }
            //先清除原本的 options 紀錄
            $key = $option_prefix . $zip_file_name . '_%';
            $sql = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
            $del = $wpdb->query($wpdb->prepare($sql, $key));
            if ($del === false) {
                // echo json_encode(array('success' => false, 'data' => array(), 'msg' => '清除資料庫中 options 失敗，請再試一次。'));
                update_site_option($step_0_option_name, array('success' => false, 'data' => array(), 'msg' => '清除資料庫中 options 失敗，請再試一次。'));
                exit;
            }
            $wp_content_dir = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR);
            $mxpdev_folder  = $wp_content_dir . str_replace('/', DIRECTORY_SEPARATOR, '/uploads/MXPDEV/');
            // 清除超連結目錄中的檔案
            if (is_dir($mxpdev_folder)) {
                $folder = opendir($mxpdev_folder);
                // 逐一讀取資料夾內的檔案
                while (($file = readdir($folder)) !== false) {
                    // 忽略 "." 和 ".." 這兩個特殊目錄
                    if ($file !== '.' && $file !== '..') {
                        $filePath = $mxpdev_folder . $file;
                        // 刪除檔案
                        unlink($filePath);
                    }
                }
                // 關閉資料夾處理器
                closedir($folder);
            }
            $zip = new \ZipArchive();
            $zip->open($zip_file_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if (!$zip) {
                update_site_option($step_0_option_name, array('success' => false, 'data' => array(), 'msg' => 'ZIP壓縮程式執行錯誤'));
                exit;
            }
            $zip->addFromString('readme.txt', 'Created by Chun. https://tw.wordpress.org/plugins/mxp-dev-tools/');
            $zip->close();
            $split_num   = MDT_PACK_LARGE_SPLIT_NUM;
            $save_times  = 0;
            $option_keys = [];
            $add_flag    = true;
            foreach ($files as $index => $file) {
                if (!$file->isDir()) {
                    $file_path = $file->getRealPath();
                    $add_flag  = true;
                    if ($exclude_path != '' && strpos($file_path, $exclude_path) !== false) {
                        $add_flag = false;
                    }
                    $zip_relative_path = str_replace($relative_path . DIRECTORY_SEPARATOR, '', $file_path);
                    // 預設就排除打包進去的資料夾（快取、備份類型）
                    $default_exclude_dirs = apply_filters(
                        'mxp_dev_default_exclude_dirs',
                        array(
                            'MXPDEV',
                            'wp-content/uploads/MXPDEV',
                            'wp-content/uploads/backwpup',
                            'wp-content/uploads/backup',
                            'wp-content/backup',
                            'wp-content/cache',
                            'wp-content/wpvivid',
                            'wp-content/wpvivid_image_optimization',
                            'wp-content/wpvivid_staging',
                            'wp-content/wpvivid_uploads',
                            'wp-content/wpvividbackups',
                            'wp-content/ai1wm-backups',
                            'wp-content/updraft',
                            'wp-content/backups-dup',
                            'wp-content/backup-migration',
                            'wp-content/backuply',
                            'wp-content/plugins/akeebabackupwp',
                        )
                    );
                    $default_exclude_dirs = array_map(function ($path) {
                        return str_replace('/', DIRECTORY_SEPARATOR, $path);
                    }, $default_exclude_dirs);
                    if (!empty($default_exclude_dirs)) {
                        foreach ($default_exclude_dirs as $default_exclude_dir) {
                            if (strpos($zip_relative_path, $default_exclude_dir) === 0) {
                                $add_flag = false;
                                break;
                            }
                        }
                    }
                    if ($add_flag && $file_path != '' && $zip_relative_path != '') {
                        //批次打包
                        $batch_array[] = array($file_path, $zip_relative_path, $it_count);
                        if (count($batch_array) == $split_num) {
                            $item = array(
                                'zip_file_name' => $zip_file_name,
                                'zip_file_path' => $zip_file_path,
                                'file_paths'    => $batch_array,
                                'status'        => 'addfile',
                            );
                            $key        = $option_prefix . $zip_file_name . '_%';
                            $sql        = 'SELECT count(*) FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
                            $save_times = $wpdb->get_var($wpdb->prepare($sql, $key));
                            update_site_option($option_prefix . $zip_file_name . '_' . $save_times, $item);
                            $option_keys[] = $option_prefix . $zip_file_name . '_' . $save_times;
                            $save_times += 1;
                            $batch_array = []; //清空
                        }
                    }
                }
                if ($add_flag) {
                    $it_count += 1;
                }
            }
            if (!empty($batch_array)) {
                // 剩下就用這個送
                $item = array(
                    'zip_file_name' => $zip_file_name,
                    'zip_file_path' => $zip_file_path,
                    'file_paths'    => $batch_array,
                    'status'        => 'addfile',
                );
                $key        = $option_prefix . $zip_file_name . '_%';
                $sql        = 'SELECT count(*) FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
                $save_times = $wpdb->get_var($wpdb->prepare($sql, $key));
                update_site_option($option_prefix . $zip_file_name . '_' . $save_times, $item);
                $option_keys[] = $option_prefix . $zip_file_name . '_' . $save_times;
            }
            update_site_option($step_0_option_name, array(
                'success' => true,
                'data'    => array(
                    'zip_file_name' => $zip_file_name,
                    'zip_file_path' => $zip_file_path,
                    'option_keys'   => $option_keys,
                ),
                'msg'     => '第一階段取得壓縮資訊成功！',
            ));
            exit;
        }
        // 取得背景打包的必要資訊
        if ($step == 1) {
            $data = get_site_option($step_0_option_name, "");
            if ($data == '') {
                echo json_encode(array('success' => false, 'data' => array(), 'msg' => '等待更新中，請稍候'));
                exit;
            } else {
                echo json_encode($data);
                $sql = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
                $del = $wpdb->query($wpdb->prepare($sql, $step_0_option_name));
                exit;
            }
        }
        //取得批次打包的資訊後開始打包
        if ($step == 2) {
            $zip_file_name = sanitize_text_field($_REQUEST['zip_file_name']);
            $zip_file_path = sanitize_text_field($_REQUEST['zip_file_path']);
            $option_key    = sanitize_text_field($_REQUEST['option_key']);
            $path          = sanitize_text_field($_REQUEST['path']);
            $type          = sanitize_text_field($_REQUEST['type']);
            if (empty($zip_file_name) || empty($zip_file_path) || !in_array($type, array('folder'), true) || empty($option_key) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-download-current-plugins-' . $path)) {
                echo json_encode(array('success' => false, 'data' => array(), 'msg' => '驗證請求失敗，請再試一次。'));
                exit;
            }
            $item = get_site_option($option_key, '');
            if ($item == '') {
                echo json_encode(array('success' => false, 'data' => array(), 'msg' => '傳入資料有誤，請再次確認！'));
                exit;
            }

            $zip = new \ZipArchive;
            $zip->open($item['zip_file_path'], \ZipArchive::CREATE);
            foreach ($item['file_paths'] as $key => $file_path) {
                $fileToAdd = $file_path[0];
                $fileInfo  = $zip->statName($file_path[0]);
                if (!$fileInfo) {
                    $zip->addFile($file_path[0], $file_path[1]);
                }
            }
            $zip->close();

            $key = $option_prefix . $item['zip_file_name'] . '_%';

            $sql = '
            SELECT COUNT(*)
            FROM ' . $table . '
            WHERE ' . $column . ' LIKE %s
            ORDER BY ' . $key_column . ' ASC
            ';
            $sp_count    = explode('_', $option_key);
            $current_num = end($sp_count);

            $total_batch_count = $wpdb->get_var($wpdb->prepare($sql, $key));
            $percent           = intval(round(((intval($current_num)) / intval($total_batch_count)), 2) * 100);
            echo json_encode(array('success' => true, 'data' => array(intval($current_num + 1), intval($total_batch_count)), 'msg' => $percent));

            exit;
        }
        //打包下載與刪除options
        if ($step == 3) {
            $zip_file_name = sanitize_text_field($_REQUEST['zip_file_name']);
            $zip_file_path = sanitize_text_field($_REQUEST['zip_file_path']);
            $path          = sanitize_text_field($_REQUEST['path']);
            $type          = sanitize_text_field($_REQUEST['type']);
            if (empty($zip_file_name) || empty($zip_file_path) || !in_array($type, array('folder'), true) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-download-current-plugins-' . $path)) {
                echo json_encode(array('success' => false, 'data' => array(), 'msg' => '驗證請求失敗，請再試一次。'));
                exit;
            }

            $key = $option_prefix . $zip_file_name . '_%';
            $sql = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
            $del = $wpdb->query($wpdb->prepare($sql, $key));
            if ($del !== false) {
                if ($zip_file_path != '' && file_exists($zip_file_path)) {
                    // 檔案存在，建立超連結提供前端下載
                    $letters = 'abcdefghijklmnopqrstuvwxyz';
                    srand((double) microtime() * 1000000);
                    $salt = '';
                    for ($i = 1; $i <= rand(4, 12); $i++) {
                        $q    = rand(1, 24);
                        $salt = $salt . $letters[$q];
                    }
                    $new_file_name  = $salt . '-' . $zip_file_name;
                    $path_to_mxpdev = str_replace('/', DIRECTORY_SEPARATOR, '/uploads/MXPDEV/');
                    $wp_content_dir = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR);
                    $download_dir   = $wp_content_dir . $path_to_mxpdev . $new_file_name;
                    if (!file_exists($wp_content_dir . $path_to_mxpdev) && !is_dir($wp_content_dir . $path_to_mxpdev)) {
                        mkdir($wp_content_dir . $path_to_mxpdev, 0777, true);
                    }
                    $index_file = $wp_content_dir . $path_to_mxpdev . 'index.html';
                    if (!file_exists($index_file)) {
                        touch($index_file);
                    }
                    if (is_link($download_dir)) {
                        unlink($download_dir);
                    }
                    $filesize = filesize($zip_file_path); // bytes
                    $filesize = round($filesize / 1024 / 1024, 1); // megabytes with 1 digit
                    if (function_exists('rename')) {
                        rename($zip_file_path, $download_dir);
                    } else {
                        if (function_exists('symlink')) {
                            symlink($zip_file_path, $download_dir);
                        } else {
                            wp_send_json(array('success' => false, 'data' => array(), 'msg' => '請聯絡網站伺服器管理員開放 symlink 或 rename 至少其中一個方法。'));
                            exit;
                        }
                    }
                    $upload_dir    = wp_upload_dir();
                    $download_link = $upload_dir['baseurl'] . "/MXPDEV/" . $new_file_name;
                    echo json_encode(array('success' => true, 'data' => array('download_link' => $download_link, 'filesize' => $filesize), 'msg' => '下載檔案中'));
                    exit;
                } else {
                    echo json_encode(array('success' => false, 'data' => array(), 'msg' => '檔案不存在'));
                    exit;
                }
            } else {
                echo json_encode(array('success' => false, 'data' => array(), 'msg' => '刪除 options 失敗！'));
                exit;
            }
            echo json_encode(array('success' => false, 'data' => array(), 'msg' => '完全不知道失敗在哪的錯誤！？'));
            exit;
        }
        echo json_encode(array('success' => false, 'data' => array(), 'msg' => '沒有這一步喔！'));
        exit;
    }

    public function mxp_ajax_background_pack_action() {
        $check_zip_module = (class_exists('ZipArchive') && method_exists('ZipArchive', 'open')) ? true : false;
        if (!$check_zip_module) {
            wp_send_json(array('success' => false, 'data' => array(), 'msg' => '未安裝/啟用 PHP ZIP 模組，無法呼叫 ZipArchive 方法打包。'));
            exit;
        }
        if (!is_super_admin()) {
            wp_send_json(array('success' => false, 'data' => array(), 'msg' => '此功能僅限網站最高權限管理人員使用！'));
            exit;
        }
        $step = sanitize_text_field($_REQUEST['step']);
        if ($step == '') {
            wp_send_json(array('success' => false, 'data' => array(), 'msg' => '缺少 step 階段的參數'));
            exit;
        }
        $path         = sanitize_text_field($_REQUEST['path']);
        $type         = sanitize_text_field($_REQUEST['type']);
        $context      = sanitize_text_field($_REQUEST['context']);
        $exclude_path = sanitize_text_field($_REQUEST['exclude_path']);
        //僅接受資料夾格式的打包
        if (empty($path) || empty($type) || !in_array($type, array('folder'), true) || empty($context) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-download-current-plugins-' . $path)) {
            wp_send_json(array('success' => false, 'data' => array(), 'msg' => '驗證請求失敗，請再試一次。'));
            exit;
        }
        $path = base64_decode($path, true);
        if ($path == false) {
            wp_send_json(array('success' => false, 'data' => array(), 'msg' => '路徑驗證請求失敗，請再試一次。'));
            exit;
        }
        if (!empty($exclude_path)) {
            $exclude_path = base64_decode($exclude_path, true);
            if ($exclude_path == false) {
                wp_send_json(array('success' => false, 'data' => array(), 'msg' => '排除路徑驗證請求失敗，請再試一次。'));
                exit;
            }
        }
        //準備資料表資訊
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

        //第一階段先取得所有要打包的檔案資訊
        if ($step == 0) {
            // 準備背景處理打包
            set_time_limit(0);
            ini_set("memory_limit", "-1");
            //準備中斷連線背景處理
            $detect = "NONE";
            $server = $_SERVER['SERVER_SOFTWARE'];
            if (preg_match('/nginx/i', $server)) {
                $detect = "METHOD_A";
            } else if (preg_match('/apache/i', $server)) {
                $detect = "METHOD_B";
            } else {
                $detect = "WTF";
            }
            ignore_user_abort(true);
            switch ($detect) {
            case 'METHOD_A':
                ob_end_clean();
                header("HTTP/1.1 200 OK\r\n");
                header("Connection: close\r\n");
                ob_start();
                echo json_encode(array('success' => true, 'data' => array('step' => 0), 'msg' => '準備打包資料中，請稍候。'));
                $size = ob_get_length();
                header("Content-Length: $size\r\n");
                header("Content-Encoding: application/json\r\n");
                ob_end_flush();
                ob_get_length() && ob_flush();
                flush();
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                break;
            case 'METHOD_B':
            case 'WTF':
            default:
                ob_end_clean();
                header("HTTP/1.1 200 OK\r\n");
                header("Connection: close\r\n");
                ob_start();
                echo json_encode(array('success' => true, 'data' => array('step' => 0), 'msg' => '準備打包資料中，請稍候。'));
                $size = ob_get_length();
                header("Content-Length: $size\r\n");
                header("Content-Encoding: application/json\r\n");
                ob_end_flush();
                ob_get_length() && ob_flush();
                flush();
                if (ob_get_length() > 0) {
                    ob_end_clean();
                }
                if (function_exists('apache_response_headers')) {
                    $headers = apache_response_headers();
                    header("Connection: close\r\n");
                    if (isset($headers['Content-Length'])) {
                        // header('Content-Length: ' . $headers['Content-Length'] . "\r\n");
                    }
                    if (ob_get_level() > 0) {
                        ob_end_flush();
                    }
                    flush();
                }
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                break;
            }
            session_write_close();
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(dirname($path)),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            $batch_array      = [];
            $it_count         = 0;
            $total_file_count = iterator_count($files);
            $zip_file_name    = '';
            $zip_file_path    = '';
            $split_path       = explode(DIRECTORY_SEPARATOR, $path);
            $zip_file_name    = $split_path[count($split_path) - 2] . '.zip';
            $relative_path    = realpath(dirname($path) . '/..'); //for support php5.3 up | dirname($path, 2) php7.0 up;
            $tmp_dir          = rtrim(get_temp_dir(), DIRECTORY_SEPARATOR);
            if ((defined('MDT_TMP_DIR') && MDT_TMP_DIR != 'TMP') || !is_writable($tmp_dir)) {
                $tmp_dir = ABSPATH . DIRECTORY_SEPARATOR . "wp-content" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "MXPDEV";
                if (!file_exists($tmp_dir) && !is_dir($tmp_dir)) {
                    mkdir($tmp_dir, 0777, true);
                }
            }
            $zip_file_path = $tmp_dir . DIRECTORY_SEPARATOR . $zip_file_name;
            //清除當前打包檔案
            if (file_exists($zip_file_path)) {
                unlink($zip_file_path);
            }
            //先清除原本的 options 紀錄
            $key = $option_prefix . $zip_file_name . '_%';
            $sql = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
            $del = $wpdb->query($wpdb->prepare($sql, $key));
            if ($del === false) {
                update_site_option($step_0_option_name, array('success' => false, 'data' => array(), 'msg' => '清除資料庫中 options 失敗，請再試一次。'));
                exit;
            }
            $wp_content_dir = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR);
            $mxpdev_folder  = $wp_content_dir . str_replace('/', DIRECTORY_SEPARATOR, '/uploads/MXPDEV/');
            // 清除超連結目錄中的檔案
            if (is_dir($mxpdev_folder)) {
                $folder = opendir($mxpdev_folder);
                // 逐一讀取資料夾內的檔案
                while (($file = readdir($folder)) !== false) {
                    // 忽略 "." 和 ".." 這兩個特殊目錄
                    if ($file !== '.' && $file !== '..') {
                        $filePath = $mxpdev_folder . $file;
                        // 刪除檔案
                        unlink($filePath);
                    }
                }
                // 關閉資料夾處理器
                closedir($folder);
            }
            $zip = new \ZipArchive();
            $zip->open($zip_file_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if (!$zip) {
                update_site_option($step_0_option_name, array('success' => false, 'data' => array(), 'msg' => 'ZIP壓縮程式執行錯誤'));
                exit;
            }
            $zip->addFromString('readme.txt', 'Created by Chun. https://tw.wordpress.org/plugins/mxp-dev-tools/');

            $split_num   = MDT_PACK_LARGE_SPLIT_NUM;
            $save_times  = 0;
            $option_keys = [];
            $add_flag    = true;
            foreach ($files as $index => $file) {
                if (!$file->isDir()) {
                    $file_path = $file->getRealPath();
                    $add_flag  = true;
                    if ($exclude_path != '' && strpos($file_path, $exclude_path) !== false) {
                        $add_flag = false;
                    }
                    $zip_relative_path = str_replace($relative_path . DIRECTORY_SEPARATOR, '', $file_path);
                    // 預設就排除打包進去的資料夾（快取、備份類型）
                    $default_exclude_dirs = apply_filters(
                        'mxp_dev_default_exclude_dirs',
                        array(
                            'MXPDEV',
                            'wp-content/uploads/MXPDEV',
                            'wp-content/uploads/backwpup',
                            'wp-content/uploads/backup',
                            'wp-content/backup',
                            'wp-content/cache',
                            'wp-content/wpvivid',
                            'wp-content/wpvivid_image_optimization',
                            'wp-content/wpvivid_staging',
                            'wp-content/wpvivid_uploads',
                            'wp-content/wpvividbackups',
                            'wp-content/ai1wm-backups',
                            'wp-content/updraft',
                            'wp-content/backups-dup',
                            'wp-content/backup-migration',
                            'wp-content/backuply',
                            'wp-content/plugins/akeebabackupwp',
                        )
                    );
                    $default_exclude_dirs = array_map(function ($path) {
                        return str_replace('/', DIRECTORY_SEPARATOR, $path);
                    }, $default_exclude_dirs);
                    if (!empty($default_exclude_dirs)) {
                        foreach ($default_exclude_dirs as $default_exclude_dir) {
                            if (strpos($zip_relative_path, $default_exclude_dir) === 0) {
                                $add_flag = false;
                                break;
                            }
                        }
                    }
                    if ($add_flag && $file_path != '' && $zip_relative_path != '') {
                        //批次顯示打包資訊用
                        $batch_array[] = array($file_path, $zip_relative_path, $it_count);
                        $fileInfo      = $zip->statName($file_path);
                        if (!$fileInfo) {
                            $zip->addFile($file_path, str_replace(DIRECTORY_SEPARATOR, '/', $zip_relative_path));
                        }
                        if (count($batch_array) == $split_num) {
                            update_site_option($step_0_option_name, array(
                                'success' => false,
                                'data'    => array(
                                    'zip_file_name' => $zip_file_name,
                                    'zip_file_path' => $zip_file_path,
                                    'file_paths'    => $batch_array,
                                    'status'        => 'addfile',
                                ),
                                'msg'     => '打包中',
                            ));
                            $batch_array = []; //清空
                        }
                    }
                }
                if ($add_flag) {
                    $it_count += 1;
                }
            }
            update_site_option($step_0_option_name, array(
                'success' => false,
                'data'    => array(
                    'zip_file_name' => $zip_file_name,
                    'zip_file_path' => $zip_file_path,
                    'file_paths'    => [],
                    'status'        => 'finish',
                ),
                'msg'     => '打包完成準備下載中。',
            ));

            $zip->close();

            update_site_option($step_0_option_name, array(
                'success' => true,
                'data'    => array(
                    'zip_file_name' => $zip_file_name,
                    'zip_file_path' => $zip_file_path,
                    'file_paths'    => [],
                    'status'        => 'done',
                ),
                'msg'     => '打包完成準備下載中。',
            ));
            exit;
        }
        // 取得背景打包的必要資訊
        if ($step == 1) {
            $data = get_site_option($step_0_option_name, "");
            if ($data === '') {
                $resp = array(
                    'success' => false,
                    'data'    => array(
                        'zip_file_name' => '',
                        'zip_file_path' => '',
                        'file_paths'    => [],
                        'status'        => 'addfile',
                    ),
                    'msg'     => '打包準備中...',
                );
                wp_send_json($resp);
                exit;
            } else {
                $status = $data['data']['status'];
                if ($status !== 'done') {
                    wp_send_json($data);
                    exit;
                }
                $zip_file_path = $data['data']['zip_file_path'];
                $zip_file_name = $data['data']['zip_file_name'];
                $sql           = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
                $del           = $wpdb->query($wpdb->prepare($sql, $step_0_option_name));
                if ($del !== false) {
                    if ($zip_file_path != '' && file_exists($zip_file_path)) {
                        // 檔案存在，建立超連結提供前端下載
                        $letters = 'abcdefghijklmnopqrstuvwxyz';
                        srand((double) microtime() * 1000000);
                        $salt = '';
                        for ($i = 1; $i <= rand(4, 12); $i++) {
                            $q    = rand(1, 24);
                            $salt = $salt . $letters[$q];
                        }
                        $new_file_name  = $salt . '-' . $zip_file_name;
                        $path_to_mxpdev = str_replace('/', DIRECTORY_SEPARATOR, '/uploads/MXPDEV/');
                        $wp_content_dir = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR);
                        $download_dir   = $wp_content_dir . $path_to_mxpdev . $new_file_name;
                        if (!file_exists($wp_content_dir . $path_to_mxpdev) && !is_dir($wp_content_dir . $path_to_mxpdev)) {
                            mkdir($wp_content_dir . $path_to_mxpdev, 0777, true);
                        }
                        $index_file = $wp_content_dir . $path_to_mxpdev . 'index.html';
                        if (!file_exists($index_file)) {
                            touch($index_file);
                        }
                        if (is_link($download_dir)) {
                            unlink($download_dir);
                        }
                        $filesize = filesize($zip_file_path); // bytes
                        $filesize = round($filesize / 1024 / 1024, 1); // megabytes with 1 digit
                        if (function_exists('rename')) {
                            rename($zip_file_path, $download_dir);
                        } else {
                            if (function_exists('symlink')) {
                                symlink($zip_file_path, $download_dir);
                            } else {
                                wp_send_json(array('success' => false, 'data' => array(), 'msg' => '請聯絡網站伺服器管理員開放 symlink 或 rename 至少其中一個方法。'));
                                exit;
                            }
                        }
                        $upload_dir    = wp_upload_dir();
                        $download_link = $upload_dir['baseurl'] . "/MXPDEV/" . $new_file_name;
                        wp_send_json(array('success' => true, 'data' => array('download_link' => $download_link, 'filesize' => $filesize, 'status' => 'download'), 'msg' => '下載檔案中'));
                        exit;
                    } else {
                        wp_send_json(array('success' => false, 'data' => array(), 'msg' => '檔案不存在'));
                        exit;
                    }
                } else {
                    wp_send_json(array('success' => false, 'data' => array(), 'msg' => '刪除 options 失敗！'));
                    exit;
                }
            }
            exit;
        }
        //不管有幾步驟，都要記得離開這程序
        exit;
    }

    public function mxp_ajax_db_optimize() {
        set_time_limit(0);
        if (!isset($_REQUEST['step']) || (intval($_REQUEST['step']) != 1 && intval($_REQUEST['step']) != 2)) {
            wp_send_json_error('請求參數有誤！');
        }
        $step      = sanitize_text_field($_REQUEST['step']);
        $force     = false;
        $search_op = array();
        if ($step == 2 && isset($_REQUEST['search_op']) && is_array($_REQUEST['search_op'])) {
            $search_op = array_map('sanitize_text_field', $_REQUEST['search_op']);
            $force     = sanitize_text_field($_REQUEST['force']);
            if ($force != '0') {
                $force = true;
            }
        }
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-ajax-nonce-for-db-optimize')) {
            wp_send_json_error('請求驗證有誤！');
        }
        global $wpdb;
        if ($step == 1) {
            //預設的設定資料欄位不清除
            $default_option_name = array('active_plugins', 'admin_email', 'admin_email_lifespan', 'auto_plugin_theme_update_emails', 'auto_update_core_dev', 'auto_update_core_major', 'auto_update_core_minor', 'avatar_default', 'avatar_rating', 'blog_charset', 'blog_public', 'blogdescription', 'blogname', 'category_base', 'close_comments_days_old', 'close_comments_for_old_posts', 'comment_max_links', 'comment_moderation', 'comment_order', 'comment_previously_approved', 'comment_registration', 'comments_notify', 'comments_per_page', 'cron', 'date_format', 'db_version', 'default_category', 'default_comment_status', 'default_comments_page', 'default_email_category', 'default_link_category', 'default_ping_status', 'default_pingback_flag', 'default_post_format', 'default_role', 'disallowed_keys', 'finished_splitting_shared_terms', 'fresh_site', 'gmt_offset', 'hack_file', 'home', 'html_type', 'https_detection_errors', 'image_default_align', 'image_default_link_type', 'image_default_size', 'initial_db_version', 'large_size_h', 'large_size_w', 'link_manager_enabled', 'links_updated_date_format', 'mailserver_login', 'mailserver_pass', 'mailserver_port', 'mailserver_url', 'medium_large_size_h', 'medium_large_size_w', 'medium_size_h', 'medium_size_w', 'moderation_keys', 'moderation_notify', 'page_comments', 'page_for_posts', 'page_on_front', 'permalink_structure', 'ping_sites', 'posts_per_page', 'posts_per_rss', 'recently_edited', 'recovery_keys', 'require_name_email', 'rewrite_rules', 'rss_use_excerpt', 'show_avatars', 'show_comments_cookies_opt_in', 'show_on_front', 'sidebars_widgets', 'site_icon', 'siteurl', 'start_of_week', 'sticky_posts', 'stylesheet', 'tag_base', 'template', 'thread_comments', 'thread_comments_depth', 'thumbnail_crop', 'thumbnail_size_h', 'thumbnail_size_w', 'time_format', 'timezone_string', 'uninstall_plugins', 'upload_path', 'upload_url_path', 'uploads_use_yearmonth_folders', 'use_balanceTags', 'use_smilies', 'use_trackback', 'user_count', 'users_can_register', 'wp_force_deactivated_plugins', 'wp_page_for_privacy_policy', 'WPLANG');
            // 撈出全部欄位
            $all_option_name = $wpdb->get_results("SELECT option_id,option_name FROM {$wpdb->options}", ARRAY_A);
            // 撈出空值欄位
            $empty_value_options = $wpdb->get_results("SELECT * FROM {$wpdb->options} WHERE option_value IS NULL OR option_value =''", ARRAY_A);
            // 最後比對的欄位組
            $search_op            = array();
            $current_theme        = get_option('stylesheet');
            $current_parent_theme = get_option('template');
            // 最後刪除的欄位
            $deleted_options = array();
            foreach ($empty_value_options as $key => $ops) {
                if (in_array($ops['option_name'], $default_option_name, true)) {
                    continue;
                }
                $search_op[$ops['option_name']] = $ops['option_id'];
            }
            foreach ($all_option_name as $key => $ops) {
                if (in_array($ops['option_name'], $default_option_name, true)) {
                    continue;
                }
                if (strpos($ops['option_name'], '_site_transient_timeout_') === 0) {
                    $del_transient     = str_replace('_timeout', '', $ops['option_name']);
                    $deleted_options[] = $del_transient;
                    $deleted_options[] = $ops['option_name'];
                    delete_option($del_transient);
                    delete_option($ops['option_name']);
                }
                if (strpos($ops['option_name'], '_transient_timeout_') === 0) {
                    $del_transient     = str_replace('_timeout', '', $ops['option_name']);
                    $deleted_options[] = $del_transient;
                    $deleted_options[] = $ops['option_name'];
                    delete_option($del_transient);
                    delete_option($ops['option_name']);
                }
                if (strpos($ops['option_name'], '_site_transient') === 0) {
                    continue;
                }
                if (strpos($ops['option_name'], '_transient') === 0) {
                    continue;
                }
                if (strpos($ops['option_name'], '_user_roles') !== false) {
                    continue;
                }
                if (strpos($ops['option_name'], 'widget_') === 0) {
                    continue;
                }
                $search_op[$ops['option_name']] = $ops['option_id'];
            }
            // 當前主題的設定不清除
            if (isset($search_op['theme_mods_' . $current_theme])) {
                unset($search_op['theme_mods_' . $current_theme]);
            }
            if (isset($search_op['theme_mods_' . $current_parent_theme])) {
                unset($search_op['theme_mods_' . $current_parent_theme]);
            }
            wp_send_json_success(array_keys($search_op));
        }
        if ($step == 2) {
            $deleted_options = array();
            if ($force) {
                foreach ($search_op as $option_name) {
                    $deleted_options[] = $option_name;
                    $res               = $wpdb->delete(
                        $wpdb->prefix . 'options',
                        array('option_name' => $option_name),
                        array('%s')
                    );
                    if ($wpdb->last_error !== '') {
                        error_log('last_query: ' . $wpdb->last_query . ' & last_error: ' . $wpdb->last_error);
                        wp_send_json_error(array('last_query' => $wpdb->last_query, 'last_error' => $wpdb->last_error));
                    }
                }
                wp_send_json_success($deleted_options);
            }
            foreach ($search_op as $option_name) {
                $in_use_flag     = false;
                $abspath         = str_replace('/', DIRECTORY_SEPARATOR, ABSPATH);
                $all_files_in_WP = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abspath, \RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($all_files_in_WP as $key => $file) {
                    $entry = $file->getPathname();
                    if ($file->isDir()) {
                        continue;
                    }
                    $path_parts = pathinfo($entry);
                    if (isset($path_parts['extension']) && strtoupper($path_parts['extension']) == 'PHP' && $entry != __FILE__) {
                        if (strpos(file_get_contents($entry), $option_name) !== false) {
                            $in_use_flag = true;
                        }
                    }
                }
                if ($in_use_flag != true) {
                    // print_r($option_name . ' => ID: MXP' . PHP_EOL);
                    $deleted_options[] = $option_name;
                    delete_option($option_name);
                }
            }
            wp_send_json_success($deleted_options);
        }
    }

    public function mxp_ajax_db_optimize_postmeta() {
        set_time_limit(0);
        if (!isset($_REQUEST['step']) || (intval($_REQUEST['step']) != 1 && intval($_REQUEST['step']) != 2)) {
            wp_send_json_error('請求參數有誤！');
        }
        $step     = sanitize_text_field($_REQUEST['step']);
        $meta_ids = isset($_REQUEST['meta_ids']) ? $_REQUEST['meta_ids'] : '';

        if (!empty($meta_ids) && !is_array($meta_ids)) {
            wp_send_json_error('請求參數有誤！');
        }

        if (is_array($meta_ids)) {
            $meta_ids = array_map('intval', $meta_ids);
        }

        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-ajax-nonce-for-db-optimize')) {
            wp_send_json_error('請求驗證有誤！');
        }

        global $wpdb;
        if ($step == 1) {
            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_id NOT IN ( SELECT * FROM ( SELECT MAX(meta_id) FROM {$wpdb->prefix}postmeta GROUP BY post_id, meta_key ) AS x)", ARRAY_A);
            foreach ($result as $index => $item) {
                $result[$index]['meta_value'] = esc_html($item['meta_value']);
            }
            wp_send_json_success($result);
        }
        if ($step == 2) {
            $success = array();
            foreach ($meta_ids as $key => $meta_id) {
                $del = $wpdb->delete(
                    $wpdb->prefix . 'postmeta',
                    array('meta_id' => $meta_id),
                    array('%d')
                );
                if ($del) {
                    $success[] = $meta_id;
                }
            }
            wp_send_json_success($success);
        }
        wp_send_json_error('請求方法有誤！');
    }

    public function mxp_ajax_db_optimize_usermeta() {
        set_time_limit(0);
        if (!isset($_REQUEST['step']) || (intval($_REQUEST['step']) != 1 && intval($_REQUEST['step']) != 2)) {
            wp_send_json_error('請求參數有誤！');
        }
        $step     = sanitize_text_field($_REQUEST['step']);
        $meta_ids = isset($_REQUEST['meta_ids']) ? $_REQUEST['meta_ids'] : '';

        if (!empty($meta_ids) && !is_array($meta_ids)) {
            wp_send_json_error('請求參數有誤！');
        }

        if (is_array($meta_ids)) {
            $meta_ids = array_map('intval', $meta_ids);
        }

        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-ajax-nonce-for-db-optimize')) {
            wp_send_json_error('請求驗證有誤！');
        }

        global $wpdb;
        if ($step == 1) {
            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}usermeta WHERE umeta_id NOT IN ( SELECT * FROM ( SELECT MAX(umeta_id) FROM {$wpdb->prefix}usermeta GROUP BY user_id, meta_key ) AS x)", ARRAY_A);
            foreach ($result as $index => $item) {
                $result[$index]['meta_value'] = esc_html($item['meta_value']);
            }
            wp_send_json_success($result);
        }
        if ($step == 2) {
            $success = array();
            foreach ($meta_ids as $key => $umeta_id) {
                $del = $wpdb->delete(
                    $wpdb->prefix . 'usermeta',
                    array('umeta_id' => $umeta_id),
                    array('%d')
                );
                if ($del) {
                    $success[] = $umeta_id;
                }
            }
            wp_send_json_success($success);
        }
        wp_send_json_error('請求方法有誤！');
    }

    public function mxp_ajax_clean_orphan() {
        if (!isset($_REQUEST['type']) || $_REQUEST['type'] == '') {
            wp_send_json_error('請求參數有誤！');
        }
        $type = sanitize_text_field($_REQUEST['type']);
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-ajax-nonce-for-db-optimize')) {
            wp_send_json_error('請求驗證有誤！');
        }
        global $wpdb;
        $res = '';
        if ($type == 'post') {
            $res = $wpdb->get_results("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL");
        }
        if ($type == 'comment') {
            $res = $wpdb->get_results("DELETE FROM {$wpdb->commentmeta} WHERE comment_id NOT IN (SELECT comment_id FROM {$wpdb->comments})");
        }
        wp_send_json_success($res);
    }
    public function mxp_ajax_clean_mxpdev() {
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-ajax-nonce-for-db-optimize')) {
            wp_send_json_error('請求驗證有誤！');
        }
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
        );
        foreach ($keys as $index => $key) {
            $sql = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s';
            $del = $wpdb->query($wpdb->prepare($sql, $key));
        }
        // 清除超連結目錄中的檔案
        $errors = [];
        if ($del === false) {
            $errors[] = array('action' => 'DELETE query', 'name' => '');
        }
        $mxpdev_folder = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR . '/uploads/MXPDEV/');
        if (is_dir($mxpdev_folder)) {
            $folder = opendir($mxpdev_folder);
            while (($file = readdir($folder)) !== false) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $mxpdev_folder . $file;
                    $r        = unlink($filePath);
                    if ($r === false) {
                        $errors[] = array('action' => 'unlink', 'name' => $filePath);
                    }
                }
            }
            closedir($folder);
        }
        $tmp_dir = rtrim(get_temp_dir(), DIRECTORY_SEPARATOR);
        if ((defined('MDT_TMP_DIR') && MDT_TMP_DIR != 'TMP') || !is_writable($tmp_dir)) {
            $tmp_dir = ABSPATH . DIRECTORY_SEPARATOR . "wp-content" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "MXPDEV";
            if (!file_exists($tmp_dir) && !is_dir($tmp_dir)) {
                mkdir($tmp_dir, 0777, true);
            }
        }
        $directory = $tmp_dir . DIRECTORY_SEPARATOR;
        $files     = scandir($directory);
        foreach ($files as $file) {
            // 忽略 "." 和 ".." 這兩個特殊目錄
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (strpos($file, 'magick-') === 0) {
                $r = unlink($directory . $file);
                if ($r === false) {
                    $errors[] = array('action' => 'unlink', 'name' => $filePath);
                }
            }
            if (preg_match('/\.zip$/i', $file)) {
                $r = unlink($directory . $file);
                if ($r === false) {
                    $errors[] = array('action' => 'unlink', 'name' => $filePath);
                }
            }
            if (preg_match('/\.sql$/i', $file)) {
                $r = unlink($directory . $file);
                if ($r === false) {
                    $errors[] = array('action' => 'unlink', 'name' => $filePath);
                }
            }
        }
        if (count($errors) == 0) {
            wp_send_json(array('success' => true, 'data' => array(), 'msg' => '清除完成！'));
        } else {
            wp_send_json(array('success' => false, 'data' => $errors, 'msg' => '請求異常'));
        }
        exit;
    }

    public function mxp_ajax_reset_user_metabox() {
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-ajax-nonce-for-db-optimize')) {
            wp_send_json_error('請求驗證有誤！');
        }
        global $wpdb;
        $res = $wpdb->get_results("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'meta-box-order_%'");
        wp_send_json_success($res);
    }

    public function mxp_ajax_set_autoload_no() {
        if (!isset($_REQUEST['name']) || $_REQUEST['name'] == '') {
            wp_send_json_error('請求參數有誤！');
        }
        $name = sanitize_text_field($_REQUEST['name']);
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-ajax-nonce-for-db-optimize')) {
            wp_send_json_error('請求驗證有誤！');
        }
        global $wpdb;
        $res = $wpdb->get_results(
            $wpdb->prepare("UPDATE {$wpdb->options} SET autoload=%s WHERE option_name=%s", 'no', $name), ARRAY_A
        );
        wp_send_json_success($res);
    }

    public function mxp_ajax_reset_wp() {
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'mxp-ajax-nonce-for-db-optimize') || !is_super_admin()) {
            wp_send_json_error('請求驗證有誤！');
        }
        global $wp_filter;
        // 清空當前註冊的所有事件，避免影響後續的重置作業
        $wp_filter          = [];
        $del_uploads        = false;
        $clean_all_tables   = true;
        $keep_options_reset = true;
        $password           = '';
        if (isset($_REQUEST['del_uploads']) && $_REQUEST['del_uploads'] != '') {
            $del_uploads = sanitize_text_field($_REQUEST['del_uploads']);
            if ($del_uploads != '0') {
                $del_uploads = true;
            }
        }
        if (isset($_REQUEST['clean_all_tables']) && $_REQUEST['clean_all_tables'] != '') {
            $clean_all_tables = sanitize_text_field($_REQUEST['clean_all_tables']);
            if ($clean_all_tables != '1') {
                $clean_all_tables = false;
            }
        }
        if (isset($_REQUEST['keep_options_reset']) && $_REQUEST['keep_options_reset'] != '') {
            $keep_options_reset = sanitize_text_field($_REQUEST['keep_options_reset']);
            if ($keep_options_reset != '1') {
                $keep_options_reset = false;
            }
        }
        if (isset($_REQUEST['password']) && $_REQUEST['password'] != '') {
            $password = sanitize_text_field($_REQUEST['password']);
        }
        if ($del_uploads) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            $fileSystemDirect = new \WP_Filesystem_Direct(false);
            $upload_dir       = wp_upload_dir();
            $fileSystemDirect->rmdir($upload_dir['basedir'], true);
        }
        require_once ABSPATH . '/wp-admin/includes/upgrade.php';
        global $wpdb;
        $oldsite_options = array();
        // 定義在 wp-admin/includes/schema.php
        $keep_options = array('siteurl', 'home', 'blogname', 'blogdescription', 'users_can_register', 'admin_email', 'start_of_week', 'use_balanceTags', 'use_smilies', 'require_name_email', 'comments_notify', 'posts_per_rss', 'rss_use_excerpt', 'mailserver_url', 'mailserver_login', 'mailserver_pass', 'mailserver_port', 'default_category', 'default_comment_status', 'default_ping_status', 'default_pingback_flag', 'posts_per_page', 'date_format', 'time_format', 'links_updated_date_format', 'comment_moderation', 'moderation_notify', 'permalink_structure', 'rewrite_rules', 'hack_file', 'blog_charset', 'moderation_keys', 'category_base', 'ping_sites', 'comment_max_links', 'gmt_offset', 'default_email_category', 'recently_edited', 'template', 'stylesheet', 'comment_registration', 'html_type', 'use_trackback', 'default_role', 'db_version', 'uploads_use_yearmonth_folders', 'upload_path', 'blog_public', 'default_link_category', 'show_on_front', 'tag_base', 'show_avatars', 'avatar_rating', 'upload_url_path', 'thumbnail_size_w', 'thumbnail_size_h', 'thumbnail_crop', 'medium_size_w', 'medium_size_h', 'avatar_default', 'large_size_w', 'large_size_h', 'image_default_link_type', 'image_default_size', 'image_default_align', 'close_comments_for_old_posts', 'close_comments_days_old', 'thread_comments', 'thread_comments_depth', 'page_comments', 'comments_per_page', 'default_comments_page', 'comment_order', 'sticky_posts', 'widget_categories', 'widget_text', 'widget_rss', 'timezone_string', 'page_for_posts', 'page_on_front', 'default_post_format', 'link_manager_enabled', 'finished_splitting_shared_terms', 'site_icon', 'medium_large_size_w', 'medium_large_size_h', 'wp_page_for_privacy_policy', 'show_comments_cookies_opt_in', 'admin_email_lifespan', 'disallowed_keys', 'comment_previously_approved', 'auto_plugin_theme_update_emails', 'auto_update_core_dev', 'auto_update_core_minor', 'auto_update_core_major', 'wp_force_deactivated_plugins');
        foreach ($keep_options as $index => $ops_key) {
            $oldsite_options[$ops_key] = get_option($ops_key);
        }
        $blogname    = $oldsite_options['blogname'];
        $admin_email = $oldsite_options['admin_email'];
        $blog_public = $oldsite_options['blog_public'];
        $languages   = get_locale();
        $user        = wp_get_current_user();
        // 找到所有資料表
        $tables            = $wpdb->get_col("SHOW TABLES");
        $wp_default_tables = array_values($wpdb->tables());
        foreach ($tables as $table) {
            if (in_array($table, $wp_default_tables)) {
                // 內建資料表全給他砍了～
                $wpdb->query("DROP TABLE `$table`");
            } else {
                // 保留彈性不一定要砍掉其他資料表
                if ($clean_all_tables) {
                    $wpdb->query("DROP TABLE `$table`");
                }
            }
        }
        // 如果沒有要保留先前設定，就判斷預設主題是否有安裝，沒有就安裝上。
        if (!$keep_options_reset) {
            $theme = wp_get_theme(WP_DEFAULT_THEME);
            if (!$theme->exists()) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                include_once ABSPATH . 'wp-admin/includes/theme.php';
                $api = themes_api(
                    'theme_information',
                    array(
                        'slug'   => WP_DEFAULT_THEME,
                        'fields' => array('sections' => false),
                    )
                );
                if (!is_wp_error($api)) {
                    $skin     = new \WP_Ajax_Upgrader_Skin();
                    $upgrader = new \Theme_Upgrader($skin);
                    $result   = $upgrader->install($api->download_link);
                }
            }
        }
        // 重新安裝 WordPress
        $result = wp_install($blogname, $user->user_login, $user->user_email, $blog_public, '', $password, $languages);
        if ($password == '') {
            $user_id = $result['user_id'];
            // 沒指定新密碼的話，就更新原本的密碼
            $query = $wpdb->prepare("UPDATE $wpdb->users SET user_pass = %s, user_activation_key = '' WHERE ID = %d", $user->user_pass, $user_id);
            $wpdb->query($query);
            update_user_meta($user_id, 'default_password_nag', false);
            update_user_meta($user_id, $wpdb->prefix . 'default_password_nag', false);
            $result['password'] = $password; //空的
        }
        // 重新啟用工具箱外掛
        @activate_plugin('mxp-dev-tools/index.php');
        // 重新把設定存回去
        foreach ($oldsite_options as $option => $value) {
            if ($keep_options_reset) {
                update_option($option, $value);
            }
        }
        wp_send_json_success($result);
    }
}