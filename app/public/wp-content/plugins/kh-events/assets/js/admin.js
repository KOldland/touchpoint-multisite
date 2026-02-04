/**
 * KH Events Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin interface
        initSettingsTabs();
        initAjaxForms();
        initQuickEdit();
        initDatePickers();
    });

    /**
     * Initialize settings tabs
     */
    function initSettingsTabs() {
        $('.kh-events-settings-tabs li').on('click', function() {
            var $tab = $(this);
            var tabId = $tab.data('tab');

            // Update active tab
            $('.kh-events-settings-tabs li').removeClass('active');
            $tab.addClass('active');

            // Show corresponding content
            $('.kh-events-settings-section').hide();
            $('#kh-events-' + tabId + '-section').show();
        });

        // Show first tab by default
        $('.kh-events-settings-tabs li:first').trigger('click');
    }

    /**
     * Initialize AJAX forms
     */
    function initAjaxForms() {
        $('.kh-events-settings form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('input[type="submit"]');
            var originalText = $submitBtn.val();

            // Show loading state
            $submitBtn.val(kh_events_admin.strings.saving).prop('disabled', true);

            // Prepare form data
            var formData = new FormData($form[0]);
            formData.append('action', 'kh_events_save_settings');
            formData.append('nonce', kh_events_admin.nonce);

            // Send AJAX request
            $.ajax({
                url: kh_events_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data, 'success');
                    } else {
                        showNotice(response.data || kh_events_admin.strings.error, 'error');
                    }
                },
                error: function() {
                    showNotice(kh_events_admin.strings.error, 'error');
                },
                complete: function() {
                    $submitBtn.val(originalText).prop('disabled', false);
                }
            });
        });
    }

    /**
     * Initialize quick edit functionality
     */
    function initQuickEdit() {
        // Handle quick edit save
        $(document).on('click', '.button-primary.save', function() {
            var $button = $(this);
            var $row = $button.closest('tr');

            // Get form data
            var postId = $row.find('input[name="post_ID"]').val();
            var eventStatus = $row.find('select[name="event_status"]').val();

            // Send AJAX request to save
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'kh_events_quick_edit_save',
                    post_id: postId,
                    event_status: eventStatus,
                    nonce: kh_events_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show updated data
                        location.reload();
                    } else {
                        alert('Error saving changes');
                    }
                }
            });
        });
    }

    /**
     * Initialize date pickers
     */
    function initDatePickers() {
        $('.kh-events-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        var noticeClass = 'notice notice-' + type + ' is-dismissible';
        var $notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');

        $('.wrap h1').after($notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Dashboard functions
     */
    window.KH_Events_Admin = {
        /**
         * Refresh dashboard stats
         */
        refreshStats: function() {
            $.ajax({
                url: kh_events_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'kh_events_refresh_stats',
                    nonce: kh_events_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.kh-events-stats-grid').html(response.data.stats_html);
                    }
                }
            });
        },

        /**
         * Generate report
         */
        generateReport: function(reportType, startDate, endDate) {
            $('.kh-events-report-content').html('<p>Generating report...</p>');

            $.ajax({
                url: kh_events_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'kh_events_generate_report',
                    report_type: reportType,
                    start_date: startDate,
                    end_date: endDate,
                    nonce: kh_events_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.kh-events-report-content').html(response.data.report_html);
                    } else {
                        $('.kh-events-report-content').html('<p>Error generating report</p>');
                    }
                },
                error: function() {
                    $('.kh-events-report-content').html('<p>Error generating report</p>');
                }
            });
        }
    };

})(jQuery);