<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sponsor Admin UI & Management Screens
 * 
 * Adds metaboxes and admin screens for sponsor profile management,
 * asset uploads, allowed claims, policies, and geo rules.
 */

add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'kh_sponsor_profile',
        __( 'Sponsor Profile', 'kh-ad-manager' ),
        'kh_ad_manager_render_sponsor_profile_metabox',
        'kh_sponsor',
        'normal',
        'high'
    );

    add_meta_box(
        'kh_sponsor_policies',
        __( 'Brand Policies & Claims', 'kh-ad-manager' ),
        'kh_ad_manager_render_sponsor_policies_metabox',
        'kh_sponsor',
        'normal',
        'high'
    );

    add_meta_box(
        'kh_sponsor_assets',
        __( 'Asset Library', 'kh-ad-manager' ),
        'kh_ad_manager_render_sponsor_assets_metabox',
        'kh_sponsor',
        'normal',
        'high'
    );

    add_meta_box(
        'kh_sponsor_budget',
        __( 'Budget & Account Info', 'kh-ad-manager' ),
        'kh_ad_manager_render_sponsor_budget_metabox',
        'kh_sponsor',
        'side',
        'default'
    );

    add_meta_box(
        'kh_sponsor_approval',
        __( 'Approval Contact', 'kh-ad-manager' ),
        'kh_ad_manager_render_sponsor_approval_metabox',
        'kh_sponsor',
        'side',
        'default'
    );

    add_meta_box(
        'kh_sponsor_geo',
        __( 'Geo-Specific Rules', 'kh-ad-manager' ),
        'kh_ad_manager_render_sponsor_geo_metabox',
        'kh_sponsor',
        'normal',
        'default'
    );
} );

/**
 * Render: Sponsor Profile metabox
 */
