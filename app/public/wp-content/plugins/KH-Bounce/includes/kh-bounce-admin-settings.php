<?php
/**
 * Admin settings controller.
 */
class KH_Bounce_Admin_Settings {

    /** @var KH_Bounce_Plugin */
    protected $plugin;

    /** @var array */
    protected $templates = array();

    public function __construct( KH_Bounce_Plugin $plugin ) {
        $this->plugin   = $plugin;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'load-settings_page_kh-bounce', array( $this, 'register_help_tabs' ) );
    }

    public function register_menu() {
        add_options_page(
            __( 'KH Bounce Settings', 'kh-bounce' ),
            __( 'KH Bounce', 'kh-bounce' ),
            'manage_options',
            'kh-bounce',
            array( $this, 'render_page' )
        );
    }

    public function register_settings() {
        register_setting( 'kh_bounce_settings_group', 'kh_bounce_settings', array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'kh_bounce_display',
            __( 'Display Settings', 'kh-bounce' ),
            '__return_false',
            'kh-bounce'
        );

        $fields = array(
            'status'          => __( 'Status', 'kh-bounce' ),
            'template'        => __( 'Template', 'kh-bounce' ),
            'title'           => __( 'Modal Title', 'kh-bounce' ),
            'text'            => __( 'Body Text', 'kh-bounce' ),
            'cta_label'       => __( 'CTA Label', 'kh-bounce' ),
            'cta_url'         => __( 'CTA URL', 'kh-bounce' ),
            'dismiss_label'   => __( 'Dismiss Label', 'kh-bounce' ),
            'display_on_home' => __( 'Only on front page?', 'kh-bounce' ),
            'show_on_mobile'  => __( 'Display on mobile devices?', 'kh-bounce' ),
            'test_mode'       => __( 'QA Test Mode', 'kh-bounce' ),
        );

        foreach ( $fields as $field => $label ) {
            add_settings_field(
                'kh_bounce_' . $field,
                $label,
                array( $this, 'render_field' ),
                'kh-bounce',
                'kh_bounce_display',
                array( 'field' => $field )
            );
        }

        add_settings_section(
            'kh_bounce_telemetry',
            __( 'Telemetry & Analytics', 'kh-bounce' ),
            '__return_false',
            'kh-bounce'
        );

        add_settings_field(
            'kh_bounce_telemetry_mode',
            __( 'Telemetry Mode', 'kh-bounce' ),
            array( $this, 'render_field' ),
            'kh-bounce',
            'kh_bounce_telemetry',
            array( 'field' => 'telemetry_mode' )
        );
    }

    public function sanitize_settings( $input ) {
        $defaults = $this->plugin->get_settings();
        $output   = array();

        $output['status'] = in_array( $input['status'], array( 'on', 'off' ), true ) ? $input['status'] : 'off';

        $templates = array_keys( $this->get_templates() );
        $output['template'] = in_array( $input['template'], $templates, true ) ? $input['template'] : 'classic';

        $output['title'] = sanitize_text_field( $input['title'] );
        if ( '' === $output['title'] ) {
            $output['title'] = $defaults['title'];
        }

        $output['text'] = sanitize_textarea_field( $input['text'] );
        if ( '' === $output['text'] ) {
            $output['text'] = $defaults['text'];
        }

        $output['cta_label'] = sanitize_text_field( $input['cta_label'] );
        if ( '' === $output['cta_label'] ) {
            $output['cta_label'] = $defaults['cta_label'];
        }

        $cta_url = esc_url_raw( $input['cta_url'] );
        if ( ! empty( $cta_url ) && ! wp_http_validate_url( $cta_url ) ) {
            $cta_url = '';
        }
        $output['cta_url'] = $cta_url;

        $output['dismiss_label'] = sanitize_text_field( $input['dismiss_label'] );
        if ( '' === $output['dismiss_label'] ) {
            $output['dismiss_label'] = $defaults['dismiss_label'];
        }

        $output['display_on_home'] = ! empty( $input['display_on_home'] ) ? '1' : '0';
        $output['show_on_mobile']  = ! empty( $input['show_on_mobile'] ) ? '1' : '0';
        $output['test_mode']       = ! empty( $input['test_mode'] ) ? '1' : '0';

        $mode = isset( $input['telemetry_mode'] ) ? $input['telemetry_mode'] : 'none';
        $output['telemetry_mode'] = in_array( $mode, array( 'none', 'events', 'rest' ), true ) ? $mode : 'none';

        return $output;
    }

    public function enqueue_assets( $hook ) {
        if ( 'settings_page_kh-bounce' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'kh-bounce-admin', KH_BOUNCE_URL . 'assets/css/admin.css', array(), KH_BOUNCE_VERSION );
        wp_enqueue_script( 'kh-bounce-admin', KH_BOUNCE_URL . 'assets/js/admin.js', array( 'jquery' ), KH_BOUNCE_VERSION, true );
    }

    public function render_field( $args ) {
        $settings = $this->plugin->get_settings();
        $field    = $args['field'];
        $value    = isset( $settings[ $field ] ) ? $settings[ $field ] : '';

        switch ( $field ) {
            case 'status':
                printf(
                    '<select name="kh_bounce_settings[status]" id="kh_bounce_status"><option value="on" %s>%s</option><option value="off" %s>%s</option></select>',
                    selected( $value, 'on', false ),
                    esc_html__( 'Enabled', 'kh-bounce' ),
                    selected( $value, 'off', false ),
                    esc_html__( 'Disabled', 'kh-bounce' )
                );
                break;

            case 'template':
                echo '<select name="kh_bounce_settings[template]" id="wbounce_template">';
                foreach ( $this->get_templates() as $key => $meta ) {
                    printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $meta['label'] ) );
                }
                echo '</select>';
                echo '<p class="description">' . esc_html__( 'Choose a layout to control the modal structure.', 'kh-bounce' ) . '</p>';
                echo '<div class="kh-bounce-template-preview">';
                foreach ( $this->get_templates() as $key => $meta ) {
                    $card_classes = 'kh-bounce-template-card';
                    if ( $value === $key ) {
                        $card_classes .= ' active';
                    }
                    echo '<div class="' . esc_attr( $card_classes ) . '" id="kh-bounce-preview-' . esc_attr( $key ) . '">';
                    printf( '<header><strong>%1$s</strong></header>', esc_html( $meta['label'] ) );
                    printf( '<div class="kh-bounce-template-sim">%s</div>', KH_Bounce_Templates::render( $key, $settings, array( 'context' => 'admin' ) ) );
                    printf( '<p class="description">%s</p>', esc_html( $meta['description'] ) );
                    echo '</div>';
                }
                echo '</div>';
                break;

            case 'text':
                printf( '<textarea name="kh_bounce_settings[text]" id="kh_bounce_text" class="large-text" rows="4" required>%s</textarea>', esc_textarea( $value ) );
                break;

            case 'cta_url':
                printf( '<input type="url" name="kh_bounce_settings[cta_url]" id="kh_bounce_cta_url" value="%s" class="regular-text" placeholder="https://example.com/landing" />', esc_attr( $value ) );
                echo '<p class="description">' . esc_html__( 'Leave blank if you plan to capture email submissions instead of linking out.', 'kh-bounce' ) . '</p>';
                break;

            case 'display_on_home':
                printf( '<label><input type="checkbox" name="kh_bounce_settings[display_on_home]" value="1" %s /> %s</label>', checked( $value, '1', false ), esc_html__( 'Limit modal to the front page.', 'kh-bounce' ) );
                break;

            case 'show_on_mobile':
                printf( '<label><input type="checkbox" name="kh_bounce_settings[show_on_mobile]" value="1" %s /> %s</label>', checked( $value, '1', false ), esc_html__( 'Allow exit-intent modal to appear on mobile/tablet breakpoints.', 'kh-bounce' ) );
                echo '<p class="description">' . esc_html__( 'Recommended to leave disabled because mobile browsers rarely expose real mouseleave events.', 'kh-bounce' ) . '</p>';
                break;

            case 'test_mode':
                printf( '<label><input type="checkbox" name="kh_bounce_settings[test_mode]" value="1" %s /> %s</label>', checked( $value, '1', false ), esc_html__( 'Force-feed the modal to administrators (or anyone using ?kh-bounce-test=1).', 'kh-bounce' ) );
                echo '<p class="description">' . esc_html__( 'Use during QA to bypass exit intent/session gating. Disable before going live.', 'kh-bounce' ) . '</p>';
                break;

            case 'telemetry_mode':
                echo '<select name="kh_bounce_settings[telemetry_mode]" id="kh_bounce_telemetry_mode">';
                $modes = array(
                    'none'   => __( 'Disabled', 'kh-bounce' ),
                    'events' => __( 'CustomEvents only', 'kh-bounce' ),
                    'rest'   => __( 'CustomEvents + REST beacon', 'kh-bounce' ),
                );
                foreach ( $modes as $mode_key => $label ) {
                    printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $mode_key ), selected( $value, $mode_key, false ), esc_html( $label ) );
                }
                echo '</select>';
                echo '<p class="description">' . esc_html__( 'REST beacons trigger kh_bounce_telemetry so you can log events server-side.', 'kh-bounce' ) . '</p>';
                break;

            default:
                $required = in_array( $field, array( 'title', 'cta_label', 'dismiss_label' ), true ) ? 'required' : '';
                printf( '<input type="text" name="kh_bounce_settings[%1$s]" id="kh_bounce_%1$s" value="%2$s" class="regular-text" %3$s />', esc_attr( $field ), esc_attr( $value ), esc_attr( $required ) );
                break;
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap kh-bounce-settings">
            <h1><?php esc_html_e( 'KH Bounce', 'kh-bounce' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'kh_bounce_settings_group' );
                do_settings_sections( 'kh-bounce' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_help_tabs() {
        $screen = get_current_screen();
        if ( ! $screen || 'settings_page_kh-bounce' !== $screen->id ) {
            return;
        }

        $screen->add_help_tab( array(
            'id'      => 'kh_bounce_templates',
            'title'   => __( 'Templates', 'kh-bounce' ),
            'content' => '<div class="kh-bounce-help"><p>' . esc_html__( 'Explore the live previews below the template select and ensure the CTA copy matches the chosen layout.', 'kh-bounce' ) . '</p></div>',
        ) );

        $screen->add_help_tab( array(
            'id'      => 'kh_bounce_telemetry',
            'title'   => __( 'Telemetry', 'kh-bounce' ),
            'content' => '<div class="kh-bounce-help"><p>' . esc_html__( 'CustomEvents integrate with GTM/Segment listeners. REST beacons call kh-bounce/v1/event and trigger the kh_bounce_telemetry action for custom logging.', 'kh-bounce' ) . '</p></div>',
        ) );

        $screen->add_help_tab( array(
            'id'      => 'kh_bounce_testing',
            'title'   => __( 'QA Checklist', 'kh-bounce' ),
            'content' => '<div class="kh-bounce-help"><ul><li>' . esc_html__( 'Enable QA Test Mode to surface the modal instantly (admins only).', 'kh-bounce' ) . '</li><li>' . esc_html__( 'Alternatively append ?kh-bounce-test=1 to any URL for a one-off preview.', 'kh-bounce' ) . '</li><li>' . esc_html__( 'Verify the modal stays suppressed on mobile when the toggle is off.', 'kh-bounce' ) . '</li></ul></div>',
        ) );
    }

    protected function get_templates() {
        if ( empty( $this->templates ) ) {
            $this->templates = KH_Bounce_Templates::all();
        }

        return $this->templates;
    }
}
