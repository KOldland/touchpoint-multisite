/**
 * GEO Export Admin JavaScript
 *
 * Handles export functionality in the admin interface
 *
 * @package KHM_SEO
 * @since 2.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // Export form submission
    $('#khm-quick-export-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'khm_geo_export_data');
        formData.append('nonce', khmGeoExport.nonce);

        // Show progress
        $('#khm-export-progress').show();
        $('.khm-export-actions button[type="submit"]').prop('disabled', true);

        // Start export
        $.ajax({
            url: khmGeoExport.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Start polling for status
                    pollExportStatus(response.data.export_id);
                } else {
                    showExportError(response.data || khmGeoExport.strings.error);
                }
            },
            error: function() {
                showExportError(khmGeoExport.strings.error);
            }
        });
    });

    // Scheduled export form submission
    $('#khm-scheduled-export-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'khm_geo_schedule_export');
        formData.append('nonce', khmGeoExport.nonce);

        $.ajax({
            url: khmGeoExport.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Export scheduled successfully!');
                    location.reload();
                } else {
                    alert('Error scheduling export: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error scheduling export');
            }
        });
    });

    // Cancel scheduled export
    $(document).on('click', '.khm-cancel-scheduled', function(e) {
        e.preventDefault();

        var scheduleId = $(this).data('id');
        if (confirm('Cancel this scheduled export?')) {
            $.post(khmGeoExport.ajaxUrl, {
                action: 'khm_geo_cancel_scheduled_export',
                schedule_id: scheduleId,
                nonce: khmGeoExport.nonce
            }, function(response) {
                if (response.success) {
                    $(e.target).closest('.khm-export-item').fadeOut();
                } else {
                    alert('Error canceling scheduled export');
                }
            });
        }
    });

    // Poll export status
    function pollExportStatus(exportId) {
        var pollInterval = setInterval(function() {
            $.post(khmGeoExport.ajaxUrl, {
                action: 'khm_geo_get_export_status',
                export_id: exportId,
                nonce: khmGeoExport.nonce
            }, function(response) {
                if (response.success) {
                    var status = response.data.status;
                    var progress = response.data.progress;

                    updateProgress(progress, status);

                    if (status === 'completed') {
                        clearInterval(pollInterval);
                        showExportComplete(response.data.filename);
                    } else if (status === 'failed') {
                        clearInterval(pollInterval);
                        showExportError('Export failed');
                    }
                } else {
                    clearInterval(pollInterval);
                    showExportError('Error checking export status');
                }
            });
        }, 2000); // Poll every 2 seconds
    }

    // Update progress bar
    function updateProgress(progress, status) {
        var progressBar = $('.khm-progress-bar');
        var progressText = $('.khm-progress-text');

        progressBar.progressbar('value', progress);

        var statusText = '';
        switch (status) {
            case 'preparing':
                statusText = khmGeoExport.strings.preparing;
                break;
            case 'processing':
                statusText = khmGeoExport.strings.exporting;
                break;
            case 'completed':
                statusText = khmGeoExport.strings.complete;
                break;
            default:
                statusText = status;
        }

        progressText.text(statusText + ' (' + progress + '%)');
    }

    // Show export complete
    function showExportComplete(filename) {
        $('#khm-export-progress').hide();
        $('.khm-export-actions button[type="submit"]').prop('disabled', false);

        var message = khmGeoExport.strings.complete;
        if (filename) {
            message += ' File: ' + filename;
        }

        // Show success message and reload to show new export in list
        alert(message);
        location.reload();
    }

    // Show export error
    function showExportError(message) {
        $('#khm-export-progress').hide();
        $('.khm-export-actions button[type="submit"]').prop('disabled', false);
        alert(khmGeoExport.strings.error + ': ' + message);
    }

    // Initialize progress bar
    $('.khm-progress-bar').progressbar({
        value: 0,
        max: 100
    });

    // Data type selection validation
    $('input[name="data_types[]"]').on('change', function() {
        var checkedBoxes = $('input[name="data_types[]"]:checked');
        if (checkedBoxes.length === 0) {
            $('.khm-export-actions button[type="submit"]').prop('disabled', true);
        } else {
            $('.khm-export-actions button[type="submit"]').prop('disabled', false);
        }
    });

    // Large export confirmation
    $('#khm-quick-export-form').on('change', 'input[name="data_types[]"]', function() {
        var checkedBoxes = $('input[name="data_types[]"]:checked');
        if (checkedBoxes.length > 2) {
            if (!confirm(khmGeoExport.strings.confirmLargeExport)) {
                $(this).prop('checked', false);
            }
        }
    });

    // Anonymize data toggle
    $('#khm-anonymize-data').on('change', function() {
        if ($(this).is(':checked')) {
            if (!confirm('Anonymizing data will remove sensitive information. Continue?')) {
                $(this).prop('checked', false);
            }
        }
    });

    // Date range validation
    $('input[type="date"]').on('change', function() {
        var fromDate = $('input[name="date_from"]').val();
        var toDate = $('input[name="date_to"]').val();

        if (fromDate && toDate && fromDate > toDate) {
            alert('From date cannot be after to date');
            $(this).val('');
        }
    });
});