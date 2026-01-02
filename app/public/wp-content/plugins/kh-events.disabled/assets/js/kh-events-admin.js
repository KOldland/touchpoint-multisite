/**
 * KH Events Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Settings page tabs
        $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
            e.preventDefault();
            var targetTab = $(this).attr('href').split('tab=')[1];

            // Update URL without page reload
            var newUrl = window.location.href.replace(/&tab=[^&]*/, '') + '&tab=' + targetTab;
            window.history.pushState({}, '', newUrl);

            // Switch tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Show corresponding content
            $('.settings-section').hide();
            $('#kh-events-' + targetTab + '-settings').show();
        });

        // Recurring events toggle
        $('#_kh_event_recurring').on('change', function() {
            if ($(this).is(':checked')) {
                $('.kh-recurring-options').slideDown();
            } else {
                $('.kh-recurring-options').slideUp();
            }
        });

        // Ticket management
        var ticketIndex = $('.kh-ticket-item').length;

        $('#add-ticket').on('click', function(e) {
            e.preventDefault();
            addTicket();
        });

        $(document).on('click', '.remove-ticket', function(e) {
            e.preventDefault();
            $(this).closest('.kh-ticket-item').remove();
        });

        function addTicket() {
            var ticketHtml = `
                <div class="kh-ticket-item">
                    <button type="button" class="remove-ticket" title="Remove Ticket">×</button>
                    <div class="ticket-fields">
                        <div class="form-field">
                            <label>Ticket Name</label>
                            <input type="text" name="kh_event_tickets[${ticketIndex}][name]" value="" />
                        </div>
                        <div class="form-field">
                            <label>Price</label>
                            <input type="number" name="kh_event_tickets[${ticketIndex}][price]" value="0" step="0.01" min="0" />
                        </div>
                        <div class="form-field">
                            <label>Quantity</label>
                            <input type="number" name="kh_event_tickets[${ticketIndex}][quantity]" value="100" min="1" />
                        </div>
                        <div class="form-field">
                            <label>Description</label>
                            <textarea name="kh_event_tickets[${ticketIndex}][description]" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            `;
            $('.kh-tickets-list').append(ticketHtml);
            ticketIndex++;
        }

        // Date/time validation
        $('#_kh_event_start_date, #_kh_event_end_date').on('change', function() {
            var startDate = $('#_kh_event_start_date').val();
            var endDate = $('#_kh_event_end_date').val();

            if (startDate && endDate && startDate > endDate) {
                alert('End date cannot be before start date.');
                $('#_kh_event_end_date').val(startDate);
            }
        });

        $('#_kh_event_start_time, #_kh_event_end_time').on('change', function() {
            var startDate = $('#_kh_event_start_date').val();
            var startTime = $('#_kh_event_start_time').val();
            var endTime = $('#_kh_event_end_time').val();

            if (startDate && startTime && endTime) {
                var startDateTime = new Date(startDate + ' ' + startTime);
                var endDateTime = new Date(startDate + ' ' + endTime);

                if (startDateTime >= endDateTime) {
                    alert('End time must be after start time.');
                    $('#_kh_event_end_time').val('');
                }
            }
        });

        // Location autocomplete (if Google Maps API is available)
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            var locationInput = document.getElementById('_kh_location_address');
            if (locationInput) {
                var autocomplete = new google.maps.places.Autocomplete(locationInput, {
                    types: ['address']
                });

                autocomplete.addListener('place_changed', function() {
                    var place = autocomplete.getPlace();
                    if (place.geometry) {
                        $('#_kh_location_lat').val(place.geometry.location.lat());
                        $('#_kh_location_lng').val(place.geometry.location.lng());
                    }
                });
            }
        }

        // Bulk actions for events
        $('#doaction, #doaction2').on('click', function(e) {
            var action = $(this).siblings('select').val();
            if (action === 'duplicate_events') {
                e.preventDefault();
                var checkedBoxes = $('input[name="post[]"]:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select events to duplicate.');
                    return;
                }
                duplicateEvents(checkedBoxes);
            }
        });

        function duplicateEvents(checkedBoxes) {
            checkedBoxes.each(function() {
                var postId = $(this).val();
                // AJAX call to duplicate event
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kh_duplicate_event',
                        post_id: postId,
                        nonce: kh_events_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error duplicating event: ' + response.data);
                        }
                    }
                });
            });
        }

        // Quick edit enhancements
        $(document).on('click', '.editinline', function() {
            // Add quick edit fields for event date
            setTimeout(function() {
                var postId = inlineEditPost.getId($(this));
                var startDate = $('#inline_' + postId + '_kh_event_start_date').val();
                if (startDate) {
                    $('.inline-edit-row .inline-edit-date input').val(startDate);
                }
            }, 100);
        });

        // Settings validation
        $('#kh-events-settings-form').on('submit', function(e) {
            var apiKey = $('#kh_events_google_maps_api_key').val();
            if (apiKey && !apiKey.match(/^AIza[0-9A-Za-z-_]{35}$/)) {
                alert('Please enter a valid Google Maps API key.');
                e.preventDefault();
                return false;
            }

            var fromEmail = $('#kh_events_from_email').val();
            if (fromEmail && !fromEmail.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                alert('Please enter a valid email address.');
                e.preventDefault();
                return false;
            }
        });

        // Dashboard widget refresh
        $('.kh-events-dashboard-widget .postbox-header .hndle').on('click', function() {
            setTimeout(function() {
                // Refresh dashboard stats if needed
                if (typeof kh_events_dashboard !== 'undefined') {
                    kh_events_dashboard.refresh();
                }
            }, 100);
        });

    });

})(jQuery);