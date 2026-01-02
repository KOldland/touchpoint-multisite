// KH Events JavaScript
jQuery(document).ready(function($) {
    // Calendar navigation
    $('.kh-nav-link').click(function(e) {
        e.preventDefault();
        var month = $(this).data('month');
        var year = $(this).data('year');
        var calendarContainer = $(this).closest('.kh-events-calendar');

        $.ajax({
            url: kh_events_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'kh_load_calendar',
                month: month,
                year: year,
                category: calendarContainer.data('category'),
                tag: calendarContainer.data('tag'),
            },
            success: function(response) {
                if (response.success) {
                    calendarContainer.replaceWith(response.data.html);
                }
            }
        });
    });

    // Event Search Functionality
    $('.kh-search-input').on('input', function() {
        var searchTerm = $(this).val();
        var searchContainer = $(this).closest('.kh-events-search');
        performSearch(searchContainer);
    });

    $('.kh-category-filter, .kh-tag-filter, .kh-status-filter, .kh-location-filter, .kh-start-date-filter, .kh-end-date-filter').on('change input', function() {
        var searchContainer = $(this).closest('.kh-events-search');
        performSearch(searchContainer);
    });

    $('.kh-clear-filters').click(function() {
        var searchContainer = $(this).closest('.kh-events-search');
        searchContainer.find('.kh-search-input').val('');
        searchContainer.find('.kh-category-filter').val('');
        searchContainer.find('.kh-tag-filter').val('');
        searchContainer.find('.kh-status-filter').val('');
        searchContainer.find('.kh-location-filter').val('');
        searchContainer.find('.kh-start-date-filter').val('');
        searchContainer.find('.kh-end-date-filter').val('');
        performSearch(searchContainer);
    });

    function performSearch(searchContainer) {
        var searchData = {
            action: 'kh_search_events',
            search: searchContainer.find('.kh-search-input').val(),
            category: searchContainer.find('.kh-category-filter').val(),
            tag: searchContainer.find('.kh-tag-filter').val(),
            status: searchContainer.find('.kh-status-filter').val(),
            location: searchContainer.find('.kh-location-filter').val(),
            start_date: searchContainer.find('.kh-start-date-filter').val(),
            end_date: searchContainer.find('.kh-end-date-filter').val(),
            limit: searchContainer.data('limit')
        };

        searchContainer.find('.kh-search-status').text('Searching...');

        $.ajax({
            url: kh_events_ajax.ajax_url,
            type: 'POST',
            data: searchData,
            success: function(response) {
                if (response.success && response.data.events && response.data.events.length > 0) {
                    var html = '';
                    response.data.events.forEach(function(event) {
                        html += '<div class="kh-event-item">';
                        html += '<h3><a href="' + event.permalink + '">' + event.title + '</a></h3>';
                        html += '<div class="kh-event-meta">';
                        if (event.date) {
                            html += '<span class="kh-event-date">' + event.date + '</span>';
                        }
                        if (event.time) {
                            html += '<span class="kh-event-time">' + event.time + '</span>';
                        }
                        if (event.status) {
                            html += '<span class="kh-event-status-display kh-event-status-' + event.status.status + '" style="background-color: ' + event.status.color + ';">' + event.status.label + '</span>';
                        }
                        html += '</div>';
                        if (event.location) {
                            html += '<div class="kh-event-location">' + event.location + '</div>';
                        }
                        if (event.excerpt) {
                            html += '<div class="kh-event-excerpt">' + event.excerpt + '</div>';
                        }
                        html += '</div>';
                    });
                    searchContainer.find('.kh-results-container').html(html);
                    searchContainer.find('.kh-search-status').text('Found ' + response.data.events.length + ' events');
                } else {
                    searchContainer.find('.kh-results-container').html('<div class="kh-no-results">No events found.</div>');
                    searchContainer.find('.kh-search-status').text('No events found');
                }
            },
            error: function() {
                searchContainer.find('.kh-search-status').text('Search failed. Please try again.');
            }
        });
    }

    // Event Submission Functionality
    $('.kh-submit-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = new FormData(this);
        formData.append('action', 'kh_submit_event');

        form.find('.kh-submit-button').prop('disabled', true).text('Submitting...');
        form.find('.kh-submit-status').html('<div class="kh-loading">Submitting event...</div>');

        $.ajax({
            url: kh_events_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    form.find('.kh-submit-status').html('<div class="kh-success">' + response.data.message + '</div>');
                    form[0].reset();
                    form.find('#image-preview').html('<span class="kh-upload-text">Click to upload image</span>');
                } else {
                    form.find('.kh-submit-status').html('<div class="kh-error">' + response.data.message + '</div>');
                }
                form.find('.kh-submit-button').prop('disabled', false).text('Submit Event');
            },
            error: function() {
                form.find('.kh-submit-status').html('<div class="kh-error">Submission failed. Please try again.</div>');
                form.find('.kh-submit-button').prop('disabled', false).text('Submit Event');
            }
        });
    });

    // Image Upload for Event Submission
    $('.kh-upload-button').click(function(e) {
        e.preventDefault();
        var button = $(this);
        var imageInput = button.siblings('#featured-image-id');
        var preview = button.siblings('.kh-image-preview');

        var mediaUploader = wp.media({
            title: 'Select Event Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            imageInput.val(attachment.id);
            preview.html('<img src="' + attachment.url + '" alt="Event image" style="max-width: 200px; max-height: 150px;">');
        });

        mediaUploader.open();
    });

    // Dashboard Functionality
    function loadDashboardStats(dashboard) {
        var userId = dashboard.data('user-id');

        $.ajax({
            url: kh_events_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'kh_get_dashboard_stats',
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    $('#total-events').text(response.data.total_events);
                    $('#published-events').text(response.data.published_events);
                    $('#pending-events').text(response.data.pending_events);
                    $('#total-views').text(response.data.total_views);
                }
            }
        });
    }

    function loadUserEvents(dashboard) {
        var userId = dashboard.data('user-id');

        $.ajax({
            url: kh_events_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'kh_get_user_events',
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    $('#user-events-list').html(response.data.html);
                } else {
                    $('#user-events-list').html('<div class="kh-no-events">No events found.</div>');
                }
            }
        });
    }

    // Initialize dashboard if present
    if ($('.kh-events-dashboard').length > 0) {
        var dashboard = $('.kh-events-dashboard');
        loadDashboardStats(dashboard);
        loadUserEvents(dashboard);
    }

    // Add any interactive functionality here
    console.log('KH Events loaded');
});