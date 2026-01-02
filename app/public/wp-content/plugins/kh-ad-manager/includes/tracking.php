<?php

if (! defined('ABSPATH')) {
    exit;
}

function kh_ad_manager_track_impression($ad_id, $slot = '') {
    $ad_id = absint($ad_id);
    if (! $ad_id) {
        return;
    }

    $current = (int) get_post_meta($ad_id, 'ad_impressions', true);
    update_post_meta($ad_id, 'ad_impressions', $current + 1);
    kh_ad_manager_log_event($ad_id, 'impression', $slot);
}


function kh_ad_manager_track_click($ad_id, $slot = '') {
    $ad_id = absint($ad_id);
    if (! $ad_id) {
        return;
    }

    $current = (int) get_post_meta($ad_id, 'ad_clicks', true);
    update_post_meta($ad_id, 'ad_clicks', $current + 1);
    kh_ad_manager_log_event($ad_id, 'click', $slot);
}


function kh_ad_manager_handle_click() {
    check_ajax_referer('kh_ad_click', 'nonce');

    $ad_id = isset($_POST['ad_id']) ? absint($_POST['ad_id']) : 0;
    if (! $ad_id) {
        wp_send_json_error('missing_ad');
    }

    $slot = sanitize_text_field($_POST['slot'] ?? '');
    kh_ad_manager_track_click($ad_id, $slot);
    wp_send_json_success();
}

add_action('wp_ajax_kh_ad_click', 'kh_ad_manager_handle_click');
add_action('wp_ajax_nopriv_kh_ad_click', 'kh_ad_manager_handle_click');


function kh_ad_manager_log_event($ad_id, $event_type, $slot = '') {
    global $wpdb;

    $table = $wpdb->prefix . 'kh_ad_events';
    $campaign_id = kh_ad_manager_get_campaign_id_for_ad($ad_id);
    $user_id = get_current_user_id();
    $membership_type = 'anon';
    if ($user_id) {
        $membership_type = get_user_meta($user_id, 'membership_type', true);
        if (! $membership_type) {
            $membership_type = 'member';
        }
    }
    $referer = wp_get_referer();
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(wp_unslash($_SERVER['HTTP_USER_AGENT']), 0, 255) : '';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ip_hash = $ip ? hash('sha256', $ip) : '';

    $throttle_key = 'kh_ad_evt_' . md5($event_type . '|' . $ad_id . '|' . $ip_hash . '|' . $slot);
    if (false !== get_transient($throttle_key)) {
        return;
    }
    set_transient($throttle_key, 1, MINUTE_IN_SECONDS);

    $wpdb->insert(
        $table,
        [
            'ad_id'       => $ad_id,
            'event_type'  => $event_type,
            'slot'        => $slot,
            'campaign_id' => $campaign_id,
            'user_id'     => $user_id,
            'membership_type' => substr($membership_type, 0, 50),
            'referer'     => $referer,
            'user_agent'  => $user_agent,
            'ip_hash'     => $ip_hash,
            'created_at'  => current_time('mysql'),
        ],
        [
            '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s'
        ]
    );

    if ('click' === $event_type && $campaign_id) {
        $meta = kh_ad_manager_get_campaign_meta($campaign_id);
        kh_ad_manager_increment_campaign_spend($campaign_id, $meta['cpc']);
    }
}
