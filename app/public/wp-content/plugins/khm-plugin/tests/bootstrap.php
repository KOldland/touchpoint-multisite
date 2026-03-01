<?php
/**
 * PHPUnit bootstrap file for KHM Plugin tests
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define test environment constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 4) . '/');
}

// Brain\Monkey setup for unit tests
require_once dirname(__DIR__) . '/vendor/antecedent/patchwork/Patchwork.php';

/**
 * Mock WordPress global $wpdb for tests that need database access
 */
global $wpdb;

if (!isset($wpdb)) {
    // Create a mock wpdb object
    $wpdb = new class {
        public $prefix = 'wp_';
        private $last_insert_id = 0;
        private $data = [];
        private $tables = [];

        public function prepare($query, ...$args) {
            $prepared = $query;
            foreach ($args as $arg) {
                if (is_numeric($arg)) {
                    $replacement = (string) $arg;
                } else {
                    $replacement = "'" . str_replace("'", "\\'", (string) $arg) . "'";
                }

                $prepared = preg_replace('/%[dfs]/', $replacement, $prepared, 1);
            }
            return $prepared;
        }

        public function insert($table, $data, $format = null) {
            $table = $this->normalize_table($table);
            $this->ensure_table($table);

            if ($table === $this->prefix . 'khm_processed_webhooks' && isset($data['event_id'])) {
                foreach ($this->data[$table] as $row) {
                    if (($row['event_id'] ?? null) === $data['event_id']) {
                        return false;
                    }
                }
            }
            if ($table === $this->prefix . 'khm_membership_webhook_operations' && isset($data['operation_key'])) {
                foreach ($this->data[$table] as $row) {
                    if (($row['operation_key'] ?? null) === $data['operation_key']) {
                        return false;
                    }
                }
            }

            $this->last_insert_id++;
            if (!isset($data['id'])) {
                $data['id'] = $this->last_insert_id;
            }
            $this->data[$table][] = $data;
            return 1;
        }

        public function get_var($query) {
            $query = trim((string) $query);

            if (preg_match("/SHOW TABLES LIKE ['\"]?([^'\"]+)['\"]?/i", $query, $m)) {
                $table = $this->normalize_table($m[1]);
                return in_array($table, $this->tables, true) ? $table : null;
            }

            if (preg_match('/SELECT COUNT\(\*\) FROM\s+([a-zA-Z0-9_`]+)\s+WHERE\s+event_id\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $event_id = $m[2];
                $count = 0;
                foreach ($this->data[$table] ?? [] as $row) {
                    if (($row['event_id'] ?? null) === $event_id) {
                        $count++;
                    }
                }
                return $count;
            }

            if (preg_match('/SELECT COUNT\(\*\) FROM\s+([a-zA-Z0-9_`]+)\s+WHERE\s+operation_key\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $operation_key = $m[2];
                $count = 0;
                foreach ($this->data[$table] ?? [] as $row) {
                    if (($row['operation_key'] ?? null) === $operation_key) {
                        $count++;
                    }
                }
                return $count;
            }

            if (preg_match('/SELECT\s+(?:id|event_id)\s+FROM\s+([a-zA-Z0-9_`]+)\s+WHERE\s+event_id\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $event_id = $m[2];
                foreach ($this->data[$table] ?? [] as $row) {
                    if (($row['event_id'] ?? null) === $event_id) {
                        return $row['id'] ?? $row['event_id'];
                    }
                }
                return null;
            }

            if (preg_match('/SELECT\s+id\s+FROM\s+([a-zA-Z0-9_`]+)\s+WHERE\s+operation_key\s*=\s*[\'"]?([^\'"\s]+)[\'"]?\s+AND\s+outcome\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $operation_key = $m[2];
                $outcome = $m[3];
                foreach ($this->data[$table] ?? [] as $row) {
                    if (($row['operation_key'] ?? null) === $operation_key && ($row['outcome'] ?? null) === $outcome) {
                        return $row['id'] ?? 1;
                    }
                }
                return null;
            }

            if (preg_match('/SELECT\s+user_id\s+FROM\s+([a-zA-Z0-9_`]+)\s+WHERE\s+stripe_customer_id\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $customer = $m[2];
                foreach ($this->data[$table] ?? [] as $row) {
                    if (($row['stripe_customer_id'] ?? null) === $customer) {
                        return $row['user_id'] ?? null;
                    }
                }
                return null;
            }

            return null;
        }

        public function get_row($query, $output = OBJECT, $offset = 0) {
            $query = trim((string) $query);
            if (preg_match('/SELECT\s+status\s+FROM\s+([a-zA-Z0-9_`]+)\s+WHERE\s+event_id\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $event_id = $m[2];
                foreach ($this->data[$table] ?? [] as $row) {
                    if (($row['event_id'] ?? null) === $event_id) {
                        $result = ['status' => $row['status'] ?? null];
                        return $output === ARRAY_A ? $result : (object) $result;
                    }
                }
                return null;
            }

            if (preg_match('/SELECT\s+status,\s*attempts\s+FROM\s+([a-zA-Z0-9_`]+)\s+WHERE\s+operation_key\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $operation_key = $m[2];
                foreach ($this->data[$table] ?? [] as $row) {
                    if (($row['operation_key'] ?? null) === $operation_key) {
                        $result = [
                            'status' => $row['status'] ?? null,
                            'attempts' => $row['attempts'] ?? null,
                        ];
                        return $output === ARRAY_A ? $result : (object) $result;
                    }
                }
                return null;
            }

            if (preg_match('/SELECT \* FROM\s+([a-zA-Z0-9_`]+)\s+WHERE\s+user_id\s*=\s*([0-9]+)/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $user_id = (int) $m[2];
                foreach ($this->data[$table] ?? [] as $row) {
                    if ((int)($row['user_id'] ?? 0) === $user_id) {
                        return $output === ARRAY_A ? $row : (object) $row;
                    }
                }
                return null;
            }

            if (preg_match('/SELECT \* FROM\s+([a-zA-Z0-9_`]+)\s+WHERE\s+event_id\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $event_id = $m[2];
                foreach ($this->data[$table] ?? [] as $row) {
                    if (($row['event_id'] ?? null) === $event_id) {
                        return $output === ARRAY_A ? $row : (object) $row;
                    }
                }
                return null;
            }

            return null;
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            $table = $this->normalize_table($table);
            $this->ensure_table($table);
            $updated = 0;
            foreach ($this->data[$table] as $idx => $row) {
                if ($this->matches_where($row, (array) $where)) {
                    $this->data[$table][$idx] = array_merge($row, (array) $data);
                    $updated++;
                }
            }
            if ($updated > 0) {
                return $updated;
            }
            return 1;
        }

        public function replace($table, $data, $format = null) {
            $table = $this->normalize_table($table);
            $this->ensure_table($table);
            $matched = false;
            if ($table === $this->prefix . 'user_membership' && isset($data['user_id'])) {
                foreach ($this->data[$table] as $idx => $row) {
                    if ((int)($row['user_id'] ?? 0) === (int)$data['user_id']) {
                        $this->data[$table][$idx] = array_merge($row, $data);
                        $matched = true;
                        break;
                    }
                }
            }
            if (!$matched) {
                $this->insert($table, $data, $format);
            }
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            $table = $this->normalize_table($table);
            $this->ensure_table($table);
            if (empty($where)) {
                $this->data[$table] = [];
                return 1;
            }
            $remaining = [];
            foreach ($this->data[$table] as $row) {
                if (!$this->matches_where($row, (array) $where)) {
                    $remaining[] = $row;
                }
            }
            $this->data[$table] = $remaining;
            return 1;
        }

        public function query($query) {
            $query = trim((string) $query);
            if (preg_match('/CREATE TABLE\s+([a-zA-Z0-9_`]+)/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $this->ensure_table($table);
                return true;
            }

            if (preg_match('/DELETE FROM\s+([a-zA-Z0-9_`]+)/i', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $this->ensure_table($table);
                $this->data[$table] = [];
                return true;
            }

            return true;
        }

        public function get_results($query, $output = OBJECT) {
            $query = trim((string) $query);
            if (preg_match('/SELECT .* FROM\s+([a-zA-Z0-9_`]+).*LIMIT\s+([0-9]+)/is', $query, $m)) {
                $table = $this->normalize_table($m[1]);
                $limit = (int) $m[2];
                $rows = array_slice(array_reverse($this->data[$table] ?? []), 0, $limit);
                if ($output === ARRAY_A) {
                    return $rows;
                }
                return array_map(fn($row) => (object) $row, $rows);
            }
            return [];
        }

        public function get_charset_collate() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        public function __get($name) {
            if ($name === 'insert_id') {
                return $this->last_insert_id;
            }
            return null;
        }

        private function normalize_table($table) {
            return str_replace('`', '', (string) $table);
        }

        private function ensure_table($table) {
            if (!isset($this->data[$table])) {
                $this->data[$table] = [];
            }
            if (!in_array($table, $this->tables, true)) {
                $this->tables[] = $table;
            }
        }

        private function matches_where(array $row, array $where): bool {
            foreach ($where as $k => $v) {
                if (!array_key_exists($k, $row)) {
                    return false;
                }
                if ((string) $row[$k] !== (string) $v) {
                    return false;
                }
            }
            return true;
        }
    };
}

// Mock WordPress functions commonly used
if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($response) {
        if ($response instanceof WP_REST_Response) {
            return $response;
        }
        return new WP_REST_Response($response, 200);
    }
}


if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user($username, $strict = false) {
        $username = strtolower((string) $username);
        return preg_replace('/[^a-z0-9_\-]/', '', $username);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_\\-]/', '', $key);
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs((int) $value);
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('email_exists')) {
    function email_exists($email) {
        return false; // Mock: user doesn't exist
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        return 'test_password_' . bin2hex(random_bytes(6));
    }
}

if (!function_exists('wp_create_user')) {
    function wp_create_user($username, $password, $email = '') {
        return rand(1, 99999);
    }
}

if (!function_exists('username_exists')) {
    function username_exists($username) {
        return false;
    }
}

if (!function_exists('wp_rand')) {
    function wp_rand($min = 0, $max = 0) {
        return mt_rand((int) $min, (int) $max);
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        return false;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 0; // Not logged in by default
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return false;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $khm_test_options;
        if (!isset($khm_test_options) || !is_array($khm_test_options)) {
            $khm_test_options = [];
        }
        return array_key_exists($option, $khm_test_options) ? $khm_test_options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $khm_test_options;
        if (!isset($khm_test_options) || !is_array($khm_test_options)) {
            $khm_test_options = [];
        }
        $khm_test_options[$option] = $value;
        return true;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.com' . $path;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        global $khm_test_filters;
        if (empty($khm_test_filters[$tag]) || !is_array($khm_test_filters[$tag])) {
            return $value;
        }

        foreach ($khm_test_filters[$tag] as $callback) {
            if (is_callable($callback)) {
                $value = $callback($value, ...$args);
            }
        }
        return $value;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback) {
        global $khm_test_filters;
        if (!isset($khm_test_filters[$tag])) {
            $khm_test_filters[$tag] = [];
        }
        $khm_test_filters[$tag][] = $callback;
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        return $gmt ? gmdate('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $khm_test_transients;
        $now = time();
        if (empty($khm_test_transients[$transient])) {
            return false;
        }
        if (($khm_test_transients[$transient]['expires'] ?? 0) < $now) {
            unset($khm_test_transients[$transient]);
            return false;
        }
        return $khm_test_transients[$transient]['value'];
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $khm_test_transients;
        if (!is_array($khm_test_transients ?? null)) {
            $khm_test_transients = [];
        }
        $khm_test_transients[$transient] = [
            'value' => $value,
            'expires' => time() + (int) $expiration,
        ];
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $khm_test_transients;
        unset($khm_test_transients[$transient]);
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        return false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        return true;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('__return_true')) {
    function __return_true() {
        return true;
    }
}

if (!function_exists('__return_false')) {
    function __return_false() {
        return false;
    }
}

if (!function_exists('error_log')) {
    function error_log($message, $message_type = 0, $destination = null, $extra_headers = null) {
        // Suppress error logs during tests
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation - do nothing
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        return null;
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value) {
        return true;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false) {
        return $single ? null : [];
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($tag, $callback) {
        global $khm_test_filters;
        if (empty($khm_test_filters[$tag]) || !is_array($khm_test_filters[$tag])) {
            return false;
        }

        foreach ($khm_test_filters[$tag] as $idx => $registered) {
            if ($registered === $callback) {
                unset($khm_test_filters[$tag][$idx]);
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = [], $override = false) {
        // Mock implementation - do nothing
        return true;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message($code = '') {
            return $this->message;
        }

        public function get_error_data($code = '') {
            return $this->data;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $method;
        private $route;
        private $body;
        private $params = [];
        private $headers = [];

        public function __construct($method = 'GET', $route = '') {
            $this->method = $method;
            $this->route = $route;
        }

        public function set_body($body) {
            $this->body = $body;
        }

        public function get_body() {
            return $this->body;
        }

        public function get_json_params() {
            return json_decode($this->body, true) ?: [];
        }

        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }

        public function get_param($key) {
            return $this->params[$key] ?? null;
        }

        public function set_route($route) {
            $this->route = $route;
        }

        public function set_header($key, $value) {
            $this->headers[$key] = $value;
        }

        public function get_header($key) {
            return $this->headers[$key] ?? '';
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;

        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }
    }
}

// Define WordPress constants
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

echo "Test bootstrap loaded\n";
