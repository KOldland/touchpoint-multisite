<?php
/**
 * Bulk Edit Schema Template
 * 
 * Template for schema configuration in bulk edit mode
 * 
 * @package KHM_SEO\Schema\Admin
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>

<fieldset class="inline-edit-col-right khm-seo-bulk-edit">
    <div class="inline-edit-col">
        <h4><?php _e( 'Schema Markup', 'khm-seo' ); ?></h4>
        
        <div class="inline-edit-group">
            <label>
                <span class="title"><?php _e( 'Schema Status', 'khm-seo' ); ?></span>
                <select name="khm_seo_bulk_schema_status" class="khm-seo-bulk-schema-status">
                    <option value=""><?php _e( '— No Change —', 'khm-seo' ); ?></option>
                    <option value="enable"><?php _e( 'Enable Schema', 'khm-seo' ); ?></option>
                    <option value="disable"><?php _e( 'Disable Schema', 'khm-seo' ); ?></option>
                </select>
            </label>
        </div>
        
        <div class="inline-edit-group">
            <label>
                <span class="title"><?php _e( 'Schema Type', 'khm-seo' ); ?></span>
                <select name="khm_seo_bulk_schema_type" class="khm-seo-bulk-schema-type">
                    <option value=""><?php _e( '— No Change —', 'khm-seo' ); ?></option>
                    <?php foreach ( $this->schema_types as $type_key => $type_config ) : ?>
                        <option value="<?php echo esc_attr( $type_key ); ?>">
                            <?php echo esc_html( $type_config['label'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        
        <div class="inline-edit-group">
            <label>
                <span class="title"><?php _e( 'Auto-generate', 'khm-seo' ); ?></span>
                <select name="khm_seo_bulk_auto_generate" class="khm-seo-bulk-auto-generate">
                    <option value=""><?php _e( '— No Change —', 'khm-seo' ); ?></option>
                    <option value="enable"><?php _e( 'Enable Auto-generation', 'khm-seo' ); ?></option>
                    <option value="disable"><?php _e( 'Disable Auto-generation', 'khm-seo' ); ?></option>
                </select>
            </label>
        </div>
        
        <div class="inline-edit-group">
            <label class="inline-edit-status">
                <input type="checkbox" name="khm_seo_bulk_regenerate_cache" value="1">
                <span class="checkbox-title"><?php _e( 'Regenerate schema cache for selected posts', 'khm-seo' ); ?></span>
            </label>
        </div>
        
        <div class="inline-edit-group khm-seo-bulk-info">
            <p class="description">
                <span class="dashicons dashicons-info"></span>
                <?php _e( 'Bulk operations will only affect selected posts. Empty fields will be ignored.', 'khm-seo' ); ?>
            </p>
        </div>
    </div>
</fieldset>

<style>
.khm-seo-bulk-edit {
    border-top: 1px solid #ddd;
    margin-top: 10px;
    padding-top: 15px;
}

.khm-seo-bulk-edit h4 {
    margin: 0 0 15px;
    color: #1e1e1e;
    font-size: 14px;
    font-weight: 600;
}

.khm-seo-bulk-edit .inline-edit-group {
    margin-bottom: 12px;
}

.khm-seo-bulk-edit .title {
    display: inline-block;
    min-width: 100px;
    margin-right: 10px;
    font-weight: 500;
}

.khm-seo-bulk-edit select {
    width: 220px;
}

.khm-seo-bulk-edit .inline-edit-status {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 5px;
}

.khm-seo-bulk-edit .checkbox-title {
    font-size: 13px;
    line-height: 1.4;
}

.khm-seo-bulk-info {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    padding: 10px;
    margin-top: 15px;
}

.khm-seo-bulk-info .description {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 5px;
    color: #646970;
    font-size: 13px;
}

.khm-seo-bulk-info .dashicons {
    color: #2271b1;
    font-size: 16px;
}

/* Hide bulk edit when no posts are selected */
.tablenav .bulkactions select option[value="khm_seo_bulk_schema"] {
    display: none;
}

.tablenav .bulkactions.has-selection select option[value="khm_seo_bulk_schema"] {
    display: block;
}
</style>