function kh_ad_manager_render_sponsor_profile_metabox( $post ) {
    $linkedin_page_url = get_post_meta( $post->ID, 'linkedin_page_url', true );
    $linkedin_handles  = get_post_meta( $post->ID, 'linkedin_handles', true );
    $content_library   = get_post_meta( $post->ID, 'content_library_url', true );
    $reps              = get_post_meta( $post->ID, 'quotable_representatives', true );

    if ( ! is_array( $linkedin_handles ) ) {
        $linkedin_handles = $linkedin_handles ? array( $linkedin_handles ) : array();
    }
    if ( ! is_array( $reps ) ) {
        $reps = array();
    }

    wp_nonce_field( 'kh_sponsor_profile_nonce', 'kh_sponsor_profile_nonce' );
    ?>
    <div class="kh-sponsor-metabox">
        <div class="kh-form-group">
            <label for="linkedin_page_url">
                <strong><?php esc_html_e( 'LinkedIn Company Page URL', 'kh-ad-manager' ); ?></strong>
            </label>
            <input 
                type="url" 
                id="linkedin_page_url" 
                name="linkedin_page_url" 
                value="<?php echo esc_attr( $linkedin_page_url ); ?>"
                class="widefat"
                placeholder="https://www.linkedin.com/company/..."
            />
            <small><?php esc_html_e( 'The official LinkedIn company page for this sponsor.', 'kh-ad-manager' ); ?></small>
        </div>

        <div class="kh-form-group">
            <label for="linkedin_handles">
                <strong><?php esc_html_e( 'LinkedIn Company Handles (CSV)', 'kh-ad-manager' ); ?></strong>
            </label>
            <input 
                type="text" 
                id="linkedin_handles" 
                name="linkedin_handles" 
                value="<?php echo esc_attr( implode( ', ', $linkedin_handles ) ); ?>"
                class="widefat"
                placeholder="acme, acme-corp, acme-solutions"
            />
            <small><?php esc_html_e( 'Comma-separated handles for mention matching.', 'kh-ad-manager' ); ?></small>
        </div>

        <div class="kh-form-group">
            <label for="content_library_url">
                <strong><?php esc_html_e( 'Content Library URL', 'kh-ad-manager' ); ?></strong>
            </label>
            <input 
                type="url" 
                id="content_library_url" 
                name="content_library_url" 
                value="<?php echo esc_attr( $content_library ); ?>"
                class="widefat"
                placeholder="https://sponsor-assets.example.com"
            />
            <small><?php esc_html_e( 'Link to sponsor\'s content asset repository.', 'kh-ad-manager' ); ?></small>
        </div>

        <div class="kh-form-group">
            <label>
                <strong><?php esc_html_e( 'Quotable Representatives', 'kh-ad-manager' ); ?></strong>
            </label>
            <div id="quotable-reps-list" class="kh-repeater">
                <?php foreach ( $reps as $rep ) : 
                    $name = is_array( $rep ) ? $rep['name'] ?? '' : '';
                    $title = is_array( $rep ) ? $rep['title'] ?? '' : '';
                ?>
                    <div class="kh-repeater-row">
                        <input type="text" class="kh-rep-name" value="<?php echo esc_attr( $name ); ?>" placeholder="Name" />
                        <input type="text" class="kh-rep-title" value="<?php echo esc_attr( $title ); ?>" placeholder="Title" />
                        <button type="button" class="button kh-repeater-remove" onclick="this.parentElement.remove();"><?php esc_html_e( 'Remove', 'kh-ad-manager' ); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button-secondary" onclick="kh_add_rep_row()"><?php esc_html_e( 'Add Representative', 'kh-ad-manager' ); ?></button>
            <input type="hidden" id="quotable_representatives" name="quotable_representatives" value="<?php echo esc_attr( wp_json_encode( $reps ) ); ?>" />
        </div>
    </div>

    <script>
    function kh_add_rep_row() {
        const list = document.getElementById('quotable-reps-list');
        const row = document.createElement('div');
        row.className = 'kh-repeater-row';
        row.innerHTML = `
            <input type="text" class="kh-rep-name" placeholder="Name" />
            <input type="text" class="kh-rep-title" placeholder="Title" />
            <button type="button" class="button kh-repeater-remove" onclick="this.parentElement.remove();">Remove</button>
        `;
        list.appendChild(row);
    }
    
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('kh-rep-name') || e.target.classList.contains('kh-rep-title')) {
            const list = document.getElementById('quotable-reps-list');
            const rows = list.querySelectorAll('.kh-repeater-row');
            const reps = [];
            rows.forEach(row => {
                const name = row.querySelector('.kh-rep-name').value;
                const title = row.querySelector('.kh-rep-title').value;
                if (name || title) {
                    reps.push({ name, title });
                }
            });
            document.getElementById('quotable_representatives').value = JSON.stringify(reps);
        }
    });
    </script>

    <style>
    .kh-sponsor-metabox { padding: 10px; }
    .kh-form-group { margin: 15px 0; }
    .kh-form-group label { display: block; margin-bottom: 5px; }
    .kh-form-group input, .kh-form-group textarea { max-width: 100%; }
    .kh-form-group small { display: block; margin-top: 5px; color: #666; }
    .kh-repeater { background: #f9f9f9; padding: 10px; margin: 10px 0; border: 1px solid #ddd; }
    .kh-repeater-row { display: flex; gap: 10px; margin-bottom: 10px; }
    .kh-repeater-row input { flex: 1; padding: 8px; }
    .kh-repeater-remove { margin-left: auto; }
    </style>
    <?php
}

/**
 * Render: Sponsor Policies & Claims metabox
 */
function kh_ad_manager_render_sponsor_policies_metabox( $post ) {
    $co_brand_policy = get_post_meta( $post->ID, 'co_brand_policy', true );
    $allowed_claims  = get_post_meta( $post->ID, 'allowed_claims', true );

    if ( ! is_array( $allowed_claims ) ) {
        $allowed_claims = $allowed_claims ? array( $allowed_claims ) : array();
    }

    wp_nonce_field( 'kh_sponsor_policies_nonce', 'kh_sponsor_policies_nonce' );
    ?>
    <div class="kh-sponsor-metabox">
        <div class="kh-form-group">
            <label for="co_brand_policy">
                <strong><?php esc_html_e( 'Co-Brand Policy', 'kh-ad-manager' ); ?></strong>
            </label>
            <select id="co_brand_policy" name="co_brand_policy" class="widefat">
                <option value="co-brand" <?php selected( $co_brand_policy, 'co-brand' ); ?>>
                    <?php esc_html_e( 'Co-Brand (joint visibility with sponsor logo)', 'kh-ad-manager' ); ?>
                </option>
                <option value="sponsor-only" <?php selected( $co_brand_policy, 'sponsor-only' ); ?>>
                    <?php esc_html_e( 'Sponsor-Only (sponsor logo only)', 'kh-ad-manager' ); ?>
                </option>
                <option value="white-label" <?php selected( $co_brand_policy, 'white-label' ); ?>>
                    <?php esc_html_e( 'White Label (no sponsor branding)', 'kh-ad-manager' ); ?>
                </option>
            </select>
            <small><?php esc_html_e( 'Controls how sponsor branding appears in ads.', 'kh-ad-manager' ); ?></small>
        </div>

        <div class="kh-form-group">
            <label>
                <strong><?php esc_html_e( 'Allowed Claims', 'kh-ad-manager' ); ?></strong>
            </label>
            <p class="description"><?php esc_html_e( 'Enter claims this sponsor approves for use in ads. One per line. E.g. "Does 95% faster processing"', 'kh-ad-manager' ); ?></p>
            <textarea 
                id="allowed_claims" 
                name="allowed_claims"
                rows="6"
                class="widefat"
                placeholder="Does X&#10;Reduces Y by Z%&#10;Saves $N per month"
            ><?php echo esc_textarea( implode( "\n", $allowed_claims ) ); ?></textarea>
            <small><?php esc_html_e( 'These claims will be validated against generated ad copy. Keep versioned for audit trail.', 'kh-ad-manager' ); ?></small>
        </div>
    </div>
    <?php
}

/**
 * Render: Sponsor Assets metabox (media library)
 */
function kh_ad_manager_render_sponsor_assets_metabox( $post ) {
    $sponsor_assets = get_post_meta( $post->ID, 'sponsor_assets', true );

    if ( ! is_array( $sponsor_assets ) ) {
        $sponsor_assets = array();
    }

    wp_nonce_field( 'kh_sponsor_assets_nonce', 'kh_sponsor_assets_nonce' );
    ?>
    <div class="kh-sponsor-metabox">
        <p><?php esc_html_e( 'Upload approved sponsor assets (logos, creatives, captions). Supported: JPG, PNG, GIF, MP4, MOV.', 'kh-ad-manager' ); ?></p>
        
        <div id="sponsor-assets-gallery" class="kh-assets-gallery">
            <?php foreach ( $sponsor_assets as $asset ) :
                $attachment_id = (int) $asset['id'];
                $attachment = get_post( $attachment_id );
                if ( ! $attachment ) continue;
                
                $url = wp_get_attachment_url( $attachment_id );
                $thumb = wp_get_attachment_thumb_url( $attachment_id ) ?: $url;
            ?>
                <div class="kh-asset-item" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>">
                    <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php esc_attr_e( 'Asset', 'kh-ad-manager' ); ?>" />
                    <div class="kh-asset-overlay">
                        <button type="button" class="button kh-remove-asset" onclick="kh_remove_sponsor_asset(<?php echo esc_attr( $attachment_id ); ?>)">
                            <?php esc_html_e( 'Remove', 'kh-ad-manager' ); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="button button-primary" id="kh-upload-sponsor-asset">
            <?php esc_html_e( 'Upload Asset', 'kh-ad-manager' ); ?>
        </button>

        <input type="hidden" id="sponsor_assets" name="sponsor_assets" value="<?php echo esc_attr( wp_json_encode( $sponsor_assets ) ); ?>" />
    </div>

    <script>
    (function($) {
        var file_frame;

        $('#kh-upload-sponsor-asset').on('click', function(e) {
            e.preventDefault();

            if ( file_frame ) {
                file_frame.open();
                return;
            }

            file_frame = wp.media({
                title: '<?php esc_attr_e( 'Select Sponsor Assets', 'kh-ad-manager' ); ?>',
                button: { text: '<?php esc_attr_e( 'Add to Library', 'kh-ad-manager' ); ?>' },
                multiple: true,
                library: {
                    type: ['image', 'video']
                }
            });

            file_frame.on('select', function() {
                const selection = file_frame.state().get('selection');
                const currentAssets = JSON.parse($('#sponsor_assets').val() || '[]');

                selection.each(function(attachment) {
                    const attachmentId = attachment.get('id');
                    // Avoid duplicates
                    if (!currentAssets.find(a => a.id === attachmentId)) {
                        currentAssets.push({
                            id: attachmentId,
                            type: attachment.get('mime'),
                            url: attachment.get('url')
                        });

                        // Add to gallery
                        const thumb = attachment.get('type') === 'image' 
                            ? attachment.get('url') 
                            : attachment.get('icon');
                        const item = `<div class="kh-asset-item" data-attachment-id="${attachmentId}">
                            <img src="${thumb}" alt="Asset" />
                            <div class="kh-asset-overlay">
                                <button type="button" class="button kh-remove-asset" onclick="kh_remove_sponsor_asset(${attachmentId})">Remove</button>
                            </div>
                        </div>`;
                        $('#sponsor-assets-gallery').append(item);
                    }
                });

                $('#sponsor_assets').val(JSON.stringify(currentAssets));
            });

            file_frame.open();
        });
    })(jQuery);

    function kh_remove_sponsor_asset(attachmentId) {
        const assets = JSON.parse(document.getElementById('sponsor_assets').value || '[]');
        const filtered = assets.filter(a => a.id !== attachmentId);
        document.getElementById('sponsor_assets').value = JSON.stringify(filtered);
        document.querySelector(`[data-attachment-id="${attachmentId}"]`).remove();
    }
    </script>

    <style>
    .kh-assets-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px; margin: 15px 0; }
    .kh-asset-item { position: relative; background: #f0f0f0; border: 1px solid #ddd; overflow: hidden; }
    .kh-asset-item img { width: 100%; height: 100px; object-fit: cover; }
    .kh-asset-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: none; align-items: center; justify-content: center; }
    .kh-asset-item:hover .kh-asset-overlay { display: flex; }
    </style>
    <?php
}

