(function ($) {
  function $sticky() {
    return $('#bahar-sticky-cart');
  }

  function $form() {
    return $('.bahar-single-product form.variations_form, .bahar-single-product form.cart').first();
  }

  function updateStickyButton() {
    var $bar = $sticky();
    var $cartForm = $form();
    if (!$bar.length || !$cartForm.length) {
      return;
    }

    var $mainBtn = $cartForm.find('.single_add_to_cart_button').first();
    var $btn = $bar.find('.bahar-sticky-cart__btn');

    if (!$mainBtn.length) {
      $btn.prop('disabled', true);
      return;
    }

    $btn.prop('disabled', $mainBtn.prop('disabled'));
    $btn.text($.trim($mainBtn.text()) || 'افزودن به سبد');
  }

  function bindSticky() {
    var $bar = $sticky();
    var $cartForm = $form();
    if (!$bar.length || !$cartForm.length || $bar.data('baharStickyReady')) {
      return;
    }
    $bar.data('baharStickyReady', true);

    var $price = $bar.find('.bahar-sticky-cart__price');
    if (!$price.data('defaultHtml')) {
      $price.data('defaultHtml', $price.html());
    }

    $bar.find('.bahar-sticky-cart__btn').on('click.baharSticky', function () {
      var $mainBtn = $cartForm.find('.single_add_to_cart_button').first();
      if ($mainBtn.length && !$mainBtn.prop('disabled')) {
        $mainBtn.trigger('click');
      }
    });

    $cartForm.on('found_variation show_variation hide_variation reset_data', function (event, variation) {
      if (variation && variation.price_html) {
        $price.html(variation.price_html);
      } else if (event.type === 'reset_data' || event.type === 'hide_variation') {
        $price.html($price.data('defaultHtml'));
      }
      updateStickyButton();
    });

    $cartForm.on('woocommerce_variation_has_changed', updateStickyButton);
    updateStickyButton();
  }

  $(function () {
    bindSticky();
  });

  $(document.body).on('wc_variation_form', function () {
    bindSticky();
  });
})(jQuery);
