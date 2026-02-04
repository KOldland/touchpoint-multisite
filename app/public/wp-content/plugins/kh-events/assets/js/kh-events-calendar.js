/**
 * KH Events Calendar JavaScript
 *
 * Frontend JavaScript for calendar interaction
 */

(function($) {
    'use strict';

    class KH_Events_Calendar {
        constructor(container) {
            this.container = $(container);
            this.currentDate = this.container.data('date') || moment().format('YYYY-MM-DD');
            this.currentView = this.container.data('view') || 'month';
            this.filters = {};
            this.loading = false;

            this.init();
        }

        init() {
            this.bindEvents();
            this.loadEvents();
        }

        bindEvents() {
            const self = this;

            // Navigation events
            this.container.on('click', '.kh-events-nav-prev, .kh-events-nav-next', function(e) {
                e.preventDefault();
                const direction = $(this).data('direction');
                const newDate = $(this).data('date');
                self.navigate(direction, newDate);
            });

            // View switch events
            this.container.on('click', '.kh-events-view-btn', function(e) {
                e.preventDefault();
                const view = $(this).data('view');
                self.switchView(view);
            });

            // Day click events
            this.container.on('click', '.kh-events-calendar-day', function(e) {
                if ($(this).hasClass('kh-events-other-month')) return;

                const date = $(this).data('date');
                self.switchView('day', date);
            });

            // Event click events
            this.container.on('click', '.kh-events-day-event, .kh-events-week-event, .kh-events-day-event-card, .kh-events-list-event', function(e) {
                e.stopPropagation();
                const eventId = $(this).data('event-id');
                if (eventId) {
                    self.showEventDetails(eventId);
                }
            });

            // Filter events
            this.container.on('click', '.kh-events-filter-apply', function(e) {
                e.preventDefault();
                self.applyFilters();
            });

            this.container.on('click', '.kh-events-filter-clear', function(e) {
                e.preventDefault();
                self.clearFilters();
            });

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (e.keyCode === 37) { // Left arrow
                    self.container.find('.kh-events-nav-prev').trigger('click');
                } else if (e.keyCode === 39) { // Right arrow
                    self.container.find('.kh-events-nav-next').trigger('click');
                }
            });
        }

        navigate(direction, newDate) {
            this.currentDate = newDate;
            this.loadEvents();
        }

        switchView(view, date = null) {
            this.currentView = view;
            if (date) {
                this.currentDate = date;
            }
            this.loadEvents();
        }

        applyFilters() {
            this.filters = {
                category: this.container.find('.kh-events-filter-category').val(),
                location: this.container.find('.kh-events-filter-location').val()
            };
            this.loadEvents();
        }

        clearFilters() {
            this.container.find('.kh-events-filter-category').val('');
            this.container.find('.kh-events-filter-location').val('');
            this.filters = {};
            this.loadEvents();
        }

        loadEvents() {
            if (this.loading) return;

            this.loading = true;
            this.showLoading();

            const self = this;
            const data = {
                action: 'kh_events_load_calendar',
                view: this.currentView,
                date: this.currentDate,
                filters: this.filters,
                nonce: kh_events_calendar.nonce
            };

            $.ajax({
                url: kh_events_calendar.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.updateCalendar(response.data.html);
                    } else {
                        self.showError(response.data.message || 'Failed to load calendar');
                    }
                },
                error: function() {
                    self.showError('Network error occurred');
                },
                complete: function() {
                    self.loading = false;
                    self.hideLoading();
                }
            });
        }

        updateCalendar(html) {
            const $newCalendar = $(html);
            this.container.replaceWith($newCalendar);
            this.container = $newCalendar;

            // Re-initialize with new container
            this.bindEvents();

            // Trigger custom event for extensions
            $(document).trigger('kh-events-calendar-updated', [this]);
        }

        showEventDetails(eventId) {
            // Show loading state
            this.showLoading();

            const self = this;
            const data = {
                action: 'kh_events_get_event_details',
                event_id: eventId,
                nonce: kh_events_calendar.nonce
            };

            $.ajax({
                url: kh_events_calendar.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.displayEventModal(response.data);
                    } else {
                        self.showError(response.data.message || 'Failed to load event details');
                    }
                },
                error: function() {
                    self.showError('Network error occurred');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        }

        displayEventModal(eventData) {
            // Create modal HTML
            const modalHtml = `
                <div class="kh-events-modal-overlay">
                    <div class="kh-events-modal">
                        <div class="kh-events-modal-header">
                            <h3>${eventData.title}</h3>
                            <button class="kh-events-modal-close">&times;</button>
                        </div>
                        <div class="kh-events-modal-body">
                            <div class="kh-events-event-meta">
                                <div class="kh-events-event-date">
                                    <strong>${kh_events_calendar.i18n.date}:</strong> ${eventData.date}
                                </div>
                                <div class="kh-events-event-time">
                                    <strong>${kh_events_calendar.i18n.time}:</strong> ${eventData.time}
                                </div>
                                ${eventData.location ? `<div class="kh-events-event-location"><strong>${kh_events_calendar.i18n.location}:</strong> ${eventData.location}</div>` : ''}
                                ${eventData.price ? `<div class="kh-events-event-price"><strong>${kh_events_calendar.i18n.price}:</strong> ${eventData.price}</div>` : ''}
                            </div>
                            ${eventData.description ? `<div class="kh-events-event-description">${eventData.description}</div>` : ''}
                            ${eventData.capacity ? `<div class="kh-events-event-capacity"><strong>${kh_events_calendar.i18n.capacity}:</strong> ${eventData.booked}/${eventData.capacity}</div>` : ''}
                        </div>
                        <div class="kh-events-modal-footer">
                            <a href="${eventData.url}" class="kh-events-btn kh-events-btn-primary">${kh_events_calendar.i18n.view_event}</a>
                            ${eventData.can_book ? `<button class="kh-events-btn kh-events-btn-secondary" data-action="book">${kh_events_calendar.i18n.book_now}</button>` : ''}
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            // Bind modal events
            $('.kh-events-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $(this).remove();
                }
            });

            $('.kh-events-modal-close').on('click', function() {
                $('.kh-events-modal-overlay').remove();
            });

            $('.kh-events-modal [data-action="book"]').on('click', function() {
                // Handle booking logic here
                alert('Booking functionality would be implemented here');
            });
        }

        showLoading() {
            this.container.addClass('kh-events-calendar-loading');
        }

        hideLoading() {
            this.container.removeClass('kh-events-calendar-loading');
        }

        showError(message) {
            // Create error notification
            const errorHtml = `
                <div class="kh-events-error-notice">
                    <p>${message}</p>
                    <button class="kh-events-error-close">&times;</button>
                </div>
            `;

            this.container.find('.kh-events-calendar-header').after(errorHtml);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                $('.kh-events-error-notice').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Bind close button
            $('.kh-events-error-close').on('click', function() {
                $(this).parent().remove();
            });
        }
    }

    // Initialize calendars on document ready
    $(document).ready(function() {
        $('.kh-events-calendar').each(function() {
            new KH_Events_Calendar(this);
        });
    });

    // Re-initialize on AJAX content updates
    $(document).on('kh-events-content-updated', function() {
        $('.kh-events-calendar').each(function() {
            if (!$(this).data('kh-events-calendar-initialized')) {
                new KH_Events_Calendar(this);
                $(this).data('kh-events-calendar-initialized', true);
            }
        });
    });

    // Export for global access
    window.KH_Events_Calendar = KH_Events_Calendar;

})(jQuery);