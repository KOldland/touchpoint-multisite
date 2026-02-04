/**
 * KH Events Timezone Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Timezone preview in event editor
    function updateTimezonePreview() {
        var $select = $('#kh_event_timezone');
        var $preview = $('#timezone-preview');

        if ($select.length && $preview.length) {
            var timezone = $select.val();
            if (timezone) {
                $.ajax({
                    url: kh_timezone_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kh_get_timezone_info',
                        timezone: timezone,
                        nonce: kh_timezone_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            $preview.find('#timezone-current-time').html(
                                '<strong>Current time:</strong> ' + data.current_time + ' ' + data.abbr +
                                ' (UTC' + (data.offset >= 0 ? '+' : '') + data.offset + ')'
                            );
                        }
                    },
                    error: function() {
                        $preview.find('#timezone-current-time').html('<em>Error loading timezone info</em>');
                    }
                });
            }
        }
    }

    // Bind timezone change event
    $(document).on('change', '#kh_event_timezone', updateTimezonePreview);

    // Initialize on page load
    updateTimezonePreview();

    // User timezone selector in profile
    function initUserTimezoneSelector() {
        var $selector = $('#kh-user-timezone-selector');
        if ($selector.length) {
            $selector.on('change', function() {
                var timezone = $(this).val();
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kh_save_user_timezone',
                        timezone: timezone,
                        nonce: kh_timezone_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            var $message = $('<div class="notice notice-success is-dismissible"><p>Timezone preference saved.</p></div>');
                            $selector.after($message);
                            setTimeout(function() {
                                $message.fadeOut();
                            }, 3000);
                        }
                    }
                });
            });
        }
    }

    initUserTimezoneSelector();

    // Timezone converter tool
    function initTimezoneConverter() {
        var $converter = $('#kh-timezone-converter');
        if ($converter.length) {
            var $datetime = $converter.find('#converter-datetime');
            var $fromTz = $converter.find('#converter-from-timezone');
            var $toTz = $converter.find('#converter-to-timezone');
            var $result = $converter.find('#converter-result');
            var $convertBtn = $converter.find('#convert-timezone-btn');

            $convertBtn.on('click', function() {
                var datetime = $datetime.val();
                var fromTz = $fromTz.val();
                var toTz = $toTz.val();

                if (!datetime || !fromTz || !toTz) {
                    $result.html('<span style="color: red;">Please fill in all fields.</span>');
                    return;
                }

                $.ajax({
                    url: kh_timezone_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kh_convert_timezone',
                        datetime: datetime,
                        from_timezone: fromTz,
                        to_timezone: toTz,
                        nonce: kh_timezone_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            $result.html(
                                '<strong>Converted:</strong> ' + data.converted +
                                '<br><small>From ' + data.from_timezone + ' to ' + data.to_timezone + '</small>'
                            );
                        } else {
                            $result.html('<span style="color: red;">Conversion failed.</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: red;">Error during conversion.</span>');
                    }
                });
            });

            // Set current datetime as default
            if (!$datetime.val()) {
                var now = new Date();
                var datetimeStr = now.getFullYear() + '-' +
                    String(now.getMonth() + 1).padStart(2, '0') + '-' +
                    String(now.getDate()).padStart(2, '0') + ' ' +
                    String(now.getHours()).padStart(2, '0') + ':' +
                    String(now.getMinutes()).padStart(2, '0') + ':' +
                    String(now.getSeconds()).padStart(2, '0');
                $datetime.val(datetimeStr);
            }
        }
    }

    initTimezoneConverter();

    // Bulk timezone update for events
    function initBulkTimezoneUpdate() {
        var $bulkUpdate = $('#kh-bulk-timezone-update');
        if ($bulkUpdate.length) {
            var $applyBtn = $bulkUpdate.find('#apply-bulk-timezone');
            var $timezoneSelect = $bulkUpdate.find('#bulk-timezone-select');
            var $eventIds = $bulkUpdate.find('#bulk-event-ids');

            $applyBtn.on('click', function() {
                var timezone = $timezoneSelect.val();
                var eventIds = $eventIds.val();

                if (!timezone || !eventIds) {
                    alert('Please select a timezone and enter event IDs.');
                    return;
                }

                if (!confirm('Are you sure you want to update the timezone for these events?')) {
                    return;
                }

                var ids = eventIds.split(',').map(function(id) { return parseInt(id.trim()); }).filter(function(id) { return id > 0; });

                var processed = 0;
                var errors = 0;

                function updateNext() {
                    if (processed >= ids.length) {
                        alert('Bulk update complete. Processed: ' + processed + ', Errors: ' + errors);
                        return;
                    }

                    var eventId = ids[processed];
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kh_update_event_timezone',
                            event_id: eventId,
                            timezone: timezone,
                            nonce: kh_timezone_ajax.nonce
                        },
                        success: function(response) {
                            if (!response.success) {
                                errors++;
                            }
                        },
                        error: function() {
                            errors++;
                        },
                        complete: function() {
                            processed++;
                            updateNext();
                        }
                    });
                }

                updateNext();
            });
        }
    }

    initBulkTimezoneUpdate();
});