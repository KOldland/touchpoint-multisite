/**
 * TouchPoint MailChimp Admin JavaScript
 */

(function($) {
    'use strict';

    // Main admin object
    const TMCAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initTooltips();
            this.checkApiConnection();
        },
        
        bindEvents: function() {
            // API connection test
            $(document).on('click', '#tmc-test-connection', this.testConnection);
            
            // User sync actions
            $(document).on('click', '#tmc-sync-users', this.syncUsers);
            $(document).on('click', '.tmc-sync-user', this.syncSingleUser);
            
            // Store sync actions
            $(document).on('click', '#tmc-sync-store', this.syncStore);
            $(document).on('click', '#tmc-sync-orders', this.syncOrders);
            
            // List management
            $(document).on('click', '.tmc-refresh-lists', this.refreshLists);
            $(document).on('change', '#tmc_api_key', this.onApiKeyChange);
            
            // Log viewer
            $(document).on('click', '#tmc-clear-logs', this.clearLogs);
            $(document).on('click', '#tmc-refresh-logs', this.refreshLogs);
            
            // Form validation
            $(document).on('submit', '#tmc-settings-form', this.validateForm);
        },
        
        initTabs: function() {
            $('.tmc-nav-tab').on('click', function(e) {
                e.preventDefault();
                
                const target = $(this).attr('href');
                
                // Update active tab
                $('.tmc-nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show target section
                $('.tmc-tab-content').hide();
                $(target).show();
                
                // Update URL hash
                if (history.pushState) {
                    history.pushState(null, null, target);
                }
            });
            
            // Show active tab on load
            const hash = window.location.hash || '#general';
            $('.tmc-nav-tab[href="' + hash + '"]').trigger('click');
        },
        
        initTooltips: function() {
            // Initialize tooltips if using a tooltip library
            if ($.fn.tooltip) {
                $('.tmc-tooltip').tooltip();
            }
        },
        
        testConnection: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $status = $('#tmc-connection-status');
            const apiKey = $('#tmc_api_key').val();
            
            if (!apiKey) {
                TMCAdmin.showNotice('Please enter an API key first.', 'error');
                return;
            }
            
            $button.prop('disabled', true).text('Testing...');
            $status.html('<span class="tmc-status warning">Testing...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmc_test_connection',
                    api_key: apiKey,
                    nonce: tmc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span class="tmc-status connected">Connected</span>');
                        TMCAdmin.showNotice('Connection successful!', 'success');
                        TMCAdmin.loadLists();
                    } else {
                        $status.html('<span class="tmc-status disconnected">Failed</span>');
                        TMCAdmin.showNotice('Connection failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    $status.html('<span class="tmc-status disconnected">Error</span>');
                    TMCAdmin.showNotice('Connection test failed due to an error.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },
        
        checkApiConnection: function() {
            const apiKey = $('#tmc_api_key').val();
            if (!apiKey) {
                $('#tmc-connection-status').html('<span class="tmc-status disconnected">No API Key</span>');
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmc_check_connection',
                    nonce: tmc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#tmc-connection-status').html('<span class="tmc-status connected">Connected</span>');
                    } else {
                        $('#tmc-connection-status').html('<span class="tmc-status disconnected">Disconnected</span>');
                    }
                }
            });
        },
        
        syncUsers: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $progress = $('#tmc-sync-progress');
            const $progressBar = $progress.find('.tmc-progress-bar');
            const $progressText = $progress.find('.tmc-progress-text');
            
            $button.prop('disabled', true).text('Syncing...');
            $progress.show();
            $progressBar.css('width', '0%');
            $progressText.text('Preparing to sync users...');
            
            TMCAdmin.syncUsersBatch(1, 0, $button, $progress, $progressBar, $progressText);
        },
        
        syncUsersBatch: function(page, totalSynced, $button, $progress, $progressBar, $progressText) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmc_sync_users',
                    page: page,
                    nonce: tmc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        totalSynced += response.data.synced;
                        const progress = Math.min((page * 50) / 1000 * 100, 100); // Estimate progress
                        
                        $progressBar.css('width', progress + '%');
                        $progressText.text(`Synced ${totalSynced} users...`);
                        
                        if (response.data.has_more) {
                            // Continue with next batch
                            TMCAdmin.syncUsersBatch(page + 1, totalSynced, $button, $progress, $progressBar, $progressText);
                        } else {
                            // Sync complete
                            $progressBar.css('width', '100%');
                            $progressText.text(`Sync complete! ${totalSynced} users synced.`);
                            TMCAdmin.showNotice(`Successfully synced ${totalSynced} users.`, 'success');
                            
                            setTimeout(function() {
                                $progress.hide();
                                $button.prop('disabled', false).text('Sync All Users');
                            }, 2000);
                        }
                    } else {
                        TMCAdmin.showNotice('Sync failed: ' + response.data.message, 'error');
                        $progress.hide();
                        $button.prop('disabled', false).text('Sync All Users');
                    }
                },
                error: function() {
                    TMCAdmin.showNotice('Sync failed due to an error.', 'error');
                    $progress.hide();
                    $button.prop('disabled', false).text('Sync All Users');
                }
            });
        },
        
        syncSingleUser: function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const userId = $link.data('user-id');
            const nonce = $link.data('nonce');
            
            $link.text('Syncing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmc_sync_single_user',
                    user_id: userId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $link.text('Sync Now');
                        $link.closest('td').find('.tmc-sync-indicator')
                            .removeClass('error pending')
                            .addClass('synced');
                        TMCAdmin.showNotice('User synced successfully.', 'success');
                    } else {
                        $link.text('Sync Now');
                        TMCAdmin.showNotice('Sync failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    $link.text('Sync Now');
                    TMCAdmin.showNotice('Sync failed due to an error.', 'error');
                }
            });
        },
        
        syncStore: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            $button.prop('disabled', true).text('Syncing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmc_sync_store',
                    nonce: tmc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        TMCAdmin.showNotice('Store synced successfully.', 'success');
                    } else {
                        TMCAdmin.showNotice('Store sync failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    TMCAdmin.showNotice('Store sync failed due to an error.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Sync Store');
                }
            });
        },
        
        syncOrders: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            $button.prop('disabled', true).text('Syncing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmc_sync_orders',
                    page: 1,
                    nonce: tmc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        TMCAdmin.showNotice('Orders synced successfully.', 'success');
                    } else {
                        TMCAdmin.showNotice('Orders sync failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    TMCAdmin.showNotice('Orders sync failed due to an error.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Sync Orders');
                }
            });
        },
        
        loadLists: function() {
            const $listsContainer = $('#tmc-lists-container');
            $listsContainer.html('<p>Loading lists...</p>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmc_get_lists',
                    nonce: tmc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        TMCAdmin.renderLists(response.data.lists);
                    } else {
                        $listsContainer.html('<p>Failed to load lists: ' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $listsContainer.html('<p>Failed to load lists due to an error.</p>');
                }
            });
        },
        
        renderLists: function(lists) {
            const $container = $('#tmc-lists-container');
            let html = '<div class="tmc-list-grid">';
            
            lists.forEach(function(list) {
                html += `
                    <div class="tmc-list-card">
                        <h3 class="tmc-list-name">${list.name}</h3>
                        <div class="tmc-list-stats">
                            <p>Members: ${list.stats.member_count}</p>
                            <p>ID: ${list.id}</p>
                        </div>
                        <div class="tmc-list-actions">
                            <button class="tmc-button secondary tmc-select-list" data-list-id="${list.id}">
                                Set as Default
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            $container.html(html);
            
            // Bind select list event
            $('.tmc-select-list').on('click', function() {
                const listId = $(this).data('list-id');
                $('#tmc_default_list').val(listId);
                TMCAdmin.showNotice('Default list updated.', 'success');
            });
        },
        
        refreshLists: function(e) {
            e.preventDefault();
            TMCAdmin.loadLists();
        },
        
        onApiKeyChange: function() {
            $('#tmc-connection-status').html('<span class="tmc-status warning">Not tested</span>');
        },
        
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all logs?')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmc_clear_logs',
                    nonce: tmc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.tmc-log-viewer').html('Logs cleared.');
                        TMCAdmin.showNotice('Logs cleared successfully.', 'success');
                    } else {
                        TMCAdmin.showNotice('Failed to clear logs.', 'error');
                    }
                }
            });
        },
        
        refreshLogs: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmc_get_logs',
                    nonce: tmc_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.tmc-log-viewer').html(response.data.logs);
                    } else {
                        TMCAdmin.showNotice('Failed to refresh logs.', 'error');
                    }
                }
            });
        },
        
        validateForm: function(e) {
            const apiKey = $('#tmc_api_key').val();
            
            if (!apiKey) {
                e.preventDefault();
                TMCAdmin.showNotice('Please enter an API key.', 'error');
                $('#tmc_api_key').focus();
                return false;
            }
            
            return true;
        },
        
        showNotice: function(message, type) {
            const noticeClass = 'tmc-notice ' + (type || 'info');
            const $notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
            
            // Remove existing notices
            $('.tmc-notice').remove();
            
            // Add new notice
            $('.tmc-admin-wrap').prepend($notice);
            
            // Auto-hide success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
            
            // Scroll to top to show notice
            $('html, body').animate({ scrollTop: 0 }, 500);
        },
        
        // Utility functions
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },
        
        formatDate: function(timestamp) {
            const date = new Date(timestamp * 1000);
            return date.toLocaleString();
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        TMCAdmin.init();
    });
    
    // Export to global scope for external access
    window.TMCAdmin = TMCAdmin;

})(jQuery);