<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register meta so it is accessible without ACF.
add_action( 'init', function () {
    $meta_keys = [
        // Ad fields
        'ad_type',
        'ad_format',
        'campaign_type',
        'campaign_start',
        'campaign_end',
        'target_category',
        'ad_image',
        'headline',
        'ad_subheadline',
        'ad_body',
        'ad_button_text',
        'ad_button_url',
        'ad_badge',
        'ad_code',
        'destination_link',
        'ad_priority',
        'ad_impressions',
        'ad_clicks',
        'card_headline',
        'card_subheading',
        'card_body',
        'card_body_text',
        'card_button_text',
        'card_button_url',
        'card_background_color',
        'card_text_color',
    ];

    foreach ( $meta_keys as $key ) {
        register_post_meta(
            'ad_unit',
            $key,
            [
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
            ]
        );
    }

    $sponsor_meta = array(
        'linkedin_page_url',
        'linkedin_handles',
        'quotable_representatives',
        'content_library_url',
        'allowed_claims',
        'co_brand_policy',
        'geo_rules',
        'ppc_budget_total',
        'ppc_daily_cap',
        'ppc_account_id',
        'approval_contact',
        'sponsor_assets',
    );

    foreach ( $sponsor_meta as $key ) {
        register_post_meta(
            'kh_sponsor',
            $key,
            [
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
            ]
        );
    }

    // Slot override meta for posts (mode and manual/auto code).
    $slots = [ 'exit_overlay', 'footer', 'header', 'popup', 'sidebar1', 'sidebar2', 'ticker', 'slide_in' ];
    foreach ( $slots as $slot ) {
        register_post_meta(
            'post',
            "{$slot}_ad_mode",
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
            ]
        );
        register_post_meta(
            'post',
            "manual_ad_code_{$slot}",
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'wp_kses_post',
                'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
            ]
        );
        register_post_meta(
            'post',
            "ad_code_{$slot}",
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'wp_kses_post',
                'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
            ]
        );
    }
} );

// Ad Unit meta box (native)
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'kh_ad_details',
        __( 'Ad Details', 'kh-ad-manager' ),
        'kh_ad_manager_render_ad_metabox',
        'ad_unit',
        'normal',
        'high'
    );
    add_meta_box(
        'kh_ad_card',
        __( 'Card Ad Builder', 'kh-ad-manager' ),
        'kh_ad_manager_render_card_metabox',
        'ad_unit',
        'normal',
        'default'
    );

    add_meta_box(
        'kh_sponsor_details',
        __( 'Sponsor Details', 'kh-ad-manager' ),
        'kh_ad_manager_render_sponsor_metabox',
        'kh_sponsor',
        'normal',
        'high'
    );
} );

