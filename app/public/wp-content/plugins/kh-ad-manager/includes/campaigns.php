<?php

if (! defined('ABSPATH')) {
    exit;
}

add_action('init', function() {
    register_taxonomy('ad-campaign', 'ad_unit', [
        'labels' => [
            'name'          => __('Ad Campaigns', 'kh-ad-manager'),
            'singular_name' => __('Ad Campaign', 'kh-ad-manager'),
        ],
        'hierarchical' => false,
        'show_ui'      => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite'      => false,
    ]);
});

function kh_ad_manager_campaign_fields($term) {
    $term_id = isset($term->term_id) ? $term->term_id : 0;
    $budget = $term_id ? get_term_meta($term_id, 'kh_campaign_budget', true) : '';
    $start  = $term_id ? get_term_meta($term_id, 'kh_campaign_start', true) : '';
    $end    = $term_id ? get_term_meta($term_id, 'kh_campaign_end', true) : '';
    $status = $term_id ? get_term_meta($term_id, 'kh_campaign_status', true) : 'draft';
    $cpc    = $term_id ? get_term_meta($term_id, 'kh_campaign_cpc', true) : '';
    $spend  = $term_id ? get_term_meta($term_id, 'kh_campaign_spend', true) : 0;
    ?>
    <tr class="form-field">
        <th scope="row"><label for="kh_campaign_budget"><?php esc_html_e('Budget (£)', 'kh-ad-manager'); ?></label></th>
        <td><input type="number" step="0.01" name="kh_campaign_budget" id="kh_campaign_budget" value="<?php echo esc_attr($budget); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_campaign_cpc"><?php esc_html_e('Cost Per Click (£)', 'kh-ad-manager'); ?></label></th>
        <td><input type="number" step="0.01" min="0" name="kh_campaign_cpc" id="kh_campaign_cpc" value="<?php echo esc_attr($cpc); ?>" /></td>
    </tr>
    <?php if ($term_id) : ?>
    <tr class="form-field">
        <th scope="row"><?php esc_html_e('Spend to Date (£)', 'kh-ad-manager'); ?></th>
        <td><strong><?php echo esc_html(number_format((float) $spend, 2)); ?></strong></td>
    </tr>
    <?php endif; ?>
    <tr class="form-field">
        <th scope="row"><label for="kh_campaign_start"><?php esc_html_e('Start Date', 'kh-ad-manager'); ?></label></th>
        <td><input type="date" name="kh_campaign_start" id="kh_campaign_start" value="<?php echo esc_attr($start); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_campaign_end"><?php esc_html_e('End Date', 'kh-ad-manager'); ?></label></th>
        <td><input type="date" name="kh_campaign_end" id="kh_campaign_end" value="<?php echo esc_attr($end); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_campaign_status"><?php esc_html_e('Status', 'kh-ad-manager'); ?></label></th>
        <td>
            <select name="kh_campaign_status" id="kh_campaign_status">
                <option value="draft" <?php selected($status, 'draft'); ?>><?php esc_html_e('Draft', 'kh-ad-manager'); ?></option>
                <option value="scheduled" <?php selected($status, 'scheduled'); ?>><?php esc_html_e('Scheduled', 'kh-ad-manager'); ?></option>
                <option value="live" <?php selected($status, 'live'); ?>><?php esc_html_e('Live', 'kh-ad-manager'); ?></option>
                <option value="paused" <?php selected($status, 'paused'); ?>><?php esc_html_e('Paused', 'kh-ad-manager'); ?></option>
            </select>
        </td>
    </tr>
    <?php
}

add_action('ad-campaign_add_form_fields', function() {
    kh_ad_manager_campaign_fields((object) []);
});

add_action('ad-campaign_edit_form_fields', function($term) {
    kh_ad_manager_campaign_fields($term);
});

function kh_ad_manager_save_campaign_meta($term_id) {
    $fields = [
        'budget' => 'kh_campaign_budget',
        'start'  => 'kh_campaign_start',
        'end'    => 'kh_campaign_end',
        'status' => 'kh_campaign_status',
        'cpc'    => 'kh_campaign_cpc',
    ];

    foreach ($fields as $meta_key => $field_name) {
        if (isset($_POST[$field_name])) {
            $value = sanitize_text_field(wp_unslash($_POST[$field_name]));
            if (in_array($meta_key, ['budget', 'cpc'], true)) {
                $value = (float) $value;
            }
            update_term_meta($term_id, 'kh_campaign_' . $meta_key, $value);
        }
    }
}

add_action('created_ad-campaign', 'kh_ad_manager_save_campaign_meta');
add_action('edited_ad-campaign', 'kh_ad_manager_save_campaign_meta');

function kh_ad_manager_get_campaign_id_for_ad($ad_id) {
    $terms = wp_get_post_terms($ad_id, 'ad-campaign', ['fields' => 'ids']);
    if (is_wp_error($terms) || empty($terms)) {
        return 0;
    }
    return (int) $terms[0];
}


function kh_ad_manager_get_campaign_meta($campaign_id) {
    if (! $campaign_id) {
        return [];
    }

    $meta = [];
    $keys = ['budget', 'start', 'end', 'status', 'spend', 'cpc'];
    foreach ($keys as $key) {
        $meta[$key] = get_term_meta($campaign_id, 'kh_campaign_' . $key, true);
    }
    $meta['budget'] = isset($meta['budget']) ? (float) $meta['budget'] : 0;
    $meta['cpc']    = isset($meta['cpc']) && $meta['cpc'] !== '' ? (float) $meta['cpc'] : 0.5;
    $meta['spend']  = isset($meta['spend']) ? (float) $meta['spend'] : 0;
    $meta['status'] = $meta['status'] ?: 'draft';

    return $meta;
}


function kh_ad_manager_is_campaign_active($campaign_id) {
    if (! $campaign_id) {
        return true;
    }

    $meta = kh_ad_manager_get_campaign_meta($campaign_id);

    if (in_array($meta['status'], ['paused', 'completed'], true)) {
        return false;
    }

    $today = current_time('Y-m-d');
    if (! empty($meta['start']) && $today < $meta['start']) {
        return false;
    }
    if (! empty($meta['end']) && $today > $meta['end']) {
        return false;
    }

    if ($meta['budget'] > 0 && $meta['spend'] >= $meta['budget']) {
        return false;
    }

    return true;
}


function kh_ad_manager_increment_campaign_spend($campaign_id, $amount) {
    if (! $campaign_id || $amount <= 0) {
        return;
    }

    $meta = kh_ad_manager_get_campaign_meta($campaign_id);
    $new_spend = $meta['spend'] + $amount;
    update_term_meta($campaign_id, 'kh_campaign_spend', $new_spend);

    if ($meta['budget'] > 0 && $new_spend >= $meta['budget']) {
        update_term_meta($campaign_id, 'kh_campaign_status', 'paused');
    }
}
