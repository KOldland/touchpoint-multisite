<?php
namespace KH\XAPI\Admin;

class SettingsPage {
    public function hooks(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_menu(): void {
        add_options_page(
            'KH xAPI Settings',
            'KH xAPI',
            'manage_options',
            'kh-xapi-settings',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'kh_xapi_settings', 'kh_xapi_lrs', [ $this, 'sanitize_settings' ] );
        register_setting( 'kh_xapi_settings', 'kh_xapi_reports', [ $this, 'sanitize_reports' ] );

        add_settings_section( 'kh_xapi_lrs_section', 'LRS Connection', '__return_false', 'kh-xapi-settings' );
        add_settings_field( 'kh_xapi_lrs_endpoint', 'Endpoint', [ $this, 'render_text_field' ], 'kh-xapi-settings', 'kh_xapi_lrs_section', [ 'key' => 'endpoint' ] );
        add_settings_field( 'kh_xapi_lrs_username', 'Username', [ $this, 'render_text_field' ], 'kh-xapi-settings', 'kh_xapi_lrs_section', [ 'key' => 'username' ] );
        add_settings_field( 'kh_xapi_lrs_password', 'Password', [ $this, 'render_password_field' ], 'kh-xapi-settings', 'kh_xapi_lrs_section', [ 'key' => 'password' ] );
        add_settings_field( 'kh_xapi_lrs_version', 'xAPI Version', [ $this, 'render_text_field' ], 'kh-xapi-settings', 'kh_xapi_lrs_section', [ 'key' => 'version', 'placeholder' => '1.0.3' ] );

        add_settings_section( 'kh_xapi_reports_section', 'Reports Hooks', '__return_false', 'kh-xapi-settings' );
        add_settings_field( 'kh_xapi_reports_scripts', 'Custom Scripts Handles', [ $this, 'render_textarea_field' ], 'kh-xapi-settings', 'kh_xapi_reports_section', [ 'key' => 'scripts', 'description' => 'Comma separated list of script handles to enqueue on reports UI.' ] );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>KH xAPI Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'kh_xapi_settings' );
                do_settings_sections( 'kh-xapi-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function sanitize_settings( array $input ): array {
        return [
            'endpoint' => isset( $input['endpoint'] ) ? esc_url_raw( trim( $input['endpoint'] ) ) : '',
            'username' => isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '',
            'password' => isset( $input['password'] ) ? $input['password'] : '',
            'version'  => isset( $input['version'] ) ? sanitize_text_field( $input['version'] ) : '1.0.3',
        ];
    }

    public function sanitize_reports( array $input ): array {
        $scripts = [];
        if ( ! empty( $input['scripts'] ) ) {
            $scripts = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $input['scripts'] ) ) ) );
        }

        return [
            'scripts' => implode( ',', $scripts ),
        ];
    }

    public function render_text_field( array $args ): void {
        $option = get_option( 'kh_xapi_lrs', [] );
        $key    = $args['key'];
        $value  = $option[ $key ] ?? '';
        $placeholder = $args['placeholder'] ?? '';
        printf(
            '<input type="text" name="kh_xapi_lrs[%1$s]" value="%2$s" class="regular-text" placeholder="%3$s" />',
            esc_attr( $key ),
            esc_attr( $value ),
            esc_attr( $placeholder )
        );
    }

    public function render_password_field( array $args ): void {
        $option = get_option( 'kh_xapi_lrs', [] );
        $key    = $args['key'];
        $value  = $option[ $key ] ?? '';
        printf(
            '<input type="password" name="kh_xapi_lrs[%1$s]" value="%2$s" class="regular-text" autocomplete="off" />',
            esc_attr( $key ),
            esc_attr( $value )
        );
    }

    public function render_textarea_field( array $args ): void {
        $option      = get_option( 'kh_xapi_reports', [] );
        $key         = $args['key'];
        $value       = $option[ $key ] ?? '';
        $description = $args['description'] ?? '';
        printf(
            '<textarea name="kh_xapi_reports[%1$s]" rows="4" cols="50" class="large-text">%2$s</textarea><p class="description">%3$s</p>',
            esc_attr( $key ),
            esc_textarea( $value ),
            esc_html( $description )
        );
    }
}
