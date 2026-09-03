/**
 * Live score for taxonomy SEO fields.
 */
(function ($) {
	'use strict';

	if (typeof damavandTermSeo === 'undefined') {
		return;
	}

	var timer = null;

	function box() {
		return $('#damavand-term-seo-box');
	}

	function render(data) {
		if (!data) {
			return;
		}
		var tone = data.tone || 'bad';
		$('.dm-term-seo .dm-score__gauge')
			.removeClass('dm-score__gauge--good dm-score__gauge--ok dm-score__gauge--bad')
			.addClass('dm-score__gauge--' + tone);
		$('#dm-term-score-num').text(data.score != null ? data.score : 0);
		var $ul = $('#dm-term-score-checks').empty();
		(data.checks || []).forEach(function (c) {
			var $li = $('<li/>').addClass(c.ok ? 'is-ok' : 'is-bad');
			$li.append($('<strong/>').text(c.label || ''));
			$li.append($('<span/>').attr('dir', 'ltr').text((c.points || 0) + '/' + (c.max || 0)));
			$li.append($('<em/>').text(c.tip || ''));
			$ul.append($li);
		});
	}

	function refresh() {
		var $b = box();
		if (!$b.length) {
			return;
		}
		var termId = parseInt($b.data('term-id'), 10) || 0;
		if (termId < 1) {
			return;
		}
		$.post(damavandTermSeo.ajaxUrl, {
			action: 'damavand_term_seo_live',
			nonce: damavandTermSeo.nonce,
			term_id: termId,
			taxonomy: ($b.data('taxonomy') || '').toString(),
			title: ($('#dm-term-seo-title').val() || '').toString(),
			desc: ($('#dm-term-seo-desc').val() || '').toString(),
			focus: ($('#dm-term-seo-focus').val() || '').toString()
		}).done(function (res) {
			if (res && res.success) {
				render(res.data);
			}
		});
	}

	$(document).on('input', '#dm-term-seo-title, #dm-term-seo-desc, #dm-term-seo-focus', function () {
		if (timer) {
			clearTimeout(timer);
		}
		timer = setTimeout(refresh, 900);
	});
})(jQuery);