/**
 * Render: Budget & Account metabox
 */
function kh_ad_manager_render_sponsor_budget_metabox( $post ) {
    $ppc_budget_total = (float) get_post_meta( $post->ID, 'ppc_budget_total', true );
    $ppc_daily_cap    = (float) get_post_meta( $post->ID, 'ppc_daily_cap', true );
    $ppc_account_id   = get_post_meta( $post->ID, 'ppc_account_id', true );
    $spend            = get_post_meta( $post->ID, 'spend_tracking', true );

    if ( ! is_array( $spend ) ) {
        $spend = array( 'total_spent' => 0, 'today_spent' => 0, 'last_updated' => 0 );
    }

    wp_nonce_field( 'kh_sponsor_budget_nonce', 'kh_sponsor_budget_nonce' );
    ?>
    <div class="kh-sponsor-metabox">
        <div class="kh-form-group">
            <label for="ppc_budget_total">
                <strong><?php esc_html_e( 'Total PPC Budget', 'kh-ad-manager' ); ?></strong>
            </label>
            <input 
                type="number" 
                id="ppc_budget_total" 
                name="ppc_budget_total" 
                value="<?php echo esc_attr( $ppc_budget_total ); ?>"
                step="0.01"
                min="0"
                class="widefat"
            />
        </div>

        <div class="kh-form-group">
            <label for="ppc_daily_cap">
                <strong><?php esc_html_e( 'Daily Budget Cap', 'kh-ad-manager' ); ?></strong>
            </label>
            <input 
                type="number" 
                id="ppc_daily_cap" 
                name="ppc_daily_cap" 
                value="<?php echo esc_attr( $ppc_daily_cap ); ?>"
                step="0.01"
                min="0"
                class="widefat"
            />
        </div>

        <div class="kh-form-group">
            <label for="ppc_account_id">
                <strong><?php esc_html_e( 'PPC Account ID', 'kh-ad-manager' ); ?></strong>
            </label>
            <input 
                type="text" 
                id="ppc_account_id" 
                name="ppc_account_id" 
                value="<?php echo esc_attr( $ppc_account_id ); ?>"
                class="widefat"
                placeholder="linkedin-ads-account-123"
            />
        </div>

        <hr />

        <p><strong><?php esc_html_e( 'Spend Tracking', 'kh-ad-manager' ); ?></strong></p>
        <p class="description">
            <?php esc_html_e( 'Total Spent:', 'kh-ad-manager' ); ?> <strong><?php echo esc_html( '$' . number_format( $spend['total_spent'] ?? 0, 2 ) ); ?></strong>
        </p>
        <p class="description">
            <?php esc_html_e( 'Today Spent:', 'kh-ad-manager' ); ?> <strong><?php echo esc_html( '$' . number_format( $spend['today_spent'] ?? 0, 2 ) ); ?></strong>
        </p>
        <p class="description">
            <?php esc_html_e( 'Remaining:', 'kh-ad-manager' ); ?> <strong><?php echo esc_html( '$' . number_format( max( 0, $ppc_budget_total - ( $spend['total_spent'] ?? 0 ) ), 2 ) ); ?></strong>
        </p>
        <p class="description" style="font-size: 0.85em; color: #666;">
            <?php esc_html_e( 'Last updated:', 'kh-ad-manager' ); ?> <?php echo esc_html( $spend['last_updated'] ? wp_date( 'Y-m-d H:i:s', $spend['last_updated'] ) : '—' ); ?>
        </p>
    </div>
    <?php
}

