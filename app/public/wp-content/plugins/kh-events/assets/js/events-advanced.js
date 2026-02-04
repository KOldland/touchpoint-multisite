/**
 * KH Events Advanced Views JavaScript
 * Handles week view navigation and photo view interactions
 */

(function($) {
    'use strict';

    var KH_Events_Advanced = {
        init: function() {
            this.initWeekNavigation();
            this.initPhotoView();
        },

        initWeekNavigation: function() {
            var self = this;

            $(document).on('click', '.kh-week-nav', function(e) {
                e.preventDefault();

                var $nav = $(this);
                var direction = $nav.data('direction');
                var $weekContainer = $nav.closest('.kh-events-week');
                var currentDate = $weekContainer.data('current-date') || new Date().toISOString().split('T')[0];

                // Calculate new date
                var newDate = new Date(currentDate);
                if (direction === 'next') {
                    newDate.setDate(newDate.getDate() + 7);
                } else if (direction === 'prev') {
                    newDate.setDate(newDate.getDate() - 7);
                }

                var newDateString = newDate.toISOString().split('T')[0];

                // Update the week view
                self.loadWeekView($weekContainer, newDateString);
            });
        },

        loadWeekView: function($container, date) {
            // This would typically make an AJAX call to load the new week
            // For now, we'll just update the data attribute
            $container.data('current-date', date);

            // You could implement AJAX loading here if needed
            // Example:
            // $.ajax({
            //     url: kh_events_ajax.ajax_url,
            //     type: 'POST',
            //     data: {
            //         action: 'kh_load_week_view',
            //         date: date,
            //         category: $container.data('category'),
            //         tag: $container.data('tag')
            //     },
            //     success: function(response) {
            //         if (response.success) {
            //             $container.html(response.data.html);
            //         }
            //     }
            // });
        },

        initPhotoView: function() {
            // Add any photo view specific interactions here
            // For example, lightbox functionality, lazy loading, etc.

            // Example: Simple hover effects
            $(document).on('mouseenter', '.kh-photo-event-item', function() {
                $(this).addClass('kh-photo-hover');
            });

            $(document).on('mouseleave', '.kh-photo-event-item', function() {
                $(this).removeClass('kh-photo-hover');
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        KH_Events_Advanced.init();
    });

})(jQuery);