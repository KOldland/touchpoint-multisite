/**
 * KH Events Map JavaScript
 * Handles interactive map display for events
 */

(function($) {
    'use strict';

    var KH_Events_Map = {
        map: null,
        markers: [],
        infoWindow: null,

        init: function() {
            if (typeof google === 'undefined' || !$('#kh-events-map').length) {
                return;
            }

            this.initMap();
            this.addMarkers();
        },

        initMap: function() {
            var center = kh_events_map_data.center;
            var zoom = kh_events_map_data.zoom;

            this.map = new google.maps.Map(document.getElementById('kh-events-map'), {
                center: { lat: parseFloat(center.lat), lng: parseFloat(center.lng) },
                zoom: zoom,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true
            });

            this.infoWindow = new google.maps.InfoWindow({
                content: document.getElementById('kh-map-info-window')
            });
        },

        addMarkers: function() {
            var self = this;
            var events = kh_events_map_data.events;
            var bounds = new google.maps.LatLngBounds();

            $.each(events, function(index, event) {
                var position = {
                    lat: parseFloat(event.lat),
                    lng: parseFloat(event.lng)
                };

                var marker = new google.maps.Marker({
                    position: position,
                    map: self.map,
                    title: event.title,
                    eventData: event
                });

                // Extend bounds to include this marker
                bounds.extend(position);

                // Add click listener
                marker.addListener('click', function() {
                    self.showEventInfo(marker);
                });

                self.markers.push(marker);
            });

            // Fit map to show all markers if we have multiple events
            if (events.length > 1) {
                this.map.fitBounds(bounds);
            }
        },

        showEventInfo: function(marker) {
            var event = marker.eventData;

            // Update info window content
            $('#kh-map-info-window .kh-map-event-title').text(event.title);
            $('#kh-map-info-window .kh-map-event-date').text('Date: ' + this.formatDate(event.date));
            $('#kh-map-info-window .kh-map-event-time').text('Time: ' + (event.time || 'TBD'));
            $('#kh-map-info-window .kh-map-event-location').text('Location: ' + event.location);
            $('#kh-map-info-window .kh-map-event-link').attr('href', event.permalink);

            this.infoWindow.open(this.map, marker);
        },

        formatDate: function(dateString) {
            if (!dateString) return 'TBD';

            var date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        KH_Events_Map.init();
    });

    // Also initialize when Google Maps API loads
    window.initKHMap = function() {
        KH_Events_Map.init();
    };

})(jQuery);