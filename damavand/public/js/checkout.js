(function ($) {
    'use strict';

    /* Smooth scroll to related products */
    $(document).on('click', '.shojaei-oos-similar-btn', function (e) {
        e.preventDefault();
        var target = $(this).attr('href');
        if ($(target).length) {
            $('html, body').animate({
                scrollTop: $(target).offset().top - 80
            }, 600);
        }
    });

    /* Quick add to cart on checkout */
    $(document).on('click', '.shojaei-quick-add', function () {
        var $btn = $(this);
        var productId = $btn.data('product-id');

        $btn.prop('disabled', true).text('...');

        $.post(shojaeiSeo.ajaxUrl, {
            action: 'shojaei_add_to_cart',
            nonce: shojaeiSeo.nonce,
            product_id: productId
        }, function (response) {
            if (response.success) {
                $btn.addClass('added').text('✓ اضافه شد');
                $(document.body).trigger('wc_fragment_refresh');
                $(document.body).trigger('update_checkout');
            } else {
                $btn.prop('disabled', false).text('افزودن');
                alert(response.data.message || 'خطا');
            }
        });
    });

})(jQuery);