/**
 * Render: Approval Contact metabox
 */
function kh_ad_manager_render_sponsor_approval_metabox( $post ) {
    $approval_contact = get_post_meta( $post->ID, 'approval_contact', true );

    if ( ! is_array( $approval_contact ) ) {
        $approval_contact = array( 'name' => '', 'email' => '', 'role' => '' );
    }

    wp_nonce_field( 'kh_sponsor_approval_nonce', 'kh_sponsor_approval_nonce' );
    ?>
    <div class="kh-sponsor-metabox">
        <p class="description"><?php esc_html_e( 'Contact who approves ad variants.', 'kh-ad-manager' ); ?></p>

        <div class="kh-form-group">
            <label for="approval_name">
                <strong><?php esc_html_e( 'Name', 'kh-ad-manager' ); ?></strong>
            </label>
            <input 
                type="text" 
                id="approval_name" 
                name="approval_name" 
                value="<?php echo esc_attr( $approval_contact['name'] ?? '' ); ?>"
                class="widefat"
                placeholder="Jane Doe"
            />
        </div>

        <div class="kh-form-group">
            <label for="approval_email">
                <strong><?php esc_html_e( 'Email', 'kh-ad-manager' ); ?></strong>
            </label>
            <input 
                type="email" 
                id="approval_email" 
                name="approval_email" 
                value="<?php echo esc_attr( $approval_contact['email'] ?? '' ); ?>"
                class="widefat"
                placeholder="jane@sponsor.com"
            />
        </div>

        <div class="kh-form-group">
            <label for="approval_role">
                <strong><?php esc_html_e( 'Role', 'kh-ad-manager' ); ?></strong>
            </label>
            <input 
                type="text" 
                id="approval_role" 
                name="approval_role" 
                value="<?php echo esc_attr( $approval_contact['role'] ?? '' ); ?>"
                class="widefat"
                placeholder="Marketing Manager"
            />
        </div>

        <input type="hidden" id="approval_contact" name="approval_contact" value="<?php echo esc_attr( wp_json_encode( $approval_contact ) ); ?>" />

        <script>
        document.addEventListener('change', function(e) {
            if (e.target.id.startsWith('approval_')) {
                const contact = {
                    name: document.getElementById('approval_name').value,
                    email: document.getElementById('approval_email').value,
                    role: document.getElementById('approval_role').value
                };
                document.getElementById('approval_contact').value = JSON.stringify(contact);
            }
        });
        </script>
    </div>
    <?php
}