<script>
(function($) {
    // Bulk Edit Schema functionality
    var $bulkEdit = $('.khm-seo-bulk-edit');
    
    // Show/hide bulk edit options based on selection
    function toggleBulkEditVisibility() {
        var hasSelection = $('.wp-list-table tbody input[type="checkbox"]:checked').length > 0;
        
        if (hasSelection) {
            $bulkEdit.addClass('has-selection');
        } else {
            $bulkEdit.removeClass('has-selection');
        }
    }
    
    // Monitor checkbox changes
    $(document).on('change', '.wp-list-table tbody input[type="checkbox"]', toggleBulkEditVisibility);
    $(document).on('change', '.check-column input[type="checkbox"]', toggleBulkEditVisibility);
    
    // Handle bulk action dropdown
    $('.tablenav .bulkactions').each(function() {
        var $select = $(this).find('select');
        var $originalOptions = $select.html();
        
        // Add schema bulk action
        $select.append('<option value="khm_seo_bulk_schema">' + '<?php echo esc_js( __( 'Edit Schema', 'khm-seo' ) ); ?>' + '</option>');
        
        // Handle bulk action selection
        $(this).find('input[type="submit"]').on('click', function(e) {
            if ($select.val() === 'khm_seo_bulk_schema') {
                e.preventDefault();
                showBulkSchemaEdit();
            }
        });
    });
    
    function showBulkSchemaEdit() {
        var selectedPosts = [];
        $('.wp-list-table tbody input[type="checkbox"]:checked').each(function() {
            var postId = $(this).val();
            if (postId && postId !== '-1') {
                selectedPosts.push(postId);
            }
        });
        
        if (selectedPosts.length === 0) {
            alert('<?php echo esc_js( __( 'Please select posts to edit.', 'khm-seo' ) ); ?>');
            return;
        }
        
        // Show custom bulk edit modal or inline edit
        showBulkSchemaModal(selectedPosts);
    }
    
    function showBulkSchemaModal(postIds) {
        // Create modal for bulk schema editing
        var modalHtml = `
            <div id="khm-seo-bulk-modal" class="khm-seo-modal-overlay">
                <div class="khm-seo-modal">
                    <div class="khm-seo-modal-header">
                        <h3><?php echo esc_js( __( 'Bulk Edit Schema', 'khm-seo' ) ); ?></h3>
                        <button type="button" class="khm-seo-modal-close">&times;</button>
                    </div>
                    <div class="khm-seo-modal-body">
                        <p><?php echo esc_js( sprintf( __( 'Editing schema for %s posts:', 'khm-seo' ), '${postIds.length}' ) ); ?></p>
                        
                        <div class="khm-seo-bulk-form">
                            <div class="field-group">
                                <label>
                                    <?php echo esc_js( __( 'Schema Status:', 'khm-seo' ) ); ?>
                                    <select id="bulk-schema-status">
                                        <option value=""><?php echo esc_js( __( '— No Change —', 'khm-seo' ) ); ?></option>
                                        <option value="enable"><?php echo esc_js( __( 'Enable', 'khm-seo' ) ); ?></option>
                                        <option value="disable"><?php echo esc_js( __( 'Disable', 'khm-seo' ) ); ?></option>
                                    </select>
                                </label>
                            </div>
                            
                            <div class="field-group">
                                <label>
                                    <?php echo esc_js( __( 'Schema Type:', 'khm-seo' ) ); ?>
                                    <select id="bulk-schema-type">
                                        <option value=""><?php echo esc_js( __( '— No Change —', 'khm-seo' ) ); ?></option>
                                        <?php foreach ( $this->schema_types as $type_key => $type_config ) : ?>
                                        <option value="<?php echo esc_attr( $type_key ); ?>"><?php echo esc_js( $type_config['label'] ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                            
                            <div class="field-group">
                                <label>
                                    <input type="checkbox" id="bulk-auto-generate" value="1">
                                    <?php echo esc_js( __( 'Auto-generate missing fields', 'khm-seo' ) ); ?>
                                </label>
                            </div>
                            
                            <div class="field-group">
                                <label>
                                    <input type="checkbox" id="bulk-regenerate-cache" value="1" checked>
                                    <?php echo esc_js( __( 'Regenerate schema cache', 'khm-seo' ) ); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="khm-seo-modal-footer">
                        <button type="button" class="button button-secondary khm-seo-modal-close">
                            <?php echo esc_js( __( 'Cancel', 'khm-seo' ) ); ?>
                        </button>
                        <button type="button" class="button button-primary" id="khm-seo-apply-bulk-changes">
                            <?php echo esc_js( __( 'Apply Changes', 'khm-seo' ) ); ?>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        // Handle modal actions
        $('#khm-seo-bulk-modal').on('click', '.khm-seo-modal-close', function() {
            $('#khm-seo-bulk-modal').remove();
        });
        
        $('#khm-seo-apply-bulk-changes').on('click', function() {
            applyBulkSchemaChanges(postIds);
        });
    }
    
    function applyBulkSchemaChanges(postIds) {
        var schemaConfig = {
            status: $('#bulk-schema-status').val(),
            type: $('#bulk-schema-type').val(),
            auto_generate: $('#bulk-auto-generate').is(':checked'),
            regenerate_cache: $('#bulk-regenerate-cache').is(':checked')
        };
        
        // Show loading state
        $('#khm-seo-apply-bulk-changes').prop('disabled', true).text('<?php echo esc_js( __( 'Applying...', 'khm-seo' ) ); ?>');
        
        // Make AJAX request
        $.post(ajaxurl, {
            action: 'khm_seo_bulk_schema_update',
            nonce: '<?php echo wp_create_nonce( "khm_seo_ajax" ); ?>',
            post_ids: postIds,
            schema_config: schemaConfig
        })
        .done(function(response) {
            if (response.success) {
                $('#khm-seo-bulk-modal').remove();
                location.reload(); // Refresh to show changes
            } else {
                alert('<?php echo esc_js( __( 'Error updating schema:', 'khm-seo' ) ); ?> ' + (response.data || '<?php echo esc_js( __( 'Unknown error', 'khm-seo' ) ); ?>'));
            }
        })
        .fail(function() {
            alert('<?php echo esc_js( __( 'Network error. Please try again.', 'khm-seo' ) ); ?>');
        })
        .always(function() {
            $('#khm-seo-apply-bulk-changes').prop('disabled', false).text('<?php echo esc_js( __( 'Apply Changes', 'khm-seo' ) ); ?>');
        });
    }
    
})(jQuery);
</script>

<style>
.khm-seo-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.khm-seo-modal {
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow: auto;
}

.khm-seo-modal-header {
    padding: 20px;
    border-bottom: 1px solid #e1e1e1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.khm-seo-modal-header h3 {
    margin: 0;
}

.khm-seo-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    line-height: 1;
}

.khm-seo-modal-close:hover {
    color: #000;
}

.khm-seo-modal-body {
    padding: 20px;
}

.khm-seo-bulk-form .field-group {
    margin-bottom: 15px;
}

.khm-seo-bulk-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.khm-seo-bulk-form select {
    width: 100%;
    margin-top: 5px;
}

.khm-seo-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e1e1e1;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>