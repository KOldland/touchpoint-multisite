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

if ( ! function_exists( 'normalize_fixture' ) ) {
    /**
     * Normalize volatile fixture fields for deterministic assertions.
     *
     * @param mixed $value Value to normalize.
     * @return mixed
     */
    function normalize_fixture( $value ) {
        if ( is_array( $value ) ) {
            $normalized = array();
            foreach ( $value as $key => $item ) {
                if ( is_string( $key ) && preg_match( '/(created|updated|timestamp|time)$/i', $key ) ) {
                    $normalized[ $key ] = '{{UNIX_TS}}';
                    continue;
                }
                if ( is_string( $key ) && preg_match( '/(^id$|_id$)/i', $key ) && is_scalar( $item ) ) {
                    $normalized[ $key ] = '{{ID}}';
                    continue;
                }
                $normalized[ $key ] = normalize_fixture( $item );
            }
            return $normalized;
        }

        if ( is_string( $value ) && preg_match( '/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$/', $value ) ) {
            return '{{ISO8601}}';
        }

        return $value;
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
        if ( isset( $GLOBALS['kh_test_caps'] ) && is_array( $GLOBALS['kh_test_caps'] ) && array_key_exists( $capability, $GLOBALS['kh_test_caps'] ) ) {
            return (bool) $GLOBALS['kh_test_caps'][ $capability ];
        }
        return true;
    }
}

if ( ! function_exists( 'get_user_meta' ) ) {
    function get_user_meta( $user_id, $key, $single = false ) {
        $store = $GLOBALS['kh_test_user_meta'][ $user_id ][ $key ] ?? null;
        if ( $single ) {
            return $store;
        }
        return $store !== null ? array( $store ) : array();
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

if ( ! function_exists( 'get_userdata' ) ) {
    function get_userdata( $user_id ) {
        $users = $GLOBALS['kh_test_users'] ?? array();
        if ( isset( $users[ $user_id ] ) ) {
            return (object) $users[ $user_id ];
        }

        return (object) array(
            'ID' => (int) $user_id,
            'display_name' => 'User ' . (int) $user_id,
            'user_email' => 'user' . (int) $user_id . '@example.com',
        );
    }
}

if ( ! function_exists( 'wp_mail' ) ) {
    function wp_mail( $to, $subject, $message, $headers = array(), $attachments = array() ) {
        if ( ! isset( $GLOBALS['kh_test_sent_mail'] ) || ! is_array( $GLOBALS['kh_test_sent_mail'] ) ) {
            $GLOBALS['kh_test_sent_mail'] = array();
        }

        $GLOBALS['kh_test_sent_mail'][] = array(
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'attachments' => $attachments,
        );

        return true;
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

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! class_exists( 'wpdb' ) ) {
    class wpdb {
        public $prefix = 'wp_';
        public $last_error = '';

        public function insert( $table, $data, $format = array() ) {
            $GLOBALS['kh_test_db_inserts'][] = array(
                'table' => $table,
                'data' => $data,
            );
            return true;
        }

        public function update( $table, $data, $where ) {
            return true;
        }

        public function get_var( $query ) {
            return null;
        }

        public function get_row( $query, $output = ARRAY_A ) {
            return null;
        }

        public function get_results( $query, $output = ARRAY_A ) {
            return array();
        }

        public function prepare( $query, ...$args ) {
            return $query;
        }

        public function query( $query ) {
            return true;
        }

        public function replace( $table, $data ) {
            $GLOBALS['kh_test_db_replaces'][] = array(
                'table' => $table,
                'data' => $data,
            );
            return true;
        }
    }
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

// TODO: Replace this test stub once PaidAdapterContract is available in the plugin load path.

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        if ( isset( $GLOBALS['kh_test_current_user_id'] ) ) {
            return (int) $GLOBALS['kh_test_current_user_id'];
        }
        return 1;
    }
}

if ( ! function_exists( 'maybe_serialize' ) ) {
    function maybe_serialize( $value ) {
        return serialize( $value );
    }
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
    function maybe_unserialize( $value ) {
        if ( is_serialized( $value ) ) {
            return @unserialize( $value );
        }
        return $value;
    }
}

if ( ! function_exists( 'is_serialized' ) ) {
    function is_serialized( $data ) {
        if ( ! is_string( $data ) ) {
            return false;
        }
        $data = trim( $data );
        if ( 'N;' === $data ) {
            return true;
        }
        if ( strlen( $data ) < 4 ) {
            return false;
        }
        if ( ':' !== $data[1] ) {
            return false;
        }
        return (bool) preg_match( '/^[aObsiCdz]/', $data );
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $value ) {
        return $value;
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES );
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES );
    }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = 'default' ) {
        echo htmlspecialchars( (string) $text, ENT_QUOTES );
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES );
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return htmlspecialchars( (string) $url, ENT_QUOTES );
    }
}

if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( $text, $domain = 'default' ) {
        echo htmlspecialchars( (string) $text, ENT_QUOTES );
    }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '', $title = '', $args = array() ) {
        throw new \RuntimeException( 'wp_die: ' . ( is_string( $message ) ? $message : 'died' ) );
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) {
        return 'http://example.com/wp-admin/' . ltrim( (string) $path, '/' );
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $args, $url = '' ) {
        return $url . '?' . http_build_query( $args );
    }
}

if ( ! function_exists( 'add_submenu_page' ) ) {
    function add_submenu_page( $parent, $page_title, $menu_title, $capability, $slug, $callback ) {
        if ( ! isset( $GLOBALS['kh_test_submenus'] ) ) {
            $GLOBALS['kh_test_submenus'] = array();
        }
        $GLOBALS['kh_test_submenus'][] = compact( 'parent', 'page_title', 'menu_title', 'capability', 'slug', 'callback' );
    }
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) {
        $out = '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce">';
        if ( $echo ) {
            echo $out;
        }
        return $out;
    }
}

if ( ! function_exists( 'selected' ) ) {
    function selected( $selected, $current = true, $echo = true ) {
        $result = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';
        if ( $echo ) {
            echo $result;
        }
        return $result;
    }
}

if ( ! function_exists( 'submit_button' ) ) {
    function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true ) {
        echo '<input type="submit" value="' . esc_attr( $text ) . '">';
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        return is_string( $value ) ? stripslashes( $value ) : $value;
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
