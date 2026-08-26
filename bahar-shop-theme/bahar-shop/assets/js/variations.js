(function ($) {
  function getStockMap($form) {
    var $json = $('#bahar-variation-stock');
    if (!$json.length) {
      return {};
    }
    try {
      return JSON.parse($json.text());
    } catch (e) {
      return {};
    }
  }

  function applyStockStates($form) {
    var stockMap = getStockMap($form);

    $form.find('.bahar-variation-picker').each(function () {
      var $picker = $(this);
      var attrKey = $picker.data('attribute');
      var attrStock = stockMap[attrKey] || stockMap[$picker.find('select').data('attribute_name')] || {};

      $picker.find('.bahar-variation-btn').each(function () {
        var $btn = $(this);
        var value = String($btn.data('value'));
        var inStock = attrStock[value] === true;

        $btn.toggleClass('is-out-of-stock', !inStock);
        $btn.prop('disabled', !inStock);
        $btn.attr('aria-disabled', inStock ? 'false' : 'true');

        if (!inStock) {
          $btn.removeClass('is-active').attr('aria-pressed', 'false');
        }
      });
    });

    $form.find('.bahar-variation-select option').prop('disabled', false);
  }

  function syncPicker($picker) {
    var $select = $picker.find('select');
    var value = $select.val();

    $picker.find('.bahar-variation-btn').each(function () {
      var $btn = $(this);
      if ($btn.hasClass('is-out-of-stock')) {
        return;
      }
      var active = $btn.data('value') === value;
      $btn.toggleClass('is-active', active);
      $btn.attr('aria-pressed', active ? 'true' : 'false');
    });
  }

  function syncClearButton($form) {
    var $wrap = $('#bahar-variation-clear-wrap');
    if (!$wrap.length) {
      return;
    }

    var hasSelection = false;
    $form.find('.bahar-variation-select').each(function () {
      if ($(this).val()) {
        hasSelection = true;
      }
    });

    if (hasSelection) {
      $wrap.removeAttr('hidden');
    } else {
      $wrap.attr('hidden', 'hidden');
    }
  }

  function bindClearButton($form) {
    var $btn = $('#bahar-variation-clear-btn');
    if (!$btn.length || $btn.data('baharClearReady')) {
      return;
    }
    $btn.data('baharClearReady', true);

    $btn.on('click.baharClear', function () {
      var $reset = $form.find('a.reset_variations').first();
      if ($reset.length) {
        $reset.trigger('click');
      } else {
        $form.find('.bahar-variation-select').val('').trigger('change');
        $form.trigger('reset_data');
      }
    });
  }

  function hideWooLabels($form) {
    $form.find('table.variations .label, table.variations th.label, table.variations td.label').each(function () {
      $(this).attr('hidden', 'hidden').attr('aria-hidden', 'true').css({
        display: 'none',
        visibility: 'hidden',
        height: 0,
        overflow: 'hidden'
      });
    });
  }

  function initPickers($scope) {
    hideWooLabels($scope);
    $scope.find('.bahar-variation-picker').each(function () {
      var $picker = $(this);
      if ($picker.data('baharReady')) {
        return;
      }
      $picker.data('baharReady', true);

      var $select = $picker.find('select');
      var $form = $picker.closest('form.variations_form');

      $picker.on('click', '.bahar-variation-btn', function (e) {
        var $btn = $(this);
        if ($btn.hasClass('is-out-of-stock') || $btn.prop('disabled')) {
          e.preventDefault();
          return;
        }
        var value = $btn.data('value');
        $select.val(value).trigger('change');
        syncPicker($picker);

        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
          $btn.removeClass('is-popping');
          // Force reflow so animation can restart.
          void $btn[0].offsetWidth;
          $btn.addClass('is-popping');
          window.setTimeout(function () {
            $btn.removeClass('is-popping');
          }, 450);
        }
      });

      $select.on('change', function () {
        syncPicker($picker);
        syncClearButton($form);
      });

      syncPicker($picker);
      applyStockStates($form);
    });

    syncClearButton($scope);
    bindClearButton($scope);
  }

  $(function () {
    var $form = $('.variations_form');
    initPickers($form);

    $form.on('woocommerce_update_variation_values check_variations', function () {
      var $f = $(this);
      $f.find('.bahar-variation-select option').each(function () {
        if ($(this).val() !== '') {
          $(this).prop('disabled', false);
        }
      });
      applyStockStates($f);
      syncClearButton($f);
    });
  });

  $(document.body).on('wc_variation_form', function (event) {
    initPickers($(event.target));
  });

  $(document.body).on('reset_data', 'form.variations_form', function () {
    var $form = $(this);
    $form.find('.bahar-variation-picker select').val('');
    $form.find('.bahar-variation-btn').removeClass('is-active').attr('aria-pressed', 'false');
    applyStockStates($form);
    syncClearButton($form);
  });
})(jQuery);
