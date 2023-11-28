<?php
namespace MxpDevTools;

if (!defined('WPINC')) {
    die;
}

trait Utility {
    public function get_wp_all_contants() {
        // $wp_constants 變數資料量太大，放另一個檔案存
        include dirname(__FILE__) . '/wp_constants.php';
        $output  = array();
        $pre_set = array();
        foreach ($wp_constants as $category) {
            $category_name = $category['title'];
            foreach ($category['constants'] as $constant) {
                if ((defined($constant['name'])) && (!empty(constant($constant['name'])))) {
                    $constant_name                 = $constant['name'];
                    $pre_set[]                     = $constant_name;
                    $constant_default_value        = $constant['value'];
                    $constant_current_value        = constant($constant_name);
                    $constant_current_output_value = '';
                    $constant_description          = $constant['description'];
                    switch (gettype($constant_current_value)) {
                    case 'array':
                    case 'object':
                        $constant_current_output_value = '<pre>' . var_export($constant_current_value, true) . '</pre>';
                        break;
                    case 'boolean':
                        $constant_current_output_value = true === $constant_current_value ? 'true' : 'false';
                        break;
                    case 'NULL':
                        $constant_current_output_value = 'NULL';
                        break;
                    default:
                        $constant_current_output_value = $constant_current_value . '';
                        break;
                    }

                    if ($constant_default_value != $constant_current_value) {
                        $output[] = array('分類' => $category_name, '常數命名' => $constant_name, '說明' => $constant_description, '預設值' => $constant_default_value, '當前值' => '<font color="red">' . $constant_current_output_value . '</font>');
                    } else {
                        $output[] = array('分類' => $category_name, '常數命名' => $constant_name, '說明' => $constant_description, '預設值' => $constant_default_value, '當前值' => $constant_current_output_value);
                    }

                }

            }

        }
        $all_constants  = get_defined_constants(true);
        $user_constants = $all_constants['user'];
        foreach ($user_constants as $constant_name => $value) {
            if (!in_array($constant_name, $pre_set, true)) {
                $category_name                 = '主題/外掛';
                $constant_current_value        = constant($constant_name);
                $constant_current_output_value = '';
                $constant_description          = '自定義常數';
                $constant_default_value        = '';
                switch (gettype($constant_current_value)) {
                case 'array':
                case 'object':
                    $constant_current_output_value = '<pre>' . var_export($constant_current_value, true) . '</pre>';
                    break;
                case 'boolean':
                    $constant_current_output_value = true === $constant_current_value ? 'true' : 'false';
                    break;
                case 'NULL':
                    $constant_current_output_value = 'NULL';
                    break;
                default:
                    $constant_current_output_value = $constant_current_value . '';
                    break;
                }
                $output[] = array('分類' => $category_name, '常數命名' => $constant_name, '說明' => $constant_description, '預設值' => $constant_default_value, '當前值' => $constant_current_output_value);
            }
        }
        return $output;
    }

    public function get_filename_dir_path($file_name) {
        if (empty($file_name) || !function_exists('get_included_files')) {
            return array();
        }
        $includedFiles = get_included_files();
        // 在被引入的檔案清單中尋找目標檔案
        $file_paths = array();
        foreach ($includedFiles as $file) {
            if (strtolower(basename($file)) === strtolower($file_name)) {
                $file_paths[] = $file;
            }
        }
        return $file_paths;
    }

    // 參考： https://gist.github.com/krisanalfa/8315091
    public function phpinfo_print_r($my_array) {
        if (is_array($my_array)) {
            echo "<table border=1 cellspacing=0 cellpadding=3 width=100%>";
            echo '<tr><td colspan=2 style="background-color:#333333;"><strong><font color=white>設定值</font></strong></td></tr>';
            foreach ($my_array as $k => $v) {
                echo '<tr><td valign="top" style="width:40px;background-color:#F0F0F0;">';
                echo '<strong>' . $k . "</strong></td><td>";
                $this->phpinfo_print_r($v);
                echo "</td></tr>";
            }
            echo "</table>";
            return;
        }
        echo $my_array;
    }

