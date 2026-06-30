<?php
/**
 * Plugin Name: KH Content Registry
 * Description: Centralized content registry for network-wide article management
 * Version: 1.0.0
 * Author: KHM Dev
 */

defined( 'ABSPATH' ) || exit;

// Autoloader setup
spl_autoload_register( function ( $class ) {
    $prefix = 'KH\\ContentRegistry\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    
    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
    
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

// Admin functionality
if ( is_admin() ) {
    add_action( 'admin_menu', function() {
        add_menu_page( 'Articles', 'Articles', 'manage_network', 'kh-content-registry', 'khcr_render_list_page', 'dashicons-list-view', 6 );
        add_submenu_page( 'kh-content-registry', 'All Articles', 'All Articles', 'manage_network', 'kh-content-registry', 'khcr_render_list_page' );
        add_submenu_page( 'kh-content-registry', 'Add New Article', 'Add New', 'manage_network', 'kh-content-registry-new', 'khcr_render_edit_page' );
        add_submenu_page( 'kh-content-registry', 'Settings', 'Settings', 'manage_network', 'kh-content-registry-settings', 'khcr_render_settings_page' );
    } );
    
    function khcr_render_list_page() {
        add_thickbox();
        $service = \KH\ContentRegistry\Services\ContentRegistryService::instance();
        $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $articles = $status ? $service->get_articles_by_status( $status ) : khcr_get_all_articles();
        ?>
        <div class="wrap">
            <h1>Articles</h1>
            <a href="<?php echo admin_url( 'admin.php?page=kh-content-registry-new' ); ?>" class="button button-primary">Add New</a>
            <table class="wp-list-table widefat fixed striped table-view-list posts">
                <caption class="screen-reader-text">Table ordered by Date. Descending.</caption>
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <input id="cb-select-all-1" type="checkbox">
                            <label for="cb-select-all-1"><span class="screen-reader-text">Select All</span></label>
                        </td>
                        <th scope="col" id="title" class="manage-column column-title column-primary sortable desc" abbr="Title">
                            <a href="#"><span>Title</span></a>
                        </th>
                        <th scope="col" id="status" class="manage-column column-status">Status</th>
                        <th scope="col" id="khm_schema" class="manage-column column-khm_schema">Schema</th>
                        <th scope="col" id="kh_author" class="manage-column column-kh_author">Author</th>
                        <th scope="col" id="taxonomy-content_type" class="manage-column column-taxonomy-content_type">Content Type</th>
                        <th scope="col" id="kh_seo_score" class="manage-column column-kh_seo_score">SEO</th>
                        <th scope="col" id="kh_geo_score" class="manage-column column-kh_geo_score">GEO</th>
                        <th scope="col" id="kh_smma" class="manage-column column-kh_smma">SMMA</th>
                        <th scope="col" id="kh_sponsor" class="manage-column column-kh_sponsor">Sponsor Commentary</th>
                        <th scope="col" id="kh_distribution" class="manage-column column-kh_distribution">Distribution</th>
                        <th scope="col" id="kh_atomic" class="manage-column column-kh_atomic">Atomic</th>
                        <th scope="col" id="kh_scheduled" class="manage-column column-kh_scheduled">Scheduled</th>
                        <th scope="col" id="date" class="manage-column column-date sorted desc" aria-sort="descending" abbr="Date">
                            <a href="#"><span>Date</span></a>
                        </th>
                    </tr>
                </thead>
                <tbody id="the-list">
                <?php foreach ( $articles as $article ): 
                    $article_status = $article->article_status ?? '';
                    $seo_data = isset($article->seo_metadata) ? (is_array($article->seo_metadata) ? $article->seo_metadata : json_decode($article->seo_metadata, true)) : [];
                    $geo_data = isset($article->geo_flags) ? (is_array($article->geo_flags) ? $article->geo_flags : json_decode($article->geo_flags, true)) : [];
                    $smma_data = isset($article->smma_flags) ? (is_array($article->smma_flags) ? $article->smma_flags : json_decode($article->smma_flags, true)) : [];
                    
                    $seo_score = is_array($seo_data) && isset($seo_data['score']) ? (int)$seo_data['score'] : 0;
                    $geo_score = is_array($geo_data) && isset($geo_data['score']) ? (int)$geo_data['score'] : 0;
                    $smma_status = is_array($smma_data) && isset($smma_data['status']) ? $smma_data['status'] : 'Awaiting';
                    
                    // Get atomic count from post meta
                    $atomic_count = 0;
                    if ( class_exists( 'KH\\Editorial\\PostTypes\\AtomicArticlePostType' ) ) {
                        $atomic_count = count( \KH\Editorial\PostTypes\AtomicArticlePostType::get_ids_for_parent( $article->id ) );
                    }
                    
                    $scheduled_date = ($article_status === 'Scheduled') ? esc_html( $article->updated_at ?? $article->created_at ) : 'Awaiting';
                    
                    // Traffic light pills
                    $seo_pill = $seo_score >= 80 ? 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #28a745; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"' : ($seo_score >= 65 ? 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #dba617; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"' : 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #dc3545; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"');
                    $geo_pill = $geo_score >= 80 ? 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #28a745; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"' : ($geo_score >= 65 ? 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #dba617; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"' : 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #dc3545; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"');
                    $smma_pill = $smma_status === 'Awaiting' ? 'style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"' : ($smma_status === 'Scheduled' ? 'style="background: #dba617; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"' : 'style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"');
                    $atomic_pill = $atomic_count === 0 ? 'style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"' : 'style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"';
                    
                    // Get WordPress post ID for edit link
                    $wp_post_id = $article->wp_post_id ?? null;
                    if ($wp_post_id) {
                        // Switch to the correct blog to get the edit link
                        switch_to_blog($article->target_blog_id);
                        $edit_url = get_edit_post_link($wp_post_id, 'raw');
                        restore_current_blog();
                    } else {
                        $edit_url = admin_url('post-new.php?post_type=post&registry_id=' . $article->id);
                    }
                ?>
                <tr id="post-<?php echo esc_attr( $article->id ); ?>" class="iedit author-self level-0 post-<?php echo esc_attr( $article->id ); ?> type-post status-<?php echo esc_attr( $article_status ); ?> format-standard hentry">
                    <th scope="row" class="check-column">
                        <input id="cb-select-<?php echo esc_attr( $article->id ); ?>" type="checkbox" name="post[]" value="<?php echo esc_attr( $article->id ); ?>">
                        <label for="cb-select-<?php echo esc_attr( $article->id ); ?>">
                            <span class="screen-reader-text">Select <?php echo esc_html( $article->title ); ?></span>
                        </label>
                    </th>
                    <td class="title column-title has-row-actions column-primary page-title" data-colname="Title">
                        <strong class="row-title"><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $article->title ); ?></a></strong>
                        <div class="row-actions">
                            <span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>" aria-label="Edit "<?php echo esc_attr( $article->title ); ?>"">Edit</a> | </span>
                            <span class="trash"><a href="<?php echo admin_url( 'admin.php?page=kh-content-registry-new&action=delete&id=' . $article->id ); ?>" class="submitdelete" aria-label="Delete "<?php echo esc_attr( $article->title ); ?>"">Trash</a></span>
                        </div>
                    </td>
                    <td class="status column-status" data-colname="Status"><?php echo esc_html( $article_status ); ?></td>
                    <td class="khm_schema column-khm_schema" data-colname="Schema">
                        <span class="khm-schema-status <?php echo $article_status === 'Live' ? 'enabled' : 'disabled'; ?>" title="<?php echo esc_attr( $article_status ); ?>">
                            <span class="dashicons dashicons-yes-alt"></span> Article
                        </span>
                    </td>
                    <td class="kh_author column-kh_author" data-colname="Author">System</td>
                    <td class="taxonomy-content_type column-taxonomy-content_type" data-colname="Content Type"><span aria-hidden="true">Word</span><span class="screen-reader-text">Content Type: Word</span></td>
                    <td class="kh_seo_score column-kh_seo_score" data-colname="SEO"><span <?php echo $seo_pill; ?>><?php echo $seo_score > 0 ? $seo_score : '—'; ?></span></td>
                    <td class="kh_geo_score column-kh_geo_score" data-colname="GEO"><span <?php echo $geo_pill; ?>><?php echo $geo_score > 0 ? $geo_score : '—'; ?></span></td>
                    <td class="kh_smma column-kh_smma" data-colname="SMMA"><span <?php echo $smma_pill; ?>><?php echo esc_html( $smma_status ); ?></span></td>
                    <td class="kh_sponsor column-kh_sponsor" data-colname="Sponsor Commentary"><?php echo $article->sponsor_commentary ? '<span title="' . esc_attr( wp_strip_all_tags( $article->sponsor_commentary ) ) . '">Yes</span>' : '<span style="color: #646970;">None</span>'; ?></td>
<td class="kh_distribution column-kh_distribution" data-colname="Distribution">
    <?php 
    $child_count = 0;
    if ( class_exists( 'KH\\Editorial\\Services\\AllocationService' ) ) {
        $alloc_service = new \KH\Editorial\Services\AllocationService();
        $alloc_status = $alloc_service->get_allocation_status( $article->id );
        $child_count = count( array_filter( $alloc_status, fn($s) => $s['allocated'] ) );
    }
    ?>
    <a href="#TB_inline?width=450&height=450&inlineId=kh-distribute-modal-<?php echo (int) $article->id; ?>" class="button button-small thickbox" style="padding: 2px 8px; font-size: 11px;">
        <?php echo $child_count > 0 ? $child_count : '—'; ?>
    </a>
</td>
                    <td class="kh_atomic column-kh_atomic" data-colname="Atomic"><span <?php echo $atomic_pill; ?>><?php echo $atomic_count > 0 ? $atomic_count : 'Pending'; ?></span></td>
                    <td class="kh_scheduled column-kh_scheduled" data-colname="Scheduled"><?php echo $scheduled_date; ?></td>
                    <td class="date column-date" data-colname="Date"><?php echo esc_html( $article->created_at ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column"><input id="cb-select-all-2" type="checkbox"><label for="cb-select-all-2"><span class="screen-reader-text">Select All</span></label></td>
                        <th scope="col" class="manage-column column-title column-primary sortable desc" abbr="Title"><a href="#"><span>Title</span></a></th>
                        <th scope="col" class="manage-column column-status">Status</th>
                        <th scope="col" class="manage-column column-khm_schema">Schema</th>
                        <th scope="col" class="manage-column column-kh_author">Author</th>
                        <th scope="col" class="manage-column column-taxonomy-content_type">Content Type</th>
                        <th scope="col" class="manage-column column-kh_seo_score">SEO</th>
                        <th scope="col" class="manage-column column-kh_geo_score">GEO</th>
                        <th scope="col" class="manage-column column-kh_smma">SMMA</th>
                        <th scope="col" class="manage-column column-kh_sponsor">Sponsor Commentary</th>
                        <th scope="col" class="manage-column column-kh_distribution">Distribution</th>
                        <th scope="col" class="manage-column column-kh_atomic">Atomic</th>
                        <th scope="col" class="manage-column column-kh_scheduled">Scheduled</th>
                        <th scope="col" class="manage-column column-date sorted desc" aria-sort="descending" abbr="Date"><a href="#"><span>Date</span></a></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Distribution Modals -->
        <?php foreach ( $articles as $article ): 
            $alloc_service = class_exists( 'KH\\Editorial\\Services\\AllocationService' ) ? new \KH\Editorial\Services\AllocationService() : null;
            $allocations = $alloc_service ? $alloc_service->get_allocation_status( $article->id ) : [];
            $allocated_ids = [];
            foreach ( $allocations as $alloc ) {
                if ( $alloc['allocated'] ) {
                    $allocated_ids[] = (int) $alloc['blog_id'];
                }
            }
        ?>
        <div id="kh-distribute-modal-<?php echo (int) $article->id; ?>" style="display:none;">
            <div class="kh-distribute-modal-content" style="padding: 16px;">
                <h2 style="margin-top: 0;"><?php esc_html_e( 'Distribute to Sites', 'kh-content-registry' ); ?></h2>
                <p style="color: #646970; font-size: 13px;">
                    <?php printf(
                        esc_html__( 'Article: %s', 'kh-content-registry' ),
                        '<strong>' . esc_html( $article->title ) . '</strong>'
                    ); ?>
                </p>

                <div class="kh-allocation-sites-list" style="margin-bottom: 12px;">
                    <?php foreach ( $allocations as $site ): 
                        $blog_id  = (int) $site['blog_id'];
                        $disabled = in_array( $blog_id, $allocated_ids, true );
                        $status   = $disabled
                            ? '<span style="color: #00a32a; font-size: 12px;">' . esc_html__( 'Already distributed', 'kh-content-registry' ) . '</span>'
                            : '';
                    ?>
                        <label class="kh-allocation-site-row"
                               style="display: flex; align-items: center; padding: 6px 0; border-bottom: 1px solid #f0f0f1; cursor: <?php echo $disabled ? 'default' : 'pointer'; ?>;"
                               data-slug="<?php echo esc_attr( $site['slug'] ); ?>"
                               data-blog-id="<?php echo esc_attr( $blog_id ); ?>">
                            <input type="checkbox"
                                   class="kh-allocation-checkbox"
                                   value="<?php echo esc_attr( $site['slug'] ); ?>"
                                   data-blog-id="<?php echo esc_attr( $blog_id ); ?>"
                                   <?php echo $disabled ? ' disabled' : ''; ?>
                                   style="margin-right: 8px;">
                            <span style="flex: 1; font-size: 13px;"><?php echo esc_html( $site['label'] ); ?></span>
                            <span class="kh-allocation-status" style="font-size: 12px;"><?php echo $status; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="kh-allocation-actions" style="display: flex; gap: 8px;">
                    <button type="button"
                            class="kh-allocate-clone-rewrite button button-primary"
                            style="flex: 1;"
                            data-post-id="<?php echo (int) $article->id; ?>"
                            disabled>
                        <?php esc_html_e( 'Clone & Rewrite', 'kh-content-registry' ); ?>
                    </button>
                    <button type="button"
                            class="kh-allocate-clone-only button"
                            style="flex: 1;"
                            data-post-id="<?php echo (int) $article->id; ?>"
                            disabled>
                        <?php esc_html_e( 'Clone Only', 'kh-content-registry' ); ?>
                    </button>
                </div>

                <div class="kh-allocation-status-message"
                     style="margin-top: 10px; padding: 8px; border-radius: 4px; display: none; font-size: 12px;">
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <style>
            .kh-distribute-modal-content .kh-allocation-site-row:hover {
                background: #f6f7f7;
            }
            .kh-distribute-modal-content button:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            .wp-list-table th, .wp-list-table td {
                vertical-align: middle;
            }
        </style>
        
        <script>
        (function ($) {
            'use strict';
            
            // Enable/disable buttons based on checkbox selection
            $(document).on('change', '.kh-allocation-checkbox', function () {
                var $modal = $(this).closest('.kh-distribute-modal-content');
                var checked = $modal.find('.kh-allocation-checkbox:checked:not(:disabled)').length;
                $modal.find('.kh-allocate-clone-rewrite, .kh-allocate-clone-only').prop('disabled', checked === 0);
            });
            
            // Clone & Rewrite
            $(document).on('click', '.kh-allocate-clone-rewrite', function () {
                doDistribute($(this), true);
            });
            
            // Clone Only
            $(document).on('click', '.kh-allocate-clone-only', function () {
                doDistribute($(this), false);
            });
            
            function doDistribute($btn, rewrite) {
                var $modal = $btn.closest('.kh-distribute-modal-content');
                var postId = $btn.data('post-id');
                var $checkboxes = $modal.find('.kh-allocation-checkbox:checked:not(:disabled)');
                var selectedSlugs = $checkboxes.map(function () { return $(this).val(); }).get();
                
                if (selectedSlugs.length === 0) {
                    showStatus($modal, 'Please select at least one site.', 'error');
                    return;
                }
                
                setLoading($modal, $btn, true);
                
                wp.apiFetch({
                    path: 'kh-editorial/v1/allocation/clone',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
                        'Content-Type': 'application/json',
                    },
                    data: {
                        post_id: postId,
                        site_slugs: selectedSlugs,
                        rewrite: rewrite,
                    },
                }).then(function (result) {
                    if (result.success) {
                        showStatus($modal, 'Post distributed successfully.', 'success');
                        
                        // Update the inline grid icons
                        if (result.results) {
                            result.results.forEach(function (r) {
                                var blogId = r.blog_id;
                                var cellKey = 'kh-dist-cell-' + postId + '-' + blogId;
                                var $cell = $('#' + cellKey);
                                if ($cell.length) {
                                    var icon = r.rewrite_applied ? 'R' : 'C';
                                    var color = r.rewrite_applied ? '#dba617' : '#00a32a';
                                    var title = r.rewrite_applied
                                        ? 'Distributed (rewritten)'
                                        : 'Distributed';
                                    $cell.html('<span style="color:' + color + ';font-weight:bold;">' + icon + '</span>')
                                        .attr('title', title);
                                }
                            });
                        }
                        
                        // Close thickbox
                        if (typeof tb_remove === 'function') {
                            tb_remove();
                        }
                        
                        // Reload after short delay to refresh modals
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        showStatus($modal, result.message || 'An error occurred.', 'error');
                    }
                }).catch(function (err) {
                    showStatus($modal, (err && err.message) || 'An error occurred.', 'error');
                }).finally(function () {
                    setLoading($modal, $btn, false);
                });
            }
            
            function showStatus($modal, message, type) {
                var $msg = $modal.find('.kh-allocation-status-message');
                $msg.text(message)
                    .removeClass('notice-success notice-error')
                    .addClass(type === 'success' ? 'notice-success' : 'notice-error')
                    .css({
                        display: 'block',
                        background: type === 'success' ? '#ecf7ed' : '#fbeaea',
                        borderLeft: type === 'success' ? '4px solid #00a32a' : '4px solid #d63638',
                        color: type === 'success' ? '#1e561e' : '#8b3a3a',
                    });
            }
            
            function setLoading($modal, $btn, loading) {
                var isRewrite = $btn.hasClass('kh-allocate-clone-rewrite');
                var $otherBtn = isRewrite ? $modal.find('.kh-allocate-clone-only') : $modal.find('.kh-allocate-clone-rewrite');
                var label = isRewrite
                    ? (loading ? 'AI rewriting...' : 'Clone & Rewrite')
                    : (loading ? 'Distributing...' : 'Clone Only');
                
                $btn.prop('disabled', loading).text(label);
                $otherBtn.prop('disabled', loading);
                $modal.find('.kh-allocation-checkbox').prop('disabled', loading);
            }
        })(jQuery);
        </script>
        <?php
    }
    
    function khcr_render_edit_page() {
        $article = null;
        $article_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : null;
        if ( $article_id ) {
            $service = \KH\ContentRegistry\Services\ContentRegistryService::instance();
            $article = $service->get_article_by_id( $article_id );
        }
        $statuses = [ 'Summary', 'Framework', 'Draft', 'Scheduled', 'Live' ];
        ?>
        <div class="wrap">
            <h1><?php echo $article ? 'Edit Article' : 'Add New Article'; ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'kh_content_registry_save', 'kr_nonce' ); ?>
                <table class="form-table">
                    <tr><th><label for="title">Title</label></th><td><input type="text" id="title" name="title" value="<?php echo esc_attr( $article->title ?? '' ); ?>" class="regular-text" required /></td></tr>
                    <tr><th><label for="slug">Slug</label></th><td><input type="text" id="slug" name="slug" value="<?php echo esc_attr( $article->slug ?? '' ); ?>" class="regular-text" required /></td></tr>
                    <tr><th><label for="article_status">Status</label></th>
                        <td><select id="article_status" name="article_status">
                            <?php foreach ( $statuses as $s ): ?>
                            <option value="<?php echo $s; ?>" <?php selected( $article->article_status ?? '', $s ); ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select></td></tr>
                    <tr><th><label for="target_blog_id">Target Blog ID</label></th><td><input type="number" id="target_blog_id" name="target_blog_id" value="<?php echo esc_attr( $article->target_blog_id ?? '' ); ?>" class="small-text" required /></td></tr>
                    <tr><th><label for="content_body">Content Body</label></th><td><textarea id="content_body" name="content_body" class="large-text"><?php echo esc_textarea( $article->content_body ?? '' ); ?></textarea></td></tr>
                    <tr><th><label for="excerpt">Excerpt</label></th><td><textarea id="excerpt" name="excerpt" class="large-text"><?php echo esc_textarea( $article->excerpt ?? '' ); ?></textarea></td></tr>
                    <tr><th><label for="sponsor_commentary">Sponsor Commentary</label></th><td><textarea id="sponsor_commentary" name="sponsor_commentary" class="large-text"><?php echo esc_textarea( $article->sponsor_commentary ?? '' ); ?></textarea></td></tr>
                    <tr><th><label for="parent_post_id">Parent Post ID</label></th><td><input type="number" id="parent_post_id" name="parent_post_id" value="<?php echo esc_attr( $article->parent_post_id ?? '' ); ?>" class="small-text" /></td></tr>
                    <tr><th><label for="seo_metadata">SEO Metadata (JSON)</label></th><td><textarea id="seo_metadata" name="seo_metadata" class="large-text"><?php echo esc_textarea( is_array($article->seo_metadata ?? null) ? json_encode($article->seo_metadata) : ($article->seo_metadata ?? '') ); ?></textarea></td></tr>
                    <tr><th><label for="geo_flags">GEO Flags (JSON)</label></th><td><textarea id="geo_flags" name="geo_flags" class="large-text"><?php echo esc_textarea( is_array($article->geo_flags ?? null) ? json_encode($article->geo_flags) : ($article->geo_flags ?? '') ); ?></textarea></td></tr>
                    <tr><th><label for="smma_flags">SMMA Flags (JSON)</label></th><td><textarea id="smma_flags" name="smma_flags" class="large-text"><?php echo esc_textarea( is_array($article->smma_flags ?? null) ? json_encode($article->smma_flags) : ($article->smma_flags ?? '') ); ?></textarea></td></tr>
                </table>
                <?php submit_button( $article ? 'Update Article' : 'Create Article' ); ?>
            </form>
        </div>
        <?php
    }
    
    function khcr_render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Articles Settings</h1>
            <p>Content Registry settings - managed centrally on the main site.</p>
            <table class="form-table">
                <tr><th><label>Cache Group</label></th><td>content_registry</td></tr>
                <tr><th><label>Cache TTL</label></th><td>3600 seconds (1 hour)</td></tr>
            </table>
        </div>
        <?php
    }
    
    function khcr_get_all_articles() {
        global $wpdb;
        $articles = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}content_registry ORDER BY created_at DESC" );
        foreach ( $articles as $article ) {
            $article->seo_metadata = isset($article->seo_metadata) ? json_decode($article->seo_metadata, true) : [];
            $article->geo_flags = isset($article->geo_flags) ? json_decode($article->geo_flags, true) : [];
            $article->smma_flags = isset($article->smma_flags) ? json_decode($article->smma_flags, true) : [];
        }
        return $articles;
    }
}

// Initialize on plugins_loaded
add_action( 'plugins_loaded', function() {
    add_action( 'rest_api_init', function() {
        register_rest_route( 'network-content/v1', '/search', [
            'methods' => 'GET',
            'callback' => function( $request ) {
                $service = \KH\ContentRegistry\Services\ContentRegistryService::instance();
                $query = $request->get_param( 'q' );
                $blog_id = $request->get_param( 'blog_id' );
                $results = $service->search_articles( $query, $blog_id );
                return rest_ensure_response( $results );
            },
            'permission_callback' => '__return_true',
        ] );
    } );
} );
