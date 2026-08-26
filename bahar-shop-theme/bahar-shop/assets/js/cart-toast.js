(function ($) {
  var hideTimer;

  function showToast() {
    var $toast = $('#bahar-cart-toast');
    if (!$toast.length) {
      return;
    }

    $toast.removeAttr('hidden').addClass('is-visible');
    window.clearTimeout(hideTimer);
    hideTimer = window.setTimeout(function () {
      $toast.removeClass('is-visible').attr('hidden', 'hidden');
    }, 2800);
  }

  $(function () {
    $(document.body).on('added_to_cart', showToast);

    $(document.body).on('wc_fragments_refreshed', function () {
      if ($('.single_add_to_cart_button.added').length) {
        showToast();
      }
    });
  });
})(jQuery);
