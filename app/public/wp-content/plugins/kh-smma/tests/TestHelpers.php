<?php

if ( ! function_exists( 'update_post_meta' ) ) {
    $GLOBALS['kh_test_post_meta'] = array();
    function update_post_meta( $post_id, $key, $value ) {
        $GLOBALS['kh_test_post_meta'][ $post_id ][ $key ] = $value;
        return true;
    }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $value ) {
        return abs( (int) $value );
    }
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key, $single = false ) {
        $store = $GLOBALS['kh_test_post_meta'][ $post_id ][ $key ] ?? null;
        if ( $single ) {
            return $store;
        }
        return $store !== null ? array( $store ) : array();
    }
}

if ( ! function_exists( 'wp_reset_postdata' ) ) {
    function wp_reset_postdata() {
        return;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $value ) {
        return json_encode( $value );
    }
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
    function wp_generate_uuid4() {
        return uniqid( 'uuid', true );
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text ) {
        return $text;
    }
}

if ( ! function_exists( 'rest_url' ) ) {
    function rest_url( $path = '' ) {
        return 'http://example.com/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
    function rest_ensure_response( $value ) {
        return $value;
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
        $GLOBALS['kh_test_filters'][ $tag ][ $priority ][] = array(
            'callback' => $callback,
            'accepted_args' => $accepted_args,
        );
        return true;
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value, ...$args ) {
        $filters = $GLOBALS['kh_test_filters'][ $tag ] ?? array();
        if ( empty( $filters ) ) {
            return $value;
        }
        ksort( $filters );
        foreach ( $filters as $callbacks ) {
            foreach ( $callbacks as $data ) {
                $callback = $data['callback'];
                $accepted = $data['accepted_args'];
                $call_args = array_merge( array( $value ), $args );
                $call_args = array_slice( $call_args, 0, $accepted );
                $value = call_user_func_array( $callback, $call_args );
            }
        }
        return $value;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
        return add_filter( $tag, $callback, $priority, $accepted_args );
    }
}

if ( ! function_exists( 'do_action' ) ) {
    function do_action( $tag, ...$args ) {
        apply_filters( $tag, null, ...$args );
    }
}

if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = array() ) {
        if ( isset( $GLOBALS['kh_test_remote_get_response'] ) ) {
            return $GLOBALS['kh_test_remote_get_response'];
        }
        return array( 'body' => '' );
    }
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        return $response['body'] ?? '';
    }
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
    function is_user_logged_in() {
        return false;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        return true;
    }
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, $action ) {
        return true;
    }
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action ) {
        return 'nonce';
    }
}

if ( ! function_exists( 'wp_salt' ) ) {
    function wp_salt( $scheme = 'auth' ) {
        return 'test-salt';
    }
}

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $post_id ) {
        return null;
    }
}

if ( ! function_exists( 'wp_insert_post' ) ) {
    function wp_insert_post( $args, $wp_error = false ) {
        if ( ! isset( $GLOBALS['kh_test_next_post_id'] ) ) {
            $GLOBALS['kh_test_next_post_id'] = 1000;
        }
        return $GLOBALS['kh_test_next_post_id']++;
    }
}

if ( ! function_exists( 'parse_blocks' ) ) {
    function parse_blocks( $content ) {
        return array();
    }
}

if ( ! function_exists( 'get_option' ) ) {
    $GLOBALS['kh_test_options'] = array();
    function get_option( $key, $default = false ) {
        return $GLOBALS['kh_test_options'][ $key ] ?? $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $key, $value ) {
        $GLOBALS['kh_test_options'][ $key ] = $value;
        return true;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ) {
        return is_string( $value ) ? trim( $value ) : $value;
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $value ) {
        return is_string( $value ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) : $value;
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) {
        return '2026-01-24 00:00:00';
    }
}

// TODO: Replace this test stub once PaidAdapterContract is available in the plugin load path.
if ( ! class_exists( '\\KH_SMMA\\Adapters\\PaidAdapterContract' ) ) {
    eval( 'namespace KH_SMMA\\Adapters { abstract class PaidAdapterContract { public function register() {} public function is_enabled(): bool { return false; } public function create_boost( array $payload ): array { return array(); } public function cancel_boost( array $payload ): array { return array(); } public function get_boost_status( array $payload ): array { return array(); } } }' );
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        return 1;
    }
}

if ( ! function_exists( 'maybe_serialize' ) ) {
    function maybe_serialize( $value ) {
        return serialize( $value );
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $value ) {
        return $value;
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error extends \Exception {
        public $errors = array();
        public function __construct( $code = '', $message = '', $data = null ) {
            parent::__construct( $message );
            $this->errors[ $code ] = array( $message );
        }
    }
}

if ( ! class_exists( 'wpdb' ) ) {
    class wpdb {
        public $prefix = 'wp_';
        public function insert( $table, $data ) {
            return true;
        }
    }
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private $params = array();
        private $headers = array();

        public function __construct( array $params = array(), array $headers = array() ) {
            $this->params = $params;
            $this->headers = $headers;
        }

        public function get_json_params() {
            return $this->params;
        }

        public function get_header( $key ) {
            return $this->headers[ $key ] ?? null;
        }

        public function get_param( $key ) {
            return $this->params[ $key ] ?? null;
        }
    }
}

if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $posts = array();

        public function __construct( $args = array() ) {
            $this->posts = $GLOBALS['kh_test_wp_query_posts'] ?? array();
        }
    }
}
