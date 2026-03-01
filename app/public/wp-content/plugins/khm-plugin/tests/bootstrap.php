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

        public function prepare($query, ...$args) {
            // Simple vsprintf-based prepare (not SQL-safe, just for testing)
            $query = str_replace('%d', '%s', $query);
            $query = str_replace('%f', '%s', $query);
            return vsprintf($query, $args);
        }

        public function insert($table, $data, $format = null) {
            $this->last_insert_id = rand(1, 99999);
            $this->data[$table][$this->last_insert_id] = $data;
            return 1;
        }

        public function get_var($query) {
            // Mock implementation - returns null for "table exists" checks
            if (strpos($query, 'SHOW TABLES') !== false) {
                return null;
            }
            // For event ID checks, return null (not processed)
            return null;
        }

        public function get_row($query, $output = OBJECT, $offset = 0) {
            return null;
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            return 1;
        }

        public function replace($table, $data, $format = null) {
            $this->last_insert_id = rand(1, 99999);
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            return 1;
        }

        public function query($query) {
            return true;
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

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = [];
        private $body = '';
        public function __construct($method = 'GET', $route = '') {}
        public function set_param($key, $value) { $this->params[$key] = $value; }
        public function get_param($key) { return $this->params[$key] ?? null; }
        public function get_params() { return $this->params; }
        public function set_body($body) { $this->body = (string) $body; }
        public function get_body() { return $this->body; }
        public function get_headers() { return []; }
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

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_\\-]/', '', $key);
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
