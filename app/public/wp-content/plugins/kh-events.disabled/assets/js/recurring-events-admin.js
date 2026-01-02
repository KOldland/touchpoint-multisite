/**
 * KH Events Recurring Events Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Toggle recurrence options when checkbox is clicked
    $('input[name="kh_event_recurring"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('.kh-recurrence-options').show();
        } else {
            $('.kh-recurrence-options').hide();
        }
    });

    // Update pattern description when pattern changes
    $('#kh_recurrence_pattern').on('change', function() {
        var pattern = $(this).val();
        var descriptions = {
            'daily': 'Repeat every day',
            'weekly': 'Repeat every week',
            'monthly': 'Repeat every month',
            'yearly': 'Repeat every year'
        };
        $('#kh-pattern-description').text(descriptions[pattern] || '');

        // Show/hide weekday options
        if (pattern === 'weekly') {
            $('#kh-weekdays-row').show();
        } else {
            $('#kh-weekdays-row').hide();
        }

        // Show/hide monthly options
        if (pattern === 'monthly') {
            $('#kh-monthly-type-row').show();
        } else {
            $('#kh-monthly-type-row').hide();
        }

        updateIntervalLabel();
        updatePreview();
    });

    // Update interval label when interval changes
    $('#kh_recurrence_interval').on('input change', function() {
        updateIntervalLabel();
        updatePreview();
    });

    // Update preview when weekdays change
    $('input[name="kh_recurrence_weekdays[]"]').on('change', function() {
        updatePreview();
    });

    // Update preview when end options change
    $('input[name="kh_recurrence_end_type"], input[name="kh_recurrence_end_date"], input[name="kh_recurrence_count"]').on('change input', function() {
        updatePreview();
    });

    function updateIntervalLabel() {
        var pattern = $('#kh_recurrence_pattern').val();
        var interval = $('#kh_recurrence_interval').val();
        var labels = {
            'daily': interval == 1 ? 'day' : 'days',
            'weekly': interval == 1 ? 'week' : 'weeks',
            'monthly': interval == 1 ? 'month' : 'months',
            'yearly': interval == 1 ? 'year' : 'years'
        };
        $('#kh-interval-label').text(labels[pattern] || '');
    }

    function updatePreview() {
        var pattern = $('#kh_recurrence_pattern').val();
        var interval = $('#kh_recurrence_interval').val();
        var endType = $('input[name="kh_recurrence_end_type"]:checked').val();
        var endDate = $('input[name="kh_recurrence_end_date"]').val();
        var count = $('input[name="kh_recurrence_count"]').val();
        var weekdays = [];
        $('input[name="kh_recurrence_weekdays[]"]:checked').each(function() {
            weekdays.push($(this).val());
        });

        // Generate preview dates
        var previewDates = generatePreviewDates(pattern, interval, endType, endDate, count, weekdays);
        var previewHtml = '';

        if (previewDates.length > 0) {
            previewHtml = '<ul>';
            previewDates.slice(0, 10).forEach(function(date) {
                previewHtml += '<li>' + date + '</li>';
            });
            if (previewDates.length > 10) {
                previewHtml += '<li>... and ' + (previewDates.length - 10) + ' more</li>';
            }
            previewHtml += '</ul>';
        } else {
            previewHtml = 'No occurrences found';
        }

        $('#kh-recurrence-preview-content').html(previewHtml);
    }

    function generatePreviewDates(pattern, interval, endType, endDate, count, weekdays) {
        var dates = [];
        var startDate = new Date(); // Use current date for preview
        var currentDate = new Date(startDate);
        var maxOccurrences = endType === 'count' ? parseInt(count) : 20;

        for (var i = 0; i < maxOccurrences; i++) {
            if (i > 0) { // Skip the first occurrence (original date)
                dates.push(formatDate(currentDate));
            }

            // Calculate next occurrence
            switch (pattern) {
                case 'daily':
                    currentDate.setDate(currentDate.getDate() + parseInt(interval));
                    break;
                case 'weekly':
                    if (weekdays.length > 0) {
                        // Complex weekly logic would go here
                        currentDate.setDate(currentDate.getDate() + 7);
                    } else {
                        currentDate.setDate(currentDate.getDate() + (7 * parseInt(interval)));
                    }
                    break;
                case 'monthly':
                    currentDate.setMonth(currentDate.getMonth() + parseInt(interval));
                    break;
                case 'yearly':
                    currentDate.setFullYear(currentDate.getFullYear() + parseInt(interval));
                    break;
            }

            // Check end conditions
            if (endType === 'date' && endDate && currentDate > new Date(endDate)) {
                break;
            }
        }

        return dates;
    }

    function formatDate(date) {
        return date.getFullYear() + '-' +
               String(date.getMonth() + 1).padStart(2, '0') + '-' +
               String(date.getDate()).padStart(2, '0');
    }

    // Initialize
    updateIntervalLabel();
    updatePreview();
});