function kh_ad_manager_render_sponsor_metabox( $post ) {
    wp_nonce_field( 'kh_ad_sponsor_meta', 'kh_ad_sponsor_meta_nonce' );

    $fields = array(
        'linkedin_page_url' => array( 'label' => __( 'LinkedIn Page URL', 'kh-ad-manager' ), 'type' => 'url' ),
        'linkedin_handles' => array( 'label' => __( 'LinkedIn Handles (comma-separated)', 'kh-ad-manager' ), 'type' => 'text' ),
        'quotable_representatives' => array( 'label' => __( 'Quotable Representatives (JSON)', 'kh-ad-manager' ), 'type' => 'textarea' ),
        'content_library_url' => array( 'label' => __( 'Content Library URL', 'kh-ad-manager' ), 'type' => 'url' ),
        'allowed_claims' => array( 'label' => __( 'Allowed Claims (one per line)', 'kh-ad-manager' ), 'type' => 'textarea' ),
        'co_brand_policy' => array( 'label' => __( 'Co-brand Policy', 'kh-ad-manager' ), 'type' => 'select', 'options' => array( 'co-brand' => 'Co-brand', 'sponsor-author' => 'Sponsor-author', 'replace-creative' => 'Replace creative' ) ),
        'geo_rules' => array( 'label' => __( 'GEO Rules (JSON)', 'kh-ad-manager' ), 'type' => 'textarea' ),
        'ppc_budget_total' => array( 'label' => __( 'PPC Budget Total', 'kh-ad-manager' ), 'type' => 'number' ),
        'ppc_daily_cap' => array( 'label' => __( 'PPC Daily Cap', 'kh-ad-manager' ), 'type' => 'number' ),
        'ppc_account_id' => array( 'label' => __( 'PPC Account ID', 'kh-ad-manager' ), 'type' => 'text' ),
        'approval_contact' => array( 'label' => __( 'Approval Contact (email)', 'kh-ad-manager' ), 'type' => 'email' ),
        'sponsor_assets' => array( 'label' => __( 'Sponsor Assets (JSON)', 'kh-ad-manager' ), 'type' => 'textarea' ),
    );

    echo '<table class="form-table">';
    foreach ( $fields as $key => $config ) {
        $val = get_post_meta( $post->ID, $key, true );
        if ( 'allowed_claims' === $key && is_array( $val ) ) {
            $val = implode( "\n", $val );
        }
        echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $config['label'] ) . '</label></th><td>';
        switch ( $config['type'] ) {
            case 'select':
                echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '">';
                foreach ( $config['options'] as $opt_key => $opt_label ) {
                    echo '<option value="' . esc_attr( $opt_key ) . '" ' . selected( $val, $opt_key, false ) . '>' . esc_html( $opt_label ) . '</option>';
                }
                echo '</select>';
                break;
            case 'textarea':
                echo '<textarea name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" class="large-text" rows="4">' . esc_textarea( is_array( $val ) ? wp_json_encode( $val ) : $val ) . '</textarea>';
                break;
            default:
                echo '<input type="' . esc_attr( $config['type'] ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text" />';
                break;
        }
        echo '</td></tr>';
    }
    echo '</table>';
}

add_action( 'save_post_kh_sponsor', function( $post_id ) {
    if ( ! isset( $_POST['kh_ad_sponsor_meta_nonce'] ) || ! wp_verify_nonce( $_POST['kh_ad_sponsor_meta_nonce'], 'kh_ad_sponsor_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = array(
        'linkedin_page_url' => 'url',
        'linkedin_handles' => 'list',
        'quotable_representatives' => 'json',
        'content_library_url' => 'url',
        'allowed_claims' => 'lines',
        'co_brand_policy' => 'text',
        'geo_rules' => 'json',
        'ppc_budget_total' => 'float',
        'ppc_daily_cap' => 'float',
        'ppc_account_id' => 'text',
        'approval_contact' => 'email',
        'sponsor_assets' => 'json',
    );

    foreach ( $fields as $field => $type ) {
        if ( ! isset( $_POST[ $field ] ) ) {
            continue;
        }
        $raw = wp_unslash( $_POST[ $field ] );
        switch ( $type ) {
            case 'url':
                $value = esc_url_raw( $raw );
                break;
            case 'email':
                $value = sanitize_email( $raw );
                break;
            case 'list':
                $value = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $raw ) ) ) );
                break;
            case 'lines':
                $lines = preg_split( '/\r\n|\r|\n/', $raw );
                $value = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $lines ) ) );
                break;
            case 'json':
                $decoded = json_decode( $raw, true );
                $value = is_array( $decoded ) ? $decoded : array();
                break;
            case 'float':
                $value = (float) $raw;
                break;
            default:
                $value = sanitize_text_field( $raw );
                break;
        }
        update_post_meta( $post_id, $field, $value );
    }
} );

