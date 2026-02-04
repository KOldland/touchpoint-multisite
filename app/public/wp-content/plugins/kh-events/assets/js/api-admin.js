/**
 * KH Events API Admin JavaScript
 *
 * Handles API management in the WordPress admin
 */

(function($) {
    'use strict';

    // API Testing
    $('#kh-events-api-test').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $result = $('#kh-events-api-test-result');

        $button.prop('disabled', true).text(kh_events_api.strings.testing);
        $result.html('');

        $.ajax({
            url: kh_events_api.ajax_url,
            type: 'POST',
            data: {
                action: 'kh_events_api_test',
                nonce: kh_events_api.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + kh_events_api.strings.test_success + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + kh_events_api.strings.test_failed + ': ' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>' + kh_events_api.strings.test_failed + ': Network error</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test API Connection');
            }
        });
    });

    // Webhook Testing
    $('.kh-events-webhook-test').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var webhookId = $button.data('id');
        var $row = $button.closest('tr');

        $button.prop('disabled', true).text('Testing...');

        $.ajax({
            url: kh_events_api.ajax_url,
            type: 'POST',
            data: {
                action: 'kh_events_webhook_test',
                webhook_id: webhookId,
                nonce: kh_events_api.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Webhook test successful! Response code: ' + response.data.response_code);
                } else {
                    alert('Webhook test failed: ' + response.data.message);
                }
            },
            error: function() {
                alert('Webhook test failed: Network error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test');
            }
        });
    });

    // Integration Toggle
    $('.kh-events-integration-toggle input').on('change', function() {
        var $toggle = $(this);
        var integrationId = $toggle.data('integration');
        var isChecked = $toggle.is(':checked');
        var $integration = $toggle.closest('.kh-events-integration');

        $integration.addClass('loading');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: isChecked ? 'kh_events_activate_integration' : 'kh_events_deactivate_integration',
                integration_id: integrationId,
                nonce: kh_events_api.nonce
            },
            success: function(response) {
                if (response.success) {
                    $integration.removeClass('inactive').addClass(isChecked ? 'active' : 'inactive');
                } else {
                    alert('Error: ' + response.data.message);
                    $toggle.prop('checked', !isChecked);
                }
            },
            error: function() {
                alert('Network error occurred');
                $toggle.prop('checked', !isChecked);
            },
            complete: function() {
                $integration.removeClass('loading');
            }
        });
    });

    // API Key Generation
    $('#generate-api-key').on('click', function() {
        var newKey = generateRandomKey(32);
        $('input[name="kh_events_api_settings[api_key]"]').val(newKey);
    });

    // Copy to clipboard functionality
    $('.kh-events-copy-to-clipboard').on('click', function(e) {
        e.preventDefault();

        var textToCopy = $(this).data('text') || $(this).prev('code').text();

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy).then(function() {
                showCopyFeedback($(this), 'Copied!');
            });
        } else {
            // Fallback for older browsers
            var textArea = document.createElement('textarea');
            textArea.value = textToCopy;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showCopyFeedback($(this), 'Copied!');
        }
    });

    // Show copy feedback
    function showCopyFeedback($element, message) {
        var originalText = $element.text();
        $element.text(message);
        setTimeout(function() {
            $element.text(originalText);
        }, 2000);
    }

    // Generate random key
    function generateRandomKey(length) {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var result = '';
        for (var i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    // API Logs viewer
    $('#kh-events-view-api-logs').on('click', function(e) {
        e.preventDefault();

        var $modal = $('#kh-events-api-logs-modal');

        if ($modal.length === 0) {
            $modal = $('<div id="kh-events-api-logs-modal" class="kh-events-modal">' +
                '<div class="kh-events-modal-content">' +
                '<div class="kh-events-modal-header">' +
                '<h3>API Request Logs</h3>' +
                '<span class="kh-events-modal-close">&times;</span>' +
                '</div>' +
                '<div class="kh-events-modal-body">' +
                '<div class="kh-events-logs-loading">Loading logs...</div>' +
                '</div>' +
                '</div>' +
                '</div>');

            $('body').append($modal);
        }

        $modal.show();
        loadApiLogs();
    });

    // Load API logs
    function loadApiLogs() {
        $.ajax({
            url: kh_events_api.ajax_url,
            type: 'POST',
            data: {
                action: 'kh_events_get_api_logs',
                nonce: kh_events_api.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayApiLogs(response.data.logs);
                } else {
                    $('.kh-events-modal-body').html('<p>Error loading logs: ' + response.data.message + '</p>');
                }
            },
            error: function() {
                $('.kh-events-modal-body').html('<p>Network error loading logs</p>');
            }
        });
    }

    // Display API logs
    function displayApiLogs(logs) {
        var $body = $('.kh-events-modal-body');

        if (!logs || logs.length === 0) {
            $body.html('<p>No API logs found.</p>');
            return;
        }

        var html = '<table class="widefat striped">' +
            '<thead>' +
            '<tr>' +
            '<th>Time</th>' +
            '<th>Method</th>' +
            '<th>Endpoint</th>' +
            '<th>IP</th>' +
            '<th>Status</th>' +
            '</tr>' +
            '</thead>' +
            '<tbody>';

        logs.forEach(function(log) {
            html += '<tr>' +
                '<td>' + log.timestamp + '</td>' +
                '<td>' + log.method + '</td>' +
                '<td>' + log.endpoint + '</td>' +
                '<td>' + log.ip + '</td>' +
                '<td>' + (log.response_code ? log.response_code : '-') + '</td>' +
                '</tr>';
        });

        html += '</tbody></table>';
        $body.html(html);
    }

    // Modal close
    $(document).on('click', '.kh-events-modal-close', function() {
        $(this).closest('.kh-events-modal').hide();
    });

    $(document).on('click', '.kh-events-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

})(jQuery);