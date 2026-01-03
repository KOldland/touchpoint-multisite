<?php

function kh_render_ad($ad_id) {
    $ad_id = absint($ad_id);
    if (! $ad_id) {
        return;
    }

    $slots       = kh_ad_get_slot_slugs($ad_id);
    $primary_slot = $slots ? reset($slots) : '';

    kh_ad_manager_track_impression($ad_id, $primary_slot);

    if (kh_ad_manager_render_overlay_card($ad_id, $slots)) {
        return;
    }

    // --- ACF FIELD MAP ---
    $ad_type      = kh_ad_get_meta($ad_id, 'ad_type');
    $ad_format    = kh_ad_get_meta($ad_id, 'ad_format');
    $img          = kh_ad_get_meta($ad_id, 'ad_image');
    if ( $img && is_numeric( $img ) ) {
        $img = [
            'url'    => wp_get_attachment_url( $img ),
            'width'  => (int) get_post_meta( $img, '_wp_attachment_metadata', true )['width'] ?? 0,
            'height' => (int) get_post_meta( $img, '_wp_attachment_metadata', true )['height'] ?? 0,
        ];
    }
    $ad_code      = kh_ad_get_meta($ad_id, 'ad_code');

    // Card fields
    $headline     = kh_ad_get_meta($ad_id, 'headline');
    $subheadline  = kh_ad_get_meta($ad_id, 'ad_subheadline');
    $body         = kh_ad_get_meta($ad_id, 'ad_body');
    $btn_text     = kh_ad_get_meta($ad_id, 'ad_button_text');
    $btn_url      = kh_ad_get_meta($ad_id, 'ad_button_url');
    $badge        = kh_ad_get_meta($ad_id, 'ad_badge');

    // --- IMAGE DIMENSION WARNING (Display slots only) ---
    $warn_msg = '';
    if ($ad_format === 'Image' && $img && isset($img['width'], $img['height']) && in_array($primary_slot, ['header', 'sidebar1', 'sidebar2', 'footer'], true)) {
        if ($primary_slot === 'header' && $img['width'] <= $img['height']) {
            $warn_msg = 'Header ads should be landscape (wider than tall). Wanna check that?';
        }
        if (in_array($primary_slot, ['sidebar1', 'sidebar2'], true) && $img['width'] >= $img['height']) {
            $warn_msg = 'Sidebar ads should be portrait (taller than wide). Wanna check that?';
        }
    if ($primary_slot === 'footer' && $img['width'] <= $img['height']) {
            $warn_msg = 'Footer ads should be landscape. Try again, Monet.';
        }
    }

    echo '<div class="kh-ad-unit-wrapper" data-kh-ad-id="' . esc_attr($ad_id) . '" data-kh-ad-slot="' . esc_attr($primary_slot) . '">';

    if ($warn_msg) {
        echo '<div class="kh-ad-warning" style="background:#ffd600;color:#900;font-weight:bold;padding:10px 12px;margin:8px 0 12px 0;border-radius:5px;border:2px solid #900;">⚠️ ' . esc_html($warn_msg) . '</div>';
    }

    switch ($ad_format) {
        case 'Image':
            echo '<div class="ad-unit ' . esc_attr($primary_slot) . '">';
            if ($img) {
                echo '<img src="' . esc_url($img['url']) . '" alt="" style="max-width:100%;height:auto;" />';
            }
            echo '</div>';
            break;

        case 'Card':
            echo '<div class="ad-unit ' . esc_attr($primary_slot) . ' ad-card">';
            if ($badge) {
                echo '<span class="ad-badge">' . esc_html($badge) . '</span>';
            }
            if ($img) {
                echo '<img src="' . esc_url($img['url']) . '" alt="" />';
            }
            if ($headline) {
                echo '<div class="ad-headline">' . esc_html($headline) . '</div>';
            }
            if ($subheadline) {
                echo '<div class="ad-subheadline">' . esc_html($subheadline) . '</div>';
            }
            if ($body) {
                echo '<div class="ad-body">' . esc_html($body) . '</div>';
            }
            if ($btn_url && $btn_text) {
                echo '<a href="' . esc_url($btn_url) . '" class="ad-btn" data-kh-ad-click="' . esc_attr($ad_id) . '">' . esc_html($btn_text) . '</a>';
            }
            echo '</div>';
            break;

        case 'Code':
        case 'Network':
            echo '<div class="ad-unit ' . esc_attr($primary_slot) . ' ad-code">';
            echo $ad_code ? wp_kses_post($ad_code) : '<em>No code set for this ad.</em>';
            echo '</div>';
            break;

        default:
            echo '<div class="ad-unit ad-error" style="color:red;font-weight:bold;">';
            echo esc_html__('Unknown or unsupported ad format.', 'kh-ad-manager');
            echo '</div>';
            break;
    }

    echo '</div>';
}


function kh_ad_manager_render_overlay_card($ad_id, array $slots) {
    if (! array_intersect(['slide-in', 'pop-up'], $slots)) {
        return false;
    }

    $context = [
        'headline'     => kh_ad_get_meta( $ad_id, 'card_headline' ),
        'subheading'   => kh_ad_get_meta( $ad_id, 'card_subheading' ),
        'body'         => kh_ad_get_meta( $ad_id, 'card_body' ),
        'button_text'  => kh_ad_get_meta( $ad_id, 'card_button_text' ),
        'button_url'   => kh_ad_get_meta( $ad_id, 'card_button_url' ),
        'bg_color'     => kh_ad_get_meta( $ad_id, 'card_background_color' ),
        'text_color'   => kh_ad_get_meta( $ad_id, 'card_text_color' ),
        'slot'         => $slots ? reset($slots) : 'slide-in',
        'ad_id'        => $ad_id,
    ];

    echo '<div class="kh-ad-unit-wrapper" data-kh-ad-id="' . esc_attr($ad_id) . '" data-kh-ad-slot="' . esc_attr($context['slot']) . '">';
    include AM_PATH . 'partials/card-ad.php';
    echo '</div>';

    return true;
}


// --- ELEMENTOR + ACF SLOT RENDERER ---
function kh_ad_manager_render_ad_for_slot_in_context($slot, $post_id = null) {
    if ( defined( 'KHM_DISABLE_ADS' ) && KHM_DISABLE_ADS ) {
        echo '<!-- KH Ad: disabled -->';
        return;
    }

    if (! $slot) {
        return;
    }

    $post_id = $post_id ?: get_the_ID();
    $slot_key = sanitize_title($slot);

    $mode = kh_ad_get_meta( $post_id, "{$slot_key}_ad_mode", 'default' );
    $field_key = $mode === 'manual'
        ? "manual_ad_code_{$slot_key}"
        : "ad_code_{$slot_key}";

    if ($mode === 'off') {
        echo '<!-- KH Ad: ' . esc_html($slot_key) . ' is turned off -->';
        return;
    }

    $ad_code = kh_ad_get_option($field_key);

    echo '<div class="kh-ad-slot" data-slot="' . esc_attr($slot_key) . '">';
    echo $ad_code
        ? wp_kses_post($ad_code)
        : '<!-- KH Ad: no ad for slot ' . esc_html($slot_key) . ' (' . esc_html($mode) . ') -->';
    echo '</div>';
}
    
