// Location Map Script
jQuery(document).ready(function($) {
    var map;
    var marker;
    var geocoder = new google.maps.Geocoder();

    function initMap() {
        var lat = parseFloat(kh_location_vars.lat) || 40.7128;
        var lng = parseFloat(kh_location_vars.lng) || -74.0060;

        var location = {lat: lat, lng: lng};

        map = new google.maps.Map(document.getElementById('kh_location_map'), {
            zoom: 15,
            center: location
        });

        marker = new google.maps.Marker({
            position: location,
            map: map,
            draggable: true
        });

        marker.addListener('dragend', function() {
            var pos = marker.getPosition();
            $('#kh_location_lat').val(pos.lat());
            $('#kh_location_lng').val(pos.lng());
        });
    }

    initMap();

    $('#update_map').click(function() {
        var address = $('#kh_location_address').val() + ', ' +
                      $('#kh_location_city').val() + ', ' +
                      $('#kh_location_state').val() + ' ' +
                      $('#kh_location_zip').val() + ', ' +
                      $('#kh_location_country').val();

        geocoder.geocode({'address': address}, function(results, status) {
            if (status === 'OK') {
                var location = results[0].geometry.location;
                map.setCenter(location);
                marker.setPosition(location);
                $('#kh_location_lat').val(location.lat());
                $('#kh_location_lng').val(location.lng());
            } else {
                alert('Geocode was not successful for the following reason: ' + status);
            }
        });
    });
});