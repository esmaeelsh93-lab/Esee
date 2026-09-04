/**
 * Live Persian SEO score + SERP preview metabox.
 */
(function ($) {
	'use strict';

	function box() {
		return $('#damavand-seo-score-box');
	}

	function readSlug() {
		var s = ($('#post_name').val() || '').toString();
		if (!s) {
			s = ($('#editable-post-name-full').text() || $('#editable-post-name').text() || '').toString();
		}
		return String(s || '').replace(/^\/+|\/+$/g, '');
	}

	function readEditor(id) {
		if (window.tinymce && tinymce.get(id)) {
			return tinymce.get(id).getContent({ format: 'raw' }) || '';
		}
		return ($('#' + id).val() || '').toString();
	}

	function readContent() {
		var html = readEditor('content');
		if (window.wp && wp.data && wp.data.select) {
			try {
				var blocks = wp.data.select('core/editor');
				if (blocks && typeof blocks.getEditedPostContent === 'function') {
					var g = blocks.getEditedPostContent();
					if (g) {
						html = g;
					}
				}
			} catch (e) {}
		}
		return html;
	}

	function readExcerpt() {
		return readEditor('excerpt');
	}

	function readGalleryIds() {
		var raw = ($('#product_image_gallery').val() || '').toString();
		return raw.replace(/\s+/g, '');
	}

	function readThumbId() {
		var n = parseInt($('#_thumbnail_id').val(), 10);
		if (!n) {
			n = parseInt($('#_thumbnail_id').attr('value'), 10);
		}
		return n > 0 ? n : 0;
	}

	function setEditorSlug(slug) {
		if (!slug || !/^[a-z0-9\-]+$/.test(slug)) {
			return;
		}
		var $pn = $('#post_name');
		if ($pn.length) {
			$pn.val(slug);
		}
		if (window.wp && wp.data && wp.data.dispatch) {
			try {
				wp.data.dispatch('core/editor').editPost({ slug: slug });
			} catch (e) {}
		}
		$('#editable-post-name-full').text(slug);
		$('#editable-post-name').text(slug);
	}

	function updateSerp(data) {
		var title = ($('#dm-score-title').val() || $('#title').val() || '').toString().trim();
		var desc = ($('#dm-score-desc').val() || '').toString().trim();
		if (data && data.title) {
			title = data.title;
		}
		if (data && data.desc) {
			desc = data.desc;
		}
		$('#dm-serp-title').text(title || 'عنوان صفحه');
		$('#dm-serp-desc').text(desc || 'توضیح متا اینجا نمایش داده می‌شود…');
		if (data && data.permalink) {
			$('#dm-serp-url').text(data.permalink);
		}
		if (data && data.site_name) {
			$('#dm-serp-site').text(data.site_name);
		}
	}

	function isWooBusyTab() {
		var $panel = $('#woocommerce-product-data');
		if (!$panel.length) {
			return false;
		}
		var href = ($panel.find('.woocommerce-product-data-tabs .active a').attr('href') || '').toString();
		return href === '#product_attributes' || href === '#variable_product_options';
	}

	function isWooPanelBlocked() {
		var $panel = $('#woocommerce-product-data');
		if (!$panel.length) {
			return false;
		}
		if ($panel.data('blockUI.isBlocked')) {
			return true;
		}
		return $panel.children('.blockUI.blockOverlay').length > 0;
	}

	var wcAjaxBusy = 0;
	var wcAjaxBusySince = 0;
	var WC_AJAX_BUSY_MAX_MS = 120000;

	function wcProductDataAjaxPayload(payload) {
		if (typeof payload !== 'string' || payload.indexOf('action=woocommerce_') === -1) {
			return false;
		}
		return /woocommerce_(save_variations|load_variations|add_variation|remove_variation|remove_variations|link_all_variations|save_attributes|add_attribute|add_new_attribute|add_attributes_and_variations|add_attribute_and_term|bulk_edit_variations|get_variation|get_formatted_variation)/.test(
			payload
		);
	}

	function resetWcAjaxBusyIfStale() {
		if (wcAjaxBusy < 1) {
			wcAjaxBusySince = 0;
			return;
		}
		if (!wcAjaxBusySince) {
			wcAjaxBusySince = Date.now();
			return;
		}
		if (Date.now() - wcAjaxBusySince > WC_AJAX_BUSY_MAX_MS) {
			wcAjaxBusy = 0;
			wcAjaxBusySince = 0;
		}
	}

	function isWooCommerceBusy() {
		resetWcAjaxBusyIfStale();
		return wcAjaxBusy > 0 || isWooPanelBlocked();
	}

	function markWooAjaxBusy(data) {
		var payload = typeof data === 'string' ? data : '';
		if (!wcProductDataAjaxPayload(payload)) {
			return;
		}
		wcAjaxBusy += 1;
		if (!wcAjaxBusySince) {
			wcAjaxBusySince = Date.now();
		}
		abortPending();
	}

	function markWooAjaxIdle(data) {
		var payload = typeof data === 'string' ? data : '';
		if (!wcProductDataAjaxPayload(payload)) {
			return;
		}
		wcAjaxBusy = Math.max(0, wcAjaxBusy - 1);
		if (wcAjaxBusy < 1) {
			wcAjaxBusySince = 0;
		}
	}

	function isScoreBoxActive() {
		var $b = box();
		if (!$b.length) {
			return false;
		}
		if ($b.closest('.postbox').hasClass('closed')) {
			return false;
		}
		if (isWooCommerceBusy() || isWooBusyTab()) {
			return false;
		}
		return true;
	}

	function abortPending() {
		if (timer) {
			clearTimeout(timer);
			timer = null;
		}
		if (linkTimer) {
			clearTimeout(linkTimer);
			linkTimer = null;
		}
		if (faqTimer) {
			clearTimeout(faqTimer);
			faqTimer = null;
		}
		if (req && typeof req.abort === 'function') {
			req.abort();
			req = null;
		}
		if (linkReq && typeof linkReq.abort === 'function') {
			linkReq.abort();
			linkReq = null;
		}
		if (faqReq && typeof faqReq.abort === 'function') {
			faqReq.abort();
			faqReq = null;
		}
	}

	function renderDetailed(list) {
		var $wrap = $('#dm-score-detailed-checks');
		if (!$wrap.length) {
			if (!list.length) {
				return;
			}
			var $det = $('<details class="dm-score__detailed" open/>');
			$det.append($('<summary/>').text('چک‌لیست تکمیلی (فاز ۲)'));
			$wrap = $('<ul class="dm-score__checks dm-score__checks--detailed" id="dm-score-detailed-checks"/>');
			$det.append($wrap);
			$('.dm-score__checks').first().after($det);
		} else {
			$wrap.empty();
		}
		if (!list.length) {
			$wrap.closest('details').remove();
			return;
		}
		list.forEach(function (c) {
			var st = (c.status || 'warning').toString();
			var cls = st === 'pass' ? 'is-ok' : (st === 'fail' ? 'is-bad' : 'is-warn');
			var $li = $('<li/>').addClass(cls);
			$li.append($('<strong/>').text(c.label || ''));
			$li.append($('<em/>').text(c.message || ''));
			$wrap.append($li);
		});
	}

	function renderReadability(r) {
		if (!r || !r.sentence_count) {
			$('.dm-score__readability').remove();
			return;
		}
		var $det = $('.dm-score__readability');
		if (!$det.length) {
			$det = $('<details class="dm-score__readability" open/>');
			$det.append($('<summary/>').text('خوانایی محتوا'));
			$det.append($('<dl class="dm-score__read-dl" id="dm-score-readability"/>'));
			$('#dm-score-checks').closest('aside').find('.dm-score__detailed').last().after($det);
		}
		var $dl = $('#dm-score-readability').empty();
		var rows = [
			['میانگین کلمات در جمله', r.avg_sentence],
			['جملات بلند (>۲۵ کلمه)', (r.long_pct != null ? r.long_pct : 0) + '%'],
			['جملات مجهول (تخمینی)', r.passive_count],
			['پاراگراف بدون زیرعنوان نزدیک', (r.para_no_h_pct != null ? r.para_no_h_pct : 0) + '%']
		];
		rows.forEach(function (row) {
			$dl.append($('<dt/>').text(row[0]));
			$dl.append($('<dd/>').attr('dir', 'ltr').text(String(row[1])));
		});
	}

	function renderAdvisoryHint(n) {
		var $h = $('#dm-score-advisory-hint');
		n = parseInt(n, 10) || 0;
		if (n < 1) {
			$h.remove();
			return;
		}
		if (!$h.length) {
			$h = $('<p class="dm-score__advisory-hint description" id="dm-score-advisory-hint"/>');
			$('.dm-score__readability').length
				? $('.dm-score__readability').after($h)
				: $('#dm-score-checks').closest('aside').append($h);
		}
		$h.text('پیشنهاد بهبود از چک‌لیست تکمیلی: ~' + n + ' امتیاز بالقوه (فقط راهنما)');
	}

	function render(data, loadExtras) {
		if (!data) {
			return;
		}
		var $b = box();
		var tone = data.tone || 'bad';
		$b.find('.dm-score__gauge')
			.removeClass('dm-score__gauge--good dm-score__gauge--ok dm-score__gauge--bad')
			.addClass('dm-score__gauge--' + tone);
		$('#dm-score-num').text(data.score != null ? data.score : 0);
		$('#dm-score-title-w').text(
			'طول وزنی فارسی: ' + (data.title_weighted != null ? data.title_weighted : '—') + ' — هدف حدود ۳۸ تا ۶۸'
		);
		$('#dm-score-desc-w').text(
			'طول وزنی فارسی: ' + (data.desc_weighted != null ? data.desc_weighted : '—') + ' — هدف حدود ۹۵ تا ۱۶۵'
		);
		var $next = $('#dm-score-next');
		if (data.next_tip) {
			$next.text(data.next_tip).prop('hidden', false);
		} else {
			$next.text('').prop('hidden', true);
		}
		var $ul = $('#dm-score-checks').empty();
		(data.checks || []).forEach(function (c) {
			var $li = $('<li/>').addClass(c.ok ? 'is-ok' : 'is-bad');
			$li.append($('<strong/>').text(c.label || ''));
			$li.append($('<span/>').attr('dir', 'ltr').text((c.points || 0) + '/' + (c.max || 0)));
			$li.append($('<em/>').text(c.tip || ''));
			$ul.append($li);
		});
		renderDetailed(data.detailed_checks || []);
		renderReadability(data.readability || null);
		renderAdvisoryHint(data.advisory_hint);
		if (data.finglish) {
			$('#dm-score-finglish-preview').text(data.finglish);
		}
		updateSerp(data);
		if (loadExtras) {
			maybeLoadLinkSuggestions(data);
			maybeLoadFaqSuggestions();
		}
	}

	function needsInternalLink(data) {
		var checks = data && data.checks ? data.checks : [];
		for (var i = 0; i < checks.length; i++) {
			var c = checks[i];
			if (c && c.id === 'content' && !c.ok) {
				var tip = (c.tip || '').toString();
				if (tip.indexOf('لینک داخلی') !== -1 || tip.indexOf('لینک') !== -1) {
					return true;
				}
			}
		}
		return false;
	}

	var linkTimer = null;
	var linkReq = null;

	function renderAtRisk(rows) {
		var $box = $('#dm-score-at-risk');
		var $ul = $('#dm-score-at-risk-list').empty();
		if (!rows || !rows.length) {
			$box.prop('hidden', true);
			return;
		}
		rows.forEach(function (row) {
			var $li = $('<li class="dm-score__link-item dm-score__link-item--risk"/>');
			$li.append($('<span class="dm-score__link-title"/>').text(row.label || ''));
			$li.append($('<em class="dm-score__link-reason"/>').text(row.dest_url || ''));
			if (row.fix === 'remove_link') {
				var $btn = $('<button type="button" class="button button-small dm-score-link-fix"/>')
					.text((damavandSeoScore.i18n && damavandSeoScore.i18n.linkFix) || 'حذف')
					.data('alert-id', row.id || '')
					.data('dest-url', row.dest_url || '');
				$li.append($btn);
			}
			$ul.append($li);
		});
		$box.prop('hidden', false);
	}

	function renderLinkSuggestions(payload) {
		var $panel = $('#dm-score-links');
		var $ul = $('#dm-score-link-list').empty();
		var list = payload && payload.suggestions ? payload.suggestions : [];
		if (!list.length) {
			$panel.prop('hidden', true);
			return;
		}
		list.forEach(function (item) {
			var $li = $('<li class="dm-score__link-item"/>');
			var $cb = $('<input type="checkbox" class="dm-score-link-pick"/>')
				.attr('value', item.post_id || '')
				.prop('checked', !!item.checked);
			$li.append($cb);
			$li.append($('<span class="dm-score__link-title"/>').text(item.title || ''));
			if (item.reason) {
				$li.append($('<em class="dm-score__link-reason"/>').text(item.reason));
			}
			$ul.append($li);
		});
		$panel.prop('hidden', false);
		if (payload && payload.at_risk) {
			renderAtRisk(payload.at_risk);
		}
	}

	function maybeLoadLinkSuggestions(data) {
		if (!needsInternalLink(data)) {
			$('#dm-score-links').prop('hidden', true);
		}
		if (linkTimer) {
			clearTimeout(linkTimer);
		}
		linkTimer = setTimeout(function () {
			fetchLinkSuggestions(needsInternalLink(data));
		}, 900);
	}

	function fetchLinkSuggestions(showSuggestions) {
		var $b = box();
		if (!$b.length || typeof damavandSeoScore === 'undefined') {
			return;
		}
		if (linkReq && typeof linkReq.abort === 'function') {
			linkReq.abort();
		}
		linkReq = $.post(damavandSeoScore.ajaxUrl, {
			action: 'damavand_link_suggest',
			nonce: damavandSeoScore.nonce,
			post_id: parseInt($b.data('post-id'), 10) || 0,
			title: ($('#dm-score-title').val() || $('#title').val() || '').toString(),
			desc: ($('#dm-score-desc').val() || '').toString(),
			focus: ($('#dm-score-focus').val() || '').toString(),
			content: clip(readContent(), 20000),
			excerpt: clip(readExcerpt(), 8000)
		})
			.done(function (res) {
				if (res && res.success && res.data) {
					if (res.data.at_risk && res.data.at_risk.length) {
						renderAtRisk(res.data.at_risk);
					} else {
						$('#dm-score-at-risk').prop('hidden', true);
					}
					if (showSuggestions !== false && res.data.suggestions && res.data.suggestions.length) {
						renderLinkSuggestions(res.data);
					} else if (showSuggestions === true) {
						$('#dm-score-links').prop('hidden', false);
						$('#dm-score-link-list').empty().append(
							$('<li class="description"/>').text('پیشنهاد خودکار یافت نشد — جستجو کنید.')
						);
					}
				}
			});
	}

	window.damavandFetchLinks = function (force) {
		fetchLinkSuggestions(force !== false);
	};

	var faqTimer = null;
	var faqReq = null;
	var faqLoaded = false;

	function renderFaqSuggestions(payload) {
		var $list = $('#dm-score-faq-list').empty();
		var items = payload && payload.suggestions ? payload.suggestions : [];
		if (!items.length) {
			$list.append($('<p class="description"/>').text(damavandSeoScore.i18n.error || '—'));
			return;
		}
		items.forEach(function (item) {
			var $row = $('<div class="dm-score__faq-item"/>');
			var $head = $('<label class="dm-score__faq-head"/>');
			$head.append(
				$('<input type="checkbox" class="dm-score-faq-pick"/>').prop('checked', !!item.checked)
			);
			$head.append($('<span/>').text('سؤال'));
			$row.append($head);
			$row.append(
				$('<input type="text" class="dm-score-faq-q widefat"/>').val(item.question || '')
			);
			$row.append($('<span class="dm-score__faq-answer-label"/>').text('پاسخ'));
			$row.append(
				$('<textarea class="dm-score-faq-a widefat" rows="2"/>').val(item.answer || '')
			);
			if (
				payload.returns_url &&
				(item.kind === 'returns' || /مرجوع|تعویض|return|refund/i.test(item.question || ''))
			) {
				$row.append(
					$('<p class="description"/>').append(
						$('<span class="damavand-faq__btn"/>')
							.text('مشاهده شرایط تعویض و مرجوعی')
							.css({ pointerEvents: 'none', opacity: '0.92' })
					)
				);
			}
			$list.append($row);
		});
		var count = payload && payload.count != null ? parseInt(payload.count, 10) : 0;
		var $badge = $('#dm-score-faq-badge');
		if (count > 0) {
			$badge.text('FAQ ذخیره‌شده: ' + count + ' سؤال (Schema فعال)').prop('hidden', false);
		} else {
			$badge.prop('hidden', true);
		}
		if (payload && payload.has_faq) {
			$('#dm-score-faq-status').text(
				(damavandSeoScore.i18n && damavandSeoScore.i18n.faqHas) || ''
			);
		}
		faqLoaded = true;
	}

	window.damavandRenderFaqSuggestions = renderFaqSuggestions;

	function maybeLoadFaqSuggestions() {
		if (faqTimer) {
			clearTimeout(faqTimer);
		}
		faqTimer = setTimeout(fetchFaqSuggestions, faqLoaded ? 1200 : 200);
	}

	function fetchFaqSuggestions() {
		var $b = box();
		if (!$b.length || typeof damavandSeoScore === 'undefined') {
			return;
		}
		if (faqReq && typeof faqReq.abort === 'function') {
			faqReq.abort();
		}
		faqReq = $.post(damavandSeoScore.ajaxUrl, {
			action: 'damavand_faq_suggest',
			nonce: damavandSeoScore.nonce,
			post_id: parseInt($b.data('post-id'), 10) || 0,
			title: ($('#dm-score-title').val() || $('#title').val() || '').toString(),
			focus: ($('#dm-score-focus').val() || '').toString(),
			content: clip(readContent(), 20000),
			excerpt: clip(readExcerpt(), 8000)
		}).done(function (res) {
			if (res && res.success && res.data) {
				renderFaqSuggestions(res.data);
			}
		});
	}

	function collectFaqItems() {
		var items = [];
		$('#dm-score-faq-list .dm-score__faq-item').each(function () {
			var $row = $(this);
			if (!$row.find('.dm-score-faq-pick').prop('checked')) {
				return;
			}
			var q = ($row.find('.dm-score-faq-q').val() || '').toString().trim();
			var a = ($row.find('.dm-score-faq-a').val() || '').toString().trim();
			if (q && a) {
				items.push({ question: q, answer: a });
			}
		});
		return items;
	}

	function setEditorHtml(id, html) {
		if (window.tinymce && tinymce.get(id)) {
			tinymce.get(id).setContent(html || '');
		}
		$('#' + id).val(html || '');
	}

	var timer = null;
	var req = null;
	var lastData = null;
	var extrasLoaded = false;
	var REFRESH_MS = 1200;
	function clip(s, n) {
		s = String(s || '');
		return s.length > n ? s.slice(0, n) : s;
	}
	function scheduleSerpOnly() {
		updateSerp(null);
	}
	function schedule(forceExtras) {
		if (!isScoreBoxActive()) {
			return;
		}
		updateSerp(null);
		if (timer) {
			clearTimeout(timer);
		}
		timer = setTimeout(function () {
			refresh(forceExtras);
		}, REFRESH_MS);
	}

	function refresh(forceExtras) {
		var $b = box();
		if (!$b.length || typeof damavandSeoScore === 'undefined') {
			return;
		}
		if (!isScoreBoxActive()) {
			return;
		}
		if (req && typeof req.abort === 'function') {
			req.abort();
		}
		req = $.post(damavandSeoScore.ajaxUrl, {
			action: 'damavand_seo_score_live',
			nonce: damavandSeoScore.nonce,
			live: 1,
			post_id: parseInt($b.data('post-id'), 10) || 0,
			title: ($('#dm-score-title').val() || $('#title').val() || '').toString(),
			desc: ($('#dm-score-desc').val() || '').toString(),
			focus: ($('#dm-score-focus').val() || '').toString(),
			related: ($('#dm-score-related').val() || '').toString(),
			slug: readSlug(),
			content: clip(readContent(), 20000),
			excerpt: clip(readExcerpt(), 8000),
			gallery: readGalleryIds(),
			thumbnail_id: readThumbId()
		})
			.done(function (res) {
				if (res && res.success) {
					lastData = res.data;
					var loadExtras = !!forceExtras || !extrasLoaded;
					render(res.data, loadExtras);
					if (loadExtras) {
						extrasLoaded = true;
					}
				}
			})
			.fail(function (xhr, status) {
				if (status === 'abort') {
					return;
				}
				$('#dm-score-status').text(damavandSeoScore.i18n.error);
			});
	}

	$(function () {
		if (!box().length) {
			return;
		}
		$(document).on(
			'input change',
			'#dm-score-title, #dm-score-desc, #dm-score-focus, #dm-score-related, #post_name, #product_image_gallery, #_thumbnail_id',
			schedule
		);
		$(document).on('input change', '#title', scheduleSerpOnly);
		$(document).on('tinymce-editor-init', function (e, ed) {
			if (!ed || !ed.id) {
				return;
			}
			if (ed.id === 'content' || ed.id === 'excerpt') {
				ed.on('keyup change SetContent undo redo paste', schedule);
			}
		});
		if (window.tinymce) {
			['content', 'excerpt'].forEach(function (id) {
				var ed = tinymce.get(id);
				if (ed) {
					ed.on('keyup change SetContent undo redo paste', schedule);
				}
			});
		}
		var galleryList = document.querySelector('#product_images_container ul.product_images') || document.getElementById('product_images_container');
		if (galleryList && window.MutationObserver) {
			new MutationObserver(function () {
				if (isScoreBoxActive()) {
					schedule();
				}
			}).observe(galleryList, { childList: true, subtree: false });
		}
		var thumbInput = document.getElementById('_thumbnail_id');
		if (thumbInput && window.MutationObserver) {
			new MutationObserver(function () {
				if (isScoreBoxActive()) {
					schedule();
				}
			}).observe(thumbInput, { attributes: true, attributeFilter: ['value'] });
		}
		$(document).on('click', '#set-post-thumbnail, #remove-post-thumbnail, .add_product_images a, .delete', function () {
			window.setTimeout(function () {
				if (isScoreBoxActive()) {
					schedule();
				}
			}, 600);
		});
		$(document).on('click', '.woocommerce-product-data-tabs a', function () {
			if (isWooBusyTab()) {
				abortPending();
				return;
			}
			window.setTimeout(function () {
				if (isScoreBoxActive()) {
					schedule();
				}
			}, 400);
		});
		$(document).ajaxSend(function (ev, xhr, settings) {
			markWooAjaxBusy(settings && settings.data ? settings.data : '');
		});
		$(document).ajaxComplete(function (ev, xhr, settings) {
			markWooAjaxIdle(settings && settings.data ? settings.data : '');
			var payload = settings && settings.data ? settings.data : '';
			if (wcProductDataAjaxPayload(payload) && wcAjaxBusy < 1 && isScoreBoxActive()) {
				window.setTimeout(function () {
					schedule(true);
				}, 500);
			}
		});
		$(document).ajaxError(function (ev, xhr, settings) {
			markWooAjaxIdle(settings && settings.data ? settings.data : '');
		});
		$(document).on('click', '#damavand-seo-score-box .handlediv, #damavand-seo-score-box .hndle', function () {
			window.setTimeout(function () {
				if (isScoreBoxActive()) {
					schedule(true);
				}
			}, 300);
		});
		$(document).on('click', '#dm-score-load-extras', function () {
			if (lastData) {
				maybeLoadLinkSuggestions(lastData);
			} else {
				fetchLinkSuggestions(true);
			}
			fetchFaqSuggestions();
		});
		$(document).on('click', '#dm-score-link-refresh', function () {
			fetchLinkSuggestions(true);
		});

		var linkSearchTimer = null;
		$(document).on('input', '#dm-score-link-search-input', function () {
			var q = ($(this).val() || '').toString().trim();
			var $b = box();
			clearTimeout(linkSearchTimer);
			if (q.length < 2) {
				$('#dm-score-link-search-results').empty();
				return;
			}
			linkSearchTimer = setTimeout(function () {
				$.post(damavandSeoScore.ajaxUrl, {
					action: 'damavand_link_search',
					nonce: damavandSeoScore.nonce,
					post_id: parseInt($b.data('post-id'), 10) || 0,
					q: q
				}).done(function (res) {
					var $ul = $('#dm-score-link-search-results').empty();
					if (!res || !res.success || !res.data || !res.data.results || !res.data.results.length) {
						$ul.append($('<li class="description"/>').text('نتیجه‌ای یافت نشد.'));
						$('#dm-score-links').prop('hidden', false);
						return;
					}
					res.data.results.forEach(function (row) {
						var $li = $('<li class="dm-score__link-item"/>');
						$li.append(
							$('<input type="checkbox" class="dm-score-link-pick"/>')
								.attr('value', row.post_id || '')
								.prop('checked', true)
						);
						$li.append(
							$('<span class="dm-score__link-title"/>').text(
								(row.type ? row.type + ': ' : '') + (row.title || '')
							)
						);
						$ul.append($li);
					});
					$('#dm-score-links').prop('hidden', false);
				});
			}, 350);
		});

		$(document).on('click', '#dm-score-finglish', function () {
			var $b = box();
			$('#dm-score-status').text('...');
			$.post(damavandSeoScore.ajaxUrl, {
				action: 'damavand_seo_score_finglish',
				nonce: damavandSeoScore.nonce,
				post_id: parseInt($b.data('post-id'), 10) || 0,
				title: $('#dm-score-title').val() || $('#title').val() || ''
			}).done(function (res) {
				if (res && res.success && res.data && res.data.slug) {
					setEditorSlug(res.data.slug);
					$('#dm-score-finglish-preview').text(res.data.slug);
					$('#dm-score-status').text(res.data.message || damavandSeoScore.i18n.applied);
					schedule(true);
				} else {
					$('#dm-score-status').text((res && res.data && res.data.message) || damavandSeoScore.i18n.error);
				}
			}).fail(function () {
				$('#dm-score-status').text(damavandSeoScore.i18n.error);
			});
		});
		$(document).on('click', '#dm-score-link-inject', function () {
			var $b = box();
			var ids = [];
			$('.dm-score-link-pick:checked').each(function () {
				var v = parseInt($(this).val(), 10);
				if (v > 0) {
					ids.push(v);
				}
			});
			if (!ids.length) {
				$('#dm-score-status').text(damavandSeoScore.i18n.linkPick || 'انتخاب کنید');
				return;
			}
			$('#dm-score-status').text(damavandSeoScore.i18n.linkInject || '...');
			$.post(damavandSeoScore.ajaxUrl, {
				action: 'damavand_link_inject',
				nonce: damavandSeoScore.nonce,
				post_id: parseInt($b.data('post-id'), 10) || 0,
				target_ids: ids,
				content: clip(readContent(), 20000),
				excerpt: clip(readExcerpt(), 8000),
				preview_only: 1
			}).done(function (res) {
				if (res && res.success && res.data) {
					if (res.data.field === 'post_excerpt') {
						setEditorHtml('excerpt', res.data.excerpt || '');
					} else {
						setEditorHtml('content', res.data.content || '');
					}
					$('#dm-score-status').text(res.data.message || '');
					schedule();
				} else {
					$('#dm-score-status').text((res && res.data && res.data.message) || damavandSeoScore.i18n.error);
				}
			}).fail(function () {
				$('#dm-score-status').text(damavandSeoScore.i18n.error);
			});
		});
		$(document).on('click', '.dm-score-link-fix', function () {
			var $b = box();
			var $btn = $(this);
			$.post(damavandSeoScore.ajaxUrl, {
				action: 'damavand_link_fix_alert',
				nonce: damavandSeoScore.nonce,
				post_id: parseInt($b.data('post-id'), 10) || 0,
				dest_url: $btn.data('dest-url') || '',
				alert_id: $btn.data('alert-id') || ''
			}).done(function (res) {
				if (res && res.success) {
					$btn.closest('.dm-score__link-item').remove();
					$('#dm-score-status').text(res.data.message || '');
					schedule();
				} else {
					$('#dm-score-status').text((res && res.data && res.data.message) || damavandSeoScore.i18n.error);
				}
			});
		});
		$(document).on('click', '#dm-score-faq-inject', function () {
			var $b = box();
			var items = collectFaqItems();
			if (!items.length) {
				$('#dm-score-faq-status').text(
					(damavandSeoScore.i18n && damavandSeoScore.i18n.faqPick) || 'انتخاب کنید'
				);
				return;
			}
			$('#dm-score-faq-status').text(
				(damavandSeoScore.i18n && damavandSeoScore.i18n.faqInject) || '...'
			);
			$.post(damavandSeoScore.ajaxUrl, {
				action: 'damavand_faq_inject',
				nonce: damavandSeoScore.nonce,
				post_id: parseInt($b.data('post-id'), 10) || 0,
				items: JSON.stringify(items),
				content: clip(readContent(), 20000),
				excerpt: clip(readExcerpt(), 8000)
			})
				.done(function (res) {
					if (res && res.success && res.data) {
						if (res.data.field === 'post_excerpt') {
							setEditorHtml('excerpt', res.data.excerpt || '');
						} else {
							setEditorHtml('content', res.data.content || '');
						}
						$('#dm-score-faq-status').text(res.data.message || '');
						if (res.data.count > 0) {
							$('#dm-score-faq-badge')
								.text('FAQ ذخیره‌شده: ' + res.data.count + ' سؤال (Schema فعال)')
								.prop('hidden', false);
						}
						schedule();
					} else {
						$('#dm-score-faq-status').text(
							(res && res.data && res.data.message) || damavandSeoScore.i18n.error
						);
					}
				})
				.fail(function () {
					$('#dm-score-faq-status').text(damavandSeoScore.i18n.error);
				});
		});
		$(document).on('click', '#dm-score-apply-tpl', function () {
			var $b = box();
			var $btn = $(this);
			var force = !!$btn.data('force');
			$('#dm-score-status').text('...');
			$.post(damavandSeoScore.ajaxUrl, {
				action: 'damavand_seo_score_apply_tpl',
				nonce: damavandSeoScore.nonce,
				post_id: parseInt($b.data('post-id'), 10) || 0,
				force: force ? 1 : 0,
				title: ($('#title').val() || '').toString(),
				focus: ($('#dm-score-focus').val() || '').toString(),
				excerpt: clip(readExcerpt(), 8000),
				content: clip(readContent(), 20000)
			}).done(function (res) {
				if (!res || !res.success || !res.data) {
					$('#dm-score-status').text((res && res.data && res.data.message) || damavandSeoScore.i18n.error);
					return;
				}
				var titleEmpty = !($('#dm-score-title').val() || '').toString().trim();
				var descEmpty = !($('#dm-score-desc').val() || '').toString().trim();
				var filled = false;
				if ((force || titleEmpty) && res.data.title_raw) {
					$('#dm-score-title').val(res.data.title_raw);
					filled = true;
				}
				if ((force || descEmpty) && res.data.desc_raw) {
					$('#dm-score-desc').val(res.data.desc_raw);
					filled = true;
				}
				if (filled) {
					$btn.data('force', false);
					$('#dm-score-status').text(damavandSeoScore.i18n.tplOk || res.data.message);
					schedule();
				} else {
					$btn.data('force', true);
					$('#dm-score-status').text(damavandSeoScore.i18n.tplFill || res.data.message);
				}
			}).fail(function () {
				$('#dm-score-status').text(damavandSeoScore.i18n.error);
			});
		});
		$(document).on('click', '#dm-score-schema-load', function () {
			var $b = box();
			var postId = parseInt($b.data('post-id'), 10) || 0;
			if (!postId) {
				return;
			}
			$('#dm-score-schema-status').text('...');
			$.post(damavandSeoScore.ajaxUrl, {
				action: 'damavand_schema_preview',
				nonce: damavandSeoScore.nonce,
				post_id: postId
			}).done(function (res) {
				if (!res || !res.success) {
					$('#dm-score-schema-status').text((res && res.data && res.data.message) || damavandSeoScore.i18n.error);
					return;
				}
				var lines = [];
				(res.data.blocks || []).forEach(function (b) {
					lines.push('/* ' + (b.kind || 'schema') + ' */');
					lines.push(b.json || '');
				});
				if (lines.length) {
					$('#dm-score-schema-pre').text(lines.join('\n\n')).prop('hidden', false);
				} else {
					$('#dm-score-schema-pre').text('').prop('hidden', true);
				}
				if (res.data.permalink) {
					$('#dm-score-schema-rrt').attr(
						'href',
						'https://search.google.com/test/rich-results?url=' + encodeURIComponent(res.data.permalink)
					);
				}
				$('#dm-score-schema-status').text(res.data.message || '');
			}).fail(function () {
				$('#dm-score-schema-status').text(damavandSeoScore.i18n.error);
			});
		});
		if (window.wp && wp.data && wp.data.subscribe) {
			var gbTimer = null;
			wp.data.subscribe(function () {
				if (!box().length || !isScoreBoxActive()) {
					return;
				}
				if (gbTimer) {
					clearTimeout(gbTimer);
				}
				gbTimer = setTimeout(function () {
					schedule();
				}, REFRESH_MS);
			});
		}
		if ($('#product-type').val() === 'variable') {
			window.setTimeout(function () {
				if (isScoreBoxActive()) {
					schedule();
				}
			}, 1800);
		} else {
			schedule();
		}

		/* Tabs */
		box().on('click', '.dm-score__tab', function () {
			var tab = $(this).data('dm-tab');
			box().find('.dm-score__tab').removeClass('is-active').attr('aria-selected', 'false');
			$(this).addClass('is-active').attr('aria-selected', 'true');
			box().find('.dm-score__panel').prop('hidden', true).removeClass('is-active');
			box().find('.dm-score__panel[data-dm-panel="' + tab + '"]').prop('hidden', false).addClass('is-active');
		});

		/* Focus keyword follows product title until user edits it */
		var $focus = $('#dm-score-focus');
		var focusAuto = $focus.data('auto-from-title') === 1 || $focus.data('auto-from-title') === '1';
		$focus.on('input', function () {
			focusAuto = false;
			$focus.attr('data-auto-from-title', '0');
		});
		$('#title').on('input change', function () {
			if (!focusAuto) {
				return;
			}
			var t = ($(this).val() || '').toString().trim();
			if (t) {
				$focus.val(t);
				schedule();
			}
		});

	});
})(jQuery);
