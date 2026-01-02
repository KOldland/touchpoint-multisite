/**
 * KHM SEO Validation Admin JavaScript
 * 
 * Handles validation interface interactions, AJAX requests, and real-time feedback
 * for schema validation and testing functionality.
 */

(function($) {
    'use strict';
    
    const KHMValidation = {
        
        // Cache DOM elements
        $tabs: null,
        $tabContents: null,
        $loadingOverlay: null,
        
        // Initialize the validation interface
        init: function() {
            this.cacheDOMElements();
            this.bindEvents();
            this.initializeTabs();
            
            console.log('KHM Validation Admin initialized');
        },
        
        // Cache frequently used DOM elements
        cacheDOMElements: function() {
            this.$tabs = $('.nav-tab');
            this.$tabContents = $('.tab-content');
            this.$loadingOverlay = $('#loading-overlay');
        },
        
        // Bind event handlers
        bindEvents: function() {
            // Tab navigation
            this.$tabs.on('click', this.handleTabClick.bind(this));
            
            // Single validation
            $('#validate-single').on('click', this.handleSingleValidation.bind(this));
            $('#validate-url').on('click', this.handleUrlValidation.bind(this));
            
            // Bulk validation
            $('#start-bulk-validation').on('click', this.handleBulkValidation.bind(this));
            
            // Rich Results testing
            $('#test-rich-results').on('click', this.handleRichResultsTest.bind(this));
            
            // Debug tools
            $('#start-debug').on('click', this.handleDebugSchema.bind(this));
            $('#copy-debug').on('click', this.handleCopyDebug.bind(this));
            $('#download-debug').on('click', this.handleDownloadDebug.bind(this));
            
            // Reports
            $('#generate-report').on('click', this.handleGenerateReport.bind(this));
            $('#export-report').on('click', this.handleExportReport.bind(this));
            
            // Bulk results details
            $(document).on('click', '.view-details', this.handleViewDetails.bind(this));
            
            // Enter key handling for inputs
            $('#validation-post-id, #validation-url').on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    if ($(this).attr('id') === 'validation-post-id') {
                        $('#validate-single').click();
                    } else {
                        $('#validate-url').click();
                    }
                }
            });
        },
        
        // Initialize tab functionality
        initializeTabs: function() {
            // Set first tab as active if none is active
            if (!this.$tabs.hasClass('nav-tab-active')) {
                this.$tabs.first().addClass('nav-tab-active');
                this.$tabContents.first().addClass('active');
            }
        },
        
        // Handle tab navigation
        handleTabClick: function(e) {
            e.preventDefault();
            
            const $clickedTab = $(e.currentTarget);
            const targetTab = $clickedTab.data('tab');
            
            // Update tab appearance
            this.$tabs.removeClass('nav-tab-active');
            $clickedTab.addClass('nav-tab-active');
            
            // Show corresponding content
            this.$tabContents.removeClass('active');
            $('#' + targetTab).addClass('active');
        },
        
        // Handle single post/page validation
        handleSingleValidation: function() {
            const postId = $('#validation-post-id').val();
            
            if (!postId) {
                this.showNotice('Please enter a valid Post ID.', 'error');
                return;
            }
            
            this.showLoading('Validating schema...');
            
            // Get post schema data first
            this.getPostSchema(postId).then((schemaData) => {
                if (!schemaData) {
                    this.hideLoading();
                    this.showNotice('No schema data found for this post.', 'warning');
                    return;
                }
                
                // Validate the schema
                this.validateSchema(schemaData).then((result) => {
                    this.hideLoading();
                    this.displayValidationResults(result);
                }).catch((error) => {
                    this.hideLoading();
                    this.showNotice('Validation error: ' + error.message, 'error');
                });
                
            }).catch((error) => {
                this.hideLoading();
                this.showNotice('Error retrieving schema: ' + error.message, 'error');
            });
        },
        
        // Handle URL validation
        handleUrlValidation: function() {
            const url = $('#validation-url').val();
            
            if (!url || !this.isValidUrl(url)) {
                this.showNotice('Please enter a valid URL.', 'error');
                return;
            }
            
            this.showLoading('Fetching and validating URL...');
            
            this.validateUrl(url).then((result) => {
                this.hideLoading();
                this.displayValidationResults(result);
            }).catch((error) => {
                this.hideLoading();
                this.showNotice('URL validation error: ' + error.message, 'error');
            });
        },
        
        // Handle bulk validation
        handleBulkValidation: function() {
            const postType = $('#bulk-post-type').val();
            const limit = parseInt($('#bulk-limit').val()) || 50;
            
            this.showLoading('Starting bulk validation...');
            
            this.getBulkPosts(postType, limit).then((posts) => {
                if (!posts || posts.length === 0) {
                    this.hideLoading();
                    this.showNotice('No posts found for validation.', 'warning');
                    return;
                }
                
                this.processBulkValidation(posts);
                
            }).catch((error) => {
                this.hideLoading();
                this.showNotice('Error starting bulk validation: ' + error.message, 'error');
            });
        },
        
        // Process bulk validation
        processBulkValidation: function(posts) {
            this.hideLoading();
            
            const $progress = $('#bulk-progress');
            const $progressFill = $('.progress-fill');
            const $currentProgress = $('.current-progress');
            const $totalProgress = $('.total-progress');
            const $resultsTable = $('#bulk-results-tbody');
            
            $progress.show();
            $totalProgress.text(posts.length);
            $currentProgress.text(0);
            $resultsTable.empty();
            
            let completedCount = 0;
            
            // Process posts in chunks to avoid overwhelming the server
            const chunkSize = 5;
            const processChunk = (startIndex) => {
                const chunk = posts.slice(startIndex, startIndex + chunkSize);
                const promises = chunk.map(post => this.validateSinglePost(post.ID));
                
                Promise.allSettled(promises).then((results) => {
                    results.forEach((result, index) => {
                        completedCount++;
                        
                        const post = chunk[index];
                        const validationResult = result.status === 'fulfilled' ? result.value : null;
                        
                        // Update progress
                        const progressPercent = (completedCount / posts.length) * 100;
                        $progressFill.css('width', progressPercent + '%');
                        $currentProgress.text(completedCount);
                        
                        // Add result to table
                        this.addBulkResultRow(post, validationResult);
                    });
                    
                    // Process next chunk if there are more posts
                    if (startIndex + chunkSize < posts.length) {
                        setTimeout(() => processChunk(startIndex + chunkSize), 500);
                    } else {
                        // All done
                        this.showNotice(`Bulk validation completed. ${completedCount} posts processed.`, 'success');
                    }
                });
            };
            
            // Start processing
            processChunk(0);
        },
        
        // Handle Rich Results testing
        handleRichResultsTest: function() {
            const url = $('#rich-results-url').val();
            
            if (!url || !this.isValidUrl(url)) {
                this.showNotice('Please enter a valid URL.', 'error');
                return;
            }
            
            this.showLoading('Testing Rich Results...');
            
            this.testRichResults(url).then((result) => {
                this.hideLoading();
                this.displayRichResultsOutput(result);
            }).catch((error) => {
                this.hideLoading();
                this.showNotice('Rich Results test error: ' + error.message, 'error');
            });
        },
        
        // Handle schema debugging
        handleDebugSchema: function() {
            const postId = $('#debug-post-id').val();
            
            if (!postId) {
                this.showNotice('Please enter a valid Post ID.', 'error');
                return;
            }
            
            this.showLoading('Debugging schema...');
            
            this.debugSchema(postId).then((result) => {
                this.hideLoading();
                this.displayDebugOutput(result);
            }).catch((error) => {
                this.hideLoading();
                this.showNotice('Debug error: ' + error.message, 'error');
            });
        },
        
        // Handle copying debug info
        handleCopyDebug: function() {
            const debugData = $('#debug-data').text();
            
            if (!debugData) {
                this.showNotice('No debug data to copy.', 'warning');
                return;
            }
            
            navigator.clipboard.writeText(debugData).then(() => {
                this.showNotice('Debug information copied to clipboard.', 'success');
            }).catch(() => {
                this.showNotice('Failed to copy to clipboard.', 'error');
            });
        },
        
        // Handle downloading debug report
        handleDownloadDebug: function() {
            const debugData = $('#debug-data').text();
            
            if (!debugData) {
                this.showNotice('No debug data to download.', 'warning');
                return;
            }
            
            const blob = new Blob([debugData], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            
            a.style.display = 'none';
            a.href = url;
            a.download = 'khm-seo-debug-' + new Date().toISOString().slice(0, 10) + '.txt';
            
            document.body.appendChild(a);
            a.click();
            
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            this.showNotice('Debug report downloaded.', 'success');
        },
        
        // Generate validation report
        handleGenerateReport: function() {
            const timeframe = $('#report-timeframe').val();
            
            this.showLoading('Generating validation report...');
            
            // Implementation would fetch validation data and generate charts
            setTimeout(() => {
                this.hideLoading();
                this.showNotice('Report generation is not yet implemented.', 'info');
            }, 1000);
        },
        
        // Export validation report
        handleExportReport: function() {
            this.showNotice('Report export is not yet implemented.', 'info');
        },
        
        // Handle viewing bulk validation details
        handleViewDetails: function(e) {
            const postId = $(e.currentTarget).data('post-id');
            
            // Could open a modal or navigate to detailed view
            this.showNotice(`Detailed view for post ${postId} would open here.`, 'info');
        },
        
        // API Methods
        
        // Get post schema data
        getPostSchema: function(postId) {
            return new Promise((resolve, reject) => {
                $.post(khmValidation.ajaxUrl, {
                    action: 'khm_get_post_schema',
                    nonce: khmValidation.nonce,
                    post_id: postId
                }).done((response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Failed to get post schema'));
                    }
                }).fail((jqXHR) => {
                    reject(new Error('AJAX request failed: ' + jqXHR.statusText));
                });
            });
        },
        
        // Validate schema data
        validateSchema: function(schemaData, schemaType = null) {
            return new Promise((resolve, reject) => {
                $.post(khmValidation.ajaxUrl, {
                    action: 'khm_validate_schema',
                    nonce: khmValidation.nonce,
                    schema_data: JSON.stringify(schemaData),
                    schema_type: schemaType
                }).done((response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Validation failed'));
                    }
                }).fail((jqXHR) => {
                    reject(new Error('AJAX request failed: ' + jqXHR.statusText));
                });
            });
        },
        
        // Validate URL
        validateUrl: function(url) {
            return new Promise((resolve, reject) => {
                $.post(khmValidation.ajaxUrl, {
                    action: 'khm_validate_url',
                    nonce: khmValidation.nonce,
                    url: url
                }).done((response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'URL validation failed'));
                    }
                }).fail((jqXHR) => {
                    reject(new Error('AJAX request failed: ' + jqXHR.statusText));
                });
            });
        },
        
        // Get posts for bulk validation
        getBulkPosts: function(postType, limit) {
            return new Promise((resolve, reject) => {
                $.post(khmValidation.ajaxUrl, {
                    action: 'khm_get_bulk_posts',
                    nonce: khmValidation.nonce,
                    post_type: postType,
                    limit: limit
                }).done((response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Failed to get posts'));
                    }
                }).fail((jqXHR) => {
                    reject(new Error('AJAX request failed: ' + jqXHR.statusText));
                });
            });
        },
        
        // Validate single post for bulk operation
        validateSinglePost: function(postId) {
            return new Promise((resolve, reject) => {
                $.post(khmValidation.ajaxUrl, {
                    action: 'khm_validate_single_post',
                    nonce: khmValidation.nonce,
                    post_id: postId
                }).done((response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Post validation failed'));
                    }
                }).fail((jqXHR) => {
                    reject(new Error('AJAX request failed: ' + jqXHR.statusText));
                });
            });
        },
        
        // Test Rich Results
        testRichResults: function(url) {
            return new Promise((resolve, reject) => {
                $.post(khmValidation.ajaxUrl, {
                    action: 'khm_test_rich_results',
                    nonce: khmValidation.nonce,
                    url: url
                }).done((response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Rich Results test failed'));
                    }
                }).fail((jqXHR) => {
                    reject(new Error('AJAX request failed: ' + jqXHR.statusText));
                });
            });
        },
        
        // Debug schema
        debugSchema: function(postId) {
            return new Promise((resolve, reject) => {
                $.post(khmValidation.ajaxUrl, {
                    action: 'khm_debug_schema',
                    nonce: khmValidation.nonce,
                    post_id: postId
                }).done((response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Schema debugging failed'));
                    }
                }).fail((jqXHR) => {
                    reject(new Error('AJAX request failed: ' + jqXHR.statusText));
                });
            });
        },
        
        // Display Methods
        
        // Display validation results
        displayValidationResults: function(results) {
            const $resultsContainer = $('#validation-results');
            const $scoreNumber = $('.score-number');
            const $scoreCircle = $('.score-circle');
            
            // Show results container
            $resultsContainer.show();
            
            // Update score
            $scoreNumber.text(Math.round(results.score || 0));
            
            // Update score circle color
            $scoreCircle.removeClass('score-good score-warning score-error');
            if (results.score >= 80) {
                $scoreCircle.addClass('score-good');
            } else if (results.score >= 60) {
                $scoreCircle.addClass('score-warning');
            } else {
                $scoreCircle.addClass('score-error');
            }
            
            // Display errors
            this.displayValidationList('.errors', results.errors || [], 'error');
            
            // Display warnings
            this.displayValidationList('.warnings', results.warnings || [], 'warning');
            
            // Display Rich Results
            this.displayValidationList('.rich-results', results.rich_results || [], 'rich-result');
            
            // Display suggestions
            this.displayValidationList('.suggestions', results.suggestions || [], 'suggestion');
        },
        
        // Display validation list (errors, warnings, etc.)
        displayValidationList: function(containerSelector, items, type) {
            const $container = $(containerSelector);
            const $list = $container.find('.validation-list');
            
            $list.empty();
            
            if (items.length > 0) {
                $container.show();
                
                items.forEach((item) => {
                    const $item = $('<li>').addClass('validation-item').addClass(type);
                    
                    if (item.type) {
                        $item.append($('<strong>').addClass('item-type').text(item.type));
                    }
                    
                    if (item.message || item.description) {
                        $item.append($('<span>').addClass('item-message').text(item.message || item.description));
                    }
                    
                    if (item.severity) {
                        $item.append($('<span>').addClass('item-severity').addClass('severity-' + item.severity).text(item.severity));
                    }
                    
                    if (type === 'rich-result' && typeof item.eligible !== 'undefined') {
                        const status = item.eligible ? '✓ Eligible' : '✗ Not Eligible';
                        $item.append($('<span>').addClass('item-status').text(status));
                        $item.addClass(item.eligible ? 'eligible' : 'not-eligible');
                    }
                    
                    $list.append($item);
                });
            } else {
                $container.hide();
            }
        },
        
        // Display Rich Results test output
        displayRichResultsOutput: function(results) {
            const $output = $('#rich-results-output');
            const $content = $('.rich-results-content');
            
            $output.show();
            $content.empty();
            
            if (results.success) {
                $content.append('<div class="rich-results-success">✓ Rich Results test completed successfully</div>');
            } else {
                $content.append('<div class="rich-results-error">✗ Rich Results test failed</div>');
            }
            
            // Display found rich results
            if (results.rich_results && results.rich_results.length > 0) {
                const $richResultsList = $('<ul>').addClass('rich-results-list');
                results.rich_results.forEach((result) => {
                    const $item = $('<li>').addClass('rich-result-item');
                    $item.html(`<strong>${result.type}:</strong> ${result.description}`);
                    $richResultsList.append($item);
                });
                $content.append('<h4>Rich Results Found:</h4>');
                $content.append($richResultsList);
            }
            
            // Display errors and warnings
            if (results.errors && results.errors.length > 0) {
                const $errorsList = $('<ul>').addClass('rich-results-errors');
                results.errors.forEach((error) => {
                    $errorsList.append(`<li class="error-item">${error.message}</li>`);
                });
                $content.append('<h4>Errors:</h4>');
                $content.append($errorsList);
            }
        },
        
        // Display debug output
        displayDebugOutput: function(debugData) {
            const $output = $('#debug-output');
            const $debugDataContainer = $('#debug-data');
            
            $output.show();
            $debugDataContainer.text(JSON.stringify(debugData, null, 2));
        },
        
        // Add bulk validation result row
        addBulkResultRow: function(post, validationResult) {
            const $tbody = $('#bulk-results-tbody');
            
            // Calculate error and warning counts
            let errorCount = 0;
            let warningCount = 0;
            let overallScore = 0;
            let hasErrors = false;
            
            if (validationResult) {
                errorCount = (validationResult.errors || []).length;
                warningCount = (validationResult.warnings || []).length;
                overallScore = validationResult.score || 0;
                hasErrors = errorCount > 0;
            }
            
            const $row = $('<tr>').addClass('bulk-result-row');
            
            // Post title and actions
            const $titleCell = $('<td>');
            $titleCell.append(`<strong>${post.post_title}</strong>`);
            const $actions = $('<div>').addClass('row-actions');
            $actions.html(`
                <span><a href="post.php?post=${post.ID}&action=edit">Edit</a> |</span>
                <span><a href="${post.permalink}" target="_blank">View</a></span>
            `);
            $titleCell.append($actions);
            $row.append($titleCell);
            
            // Post type
            $row.append($('<td>').text(post.post_type));
            
            // Score
            const scoreClass = overallScore >= 80 ? 'good' : overallScore >= 60 ? 'warning' : 'error';
            const $scoreCell = $('<td>');
            $scoreCell.append(`<span class="score-badge score-${scoreClass}">${overallScore.toFixed(1)}%</span>`);
            $row.append($scoreCell);
            
            // Status
            const statusClass = hasErrors ? 'error' : 'success';
            const statusText = hasErrors ? 'Issues Found' : 'Valid';
            const $statusCell = $('<td>');
            $statusCell.append(`<span class="status-badge status-${statusClass}">${statusText}</span>`);
            $row.append($statusCell);
            
            // Issues
            $row.append($('<td>').text(`${errorCount} errors, ${warningCount} warnings`));
            
            // Actions
            const $actionsCell = $('<td>');
            $actionsCell.append(`<button type="button" class="button button-small view-details" data-post-id="${post.ID}">View Details</button>`);
            $row.append($actionsCell);
            
            $tbody.append($row);
        },
        
        // Utility Methods
        
        // Show loading overlay
        showLoading: function(message = 'Loading...') {
            this.$loadingOverlay.find('.loading-text').text(message);
            this.$loadingOverlay.show();
        },
        
        // Hide loading overlay
        hideLoading: function() {
            this.$loadingOverlay.hide();
        },
        
        // Show admin notice
        showNotice: function(message, type = 'info') {
            const $notice = $('<div>').addClass(`notice notice-${type} is-dismissible`);
            $notice.append(`<p>${message}</p>`);
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            $('.wrap').prepend($notice);
            
            // Auto-dismiss after 5 seconds for non-error messages
            if (type !== 'error') {
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 5000);
            }
            
            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.remove();
            });
        },
        
        // Validate URL format
        isValidUrl: function(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        KHMValidation.init();
    });
    
    // Expose to global scope for external access
    window.KHMValidation = KHMValidation;
    
})(jQuery);