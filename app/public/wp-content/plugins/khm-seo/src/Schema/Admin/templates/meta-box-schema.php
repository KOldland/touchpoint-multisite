<?php
/**
 * Schema Meta Box Template
 * 
 * Template for displaying schema configuration in post meta box
 * 
 * @package KHM_SEO\Schema\Admin
 * @since 4.0.0
 * 
 * Available variables:
 * @var \WP_Post $post Current post object
 * @var string $current_type Current schema type
 * @var array $custom_fields Custom schema fields
 * @var array $schema_types Available schema types
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$is_enabled = ! empty( $current_schema['enabled'] );
$applicable_types = array();

// Get applicable schema types for this post type
foreach ( $this->schema_types as $type_key => $type_config ) {
    if ( in_array( 'all', $type_config['applicable_to'] ) || 
         in_array( $post->post_type, $type_config['applicable_to'] ) ) {
        $applicable_types[ $type_key ] = $type_config;
    }
}
?>

<div id="khm-seo-schema-meta-box" class="khm-seo-meta-box">
    
    <!-- Schema Enable/Disable -->
    <div class="khm-seo-field-group">
        <label class="khm-seo-toggle">
            <input type="checkbox" 
                   name="khm_seo_schema_enabled" 
                   id="khm_seo_schema_enabled"
                   value="1" 
                   <?php checked( $is_enabled ); ?>
                   class="khm-seo-schema-toggle">
            <span class="khm-seo-toggle-slider"></span>
            <strong><?php _e( 'Enable Schema Markup', 'khm-seo' ); ?></strong>
        </label>
        <p class="description">
            <?php _e( 'Add structured data markup to this content for better search engine understanding.', 'khm-seo' ); ?>
        </p>
    </div>

    <!-- Schema Configuration Panel -->
    <div id="khm-seo-schema-config" class="khm-seo-schema-config" <?php echo $is_enabled ? '' : 'style="display: none;"'; ?>>
        
        <!-- Schema Type Selection -->
        <div class="khm-seo-field-group">
            <label for="khm_seo_schema_type">
                <strong><?php _e( 'Schema Type', 'khm-seo' ); ?></strong>
            </label>
            <select name="khm_seo_schema_type" id="khm_seo_schema_type" class="widefat">
                <?php foreach ( $applicable_types as $type_key => $type_config ) : ?>
                    <option value="<?php echo esc_attr( $type_key ); ?>" 
                            <?php selected( $current_type, $type_key ); ?>>
                        <?php echo esc_html( $type_config['label'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php _e( 'Select the most appropriate schema type for this content.', 'khm-seo' ); ?>
            </p>
        </div>

        <!-- Dynamic Schema Fields -->
        <div id="khm-seo-schema-fields" class="khm-seo-schema-fields">
            <?php foreach ( $applicable_types as $type_key => $type_config ) : ?>
                <div class="khm-seo-schema-type-fields" 
                     data-schema-type="<?php echo esc_attr( $type_key ); ?>"
                     <?php echo $current_type === $type_key ? '' : 'style="display: none;"'; ?>>
                    
                    <h4><?php echo esc_html( $type_config['label'] ); ?> <?php _e( 'Fields', 'khm-seo' ); ?></h4>
                    <p class="description"><?php echo esc_html( $type_config['description'] ); ?></p>
                    
                    <?php foreach ( $type_config['fields'] as $field_key ) : ?>
                        <?php 
                        $field_value = $custom_fields[ $field_key ] ?? '';
                        $field_label = $this->get_field_label( $field_key );
                        $field_description = $this->get_field_description( $field_key );
                        $field_type = $this->get_field_type( $field_key );
                        ?>
                        
                        <div class="khm-seo-field">
                            <label for="khm_seo_field_<?php echo esc_attr( $field_key ); ?>">
                                <?php echo esc_html( $field_label ); ?>
                                <?php if ( $this->is_required_field( $field_key, $type_key ) ) : ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ( $field_type === 'textarea' ) : ?>
                                <textarea name="khm_seo_schema_fields[<?php echo esc_attr( $field_key ); ?>]"
                                          id="khm_seo_field_<?php echo esc_attr( $field_key ); ?>"
                                          class="widefat"
                                          rows="3"
                                          placeholder="<?php echo esc_attr( $this->get_field_placeholder( $field_key ) ); ?>"><?php echo esc_textarea( $field_value ); ?></textarea>
                            <?php elseif ( $field_type === 'url' ) : ?>
                                <input type="url" 
                                       name="khm_seo_schema_fields[<?php echo esc_attr( $field_key ); ?>]"
                                       id="khm_seo_field_<?php echo esc_attr( $field_key ); ?>"
                                       class="widefat"
                                       value="<?php echo esc_attr( $field_value ); ?>"
                                       placeholder="<?php echo esc_attr( $this->get_field_placeholder( $field_key ) ); ?>">
                            <?php elseif ( $field_type === 'date' ) : ?>
                                <input type="date" 
                                       name="khm_seo_schema_fields[<?php echo esc_attr( $field_key ); ?>]"
                                       id="khm_seo_field_<?php echo esc_attr( $field_key ); ?>"
                                       class="widefat"
                                       value="<?php echo esc_attr( $field_value ); ?>">
                            <?php else : ?>
                                <input type="text" 
                                       name="khm_seo_schema_fields[<?php echo esc_attr( $field_key ); ?>]"
                                       id="khm_seo_field_<?php echo esc_attr( $field_key ); ?>"
                                       class="widefat"
                                       value="<?php echo esc_attr( $field_value ); ?>"
                                       placeholder="<?php echo esc_attr( $this->get_field_placeholder( $field_key ) ); ?>">
                            <?php endif; ?>
                            
                            <?php if ( $field_description ) : ?>
                                <p class="description"><?php echo esc_html( $field_description ); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Advanced Options -->
        <div class="khm-seo-field-group">
            <h4><?php _e( 'Advanced Options', 'khm-seo' ); ?></h4>
            
            <div class="khm-seo-field">
                <label>
                    <input type="checkbox" 
                           name="khm_seo_schema_options[auto_generate]"
                           value="1"
                           <?php checked( ! empty( $current_schema['options']['auto_generate'] ) ); ?>>
                    <?php _e( 'Auto-generate missing fields from post content', 'khm-seo' ); ?>
                </label>
            </div>
            
            <div class="khm-seo-field">
                <label>
                    <input type="checkbox" 
                           name="khm_seo_schema_options[include_breadcrumbs]"
                           value="1"
                           <?php checked( ! empty( $current_schema['options']['include_breadcrumbs'] ) ); ?>>
                    <?php _e( 'Include breadcrumb schema', 'khm-seo' ); ?>
                </label>
            </div>
            
            <div class="khm-seo-field">
                <label>
                    <input type="checkbox" 
                           name="khm_seo_schema_options[validate_output]"
                           value="1"
                           <?php checked( ! empty( $current_schema['options']['validate_output'] ) ); ?>>
                    <?php _e( 'Validate schema output', 'khm-seo' ); ?>
                </label>
            </div>
        </div>

        <!-- Schema Preview and Tools -->
        <div class="khm-seo-field-group">
            <h4><?php _e( 'Schema Tools', 'khm-seo' ); ?></h4>
            
            <div class="khm-seo-schema-actions">
                <button type="button" 
                        id="khm-seo-preview-schema" 
                        class="button button-secondary">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e( 'Preview Schema', 'khm-seo' ); ?>
                </button>
                
                <button type="button" 
                        id="khm-seo-validate-schema" 
                        class="button button-secondary">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e( 'Validate Schema', 'khm-seo' ); ?>
                </button>
                
                <button type="button" 
                        id="khm-seo-test-schema" 
                        class="button button-secondary">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e( 'Test with Google', 'khm-seo' ); ?>
                </button>
            </div>
        </div>

        <!-- Schema Preview Panel -->
        <div id="khm-seo-schema-preview" class="khm-seo-schema-preview" style="display: none;">
            <h4><?php _e( 'Schema Preview', 'khm-seo' ); ?></h4>
            <div class="khm-seo-preview-content">
                <textarea readonly class="widefat code" rows="10" id="khm-seo-preview-output"></textarea>
            </div>
            <div class="khm-seo-preview-actions">
                <button type="button" class="button" id="khm-seo-copy-schema">
                    <?php _e( 'Copy to Clipboard', 'khm-seo' ); ?>
                </button>
            </div>
        </div>

        <!-- Validation Results -->
        <div id="khm-seo-validation-results" class="khm-seo-validation-results" style="display: none;">
            <h4><?php _e( 'Validation Results', 'khm-seo' ); ?></h4>
            <div class="khm-seo-validation-content"></div>
        </div>

    </div>
</div>

<style>
.khm-seo-meta-box {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    padding: 20px;
    margin: 10px 0;
}

.khm-seo-field-group {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.khm-seo-field-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.khm-seo-field {
    margin-bottom: 15px;
}

.khm-seo-field label {
    display: block;
    font-weight: 500;
    margin-bottom: 5px;
}

.khm-seo-field .required {
    color: #d63384;
}

.khm-seo-toggle {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.khm-seo-toggle-slider {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
    background-color: #ccc;
    border-radius: 12px;
    margin-right: 10px;
    transition: 0.3s;
}

.khm-seo-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    top: 3px;
    background-color: white;
    border-radius: 50%;
    transition: 0.3s;
}

.khm-seo-toggle input:checked + .khm-seo-toggle-slider {
    background-color: #2271b1;
}

.khm-seo-toggle input:checked + .khm-seo-toggle-slider:before {
    transform: translateX(26px);
}

.khm-seo-schema-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.khm-seo-schema-actions .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.khm-seo-schema-preview {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    padding: 15px;
    margin-top: 15px;
}

.khm-seo-validation-results {
    margin-top: 15px;
}

.khm-seo-validation-success {
    background: #d1e7dd;
    border: 1px solid #badbcc;
    color: #0f5132;
    padding: 10px;
    border-radius: 4px;
}

.khm-seo-validation-error {
    background: #f8d7da;
    border: 1px solid #f5c2c7;
    color: #842029;
    padding: 10px;
    border-radius: 4px;
}

.khm-seo-validation-warning {
    background: #fff3cd;
    border: 1px solid #ffecb5;
    color: #664d03;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 10px;
}
</style>

<?php
// Helper methods would normally be in the class, but defining here for template use
if ( ! function_exists( 'khm_seo_get_field_label' ) ) :
    function khm_seo_get_field_label( $field_key ) {
        $labels = array(
            'headline' => __( 'Headline', 'khm-seo' ),
            'author' => __( 'Author', 'khm-seo' ),
            'datePublished' => __( 'Published Date', 'khm-seo' ),
            'dateModified' => __( 'Modified Date', 'khm-seo' ),
            'description' => __( 'Description', 'khm-seo' ),
            'name' => __( 'Name', 'khm-seo' ),
            'url' => __( 'URL', 'khm-seo' ),
            'logo' => __( 'Logo URL', 'khm-seo' ),
            'contactPoint' => __( 'Contact Information', 'khm-seo' ),
            'address' => __( 'Address', 'khm-seo' ),
            'sameAs' => __( 'Social Media URLs', 'khm-seo' ),
            'jobTitle' => __( 'Job Title', 'khm-seo' ),
            'image' => __( 'Image URL', 'khm-seo' ),
            'worksFor' => __( 'Works For', 'khm-seo' ),
        );
        
        return $labels[ $field_key ] ?? ucfirst( str_replace( '_', ' ', $field_key ) );
    }
    
    function khm_seo_get_field_description( $field_key ) {
        $descriptions = array(
            'headline' => __( 'The main headline of the article', 'khm-seo' ),
            'author' => __( 'Author name or organization', 'khm-seo' ),
            'datePublished' => __( 'When the content was first published', 'khm-seo' ),
            'dateModified' => __( 'When the content was last updated', 'khm-seo' ),
            'description' => __( 'Brief description of the content', 'khm-seo' ),
            'sameAs' => __( 'One URL per line for social media profiles', 'khm-seo' ),
        );
        
        return $descriptions[ $field_key ] ?? '';
    }
    
    function khm_seo_get_field_type( $field_key ) {
        $types = array(
            'datePublished' => 'date',
            'dateModified' => 'date',
            'url' => 'url',
            'logo' => 'url',
            'image' => 'url',
            'description' => 'textarea',
            'address' => 'textarea',
            'sameAs' => 'textarea',
        );
        
        return $types[ $field_key ] ?? 'text';
    }
    
    function khm_seo_get_field_placeholder( $field_key ) {
        $placeholders = array(
            'headline' => __( 'Enter article headline...', 'khm-seo' ),
            'author' => __( 'Enter author name...', 'khm-seo' ),
            'description' => __( 'Enter content description...', 'khm-seo' ),
            'name' => __( 'Enter organization name...', 'khm-seo' ),
            'url' => __( 'https://example.com', 'khm-seo' ),
            'logo' => __( 'https://example.com/logo.png', 'khm-seo' ),
            'sameAs' => __( "https://facebook.com/page\nhttps://twitter.com/account", 'khm-seo' ),
        );
        
        return $placeholders[ $field_key ] ?? '';
    }
    
    function khm_seo_is_required_field( $field_key, $schema_type ) {
        $required_fields = array(
            'article' => array( 'headline', 'author', 'datePublished' ),
            'organization' => array( 'name', 'url' ),
            'person' => array( 'name' ),
            'product' => array( 'name' ),
        );
        
        return in_array( $field_key, $required_fields[ $schema_type ] ?? array() );
    }
endif;

// Use helper functions in template
$this->get_field_label = 'khm_seo_get_field_label';
$this->get_field_description = 'khm_seo_get_field_description';
$this->get_field_type = 'khm_seo_get_field_type';
$this->get_field_placeholder = 'khm_seo_get_field_placeholder';
$this->is_required_field = 'khm_seo_is_required_field';
?>