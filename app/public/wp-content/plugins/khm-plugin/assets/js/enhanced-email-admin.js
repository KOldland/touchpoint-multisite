/**
 * Enhanced Email Admin JavaScript
 *
 * Handles admin interface interactions for enhanced email system
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var EmailAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initCharts();
        },
        
        bindEvents: function() {
            // Test email functionality
            $('#send-test-email').on('click', this.sendTestEmail);
            
            // Process queue functionality
            $('#process-queue-now').on('click', this.processQueue);
            $('#clear-failed-queue').on('click', this.clearFailedQueue);
            
            // API provider change
            $('#api-provider').on('change', this.handleApiProviderChange);
            
            // Auto-refresh stats
            this.startStatsRefresh();
        },
        
        sendTestEmail: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#test-email-result');
            var emailAddress = $('#test-email-address').val();
            
            if (!emailAddress || !EmailAdmin.isValidEmail(emailAddress)) {
                $result.html('<div class="notice notice-error"><p>Please enter a valid email address.</p></div>');
                return;
            }
            
            $button.prop('disabled', true).text(khmEmailAdmin.strings.loading);
            $result.empty();
            
            $.ajax({
                url: khmEmailAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_test_email',
                    email: emailAddress,
                    nonce: khmEmailAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + 
                                   khmEmailAdmin.strings.testEmailSent + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + 
                                   (response.data || khmEmailAdmin.strings.testEmailFailed) + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error"><p>' + 
                               khmEmailAdmin.strings.testEmailFailed + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Send Test Email');
                }
            });
        },
        
        processQueue: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#queue-process-result');
            
            $button.prop('disabled', true).text(khmEmailAdmin.strings.loading);
            $result.empty();
            
            $.ajax({
                url: khmEmailAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_process_email_queue',
                    nonce: khmEmailAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + 
                                   khmEmailAdmin.strings.queueProcessed + '</p></div>');
                        // Refresh queue stats
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + 
                                   (response.data || khmEmailAdmin.strings.queueProcessFailed) + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error"><p>' + 
                               khmEmailAdmin.strings.queueProcessFailed + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Process Queue Now');
                }
            });
        },
        
        clearFailedQueue: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all failed emails from the queue?')) {
                return;
            }
            
            var $button = $(this);
            
            $button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: khmEmailAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_clear_failed_queue',
                    nonce: khmEmailAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to clear queue: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Failed to clear queue: Network error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clear Failed Emails');
                }
            });
        },
        
        handleApiProviderChange: function() {
            var provider = $(this).val();
            
            if (provider === 'mailgun') {
                $('.mailgun-only').slideDown();
            } else {
                $('.mailgun-only').slideUp();
            }
        },
        
        startStatsRefresh: function() {
            // Auto-refresh stats every 30 seconds if on stats tab
            if (window.location.href.indexOf('tab=stats') !== -1) {
                setInterval(function() {
                    EmailAdmin.refreshStats();
                }, 30000);
            }
        },
        
        refreshStats: function() {
            $.ajax({
                url: khmEmailAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_email_stats',
                    nonce: khmEmailAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        EmailAdmin.updateStatsDisplay(response.data);
                    }
                }
            });
        },
        
        updateStatsDisplay: function(stats) {
            $('.khm-email-stats-overview .stat-number').each(function(index) {
                var newValue = '';
                switch(index) {
                    case 0: newValue = EmailAdmin.formatNumber(stats.sent); break;
                    case 1: newValue = EmailAdmin.formatNumber(stats.failed); break;
                    case 2: newValue = EmailAdmin.formatNumber(stats.success_rate, 1) + '%'; break;
                }
                $(this).text(newValue);
            });
        },
        
        initCharts: function() {
            // Initialize any charts if Chart.js is available
            if (typeof Chart !== 'undefined') {
                this.initDeliveryChart();
            }
        },
        
        initDeliveryChart: function() {
            var ctx = document.getElementById('delivery-chart');
            if (!ctx) return;
            
            // Sample chart - replace with real data
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Sent', 'Failed', 'Pending'],
                    datasets: [{
                        data: [120, 15, 8],
                        backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'bottom'
                    }
                }
            });
        },
        
        // Utility functions
        isValidEmail: function(email) {
            var regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return regex.test(email);
        },
        
        formatNumber: function(num, decimals) {
            decimals = decimals || 0;
            return parseFloat(num).toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }
    };
    
    // Initialize the email admin
    EmailAdmin.init();
    
    // Expose to global scope for debugging
    window.EmailAdmin = EmailAdmin;
    
    // Handle form submissions with loading states
    $('.khm-email-admin form').on('submit', function() {
        var $form = $(this);
        var $submit = $form.find('input[type="submit"]');
        
        $submit.prop('disabled', true);
        $submit.after('<span class="spinner is-active" style="float: none; margin-left: 10px;"></span>');
        
        // Re-enable after a delay (WordPress will redirect on success)
        setTimeout(function() {
            $submit.prop('disabled', false);
            $form.find('.spinner').remove();
        }, 5000);
    });
    
    // Enhanced tooltips
    $('.khm-email-admin [title]').each(function() {
        $(this).tooltip({
            position: { my: "left+15 center", at: "right center" },
            tooltipClass: "khm-tooltip"
        });
    });
    
    // Real-time validation for required fields
    $('.khm-email-admin input[type="email"]').on('blur', function() {
        var $field = $(this);
        var $wrapper = $field.closest('td');
        
        $wrapper.find('.validation-message').remove();
        
        if ($field.val() && !EmailAdmin.isValidEmail($field.val())) {
            $wrapper.append('<div class="validation-message error">Please enter a valid email address.</div>');
        } else if ($field.val() && EmailAdmin.isValidEmail($field.val())) {
            $wrapper.append('<div class="validation-message success">✓ Valid email address</div>');
        }
    });
    
    // Dynamic settings visibility
    $('select[name="khm_email_delivery_method"]').on('change', function() {
        var method = $(this).val();
        var $tabs = $('.nav-tab-wrapper .nav-tab');
        
        // Show/hide relevant tabs based on delivery method
        if (method === 'smtp') {
            $tabs.filter('[href*="tab=smtp"]').removeClass('disabled').attr('title', '');
        } else {
            $tabs.filter('[href*="tab=smtp"]').addClass('disabled').attr('title', 'SMTP not selected as delivery method');
        }
        
        if (method === 'api') {
            $tabs.filter('[href*="tab=api"]').removeClass('disabled').attr('title', '');
        } else {
            $tabs.filter('[href*="tab=api"]').addClass('disabled').attr('title', 'API not selected as delivery method');
        }
    }).trigger('change');
    
    // Connection testing for SMTP
    if ($('#test-smtp-connection').length) {
        $('#test-smtp-connection').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#smtp-test-result');
            
            $button.prop('disabled', true).text('Testing...');
            $result.empty();
            
            var smtpData = {
                host: $('[name="khm_smtp_host"]').val(),
                port: $('[name="khm_smtp_port"]').val(),
                encryption: $('[name="khm_smtp_encryption"]').val(),
                username: $('[name="khm_smtp_username"]').val(),
                password: $('[name="khm_smtp_password"]').val()
            };
            
            $.ajax({
                url: khmEmailAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'khm_test_smtp_connection',
                    smtp_data: smtpData,
                    nonce: khmEmailAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>SMTP connection successful!</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>SMTP connection failed: ' + 
                                   (response.data || 'Unknown error') + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error"><p>Connection test failed.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        });
    }
});