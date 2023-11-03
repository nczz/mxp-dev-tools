<?php
namespace MxpDevTools;

if (!defined('WPINC')) {
    die;
}

// Ref: https://gist.github.com/Viper007Bond/5192117
class MxpDevHooksUsage {

    public $data = array();

    public function __construct() {
        add_action('admin_bar_menu', array($this, 'customize_admin_bar'), 99);
        add_action('all', array($this, 'filter_start'));
        add_action('shutdown', array($this, 'results'));
    }

    public function customize_admin_bar() {
        global $wp_admin_bar;
        $wp_admin_bar->add_menu(array(
            'id'    => 'mxp_dev_hooks_usage',
            'title' => 'Hooks 執行時間',
            'href'  => false,
        ));
        $wp_admin_bar->add_menu(array(
            'id'     => 'demo-sub-menu',
            'parent' => 'mxp_dev_hooks_usage',
            'title'  => 'hook:ms',
            'href'   => false,
        ));
    }

    // This runs first for all actions and filters.
    // It starts a timer for this hook.
    public function filter_start() {
        $current_filter                         = current_filter();
        $this->data[$current_filter][]['start'] = microtime(true);
        add_filter($current_filter, array($this, 'filter_end'), 99999);
    }

    // This runs last (hopefully) for each hook and records the end time.
    // This has problems if a hook fires inside of itself since it assumes
    // the last entry in the data key for this hook is the matching pair.
    public function filter_end($filter_data = null) {
        $current_filter = current_filter();
        remove_filter($current_filter, array($this, 'filter_end'), 99999);
        end($this->data[$current_filter]);
        $last_key                                       = key($this->data[$current_filter]);
        $this->data[$current_filter][$last_key]['stop'] = microtime(true);
        return $filter_data;
    }

    // Processes the results and var_dump()'s them. TODO: Debug bar panel?
    public function results() {
        $results = array();
        foreach ($this->data as $filter => $calls) {
            foreach ($calls as $call) {
                // Skip filters with no end point (i.e. the hook this function is hooked into)
                if (!isset($call['stop'])) {
                    continue;
                }
                if (!isset($results[$filter])) {
                    $results[$filter] = 0;
                }
                $results[$filter] = $results[$filter] + ($call['stop'] - $call['start']);
            }
        }
        asort($results, SORT_NUMERIC);
        $results = array_reverse($results);
        $now     = time();
        $new_res = array();
        $total   = 0;
        foreach ($results as $hook_name => $time_diff) {
            $time = round($time_diff, 3);
            $total += $time;
            if ($time > 0.01) {
                $new_res[$hook_name] = $time;
            }
        }
        $insert      = array('總計' => round($total, 3));
        $resultArray = $insert + $new_res;
        echo '<script>var mxp_hooks_usage=' . json_encode($resultArray) . ';var ulElement = document.getElementById("wp-admin-bar-mxp_dev_hooks_usage-default");ulElement.innerHTML = "";for (var hook in mxp_hooks_usage) { if (mxp_hooks_usage.hasOwnProperty(hook)) { console.log(hook,mxp_hooks_usage[hook]+" 秒");var liElement = document.createElement("li"); liElement.innerHTML = "<div class=\'ab-item ab-empty-item\'>"+hook + " : " + mxp_hooks_usage[hook]+" 秒</div>"; ulElement.appendChild(liElement); } }
</script>';
    }
}

if (!function_exists('wp_get_current_user')) {
    include_once ABSPATH . WPINC . '/pluggable.php';
}
if (isset($_REQUEST['debug']) && \is_super_admin()) {
    new MxpDevHooksUsage();
}
