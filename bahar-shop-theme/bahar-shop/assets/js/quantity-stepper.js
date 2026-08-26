(function ($) {
  function clampQty($input, next) {
    var min = parseFloat($input.attr('min'));
    var max = parseFloat($input.attr('max'));
    var step = parseFloat($input.attr('step')) || 1;
    var val = parseFloat($input.val());

    if (isNaN(val)) {
      val = min || 1;
    }

    if (!isNaN(min)) {
      val = Math.max(min, val);
    }
    if (!isNaN(max)) {
      val = Math.min(max, val);
    }

    if (typeof next === 'number') {
      val = next;
      if (!isNaN(min)) {
        val = Math.max(min, val);
      }
      if (!isNaN(max)) {
        val = Math.min(max, val);
      }
    }

    var decimals = (String(step).split('.')[1] || '').length;
    $input.val(decimals ? val.toFixed(decimals) : val).trigger('change');
  }

  function initSteppers($scope) {
    $scope.find('.bahar-single-product .quantity').each(function () {
      var $qty = $(this);
      if ($qty.data('baharQtyReady')) {
        return;
      }
      $qty.data('baharQtyReady', true);
      $qty.addClass('bahar-qty-stepper');

      var $input = $qty.find('.qty');
      if (!$input.length) {
        return;
      }

      $qty.on('click', '.bahar-qty-btn--minus', function () {
        var step = parseFloat($input.attr('step')) || 1;
        var val = parseFloat($input.val()) || 1;
        clampQty($input, val - step);
      });

      $qty.on('click', '.bahar-qty-btn--plus', function () {
        var step = parseFloat($input.attr('step')) || 1;
        var val = parseFloat($input.val()) || 1;
        clampQty($input, val + step);
      });
    });
  }

  $(function () {
    initSteppers($(document));
  });

  $(document.body).on('wc_variation_form', function (event) {
    initSteppers($(event.target).closest('.bahar-single-product'));
  });
})(jQuery);
