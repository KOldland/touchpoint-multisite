jQuery(document).ready(function($) {
    $('.ssm-share-trigger').on('click', function() {
        $('#ssm-modal').removeClass('ssm-hidden');
    });
    $('.ssm-close').on('click', function() {
        $('#ssm-modal').addClass('ssm-hidden');
    });
});
