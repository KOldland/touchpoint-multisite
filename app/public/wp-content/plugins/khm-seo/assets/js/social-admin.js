/**
 * Social Media Admin JavaScript
 * 
 * Handles interactive functionality for the social media admin interface
 * including URL testing, tag validation, preview generation, and image uploads.
 */

(function($) {
    'use strict';

    /**
     * Social Media Admin Object
     */
    const SocialAdmin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initImageUploads();
            this.initTabs();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // URL Testing
            $('#test_url_btn').on('click', this.testUrl.bind(this));
            $('#test_url').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    SocialAdmin.testUrl();
                }
            });

            // Tag Validation
            $('#validate_tags_btn').on('click', this.validateTags.bind(this));

            // Platform Preview
            $('#generate_preview_btn').on('click', this.generatePreview.bind(this));

            // Cache Management
            $('#clear_cache_btn').on('click', this.clearCache.bind(this));

            // Image Upload/Remove
            $(document).on('click', '.image-upload-btn', this.openMediaLibrary.bind(this));
            $(document).on('click', '.image-remove-btn', this.removeImage.bind(this));

            // Real-time character counting
            $('textarea[name="khm_seo_social_description"]').on('input', this.updateCharacterCount.bind(this));
            
            // Platform toggles
            $('.platform-toggle').on('change', this.togglePlatformSettings.bind(this));

            // Form validation
            $('form').on('submit', this.validateForm.bind(this));
        },

        /**
         * Initialize image upload functionality
         */
        initImageUploads: function() {
            // Setup media uploader for existing image fields
            $('.image-upload-field').each(function() {
                const $field = $(this);
                const $input = $field.find('input[type="hidden"]');
                const $preview = $field.find('.image-preview');
                
                // Update button text based on image presence
                SocialAdmin.updateImageButtonText($field);
            });
        },

        /**
         * Initialize tab functionality
         */
        initTabs: function() {
            // Auto-scroll to active tab if it's not fully visible
            const $activeTab = $('.nav-tab-active');
            if ($activeTab.length) {
                const tabWrapper = $('.nav-tab-wrapper')[0];
                const activeTabOffset = $activeTab[0].offsetLeft;
                const wrapperWidth = tabWrapper.offsetWidth;
                
                if (activeTabOffset > wrapperWidth) {
                    tabWrapper.scrollLeft = activeTabOffset - (wrapperWidth / 2);
                }
            }

            // Add tab keyboard navigation
            $('.nav-tab').on('keydown', function(e) {
                if (e.which === 37 || e.which === 39) { // Left or Right arrow
                    e.preventDefault();
                    const $tabs = $('.nav-tab');
                    const currentIndex = $tabs.index(this);
                    let nextIndex;
                    
                    if (e.which === 37) { // Left arrow
                        nextIndex = currentIndex > 0 ? currentIndex - 1 : $tabs.length - 1;
                    } else { // Right arrow
                        nextIndex = currentIndex < $tabs.length - 1 ? currentIndex + 1 : 0;
                    }
                    
                    $tabs.eq(nextIndex)[0].click();
                }
            });
        },

        /**
         * Test URL for social media tags
         */
        testUrl: function() {
            const url = $('#test_url').val().trim();
            const $button = $('#test_url_btn');
            const $results = $('#url_test_results');

            if (!url) {
                this.showMessage('error', khmSeoSocial.strings.invalid_url);
                return;
            }

            // Validate URL format
            if (!this.isValidUrl(url)) {
                this.showMessage('error', khmSeoSocial.strings.invalid_url);
                return;
            }

            // Show loading state
            $button.prop('disabled', true).text(khmSeoSocial.strings.testing);
            $results.hide();

            // Make AJAX request
            $.ajax({
                url: khmSeoSocial.ajaxurl,
                type: 'POST',
                data: {
                    action: 'khm_seo_test_social_url',
                    url: url,
                    nonce: khmSeoSocial.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SocialAdmin.displayUrlTestResults(response.data);
                    } else {
                        SocialAdmin.showMessage('error', response.data || khmSeoSocial.strings.error);
                    }
                },
                error: function() {
                    SocialAdmin.showMessage('error', khmSeoSocial.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test URL');
                }
            });
        },

        /**
         * Display URL test results
         */
        displayUrlTestResults: function(data) {
            const $results = $('#url_test_results');
            let html = '<div class="test-results-content">';
            
            // Basic information
            html += '<h4>URL Test Results</h4>';
            html += '<div class="url-info">';
            html += '<strong>Tested URL:</strong> ' + data.url + '<br>';
            html += '<strong>Title:</strong> ' + (data.title || 'Not found') + '<br>';
            html += '<strong>Description:</strong> ' + (data.description || 'Not found') + '<br>';
            html += '<strong>Image:</strong> ' + (data.image || 'Not found') + '<br>';
            html += '</div>';

            // Platform results
            if (data.platforms) {
                html += '<h5>Platform Validation:</h5>';
                html += '<div class="platform-results">';
                
                for (const [platform, result] of Object.entries(data.platforms)) {
                    const statusClass = result.status === 'success' ? 'success' : 
                                      result.status === 'warning' ? 'warning' : 'error';
                    
                    html += '<div class="platform-result ' + statusClass + '">';
                    html += '<strong>' + this.capitalize(platform) + ':</strong> ';
                    html += result.message;
                    html += '</div>';
                }
                
                html += '</div>';
            }

            // Found tags
            if (data.tags_found && Object.keys(data.tags_found).length > 0) {
                html += '<h5>Found Tags:</h5>';
                html += '<div class="found-tags">';
                html += '<ul>';
                
                for (const [tag, content] of Object.entries(data.tags_found)) {
                    html += '<li><code>' + tag + '</code>: ' + content + '</li>';
                }
                
                html += '</ul>';
                html += '</div>';
            }

            html += '</div>';
            
            $results.html(html).show();
        },

        /**
         * Validate social media tags
         */
        validateTags: function() {
            const $button = $('#validate_tags_btn');
            const $results = $('#validation_results');

            // Show loading state
            $button.prop('disabled', true).text(khmSeoSocial.strings.validating);
            $results.hide();

            // Make AJAX request
            $.ajax({
                url: khmSeoSocial.ajaxurl,
                type: 'POST',
                data: {
                    action: 'khm_seo_validate_social_tags',
                    nonce: khmSeoSocial.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SocialAdmin.displayValidationResults(response.data);
                    } else {
                        SocialAdmin.showMessage('error', response.data || khmSeoSocial.strings.error);
                    }
                },
                error: function() {
                    SocialAdmin.showMessage('error', khmSeoSocial.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Validate Current Page Tags');
                }
            });
        },

        /**
         * Display tag validation results
         */
        displayValidationResults: function(data) {
            const $results = $('#validation_results');
            let html = '<div class="validation-results-content">';
            
            // Overall status
            const overallStatus = data.validation.valid ? 'success' : 'error';
            html += '<div class="overall-status ' + overallStatus + '">';
            html += '<h4>' + (data.validation.valid ? 'Validation Passed' : 'Validation Failed') + '</h4>';
            html += '</div>';

            // Errors
            if (data.validation.errors && data.validation.errors.length > 0) {
                html += '<div class="validation-errors error">';
                html += '<h5>Errors:</h5>';
                html += '<ul>';
                data.validation.errors.forEach(function(error) {
                    html += '<li>' + error + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }

            // Warnings
            if (data.validation.warnings && data.validation.warnings.length > 0) {
                html += '<div class="validation-warnings warning">';
                html += '<h5>Warnings:</h5>';
                html += '<ul>';
                data.validation.warnings.forEach(function(warning) {
                    html += '<li>' + warning + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }

            // Platform-specific validation
            if (data.validation.platforms) {
                html += '<h5>Platform Validation:</h5>';
                
                for (const [platform, result] of Object.entries(data.validation.platforms)) {
                    const statusClass = result.valid ? 'success' : 'error';
                    
                    html += '<div class="platform-validation ' + statusClass + '">';
                    html += '<h6>' + this.capitalize(platform) + '</h6>';
                    
                    if (result.errors && result.errors.length > 0) {
                        html += '<div class="errors">Errors: ' + result.errors.join(', ') + '</div>';
                    }
                    
                    if (result.warnings && result.warnings.length > 0) {
                        html += '<div class="warnings">Warnings: ' + result.warnings.join(', ') + '</div>';
                    }
                    
                    html += '</div>';
                }
            }

            // Generated HTML
            if (data.html) {
                html += '<h5>Generated Meta Tags:</h5>';
                html += '<div class="generated-html">';
                html += '<textarea readonly rows="10" style="width: 100%; font-family: monospace;">';
                html += data.html;
                html += '</textarea>';
                html += '</div>';
            }

            html += '</div>';
            
            $results.html(html).show();
        },

        /**
         * Generate platform preview
         */
        generatePreview: function() {
            const platform = $('#preview_platform').val();
            const $button = $('#generate_preview_btn');
            const $preview = $('#platform_preview');

            if (!platform) {
                this.showMessage('error', 'Please select a platform');
                return;
            }

            // Show loading state
            $button.prop('disabled', true).text(khmSeoSocial.strings.generating_preview);
            $preview.hide();

            // Make AJAX request
            $.ajax({
                url: khmSeoSocial.ajaxurl,
                type: 'POST',
                data: {
                    action: 'khm_seo_generate_social_preview',
                    platform: platform,
                    nonce: khmSeoSocial.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SocialAdmin.displayPlatformPreview(response.data);
                    } else {
                        SocialAdmin.showMessage('error', response.data || khmSeoSocial.strings.error);
                    }
                },
                error: function() {
                    SocialAdmin.showMessage('error', khmSeoSocial.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate Preview');
                }
            });
        },

        /**
         * Display platform preview
         */
        displayPlatformPreview: function(data) {
            const $preview = $('#platform_preview');
            
            let html = '<div class="' + data.platform + '-preview">';
            html += '<h4>' + this.capitalize(data.platform) + ' Preview</h4>';
            html += data.preview;
            html += '</div>';
            
            $preview.html(html).show();
        },

        /**
         * Clear social media cache
         */
        clearCache: function() {
            const $button = $('#clear_cache_btn');
            const $spinner = $('#cache_spinner');

            // Show loading state
            $button.prop('disabled', true).text(khmSeoSocial.strings.clearing_cache);
            $spinner.addClass('is-active');

            // Make AJAX request
            $.ajax({
                url: khmSeoSocial.ajaxurl,
                type: 'POST',
                data: {
                    action: 'khm_seo_clear_social_cache',
                    nonce: khmSeoSocial.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SocialAdmin.showMessage('success', khmSeoSocial.strings.success);
                    } else {
                        SocialAdmin.showMessage('error', response.data || khmSeoSocial.strings.error);
                    }
                },
                error: function() {
                    SocialAdmin.showMessage('error', khmSeoSocial.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clear Social Media Cache');
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Open WordPress media library
         */
        openMediaLibrary: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const targetField = $button.data('target');
            const previewContainer = $button.data('preview');
            
            // Create media frame
            const frame = wp.media({
                title: 'Select Social Media Image',
                button: { text: 'Use This Image' },
                multiple: false,
                library: { type: 'image' }
            });

            // When image is selected
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                
                // Update hidden field
                $('#' + targetField).val(attachment.id || attachment.url);
                
                // Update preview
                const $preview = $('#' + previewContainer);
                $preview.html('<img src="' + attachment.url + '" alt="' + (attachment.alt || attachment.title) + '">');
                
                // Update button text and add remove button
                SocialAdmin.updateImageButtonText($button.closest('.image-upload-field'));
            });

            // Open frame
            frame.open();
        },

        /**
         * Remove image
         */
        removeImage: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $field = $button.closest('.image-upload-field');
            const targetField = $button.data('target');
            const previewContainer = $button.data('preview');
            
            // Clear hidden field
            $('#' + targetField).val('');
            
            // Clear preview
            $('#' + previewContainer).empty();
            
            // Update buttons
            this.updateImageButtonText($field);
        },

        /**
         * Update image button text and visibility
         */
        updateImageButtonText: function($field) {
            const $input = $field.find('input[type="hidden"]');
            const $uploadBtn = $field.find('.image-upload-btn');
            const $removeBtn = $field.find('.image-remove-btn');
            const hasImage = $input.val().length > 0;
            
            $uploadBtn.text(hasImage ? 'Change Image' : 'Select Image');
            $removeBtn.toggle(hasImage);
        },

        /**
         * Update character count for textarea
         */
        updateCharacterCount: function(e) {
            const $textarea = $(e.target);
            const text = $textarea.val();
            const length = text.length;
            const maxLength = 300; // Social media description limit
            
            let $counter = $textarea.siblings('.character-counter');
            if (!$counter.length) {
                $counter = $('<div class="character-counter"></div>');
                $textarea.after($counter);
            }
            
            const remaining = maxLength - length;
            const className = remaining < 20 ? 'warning' : remaining < 0 ? 'error' : 'normal';
            
            $counter
                .removeClass('normal warning error')
                .addClass(className)
                .text(length + '/' + maxLength + ' characters');
        },

        /**
         * Toggle platform-specific settings
         */
        togglePlatformSettings: function(e) {
            const $checkbox = $(e.target);
            const platform = $checkbox.data('platform');
            const $settings = $('.' + platform + '-settings');
            
            if ($checkbox.is(':checked')) {
                $settings.show();
            } else {
                $settings.hide();
            }
        },

        /**
         * Validate form before submission
         */
        validateForm: function(e) {
            const $form = $(e.target);
            let isValid = true;
            
            // Validate URLs
            $form.find('input[type="url"]').each(function() {
                const $input = $(this);
                const url = $input.val().trim();
                
                if (url && !SocialAdmin.isValidUrl(url)) {
                    isValid = false;
                    $input.addClass('error');
                    SocialAdmin.showMessage('error', 'Please enter a valid URL for ' + $input.closest('tr').find('th').text());
                } else {
                    $input.removeClass('error');
                }
            });

            // Validate required fields
            $form.find('[required]').each(function() {
                const $input = $(this);
                
                if (!$input.val().trim()) {
                    isValid = false;
                    $input.addClass('error');
                    SocialAdmin.showMessage('error', 'Please fill in all required fields');
                } else {
                    $input.removeClass('error');
                }
            });

            if (!isValid) {
                e.preventDefault();
            }
        },

        /**
         * Show admin message
         */
        showMessage: function(type, message) {
            // Remove existing messages
            $('.notice-social').remove();
            
            // Create new message
            const $notice = $('<div class="notice-social ' + type + '">' + message + '</div>');
            
            // Insert message at top of content
            $('.khm-seo-tab-content').prepend($notice);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
            
            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 300);
        },

        /**
         * Validate URL format
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Capitalize first letter
         */
        capitalize: function(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                const later = () => {
                    timeout = null;
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        SocialAdmin.init();
    });

    /**
     * Export for testing/debugging
     */
    window.SocialAdmin = SocialAdmin;

})(jQuery);