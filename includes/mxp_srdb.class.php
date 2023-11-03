<?php
/**
 * Modify from interconnect\it source: https://github.com/interconnectit/Search-Replace-DB/
 * Author: Chun
 * Author URI: https://www.mxp.tw/contact/
 * License: GPL v3
 */
if (!defined('WPINC')) {
    die;
}

class Mxp_SRDB {
    public $tables = array();

    public $exclude_tables = array();

    public $search = false;

    public $replace = false;

    public $regex = false;

    public $exclude_cols = array();

    public $include_cols = array();

    public $dry_run = true;

    public $debug = false;

    /**
     * @var array Stores a list of exceptions
     */
    public $errors = array(
        'search'         => array(),
        'db'             => array(),
        'tables'         => array(),
        'results'        => array(),
        'exclude_tables' => array(),
        'compatibility'  => array(),
    );

    public $error_type = 'search';

    /**
     * @var array Stores the report array
     */
    public $report = array();

    /**
     * @var 預設回報改變的數量
     */
    public $report_change_num = 10;

    /**
     * @var bool 是否印出資訊
     */
    public $verbose = false;

    /**
     * @var WPDB
     */
    public $db;
    /**
     * @var 指定資料庫處理
     */
    public $dbname;

    public $page_size = 50000;

    public function __construct($args) {

        $args = array_merge(array(
            'dbname'         => '',
            'search'         => '',
            'replace'        => '',
            'tables'         => array(),
            'exclude_tables' => array(),
            'exclude_cols'   => array(),
            'include_cols'   => array(),
            'dry_run'        => true,
            'regex'          => false,
            'page_size'      => 500,
            'verbose'        => false,
            'debug'          => false,
        ), $args);

        // handle exceptions
        set_exception_handler(array($this, 'exceptions'));

        // handle errors
        if ($args['debug'] === false) {
            set_error_handler(array($this, 'error_handler'), E_ERROR | E_WARNING);
        }

        mb_regex_encoding('UTF-8');

        // set class vars
        foreach ($args as $property => $value) {
            if (is_string($value)) {
                $value = stripcslashes($value);
            }
            if (is_array($value)) {
                $value = array_map('stripcslashes', $value);
            }
            $this->$property = $value;
        }

        // set up db connection
        $this->db_setup();

        if ($this->db_valid()) {

            if (is_array($this->search)) {
                $report = array();
                for ($i = 0; $i < count($this->search); $i++) {
                    $report[$i] = $this->replacer($this->search[$i], $this->replace[$i], $this->tables,
                        $this->exclude_tables);
                }
            } else {
                $report = $this->replacer($this->search, $this->replace, $this->tables, $this->exclude_tables);
            }

        } else {
            $report = $this->report;
        }
        // store report
        $this->report = $report;
        return $report;
    }

    /**
     * Terminates db connection
     *
     * @return void
     */
    public function __destruct() {
    }

    public function get($property) {
        return $this->$property;
    }

    public function set($property, $value) {
        $this->$property = $value;
    }

    /**
     * @param $exception Exception
     */
    public function exceptions($exception) {
        echo $exception->getMessage() . "\n";
    }

    /**
     * Custom error handler
     *
     * @param $errno
     * @param $message
     * @param $file
     * @param $line
     *
     * @return bool
     */
    public function error_handler($errno, $message, $file, $line) {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        switch ($errno) {
        case E_USER_ERROR:
            echo "ERROR [$errno]: $message\n";
            echo "Aborting...\n";
            exit($errno);
            break;

        case E_USER_WARNING:
            echo "WARNING [$errno] $message\n";
            break;

        default:
            echo "Unknown error: [$errno] $message in $file on line $line\n";
            exit($errno);
            break;
        }

        /* Don't execute PHP internal error handler */

        return true;
    }

    public function log($type = '') {
        $args = array_slice(func_get_args(), 1);
        if ($this->verbose) {
            echo "{$type}: ";
            print_r($args);
            echo "\n";
        }

        return $args;
    }

    public function add_error($error, $type = null) {
        if ($type !== null) {
            $this->error_type = $type;
        }
        $this->errors[$this->error_type][] = $error;
        $this->log('error', $this->error_type, $error);
    }

