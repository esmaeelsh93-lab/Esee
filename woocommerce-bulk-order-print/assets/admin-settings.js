(function ($) {
	'use strict';

	function toggleCustomPaper() {
		var type = $('#wbop-paper-type').val();
		var rows = $('.wbop-custom-paper-row');
		if (type === 'custom') {
			rows.removeClass('is-hidden').show();
		} else {
			rows.addClass('is-hidden').hide();
		}
	}

	$(function () {
		toggleCustomPaper();
		$('#wbop-paper-type').on('change', toggleCustomPaper);

		var frame = null;

		$('#wbop-upload-header').on('click', function (e) {
			e.preventDefault();

			if (typeof wp === 'undefined' || !wp.media) {
				window.alert('کتابخانه رسانه وردپرس در دسترس نیست.');
				return;
			}

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: (window.wbopSettings && wbopSettings.title) || 'انتخاب تصویر سربرگ',
				button: {
					text: (window.wbopSettings && wbopSettings.button) || 'استفاده از این تصویر'
				},
				library: {
					type: 'image'
				},
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				if (!attachment || !attachment.id) {
					return;
				}

				$('#wbop-header-image').val(attachment.id);
				var url = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url)
					? attachment.sizes.medium.url
					: attachment.url;
				$('#wbop-header-preview').html('<img src="' + url + '" alt="">');
				$('#wbop-remove-header').prop('disabled', false);
			});

			frame.open();
		});

		$('#wbop-remove-header').on('click', function (e) {
			e.preventDefault();
			$('#wbop-header-image').val('0');
			$('#wbop-header-preview').empty();
			$(this).prop('disabled', true);
		});
	});
})(jQuery);