/**
 * Render: Geo-Specific Rules metabox
 */
function kh_ad_manager_render_sponsor_geo_metabox( $post ) {
    $geo_rules = get_post_meta( $post->ID, 'geo_rules', true );

    if ( ! is_array( $geo_rules ) ) {
        $geo_rules = array();
    }

    wp_nonce_field( 'kh_sponsor_geo_nonce', 'kh_sponsor_geo_nonce' );
    ?>
    <div class="kh-sponsor-metabox">
        <p class="description"><?php esc_html_e( 'Define sponsorship policies for specific countries/regions. E.g., UK might require specific logos or budget caps.', 'kh-ad-manager' ); ?></p>

        <div id="geo-rules-list">
            <?php foreach ( $geo_rules as $geo => $rule ) : ?>
                <div class="kh-geo-rule">
                    <input type="text" class="kh-geo-code" value="<?php echo esc_attr( $geo ); ?>" placeholder="GB" maxlength="2" />
                    <select class="kh-geo-policy">
                        <option value="co-brand" <?php selected( $rule['policy'] ?? '', 'co-brand' ); ?>>Co-Brand</option>
                        <option value="sponsor-only" <?php selected( $rule['policy'] ?? '', 'sponsor-only' ); ?>>Sponsor Only</option>
                        <option value="white-label" <?php selected( $rule['policy'] ?? '', 'white-label' ); ?>>White Label</option>
                    </select>
                    <input type="number" class="kh-geo-budget" value="<?php echo esc_attr( $rule['budget_cap'] ?? '' ); ?>" placeholder="Budget cap" step="0.01" />
                    <button type="button" class="button kh-remove-geo" onclick="this.parentElement.remove();">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="button button-secondary" onclick="kh_add_geo_rule()">
            <?php esc_html_e( 'Add Geo Rule', 'kh-ad-manager' ); ?>
        </button>

        <input type="hidden" id="geo_rules" name="geo_rules" value="<?php echo esc_attr( wp_json_encode( $geo_rules ) ); ?>" />

        <style>
        .kh-geo-rule { display: flex; gap: 10px; margin: 10px 0; align-items: center; }
        .kh-geo-rule input, .kh-geo-rule select { padding: 5px; }
        .kh-geo-code { width: 60px; }
        .kh-geo-policy { flex: 1; }
        .kh-geo-budget { width: 120px; }
        </style>

        <script>
        function kh_add_geo_rule() {
            const list = document.getElementById('geo-rules-list');
            const rule = document.createElement('div');
            rule.className = 'kh-geo-rule';
            rule.innerHTML = `
                <input type="text" class="kh-geo-code" placeholder="GB" maxlength="2" />
                <select class="kh-geo-policy">
                    <option value="co-brand">Co-Brand</option>
                    <option value="sponsor-only">Sponsor Only</option>
                    <option value="white-label">White Label</option>
                </select>
                <input type="number" class="kh-geo-budget" placeholder="Budget cap" step="0.01" />
                <button type="button" class="button kh-remove-geo" onclick="this.parentElement.remove();">Remove</button>
            `;
            list.appendChild(rule);
        }

        function kh_sync_geo_rules() {
            const list = document.getElementById('geo-rules-list');
            const rules = {};
            list.querySelectorAll('.kh-geo-rule').forEach(rule => {
                const geo = rule.querySelector('.kh-geo-code').value;
                if (geo) {
                    rules[geo] = {
                        policy: rule.querySelector('.kh-geo-policy').value,
                        budget_cap: rule.querySelector('.kh-geo-budget').value ? parseFloat(rule.querySelector('.kh-geo-budget').value) : null
                    };
                }
            });
            document.getElementById('geo_rules').value = JSON.stringify(rules);
        }

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('kh-geo-code') || 
                e.target.classList.contains('kh-geo-policy') || 
                e.target.classList.contains('kh-geo-budget')) {
                kh_sync_geo_rules();
            }
        });
        </script>
    </div>
    <?php
}

