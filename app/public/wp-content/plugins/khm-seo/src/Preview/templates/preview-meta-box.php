<?php
/**
 * Social Media Preview Meta Box Template
 * 
 * @package KHM_SEO
 * @subpackage Preview
 */

if (!defined('ABSPATH')) {
    exit;
}

global $post;
$preview_manager = new KHM_SEO\Preview\SocialMediaPreviewManager();
$platforms = $preview_manager->get_platforms();
?>

<div class="khm-social-preview-container">
    
    <!-- Preview Controls -->
    <div class="preview-controls">
        <div class="control-buttons">
            <button type="button" id="refresh-all-previews" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh All Previews', 'khm-seo'); ?>
            </button>
            
            <button type="button" id="optimize-all-meta" class="button button-primary">
                <span class="dashicons dashicons-performance"></span>
                <?php esc_html_e('Auto-Optimize Meta Tags', 'khm-seo'); ?>
            </button>
        </div>
        
        <div class="preview-info">
            <p class="description">
                <?php esc_html_e('Preview how your content will appear when shared on different social media platforms. Click on any preview to customize the meta tags for that platform.', 'khm-seo'); ?>
            </p>
        </div>
    </div>
    
    <!-- Platform Tabs -->
    <div class="social-preview-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#all-platforms" class="nav-tab nav-tab-active" data-platform="all">
                <span class="tab-icon">🌍</span>
                <?php esc_html_e('All Platforms', 'khm-seo'); ?>
            </a>
            
            <?php foreach ($platforms as $platform_key => $platform_config): ?>
                <a href="#platform-<?php echo esc_attr($platform_key); ?>" 
                   class="nav-tab" 
                   data-platform="<?php echo esc_attr($platform_key); ?>">
                    <span class="tab-icon"><?php echo esc_html($platform_config['icon']); ?></span>
                    <?php echo esc_html($platform_config['name']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        
        <!-- All Platforms View -->
        <div id="all-platforms" class="tab-content active">
            <div class="platforms-grid">
                <?php foreach ($platforms as $platform_key => $platform_config): ?>
                    <div class="platform-preview-wrapper" data-platform="<?php echo esc_attr($platform_key); ?>">
                        <div class="platform-header">
                            <h3 class="platform-title">
                                <span class="platform-icon"><?php echo esc_html($platform_config['icon']); ?></span>
                                <?php echo esc_html($platform_config['name']); ?>
                                <span class="platform-status loading">
                                    <span class="dashicons dashicons-update-alt"></span>
                                </span>
                            </h3>
                            
                            <div class="platform-actions">
                                <button type="button" class="button button-small refresh-preview" data-platform="<?php echo esc_attr($platform_key); ?>">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e('Refresh', 'khm-seo'); ?>
                                </button>
                                
                                <button type="button" class="button button-small edit-meta" data-platform="<?php echo esc_attr($platform_key); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php esc_html_e('Edit', 'khm-seo'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="preview-container" id="preview-<?php echo esc_attr($platform_key); ?>">
                            <div class="preview-loading">
                                <div class="loading-spinner"></div>
                                <p><?php esc_html_e('Generating preview...', 'khm-seo'); ?></p>
                            </div>
                        </div>
                        
                        <div class="preview-warnings" id="warnings-<?php echo esc_attr($platform_key); ?>">
                            <!-- Warnings will be populated via JavaScript -->
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Individual Platform Tabs -->
        <?php foreach ($platforms as $platform_key => $platform_config): ?>
            <div id="platform-<?php echo esc_attr($platform_key); ?>" class="tab-content">
                <div class="single-platform-view">
                    
                    <!-- Platform Info -->
                    <div class="platform-info">
                        <div class="platform-details">
                            <h3>
                                <span class="platform-icon"><?php echo esc_html($platform_config['icon']); ?></span>
                                <?php echo esc_html($platform_config['name']); ?> 
                                <?php esc_html_e('Preview', 'khm-seo'); ?>
                            </h3>
                            
                            <div class="platform-specs">
                                <span class="spec-item">
                                    <strong><?php esc_html_e('Card Size:', 'khm-seo'); ?></strong>
                                    <?php echo esc_html($platform_config['card_width'] . ' x ' . $platform_config['card_height']); ?>px
                                </span>
                                
                                <span class="spec-item">
                                    <strong><?php esc_html_e('Image Ratio:', 'khm-seo'); ?></strong>
                                    <?php echo esc_html($platform_config['image_ratio']); ?>
                                </span>
                                
                                <span class="spec-item">
                                    <strong><?php esc_html_e('Title Limit:', 'khm-seo'); ?></strong>
                                    <?php echo esc_html($platform_config['title_limit']); ?> chars
                                </span>
                                
                                <?php if ($platform_config['preview_url']): ?>
                                    <a href="<?php echo esc_url($platform_config['preview_url']); ?>" 
                                       target="_blank" 
                                       class="external-validator">
                                        <span class="dashicons dashicons-external"></span>
                                        <?php esc_html_e('Official Validator', 'khm-seo'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="platform-actions-detailed">
                            <button type="button" class="button button-secondary refresh-preview" data-platform="<?php echo esc_attr($platform_key); ?>">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Refresh Preview', 'khm-seo'); ?>
                            </button>
                            
                            <button type="button" class="button button-primary optimize-meta" data-platform="<?php echo esc_attr($platform_key); ?>">
                                <span class="dashicons dashicons-performance"></span>
                                <?php esc_html_e('Auto-Optimize', 'khm-seo'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Preview and Editor Side by Side -->
                    <div class="preview-editor-container">
                        
                        <!-- Live Preview -->
                        <div class="preview-section">
                            <h4><?php esc_html_e('Live Preview', 'khm-seo'); ?></h4>
                            
                            <div class="preview-container large" id="preview-large-<?php echo esc_attr($platform_key); ?>">
                                <div class="preview-loading">
                                    <div class="loading-spinner"></div>
                                    <p><?php esc_html_e('Generating preview...', 'khm-seo'); ?></p>
                                </div>
                            </div>
                            
                            <div class="preview-analysis" id="analysis-<?php echo esc_attr($platform_key); ?>">
                                <!-- Analysis will be populated via JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Meta Editor -->
                        <div class="editor-section">
                            <h4><?php esc_html_e('Meta Tags Editor', 'khm-seo'); ?></h4>
                            
                            <div class="meta-editor" id="editor-<?php echo esc_attr($platform_key); ?>">
                                
                                <!-- Title Field -->
                                <div class="meta-field">
                                    <label for="<?php echo esc_attr($platform_key); ?>_title">
                                        <?php esc_html_e('Title', 'khm-seo'); ?>
                                        <span class="character-counter" data-limit="<?php echo esc_attr($platform_config['title_limit']); ?>">
                                            <span class="current-count">0</span> / <?php echo esc_html($platform_config['title_limit']); ?>
                                        </span>
                                    </label>
                                    
                                    <input type="text" 
                                           id="<?php echo esc_attr($platform_key); ?>_title"
                                           name="<?php echo esc_attr($platform_key); ?>_title"
                                           class="regular-text meta-input"
                                           data-platform="<?php echo esc_attr($platform_key); ?>"
                                           data-field="title"
                                           data-limit="<?php echo esc_attr($platform_config['title_limit']); ?>"
                                           placeholder="<?php esc_attr_e('Enter custom title or leave blank for auto-generation', 'khm-seo'); ?>">
                                    
                                    <div class="field-suggestions" id="title-suggestions-<?php echo esc_attr($platform_key); ?>"></div>
                                </div>
                                
                                <!-- Description Field -->
                                <div class="meta-field">
                                    <label for="<?php echo esc_attr($platform_key); ?>_description">
                                        <?php esc_html_e('Description', 'khm-seo'); ?>
                                        <span class="character-counter" data-limit="<?php echo esc_attr($platform_config['description_limit']); ?>">
                                            <span class="current-count">0</span> / <?php echo esc_html($platform_config['description_limit']); ?>
                                        </span>
                                    </label>
                                    
                                    <textarea id="<?php echo esc_attr($platform_key); ?>_description"
                                              name="<?php echo esc_attr($platform_key); ?>_description"
                                              class="large-text meta-input"
                                              data-platform="<?php echo esc_attr($platform_key); ?>"
                                              data-field="description"
                                              data-limit="<?php echo esc_attr($platform_config['description_limit']); ?>"
                                              rows="3"
                                              placeholder="<?php esc_attr_e('Enter custom description or leave blank for auto-generation', 'khm-seo'); ?>"></textarea>
                                    
                                    <div class="field-suggestions" id="description-suggestions-<?php echo esc_attr($platform_key); ?>"></div>
                                </div>
                                
                                <!-- Image Field -->
                                <div class="meta-field">
                                    <label for="<?php echo esc_attr($platform_key); ?>_image">
                                        <?php esc_html_e('Image', 'khm-seo'); ?>
                                        <span class="recommended-size">
                                            (<?php esc_html_e('Recommended:', 'khm-seo'); ?> <?php echo esc_html($platform_config['image_ratio']); ?>)
                                        </span>
                                    </label>
                                    
                                    <div class="image-field-container">
                                        <input type="url" 
                                               id="<?php echo esc_attr($platform_key); ?>_image"
                                               name="<?php echo esc_attr($platform_key); ?>_image"
                                               class="regular-text meta-input"
                                               data-platform="<?php echo esc_attr($platform_key); ?>"
                                               data-field="image"
                                               placeholder="<?php esc_attr_e('Enter image URL or leave blank to use featured image', 'khm-seo'); ?>">
                                        
                                        <button type="button" class="button select-image" data-platform="<?php echo esc_attr($platform_key); ?>">
                                            <span class="dashicons dashicons-admin-media"></span>
                                            <?php esc_html_e('Select Image', 'khm-seo'); ?>
                                        </button>
                                    </div>
                                    
                                    <div class="image-preview" id="image-preview-<?php echo esc_attr($platform_key); ?>"></div>
                                    <div class="field-suggestions" id="image-suggestions-<?php echo esc_attr($platform_key); ?>"></div>
                                </div>
                                
                                <!-- Platform-specific fields -->
                                <?php if ($platform_key === 'twitter'): ?>
                                    <div class="meta-field">
                                        <label for="twitter_card_type"><?php esc_html_e('Card Type', 'khm-seo'); ?></label>
                                        <select id="twitter_card_type" 
                                                name="twitter_card_type" 
                                                class="meta-input"
                                                data-platform="twitter"
                                                data-field="card_type">
                                            <option value="summary_large_image"><?php esc_html_e('Large Image', 'khm-seo'); ?></option>
                                            <option value="summary"><?php esc_html_e('Summary', 'khm-seo'); ?></option>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Save Button -->
                                <div class="meta-field-actions">
                                    <button type="button" class="button button-primary save-meta" data-platform="<?php echo esc_attr($platform_key); ?>">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php esc_html_e('Save & Update Preview', 'khm-seo'); ?>
                                    </button>
                                    
                                    <button type="button" class="button button-secondary reset-meta" data-platform="<?php echo esc_attr($platform_key); ?>">
                                        <span class="dashicons dashicons-undo"></span>
                                        <?php esc_html_e('Reset to Default', 'khm-seo'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
    </div>
    
    <!-- Preview Modal for Mobile -->
    <div id="preview-modal" class="preview-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"></h3>
                <button type="button" class="modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="modal-preview-container"></div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close">
                    <?php esc_html_e('Close', 'khm-seo'); ?>
                </button>
                
                <button type="button" class="button button-primary modal-edit">
                    <?php esc_html_e('Edit Meta Tags', 'khm-seo'); ?>
                </button>
            </div>
        </div>
    </div>
    
</div>

<!-- Templates for JavaScript -->
<script type="text/template" id="preview-card-template">
    <%- cardHtml %>
</script>

<script type="text/template" id="warning-item-template">
    <div class="warning-item warning-<%- severity %>">
        <span class="dashicons dashicons-warning"></span>
        <span class="warning-message"><%- message %></span>
        <% if (field) { %>
            <button type="button" class="button button-small fix-warning" data-field="<%- field %>" data-action="<%- action || 'edit' %>">
                <?php esc_html_e('Fix', 'khm-seo'); ?>
            </button>
        <% } %>
    </div>
</script>

<script type="text/template" id="suggestion-item-template">
    <div class="suggestion-item">
        <span class="dashicons dashicons-lightbulb"></span>
        <span class="suggestion-message"><%- message %></span>
        <% if (action) { %>
            <button type="button" class="button button-small apply-suggestion" data-action="<%- action %>">
                <?php esc_html_e('Apply', 'khm-seo'); ?>
            </button>
        <% } %>
    </div>
</script>

<style>
/* Inline critical styles for immediate loading */
.khm-social-preview-container {
    margin: 0;
}

.preview-loading {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #0073aa;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.platforms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.platform-preview-wrapper {
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    background: #fff;
}

.platform-header {
    display: flex;
    justify-content: between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #ccd0d4;
    background: #f6f7f7;
}

.platform-title {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
}

.preview-container {
    padding: 16px;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>