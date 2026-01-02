(function ($) {
    'use strict';

    $(document).on('submit', '.kh-form', function (event) {
        event.preventDefault();
        var $form = $(this);
        var $response = $form.find('.kh-form-response');
        $response.hide().removeClass('kh-success kh-error');

        $.post(khFormFrontend.ajaxUrl, $form.serialize() + '&action=kh_form_submit')
            .done(function (res) {
                if (res.success) {
                    $response.addClass('kh-success').text(res.data.message).fadeIn();
                    $form[0].reset();
                } else {
                    $response.addClass('kh-error').text(res.data ? res.data : khFormFrontend.error).fadeIn();
                }
            }).fail(function () {
                $response.addClass('kh-error').text(khFormFrontend.error).fadeIn();
            });
    });
})(jQuery);
