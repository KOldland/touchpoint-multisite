/**
 * KH Events Import/Export Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Export form submission
    $('#kh-export-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'kh_export_events');
        formData.append('nonce', kh_import_export_ajax.nonce);

        $('#kh-export-button').prop('disabled', true).text(kh_import_export_ajax.strings.exporting);
        $('.spinner').addClass('is-active');

        $.ajax({
            url: kh_import_export_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#kh-export-button').prop('disabled', false).text('Export Events');
                $('.spinner').removeClass('is-active');

                if (response.success) {
                    // Trigger download
                    var blob = new Blob([response.data], {type: 'text/csv'});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'kh-events-export-' + new Date().toISOString().split('T')[0] + '.csv';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                } else {
                    alert(kh_import_export_ajax.strings.error + ': ' + response.data);
                }
            },
            error: function() {
                $('#kh-export-button').prop('disabled', false).text('Export Events');
                $('.spinner').removeClass('is-active');
                alert(kh_import_export_ajax.strings.error);
            }
        });
    });

    // Import form submission
    $('#kh-import-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'kh_import_events');
        formData.append('nonce', kh_import_export_ajax.nonce);

        $('#kh-import-button').prop('disabled', true).text(kh_import_export_ajax.strings.importing);
        $('.spinner').addClass('is-active');
        $('#kh-import-progress').show();

        $.ajax({
            url: kh_import_export_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#kh-import-button').prop('disabled', false).text('Import Events');
                $('.spinner').removeClass('is-active');
                $('#kh-import-progress').hide();

                if (response.success) {
                    var results = response.data;
                    var message = 'Import complete!\n\n';
                    message += 'Imported: ' + results.imported + '\n';
                    message += 'Skipped: ' + results.skipped + '\n';
                    if (results.errors.length > 0) {
                        message += 'Errors: ' + results.errors.length + '\n';
                        console.log('Import errors:', results.errors);
                    }
                    alert(message);

                    // Refresh page to show new events
                    location.reload();
                } else {
                    alert(kh_import_export_ajax.strings.error + ': ' + response.data);
                }
            },
            error: function() {
                $('#kh-import-button').prop('disabled', false).text('Import Events');
                $('.spinner').removeClass('is-active');
                $('#kh-import-progress').hide();
                alert(kh_import_export_ajax.strings.error);
            }
        });
    });

    // iCal import form submission
    $('#kh-ical-import-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'kh_import_ical');
        formData.append('nonce', kh_import_export_ajax.nonce);

        $('button[type="submit"]', this).prop('disabled', true).text('Importing...');
        $('.spinner', this).addClass('is-active');

        $.ajax({
            url: kh_import_export_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('button[type="submit"]', this).prop('disabled', false).text('Import iCal');
                $('.spinner', this).removeClass('is-active');

                if (response.success) {
                    alert('iCal import complete! Imported ' + response.data.imported + ' events.');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }.bind(this),
            error: function() {
                $('button[type="submit"]', this).prop('disabled', false).text('Import iCal');
                $('.spinner', this).removeClass('is-active');
                alert(kh_import_export_ajax.strings.error);
            }.bind(this)
        });
    });

    // Facebook import form submission
    $('#kh-facebook-import-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'kh_import_facebook');
        formData.append('nonce', kh_import_export_ajax.nonce);

        $('button[type="submit"]', this).prop('disabled', true).text('Importing...');
        $('.spinner', this).addClass('is-active');

        $.ajax({
            url: kh_import_export_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('button[type="submit"]', this).prop('disabled', false).text('Import Facebook Events');
                $('.spinner', this).removeClass('is-active');

                if (response.success) {
                    alert('Facebook import complete! Imported ' + response.data.imported + ' events.');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }.bind(this),
            error: function() {
                $('button[type="submit"]', this).prop('disabled', false).text('Import Facebook Events');
                $('.spinner', this).removeClass('is-active');
                alert(kh_import_export_ajax.strings.error);
            }.bind(this)
        });
    });

    // Toggle date range fields
    $('input[name="date_range"]').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#export_start_date, #export_end_date').prop('disabled', false);
        } else {
            $('#export_start_date, #export_end_date').prop('disabled', true);
        }
    });

    // Initialize date range state
    $('input[name="date_range"]:checked').trigger('change');
});