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
            
            <!-- Filter Bar -->
            <div class="khcr-filter-bar" style="display: flex; align-items: center; gap: 8px; margin: 12px 0; flex-wrap: wrap;">
                <div class="khcr-add-filter-wrapper" style="position: relative;">
                    <button type="button" class="button khcr-add-filter-btn">+ Add Filter</button>
                    <div class="khcr-filter-dropdown" style="display: none; position: absolute; top: 100%; left: 0; z-index: 100; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; box-shadow: 0 3px 10px rgba(0,0,0,0.15); min-width: 200px; padding: 4px 0; margin-top: 4px;">
                        <a href="#" class="khcr-filter-option" data-column="status" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">Status</a>
                        <a href="#" class="khcr-filter-option" data-column="schema" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">Schema</a>
                        <a href="#" class="khcr-filter-option" data-column="author" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">Author</a>
                        <a href="#" class="khcr-filter-option" data-column="content-type" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">Content Type</a>
                        <a href="#" class="khcr-filter-option" data-column="seo" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">SEO</a>
                        <a href="#" class="khcr-filter-option" data-column="geo" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">GEO</a>
                        <a href="#" class="khcr-filter-option" data-column="smma" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">SMMA</a>
                        <a href="#" class="khcr-filter-option" data-column="sponsor" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">Sponsor Commentary</a>
                        <a href="#" class="khcr-filter-option" data-column="distribution" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">Distribution</a>
                        <a href="#" class="khcr-filter-option" data-column="atomic" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">Atomic</a>
                        <a href="#" class="khcr-filter-option" data-column="scheduled" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">Scheduled</a>
                        <a href="#" class="khcr-filter-option" data-column="date" style="display: block; padding: 6px 12px; text-decoration: none; color: #3c434a;">Date</a>
                    </div>
                </div>
                
                <div class="khcr-columns-wrapper" style="position: relative;">
                    <button type="button" class="button khcr-columns-btn">Columns</button>
                    <div class="khcr-columns-dropdown" style="display: none; position: absolute; top: 100%; left: 0; z-index: 100; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; box-shadow: 0 3px 10px rgba(0,0,0,0.15); min-width: 180px; padding: 8px 12px; margin-top: 4px;">
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="2" checked> Status</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="3" checked> Schema</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="4" checked> Author</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="5" checked> Content Type</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="6" checked> SEO</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="7" checked> GEO</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="8" checked> SMMA</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="9" checked> Sponsor Commentary</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="10" checked> Distribution</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="11" checked> Atomic</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="12" checked> Scheduled</label>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px;"><input type="checkbox" class="khcr-col-toggle" data-col="13" checked> Date</label>
                    </div>
                </div>
                
                <div class="khcr-views-wrapper" style="position: relative;">
                    <button type="button" class="button khcr-views-btn">Saved Views</button>
                    <div class="khcr-views-dropdown" style="display: none; position: absolute; top: 100%; left: 0; z-index: 100; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; box-shadow: 0 3px 10px rgba(0,0,0,0.15); min-width: 220px; padding: 4px 0; margin-top: 4px;">
                        <div class="khcr-views-list" style="max-height: 260px; overflow-y: auto;"></div>
                        <div class="khcr-views-empty" style="padding: 8px 12px; color: #646970; font-size: 12px; font-style: italic;">No saved views yet.</div>
                        <div style="border-top: 1px solid #e0e0e0; margin: 4px 0;"></div>
                        <a href="#" class="khcr-views-save" style="display: block; padding: 6px 12px; text-decoration: none; color: #2271b1; font-size: 13px;">Save Current View...</a>
                        <a href="#" class="khcr-views-manage" style="display: block; padding: 6px 12px; text-decoration: none; color: #2271b1; font-size: 13px;">Manage Views...</a>
                    </div>
                </div>
                <span class="khcr-active-view-label" style="font-size: 12px; color: #2271b1; font-weight: 600; display: none;"></span>
                <button type="button" class="button khcr-clear-filters-btn" style="display: none;">Clear All Filters</button>
                <span class="khcr-filter-count" style="font-size: 12px; color: #646970; display: none;"></span>
                
                <div id="khcr-active-chips" style="display: flex; gap: 4px; flex-wrap: wrap; margin-left: 4px;"></div>
            </div>
            
            <!-- Filter Config Modal (hidden, cloned per use) -->
            <div id="khcr-filter-config-template" style="display: none;">
                <div class="khcr-filter-config-content" style="padding: 16px; min-width: 260px;">
                    <h3 class="khcr-filter-config-title" style="margin: 0 0 12px 0;">Filter by <span class="khcr-col-label"></span></h3>
                    <div class="khcr-filter-mode" style="margin-bottom: 12px;">
                        <label style="margin-right: 16px;"><input type="radio" name="khcr-mode" value="include" checked> Show</label>
                        <label><input type="radio" name="khcr-mode" value="exclude"> Exclude</label>
                    </div>
                    <div class="khcr-filter-values"></div>
                    <div class="khcr-filter-actions" style="margin-top: 12px; display: flex; gap: 8px;">
                        <button type="button" class="button button-primary khcr-apply-filter">Apply</button>
                        <button type="button" class="button khcr-cancel-filter">Cancel</button>
                    </div>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped table-view-list posts khcr-main-table">
                <caption class="screen-reader-text">Table ordered by Date. Descending.</caption>
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column" data-col="0">
                            <input id="cb-select-all-1" type="checkbox">
                            <label for="cb-select-all-1"><span class="screen-reader-text">Select All</span></label>
                        </td>
                        <th scope="col" id="title" class="manage-column column-title column-primary sortable desc" abbr="Title" data-col="1">
                            <a href="#"><span>Title</span></a>
                        </th>
                        <th scope="col" id="status" class="manage-column column-status" data-col="2">Status</th>
                        <th scope="col" id="khm_schema" class="manage-column column-khm_schema" data-col="3">Schema</th>
                        <th scope="col" id="kh_author" class="manage-column column-kh_author" data-col="4">Author</th>
                        <th scope="col" id="taxonomy-content_type" class="manage-column column-taxonomy-content_type" data-col="5">Content Type</th>
                        <th scope="col" id="kh_seo_score" class="manage-column column-kh_seo_score" data-col="6">SEO</th>
                        <th scope="col" id="kh_geo_score" class="manage-column column-kh_geo_score" data-col="7">GEO</th>
                        <th scope="col" id="kh_smma" class="manage-column column-kh_smma" data-col="8">SMMA</th>
                        <th scope="col" id="kh_sponsor" class="manage-column column-kh_sponsor" data-col="9">Sponsor Commentary</th>
                        <th scope="col" id="kh_distribution" class="manage-column column-kh_distribution" data-col="10">Distribution</th>
                        <th scope="col" id="kh_atomic" class="manage-column column-kh_atomic" data-col="11">Atomic</th>
                        <th scope="col" id="kh_scheduled" class="manage-column column-kh_scheduled" data-col="12">Scheduled</th>
                        <th scope="col" id="date" class="manage-column column-date sorted desc" aria-sort="descending" abbr="Date" data-col="13">
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
                    
                    // Get atomic count from database
                    $atomic_count = (int) ($article->atomic_count ?? 0);
                    
                    $scheduled_date = ($article_status === 'Scheduled') ? esc_html( $article->updated_at ?? $article->created_at ) : 'Awaiting';
                    
                    // Traffic light pills
                    $seo_pill = $seo_score >= 80 ? 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #28a745; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"' : ($seo_score >= 65 ? 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #dba617; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"' : 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #dc3545; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"');
                    $geo_pill = $geo_score >= 80 ? 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #28a745; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"' : ($geo_score >= 65 ? 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #dba617; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"' : 'style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: #dc3545; color: #fff; font-weight: 600; text-align: center; font-size: 12px;"');
                    $smma_pill = $smma_status === 'Awaiting' ? 'style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"' : ($smma_status === 'Scheduled' ? 'style="background: #dba617; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"' : 'style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"');
                    $atomic_pill = $atomic_count === 0 ? 'style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"' : 'style="background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;"';
                    
                    // Get WordPress post ID for edit link
                    // Posts are now all on blog 1 (parent site), so no need to switch blogs
                    $wp_post_id = $article->wp_post_id ?? null;
                    if ($wp_post_id) {
                        $edit_url = get_edit_post_link($wp_post_id, 'raw');
                    } else {
                        $edit_url = admin_url('post-new.php?post_type=post&registry_id=' . $article->id);
                    }

                    // Distribution child count
                    $child_count = 0;
                    if ( class_exists( 'KH\\Editorial\\Services\\AllocationService' ) ) {
                        $alloc_service = new \KH\Editorial\Services\AllocationService();
                        $alloc_status = $alloc_service->get_allocation_status( $article->id );
                        $child_count = count( array_filter( $alloc_status, function($s) { return $s['allocated']; } ) );
                    }

                    $scheduled_val = ($article_status === 'Scheduled') ? esc_attr( $article->updated_at ?? $article->created_at ) : 'Awaiting';
                ?>
                <tr id="post-<?php echo esc_attr( $article->id ); ?>" class="iedit author-self level-0 post-<?php echo esc_attr( $article->id ); ?> type-post status-<?php echo esc_attr( $article_status ); ?> format-standard hentry khcr-row"
                    data-status="<?php echo esc_attr( $article_status ); ?>"
                    data-seo="<?php echo (int)$seo_score; ?>"
                    data-geo="<?php echo (int)$geo_score; ?>"
                    data-smma="<?php echo esc_attr( $smma_status ); ?>"
                    data-sponsor="<?php echo $article->sponsor_commentary ? 'yes' : 'none'; ?>"
                    data-distribution="<?php echo (int)$child_count; ?>"
                    data-atomic="<?php echo (int)$atomic_count; ?>"
                    data-scheduled="<?php echo esc_attr( $scheduled_val ); ?>"
                    data-date="<?php echo esc_attr( $article->created_at ); ?>"
                    data-schema="article"
                    data-author="system"
                    data-content-type="word">
                    <th scope="row" class="check-column" data-col="0">
                        <input id="cb-select-<?php echo esc_attr( $article->id ); ?>" type="checkbox" name="post[]" value="<?php echo esc_attr( $article->id ); ?>">
                        <label for="cb-select-<?php echo esc_attr( $article->id ); ?>">
                            <span class="screen-reader-text">Select <?php echo esc_html( $article->title ); ?></span>
                        </label>
                    </th>
                    <td class="title column-title has-row-actions column-primary page-title" data-col="1" data-colname="Title">
                        <strong class="row-title"><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $article->title ); ?></a></strong>
                        <div class="row-actions">
                            <span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>" aria-label="Edit "<?php echo esc_attr( $article->title ); ?>"">Edit</a></span>
                            <?php $session_id = $article->planner_session_id ?? 0; ?>
                            <?php if ( $session_id && in_array( $article_status, [ 'Summary', 'Framework' ], true ) ): ?>
                                | <span class="view-session"><a href="<?php echo esc_url( admin_url( 'admin.php?page=editorial_planner&session_id=' . $session_id . '&id=' . $session_id ) ); ?>" aria-label="View Session for "<?php echo esc_attr( $article->title ); ?>"">View Session</a></span>
                            <?php endif; ?>
                            | <span class="trash"><a href="<?php echo admin_url( 'admin.php?page=kh-content-registry-new&action=delete&id=' . $article->id ); ?>" class="submitdelete" aria-label="Delete "<?php echo esc_attr( $article->title ); ?>"">Trash</a></span>
                        </div>
                    </td>
                    <td class="status column-status" data-col="2" data-colname="Status"><?php echo esc_html( $article_status ); ?></td>
                    <td class="khm_schema column-khm_schema" data-col="3" data-colname="Schema">
                        <span class="khm-schema-status <?php echo $article_status === 'Live' ? 'enabled' : 'disabled'; ?>" title="<?php echo esc_attr( $article_status ); ?>">
                            <span class="dashicons dashicons-yes-alt"></span> Article
                        </span>
                    </td>
                    <td class="kh_author column-kh_author" data-col="4" data-colname="Author">System</td>
                    <td class="taxonomy-content_type column-taxonomy-content_type" data-col="5" data-colname="Content Type"><span aria-hidden="true">Word</span><span class="screen-reader-text">Content Type: Word</span></td>
                    <td class="kh_seo_score column-kh_seo_score" data-col="6" data-colname="SEO"><span <?php echo $seo_pill; ?>><?php echo $seo_score > 0 ? $seo_score : '—'; ?></span></td>
                    <td class="kh_geo_score column-kh_geo_score" data-col="7" data-colname="GEO"><span <?php echo $geo_pill; ?>><?php echo $geo_score > 0 ? $geo_score : '—'; ?></span></td>
                    <td class="kh_smma column-kh_smma" data-col="8" data-colname="SMMA"><span <?php echo $smma_pill; ?>><?php echo esc_html( $smma_status ); ?></span></td>
                    <td class="kh_sponsor column-kh_sponsor" data-col="9" data-colname="Sponsor Commentary"><?php echo $article->sponsor_commentary ? '<span title="' . esc_attr( wp_strip_all_tags( $article->sponsor_commentary ) ) . '">Yes</span>' : '<span style="color: #646970;">None</span>'; ?></td>
                    <td class="kh_distribution column-kh_distribution" data-col="10" data-colname="Distribution">
                        <a href="#TB_inline?width=450&height=450&inlineId=kh-distribute-modal-<?php echo (int) $article->id; ?>" class="button button-small thickbox" style="padding: 2px 8px; font-size: 11px;">
                            <?php echo $child_count > 0 ? $child_count : '—'; ?>
                        </a>
                    </td>
                    <td class="kh_atomic column-kh_atomic" data-col="11" data-colname="Atomic"><span <?php echo $atomic_pill; ?>><?php echo $atomic_count > 0 ? $atomic_count : 'Pending'; ?></span></td>
                    <td class="kh_scheduled column-kh_scheduled" data-col="12" data-colname="Scheduled"><?php echo $scheduled_date; ?></td>
                    <td class="date column-date" data-col="13" data-colname="Date"><?php echo esc_html( $article->created_at ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column" data-col="0"><input id="cb-select-all-2" type="checkbox"><label for="cb-select-all-2"><span class="screen-reader-text">Select All</span></label></td>
                        <th scope="col" class="manage-column column-title column-primary sortable desc" abbr="Title" data-col="1"><a href="#"><span>Title</span></a></th>
                        <th scope="col" class="manage-column column-status" data-col="2">Status</th>
                        <th scope="col" class="manage-column column-khm_schema" data-col="3">Schema</th>
                        <th scope="col" class="manage-column column-kh_author" data-col="4">Author</th>
                        <th scope="col" class="manage-column column-taxonomy-content_type" data-col="5">Content Type</th>
                        <th scope="col" class="manage-column column-kh_seo_score" data-col="6">SEO</th>
                        <th scope="col" class="manage-column column-kh_geo_score" data-col="7">GEO</th>
                        <th scope="col" class="manage-column column-kh_smma" data-col="8">SMMA</th>
                        <th scope="col" class="manage-column column-kh_sponsor" data-col="9">Sponsor Commentary</th>
                        <th scope="col" class="manage-column column-kh_distribution" data-col="10">Distribution</th>
                        <th scope="col" class="manage-column column-kh_atomic" data-col="11">Atomic</th>
                        <th scope="col" class="manage-column column-kh_scheduled" data-col="12">Scheduled</th>
                        <th scope="col" class="manage-column column-date sorted desc" aria-sort="descending" abbr="Date" data-col="13"><a href="#"><span>Date</span></a></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Distribution Modals -->
        <?php foreach ( $articles as $article ): 
            $alloc_service2 = class_exists( 'KH\\Editorial\\Services\\AllocationService' ) ? new \KH\Editorial\Services\AllocationService() : null;
            $allocations2 = $alloc_service2 ? $alloc_service2->get_allocation_status( $article->id ) : [];
            $allocated_ids2 = [];
            foreach ( $allocations2 as $alloc2 ) {
                if ( $alloc2['allocated'] ) {
                    $allocated_ids2[] = (int) $alloc2['blog_id'];
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
                    <?php foreach ( $allocations2 as $site2 ): 
                        $blog_id2  = (int) $site2['blog_id'];
                        $disabled2 = in_array( $blog_id2, $allocated_ids2, true );
                        $status2   = $disabled2
                            ? '<span style="color: #00a32a; font-size: 12px;">' . esc_html__( 'Already distributed', 'kh-content-registry' ) . '</span>'
                            : '';
                    ?>
                        <label class="kh-allocation-site-row"
                               style="display: flex; align-items: center; padding: 6px 0; border-bottom: 1px solid #f0f0f1; cursor: <?php echo $disabled2 ? 'default' : 'pointer'; ?>;"
                               data-slug="<?php echo esc_attr( $site2['slug'] ); ?>"
                               data-blog-id="<?php echo esc_attr( $blog_id2 ); ?>">
                            <input type="checkbox"
                                   class="kh-allocation-checkbox"
                                   value="<?php echo esc_attr( $site2['slug'] ); ?>"
                                   data-blog-id="<?php echo esc_attr( $blog_id2 ); ?>"
                                   <?php echo $disabled2 ? ' disabled' : ''; ?>
                                   style="margin-right: 8px;">
                            <span style="flex: 1; font-size: 13px;"><?php echo esc_html( $site2['label'] ); ?></span>
                            <span class="kh-allocation-status" style="font-size: 12px;"><?php echo $status2; ?></span>
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
            /* Filter Bar Styles */
            .khcr-filter-bar { position: relative; }
            .khcr-filter-dropdown a:hover { background: #f0f0f1; }
            .khcr-filter-chip {
                display: inline-flex; align-items: center; gap: 4px;
                background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px;
                padding: 2px 8px; font-size: 12px; line-height: 1.6;
            }
            .khcr-filter-chip .khcr-chip-remove {
                cursor: pointer; color: #b32d2e; font-weight: bold; font-size: 14px; line-height: 1;
            }
            .khcr-filter-chip .khcr-chip-remove:hover { color: #d63638; }
            .khcr-filter-chip.include-chip { border-left: 3px solid #28a745; }
            .khcr-filter-chip.exclude-chip { border-left: 3px solid #dc3545; }
            .khcr-row.khcr-hidden { display: none; }
            /* Override fixed table layout so hidden columns redistribute width */
            .khcr-main-table { table-layout: auto !important; }
            .khcr-main-table.hide-col-2 th[data-col="2"],
            .khcr-main-table.hide-col-2 td[data-col="2"],
            .khcr-main-table.hide-col-3 th[data-col="3"],
            .khcr-main-table.hide-col-3 td[data-col="3"],
            .khcr-main-table.hide-col-4 th[data-col="4"],
            .khcr-main-table.hide-col-4 td[data-col="4"],
            .khcr-main-table.hide-col-5 th[data-col="5"],
            .khcr-main-table.hide-col-5 td[data-col="5"],
            .khcr-main-table.hide-col-6 th[data-col="6"],
            .khcr-main-table.hide-col-6 td[data-col="6"],
            .khcr-main-table.hide-col-7 th[data-col="7"],
            .khcr-main-table.hide-col-7 td[data-col="7"],
            .khcr-main-table.hide-col-8 th[data-col="8"],
            .khcr-main-table.hide-col-8 td[data-col="8"],
            .khcr-main-table.hide-col-9 th[data-col="9"],
            .khcr-main-table.hide-col-9 td[data-col="9"],
            .khcr-main-table.hide-col-10 th[data-col="10"],
            .khcr-main-table.hide-col-10 td[data-col="10"],
            .khcr-main-table.hide-col-11 th[data-col="11"],
            .khcr-main-table.hide-col-11 td[data-col="11"],
            .khcr-main-table.hide-col-12 th[data-col="12"],
            .khcr-main-table.hide-col-12 td[data-col="12"],
            .khcr-main-table.hide-col-13 th[data-col="13"],
            .khcr-main-table.hide-col-13 td[data-col="13"] {
                display: none;
            }
        </style>
        
        <script>
        (function ($) {
            'use strict';

            // ============================================================
            // FILTER SYSTEM
            // ============================================================
            var filterKey = 'khcr_filters';
            var colVisKey = 'khcr_col_visibility';
            var filters = [];
            var columnVisibility = {};

            // Column definitions
            var columnDefs = {
                'status':        { label: 'Status', type: 'categorical', options: ['Summary','Framework','Draft','Scheduled','Live'] },
                'schema':        { label: 'Schema', type: 'categorical', options: ['article'] },
                'author':        { label: 'Author', type: 'categorical', options: ['system'] },
                'content-type':  { label: 'Content Type', type: 'categorical', options: ['word'] },
                'seo':           { label: 'SEO', type: 'numeric' },
                'geo':           { label: 'GEO', type: 'numeric' },
                'smma':          { label: 'SMMA', type: 'categorical', options: ['Awaiting','Scheduled','Distributed'] },
                'sponsor':       { label: 'Sponsor Commentary', type: 'categorical', options: ['yes','none'] },
                'distribution':  { label: 'Distribution', type: 'numeric' },
                'atomic':        { label: 'Atomic', type: 'numeric' },
                'scheduled':     { label: 'Scheduled', type: 'scheduled', options: ['Awaiting'] },
                'date':          { label: 'Date', type: 'date' }
            };

            // Load saved state
            function loadFilters() {
                try {
                    var raw = sessionStorage.getItem(filterKey);
                    filters = raw ? JSON.parse(raw) : [];
                } catch(e) { filters = []; }
            }
            function saveFilters() {
                sessionStorage.setItem(filterKey, JSON.stringify(filters));
            }
            function loadColVis() {
                try {
                    var raw = localStorage.getItem(colVisKey);
                    columnVisibility = raw ? JSON.parse(raw) : {};
                } catch(e) { columnVisibility = {}; }
            }
            function saveColVis() {
                localStorage.setItem(colVisKey, JSON.stringify(columnVisibility));
            }

            loadFilters();
            loadColVis();

            // Apply column visibility on load
            function applyColVis() {
                var $table = $('.khcr-main-table');
                // Remove all hide classes from columns 2-13
                for (var i = 2; i <= 13; i++) {
                    $table.removeClass('hide-col-' + i);
                }
                // Apply saved visibility
                $('.khcr-col-toggle').each(function() {
                    var col = $(this).data('col');
                    var checked = $(this).prop('checked');
                    if (!checked && col >= 2) {
                        $table.addClass('hide-col-' + col);
                    }
                });
            }

            // Init column toggle checkboxes from localStorage
            $.each(columnVisibility, function(col, visible) {
                $('.khcr-col-toggle[data-col="' + col + '"]').prop('checked', visible);
            });
            applyColVis();

            // ============================================================
            // CHIP RENDERING
            // ============================================================
            function renderChips() {
                var $container = $('#khcr-active-chips');
                $container.empty();

                if (filters.length === 0) {
                    $('.khcr-clear-filters-btn').hide();
                    $('.khcr-filter-count').hide();
                } else {
                    $('.khcr-clear-filters-btn').show();
                    $('.khcr-filter-count').text(filters.length + ' active filter(s)').show();
                }

                $.each(filters, function(i, f) {
                    var colDef = columnDefs[f.column] || { label: f.column };
                    var chipClass = f.mode === 'include' ? 'include-chip' : 'exclude-chip';
                    var modeLabel = f.mode === 'include' ? 'Show' : 'Exclude';
                    var valueLabel = '';

                    if (f.type === 'categorical' || f.type === 'scheduled') {
                        valueLabel = f.values.join(', ');
                    } else if (f.type === 'numeric') {
                        valueLabel = f.operator + ' ' + f.value;
                    } else if (f.type === 'date') {
                        if (f.operator === 'between') {
                            valueLabel = f.value1 + ' - ' + f.value2;
                        } else {
                            valueLabel = f.operator + ' ' + f.value;
                        }
                    }

                    var $chip = $('<span class="khcr-filter-chip ' + chipClass + '">'
                        + '<strong>' + colDef.label + '</strong> · ' + modeLabel + ': ' + valueLabel
                        + ' <span class="khcr-chip-remove" data-index="' + i + '">&times;</span>'
                        + '</span>');
                    $container.append($chip);
                });
            }

            function applyFilters() {
                var $rows = $('.khcr-row');
                var includeFilters = $.grep(filters, function(f) { return f.mode === 'include'; });
                var excludeFilters = $.grep(filters, function(f) { return f.mode === 'exclude'; });

                $rows.each(function() {
                    var $row = $(this);
                    var visible = true;

                    // Check include filters (ALL must match, AND logic)
                    if (includeFilters.length > 0) {
                        visible = includeFilters.every(function(f) {
                            return rowMatchesFilter($row, f);
                        });
                    }

                    // Check exclude filters (ANY match hides the row)
                    if (visible && excludeFilters.length > 0) {
                        var excluded = excludeFilters.some(function(f) {
                            return rowMatchesFilter($row, f);
                        });
                        if (excluded) visible = false;
                    }

                    $row.toggleClass('khcr-hidden', !visible);
                });

                renderChips();
                saveFilters();
            }

            function rowMatchesFilter($row, f) {
                var attrName = 'data-' + f.column;
                var rawVal = $row.attr(attrName);
                if (typeof rawVal === 'undefined') return false;

                if (f.type === 'categorical' || f.type === 'scheduled') {
                    // OR within values
                    return f.values.some(function(v) {
                        return rawVal.trim().toLowerCase() === v.trim().toLowerCase();
                    });
                }

                if (f.type === 'numeric') {
                    var numVal = parseFloat(rawVal);
                    if (isNaN(numVal)) return false;
                    var cmpVal = parseFloat(f.value);
                    if (isNaN(cmpVal)) return false;
                    switch (f.operator) {
                        case 'gt': return numVal > cmpVal;
                        case 'gte': return numVal >= cmpVal;
                        case 'eq': return numVal === cmpVal;
                        case 'lte': return numVal <= cmpVal;
                        case 'lt': return numVal < cmpVal;
                        default: return false;
                    }
                }

                if (f.type === 'date') {
                    if (f.operator === 'between') {
                        if (!f.value1 || !f.value2) return false;
                        return rawVal >= f.value1 && rawVal <= f.value2;
                    }
                    if (!f.value) return false;
                    switch (f.operator) {
                        case 'after': return rawVal >= f.value;
                        case 'before': return rawVal <= f.value;
                        case 'on': return rawVal.substring(0,10) === f.value.substring(0,10);
                        default: return false;
                    }
                }

                return false;
            }

            // ============================================================
            // ADD FILTER DROPDOWN
            // ============================================================
            $(document).on('click', '.khcr-add-filter-btn', function(e) {
                e.stopPropagation();
                $('.khcr-filter-dropdown').toggle();
                $('.khcr-columns-dropdown').hide();
            });

            $(document).on('click', '.khcr-filter-option', function(e) {
                e.preventDefault();
                $('.khcr-filter-dropdown').hide();
                var column = $(this).data('column');
                showFilterConfig(column);
            });

            // ============================================================
            // FILTER CONFIG POPOVER
            // ============================================================
            var $filterPopover = null;

            function showFilterConfig(column) {
                var colDef = columnDefs[column];
                if (!colDef) return;

                if ($filterPopover) $filterPopover.remove();

                var template = $('#khcr-filter-config-template').html();
                $filterPopover = $('<div class="khcr-filter-popover" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); z-index: 1000; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 5px 25px rgba(0,0,0,0.2);"></div>');
                $filterPopover.html(template);

                $filterPopover.find('.khcr-col-label').text(colDef.label);

                var $valuesContainer = $filterPopover.find('.khcr-filter-values');

                if (colDef.type === 'categorical') {
                    $.each(colDef.options, function(i, opt) {
                        $valuesContainer.append(
                            '<label style="display: block; margin-bottom: 3px; font-size: 13px;">' +
                            '<input type="checkbox" class="khcr-cat-val" value="' + opt + '"> ' + opt +
                            '</label>'
                        );
                    });
                } else if (colDef.type === 'numeric') {
                    $valuesContainer.append(
                        '<select class="khcr-num-op" style="margin-right: 6px;">' +
                        '<option value="gt">></option>' +
                        '<option value="gte">>=</option>' +
                        '<option value="eq">=</option>' +
                        '<option value="lte"><=</option>' +
                        '<option value="lt"><</option>' +
                        '</select>' +
                        '<input type="number" class="khcr-num-val" style="width: 80px;" placeholder="Value">'
                    );
                } else if (colDef.type === 'scheduled') {
                    $valuesContainer.append(
                        '<label style="display: block; margin-bottom: 3px; font-size: 13px;">' +
                        '<input type="checkbox" class="khcr-cat-val" value="Awaiting"> Awaiting</label>'
                    );
                    $valuesContainer.append('<p style="margin: 6px 0; font-size: 12px; color: #646970;">- or date -</p>');
                    $valuesContainer.append(
                        '<select class="khcr-date-op" style="margin-right: 6px;">' +
                        '<option value="before">Before</option>' +
                        '<option value="after">After</option>' +
                        '<option value="on">On</option>' +
                        '</select>' +
                        '<input type="date" class="khcr-date-val" style="width: 145px;">'
                    );
                } else if (colDef.type === 'date') {
                    $valuesContainer.append(
                        '<select class="khcr-date-op" style="margin-bottom: 6px;">' +
                        '<option value="after">After</option>' +
                        '<option value="before">Before</option>' +
                        '<option value="on">On</option>' +
                        '<option value="between">Between</option>' +
                        '</select><br>' +
                        '<input type="date" class="khcr-date-val1" style="width: 145px; margin-bottom: 4px;"><br>' +
                        '<input type="date" class="khcr-date-val2" style="width: 145px; display: none;" placeholder="End date">'
                    );
                }

                var $backdrop = $('<div class="khcr-popover-backdrop" style="position: fixed; top:0;left:0;right:0;bottom:0; z-index:999; background:rgba(0,0,0,0.3);"></div>');
                $('body').append($backdrop);
                $('body').append($filterPopover);

                $filterPopover.on('change', '.khcr-date-op', function() {
                    var op = $(this).val();
                    $filterPopover.find('.khcr-date-val2').toggle(op === 'between');
                });

                $filterPopover.on('click', '.khcr-apply-filter', function() {
                    var mode = $filterPopover.find('input[name="khcr-mode"]:checked').val();
                    var filterObj = { column: column, mode: mode, type: colDef.type };

                    if (colDef.type === 'categorical') {
                        var vals = $filterPopover.find('.khcr-cat-val:checked').map(function() { return $(this).val(); }).get();
                        if (vals.length === 0) { closePopover(); return; }
                        filterObj.values = vals;
                    } else if (colDef.type === 'numeric') {
                        var op = $filterPopover.find('.khcr-num-op').val();
                        var val = $filterPopover.find('.khcr-num-val').val();
                        if (!val) { closePopover(); return; }
                        filterObj.operator = op;
                        filterObj.value = val;
                    } else if (colDef.type === 'scheduled') {
                        var catVals = $filterPopover.find('.khcr-cat-val:checked').map(function() { return $(this).val(); }).get();
                        var dateOp = $filterPopover.find('.khcr-date-op').val();
                        var dateVal = $filterPopover.find('.khcr-date-val').val();
                        if (catVals.length > 0) {
                            filterObj.type = 'categorical';
                            filterObj.values = catVals;
                        } else if (dateVal) {
                            filterObj.type = 'date';
                            filterObj.operator = dateOp;
                            filterObj.value = dateVal;
                        } else { closePopover(); return; }
                    } else if (colDef.type === 'date') {
                        var dOp = $filterPopover.find('.khcr-date-op').val();
                        if (dOp === 'between') {
                            var d1 = $filterPopover.find('.khcr-date-val1').val();
                            var d2 = $filterPopover.find('.khcr-date-val2').val();
                            if (!d1 || !d2) { closePopover(); return; }
                            filterObj.operator = 'between';
                            filterObj.value1 = d1;
                            filterObj.value2 = d2;
                        } else {
                            var dv = $filterPopover.find('.khcr-date-val1').val();
                            if (!dv) { closePopover(); return; }
                            filterObj.operator = dOp;
                            filterObj.value = dv;
                        }
                    }

                    filters.push(filterObj);
                    closePopover();
                    applyFilters();
                });

                $filterPopover.on('click', '.khcr-cancel-filter', closePopover);
                $backdrop.on('click', closePopover);
            }

            function closePopover() {
                if ($filterPopover) { $filterPopover.remove(); $filterPopover = null; }
                $('.khcr-popover-backdrop').remove();
            }

            // ============================================================
            // REMOVE FILTER CHIP
            // ============================================================
            $(document).on('click', '.khcr-chip-remove', function() {
                var idx = $(this).data('index');
                filters.splice(idx, 1);
                applyFilters();
            });

            // Clear all filters
            $(document).on('click', '.khcr-clear-filters-btn', function() {
                filters = [];
                applyFilters();
            });

            // ============================================================
            // COLUMN VISIBILITY
            // ============================================================
            $(document).on('click', '.khcr-columns-btn', function(e) {
                e.stopPropagation();
                $('.khcr-columns-dropdown').toggle();
                $('.khcr-filter-dropdown').hide();
            });

            $(document).on('change', '.khcr-col-toggle', function() {
                var col = $(this).data('col');
                var checked = $(this).prop('checked');
                columnVisibility[col] = checked;
                saveColVis();

                var $table = $('.khcr-main-table');
                if (checked) {
                    $table.removeClass('hide-col-' + col);
                } else {
                    $table.addClass('hide-col-' + col);
                }
            });

            // ============================================================
            // SAVED VIEWS
            // ============================================================
            var viewsKey = 'khcr_saved_views';
            var activeViewName = null;

            function getSavedViews() {
                try {
                    var raw = localStorage.getItem(viewsKey);
                    return raw ? JSON.parse(raw) : [];
                } catch(e) { return []; }
            }

            function saveViews(views) {
                localStorage.setItem(viewsKey, JSON.stringify(views));
            }

            function getCurrentSnapshot() {
                return {
                    filters: JSON.parse(JSON.stringify(filters)),
                    columnVisibility: JSON.parse(JSON.stringify(columnVisibility))
                };
            }

            function applySnapshot(snapshot) {
                // Apply filters
                filters = snapshot.filters || [];
                saveFilters();
                applyFilters();

                // Apply column visibility
                columnVisibility = snapshot.columnVisibility || {};
                saveColVis();
                // Sync checkboxes
                $.each(columnVisibility, function(col, visible) {
                    var $cb = $('.khcr-col-toggle[data-col="' + col + '"]');
                    $cb.prop('checked', visible);
                });
                applyColVis();
            }

            function showActiveViewLabel(name) {
                activeViewName = name;
                $('.khcr-active-view-label').text('View: ' + name).show();
                $('.khcr-views-btn').text('Saved Views: ' + name);
            }

            function clearActiveViewLabel() {
                activeViewName = null;
                $('.khcr-active-view-label').hide();
                $('.khcr-views-btn').text('Saved Views');
            }

            function renderViewList() {
                var $list = $('.khcr-views-list');
                var $empty = $('.khcr-views-empty');
                var views = getSavedViews();
                $list.empty();

                if (views.length === 0) {
                    $empty.show();
                } else {
                    $empty.hide();
                    $.each(views, function(i, v) {
                        var $item = $('<div class="khcr-view-item" style="display: flex; align-items: center; padding: 6px 12px; cursor: pointer;">'
                            + '<span class="khcr-view-item-name" style="flex: 1; font-size: 13px;">' + escHtml(v.name) + '</span>'
                            + '</div>');
                        $item.on('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            $('.khcr-views-dropdown').hide();
                            applySnapshot(v.snapshot);
                            showActiveViewLabel(v.name);
                        });
                        $list.append($item);
                    });
                }
            }

            function escHtml(str) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            function escAttr(str) {
                return String(str).replace(/"/g, '"').replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>');
            }

            // Check if current state matches a saved view
            function checkActiveView() {
                var snapshot = getCurrentSnapshot();
                var views = getSavedViews();
                var matched = null;
                $.each(views, function(i, v) {
                    if (JSON.stringify(v.snapshot) === JSON.stringify(snapshot)) {
                        matched = v.name;
                        return false; // break
                    }
                });
                if (matched) {
                    showActiveViewLabel(matched);
                } else {
                    clearActiveViewLabel();
                }
            }

            // Hook into applyFilters and col vis to update active view status
            var originalApplyFilters = applyFilters;
            applyFilters = function() {
                originalApplyFilters();
                checkActiveView();
            };

            var originalColVisChange = null;
            $(document).on('change', '.khcr-col-toggle', function() {
                checkActiveView();
            });

            // Views dropdown toggle
            $(document).on('click', '.khcr-views-btn', function(e) {
                e.stopPropagation();
                renderViewList();
                $('.khcr-views-dropdown').toggle();
                $('.khcr-filter-dropdown').hide();
                $('.khcr-columns-dropdown').hide();
            });

            // Save current view
            $(document).on('click', '.khcr-views-save', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.khcr-views-dropdown').hide();
                var name = prompt('Enter a name for this view:', activeViewName || '');
                if (!name || !name.trim()) return;
                name = name.trim();
                var views = getSavedViews();
                var snapshot = getCurrentSnapshot();
                // Remove existing view with same name (overwrite)
                views = $.grep(views, function(v) { return v.name !== name; });
                views.push({ name: name, snapshot: snapshot });
                // Limit to 20
                if (views.length > 20) views = views.slice(views.length - 20);
                saveViews(views);
                showActiveViewLabel(name);
                renderViewList();
            });

            // Manage views
            $(document).on('click', '.khcr-views-manage', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.khcr-views-dropdown').hide();
                showManageViewsModal();
            });

            function showManageViewsModal() {
                var views = getSavedViews();
                var html = '<div class="khcr-manage-views-backdrop" style="position: fixed; top:0;left:0;right:0;bottom:0; z-index:999; background:rgba(0,0,0,0.3);"></div>';
                html += '<div class="khcr-manage-views-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); z-index: 1000; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 5px 25px rgba(0,0,0,0.2); padding: 20px; min-width: 320px; max-width: 420px;">';
                html += '<h3 style="margin: 0 0 12px 0;">Manage Saved Views</h3>';
                if (views.length === 0) {
                    html += '<p style="color: #646970; font-size: 13px;">No saved views.</p>';
                } else {
                    html += '<div style="max-height: 300px; overflow-y: auto; margin-bottom: 12px;">';
                    $.each(views, function(i, v) {
                        html += '<div style="display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f1;">'
                            + '<span class="khcr-view-name" style="flex: 1; font-size: 13px;">' + escHtml(v.name) + '</span>'
                            + '<button type="button" class="button button-small khcr-delete-view" style="color: #b32d2e; border-color: #b32d2e;">Delete</button>'
                            + '</div>';
                    });
                    html += '</div>';
                }
                html += '<button type="button" class="button khcr-close-manage">Close</button>';
                html += '</div>';

                var $modal = $(html);
                $('body').append($modal);

                $modal.find('.khcr-delete-view').each(function() {
                    var $btn = $(this);
                    var viewName = $btn.closest('div').find('.khcr-view-name').text();
                    $btn.on('click', function() {
                        var views = getSavedViews();
                        views = $.grep(views, function(v) { return v.name !== viewName; });
                        saveViews(views);
                        if (activeViewName === viewName) clearActiveViewLabel();
                        renderViewList();
                        $modal.remove();
                        showManageViewsModal(); // Refresh
                    });
                });

                $modal.find('.khcr-close-manage, .khcr-manage-views-backdrop').on('click', function() {
                    $modal.remove();
                });
            }

            // ============================================================
            // CLOSE DROPDOWNS ON OUTSIDE CLICK
            // ============================================================
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.khcr-add-filter-wrapper').length) {
                    $('.khcr-filter-dropdown').hide();
                }
                if (!$(e.target).closest('.khcr-columns-wrapper').length) {
                    $('.khcr-columns-dropdown').hide();
                }
                if (!$(e.target).closest('.khcr-views-wrapper').length) {
                    $('.khcr-views-dropdown').hide();
                }
            });

            // ============================================================
            // DISTRIBUTION LOGIC (existing)
            // ============================================================
            $(document).on('change', '.kh-allocation-checkbox', function () {
                var $modal = $(this).closest('.kh-distribute-modal-content');
                var checked = $modal.find('.kh-allocation-checkbox:checked:not(:disabled)').length;
                $modal.find('.kh-allocate-clone-rewrite, .kh-allocate-clone-only').prop('disabled', checked === 0);
            });
            
            $(document).on('click', '.kh-allocate-clone-rewrite', function () {
                doDistribute($(this), true);
            });
            
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
                        
                        if (typeof tb_remove === 'function') {
                            tb_remove();
                        }
                        
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
        $articles = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}content_registry WHERE parent_post_id IS NULL OR parent_post_id = 0 OR article_status IN ('Summary','Framework') ORDER BY created_at DESC" );
        foreach ( $articles as $article ) {
            $article->seo_metadata = isset($article->seo_metadata) ? json_decode($article->seo_metadata, true) : [];
            $article->geo_flags = isset($article->geo_flags) ? json_decode($article->geo_flags, true) : [];
            $article->smma_flags = isset($article->smma_flags) ? json_decode($article->smma_flags, true) : [];
        }
        return $articles;
    }
    
    function khcr_handle_post_new_from_registry() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== 'post' ) {
            return;
        }
        
        $registry_id = isset( $_GET['registry_id'] ) ? intval( $_GET['registry_id'] ) : 0;
        if ( ! $registry_id ) {
            return;
        }
        
        $service = \KH\ContentRegistry\Services\ContentRegistryService::instance();
        $article = $service->get_article_by_id( $registry_id );
        if ( ! $article ) {
            return;
        }
        
        if ( ! empty( $article->wp_post_id ) ) {
            // Posts are now all on blog 1, so no switch_to_blog needed
            $edit_url = get_edit_post_link( $article->wp_post_id, 'raw' );
            if ( $edit_url ) {
                wp_redirect( $edit_url );
                exit;
            }
            return;
        }
        
        $target_blog_id = $article->target_blog_id ?: get_current_blog_id();
        $post_title = $article->title;
        $post_content = '';
        $post_excerpt = '';
        
        if ( $article->article_status === 'Summary' ) {
            $synopsis = json_decode( $article->excerpt, true );
            if ( is_array( $synopsis ) ) {
                $post_content = '<h2>Summary</h2>' . "\n";
                $post_content .= '<p>' . esc_html( $synopsis['summary'] ?? '' ) . '</p>' . "\n";
                if ( ! empty( $synopsis['key_points'] ) ) {
                    $post_content .= '<h3>Key Points</h3>' . "\n<ul>\n";
                    foreach ( $synopsis['key_points'] as $point ) {
                        $post_content .= '<li>' . esc_html( $point ) . '</li>' . "\n";
                    }
                    $post_content .= '</ul>' . "\n";
                }
            }
            $post_excerpt = $synopsis['summary'] ?? '';
        } elseif ( $article->article_status === 'Framework' ) {
            $framework = json_decode( $article->content_body, true );
            if ( is_array( $framework ) ) {
                $post_title = $framework['title'] ?? $post_title;
                if ( ! empty( $framework['article_idea'] ) ) {
                    $post_content .= '<h2>Article Idea</h2>' . "\n";
                    $post_content .= wp_kses_post( $framework['article_idea'] ) . "\n";
                }
                if ( ! empty( $framework['overview'] ) ) {
                    $post_content .= '<h2>Overview</h2>' . "\n";
                    $post_content .= wp_kses_post( $framework['overview'] ) . "\n";
                }
                if ( ! empty( $framework['context'] ) ) {
                    $post_content .= '<h2>Context</h2>' . "\n";
                    $post_content .= wp_kses_post( $framework['context'] ) . "\n";
                }
                if ( ! empty( $framework['application'] ) ) {
                    $post_content .= '<h2>Application</h2>' . "\n";
                    $post_content .= wp_kses_post( $framework['application'] ) . "\n";
                }
                if ( ! empty( $framework['writer_guidance'] ) ) {
                    $post_content .= '<h2>Writer Guidance</h2>' . "\n";
                    $post_content .= wp_kses_post( $framework['writer_guidance'] ) . "\n";
                }
            }
            $post_excerpt = $framework['overview'] ?? '';
        }
        
        // Posts are always created on the current blog (blog 1 for the admin context)
        // target_blog_id is preserved in the registry table for routing purposes
        $post_data = [
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_excerpt' => wp_trim_words( $post_excerpt, 55 ),
            'post_status'  => 'draft',
            'post_type'    => 'post',
            'post_author'  => get_current_user_id() ?: 1,
        ];
        
        $wp_post_id = wp_insert_post( $post_data, true );
        
        if ( is_wp_error( $wp_post_id ) ) {
            return;
        }
        
        update_post_meta( $wp_post_id, '_registry_id', $registry_id );
        $edit_url = get_edit_post_link( $wp_post_id, 'raw' );
        
        global $wpdb;
        $wpdb->update(
            $wpdb->base_prefix . 'content_registry',
            [
                'wp_post_id'     => $wp_post_id,
                'article_status' => 'Draft',
                'updated_at'     => current_time( 'mysql' ),
            ],
            [ 'id' => $registry_id ]
        );
        
        if ( $edit_url ) {
            wp_redirect( $edit_url );
            exit;
        }
    }
}