function kh_ad_manager_render_ad_metabox( $post ) {
    wp_nonce_field( 'kh_ad_save_meta', 'kh_ad_meta_nonce' );
    $fields = [
        'ad_type'       => [ 'label' => __( 'Ad Type', 'kh-ad-manager' ), 'type' => 'select', 'options' => [ 'Display' => 'Display', 'Dynamic' => 'Dynamic' ] ],
        'ad_format'     => [ 'label' => __( 'Ad Format', 'kh-ad-manager' ), 'type' => 'select', 'options' => [ 'Image' => 'Image', 'Card' => 'Card', 'Code' => 'Code', 'Network' => 'Network' ] ],
        'campaign_type' => [ 'label' => __( 'Campaign Type', 'kh-ad-manager' ), 'type' => 'select', 'options' => [ 'Date Based' => 'Date Based', 'Content Based' => 'Content Based', 'Hybrid' => 'Hybrid' ] ],
        'campaign_start'=> [ 'label' => __( 'Campaign Start', 'kh-ad-manager' ), 'type' => 'date' ],
        'campaign_end'  => [ 'label' => __( 'Campaign End', 'kh-ad-manager' ), 'type' => 'date' ],
        'target_category'=> [ 'label' => __( 'Target Category (IDs comma separated)', 'kh-ad-manager' ), 'type' => 'text' ],
        'destination_link'=> [ 'label' => __( 'Destination Link', 'kh-ad-manager' ), 'type' => 'url' ],
        'ad_priority'   => [ 'label' => __( 'Ad Priority', 'kh-ad-manager' ), 'type' => 'number' ],
        'ad_code'       => [ 'label' => __( 'Ad Code', 'kh-ad-manager' ), 'type' => 'textarea' ],
        'ad_impressions'=> [ 'label' => __( 'Ad Impressions', 'kh-ad-manager' ), 'type' => 'number', 'readonly' => true ],
        'ad_clicks'     => [ 'label' => __( 'Clicks', 'kh-ad-manager' ), 'type' => 'number', 'readonly' => true ],
    ];
    echo '<table class="form-table khm-ad-table">';
    foreach ( $fields as $key => $config ) {
        $val = kh_ad_get_meta( $post->ID, $key, '' );
        echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $config['label'] ) . '</label></th><td>';
        switch ( $config['type'] ) {
            case 'select':
                echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '">';
                foreach ( $config['options'] as $opt_key => $opt_label ) {
                    echo '<option value="' . esc_attr( $opt_key ) . '" ' . selected( $val, $opt_key, false ) . '>' . esc_html( $opt_label ) . '</option>';
                }
                echo '</select>';
                break;
            case 'date':
            case 'url':
            case 'text':
            case 'number':
                $readonly = ! empty( $config['readonly'] ) ? 'readonly' : '';
                echo '<input type="' . esc_attr( $config['type'] ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text" ' . $readonly . ' />';
                break;
            case 'textarea':
                echo '<textarea name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" class="large-text" rows="4">' . esc_textarea( $val ) . '</textarea>';
                break;
        }
        echo '</td></tr>';
    }

    // Simple media field for ad_image
    $image_id = kh_ad_get_meta( $post->ID, 'ad_image', '' );
    $img_src  = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
    echo '<tr><th><label>' . esc_html__( 'Ad Image', 'kh-ad-manager' ) . '</label></th><td>';
    echo '<div id="khm-ad-image-preview">' . ( $img_src ? '<img src="' . esc_url( $img_src ) . '" style="max-width:150px;height:auto;" />' : '' ) . '</div>';
    echo '<input type="hidden" name="ad_image" id="khm-ad-image" value="' . esc_attr( $image_id ) . '" />';
    echo '<button type="button" class="button" id="khm-ad-image-select">' . esc_html__( 'Select Image', 'kh-ad-manager' ) . '</button> ';
    echo '<button type="button" class="button" id="khm-ad-image-remove">' . esc_html__( 'Remove', 'kh-ad-manager' ) . '</button>';
    echo '</td></tr>';

    echo '</table>';

    // enqueue media for selector
    wp_enqueue_media();
    ?>
    <script>
    (function($){
        $('#khm-ad-image-select').on('click', function(e){
            e.preventDefault();
            const frame = wp.media({title: 'Select Ad Image', multiple: false});
            frame.on('select', function(){
                const attachment = frame.state().get('selection').first().toJSON();
                $('#khm-ad-image').val(attachment.id);
                $('#khm-ad-image-preview').html('<img src="'+attachment.url+'" style="max-width:150px;height:auto;" />');
            });
            frame.open();
        });
        $('#khm-ad-image-remove').on('click', function(e){
            e.preventDefault();
            $('#khm-ad-image').val('');
            $('#khm-ad-image-preview').empty();
        });
    })(jQuery);
    </script>
    <?php
}