    public function get_recently_mod_files($day_from = '', $day_to = '') {
        $recently_mod_files = array(
            'dot_files'           => array(),
            'core_files'          => array(),
            'uploads_files'       => array(),
            'uncategorized_files' => array(),
            'theme_files'         => array(),
            'plugin_files'        => array(),
        );
        $day_from = strtotime($day_from);
        $day_to   = strtotime($day_to);
        if ($day_from == '' || $day_to == '' || $day_from == false || $day_to == false) {
            return $recently_mod_files;
        }
        // 如果前後時間選錯，直接排序過
        if ($day_to < $day_from) {
            $tmp      = $day_to;
            $day_to   = $day_from;
            $day_from = $tmp;
        }
        // 如果選到同一天，就抓前一天到當天
        if ($day_to == $day_from) {
            $day_from -= DAY_IN_SECONDS;
        }
        // 結束日抓完整的一日來看
        $day_to += DAY_IN_SECONDS;
        $theme_root      = str_replace('/', DIRECTORY_SEPARATOR, get_theme_root());
        $plugin_root     = str_replace('/', DIRECTORY_SEPARATOR, WP_PLUGIN_DIR);
        $uploads_root    = str_replace('/', DIRECTORY_SEPARATOR, wp_get_upload_dir()['basedir']);
        $abspath         = str_replace('/', DIRECTORY_SEPARATOR, ABSPATH);
        $all_files_in_WP = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abspath, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($all_files_in_WP as $key => $file) {
            $entry = $file->getPathname();
            if ($file->isDir()) {
                continue;
            }
            $mtime = 0;
            try {
                $mtime = $file->getMTime();
            } catch (\Exception $e) {
                $mtime = 0;
            }
            $path_parts = pathinfo($entry);
            //先記錄所有隱藏檔案
            if (strpos($path_parts['basename'], '.') === 0) {
                if ('.DS_Store' === $path_parts['basename']) {
                    @unlink($entry);
                    continue;
                }
                $recently_mod_files['dot_files'][] = array('full_path' => $entry, 'relative_path' => str_replace(ABSPATH, '', $entry), 'name' => $path_parts['basename'], 'mod_time' => $mtime);
                continue;
            }
            // 判斷是否是核心檔案， WP_CONTENT_DIR 之外的都算
            $wp_content_dir = str_replace('/', DIRECTORY_SEPARATOR, WP_CONTENT_DIR);
            if (strpos($entry, $wp_content_dir) === 0) {
                // 一天內修改的檔案
                if ($mtime >= $day_from && $mtime <= $day_to) {
                    // 非系統檔案
                    if (strpos($entry, $theme_root) === 0) {
                        // 主題檔案
                        $recently_mod_files['theme_files'][] = array('full_path' => $entry, 'relative_path' => str_replace($theme_root . DIRECTORY_SEPARATOR, '', $entry), 'name' => $path_parts['basename'], 'mod_time' => $mtime);
                        continue;
                    }
                    if (strpos($entry, $plugin_root) === 0) {
                        // 外掛檔案
                        $recently_mod_files['plugin_files'][] = array('full_path' => $entry, 'relative_path' => str_replace($plugin_root . DIRECTORY_SEPARATOR, '', $entry), 'name' => $path_parts['basename'], 'mod_time' => $mtime);
                        continue;
                    }
                    if (strpos($entry, $uploads_root) === 0) {
                        // 上傳檔案
                        $recently_mod_files['uploads_files'][] = array('full_path' => $entry, 'relative_path' => str_replace($uploads_root . DIRECTORY_SEPARATOR, '', $entry), 'name' => $path_parts['basename'], 'mod_time' => $mtime);
                        continue;
                    }
                    // 其他未分類檔案
                    $recently_mod_files['uncategorized_files'][] = array('full_path' => $entry, 'relative_path' => str_replace($wp_content_dir . DIRECTORY_SEPARATOR, '', $entry), 'name' => $path_parts['basename'], 'mod_time' => $mtime);
                }
            } else {
                // 算系統檔案（預設紀錄一天內修改的檔案）
                if ($mtime >= $day_from && $mtime <= $day_to) {
                    $recently_mod_files['core_files'][] = array('full_path' => $entry, 'relative_path' => str_replace($abspath, '', $entry), 'name' => $path_parts['basename'], 'mod_time' => $mtime);
                    continue;
                }
            }
        }
        return $recently_mod_files;
    }

    public function page_wraper($title, $cb) {
        echo '<div class="wrap" id="mxp"><h1>' . $title . '</h1>';
        call_user_func($cb);
        echo '</div>';
    }

    public function build_table($array) {
        // start table
        $html = '<table>';
        // header row
        $html .= '<tr>';
        foreach ($array[0] as $key => $value) {
            $html .= '<th>' . esc_html($key) . '</th>';
        }
        $html .= '</tr>';

        // data rows
        foreach ($array as $key => $value) {
            $html .= '<tr>';
            foreach ($value as $key2 => $value2) {
                $html .= '<td align="center">' . $value2 . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
        $html .= '<style>table, th, td {border: 1px solid black;}</style>';
        return $html;
    }

}