add_action( 'load-post-new.php', 'khcr_handle_post_new_from_registry' );
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

// Register ACF blocks for the content registry lifecycle.
\KH\ContentRegistry\Blocks\BlockRegistrar::init();

// Register REST endpoints for inserting blocks into posts.
\KH\ContentRegistry\API\BlockEndpoints::register_routes();

// Register REST endpoints for manual status override in Editor.
add_action( 'rest_api_init', [ '\KH\ContentRegistry\API\StatusEndpoint', 'register' ] );
add_action( 'rest_api_init', [ '\KH\ContentRegistry\API\StatusEndpoint', 'register_read_endpoint' ] );

 // Enqueue Editor Extensions
 add_action( 'enqueue_block_editor_assets', function() {
     wp_enqueue_script(
         'kh-content-registry-editor-status',
         plugin_dir_url( __FILE__ ) . 'assets/editor-status.js',
         [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-data' ],
         '1.0.0',
         true
     );
     
     wp_enqueue_script(
         'kh-content-registry-framework-ai',
         plugin_dir_url( __FILE__ ) . 'assets/framework-ai.js',
         [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-data' ],
         '1.0.0',
         true
     );
     
     // Enqueue commentary block JS for Gutenberg editor
     wp_enqueue_script(
         'kh-content-registry-commentary-block',
         plugin_dir_url( __FILE__ ) . 'assets/commentary-block.js',
         [ 'wp-blocks', 'wp-i18n', 'wp-components', 'wp-data', 'wp-element', 'wp-block-editor' ],
         '1.0.0',
         true
     );
 } );
