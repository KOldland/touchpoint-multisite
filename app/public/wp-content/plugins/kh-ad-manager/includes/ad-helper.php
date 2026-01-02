<?php

/**
 * Get ads for a slot with optional category targeting.
 */
function kh_get_ads_for_slot($slot_slug, $category_id = null, $limit = 1) {
    $args = [
        'post_type'      => 'ad_unit',
        'posts_per_page' => max(1, (int) $limit),
        'orderby'        => 'meta_value_num',
        'meta_key'       => 'ad_priority',
        'order'          => 'DESC',
        'tax_query'      => [
            [
                'taxonomy' => 'ad-slot',
                'field'    => 'slug',
                'terms'    => $slot_slug,
            ]
        ],
    ];

    if ($category_id) {
        $args['tax_query'][] = [
            'taxonomy' => 'category',
            'field'    => 'term_id',
            'terms'    => $category_id,
        ];
    }

    $ads = get_posts($args);
    $ads = array_values(array_filter($ads, function($ad) {
        $campaign_id = kh_ad_manager_get_campaign_id_for_ad($ad->ID);
        return kh_ad_manager_is_campaign_active($campaign_id);
    }));

    return $ads;
}


function kh_get_ad_for_slot($slot_slug, $category_id = null) {
    $ads = kh_get_ads_for_slot($slot_slug, $category_id, 1);
    return $ads ? $ads[0] : null;
}


/**
 * Render the best ad for a given slot without duplicating lookup logic.
 *
 * @param string $slot_slug
 * @param array  $args Optional arguments (currently supports 'category_id').
 */
function kh_render_ad_slot($slot_slug, array $args = []) {
    $slot_slug = sanitize_title($slot_slug);
    if ('' === $slot_slug) {
        return;
    }

    $category_id   = isset($args['category_id']) ? absint($args['category_id']) : null;
    $rotation_pool = isset($args['rotation_pool']) ? max(1, (int) $args['rotation_pool']) : 5;
    $ads           = kh_get_ads_for_slot($slot_slug, $category_id, $rotation_pool);

    if (empty($ads)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<!-- KH Ad Manager: no ad found for slot ' . esc_html($slot_slug) . ' -->';
        }
        return;
    }

    $ad = kh_ad_manager_select_rotating_ad($slot_slug, $ads, $args);

    if (! $ad) {
        $ad = reset($ads);
    }

    kh_ad_manager_mark_ad_seen($slot_slug, $ad->ID);
    kh_render_ad($ad->ID);
}


/**
 * Helper to fetch slot slugs for an ad.
 */
function kh_ad_manager_get_slot_slugs($ad_id) {
    return kh_ad_get_slot_slugs( $ad_id );
}


function kh_ad_manager_select_rotating_ad($slot_slug, array $ads, array $args = []) {
    $seen = kh_ad_manager_get_seen_ads();
    $slot_seen = isset($seen[$slot_slug]) ? (array) $seen[$slot_slug] : [];

    foreach ($ads as $ad) {
        if (! in_array($ad->ID, $slot_seen, true)) {
            return $ad;
        }
    }

    // If all seen recently, return first to keep delivery predictable.
    return reset($ads);
}


function kh_ad_manager_get_seen_ads() {
    if (empty($_COOKIE['kh_ad_seen'])) {
        return [];
    }

    $decoded = json_decode(stripslashes($_COOKIE['kh_ad_seen']), true);
    return is_array($decoded) ? $decoded : [];
}


function kh_ad_manager_mark_ad_seen($slot_slug, $ad_id) {
    $seen = kh_ad_manager_get_seen_ads();
    $slot = isset($seen[$slot_slug]) ? (array) $seen[$slot_slug] : [];
    array_unshift($slot, (int) $ad_id);
    $slot = array_values(array_unique($slot));
    $seen[$slot_slug] = array_slice($slot, 0, 5);

    if (! headers_sent()) {
        setcookie(
            'kh_ad_seen',
            wp_json_encode($seen),
            time() + HOUR_IN_SECONDS,
            COOKIEPATH ?: '/',
            COOKIE_DOMAIN ?: '',
            is_ssl(),
            true
        );
    }
}


/**
 * Slot-specific dimension rules.
 * Use these to bully users into uploading properly-sized ads.
 *
 * @return array
 */
function kh_get_slot_exact_dimensions() {
    return [
        'header'     => ['width' => 1600, 'height' => 500],
        'footer'     => ['width' => 1600, 'height' => 500],
        'sidebar1'   => ['width' => 300,  'height' => 600],
        'sidebar2'   => ['width' => 300,  'height' => 600],
        'pop-up'      => ['width' => 700,  'height' => 700],
        'slide-in'   => ['width' => 300,  'height' => 250],
    ];
}