/**
 * Save sponsor metabox data
 */
add_action( 'save_post_kh_sponsor', function( $post_id ) {
    if ( ! isset( $_POST['kh_sponsor_profile_nonce'] ) || ! wp_verify_nonce( $_POST['kh_sponsor_profile_nonce'], 'kh_sponsor_profile_nonce' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Profile fields
    if ( isset( $_POST['linkedin_page_url'] ) ) {
        update_post_meta( $post_id, 'linkedin_page_url', esc_url_raw( $_POST['linkedin_page_url'] ) );
    }

    if ( isset( $_POST['linkedin_handles'] ) ) {
        $handles = array_map( 'trim', explode( ',', sanitize_text_field( $_POST['linkedin_handles'] ) ) );
        $handles = array_filter( $handles );
        update_post_meta( $post_id, 'linkedin_handles', $handles );
    }

    if ( isset( $_POST['content_library_url'] ) ) {
        update_post_meta( $post_id, 'content_library_url', esc_url_raw( $_POST['content_library_url'] ) );
    }

    if ( isset( $_POST['quotable_representatives'] ) ) {
        $reps = json_decode( sanitize_text_field( $_POST['quotable_representatives'] ), true );
        update_post_meta( $post_id, 'quotable_representatives', is_array( $reps ) ? $reps : array() );
    }

    // Policies
    if ( isset( $_POST['co_brand_policy'] ) ) {
        update_post_meta( $post_id, 'co_brand_policy', sanitize_text_field( $_POST['co_brand_policy'] ) );
    }

    if ( isset( $_POST['allowed_claims'] ) ) {
        $claims = array_map( 'trim', explode( "\n", sanitize_textarea_field( $_POST['allowed_claims'] ) ) );
        $claims = array_filter( $claims );
        update_post_meta( $post_id, 'allowed_claims', $claims );
    }

    // Assets
    if ( isset( $_POST['sponsor_assets'] ) ) {
        $assets = json_decode( sanitize_text_field( $_POST['sponsor_assets'] ), true );
        update_post_meta( $post_id, 'sponsor_assets', is_array( $assets ) ? $assets : array() );
    }

    // Budget
    if ( isset( $_POST['ppc_budget_total'] ) ) {
        update_post_meta( $post_id, 'ppc_budget_total', floatval( $_POST['ppc_budget_total'] ) );
    }

    if ( isset( $_POST['ppc_daily_cap'] ) ) {
        update_post_meta( $post_id, 'ppc_daily_cap', floatval( $_POST['ppc_daily_cap'] ) );
    }

    if ( isset( $_POST['ppc_account_id'] ) ) {
        update_post_meta( $post_id, 'ppc_account_id', sanitize_text_field( $_POST['ppc_account_id'] ) );
    }

    // Approval Contact
    if ( isset( $_POST['approval_contact'] ) ) {
        $contact = json_decode( sanitize_text_field( $_POST['approval_contact'] ), true );
        update_post_meta( $post_id, 'approval_contact', is_array( $contact ) ? $contact : array() );
    }

    // Geo Rules
    if ( isset( $_POST['geo_rules'] ) ) {
        $rules = json_decode( sanitize_text_field( $_POST['geo_rules'] ), true );
        update_post_meta( $post_id, 'geo_rules', is_array( $rules ) ? $rules : array() );
    }
} );
