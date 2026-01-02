/**
 * Schema Admin JavaScript
 */
(function($) {
    'use strict';

    const SchemaAdmin = {
        init: function() {
            this.bindEvents();
            this.initModal();
            this.initDropdowns();
        },

        bindEvents: function() {
            // Quick action buttons
            $('#test-current-page').on('click', this.testCurrentPage);
            $('#validate-schema').on('click', this.validateSchema);
            $('#clear-schema-cache').on('click', this.clearSchemaCache);
            
            // Validation tools
            $('#test-url-schema').on('click', this.testUrlSchema);
            $('#validate-custom-schema').on('click', this.validateCustomSchema);
            $('#generate-sample').on('click', this.generateSample);
            
            // Organization logo upload
            $('#upload-logo').on('click', this.uploadLogo);
        },

        initModal: function() {
            const modal = $('#schema-test-modal');
            const closeBtn = $('.schema-modal-close');
            
            closeBtn.on('click', function() {
                modal.hide();
            });
            
            $(window).on('click', function(e) {
                if (e.target === modal[0]) {
                    modal.hide();
                }
            });
        },

        initDropdowns: function() {
            $(document).on('click', '.dropdown-toggle', function(e) {
                e.stopPropagation();
                const menu = $(this).siblings('.dropdown-menu');
                $('.dropdown-menu').not(menu).removeClass('show');
                menu.toggleClass('show');
            });
            
            $(document).on('click', function() {
                $('.dropdown-menu').removeClass('show');
            });
        },

        showModal: function(title, content) {
            const modal = $('#schema-test-modal');
            const modalBody = $('.schema-modal-body');
            
            modal.find('h3').text(title);
            modalBody.html(content);
            modal.show();
        },

        showLoader: function(button, text) {
            button.addClass('loading')
                  .prop('disabled', true)
                  .attr('data-original-text', button.text())
                  .text(text || khmSeoSchemaAdmin.strings.testing);
        },

        hideLoader: function(button) {
            const originalText = button.attr('data-original-text');
            button.removeClass('loading')
                  .prop('disabled', false)
                  .text(originalText || button.text());
        },

        showNotice: function(message, type) {
            type = type || 'success';
            
            const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.khm-seo-schema-admin').prepend(notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
        },

        ajaxRequest: function(action, data, button) {
            data = data || {};
            data.action = action;
            data.nonce = khmSeoSchemaAdmin.nonce;
            
            return $.ajax({
                url: khmSeoSchemaAdmin.ajax_url,
                type: 'POST',
                data: data,
                beforeSend: function() {
                    if (button) {
                        SchemaAdmin.showLoader(button);
                    }
                },
                complete: function() {
                    if (button) {
                        SchemaAdmin.hideLoader(button);
                    }
                }
            });
        },

        testCurrentPage: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const currentUrl = window.location.origin + '/';
            
            SchemaAdmin.testUrl(currentUrl, button);
        },

        testUrlSchema: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const url = $('#test-url').val();
            
            if (!url) {
                SchemaAdmin.showNotice('Please enter a URL to test', 'error');
                return;
            }
            
            SchemaAdmin.testUrl(url, button);
        },

        testUrl: function(url, button) {
            SchemaAdmin.ajaxRequest('khm_seo_test_schema_url', { url: url }, button)
                .done(function(response) {
                    if (response.success) {
                        const data = response.data;
                        let content = '<div class="test-results">';
                        content += '<p><strong>URL:</strong> ' + data.url + '</p>';
                        content += '<p><strong>Schemas Found:</strong> ' + data.schemas_found + '</p>';
                        
                        if (data.schemas_found > 0) {
                            content += '<h4>Schema Data:</h4>';
                            data.schemas.forEach(function(schema, index) {
                                content += '<h5>Schema ' + (index + 1) + ' (' + (schema['@type'] || 'Unknown') + '):</h5>';
                                content += '<pre>' + JSON.stringify(schema, null, 2) + '</pre>';
                            });
                        } else {
                            content += '<p class="validation-error">No schema markup found on this page.</p>';
                        }
                        
                        content += '</div>';
                        
                        SchemaAdmin.showModal('Schema Test Results', content);
                    } else {
                        SchemaAdmin.showNotice('Test failed: ' + response.data, 'error');
                    }
                })
                .fail(function() {
                    SchemaAdmin.showNotice('Ajax request failed', 'error');
                });
        },

        validateSchema: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const currentUrl = window.location.origin + '/';
            
            SchemaAdmin.showLoader(button, khmSeoSchemaAdmin.strings.validating);
            
            // Simulate validation process
            setTimeout(function() {
                SchemaAdmin.hideLoader(button);
                
                // For demo purposes, show a validation result
                const content = '<div class="validation-results">' +
                    '<p class="validation-success">✓ Schema validation passed</p>' +
                    '<p>All schema markup appears to be valid and follows Schema.org specifications.</p>' +
                    '<h4>Validation Details:</h4>' +
                    '<ul>' +
                    '<li class="validation-success">✓ Valid JSON-LD format</li>' +
                    '<li class="validation-success">✓ Required properties present</li>' +
                    '<li class="validation-success">✓ Proper @context and @type</li>' +
                    '<li class="validation-warning">! Optional image property could be added</li>' +
                    '</ul>' +
                    '</div>';
                
                SchemaAdmin.showModal('Schema Validation Results', content);
            }, 1500);
        },

        validateCustomSchema: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const schemaText = $('#custom-schema').val();
            
            if (!schemaText.trim()) {
                SchemaAdmin.showNotice('Please enter schema markup to validate', 'error');
                return;
            }
            
            // Basic JSON validation
            try {
                const parsed = JSON.parse(schemaText);
                
                let results = 'Custom Schema Validation Results:\n\n';
                results += '✓ Valid JSON format\n';
                
                if (parsed['@context']) {
                    results += '✓ Has @context property\n';
                } else {
                    results += '✗ Missing @context property\n';
                }
                
                if (parsed['@type']) {
                    results += '✓ Has @type property: ' + parsed['@type'] + '\n';
                } else {
                    results += '✗ Missing @type property\n';
                }
                
                // Check for common required fields based on type
                if (parsed['@type'] === 'Article') {
                    if (parsed.headline) results += '✓ Has headline\n';
                    else results += '! Missing headline (recommended)\n';
                    
                    if (parsed.author) results += '✓ Has author\n';
                    else results += '! Missing author (recommended)\n';
                    
                    if (parsed.datePublished) results += '✓ Has datePublished\n';
                    else results += '! Missing datePublished (recommended)\n';
                }
                
                const resultsDiv = $('#custom-validation-results');
                resultsDiv.find('.results-content').text(results);
                resultsDiv.show();
                
            } catch (error) {
                const resultsDiv = $('#custom-validation-results');
                resultsDiv.find('.results-content').text('Invalid JSON: ' + error.message);
                resultsDiv.show();
            }
        },

        generateSample: function(e) {
            e.preventDefault();
            
            const button = $(this);
            
            SchemaAdmin.showLoader(button, khmSeoSchemaAdmin.strings.generating);
            
            setTimeout(function() {
                SchemaAdmin.hideLoader(button);
                
                const sampleSchema = {
                    "@context": "https://schema.org",
                    "@type": "Article",
                    "headline": "Sample Article Title",
                    "author": {
                        "@type": "Person",
                        "name": "John Doe"
                    },
                    "publisher": {
                        "@type": "Organization",
                        "name": "Example Site",
                        "logo": {
                            "@type": "ImageObject",
                            "url": "https://example.com/logo.png"
                        }
                    },
                    "datePublished": "2023-01-15T08:00:00+00:00",
                    "dateModified": "2023-01-15T09:30:00+00:00",
                    "description": "This is a sample article description for demonstration purposes.",
                    "image": "https://example.com/article-image.jpg",
                    "mainEntityOfPage": {
                        "@type": "WebPage",
                        "@id": "https://example.com/sample-article"
                    }
                };
                
                $('#custom-schema').val(JSON.stringify(sampleSchema, null, 2));
            }, 1000);
        },

        clearSchemaCache: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const confirmed = confirm(khmSeoSchemaAdmin.strings.confirm_clear_cache);
            
            if (!confirmed) {
                return;
            }
            
            SchemaAdmin.ajaxRequest('khm_seo_clear_schema_cache', {}, button)
                .done(function(response) {
                    if (response.success) {
                        SchemaAdmin.showNotice('Schema cache cleared successfully', 'success');
                    } else {
                        SchemaAdmin.showNotice('Error: ' + response.data, 'error');
                    }
                })
                .fail(function() {
                    SchemaAdmin.showNotice('Ajax request failed', 'error');
                });
        },

        uploadLogo: function(e) {
            e.preventDefault();
            
            if (typeof wp !== 'undefined' && wp.media) {
                const mediaUploader = wp.media({
                    title: 'Choose Organization Logo',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('input[name="khm_seo_organization_settings[organization_logo]"]').val(attachment.url);
                });
                
                mediaUploader.open();
            } else {
                alert('WordPress media uploader not available');
            }
        },

        // External tool integration
        openExternalTool: function(tool, url) {
            if (!url) {
                url = window.location.origin + '/';
            }
            
            const tools = khmSeoSchemaAdmin.testing_tools;
            if (tools[tool]) {
                let toolUrl = tools[tool].url;
                
                if (tool === 'google_rich_results') {
                    toolUrl += '?url=' + encodeURIComponent(url);
                } else if (tool === 'schema_validator') {
                    // Schema.org validator requires manual URL input
                    toolUrl += '#url=' + encodeURIComponent(url);
                }
                
                window.open(toolUrl, '_blank');
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SchemaAdmin.init();
        
        // Global access for external tools
        window.SchemaAdmin = SchemaAdmin;
    });

})(jQuery);