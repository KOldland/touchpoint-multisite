<?php
namespace KH_SMMA\OAuth;

use KH_SMMA\Services\TokenRepository;

use function absint;
use function add_action;
use function add_query_arg;
use function admin_url;
use function apply_filters;
use function base64_encode;
use function check_admin_referer;
use function current_user_can;
use function delete_transient;
use function esc_html__;
use function get_post_meta;
use function get_transient;
use function http_build_query;
use function json_decode;
use function is_wp_error;
use function sanitize_key;
use function sanitize_text_field;
use function set_transient;
use function do_action;
use function time;
use function update_post_meta;
use function wp_create_nonce;
use function wp_die;
use function wp_generate_password;
use function wp_remote_get;
use function rawurlencode;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_safe_redirect;
use const MINUTE_IN_SECONDS;
use const HOUR_IN_SECONDS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OAuthManager {
    private TokenRepository $tokens;

    public function __construct( TokenRepository $tokens ) {
        $this->tokens = $tokens;
    }

    public function register(): void {
        add_action( 'admin_post_kh_smma_oauth_start', array( $this, 'handle_start' ) );
        add_action( 'admin_post_kh_smma_oauth_callback', array( $this, 'handle_callback' ) );
    }

    public function handle_start(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_oauth_start' );

        $provider = sanitize_text_field( $_POST['provider'] ?? '' );
        $account  = absint( $_POST['account_id'] ?? 0 );

        $config = $this->get_provider_config( $provider );
        if ( empty( $provider ) || empty( $account ) || empty( $config ) ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'oauth-missing' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $redirect_uri = $this->build_redirect_uri( $provider );
        $state        = wp_generate_password( 24, false );
        $state_data   = array(
            'account_id' => $account,
            'provider'   => $provider,
        );

        $params = array(
            'client_id'     => $config['client_id'],
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => $config['scope'],
            'state'         => $state,
        );

        if ( 'meta' === $provider && ! empty( $config['permissions_mode'] ) ) {
            $params['auth_type'] = $config['permissions_mode'];
        }

        if ( 'twitter' === $provider ) {
            $verifier                      = $this->generate_code_verifier();
            $state_data['code_verifier']    = $verifier;
            $params['code_challenge']       = $this->generate_code_challenge( $verifier );
            $params['code_challenge_method']= 'S256';
        }

        $this->store_state( $state, $state_data );

        $auth_url = $config['authorize_url'] . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
        wp_safe_redirect( $auth_url );
        exit;
    }

    public function handle_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kh-smma' ) );
        }

        check_admin_referer( 'kh_smma_oauth_callback' );

        $provider = sanitize_text_field( $_GET['provider'] ?? '' );
        $state    = sanitize_key( $_GET['state'] ?? '' );
        $code     = sanitize_text_field( $_GET['code'] ?? '' );

        $state_data = $this->consume_state( $state );
        if ( ! $state_data || $state_data['provider'] !== $provider ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'oauth-state' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( empty( $code ) ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'oauth-no-code' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $config = $this->get_provider_config( $provider );
        if ( empty( $config ) ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'oauth-config' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $redirect_uri = $this->build_redirect_uri( $provider );
        $token_data   = $this->exchange_code( $provider, $code, $redirect_uri, $config, $state_data );

        if ( is_wp_error( $token_data ) ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'oauth-error' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $account = absint( $state_data['account_id'] );
        $existing_id = absint( get_post_meta( $account, '_kh_smma_token_id', true ) );

        if ( $existing_id ) {
            $this->tokens->update_token( $existing_id, $token_data );
            $token_id = $existing_id;
        } else {
            $token_id = $this->tokens->save_token( $account, $token_data );
        }

        update_post_meta( $account, '_kh_smma_token_id', $token_id );
        update_post_meta( $account, '_kh_smma_status', 'connected' );

        do_action( 'kh_smma_oauth_success', $provider, $account, $token_data );

        wp_safe_redirect( add_query_arg( array( 'page' => 'kh-smma-dashboard', 'message' => 'oauth-success' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function get_provider_config( string $provider ): ?array {
        $defaults = array(
            'meta' => array(
                'client_id'     => defined( 'KH_SMMA_META_CLIENT_ID' ) ? KH_SMMA_META_CLIENT_ID : '',
                'client_secret' => defined( 'KH_SMMA_META_CLIENT_SECRET' ) ? KH_SMMA_META_CLIENT_SECRET : '',
                'authorize_url' => 'https://www.facebook.com/v18.0/dialog/oauth',
                'token_url'     => 'https://graph.facebook.com/v18.0/oauth/access_token',
                'scope'         => 'pages_manage_posts,pages_read_engagement,pages_show_list',
                'permissions_mode' => 'rerequest',
            ),
            'linkedin' => array(
                'client_id'     => defined( 'KH_SMMA_LINKEDIN_CLIENT_ID' ) ? KH_SMMA_LINKEDIN_CLIENT_ID : '',
                'client_secret' => defined( 'KH_SMMA_LINKEDIN_CLIENT_SECRET' ) ? KH_SMMA_LINKEDIN_CLIENT_SECRET : '',
                'authorize_url' => 'https://www.linkedin.com/oauth/v2/authorization',
                'token_url'     => 'https://www.linkedin.com/oauth/v2/accessToken',
                'scope'         => 'r_liteprofile r_emailaddress w_member_social',
            ),
            'twitter' => array(
                'client_id'     => defined( 'KH_SMMA_TWITTER_CLIENT_ID' ) ? KH_SMMA_TWITTER_CLIENT_ID : '',
                'client_secret' => defined( 'KH_SMMA_TWITTER_CLIENT_SECRET' ) ? KH_SMMA_TWITTER_CLIENT_SECRET : '',
                'authorize_url' => 'https://twitter.com/i/oauth2/authorize',
                'token_url'     => 'https://api.twitter.com/2/oauth2/token',
                'scope'         => 'tweet.read tweet.write users.read offline.access',
            ),
        );

        $config = $defaults[ $provider ] ?? null;
        return apply_filters( 'kh_smma_provider_config', $config, $provider );
    }

    private function build_redirect_uri( string $provider ): string {
        return add_query_arg(
            array(
                'action'   => 'kh_smma_oauth_callback',
                'provider' => $provider,
                '_wpnonce' => wp_create_nonce( 'kh_smma_oauth_callback' ),
            ),
            admin_url( 'admin-post.php' )
        );
    }

    private function store_state( string $state, array $data ): void {
        set_transient( 'kh_smma_oauth_state_' . $state, $data, 10 * MINUTE_IN_SECONDS );
    }

    private function consume_state( string $state ) {
        if ( empty( $state ) ) {
            return null;
        }

        $data = get_transient( 'kh_smma_oauth_state_' . $state );
        delete_transient( 'kh_smma_oauth_state_' . $state );
        return $data;
    }

    private function exchange_code( string $provider, string $code, string $redirect_uri, array $config, array $state_data ) {
        switch ( $provider ) {
            case 'meta':
                return $this->exchange_meta( $code, $redirect_uri, $config );
            case 'linkedin':
                return $this->exchange_linkedin( $code, $redirect_uri, $config );
            case 'twitter':
                return $this->exchange_twitter( $code, $redirect_uri, $config, $state_data );
            default:
                return null;
        }
    }

    private function exchange_meta( string $code, string $redirect_uri, array $config ) {
        $response = wp_remote_post( $config['token_url'], array(
            'timeout' => 20,
            'body'    => array(
                'client_id'     => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri'  => $redirect_uri,
                'code'          => $code,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['access_token'] ) ) {
            return new \WP_Error( 'kh_smma_meta_token', __( 'Meta token exchange failed.', 'kh-smma' ), $data );
        }

        $page_data = $this->fetch_meta_page( $data['access_token'] );

        return array(
            'provider'          => 'meta',
            'access_token'      => $data['access_token'],
            'refresh_token'     => $data['refresh_token'] ?? '',
            'expires_at'        => time() + (int) ( $data['expires_in'] ?? HOUR_IN_SECONDS ),
            'page_id'           => $page_data['id'] ?? '',
            'page_access_token' => $page_data['access_token'] ?? '',
        );
    }

    private function fetch_meta_page( string $user_token ): array {
        $response = wp_remote_get( 'https://graph.facebook.com/v18.0/me/accounts?access_token=' . rawurlencode( $user_token ) );
        if ( is_wp_error( $response ) ) {
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body['data'][0] ?? array();
    }

    private function exchange_linkedin( string $code, string $redirect_uri, array $config ) {
        $response = wp_remote_post( $config['token_url'], array(
            'timeout' => 20,
            'body'    => array(
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $redirect_uri,
                'client_id'     => $config['client_id'],
                'client_secret' => $config['client_secret'],
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $token = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $token['access_token'] ) ) {
            return new \WP_Error( 'kh_smma_linkedin_token', __( 'LinkedIn token exchange failed.', 'kh-smma' ), $token );
        }

        $profile_resp = wp_remote_get( 'https://api.linkedin.com/v2/me', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token['access_token'] ),
            'timeout' => 15,
        ) );

        $profile = array();
        if ( ! is_wp_error( $profile_resp ) ) {
            $profile = json_decode( wp_remote_retrieve_body( $profile_resp ), true );
        }

        $author = ! empty( $profile['id'] ) ? 'urn:li:person:' . $profile['id'] : '';

        return array(
            'provider'      => 'linkedin',
            'access_token'  => $token['access_token'],
            'expires_at'    => time() + (int) ( $token['expires_in'] ?? HOUR_IN_SECONDS ),
            'author'        => $author,
            'refresh_token' => $token['refresh_token'] ?? '',
        );
    }

    private function exchange_twitter( string $code, string $redirect_uri, array $config, array $state_data ) {
        $verifier = $state_data['code_verifier'] ?? '';
        if ( empty( $verifier ) ) {
            return new \WP_Error( 'kh_smma_twitter_verifier', __( 'Missing PKCE verifier for Twitter.', 'kh-smma' ) );
        }

        $response = wp_remote_post( $config['token_url'], array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $config['client_id'] . ':' . $config['client_secret'] ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => http_build_query( array(
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $redirect_uri,
                'code_verifier'=> $verifier,
            ), '', '&', PHP_QUERY_RFC3986 ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $token = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $token['access_token'] ) ) {
            return new \WP_Error( 'kh_smma_twitter_token', __( 'Twitter token exchange failed.', 'kh-smma' ), $token );
        }

        $user_resp = wp_remote_get( 'https://api.twitter.com/2/users/me', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token['access_token'] ),
            'timeout' => 15,
        ) );

        $user = array();
        if ( ! is_wp_error( $user_resp ) ) {
            $user = json_decode( wp_remote_retrieve_body( $user_resp ), true );
        }

        return array(
            'provider'      => 'twitter',
            'bearer_token'  => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? '',
            'expires_at'    => time() + (int) ( $token['expires_in'] ?? HOUR_IN_SECONDS ),
            'user_id'       => $user['data']['id'] ?? '',
            'username'      => $user['data']['username'] ?? '',
        );
    }

    private function generate_code_verifier(): string {
        return wp_generate_password( 64, false );
    }

    private function generate_code_challenge( string $verifier ): string {
        return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
    }
}
