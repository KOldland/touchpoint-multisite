<?php
/**
 * Schema Admin Page Template
 * 
 * Template for schema configuration admin page
 * 
 * @package KHM_SEO\Schema\Admin
 * @since 4.0.0
 * 
 * Available variables:
 * @var array $config Current configuration
 * @var array $schema_types Available schema types
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>

<div class="wrap">
    <h1><?php _e( 'Schema Settings', 'khm-seo' ); ?></h1>
    
    <div id="khm-seo-schema-admin" class="khm-seo-admin-page">
        
        <!-- Tabs Navigation -->
        <nav class="nav-tab-wrapper wp-clearfix">
            <a href="#general" class="nav-tab nav-tab-active"><?php _e( 'General', 'khm-seo' ); ?></a>
            <a href="#types" class="nav-tab"><?php _e( 'Schema Types', 'khm-seo' ); ?></a>
            <a href="#validation" class="nav-tab"><?php _e( 'Validation', 'khm-seo' ); ?></a>
            <a href="#tools" class="nav-tab"><?php _e( 'Tools', 'khm-seo' ); ?></a>
        </nav>

        <form method="post" action="">
            <?php wp_nonce_field( 'khm_seo_schema_admin', 'khm_seo_schema_admin_nonce' ); ?>
            
            <!-- General Settings Tab -->
            <div id="general" class="tab-content active">
                <table class="form-table">
                    <tbody>
                        
                        <!-- Enable Meta Boxes -->
                        <tr>
                            <th scope="row">
                                <label for="enable_meta_boxes"><?php _e( 'Meta Boxes', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="enable_meta_boxes"
                                           name="khm_seo_schema_admin[enable_meta_boxes]" 
                                           value="1" 
                                           <?php checked( $this->config['enable_meta_boxes'] ); ?>>
                                    <?php _e( 'Enable schema meta boxes on post edit screens', 'khm-seo' ); ?>
                                </label>
                                <p class="description">
                                    <?php _e( 'Adds schema configuration options to post and page edit screens.', 'khm-seo' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Supported Post Types -->
                        <tr>
                            <th scope="row">
                                <label for="supported_post_types"><?php _e( 'Supported Post Types', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <?php
                                $available_post_types = get_post_types( array( 'public' => true ), 'objects' );
                                foreach ( $available_post_types as $post_type ) :
                                ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" 
                                               name="khm_seo_schema_admin[supported_post_types][]" 
                                               value="<?php echo esc_attr( $post_type->name ); ?>"
                                               <?php checked( in_array( $post_type->name, $this->config['supported_post_types'] ) ); ?>>
                                        <?php echo esc_html( $post_type->label ); ?>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description">
                                    <?php _e( 'Select which post types should have schema markup options.', 'khm-seo' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Default Schema Type -->
                        <tr>
                            <th scope="row">
                                <label for="default_schema_type"><?php _e( 'Default Schema Type', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <select id="default_schema_type" name="khm_seo_schema_admin[default_schema_type]">
                                    <?php foreach ( $this->schema_types as $type_key => $type_config ) : ?>
                                        <option value="<?php echo esc_attr( $type_key ); ?>" 
                                                <?php selected( $this->config['default_schema_type'], $type_key ); ?>>
                                            <?php echo esc_html( $type_config['label'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php _e( 'Default schema type for new posts and pages.', 'khm-seo' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Schema Preview -->
                        <tr>
                            <th scope="row">
                                <label for="enable_schema_preview"><?php _e( 'Schema Preview', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="enable_schema_preview"
                                           name="khm_seo_schema_admin[enable_schema_preview]" 
                                           value="1" 
                                           <?php checked( $this->config['enable_schema_preview'] ); ?>>
                                    <?php _e( 'Enable real-time schema preview in meta boxes', 'khm-seo' ); ?>
                                </label>
                                <p class="description">
                                    <?php _e( 'Shows a live preview of generated JSON-LD markup.', 'khm-seo' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Schema Validation -->
                        <tr>
                            <th scope="row">
                                <label for="enable_validation"><?php _e( 'Schema Validation', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="enable_validation"
                                           name="khm_seo_schema_admin[enable_validation]" 
                                           value="1" 
                                           <?php checked( $this->config['enable_validation'] ); ?>>
                                    <?php _e( 'Validate schema markup automatically', 'khm-seo' ); ?>
                                </label>
                                <p class="description">
                                    <?php _e( 'Automatically validates schema markup and shows warnings for missing required fields.', 'khm-seo' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Bulk Management -->
                        <tr>
                            <th scope="row">
                                <label for="enable_bulk_management"><?php _e( 'Bulk Management', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="enable_bulk_management"
                                           name="khm_seo_schema_admin[enable_bulk_management]" 
                                           value="1" 
                                           <?php checked( $this->config['enable_bulk_management'] ); ?>>
                                    <?php _e( 'Enable bulk schema management tools', 'khm-seo' ); ?>
                                </label>
                                <p class="description">
                                    <?php _e( 'Adds bulk edit options and schema management tools to post lists.', 'khm-seo' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Debug Mode -->
                        <tr>
                            <th scope="row">
                                <label for="show_debug_info"><?php _e( 'Debug Mode', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="show_debug_info"
                                           name="khm_seo_schema_admin[show_debug_info]" 
                                           value="1" 
                                           <?php checked( $this->config['show_debug_info'] ); ?>>
                                    <?php _e( 'Show debug information and raw JSON-LD output', 'khm-seo' ); ?>
                                </label>
                                <p class="description">
                                    <?php _e( 'Enables advanced debugging features for developers. Only enable if you know what you\'re doing.', 'khm-seo' ); ?>
                                </p>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

            <!-- Schema Types Tab -->
            <div id="types" class="tab-content">
                <h2><?php _e( 'Available Schema Types', 'khm-seo' ); ?></h2>
                <p><?php _e( 'Configure how different schema types are handled and their default settings.', 'khm-seo' ); ?></p>
                
                <div class="khm-seo-schema-types-grid">
                    <?php foreach ( $this->schema_types as $type_key => $type_config ) : ?>
                        <div class="khm-seo-schema-type-card">
                            <div class="schema-type-header">
                                <h3><?php echo esc_html( $type_config['label'] ); ?></h3>
                                <span class="schema-type-badge">Schema.org</span>
                            </div>
                            <p class="schema-type-description">
                                <?php echo esc_html( $type_config['description'] ); ?>
                            </p>
                            <div class="schema-type-details">
                                <strong><?php _e( 'Applicable to:', 'khm-seo' ); ?></strong>
                                <?php echo esc_html( implode( ', ', $type_config['applicable_to'] ) ); ?>
                            </div>
                            <div class="schema-type-fields">
                                <strong><?php _e( 'Fields:', 'khm-seo' ); ?></strong>
                                <span class="field-count"><?php echo count( $type_config['fields'] ); ?> fields</span>
                                <div class="field-list">
                                    <?php echo esc_html( implode( ', ', array_slice( $type_config['fields'], 0, 3 ) ) ); ?>
                                    <?php if ( count( $type_config['fields'] ) > 3 ) : ?>
                                        <span class="more-fields">+<?php echo count( $type_config['fields'] ) - 3; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Validation Tab -->
            <div id="validation" class="tab-content">
                <h2><?php _e( 'Schema Validation', 'khm-seo' ); ?></h2>
                
                <div class="khm-seo-validation-tools">
                    <div class="validation-tool">
                        <h3><?php _e( 'Test Current Site Schema', 'khm-seo' ); ?></h3>
                        <p><?php _e( 'Run a comprehensive validation of all schema markup on your site.', 'khm-seo' ); ?></p>
                        <button type="button" id="khm-seo-run-site-validation" class="button button-primary">
                            <?php _e( 'Run Site Validation', 'khm-seo' ); ?>
                        </button>
                        <div id="khm-seo-site-validation-results" class="validation-results"></div>
                    </div>
                    
                    <div class="validation-tool">
                        <h3><?php _e( 'Google Rich Results Test', 'khm-seo' ); ?></h3>
                        <p><?php _e( 'Test a specific URL with Google\'s Rich Results testing tool.', 'khm-seo' ); ?></p>
                        <div class="url-test-form">
                            <input type="url" 
                                   id="khm-seo-test-url" 
                                   placeholder="<?php esc_attr_e( 'Enter URL to test...', 'khm-seo' ); ?>"
                                   class="regular-text">
                            <button type="button" id="khm-seo-test-google" class="button">
                                <?php _e( 'Test with Google', 'khm-seo' ); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="validation-tool">
                        <h3><?php _e( 'Schema.org Validator', 'khm-seo' ); ?></h3>
                        <p><?php _e( 'Validate schema markup using the official Schema.org validator.', 'khm-seo' ); ?></p>
                        <div class="schema-test-form">
                            <textarea id="khm-seo-schema-input" 
                                      placeholder="<?php esc_attr_e( 'Paste JSON-LD schema markup here...', 'khm-seo' ); ?>"
                                      rows="5" 
                                      class="large-text code"></textarea>
                            <br>
                            <button type="button" id="khm-seo-validate-schema" class="button">
                                <?php _e( 'Validate Schema', 'khm-seo' ); ?>
                            </button>
                        </div>
                        <div id="khm-seo-schema-validation-results" class="validation-results"></div>
                    </div>
                </div>
            </div>

            <!-- Tools Tab -->
            <div id="tools" class="tab-content">
                <h2><?php _e( 'Schema Management Tools', 'khm-seo' ); ?></h2>
                
                <div class="khm-seo-tools-grid">
                    
                    <!-- Bulk Schema Assignment -->
                    <div class="tool-card">
                        <h3><?php _e( 'Bulk Schema Assignment', 'khm-seo' ); ?></h3>
                        <p><?php _e( 'Apply schema settings to multiple posts at once.', 'khm-seo' ); ?></p>
                        <div class="tool-form">
                            <label>
                                <?php _e( 'Post Type:', 'khm-seo' ); ?>
                                <select id="bulk-post-type">
                                    <?php foreach ( $this->supported_post_types as $post_type_name ) : 
                                        $post_type_obj = get_post_type_object( $post_type_name );
                                    ?>
                                        <option value="<?php echo esc_attr( $post_type_name ); ?>">
                                            <?php echo esc_html( $post_type_obj->label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            
                            <label>
                                <?php _e( 'Schema Type:', 'khm-seo' ); ?>
                                <select id="bulk-schema-type">
                                    <?php foreach ( $this->schema_types as $type_key => $type_config ) : ?>
                                        <option value="<?php echo esc_attr( $type_key ); ?>">
                                            <?php echo esc_html( $type_config['label'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            
                            <label>
                                <input type="checkbox" id="bulk-enable-schema" checked>
                                <?php _e( 'Enable schema markup', 'khm-seo' ); ?>
                            </label>
                            
                            <button type="button" id="khm-seo-bulk-assign" class="button button-primary">
                                <?php _e( 'Apply to All Posts', 'khm-seo' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Schema Cache Management -->
                    <div class="tool-card">
                        <h3><?php _e( 'Cache Management', 'khm-seo' ); ?></h3>
                        <p><?php _e( 'Manage schema markup cache for improved performance.', 'khm-seo' ); ?></p>
                        <div class="tool-actions">
                            <button type="button" id="khm-seo-clear-schema-cache" class="button">
                                <?php _e( 'Clear Schema Cache', 'khm-seo' ); ?>
                            </button>
                            <button type="button" id="khm-seo-regenerate-cache" class="button">
                                <?php _e( 'Regenerate Cache', 'khm-seo' ); ?>
                            </button>
                        </div>
                        <div id="cache-status">
                            <?php
                            $cache_stats = $this->get_cache_stats();
                            printf( 
                                __( 'Cached items: %d | Last updated: %s', 'khm-seo' ),
                                $cache_stats['count'],
                                $cache_stats['last_updated']
                            );
                            ?>
                        </div>
                    </div>

                    <!-- Export/Import Settings -->
                    <div class="tool-card">
                        <h3><?php _e( 'Export/Import Settings', 'khm-seo' ); ?></h3>
                        <p><?php _e( 'Backup and restore your schema configuration.', 'khm-seo' ); ?></p>
                        <div class="tool-actions">
                            <button type="button" id="khm-seo-export-settings" class="button">
                                <?php _e( 'Export Settings', 'khm-seo' ); ?>
                            </button>
                            <div class="import-section">
                                <input type="file" id="khm-seo-import-file" accept=".json" style="display: none;">
                                <button type="button" id="khm-seo-import-settings" class="button">
                                    <?php _e( 'Import Settings', 'khm-seo' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" 
                       value="<?php esc_attr_e( 'Save Changes', 'khm-seo' ); ?>">
            </p>

        </form>
    </div>
</div>

<style>
.khm-seo-admin-page {
    max-width: 1200px;
}

.tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: none;
}

.tab-content.active {
    display: block;
}

.khm-seo-schema-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.khm-seo-schema-type-card {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
}

.schema-type-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.schema-type-badge {
    background: #2271b1;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.schema-type-description {
    color: #666;
    margin-bottom: 15px;
}

.schema-type-details, .schema-type-fields {
    margin-bottom: 10px;
    font-size: 14px;
}

.field-count {
    color: #2271b1;
    font-weight: 500;
}

.more-fields {
    color: #666;
    font-style: italic;
}

.khm-seo-validation-tools {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.validation-tool {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
}

.url-test-form, .schema-test-form {
    margin-top: 15px;
}

.url-test-form input, .schema-test-form textarea {
    margin-bottom: 10px;
}

.validation-results {
    margin-top: 15px;
    padding: 15px;
    border-radius: 4px;
    display: none;
}

.khm-seo-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.tool-card {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
}

.tool-form label {
    display: block;
    margin-bottom: 10px;
}

.tool-form select, .tool-form input {
    width: 100%;
    margin-top: 5px;
}

.tool-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.import-section {
    display: flex;
    align-items: center;
    gap: 10px;
}

#cache-status {
    margin-top: 15px;
    padding: 10px;
    background: #e8f4fd;
    border-radius: 4px;
    font-size: 14px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href').substring(1);
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').removeClass('active');
        $('#' + target).addClass('active');
    });
    
    // Tool handlers would be implemented here
    // This is a template file, so actual functionality would be in admin JS file
});
</script>

<?php
// Helper method for cache stats (would normally be in class)
if ( ! method_exists( $this, 'get_cache_stats' ) ) {
    $this->get_cache_stats = function() {
        global $wpdb;
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_khm_seo_schema_cache'" );
        $last_updated = $wpdb->get_var( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_khm_seo_schema_cache_updated' ORDER BY post_id DESC LIMIT 1" );
        
        return array(
            'count' => (int) $count,
            'last_updated' => $last_updated ? date( 'M j, Y g:i a', strtotime( $last_updated ) ) : __( 'Never', 'khm-seo' )
        );
    };
}
?>