    /**
     * Setup connection, populate tables array
     * Also responsible for selecting the type of connection to use.
     *
     * @return void|bool
     */
    public function db_setup() {
        global $wpdb;
        // connect
        $this->db = $wpdb;

    }

    public function db_valid() {
        if ($this->db instanceof wpdb) {
            return true;
        } else {
            return false;
        }
    }

    public function db_escape($string) {
        $string = addslashes($string); //str_replace("\n", "\\n", addslashes($string));
        return "'{$string}'";
    }

    public function db_free_result($data) {
        $this->db->flush();
    }

    /**
     * Walk an array replacing one element for another. ( NOT USED ANY MORE )
     *
     * @param string $find The string we want to replace.
     * @param string $replace What we'll be replacing it with.
     * @param array $data Used to pass any subordinate arrays back to the
     * function for searching.
     *
     * @return array    The original array with the replacements made.
     */
    public function recursive_array_replace($find, $replace, $data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $this->recursive_array_replace($find, $replace, $data[$key]);
                } else {
                    // have to check if it's string to ensure no switching to string for booleans/numbers/nulls - don't need any nasty conversions
                    if (is_string($value)) {
                        $data[$key] = $this->str_replace($find, $replace, $value);
                    }
                }
            }
        } else {
            if (is_string($data)) {
                $data = $this->str_replace($find, $replace, $data);
            }
        }
    }

    /**
     * Take a serialised array and unserialise it replacing elements as needed and
     * unserialising any subordinate arrays and performing the replace on those too.
     *
     * @param string $from String we're looking to replace.
     * @param string $to What we want it to be replaced with
     * @param array $data Used to pass any subordinate arrays back to in.
     * @param bool $serialised Does the array passed via $data need serialising.
     *
     * @return array    The original array with all elements replaced as needed.
     */
    public function recursive_unserialize_replace($from = '', $to = '', $data = '', $serialised = false) {

        // some unserialised data cannot be re-serialised eg. SimpleXMLElements
        try {

            if (is_string($data) && ($unserialized = @unserialize($data)) !== false) {
                $data = $this->recursive_unserialize_replace($from, $to, $unserialized, true);
            } elseif (is_array($data)) {
                $_tmp = array();
                foreach ($data as $key => $value) {
                    $_tmp[$key] = $this->recursive_unserialize_replace($from, $to, $value, false);
                }

                $data = $_tmp;
                unset($_tmp);
            } elseif (is_object($data) && !is_a($data, '__PHP_Incomplete_Class')) {
                $_tmp  = $data;
                $props = get_object_vars($data);
                foreach ($props as $key => $value) {
                    $_tmp->$key = $this->recursive_unserialize_replace($from, $to, $value, false);
                }

                $data = $_tmp;
                unset($_tmp);
            } else {
                if (is_string($data)) {
                    $data = $this->str_replace($from, $to, $data);
                }
            }

            if ($serialised) {
                return serialize($data);
            }

        } catch (Error $error) {
            $this->add_error($error->getMessage(), 'results');
        } catch (Exception $error) {
            $this->add_error($error->getMessage() . ':: This is usually caused by a plugin storing classes as a
            serialised string which other PHP classes can\'t then access. It is not possible to unserialise this data
            because the PHP can\'t access this class. P.S. It\'s most commonly a Yoast plugin that causes this error.',
                'results');
        }

        return $data;
    }

    /**
     * Regular expression callback to fix serialised string lengths
     *
     * @param array $matches matches from the regular expression
     *
     * @return string
     */
    public function preg_fix_serialised_count($matches) {
        $length = mb_strlen($matches[2]);
        if ($length !== intval($matches[1])) {
            return "s:{$length}:\"{$matches[2]}\";";
        }

        return $matches[0];
    }

    /**
     * The main loop triggered in step 5. Up here to keep it out of the way of the
     * HTML. This walks every table in the db that was selected in step 3 and then
     * walks every row and column replacing all occurences of a string with another.
     * We split large tables into 50,000 row blocks when dealing with them to save
     * on memmory consumption.
     *
     * @param string $search What we want to replace
     * @param string $replace What we want to replace it with.
     * @param array $tables The tables we want to look at.
     *
     * @return array|bool    Collection of information gathered during the run.
     */
    public function replacer($search = '', $replace = '', $tables = array(), $exclude_tables = array()) {
        $search = (string) $search;
        // check we have a search string, bail if not
        if ('' === $search) {
            $this->add_error('Search string is empty', 'search');

            return false;
        }

        $report = array(
            'tables'        => 0,
            'rows'          => 0,
            'change'        => 0,
            'updates'       => 0,
            'start'         => microtime(true),
            'end'           => microtime(true),
            'errors'        => array(),
            'table_reports' => array(),
        );

        $table_report = array(
            'rows'    => 0,
            'change'  => 0,
            'changes' => array(),
            'updates' => 0,
            'start'   => microtime(true),
            'end'     => microtime(true),
            'errors'  => array(),
        );

        $dry_run = $this->dry_run;
        $errors  = $this->errors;
        $dbname  = '';
        if ($this->dbname != '') {
            $dbname = "`{$this->dbname}`.";
        }
        if ($this->dry_run and !(in_array('The dry-run option was selected. No replacements will be made.',
            $errors['results']))) // Report this as a search-only run.
        {
            $this->add_error('The dry-run option was selected. No replacements will be made.', 'results');
        }

        // if no tables selected assume all
        if (empty($tables)) {
            $this->add_error('Table is empty', 'search');
            return false;
        }

        if (is_array($tables) && !empty($tables)) {

            foreach ($tables as $table) {
                if (in_array($table, $exclude_tables)) {
                    $this->add_error('Ignoring Table: ' . $table);
                    continue;
                }

                $report['tables']++;

                // get primary key and columns
                list($primary_key, $columns) = $this->get_columns($table);

                if ($primary_key === null || empty($primary_key)) {
                    $this->add_error("The table \"{$table}\" has no primary key. Changes will have to be made manually.",
                        'results');
                    continue;
                }

                // create new table report instance
                $new_table_report          = $table_report;
                $new_table_report['start'] = microtime(true);

                $this->log('search_replace_table_start', $table, $search, $replace);

                // Count the number of rows we have in the table if large we'll split into blocks, This is a mod from Simon Wheatley
                $row_count = $this->db->get_var("SELECT COUNT(*) FROM {$dbname}`{$table}`");

                $page_size = $this->page_size;
                $pages     = ceil($row_count / $page_size);
                $offset    = 0;

                while (true) {

                    $data = $this->db->get_results(
                        $this->db->prepare("SELECT * FROM {$dbname}{$table} LIMIT %d, %d;", $offset, $page_size)
                        , ARRAY_A);
                    $count = count($data);

                    foreach ($data as $index => $row) {

                        $report['rows']++; // Increment the row counter
                        $new_table_report['rows']++;

                        $update_sql = array();
                        $where_sql  = array();
                        $update     = false;

                        foreach ($columns as $column) {
                            $edited_data = $data_to_fix = $row[$column];

                            if (in_array($column, $primary_key)) {
                                $where_sql[] = "`{$column}` = " . $this->db_escape($data_to_fix);
                                continue;
                            }

                            // exclude cols
                            if (in_array($column, $this->exclude_cols)) {
                                continue;
                            }

                            // include cols
                            if (!empty($this->include_cols) && !in_array($column, $this->include_cols)) {
                                continue;
                            }
                            // Run a search replace on the data that'll respect the serialisation.
                            $edited_data = $this->recursive_unserialize_replace($search, $replace, $data_to_fix);
                            // Something was changed
                            if ($edited_data != $data_to_fix) {

                                $report['change']++;
                                $new_table_report['change']++;

                                // log first x changes
                                if ($new_table_report['change'] <= $this->report_change_num) {
                                    $new_table_report['changes'][] = array(
                                        'row'    => $new_table_report['rows'],
                                        'column' => $column,
                                        'from'   => ($data_to_fix),
                                        'to'     => ($edited_data),
                                    );
                                }

                                $update_sql[] = "`{$column}` = " . $this->db_escape($edited_data);
                                $update       = true;
                            }

                        }

                        if ($dry_run) {
                            // nothing for this state
                        } elseif ($update && !empty($where_sql)) {
                            $sql = 'UPDATE ' . $dbname . $table . ' SET ' . implode(', ',
                                $update_sql) . ' WHERE ' . implode(' AND ', array_filter($where_sql));
                            $this->log('SQL', $sql);
                            $result = $this->db->query($sql);

                            if ($result === false) {
                                $this->add_error($this->$db->last_error . PHP_EOL . 'Last Query:' . $this->db->last_query, 'results');
                            } else {

                                $report['updates']++;
                                $new_table_report['updates']++;
                            }

                        }

                    }

                    $this->db_free_result($data);
                    if ($count < $page_size) {
                        break;
                    }
                    $offset += $page_size;
                }

                $new_table_report['end'] = microtime(true);

                // store table report in main
                $report['table_reports'][$table] = $new_table_report;

                // log result
                $this->log('search_replace_table_end', $table, $new_table_report);
            }

        }

        $report['end'] = microtime(true);

        $this->log('search_replace_end', $search, $replace, $report);

        return $report;
    }

    public function get_columns($table) {
        $primary_key = array();
        $columns     = array();
        $dbname      = '';
        if ($this->dbname != '') {
            $dbname = "`{$this->dbname}`.";
        }
        $table_desc = $this->db->get_results("DESCRIBE {$dbname}{$table}", ARRAY_A);
        foreach ($table_desc as $index => $field) {
            $columns[] = $field['Field'];
            if ($field['Key'] == 'PRI') {
                $primary_key[] = $field['Field'];
            }
        }
        return array($primary_key, $columns);
    }

    /**
     * Replace all occurrences of the search string with the replacement string.
     *
     * @param mixed $search
     * @param mixed $replace
     * @param mixed $subject
     * @param int $count
     *
     * @return mixed
     * @copyright Copyright 2012 Sean Murphy. All rights reserved.
     * @license http://creativecommons.org/publicdomain/zero/1.0/
     * @link http://php.net/manual/function.str-replace.php
     *
     * @author Sean Murphy <sean@iamseanmurphy.com>
     */
    public static function mb_str_replace($search, $replace, $subject, &$count = 0) {
        if (!is_array($subject)) {
            // Normalize $search and $replace so they are both arrays of the same length
            $searches     = is_array($search) ? array_values($search) : array($search);
            $replacements = is_array($replace) ? array_values($replace) : array($replace);
            $replacements = array_pad($replacements, count($searches), '');

            foreach ($searches as $key => $search) {
                $parts = mb_split(preg_quote($search), $subject);
                if (!is_array($parts)) {
                    continue;
                }
                $count += count($parts) - 1;
                $subject = implode($replacements[$key], $parts);
            }
        } else {
            // Call mb_str_replace for each subject in array, recursively
            foreach ($subject as $key => $value) {
                $subject[$key] = self::mb_str_replace($search, $replace, $value, $count);
            }
        }

        return $subject;
    }

    /**
     * Wrapper for regex/non regex search & replace
     *
     * @param string $search
     * @param string $replace
     * @param string $string
     * @param int $count
     *
     * @return string
     */
    public function str_replace($search, $replace, $string, &$count = 0) {
        if ($this->regex) {
            return preg_replace($search, $replace, $string, -1, $count);
        } elseif (function_exists('mb_split')) {
            return self::mb_str_replace($search, $replace, $string, $count);
        } else {
            return str_replace($search, $replace, $string, $count);
        }
    }

    /**
     * Convert a string containing unicode into HTML entities for front end display
     *
     * @param string $string
     *
     * @return string
     */
    public function charset_decode_utf_8($string) {
        /* Only do the slow convert if there are 8-bit characters */
        /* avoid using 0xA0 (\240) in ereg ranges. RH73 does not like that */
        if (!preg_match("/[\200-\237]/", $string) and !preg_match("/[\241-\377]/", $string)) {
            return $string;
        }

        // decode three byte unicode characters
        $string = preg_replace("/([\340-\357])([\200-\277])([\200-\277])/e",
            "'&#'.((ord('\\1')-224)*4096 + (ord('\\2')-128)*64 + (ord('\\3')-128)).';'",
            $string);

        // decode two byte unicode characters
        $string = preg_replace("/([\300-\337])([\200-\277])/e",
            "'&#'.((ord('\\1')-192)*64+(ord('\\2')-128)).';'",
            $string);

        return $string;
    }

}
