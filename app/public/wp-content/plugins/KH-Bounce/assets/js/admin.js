(function( wbounce_backend, $, undefined ) {
    var select_id = '#wbounce_template';

    $(document).ready(function() {
        if ( ! $( select_id ).length ) {
            return;
        }
        updatePreviews();
        $( select_id ).on( 'change', updatePreviews );
    });

    function updatePreviews() {
        var current = $( select_id ).val();
        $('.kh-bounce-template-card').removeClass('active');
        $('#kh-bounce-preview-' + current).addClass('active');
    }
})( window.wbounce_backend = window.wbounce_backend || {}, jQuery );
