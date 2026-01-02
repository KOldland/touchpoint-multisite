<?php
/**
 * Quick Edit Schema Template
 * 
 * Template for schema configuration in quick edit mode
 * 
 * @package KHM_SEO\Schema\Admin
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>

<fieldset class="inline-edit-col-right khm-seo-quick-edit">
    <div class="inline-edit-col">
        <h4><?php _e( 'Schema Markup', 'khm-seo' ); ?></h4>
        
        <div class="inline-edit-group">
            <label class="inline-edit-status">
                <input type="checkbox" name="khm_seo_schema_enabled" value="1">
                <span class="checkbox-title"><?php _e( 'Enable Schema', 'khm-seo' ); ?></span>
            </label>
        </div>
        
        <div class="inline-edit-group">
            <label>
                <span class="title"><?php _e( 'Schema Type', 'khm-seo' ); ?></span>
                <select name="khm_seo_schema_type" class="khm-seo-quick-edit-type">
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
            <label class="inline-edit-status">
                <input type="checkbox" name="khm_seo_schema_auto_generate" value="1">
                <span class="checkbox-title"><?php _e( 'Auto-generate fields', 'khm-seo' ); ?></span>
            </label>
        </div>
    </div>
</fieldset>

<style>
.khm-seo-quick-edit {
    border-top: 1px solid #ddd;
    margin-top: 10px;
    padding-top: 10px;
}

.khm-seo-quick-edit h4 {
    margin: 0 0 10px;
    color: #1e1e1e;
}

.khm-seo-quick-edit .inline-edit-group {
    margin-bottom: 10px;
}

.khm-seo-quick-edit .inline-edit-status {
    display: flex;
    align-items: center;
    gap: 8px;
}

.khm-seo-quick-edit .title {
    display: inline-block;
    min-width: 80px;
    margin-right: 10px;
}

.khm-seo-quick-edit select {
    width: 200px;
}
</style>

<script>
(function($) {
    // Quick Edit functionality
    $('#the-list').on('click', '.editinline', function() {
        var postId = $(this).closest('tr').attr('id').replace('post-', '');
        var $row = $('#post-' + postId);
        var $schemaColumn = $row.find('.column-khm_schema');
        
        // Get current schema status
        var isEnabled = $schemaColumn.find('.enabled').length > 0;
        var schemaType = '';
        var typeMatch = $schemaColumn.text().match(/Schema Type: (\w+)/);
        if (typeMatch) {
            schemaType = typeMatch[1].toLowerCase();
        }
        
        // Set form values
        $('#edit-' + postId).find('input[name="khm_seo_schema_enabled"]').prop('checked', isEnabled);
        if (schemaType) {
            $('#edit-' + postId).find('select[name="khm_seo_schema_type"]').val(schemaType);
        }
    });
})(jQuery);
</script>