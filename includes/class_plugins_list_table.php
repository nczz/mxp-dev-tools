<?php
if (!defined('WPINC')) {
    die;
}
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
class Mxp_Plugins_List_Table extends WP_List_Table {
    private $table_id = 'mxp_plugins_list_table';
    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->waiting_info('on');
        $data = $this->table_data();
        $this->waiting_info('off');
        $this->handle_actions();
        //usort($data, array(&$this, 'sort_data'));
        $this->set_columns_style();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items           = $data;
    }
    public function get_columns() {
        $columns = array(
            'id'          => '編號',
            'cb'          => '操作',
            'name'        => '外掛名稱',
            'slug'        => '外掛代號',
            'hosted'      => '服務資源',
            'dlink'       => '下載連結',
            'status'      => '狀態',
            'file'        => '本機檔案',
            'description' => '外掛描述',
        );

        return $columns;
    }
    public function get_hidden_columns() {
        return array('slug', 'hosted', 'dlink', 'file');
    }
    public function get_sortable_columns() {
        return array(); //array('id' => array('id', false));
    }
    public function column_slug($items) {
        return $items['slug'];
    }
    private function set_columns_style() {
        echo '<style type="text/css">';
        echo '.wp-list-table .column-id { width: 16%; }';
        echo '.wp-list-table .column-cb { width: 5%; }';
        echo '.wp-list-table .column-name { width: 17%; }';
        echo '.wp-list-table .column-status { width: 12%; }';
        echo '.wp-list-table .column-description { width: 50%;}';
        echo '</style>';
    }
    public function get_bulk_actions() {
        $actions = array(
            'install'  => '安裝',
            'activate' => '啟用',
        );
        return $actions;
    }
    public function handle_actions() {
        //change to AJAX method
    }
    public function column_cb($item) {
        return sprintf('<input type="checkbox" class="plugins_list" data-id="p_%s" name="plugins[]"/><textarea id="p_%s" style="display:none">%s</textarea>', $item['id'], $item['id'], json_encode($item));
    }
    public function column_name($item) {
        $viewDetailsLink = $item['name'];
        if (strpos($item['dlink'], 'https://downloads.wordpress.org') === 0) {
            $viewDetailsLink = sprintf('<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
                esc_url(network_admin_url('plugin-install.php?tab=plugin-information&plugin=' . urlencode($item['slug']) .
                    '&TB_iframe=true&width=600&height=550')),
                esc_attr(sprintf(__('更多關於 %s'), $item['name'])),
                esc_attr($item['name']),
                $item['name']
            );
        }
        return $viewDetailsLink;
    }
    public function column_status($item) {
        $status = $item['status'];
        switch ($status) {
        case 'install':
            $status = '尚未安裝';
            break;
        case 'latest_installed':
            $status = '已安裝最新版';
            break;
        case 'update_available':
            $status = '需要更新';
            break;
        default:
            $status = $item['status'];
            break;
        }
        return sprintf('<div class="p_%s">%s</div>', $item['id'], $status);
    }
    public function table_data() {
        $response = wp_remote_get('https://raw.githubusercontent.com/nczz/work_with_wordpress/master/plugins_list.json?t=' . time());
        if (is_array($response) && !is_wp_error($response) && $response['response']['code'] == 200) {
            $data = json_decode($response['body'], true);
            if (!$data) {
                echo '<br/>請回報下列錯誤資訊至：im@mxp.tw<br/>錯誤的格式：<br/><pre>' . print_r($response, true) . '</pre>';
                wp_die();
            }
        } else {
            echo '<br/>請回報下列錯誤資訊至：im@mxp.tw<br/>檔案不存在或伺服器發生問題：<br/><pre>' . print_r($response, true) . '</pre>';
            wp_die();
        }
        $requests = [];
        for ($i = 0; $i < count($data); ++$i) {
            if ($data[$i]['dlink'] == "") {
                $requests[] = [
                    'url'     => 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . $data[$i]['slug'] . '&request[locale]=zh_TW&request[wp_version]=' . get_bloginfo('version'),
                    'headers' => ['Accept' => 'application/json'],
                ];
            }
        }
        if (class_exists('WpOrg\Requests\Requests')) {
            $responses = WpOrg\Requests\Requests::request_multiple($requests/*, $options*/);
        } else {
            $responses = Requests::request_multiple($requests/*, $options*/);
        }
        $resp_obj = [];
        foreach ($responses as $r_index => $p_info) {
            $plugin_info = isset($p_info->body) ? json_decode($p_info->body) : '';
            if (json_last_error() === JSON_ERROR_NONE && property_exists($plugin_info, 'slug')) {
                $resp_obj[$plugin_info->slug] = $plugin_info;
            }
        }
        for ($i = 0; $i < count($data); ++$i) {
            $data[$i]['id'] = $i + 1;
            $wp             = isset($resp_obj[$data[$i]['slug']]) ? $resp_obj[$data[$i]['slug']] : ''; //$this->check_status_from_wp($data[$i]['slug']);
            if (!empty($wp) && $data[$i]['dlink'] == "") {
                $data[$i]['version'] = $wp->version;
                $data[$i]['dlink']   = $wp->download_link;
            } else {
                $data[$i]['version'] = isset($data[$i]['version']) ? $data[$i]['version'] : '0.0.0';
            }
            $local                 = $this->check_status_from_local($data[$i]);
            $data[$i]['status']    = $local['status'];
            $data[$i]['file']      = $local['file'];
            $data[$i]['activated'] = is_plugin_active($local['file']);
        }
        return $data;
    }
    //$api = (Object){name:plugin_name,slug:plugin_slug,version:plugin_version}
    private function check_status_from_local($api) {
        if (!function_exists('install_plugin_install_status')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }
        $pluginInfo = install_plugin_install_status($api);
        return $pluginInfo;
    }
    private function check_status_from_wp($slug) {
        if (!function_exists('plugins_api')) {
            include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }
        if (false === ($plugin_info = get_transient($this->table_id . 'cache' . $slug))) {
            $plugin_info = plugins_api('plugin_information',
                array(
                    'slug'   => $slug,
                    'fields' => array(
                        'downloaded'        => false,
                        'rating'            => false,
                        //'contributors' => false,
                        'short_description' => false,
                        'sections'          => false,
                        //'requires' => false,
                        'ratings'           => false,
                        'last_updated'      => false,
                        'added'             => false,
                        'tags'              => false,
                        'compatibility'     => false,
                        'homepage'          => false,
                        'donate_link'       => false,
                    ),
                )
            );
            set_transient($this->table_id . 'cache' . $slug, $plugin_info, 20 * MINUTE_IN_SECONDS);
        }
        return $plugin_info;
    }
    public function column_id($item) {
        $actions             = array();
        $actions['activate'] = sprintf('<button class="mxp-activate" data-id="p_%s" disabled>啟用</button>', $item['id']);
        $actions['update']   = sprintf('<button class="mxp-update" data-id="p_%s" disabled>更新</button>', $item['id']);
        $actions['install']  = sprintf('<button class="mxp-install" data-id="p_%s" disabled>安裝</button>', $item['id']);
        return sprintf('%1$s %2$s', $item['id'], $this->row_actions($actions));
    }
    public function column_default($item, $column_name) {
        switch ($column_name) {
        case 'id':
        // case 'name':
        case 'slug':
        case 'description':
        case 'status':
            return $item[$column_name];
        default:
            return print_r($item, true);
        }
    }
    private function waiting_info($flag) {
        if ($flag == 'on') {
            ob_start();
            echo '<div id="mxp_info">請稍候，正在讀取中。(由於清單會即時比對最新版本的外掛，所以讀取速度較為緩慢)</div>';
            ob_flush();
            flush();
            ob_end_clean();
        } else {
            echo '<style>#mxp_info{display:none;}</style>';
        }
    }
    private function sort_data($a, $b) {
        $orderby = 'id';
        $order   = 'asc';
        if (!empty($_GET['orderby'])) {
            $orderby = sanitize_text_field($_GET['orderby']);
        }
        if (!empty($_GET['order'])) {
            $order = sanitize_text_field($_GET['order']);
        }
        $result = strnatcmp($a[$orderby], $b[$orderby]);
        if ($order === 'asc') {
            return $result;
        }
        return -$result;
    }

}