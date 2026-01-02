/**
 * KHM SEO Schema Admin JavaScript
 * 
 * Handles all admin interface interactions for schema management
 * 
 * @package KHM_SEO\Schema\Admin
 * @since 4.0.0
 * @version 4.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Schema Admin Manager
     */
    var KHMSchemaAdmin = {
        
        /**
         * Initialize admin interface
         */
        init: function() {
            this.initMetaBox();
            this.initAdminPage();
            this.initPostList();
            this.initValidation();
            this.bindEvents();
        },
        
        /**
         * Initialize meta box functionality
         */
        initMetaBox: function() {
            // Schema toggle functionality
            $(document).on('change', '#khm_seo_schema_enabled', function() {
                var $config = $('#khm-seo-schema-config');
                
                if ($(this).is(':checked')) {
                    $config.slideDown(300);
                } else {
                    $config.slideUp(300);
                }
            });
            
            // Schema type change handler
            $(document).on('change', '#khm_seo_schema_type', function() {
                var selectedType = $(this).val();
                var $fieldsContainer = $('#khm-seo-schema-fields');
                
                // Hide all field groups
                $fieldsContainer.find('.khm-seo-schema-type-fields').hide();
                
                // Show selected type fields
                if (selectedType) {
                    $fieldsContainer.find('[data-schema-type="' + selectedType + '"]').show();
                }
                
                // Update field requirements
                this.updateFieldRequirements(selectedType);
            }.bind(this));
            
            // Auto-populate fields from post content
            $(document).on('change', 'input[name="khm_seo_schema_options[auto_generate]"]', function() {
                if ($(this).is(':checked')) {
                    KHMSchemaAdmin.autoPopulateFields();
                }
            });
        },
        
        /**
         * Initialize admin page functionality
         */
        initAdminPage: function() {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href').substring(1);
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $('#' + target).addClass('active');
            });
            
            // Bulk schema assignment
            $('#khm-seo-bulk-assign').on('click', this.handleBulkAssignment.bind(this));
            
            // Cache management
            $('#khm-seo-clear-schema-cache').on('click', this.clearSchemaCache.bind(this));
            $('#khm-seo-regenerate-cache').on('click', this.regenerateCache.bind(this));
            
            // Export/Import
            $('#khm-seo-export-settings').on('click', this.exportSettings.bind(this));
            $('#khm-seo-import-settings').on('click', function() {
                $('#khm-seo-import-file').click();
            });
            $('#khm-seo-import-file').on('change', this.importSettings.bind(this));
        },
        
        /**
         * Initialize post list functionality
         */
        initPostList: function() {
            // Quick edit integration
            this.initQuickEdit();
            
            // Bulk edit integration
            this.initBulkEdit();
            
            // Schema status indicators
            this.updateSchemaStatusIndicators();
        },
        
        /**
         * Initialize validation functionality
         */
        initValidation: function() {
            // Real-time validation for required fields
            $(document).on('blur', '.khm-seo-schema-type-fields input, .khm-seo-schema-type-fields textarea', 
                this.validateField.bind(this));
            
            // Form submission validation
            $('form').on('submit', function(e) {
                if (!KHMSchemaAdmin.validateForm()) {
                    e.preventDefault();
                }
            });
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Schema preview
            $(document).on('click', '#khm-seo-preview-schema', this.previewSchema.bind(this));
            
            // Schema validation
            $(document).on('click', '#khm-seo-validate-schema', this.validateSchema.bind(this));
            
            // Test with Google
            $(document).on('click', '#khm-seo-test-schema', this.testWithGoogle.bind(this));
            
            // Copy schema to clipboard
            $(document).on('click', '#khm-seo-copy-schema', this.copySchemaToClipboard.bind(this));
            
            // Site validation
            $(document).on('click', '#khm-seo-run-site-validation', this.runSiteValidation.bind(this));
            
            // Google Rich Results test
            $(document).on('click', '#khm-seo-test-google', this.testGoogleRichResults.bind(this));
            
            // Schema.org validation
            $(document).on('click', '#khm-seo-validate-schema', this.validateSchemaOrg.bind(this));
        },
        
        /**
         * Auto-populate schema fields from post content
         */
        autoPopulateFields: function() {
            var postTitle = $('#title').val() || $('input[name="post_title"]').val();
            var postContent = this.getPostContent();
            var postDate = $('input[name="post_date"]').val() || new Date().toISOString().split('T')[0];
            
            // Populate headline/name
            if (postTitle && !$('#khm_seo_field_headline').val()) {
                $('#khm_seo_field_headline').val(postTitle);
            }
            if (postTitle && !$('#khm_seo_field_name').val()) {
                $('#khm_seo_field_name').val(postTitle);
            }
            
            // Populate description
            if (postContent && !$('#khm_seo_field_description').val()) {
                var excerpt = this.extractExcerpt(postContent, 160);
                $('#khm_seo_field_description').val(excerpt);
            }
            
            // Populate dates
            if (postDate && !$('#khm_seo_field_datePublished').val()) {
                $('#khm_seo_field_datePublished').val(postDate);
            }
            
            // Auto-detect author
            var authorName = $('.post-author-display-name').text() || $('input[name="post_author_override"]').val();
            if (authorName && !$('#khm_seo_field_author').val()) {
                $('#khm_seo_field_author').val(authorName);
            }
            
            // Show success message
            this.showMessage('Fields auto-populated successfully!', 'success');
        },
        
        /**
         * Get post content from editor
         */
        getPostContent: function() {
            // Try TinyMCE editor first
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                return tinyMCE.get('content').getContent({format: 'text'});
            }
            
            // Fallback to textarea
            return $('#content').val() || '';
        },
        
        /**
         * Extract excerpt from content
         */
        extractExcerpt: function(content, maxLength) {
            // Remove HTML tags
            var text = $('<div>').html(content).text();
            
            // Trim and limit length
            if (text.length <= maxLength) {
                return text;
            }
            
            return text.substring(0, maxLength).replace(/\s+\S*$/, '') + '...';
        },
        
        /**
         * Preview schema markup
         */
        previewSchema: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var $preview = $('#khm-seo-schema-preview');
            var $output = $('#khm-seo-preview-output');
            
            // Show loading state
            $button.prop('disabled', true).find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-update-alt');
            $output.val(khm_seo_schema.strings.preview_loading);
            $preview.slideDown();
            
            // Collect form data
            var schemaConfig = this.collectSchemaFormData();
            var postId = $('input[name="post_ID"]').val() || 0;
            
            // Make AJAX request
            $.post(khm_seo_schema.ajax_url, {
                action: 'khm_seo_preview_schema',
                nonce: khm_seo_schema.nonce,
                post_id: postId,
                schema_config: schemaConfig
            })
            .done(function(response) {
                if (response.success && response.data.formatted) {
                    $output.val(response.data.formatted);
                    KHMSchemaAdmin.enableCodeHighlighting($output[0]);
                } else {
                    $output.val(khm_seo_schema.strings.preview_error + ': ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function() {
                $output.val(khm_seo_schema.strings.preview_error);
            })
            .always(function() {
                $button.prop('disabled', false).find('.dashicons').removeClass('dashicons-update-alt').addClass('dashicons-visibility');
            });
        },
        
        /**
         * Validate schema markup
         */
        validateSchema: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var $results = $('#khm-seo-validation-results');
            
            // Show loading state
            $button.prop('disabled', true);
            $results.html('<div class="khm-seo-validation-loading">Validating schema...</div>').slideDown();
            
            // Get schema JSON from preview or generate it
            var schemaJson = $('#khm-seo-preview-output').val();
            
            if (!schemaJson) {
                // Generate schema first
                this.generateSchemaForValidation(function(json) {
                    KHMSchemaAdmin.performValidation(json, $button, $results);
                });
            } else {
                this.performValidation(schemaJson, $button, $results);
            }
        },
        
        /**
         * Perform schema validation
         */
        performValidation: function(schemaJson, $button, $results) {
            $.post(khm_seo_schema.ajax_url, {
                action: 'khm_seo_validate_schema',
                nonce: khm_seo_schema.nonce,
                schema_json: schemaJson
            })
            .done(function(response) {
                if (response.success) {
                    KHMSchemaAdmin.displayValidationResults(response.data, $results);
                } else {
                    $results.html('<div class="khm-seo-validation-error">' + (response.data || 'Validation failed') + '</div>');
                }
            })
            .fail(function() {
                $results.html('<div class="khm-seo-validation-error">Network error during validation</div>');
            })
            .always(function() {
                $button.prop('disabled', false);
            });
        },
        
        /**
         * Display validation results
         */
        displayValidationResults: function(results, $container) {
            var html = '';
            
            if (results.valid) {
                html += '<div class="khm-seo-validation-success">';
                html += '<span class="dashicons dashicons-yes-alt"></span> ';
                html += khm_seo_schema.strings.validation_success;
                html += ' <span class="validation-score">(Score: ' + results.score + '/100)</span>';
                html += '</div>';
            } else {
                html += '<div class="khm-seo-validation-error">';
                html += '<span class="dashicons dashicons-warning"></span> ';
                html += khm_seo_schema.strings.validation_error;
                html += '</div>';
            }
            
            // Display errors
            if (results.errors && results.errors.length > 0) {
                html += '<div class="validation-errors"><h4>Errors:</h4><ul>';
                results.errors.forEach(function(error) {
                    html += '<li class="error-item">' + error + '</li>';
                });
                html += '</ul></div>';
            }
            
            // Display warnings
            if (results.warnings && results.warnings.length > 0) {
                html += '<div class="validation-warnings"><h4>Warnings:</h4><ul>';
                results.warnings.forEach(function(warning) {
                    html += '<li class="warning-item">' + warning + '</li>';
                });
                html += '</ul></div>';
            }
            
            $container.html(html);
        },
        
        /**
         * Test schema with Google Rich Results
         */
        testWithGoogle: function(e) {
            e.preventDefault();
            
            var postUrl = this.getCurrentPostUrl();
            
            if (!postUrl) {
                alert('Please save the post first to test with Google.');
                return;
            }
            
            // Open Google Rich Results Test in new tab
            var googleTestUrl = 'https://search.google.com/test/rich-results?url=' + encodeURIComponent(postUrl);
            window.open(googleTestUrl, '_blank');
        },
        
        /**
         * Copy schema to clipboard
         */
        copySchemaToClipboard: function(e) {
            e.preventDefault();
            
            var $output = $('#khm-seo-preview-output');
            var text = $output.val();
            
            if (!text) {
                alert('No schema data to copy. Generate a preview first.');
                return;
            }
            
            // Copy to clipboard
            $output.select();
            document.execCommand('copy');
            
            // Show feedback
            this.showMessage('Schema copied to clipboard!', 'success');
        },
        
        /**
         * Collect schema form data
         */
        collectSchemaFormData: function() {
            var config = {
                enabled: $('#khm_seo_schema_enabled').is(':checked'),
                type: $('#khm_seo_schema_type').val(),
                custom_fields: {},
                options: {}
            };
            
            // Collect custom fields for active schema type
            var activeType = config.type;
            if (activeType && khm_seo_schema.schema_types[activeType]) {
                khm_seo_schema.schema_types[activeType].fields.forEach(function(fieldKey) {
                    var $field = $('#khm_seo_field_' + fieldKey);
                    if ($field.length) {
                        config.custom_fields[fieldKey] = $field.val();
                    }
                });
            }
            
            // Collect options
            $('input[name^="khm_seo_schema_options"]').each(function() {
                var name = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
                config.options[name] = $(this).is(':checked') || $(this).val();
            });
            
            return config;
        },
        
        /**
         * Get current post URL
         */
        getCurrentPostUrl: function() {
            var postId = $('input[name="post_ID"]').val();
            var postStatus = $('select[name="post_status"]').val();
            
            if (!postId || postStatus === 'auto-draft') {
                return null;
            }
            
            // Try to get preview URL
            var $previewButton = $('#post-preview');
            if ($previewButton.length) {
                return $previewButton.attr('href');
            }
            
            return null;
        },
        
        /**
         * Update field requirements based on schema type
         */
        updateFieldRequirements: function(schemaType) {
            if (!schemaType || !khm_seo_schema.schema_types[schemaType]) {
                return;
            }
            
            var typeConfig = khm_seo_schema.schema_types[schemaType];
            
            // Reset all field requirements
            $('.khm-seo-field label .required').remove();
            
            // Add required indicators
            typeConfig.fields.forEach(function(fieldKey) {
                var $field = $('#khm_seo_field_' + fieldKey);
                var $label = $field.prev('label');
                
                if (KHMSchemaAdmin.isRequiredField(fieldKey, schemaType)) {
                    $label.append(' <span class="required">*</span>');
                }
            });
        },
        
        /**
         * Check if field is required for schema type
         */
        isRequiredField: function(fieldKey, schemaType) {
            var requiredFields = {
                'article': ['headline', 'author', 'datePublished'],
                'organization': ['name', 'url'],
                'person': ['name'],
                'product': ['name']
            };
            
            return requiredFields[schemaType] && requiredFields[schemaType].includes(fieldKey);
        },
        
        /**
         * Validate individual field
         */
        validateField: function(e) {
            var $field = $(e.currentTarget);
            var fieldValue = $field.val().trim();
            var fieldName = $field.attr('name');
            var isRequired = $field.prev('label').find('.required').length > 0;
            
            // Remove existing validation styles
            $field.removeClass('khm-seo-field-error khm-seo-field-valid');
            $field.next('.khm-seo-field-message').remove();
            
            if (isRequired && !fieldValue) {
                $field.addClass('khm-seo-field-error');
                $field.after('<span class="khm-seo-field-message error">This field is required</span>');
                return false;
            } else if (fieldValue) {
                // Field-specific validation
                if (this.validateFieldType($field, fieldValue)) {
                    $field.addClass('khm-seo-field-valid');
                    return true;
                } else {
                    $field.addClass('khm-seo-field-error');
                    return false;
                }
            }
            
            return true;
        },
        
        /**
         * Validate field type
         */
        validateFieldType: function($field, value) {
            var fieldType = $field.attr('type') || 'text';
            
            switch (fieldType) {
                case 'url':
                    var urlPattern = /^https?:\/\/.+/;
                    if (!urlPattern.test(value)) {
                        $field.after('<span class="khm-seo-field-message error">Please enter a valid URL</span>');
                        return false;
                    }
                    break;
                    
                case 'date':
                    var datePattern = /^\d{4}-\d{2}-\d{2}$/;
                    if (!datePattern.test(value)) {
                        $field.after('<span class="khm-seo-field-message error">Please enter a valid date (YYYY-MM-DD)</span>');
                        return false;
                    }
                    break;
            }
            
            return true;
        },
        
        /**
         * Validate entire form
         */
        validateForm: function() {
            var isValid = true;
            var $enabledCheckbox = $('#khm_seo_schema_enabled');
            
            if (!$enabledCheckbox.is(':checked')) {
                return true; // Skip validation if schema is disabled
            }
            
            // Validate all visible fields
            $('.khm-seo-schema-type-fields:visible input, .khm-seo-schema-type-fields:visible textarea').each(function() {
                if (!KHMSchemaAdmin.validateField({currentTarget: this})) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                this.showMessage('Please fix the validation errors before saving.', 'error');
                $('html, body').animate({
                    scrollTop: $('.khm-seo-field-error').first().offset().top - 100
                }, 500);
            }
            
            return isValid;
        },
        
        /**
         * Quick Edit functionality
         */
        initQuickEdit: function() {
            $(document).on('click', '.editinline', function() {
                var postId = $(this).closest('tr').attr('id').replace('post-', '');
                var $row = $('#post-' + postId);
                var $schemaColumn = $row.find('.column-khm_schema');
                
                // Get current schema status
                var isEnabled = $schemaColumn.find('.enabled').length > 0;
                var schemaTypeText = $schemaColumn.text();
                var schemaType = '';
                
                // Extract schema type from column text
                $.each(khm_seo_schema.schema_types, function(key, config) {
                    if (schemaTypeText.includes(config.label)) {
                        schemaType = key;
                        return false;
                    }
                });
                
                // Set quick edit form values
                setTimeout(function() {
                    var $quickEditRow = $('#edit-' + postId);
                    $quickEditRow.find('input[name="khm_seo_schema_enabled"]').prop('checked', isEnabled);
                    
                    if (schemaType) {
                        $quickEditRow.find('select[name="khm_seo_schema_type"]').val(schemaType);
                    }
                }, 100);
            });
        },
        
        /**
         * Bulk Edit functionality
         */
        initBulkEdit: function() {
            // Implementation would go here for bulk edit
            // This is more complex and involves modifying WordPress's bulk edit system
        },
        
        /**
         * Handle bulk schema assignment
         */
        handleBulkAssignment: function(e) {
            e.preventDefault();
            
            var postType = $('#bulk-post-type').val();
            var schemaType = $('#bulk-schema-type').val();
            var enableSchema = $('#bulk-enable-schema').is(':checked');
            
            if (!confirm(khm_seo_schema.strings.bulk_update_confirm)) {
                return;
            }
            
            var $button = $(e.currentTarget);
            $button.prop('disabled', true).text('Processing...');
            
            // Get all posts of selected type
            $.post(khm_seo_schema.ajax_url, {
                action: 'khm_seo_get_posts_for_bulk_assignment',
                nonce: khm_seo_schema.nonce,
                post_type: postType
            })
            .done(function(response) {
                if (response.success && response.data.post_ids) {
                    KHMSchemaAdmin.performBulkAssignment(response.data.post_ids, {
                        type: schemaType,
                        enabled: enableSchema
                    }, $button);
                } else {
                    alert('Failed to get posts: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Network error');
            })
            .always(function() {
                $button.prop('disabled', false).text('Apply to All Posts');
            });
        },
        
        /**
         * Perform bulk assignment
         */
        performBulkAssignment: function(postIds, schemaConfig, $button) {
            $.post(khm_seo_schema.ajax_url, {
                action: 'khm_seo_bulk_schema_update',
                nonce: khm_seo_schema.nonce,
                post_ids: postIds,
                schema_config: schemaConfig
            })
            .done(function(response) {
                if (response.success) {
                    KHMSchemaAdmin.showMessage(response.data.message, 'success');
                } else {
                    alert('Bulk update failed: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Network error during bulk update');
            });
        },
        
        /**
         * Clear schema cache
         */
        clearSchemaCache: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all schema cache?')) {
                return;
            }
            
            var $button = $(e.currentTarget);
            $button.prop('disabled', true).text('Clearing...');
            
            $.post(khm_seo_schema.ajax_url, {
                action: 'khm_seo_clear_cache',
                nonce: khm_seo_schema.nonce
            })
            .done(function(response) {
                if (response.success) {
                    KHMSchemaAdmin.showMessage('Cache cleared successfully!', 'success');
                    $('#cache-status').html('Cached items: 0 | Last updated: Never');
                } else {
                    alert('Failed to clear cache: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Network error');
            })
            .always(function() {
                $button.prop('disabled', false).text('Clear Schema Cache');
            });
        },
        
        /**
         * Regenerate cache
         */
        regenerateCache: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            $button.prop('disabled', true).text('Regenerating...');
            
            $.post(khm_seo_schema.ajax_url, {
                action: 'khm_seo_regenerate_cache',
                nonce: khm_seo_schema.nonce
            })
            .done(function(response) {
                if (response.success) {
                    KHMSchemaAdmin.showMessage('Cache regenerated successfully!', 'success');
                    location.reload(); // Refresh to show updated stats
                } else {
                    alert('Failed to regenerate cache: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Network error');
            })
            .always(function() {
                $button.prop('disabled', false).text('Regenerate Cache');
            });
        },
        
        /**
         * Export settings
         */
        exportSettings: function(e) {
            e.preventDefault();
            
            var settings = {
                schema_admin: khm_seo_schema.admin_settings || {},
                timestamp: new Date().toISOString(),
                version: '4.0.0'
            };
            
            var dataStr = JSON.stringify(settings, null, 2);
            var dataBlob = new Blob([dataStr], {type: 'application/json'});
            
            var link = document.createElement('a');
            link.href = URL.createObjectURL(dataBlob);
            link.download = 'khm-seo-schema-settings-' + new Date().toISOString().split('T')[0] + '.json';
            link.click();
            
            this.showMessage('Settings exported successfully!', 'success');
        },
        
        /**
         * Import settings
         */
        importSettings: function(e) {
            var file = e.target.files[0];
            
            if (!file) {
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var settings = JSON.parse(e.target.result);
                    
                    if (!settings.schema_admin) {
                        alert('Invalid settings file format');
                        return;
                    }
                    
                    if (confirm('Are you sure you want to import these settings? Current settings will be overwritten.')) {
                        KHMSchemaAdmin.applyImportedSettings(settings);
                    }
                    
                } catch (error) {
                    alert('Error parsing settings file: ' + error.message);
                }
            };
            
            reader.readAsText(file);
        },
        
        /**
         * Apply imported settings
         */
        applyImportedSettings: function(settings) {
            $.post(khm_seo_schema.ajax_url, {
                action: 'khm_seo_import_settings',
                nonce: khm_seo_schema.nonce,
                settings: settings.schema_admin
            })
            .done(function(response) {
                if (response.success) {
                    KHMSchemaAdmin.showMessage('Settings imported successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    alert('Failed to import settings: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Network error during import');
            });
        },
        
        /**
         * Update schema status indicators
         */
        updateSchemaStatusIndicators: function() {
            $('.column-khm_schema .khm-schema-status').each(function() {
                var $this = $(this);
                if ($this.hasClass('enabled')) {
                    $this.css('color', '#46b450');
                } else {
                    $this.css('color', '#dc3232');
                }
            });
        },
        
        /**
         * Enable code highlighting for textarea
         */
        enableCodeHighlighting: function(textarea) {
            if (typeof wp !== 'undefined' && wp.codeEditor) {
                wp.codeEditor.initialize(textarea, {
                    type: 'application/json',
                    codemirror: {
                        mode: 'application/json',
                        lineNumbers: true,
                        readOnly: true
                    }
                });
            }
        },
        
        /**
         * Show admin message
         */
        showMessage: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Make dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        KHMSchemaAdmin.init();
    });
    
    // Make globally available
    window.KHMSchemaAdmin = KHMSchemaAdmin;
    
})(jQuery);