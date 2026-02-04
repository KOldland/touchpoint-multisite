/**
 * KH Events Status Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Update status description when status changes
    $('#kh_event_status').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var description = selectedOption.data('color');
        var statusKey = $(this).val();

        // Update description text based on status
        var descriptions = {
            'scheduled': 'Event is scheduled to take place',
            'canceled': 'Event has been canceled',
            'postponed': 'Event has been postponed to a later date'
        };

        $('#kh-status-description').text(descriptions[statusKey] || '');

        // Visual feedback - change background color of select
        $(this).css('background-color', description);
    });

    // Set initial description and color
    $('#kh_event_status').trigger('change');
});