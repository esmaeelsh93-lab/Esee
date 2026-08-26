/**
 * Admin media picker for Bahar Shop hero images.
 */
(function ($) {
  'use strict';

  function bindField($field) {
    var frame;
    var $id = $field.find('.bahar-media-field__id');
    var $url = $field.find('.bahar-media-field__url');
    var $preview = $field.find('.bahar-media-field__preview');
    var $remove = $field.find('.bahar-media-field__remove');

    function setPreview(src) {
      if (!src) {
        $preview.hide().empty();
        $remove.prop('disabled', true);
        return;
      }
      $preview
        .html(
          '<img src="' +
            src.replace(/"/g, '&quot;') +
            '" alt="" style="max-width:280px;height:auto;border-radius:12px;border:1px solid #f1dce8;background:#fff9fc;" />'
        )
        .show();
      $remove.prop('disabled', false);
    }

    $field.find('.bahar-media-field__upload').on('click', function (e) {
      e.preventDefault();
      if (frame) {
        frame.open();
        return;
      }
      frame = wp.media({
        title: 'انتخاب تصویر هیرو',
        button: { text: 'استفاده از این تصویر' },
        library: { type: 'image' },
        multiple: false,
      });
      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        $id.val(attachment.id || 0);
        $url.val(attachment.url || '');
        setPreview(attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url);
      });
      frame.open();
    });

    $remove.on('click', function (e) {
      e.preventDefault();
      $id.val('0');
      $url.val('');
      setPreview('');
    });
  }

  $(function () {
    $('.bahar-media-field[data-bahar-media]').each(function () {
      bindField($(this));
    });
  });
})(jQuery);
