/**
 * KH Events Timezone Frontend JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize timezone functionality
    function initTimezoneFeatures() {
        // Convert event times to user's timezone
        convertEventTimes();

        // User timezone selector
        initUserTimezoneSelector();

        // Timezone info display
        showTimezoneInfo();
    }

    // Convert event times to user's timezone
    function convertEventTimes() {
        $('.kh-event-time').each(function() {
            var $element = $(this);
            var eventId = $element.data('event-id');
            var originalTime = $element.data('original-time');
            var eventTimezone = $element.data('event-timezone');

            if (originalTime && eventTimezone && kh_timezone_vars.enable_user_timezones) {
                var userTimezone = kh_timezone_vars.user_timezone;

                if (eventTimezone !== userTimezone) {
                    // Convert timezone
                    $.ajax({
                        url: kh_timezone_vars.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'kh_convert_timezone',
                            datetime: originalTime,
                            from_timezone: eventTimezone,
                            to_timezone: userTimezone,
                            nonce: kh_timezone_vars.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                var convertedTime = formatConvertedTime(response.data.converted, userTimezone);
                                $element.html(convertedTime);

                                // Add timezone indicator
                                if (!$element.next('.timezone-indicator').length) {
                                    var abbr = getTimezoneAbbr(userTimezone);
                                    $element.after('<span class="timezone-indicator"> (' + abbr + ')</span>');
                                }
                            }
                        }
                    });
                } else {
                    // Same timezone, just format
                    var formattedTime = formatConvertedTime(originalTime, eventTimezone);
                    $element.html(formattedTime);

                    if (kh_timezone_vars.show_timezone_labels) {
                        var abbr = getTimezoneAbbr(eventTimezone);
                        $element.after('<span class="timezone-indicator"> (' + abbr + ')</span>');
                    }
                }
            }
        });
    }

    // Format converted time for display
    function formatConvertedTime(datetime, timezone) {
        var date = new Date(datetime + (datetime.indexOf(' ') > -1 ? '' : ' UTC'));
        var options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };

        try {
            return date.toLocaleDateString(undefined, options);
        } catch (e) {
            // Fallback formatting
            return datetime;
        }
    }

    // Get timezone abbreviation
    function getTimezoneAbbr(timezone) {
        try {
            var date = new Date();
            var tzDate = new Date(date.toLocaleString('en-US', {timeZone: timezone}));
            var offset = tzDate.getTime() - date.getTime();
            var hours = Math.round(offset / 3600000);
            return 'UTC' + (hours >= 0 ? '+' : '') + hours;
        } catch (e) {
            return timezone.split('/').pop().replace('_', ' ');
        }
    }

    // User timezone selector
    function initUserTimezoneSelector() {
        var $selector = $('#kh-user-timezone-selector');
        if ($selector.length && kh_timezone_vars.enable_user_timezones) {
            // Load current user timezone
            $selector.val(kh_timezone_vars.user_timezone);

            $selector.on('change', function() {
                var timezone = $(this).val();

                $.ajax({
                    url: kh_timezone_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kh_save_user_timezone',
                        timezone: timezone,
                        nonce: kh_timezone_vars.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update global variable
                            kh_timezone_vars.user_timezone = timezone;

                            // Re-convert all event times
                            convertEventTimes();

                            // Show success message
                            showMessage('Timezone preference updated!', 'success');
                        } else {
                            showMessage('Failed to update timezone preference.', 'error');
                        }
                    },
                    error: function() {
                        showMessage('Error updating timezone preference.', 'error');
                    }
                });
            });
        }
    }

    // Show timezone information
    function showTimezoneInfo() {
        $('.kh-event-timezone-info').each(function() {
            var $element = $(this);
            var eventId = $element.data('event-id');

            if (eventId) {
                $.ajax({
                    url: kh_timezone_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kh_get_event_timezone_info',
                        event_id: eventId,
                        nonce: kh_timezone_vars.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var info = response.data;
                            $element.html(
                                '<strong>Timezone:</strong> ' + info.timezone_name +
                                ' (UTC' + (info.offset >= 0 ? '+' : '') + info.offset + ')' +
                                '<br><small>Event time: ' + info.local_time + ' ' + info.abbr + '</small>'
                            );
                        }
                    }
                });
            }
        });
    }

    // Show message to user
    function showMessage(message, type) {
        var $message = $('<div class="kh-timezone-message kh-timezone-' + type + '">' + message + '</div>');
        $('body').append($message);

        setTimeout(function() {
            $message.fadeOut(function() {
                $message.remove();
            });
        }, 3000);
    }

    // Detect user's timezone from browser
    function detectUserTimezone() {
        if (window.Intl && window.Intl.DateTimeFormat) {
            var timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (timezone && kh_timezone_vars.enable_user_timezones) {
                // Suggest timezone to user
                var $suggestion = $(
                    '<div class="kh-timezone-suggestion">' +
                    '<p>We detected your timezone as: <strong>' + timezone + '</strong></p>' +
                    '<button id="accept-timezone" class="button">Use This Timezone</button> ' +
                    '<button id="dismiss-timezone" class="button button-secondary">Dismiss</button>' +
                    '</div>'
                );

                $('body').append($suggestion);

                $('#accept-timezone').on('click', function() {
                    $('#kh-user-timezone-selector').val(timezone).trigger('change');
                    $suggestion.remove();
                });

                $('#dismiss-timezone').on('click', function() {
                    $suggestion.remove();
                });
            }
        }
    }

    // Event time converter widget
    function initTimeConverterWidget() {
        var $widget = $('.kh-time-converter-widget');
        if ($widget.length) {
            var $input = $widget.find('.time-input');
            var $fromSelect = $widget.find('.from-timezone');
            var $toSelect = $widget.find('.to-timezone');
            var $result = $widget.find('.conversion-result');
            var $convertBtn = $widget.find('.convert-btn');

            $convertBtn.on('click', function() {
                var timeInput = $input.val();
                var fromTz = $fromSelect.val();
                var toTz = $toSelect.val();

                if (!timeInput || !fromTz || !toTz) {
                    $result.html('<span style="color: red;">Please fill in all fields.</span>');
                    return;
                }

                $.ajax({
                    url: kh_timezone_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kh_convert_timezone',
                        datetime: timeInput,
                        from_timezone: fromTz,
                        to_timezone: toTz,
                        nonce: kh_timezone_vars.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var converted = new Date(data.converted);
                            var formatted = converted.toLocaleString();

                            $result.html(
                                '<strong>Converted Time:</strong><br>' +
                                formatted + '<br>' +
                                '<small>From ' + data.from_timezone + ' to ' + data.to_timezone + '</small>'
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

            // Set current time as default
            if (!$input.val()) {
                var now = new Date();
                var timeStr = now.getFullYear() + '-' +
                    String(now.getMonth() + 1).padStart(2, '0') + '-' +
                    String(now.getDate()).padStart(2, '0') + 'T' +
                    String(now.getHours()).padStart(2, '0') + ':' +
                    String(now.getMinutes()).padStart(2, '0');
                $input.val(timeStr);
            }
        }
    }

    // Initialize all features
    initTimezoneFeatures();
    initTimeConverterWidget();

    // Detect timezone on first visit (only if no preference set)
    if (kh_timezone_vars.enable_user_timezones && !kh_timezone_vars.user_timezone_set) {
        setTimeout(detectUserTimezone, 2000); // Delay to not interfere with page load
    }
});