function kh_ad_manager_render_card_metabox( $post ) {
    $fields = [
        'headline'             => __( 'Card Headline', 'kh-ad-manager' ),
        'ad_subheadline'       => __( 'Card Subheading', 'kh-ad-manager' ),
        'ad_body'              => __( 'Card Body', 'kh-ad-manager' ),
        'ad_button_text'       => __( 'Button Text', 'kh-ad-manager' ),
        'ad_button_url'        => __( 'Button URL', 'kh-ad-manager' ),
        'ad_badge'             => __( 'Badge', 'kh-ad-manager' ),
        'card_background_color'=> __( 'Background Color', 'kh-ad-manager' ),
        'card_text_color'      => __( 'Text Color', 'kh-ad-manager' ),
    ];
    echo '<table class="form-table khm-ad-table">';
    foreach ( $fields as $key => $label ) {
        $val = kh_ad_get_meta( $post->ID, $key, '' );
        echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
        $type = ( false !== strpos( $key, 'color' ) ) ? 'text' : 'text';
        echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" class="regular-text" value="' . esc_attr( $val ) . '" />';
        echo '</td></tr>';
    }
    echo '</table>';
}

// Save ad meta
add_action( 'save_post_ad_unit', function( $post_id ) {
    if ( ! isset( $_POST['kh_ad_meta_nonce'] ) || ! wp_verify_nonce( $_POST['kh_ad_meta_nonce'], 'kh_ad_save_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = [
        'ad_type', 'ad_format', 'campaign_type', 'campaign_start', 'campaign_end', 'target_category',
        'destination_link', 'ad_priority', 'ad_code', 'headline', 'ad_subheadline', 'ad_body',
        'ad_button_text', 'ad_button_url', 'ad_badge', 'card_background_color', 'card_text_color',
    ];
    foreach ( $fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            $san = is_array( $_POST[ $field ] ) ? '' : wp_kses_post( wp_unslash( $_POST[ $field ] ) );
            kh_ad_update_meta( $post_id, $field, $san );
        }
    }

    // Image
    if ( isset( $_POST['ad_image'] ) ) {
        kh_ad_update_meta( $post_id, 'ad_image', absint( $_POST['ad_image'] ) );
    }

    // Optional image dimension validation (warn if mismatched).
    if ( isset( $_POST['ad_image'] ) && $_POST['ad_image'] ) {
        $img_id = absint( $_POST['ad_image'] );
        $meta   = wp_get_attachment_metadata( $img_id );
        if ( $meta && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
            $slots = kh_ad_get_slot_slugs( $post_id );
            $primary_slot = $slots ? reset( $slots ) : '';
            $rules = kh_get_slot_exact_dimensions();
            if ( $primary_slot && isset( $rules[ $primary_slot ] ) ) {
                $expected = $rules[ $primary_slot ];
                if ( (int) $meta['width'] !== (int) $expected['width'] || (int) $meta['height'] !== (int) $expected['height'] ) {
                    add_filter( 'redirect_post_location', function( $location ) use ( $expected, $meta ) {
                        $msg = sprintf(
                            'Image must be exactly %dx%d pixels; you uploaded %dx%d.',
                            $expected['width'],
                            $expected['height'],
                            (int) $meta['width'],
                            (int) $meta['height']
                        );
                        return add_query_arg( [ 'kh_ad_img_warn' => rawurlencode( $msg ) ], $location );
                    } );
                }
            }
        }
    }
} );

// Display warning notice for image dimension mismatch.
add_action( 'admin_notices', function() {
    if ( isset( $_GET['kh_ad_img_warn'] ) ) {
        $msg = sanitize_text_field( wp_unslash( $_GET['kh_ad_img_warn'] ) );
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
    }
} );

// Slot overrides meta box
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'kh_ad_slot_overrides',
        __( 'Ad Slot Overrides', 'kh-ad-manager' ),
        'kh_ad_manager_render_slot_overrides',
        [ 'post', 'page' ],
        'side',
        'default'
    );
} );

