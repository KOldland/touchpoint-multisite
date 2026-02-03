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
    $sponsor_id = $term_id ? get_term_meta($term_id, 'kh_sponsor_id', true) : '';
    $sponsor_post_id = $term_id ? get_term_meta($term_id, 'kh_sponsor_post_id', true) : '';
    $linkedin_page_url = $term_id ? get_term_meta($term_id, 'kh_sponsor_linkedin_page_url', true) : '';
    $linkedin_handles = $term_id ? get_term_meta($term_id, 'kh_sponsor_linkedin_handles', true) : array();
    $quotable_reps = $term_id ? get_term_meta($term_id, 'kh_sponsor_quotable_representatives', true) : array();
    $content_library_url = $term_id ? get_term_meta($term_id, 'kh_sponsor_content_library_url', true) : '';
    $sponsor_assets = $term_id ? get_term_meta($term_id, 'kh_sponsor_assets', true) : array();
    $allowed_claims = $term_id ? get_term_meta($term_id, 'kh_sponsor_allowed_claims', true) : array();
    $co_brand_policy = $term_id ? get_term_meta($term_id, 'kh_sponsor_co_brand_policy', true) : 'co-brand';
    $geo_rules = $term_id ? get_term_meta($term_id, 'kh_sponsor_geo_rules', true) : array();
    $ppc_budget_total = $term_id ? get_term_meta($term_id, 'kh_sponsor_ppc_budget_total', true) : '';
    $ppc_daily_cap = $term_id ? get_term_meta($term_id, 'kh_sponsor_ppc_daily_cap', true) : '';
    $ppc_account_id = $term_id ? get_term_meta($term_id, 'kh_sponsor_ppc_account_id', true) : '';
    $approval_contact = $term_id ? get_term_meta($term_id, 'kh_sponsor_approval_contact', true) : '';
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
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_id"><?php esc_html_e('Sponsor ID', 'kh-ad-manager'); ?></label></th>
        <td><input type="number" name="kh_sponsor_id" id="kh_sponsor_id" value="<?php echo esc_attr($sponsor_id ?: $term_id); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_post_id"><?php esc_html_e('Sponsor Record ID', 'kh-ad-manager'); ?></label></th>
        <td><input type="number" name="kh_sponsor_post_id" id="kh_sponsor_post_id" value="<?php echo esc_attr($sponsor_post_id); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_linkedin_page_url"><?php esc_html_e('LinkedIn Page URL', 'kh-ad-manager'); ?></label></th>
        <td><input type="url" name="kh_sponsor_linkedin_page_url" id="kh_sponsor_linkedin_page_url" value="<?php echo esc_url($linkedin_page_url); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_linkedin_handles"><?php esc_html_e('LinkedIn Handles', 'kh-ad-manager'); ?></label></th>
        <td><input type="text" name="kh_sponsor_linkedin_handles" id="kh_sponsor_linkedin_handles" value="<?php echo esc_attr(is_array($linkedin_handles) ? implode(', ', $linkedin_handles) : $linkedin_handles); ?>" placeholder="handle1, handle2" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_quotable_representatives"><?php esc_html_e('Quotable Representatives (JSON)', 'kh-ad-manager'); ?></label></th>
        <td><textarea name="kh_sponsor_quotable_representatives" id="kh_sponsor_quotable_representatives" rows="4"><?php echo esc_textarea(wp_json_encode($quotable_reps)); ?></textarea></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_content_library_url"><?php esc_html_e('Content Library URL', 'kh-ad-manager'); ?></label></th>
        <td><input type="url" name="kh_sponsor_content_library_url" id="kh_sponsor_content_library_url" value="<?php echo esc_url($content_library_url); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_assets"><?php esc_html_e('Sponsor Assets (JSON)', 'kh-ad-manager'); ?></label></th>
        <td><textarea name="kh_sponsor_assets" id="kh_sponsor_assets" rows="4"><?php echo esc_textarea(wp_json_encode($sponsor_assets)); ?></textarea></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_allowed_claims"><?php esc_html_e('Allowed Claims', 'kh-ad-manager'); ?></label></th>
        <td><textarea name="kh_sponsor_allowed_claims" id="kh_sponsor_allowed_claims" rows="4" placeholder="One claim per line"><?php echo esc_textarea(is_array($allowed_claims) ? implode("\n", $allowed_claims) : $allowed_claims); ?></textarea></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_co_brand_policy"><?php esc_html_e('Co-brand Policy', 'kh-ad-manager'); ?></label></th>
        <td>
            <select name="kh_sponsor_co_brand_policy" id="kh_sponsor_co_brand_policy">
                <option value="co-brand" <?php selected($co_brand_policy, 'co-brand'); ?>><?php esc_html_e('Co-brand', 'kh-ad-manager'); ?></option>
                <option value="sponsor-author" <?php selected($co_brand_policy, 'sponsor-author'); ?>><?php esc_html_e('Sponsor-author', 'kh-ad-manager'); ?></option>
                <option value="replace-creative" <?php selected($co_brand_policy, 'replace-creative'); ?>><?php esc_html_e('Replace creative', 'kh-ad-manager'); ?></option>
            </select>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_geo_rules"><?php esc_html_e('GEO Rules (JSON)', 'kh-ad-manager'); ?></label></th>
        <td><textarea name="kh_sponsor_geo_rules" id="kh_sponsor_geo_rules" rows="4"><?php echo esc_textarea(wp_json_encode($geo_rules)); ?></textarea></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_ppc_budget_total"><?php esc_html_e('PPC Budget Total', 'kh-ad-manager'); ?></label></th>
        <td><input type="number" step="0.01" name="kh_sponsor_ppc_budget_total" id="kh_sponsor_ppc_budget_total" value="<?php echo esc_attr($ppc_budget_total); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_ppc_daily_cap"><?php esc_html_e('PPC Daily Cap', 'kh-ad-manager'); ?></label></th>
        <td><input type="number" step="0.01" name="kh_sponsor_ppc_daily_cap" id="kh_sponsor_ppc_daily_cap" value="<?php echo esc_attr($ppc_daily_cap); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_ppc_account_id"><?php esc_html_e('PPC Account ID', 'kh-ad-manager'); ?></label></th>
        <td><input type="text" name="kh_sponsor_ppc_account_id" id="kh_sponsor_ppc_account_id" value="<?php echo esc_attr($ppc_account_id); ?>" /></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="kh_sponsor_approval_contact"><?php esc_html_e('Approval Contact', 'kh-ad-manager'); ?></label></th>
        <td><input type="email" name="kh_sponsor_approval_contact" id="kh_sponsor_approval_contact" value="<?php echo esc_attr($approval_contact); ?>" /></td>
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

    $sponsor_fields = [
        'kh_sponsor_id' => 'int',
        'kh_sponsor_post_id' => 'int',
        'kh_sponsor_linkedin_page_url' => 'url',
        'kh_sponsor_linkedin_handles' => 'list',
        'kh_sponsor_quotable_representatives' => 'json',
        'kh_sponsor_content_library_url' => 'url',
        'kh_sponsor_assets' => 'json',
        'kh_sponsor_allowed_claims' => 'lines',
        'kh_sponsor_co_brand_policy' => 'text',
        'kh_sponsor_geo_rules' => 'json',
        'kh_sponsor_ppc_budget_total' => 'float',
        'kh_sponsor_ppc_daily_cap' => 'float',
        'kh_sponsor_ppc_account_id' => 'text',
        'kh_sponsor_approval_contact' => 'email',
    ];

    foreach ( $sponsor_fields as $field => $type ) {
        if ( ! isset( $_POST[ $field ] ) ) {
            continue;
        }

        $raw = wp_unslash( $_POST[ $field ] );
        switch ( $type ) {
            case 'int':
                $value = (int) $raw;
                break;
            case 'float':
                $value = (float) $raw;
                break;
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
            default:
                $value = sanitize_text_field( $raw );
                break;
        }

        update_term_meta( $term_id, $field, $value );
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

function kh_ad_manager_get_sponsor_meta( $campaign_id ) {
    if ( ! $campaign_id ) {
        return array();
    }

    $post = get_post( $campaign_id );
    if ( $post && 'kh_sponsor' === $post->post_type ) {
        return array(
            'sponsor_id' => $post->ID,
            'name' => $post->post_title,
            'linkedin_page_url' => get_post_meta( $post->ID, 'linkedin_page_url', true ),
            'linkedin_handles' => get_post_meta( $post->ID, 'linkedin_handles', true ),
            'quotable_representatives' => get_post_meta( $post->ID, 'quotable_representatives', true ),
            'content_library_url' => get_post_meta( $post->ID, 'content_library_url', true ),
            'sponsor_assets' => get_post_meta( $post->ID, 'sponsor_assets', true ),
            'allowed_claims' => get_post_meta( $post->ID, 'allowed_claims', true ),
            'co_brand_policy' => get_post_meta( $post->ID, 'co_brand_policy', true ),
            'geo_rules' => get_post_meta( $post->ID, 'geo_rules', true ),
            'ppc_budget_total' => (float) get_post_meta( $post->ID, 'ppc_budget_total', true ),
            'ppc_daily_cap' => (float) get_post_meta( $post->ID, 'ppc_daily_cap', true ),
            'ppc_account_id' => get_post_meta( $post->ID, 'ppc_account_id', true ),
            'approval_contact' => get_post_meta( $post->ID, 'approval_contact', true ),
        );
    }

    $term = get_term( $campaign_id, 'ad-campaign' );
    if ( ! $term || is_wp_error( $term ) ) {
        return array();
    }

    $sponsor_post_id = (int) get_term_meta( $campaign_id, 'kh_sponsor_post_id', true );
    if ( $sponsor_post_id ) {
        $sponsor = get_post( $sponsor_post_id );
        if ( $sponsor && 'kh_sponsor' === $sponsor->post_type ) {
            return kh_ad_manager_get_sponsor_meta( $sponsor_post_id );
        }
    }

    return array(
        'sponsor_id' => (int) get_term_meta( $campaign_id, 'kh_sponsor_id', true ),
        'name' => $term->name,
        'linkedin_page_url' => get_term_meta( $campaign_id, 'kh_sponsor_linkedin_page_url', true ),
        'linkedin_handles' => get_term_meta( $campaign_id, 'kh_sponsor_linkedin_handles', true ),
        'quotable_representatives' => get_term_meta( $campaign_id, 'kh_sponsor_quotable_representatives', true ),
        'content_library_url' => get_term_meta( $campaign_id, 'kh_sponsor_content_library_url', true ),
        'sponsor_assets' => get_term_meta( $campaign_id, 'kh_sponsor_assets', true ),
        'allowed_claims' => get_term_meta( $campaign_id, 'kh_sponsor_allowed_claims', true ),
        'co_brand_policy' => get_term_meta( $campaign_id, 'kh_sponsor_co_brand_policy', true ),
        'geo_rules' => get_term_meta( $campaign_id, 'kh_sponsor_geo_rules', true ),
        'ppc_budget_total' => (float) get_term_meta( $campaign_id, 'kh_sponsor_ppc_budget_total', true ),
        'ppc_daily_cap' => (float) get_term_meta( $campaign_id, 'kh_sponsor_ppc_daily_cap', true ),
        'ppc_account_id' => get_term_meta( $campaign_id, 'kh_sponsor_ppc_account_id', true ),
        'approval_contact' => get_term_meta( $campaign_id, 'kh_sponsor_approval_contact', true ),
    );
}

add_action( 'admin_init', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( get_option( 'kh_sponsor_migration_done' ) ) {
        return;
    }

    $terms = get_terms( array(
        'taxonomy' => 'ad-campaign',
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) ) {
        return;
    }

    foreach ( $terms as $term ) {
        $existing = (int) get_term_meta( $term->term_id, 'kh_sponsor_post_id', true );
        if ( $existing ) {
            continue;
        }

        $sponsor_post_id = wp_insert_post( array(
            'post_type' => 'kh_sponsor',
            'post_title' => $term->name,
            'post_status' => 'publish',
        ), true );

        if ( is_wp_error( $sponsor_post_id ) ) {
            continue;
        }

        update_post_meta( $sponsor_post_id, 'linkedin_page_url', get_term_meta( $term->term_id, 'kh_sponsor_linkedin_page_url', true ) );
        update_post_meta( $sponsor_post_id, 'linkedin_handles', get_term_meta( $term->term_id, 'kh_sponsor_linkedin_handles', true ) );
        update_post_meta( $sponsor_post_id, 'quotable_representatives', get_term_meta( $term->term_id, 'kh_sponsor_quotable_representatives', true ) );
        update_post_meta( $sponsor_post_id, 'content_library_url', get_term_meta( $term->term_id, 'kh_sponsor_content_library_url', true ) );
        update_post_meta( $sponsor_post_id, 'allowed_claims', get_term_meta( $term->term_id, 'kh_sponsor_allowed_claims', true ) );
        update_post_meta( $sponsor_post_id, 'co_brand_policy', get_term_meta( $term->term_id, 'kh_sponsor_co_brand_policy', true ) );
        update_post_meta( $sponsor_post_id, 'geo_rules', get_term_meta( $term->term_id, 'kh_sponsor_geo_rules', true ) );
        update_post_meta( $sponsor_post_id, 'ppc_budget_total', get_term_meta( $term->term_id, 'kh_sponsor_ppc_budget_total', true ) );
        update_post_meta( $sponsor_post_id, 'ppc_daily_cap', get_term_meta( $term->term_id, 'kh_sponsor_ppc_daily_cap', true ) );
        update_post_meta( $sponsor_post_id, 'ppc_account_id', get_term_meta( $term->term_id, 'kh_sponsor_ppc_account_id', true ) );
        update_post_meta( $sponsor_post_id, 'approval_contact', get_term_meta( $term->term_id, 'kh_sponsor_approval_contact', true ) );
        update_post_meta( $sponsor_post_id, 'sponsor_assets', get_term_meta( $term->term_id, 'kh_sponsor_assets', true ) );

        update_term_meta( $term->term_id, 'kh_sponsor_post_id', $sponsor_post_id );
        update_term_meta( $term->term_id, 'kh_sponsor_id', $sponsor_post_id );
    }

    update_option( 'kh_sponsor_migration_done', 1 );
} );


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
