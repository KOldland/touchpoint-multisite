<?php
/**
 * Entity List Admin Template
 * 
 * Display list of entities with search, filtering, and management options.
 * Part of the Entity & Glossary Registry system.
 * 
 * @package KHM_SEO\GEO\Templates
 * @since 2.0.0
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Current user capabilities
$can_create = current_user_can( 'edit_posts' );
$can_delete = current_user_can( 'delete_posts' );
?>

<div class="wrap khm-geo-entities">
    <h1 class="wp-heading-inline">
        <?php _e( 'Entity Dictionary', 'khm-seo' ); ?>
        <?php if ( $can_create ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'action', 'new' ) ); ?>" class="page-title-action">
                <?php _e( 'Add New Entity', 'khm-seo' ); ?>
            </a>
        <?php endif; ?>
    </h1>
    
    <hr class="wp-header-end">
    
    <!-- Statistics Dashboard -->
    <div class="khm-geo-stats-grid">
        <div class="khm-stat-card">
            <div class="khm-stat-number"><?php echo number_format( $stats['total_entities'] ); ?></div>
            <div class="khm-stat-label"><?php _e( 'Total Entities', 'khm-seo' ); ?></div>
        </div>
        <div class="khm-stat-card">
            <div class="khm-stat-number"><?php echo number_format( $stats['active_entities'] ); ?></div>
            <div class="khm-stat-label"><?php _e( 'Active Entities', 'khm-seo' ); ?></div>
        </div>
        <div class="khm-stat-card">
            <div class="khm-stat-number"><?php echo number_format( $stats['total_aliases'] ); ?></div>
            <div class="khm-stat-label"><?php _e( 'Total Aliases', 'khm-seo' ); ?></div>
        </div>
        <div class="khm-stat-card khm-stat-warning">
            <div class="khm-stat-number"><?php echo number_format( $stats['deprecated_entities'] ); ?></div>
            <div class="khm-stat-label"><?php _e( 'Deprecated', 'khm-seo' ); ?></div>
        </div>
    </div>
    
    <!-- Search and Filter Form -->
    <div class="khm-geo-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="khm-seo-entities">
            
            <div class="khm-filter-row">
                <div class="khm-filter-group">
                    <label for="search"><?php _e( 'Search Entities', 'khm-seo' ); ?></label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo esc_attr( $search ); ?>" 
                           placeholder="<?php _e( 'Search by canonical name or alias...', 'khm-seo' ); ?>"
                           class="regular-text">
                </div>
                
                <div class="khm-filter-group">
                    <label for="type"><?php _e( 'Type', 'khm-seo' ); ?></label>
                    <select id="type" name="type">
                        <option value=""><?php _e( 'All Types', 'khm-seo' ); ?></option>
                        <option value="Organization" <?php selected( $type_filter, 'Organization' ); ?>><?php _e( 'Organization', 'khm-seo' ); ?></option>
                        <option value="Product" <?php selected( $type_filter, 'Product' ); ?>><?php _e( 'Product', 'khm-seo' ); ?></option>
                        <option value="Technology" <?php selected( $type_filter, 'Technology' ); ?>><?php _e( 'Technology', 'khm-seo' ); ?></option>
                        <option value="Metric" <?php selected( $type_filter, 'Metric' ); ?>><?php _e( 'Metric', 'khm-seo' ); ?></option>
                        <option value="Acronym" <?php selected( $type_filter, 'Acronym' ); ?>><?php _e( 'Acronym', 'khm-seo' ); ?></option>
                        <option value="Term" <?php selected( $type_filter, 'Term' ); ?>><?php _e( 'Term', 'khm-seo' ); ?></option>
                        <option value="Person" <?php selected( $type_filter, 'Person' ); ?>><?php _e( 'Person', 'khm-seo' ); ?></option>
                        <option value="Place" <?php selected( $type_filter, 'Place' ); ?>><?php _e( 'Place', 'khm-seo' ); ?></option>
                        <option value="Thing" <?php selected( $type_filter, 'Thing' ); ?>><?php _e( 'Thing', 'khm-seo' ); ?></option>
                    </select>
                </div>
                
                <div class="khm-filter-group">
                    <label for="scope"><?php _e( 'Scope', 'khm-seo' ); ?></label>
                    <select id="scope" name="scope">
                        <option value=""><?php _e( 'All Scopes', 'khm-seo' ); ?></option>
                        <option value="global" <?php selected( $scope_filter, 'global' ); ?>><?php _e( 'Global', 'khm-seo' ); ?></option>
                        <option value="client" <?php selected( $scope_filter, 'client' ); ?>><?php _e( 'Client', 'khm-seo' ); ?></option>
                        <option value="site" <?php selected( $scope_filter, 'site' ); ?>><?php _e( 'Site', 'khm-seo' ); ?></option>
                    </select>
                </div>
                
                <div class="khm-filter-group">
                    <label for="status"><?php _e( 'Status', 'khm-seo' ); ?></label>
                    <select id="status" name="status">
                        <option value="active" <?php selected( $status_filter, 'active' ); ?>><?php _e( 'Active', 'khm-seo' ); ?></option>
                        <option value="deprecated" <?php selected( $status_filter, 'deprecated' ); ?>><?php _e( 'Deprecated', 'khm-seo' ); ?></option>
                        <option value=""><?php _e( 'All Statuses', 'khm-seo' ); ?></option>
                    </select>
                </div>
                
                <div class="khm-filter-group">
                    <label>&nbsp;</label>
                    <input type="submit" class="button" value="<?php _e( 'Filter', 'khm-seo' ); ?>">
                    <?php if ( ! empty( $search ) || ! empty( $type_filter ) || ! empty( $scope_filter ) || $status_filter !== 'active' ) : ?>
                        <a href="<?php echo esc_url( remove_query_arg( array( 'search', 'type', 'scope', 'status' ) ) ); ?>" 
                           class="button button-secondary"><?php _e( 'Clear', 'khm-seo' ); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Bulk Actions -->
    <?php if ( $can_delete ) : ?>
    <div class="khm-geo-bulk-actions">
        <form method="post" action="">
            <?php wp_nonce_field( 'khm_geo_bulk_action', 'khm_geo_bulk_nonce' ); ?>
            <div class="alignleft actions">
                <select name="bulk_action">
                    <option value=""><?php _e( 'Bulk Actions', 'khm-seo' ); ?></option>
                    <option value="activate"><?php _e( 'Activate', 'khm-seo' ); ?></option>
                    <option value="deprecate"><?php _e( 'Deprecate', 'khm-seo' ); ?></option>
                    <option value="delete"><?php _e( 'Delete', 'khm-seo' ); ?></option>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php _e( 'Apply', 'khm-seo' ); ?>">
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Entities Table -->
    <div class="khm-geo-table-container">
    <!-- Notification area for AJAX feedback -->
    <div id="khm-geo-notification" style="display:none;" class="notice notice-success is-dismissible"></div>
        <table class="wp-list-table widefat fixed striped khm-geo-entities-table">
            <thead>
                <tr>
                    <?php if ( $can_delete ) : ?>
                    <td id="cb" class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1"><?php _e( 'Select All', 'khm-seo' ); ?></label>
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <?php endif; ?>
                    <th scope="col" class="manage-column column-canonical column-primary">
                        <span><?php _e( 'Canonical Name', 'khm-seo' ); ?></span>
                    </th>
                    <th scope="col" class="manage-column column-type">
                        <span><?php _e( 'Type', 'khm-seo' ); ?></span>
                    </th>
                    <th scope="col" class="manage-column column-scope">
                        <span><?php _e( 'Scope', 'khm-seo' ); ?></span>
                    </th>
                    <th scope="col" class="manage-column column-aliases">
                        <span><?php _e( 'Aliases', 'khm-seo' ); ?></span>
                    </th>
                    <th scope="col" class="manage-column column-usage">
                        <span><?php _e( 'Usage', 'khm-seo' ); ?></span>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <span><?php _e( 'Status', 'khm-seo' ); ?></span>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <span><?php _e( 'Actions', 'khm-seo' ); ?></span>
                    </th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if ( empty( $entities ) ) : ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="<?php echo $can_delete ? '7' : '6'; ?>">
                            <?php _e( 'No entities found.', 'khm-seo' ); ?>
                            <?php if ( $can_create ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'action', 'new' ) ); ?>" class="button button-primary">
                                    <?php _e( 'Create your first entity', 'khm-seo' ); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $entities as $entity ) : ?>
                        <tr id="entity-<?php echo esc_attr( $entity->id ); ?>" 
                            class="khm-entity-row <?php echo $entity->status === 'deprecated' ? 'khm-deprecated' : ''; ?>">
                            
                            <?php if ( $can_delete ) : ?>
                            <th scope="row" class="check-column">
                                <input id="cb-select-<?php echo esc_attr( $entity->id ); ?>" 
                                       type="checkbox" 
                                       name="entity_ids[]" 
                                       value="<?php echo esc_attr( $entity->id ); ?>">
                                <label for="cb-select-<?php echo esc_attr( $entity->id ); ?>">
                                    <span class="screen-reader-text"><?php _e( 'Select entity', 'khm-seo' ); ?></span>
                                </label>
                            </th>
                            <?php endif; ?>
                            
                            <td class="column-canonical column-primary" data-colname="<?php _e( 'Canonical Name', 'khm-seo' ); ?>">
                                <strong>
                                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'entity_id' => $entity->id ) ) ); ?>" 
                                       class="row-title" 
                                       aria-label="<?php echo esc_attr( sprintf( __( 'Edit "%s"', 'khm-seo' ), $entity->canonical ) ); ?>">
                                        <?php echo esc_html( $entity->canonical ); ?>
                                    </a>
                                </strong>
                                <?php if ( ! empty( $entity->definition ) ) : ?>
                                    <div class="khm-entity-definition">
                                        <?php echo esc_html( wp_trim_words( $entity->definition, 15 ) ); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'entity_id' => $entity->id ) ) ); ?>" 
                                           aria-label="<?php echo esc_attr( sprintf( __( 'Edit "%s"', 'khm-seo' ), $entity->canonical ) ); ?>">
                                            <?php _e( 'Edit', 'khm-seo' ); ?>
                                        </a>
                                    </span>
                                    <?php if ( $can_delete ) : ?>
                                        | <span class="delete">
                                            <a href="<?php echo esc_url( wp_nonce_url( 
                                                add_query_arg( array( 'action' => 'delete', 'entity_id' => $entity->id ) ), 
                                                'delete_entity_' . $entity->id 
                                            ) ); ?>" 
                                               class="submitdelete" 
                                               onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this entity?', 'khm-seo' ) ); ?>');"
                                               aria-label="<?php echo esc_attr( sprintf( __( 'Delete "%s"', 'khm-seo' ), $entity->canonical ) ); ?>">
                                                <?php _e( 'Delete', 'khm-seo' ); ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td class="column-type" data-colname="<?php _e( 'Type', 'khm-seo' ); ?>">
                                <span class="khm-entity-type khm-type-<?php echo esc_attr( strtolower( $entity->type ) ); ?>">
                                    <?php echo esc_html( $entity->type ); ?>
                                </span>
                            </td>
                            
                            <td class="column-scope" data-colname="<?php _e( 'Scope', 'khm-seo' ); ?>">
                                <span class="khm-entity-scope khm-scope-<?php echo esc_attr( $entity->scope ); ?>">
                                    <?php echo esc_html( ucfirst( $entity->scope ) ); ?>
                                </span>
                            </td>
                            
                            <td class="column-aliases" data-colname="<?php _e( 'Aliases', 'khm-seo' ); ?>">
                                <?php if ( !empty( $entity->aliases ) ) : ?>
                                    <ul class="khm-alias-list">
                                        <?php foreach ( $entity->aliases as $alias ) : ?>
                                            <li>
                                                <?php echo esc_html( $alias ); ?>
                                                <button type="button" class="khm-remove-alias" data-entity-id="<?php echo esc_attr( $entity->id ); ?>" data-alias="<?php echo esc_attr( $alias ); ?>">&times;</button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <span class="khm-no-aliases"><?php _e( 'No aliases', 'khm-seo' ); ?></span>
                                <?php endif; ?>
                                <button type="button" class="button khm-add-alias" data-entity-id="<?php echo esc_attr( $entity->id ); ?>">Add Alias</button>
                            </td>
                            
                            <td class="column-usage" data-colname="<?php _e( 'Usage', 'khm-seo' ); ?>">
                                <?php if ( isset( $entity->usage_count ) && $entity->usage_count > 0 ) : ?>
                                    <span class="khm-usage-count">
                                        <?php echo sprintf( _n( '%d post', '%d posts', $entity->usage_count, 'khm-seo' ), $entity->usage_count ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="khm-no-usage"><?php _e( 'Unused', 'khm-seo' ); ?></span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="column-status" data-colname="<?php _e( 'Status', 'khm-seo' ); ?>">
                                <?php if ( $entity->status === 'active' ) : ?>
                                    <span class="khm-status khm-status-active">
                                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                        <?php _e( 'Active', 'khm-seo' ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="khm-status khm-status-deprecated">
                                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                        <?php _e( 'Deprecated', 'khm-seo' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="column-actions" data-colname="<?php _e( 'Actions', 'khm-seo' ); ?>">
                                <div class="khm-action-buttons">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'entity_id' => $entity->id ) ) ); ?>" 
                                       class="button button-small" 
                                       title="<?php _e( 'Edit Entity', 'khm-seo' ); ?>">
                                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                        <span class="screen-reader-text"><?php _e( 'Edit', 'khm-seo' ); ?></span>
                                    </a>
                                    
                                    <button type="button" 
                                            class="button button-small khm-quick-validate" 
                                            data-entity-id="<?php echo esc_attr( $entity->id ); ?>"
                                            title="<?php _e( 'Quick Validate Usage', 'khm-seo' ); ?>">
                                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                                        <span class="screen-reader-text"><?php _e( 'Validate Usage', 'khm-seo' ); ?></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Quick Actions Section -->
    <div class="khm-geo-quick-actions">
        <h3><?php _e( 'Quick Actions', 'khm-seo' ); ?></h3>
        <div class="khm-quick-actions-grid">
            <div class="khm-quick-action-card">
                <h4><?php _e( 'Import Entities', 'khm-seo' ); ?></h4>
                <p><?php _e( 'Import entities from a CSV file to quickly populate your dictionary.', 'khm-seo' ); ?></p>
                <a href="<?php echo esc_url( add_query_arg( 'action', 'import' ) ); ?>" class="button button-primary">
                    <?php _e( 'Import CSV', 'khm-seo' ); ?>
                </a>
            </div>
            
            <div class="khm-quick-action-card">
                <h4><?php _e( 'Export Dictionary', 'khm-seo' ); ?></h4>
                <p><?php _e( 'Export your entity dictionary for backup or sharing with team members.', 'khm-seo' ); ?></p>
                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'export' ), 'export_entities' ) ); ?>" class="button">
                    <?php _e( 'Export CSV', 'khm-seo' ); ?>
                </a>
            </div>
            
            <div class="khm-quick-action-card">
                <h4><?php _e( 'Validate Content', 'khm-seo' ); ?></h4>
                <p><?php _e( 'Run a site-wide validation to check for entity governance issues.', 'khm-seo' ); ?></p>
                <button type="button" class="button khm-validate-site" data-action="validate-site">
                    <?php _e( 'Validate Site', 'khm-seo' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Inline Styles -->
<style>
.khm-geo-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 1.5rem 0;
}

.khm-stat-card {
    background: #fff;
    padding: 1.5rem;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    text-align: center;
}

.khm-stat-card.khm-stat-warning {
    border-left: 4px solid #f56e28;
}

.khm-stat-number {
    font-size: 2em;
    font-weight: 600;
    color: #1d2327;
    line-height: 1;
}

.khm-stat-label {
    margin-top: 0.5rem;
    color: #646970;
    font-size: 0.9em;
}

.khm-geo-filters {
    background: #fff;
    padding: 1rem;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin: 1rem 0;
}

.khm-filter-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
    gap: 1rem;
    align-items: end;
}

.khm-filter-group label {
    display: block;
    margin-bottom: 0.25rem;
    font-weight: 600;
}

.khm-entity-type {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-size: 0.85em;
    font-weight: 500;
    background: #f0f6fc;
    color: #0073aa;
    border: 1px solid #c5d9ed;
}

.khm-entity-scope {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-size: 0.85em;
    text-transform: uppercase;
    font-weight: 500;
}

.khm-scope-global { background: #f7fcf0; color: #5b8f3f; border: 1px solid #c5e0a8; }
.khm-scope-client { background: #fff8e1; color: #8f6f00; border: 1px solid #e5d394; }
.khm-scope-site { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }

.khm-status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 500;
}

.khm-status-active { color: #10b981; }
.khm-status-deprecated { color: #f59e0b; }

.khm-entity-definition {
    color: #646970;
    font-style: italic;
    margin-top: 0.25rem;
    font-size: 0.9em;
}

.khm-quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.khm-quick-action-card {
    background: #fff;
    padding: 1.5rem;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.khm-quick-action-card h4 {
    margin: 0 0 0.5rem 0;
    color: #1d2327;
}

.khm-quick-action-card p {
    margin: 0 0 1rem 0;
    color: #646970;
}

.khm-action-buttons {
    display: flex;
    gap: 0.25rem;
}

.khm-deprecated {
    opacity: 0.7;
}

.khm-alias-count, .khm-usage-count {
    color: #646970;
}

.khm-no-aliases, .khm-no-usage {
    color: #a7aaad;
    font-style: italic;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Quick validate functionality
    $('.khm-quick-validate').on('click', function() {
        var $button = $(this);
        var entityId = $button.data('entity-id');
        
        $button.prop('disabled', true).find('.dashicons').removeClass('dashicons-search').addClass('dashicons-update-alt');
        
        $.post(ajaxurl, {
            action: 'khm_geo_validate_entity',
            entity_id: entityId,
            nonce: '<?php echo wp_create_nonce( 'khm_seo_ajax' ); ?>'
        }, function(response) {
            $button.prop('disabled', false).find('.dashicons').removeClass('dashicons-update-alt').addClass('dashicons-search');
            
            if (response.success) {
                alert('Entity validation completed. Found ' + response.data.issues.length + ' issues.');
            } else {
                alert('Validation failed: ' + response.data);
            }
        });
    });
    
    // Site-wide validation
    $('.khm-validate-site').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('Validating...');
        
        $.post(ajaxurl, {
            action: 'khm_geo_validate_site',
            nonce: '<?php echo wp_create_nonce( 'khm_seo_ajax' ); ?>'
        }, function(response) {
            $button.prop('disabled', false).text('Validate Site');
            
            if (response.success) {
                alert('Site validation completed. Check the governance dashboard for detailed results.');
            } else {
                alert('Validation failed: ' + response.data);
            }
        });
    });
    
    // Bulk action handling
    $('#doaction').on('click', function(e) {
        var action = $('select[name="bulk_action"]').val();
        var checked = $('input[name="entity_ids[]"]:checked');
        
        if (!action) {
            e.preventDefault();
            alert('Please select an action.');
            return false;
        }
        
        if (checked.length === 0) {
            e.preventDefault();
            alert('Please select at least one entity.');
            return false;
        }
        
        if (action === 'delete' && !confirm('Are you sure you want to delete the selected entities? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>