function kh_ad_manager_render_slot_overrides( $post ) {
    wp_nonce_field( 'kh_ad_override_save', 'kh_ad_override_nonce' );
    $slots = [ 'exit_overlay', 'footer', 'header', 'popup', 'sidebar1', 'sidebar2', 'ticker', 'slide_in' ];
    foreach ( $slots as $slot ) {
        $mode   = kh_ad_get_meta( $post->ID, "{$slot}_ad_mode", 'off' );
        $manual = kh_ad_get_meta( $post->ID, "manual_ad_code_{$slot}", '' );
        $auto   = kh_ad_get_meta( $post->ID, "ad_code_{$slot}", '' );
        echo '<p><strong>' . esc_html( ucfirst( str_replace( '_', ' ', $slot ) ) ) . '</strong><br>';
        echo '<select name="' . esc_attr( "{$slot}_ad_mode" ) . '">';
        foreach ( [ 'default' => 'Default', 'manual' => 'Manual', 'off' => 'Off' ] as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $mode, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label>' . esc_html__( 'Manual Code', 'kh-ad-manager' ) . '</label><br><textarea name="' . esc_attr( "manual_ad_code_{$slot}" ) . '" rows="2" class="widefat">' . esc_textarea( $manual ) . '</textarea></p>';
        echo '<p><label>' . esc_html__( 'Auto Code', 'kh-ad-manager' ) . '</label><br><textarea name="' . esc_attr( "ad_code_{$slot}" ) . '" rows="2" class="widefat">' . esc_textarea( $auto ) . '</textarea></p>';
        echo '<hr>';
    }
}

add_action( 'save_post', function( $post_id ) {
    if ( ! isset( $_POST['kh_ad_override_nonce'] ) || ! wp_verify_nonce( $_POST['kh_ad_override_nonce'], 'kh_ad_override_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $slots = [ 'exit_overlay', 'footer', 'header', 'popup', 'sidebar1', 'sidebar2', 'ticker', 'slide_in' ];
    foreach ( $slots as $slot ) {
        if ( isset( $_POST["{$slot}_ad_mode"] ) ) {
            kh_ad_update_meta( $post_id, "{$slot}_ad_mode", sanitize_text_field( wp_unslash( $_POST["{$slot}_ad_mode"] ) ) );
        }
        if ( isset( $_POST["manual_ad_code_{$slot}"] ) ) {
            kh_ad_update_meta( $post_id, "manual_ad_code_{$slot}", wp_kses_post( wp_unslash( $_POST["manual_ad_code_{$slot}"] ) ) );
        }
        if ( isset( $_POST["ad_code_{$slot}"] ) ) {
            kh_ad_update_meta( $post_id, "ad_code_{$slot}", wp_kses_post( wp_unslash( $_POST["ad_code_{$slot}"] ) ) );
        }
    }
} );

// Options page for global ad codes (native).
add_action( 'admin_menu', function() {
    add_options_page(
        __( 'KH Ad Settings', 'kh-ad-manager' ),
        __( 'KH Ad Settings', 'kh-ad-manager' ),
        'manage_options',
        'kh-ad-settings-native',
        'kh_ad_manager_render_options_page'
    );
} );

add_action( 'admin_init', function() {
    register_setting( 'kh_ad_settings', 'ad_code_exit_overlay' );
    register_setting( 'kh_ad_settings', 'ad_code_footer' );
    register_setting( 'kh_ad_settings', 'ad_code_header' );
    register_setting( 'kh_ad_settings', 'ad_code_popup' );
    register_setting( 'kh_ad_settings', 'ad_code_sidebar1' );
    register_setting( 'kh_ad_settings', 'ad_code_sidebar2' );
    register_setting( 'kh_ad_settings', 'ad_code_ticker' );
    register_setting( 'kh_ad_settings', 'ad_code_slide_in' );
} );

function kh_ad_manager_render_options_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'KH Ad Settings', 'kh-ad-manager' ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'kh_ad_settings' ); ?>
            <table class="form-table">
                <?php foreach ( [ 'exit_overlay', 'footer', 'header', 'popup', 'sidebar1', 'sidebar2', 'ticker', 'slide_in' ] as $slot ) : ?>
                    <tr>
                        <th><label for="ad_code_<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $slot ) ) ); ?></label></th>
                        <td><textarea class="large-text" rows="3" id="ad_code_<?php echo esc_attr( $slot ); ?>" name="ad_code_<?php echo esc_attr( $slot ); ?>"><?php echo esc_textarea( get_option( "ad_code_{$slot}", '' ) ); ?></textarea></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
