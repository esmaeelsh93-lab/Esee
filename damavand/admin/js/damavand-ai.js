/**
 * Damavand content generation — Groq / OpenRouter (+ relay).
 */
(function ($) {
	'use strict';

	if (typeof damavandAI === 'undefined') {
		return;
	}

	function postId() {
		var $b = $('#damavand-seo-score-box');
		return parseInt($b.data('post-id'), 10) || 0;
	}

	function ctx() {
		var title = ($('#title').val() || '').toString().trim();
		var keyword = ($('#dm-score-focus').val() || '').toString().trim();
		if (!keyword) {
			keyword = title;
		}
		return {
			nonce: damavandAI.nonce,
			post_id: postId(),
			title: title,
			keyword: keyword,
			extra: ($('#dm-ollama-extra').val() || '').toString()
		};
	}

	function setStatus(msg, isErr) {
		$('#dm-ollama-status')
			.text(msg || '')
			.css('color', isErr ? '#b32d2e' : '');
	}

	function setBusy(on) {
		$('#dm-ollama-panel [data-ai]').prop('disabled', !!on);
	}

	function readContent() {
		if (window.tinymce && tinymce.get('content')) {
			return tinymce.get('content').getContent({ format: 'raw' }) || '';
		}
		return ($('#content').val() || '').toString();
	}

	function setEditorHtml(id, html) {
		if (window.tinymce && tinymce.get(id)) {
			tinymce.get(id).setContent(html || '');
		}
		$('#' + id).val(html || '');
	}

	function readGalleryIds() {
		var ids = [];
		var thumb = parseInt($('#_thumbnail_id').val(), 10);
		if (thumb > 0) {
			ids.push(thumb);
		}
		var raw = ($('#product_image_gallery').val() || '').toString();
		raw.split(',').forEach(function (part) {
			var n = parseInt(part, 10);
			if (n > 0 && ids.indexOf(n) === -1) {
				ids.push(n);
			}
		});
		return ids;
	}

	function ajaxErrorMessage(xhr, fallback) {
		if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
			return xhr.responseJSON.data.message;
		}
		if (xhr && xhr.status === 0) {
			return 'اتصال قطع شد (timeout یا مسدودیت شبکه).';
		}
		if (xhr && xhr.status >= 500) {
			return 'خطای سرور (' + xhr.status + ').';
		}
		return fallback || damavandAI.i18n.error;
	}

	function pickBuyTitle(titles) {
		if (!titles || !titles.length) {
			return '';
		}
		var pick = titles[0];
		titles.forEach(function (t) {
			if (t && t.indexOf('خرید') >= 0) {
				pick = t;
			}
		});
		return pick;
	}

	function renderTitlePicker(titles) {
		var $box = $('#dm-ollama-titles').empty().prop('hidden', false);
		if (!titles || !titles.length) {
			return;
		}
		titles.forEach(function (t, i) {
			var $btn = $('<button type="button" class="button button-small"/>')
				.text(i + 1 + '. ' + t)
				.on('click', function () {
					$('#dm-score-title').val(t).trigger('input');
					setStatus(damavandAI.i18n.done);
				});
			$box.append($btn).append(' ');
		});
	}

	function renderFaqFromAI(faqs) {
		if (!faqs || !faqs.length) {
			return;
		}
		var payload = {
			suggestions: faqs.map(function (row) {
				return {
					question: row.question || '',
					answer: row.answer || '',
					checked: true
				};
			}),
			count: 0
		};
		if (typeof window.damavandRenderFaqSuggestions === 'function') {
			window.damavandRenderFaqSuggestions(payload);
		}
		$('#dm-score-faq').prop('hidden', false);
	}

	var pendingDraft = null;

	function applyPackToFields(d) {
		if (!d) {
			return;
		}
		if (d.meta_title) {
			$('#dm-score-title').val(d.meta_title).trigger('input');
		}
		if (d.meta_desc) {
			$('#dm-score-desc').val(d.meta_desc).trigger('input');
		}
		if (d.short_desc) {
			setEditorHtml('excerpt', d.short_desc);
		}
		if (d.long_desc) {
			setEditorHtml('content', d.long_desc);
		}
		if (d.faqs && typeof window.damavandRenderFaqSuggestions === 'function') {
			renderFaqFromAI(d.faqs);
		}
	}

	function showDraftPanel(d) {
		pendingDraft = d;
		$('#dm-ai-draft-panel').prop('hidden', false);
		$('#dm-ai-draft-msg').text(d.draft_message || damavandAI.i18n.draftReady || '');
		setStatus(d.draft_message || damavandAI.i18n.draftReady || damavandAI.i18n.done);
	}

	function afterContentGenerated() {
		if (typeof window.damavandFetchLinks === 'function') {
			window.damavandFetchLinks(true);
		}
		$('#dm-score-links').prop('hidden', false);
	}

	function applyResult(kind, d) {
		if (typeof d === 'string') {
			if (kind === 'meta_desc') {
				$('#dm-score-desc').val(d).trigger('input');
			} else if (kind === 'short_desc') {
				setEditorHtml('excerpt', d);
			} else if (kind === 'long_desc') {
				setEditorHtml('content', d);
				setStatus(damavandAI.i18n.done);
				afterContentGenerated();
				return;
			} else if (kind === 'llms_txt') {
				$('#shojaei-ai-llms-preview').val(d);
			} else if (kind === 'slug' && d) {
				/* فقط پیشنهاد — هرگز post_name را خودکار عوض نکن */
				$('#dm-score-finglish-preview').text(d);
				setStatus('پیشنهاد نامک: ' + d + ' — برای اعمال دستی در فیلد نامک کپی کنید (تغییر خودکار ممنوع).');
				return;
			}
			setStatus(damavandAI.i18n.done);
			return;
		}

		if (kind === 'keywords' && d && d.primary) {
			$('#dm-score-focus').val(d.primary).trigger('input');
			if (d.secondary && d.secondary.length) {
				$('#dm-score-related').val(d.secondary.join(', '));
			}
			setStatus(damavandAI.i18n.done);
			return;
		}
		if (kind === 'meta_titles' && d && d.titles) {
			renderTitlePicker(d.titles);
			setStatus(damavandAI.i18n.pickTitle);
			return;
		}
		if (kind === 'faq' && d && d.faqs) {
			renderFaqFromAI(d.faqs);
			if (d.schema) {
				$('#dm-ollama-schema-pre').text(JSON.stringify(d.schema, null, 2)).prop('hidden', false);
			}
			setStatus(damavandAI.i18n.done);
			return;
		}
		if (kind === 'alt_texts' && d && typeof d === 'object') {
			var ok = 0;
			var fail = 0;
			Object.keys(d).forEach(function (id) {
				var row = d[id];
				if (row && row.alt) {
					ok++;
				} else if (row && row.error) {
					fail++;
				}
			});
			var msg = ok
				? 'Alt برای ' + ok + ' تصویر در کتابخانه رسانه ذخیره شد.'
				: damavandAI.i18n.error;
			if (fail) {
				msg += ' (' + fail + ' خطا)';
			}
			setStatus(msg, !ok);
			return;
		}
		if (kind === 'itemlist' && d && d['@type'] === 'ItemList') {
			$('#dm-ollama-schema-pre').text(JSON.stringify(d, null, 2)).prop('hidden', false);
			setStatus(damavandAI.i18n.done);
			return;
		}
		if (kind === 'full_pack' && d) {
			if (d.draft && damavandAI.draftMode) {
				showDraftPanel(d);
				afterContentGenerated();
				return;
			}
			applyPackToFields(d);
			var altMsg = '';
			if (d.alt_saved) {
				altMsg = ' — Alt ' + d.alt_saved + ' تصویر ذخیره شد';
			}
			setStatus(damavandAI.i18n.done + altMsg);
			afterContentGenerated();
			return;
		}
		setStatus(damavandAI.i18n.done);
	}

	function extraForKind(kind) {
		if (kind === 'faq') {
			return { article: readContent() };
		}
		if (kind === 'alt_texts') {
			return { image_ids: readGalleryIds() };
		}
		return {};
	}

	function generate(kind) {
		if (!damavandAI.enabled) {
			setStatus('موتور تولید خاموش است یا کلید API ذخیره نشده.', true);
			return;
		}
		if (kind === 'alt_texts' && !readGalleryIds().length) {
			setStatus('تصویر شاخص یا گالری انتخاب نشده.', true);
			return;
		}

		generateAsync(kind).then(function (d) {
			applyResult(kind, d);
		}).catch(function (err) {
			setStatus(err || damavandAI.i18n.error, true);
		});
	}

	function generateAsync(kind, quiet) {
		if (!damavandAI.enabled) {
			return $.Deferred().reject('موتور تولید خاموش است یا کلید API ذخیره نشده.').promise();
		}
		if (kind === 'alt_texts' && !readGalleryIds().length) {
			return $.Deferred().reject('تصویر شاخص یا گالری انتخاب نشده.').promise();
		}

		var payload = $.extend({}, ctx(), extraForKind(kind), {
			action: kind === 'itemlist' ? 'shojaei_ai_itemlist' : 'shojaei_ai_generate',
			job_kind: kind
		});

		if (!quiet) {
			setBusy(true);
			setStatus(damavandAI.i18n.working);
		}

		return $.ajax({
			url: damavandAI.ajaxUrl,
			type: 'POST',
			data: payload,
			timeout: kind === 'full_pack' || kind === 'long_desc' || kind === 'alt_texts' ? 240000 : 60000
		}).then(function (res) {
			if (!res || !res.success) {
				var msg = (res && res.data && res.data.message) || damavandAI.i18n.error;
				if (res && res.status === 429) {
					msg = damavandAI.i18n.rateLimit || msg;
				}
				return $.Deferred().reject(msg).promise();
			}
			return res.data && res.data.data !== undefined ? res.data.data : res.data;
		}).always(function () {
			if (!quiet) {
				setBusy(false);
			}
		});
	}

	function setAutoProgress(pct, label) {
		$('#dm-auto-seo-progress').prop('hidden', false);
		$('#dm-auto-seo-bar-fill').css('width', Math.max(0, Math.min(100, pct)) + '%');
		$('#dm-auto-seo-step').text(label || '');
	}

	function renderChecklist(items) {
		var $ul = $('#dm-auto-seo-checklist').empty();
		if (!items || !items.length) {
			return;
		}
		items.forEach(function (row) {
			var mark = row.ok ? '✓' : '○';
			$('<li/>')
				.text(mark + ' ' + (row.label || '') + (row.detail ? ' — ' + row.detail : ''))
				.css('color', row.ok ? '#1d6f42' : '#b32d2e')
				.appendTo($ul);
		});
	}

	function fetchChecklist() {
		return $.post(damavandAI.ajaxUrl, $.extend({}, ctx(), {
			action: 'shojaei_ai_validate_seo',
			nonce: damavandAI.nonce
		})).then(function (res) {
			if (res && res.success && res.data && res.data.checklist) {
				renderChecklist(res.data.checklist);
			}
		});
	}

	function runAutoSeo() {
		if (!damavandAI.enabled) {
			setStatus('موتور تولید خاموش است یا کلید API ذخیره نشده.', true);
			return;
		}
		var title = ($('#title').val() || '').toString().trim();
		if (!title) {
			setStatus('ابتدا عنوان محصول را بنویسید.', true);
			return;
		}

		var steps = [
			{ kind: 'keywords', label: 'پیشنهاد کلمه کلیدی' },
			{ kind: 'meta_titles', label: 'عنوان متا' },
			{ kind: 'meta_desc', label: 'توضیح متا' },
			{ kind: 'short_desc', label: 'توضیح کوتاه' },
			{ kind: 'long_desc', label: 'توضیح کامل' },
			{ kind: 'faq', label: 'FAQ' }
		];
		if (readGalleryIds().length) {
			steps.push({ kind: 'alt_texts', label: 'Alt تصاویر' });
		}
		/* نامک/Slug: طبق نقشه راه — هیچ تغییر خودکاری (حتی در سئو خودکار) مجاز نیست. */

		var total = steps.length + 1;
		var idx = 0;
		$('#dm-auto-seo-checklist').empty();
		setAutoProgress(0, damavandAI.i18n.autoSeo + '…');
		$('#dm-auto-seo-run').prop('disabled', true);
		setBusy(true);

		function next() {
			if (idx >= steps.length) {
				setAutoProgress(95, 'بررسی چک‌لیست سئو…');
				return fetchChecklist().always(function () {
					setAutoProgress(100, damavandAI.i18n.done);
					setStatus(damavandAI.i18n.done);
					afterContentGenerated();
					$('#dm-auto-seo-run').prop('disabled', false);
					setBusy(false);
				});
			}
			var step = steps[idx];
			setAutoProgress(Math.round((idx / total) * 100), step.label + '…');
			generateAsync(step.kind, true).done(function (d) {
				if (step.kind === 'keywords' && d && d.primary) {
					$('#dm-score-focus').val(d.primary).trigger('input');
					if (d.secondary && d.secondary.length) {
						$('#dm-score-related').val(d.secondary.join(', '));
					}
				} else if (step.kind === 'meta_titles' && d && d.titles && d.titles.length) {
					$('#dm-score-title').val(pickBuyTitle(d.titles)).trigger('input');
				} else if (step.kind === 'meta_desc' && typeof d === 'string') {
					$('#dm-score-desc').val(d).trigger('input');
				} else if (step.kind === 'short_desc' && typeof d === 'string') {
					setEditorHtml('excerpt', d);
				} else if (step.kind === 'long_desc' && typeof d === 'string') {
					setEditorHtml('content', d);
					syncSizeChartUi();
				} else if (step.kind === 'faq' && d && d.faqs) {
					renderFaqFromAI(d.faqs);
				}
				/* slug never in auto steps */
				idx += 1;
				next();
			}).fail(function (msg) {
				setStatus(msg || damavandAI.i18n.error, true);
				setAutoProgress(Math.round((idx / total) * 100), 'متوقف شد: ' + (msg || ''));
				$('#dm-auto-seo-run').prop('disabled', false);
				setBusy(false);
			});
		}

		next();
	}

	function settingsPayload() {
		var selModel = ($('#shojaei_seo_ai_model').val() || '').toString().trim();
		var payload = {
			provider: ($('#shojaei_seo_ai_provider').val() || '').toString(),
			model: selModel
		};
		var key = ($('#shojaei_seo_ai_api_key').val() || '').toString().trim();
		if (key && key.indexOf('••••') !== 0) {
			payload.api_key = key;
		}
		if ('__custom__' === selModel) {
			var custom = ($('#shojaei_seo_ai_model_custom').val() || '').toString().trim();
			if (custom) {
				payload.model = custom;
			}
		}
		return payload;
	}

	function syncAiProviderUi() {
		var provider = ($('#shojaei_seo_ai_provider').val() || 'openrouter').toString();
		$('.shojaei-ai-provider-hint').hide();
		$('.shojaei-ai-provider-hint[data-provider="' + provider + '"]').show();
		$('.shojaei-ai-provider-panel').hide();
		$('.shojaei-ai-provider-panel[data-provider="' + provider + '"]').show();
		if ('gemini' === provider) {
			$('.shojaei-ai-relay-fields').hide();
		} else {
			$('.shojaei-ai-relay-fields').show();
		}
	}

	function rebuildModelSelect(provider, preferred) {
		var $sel = $('#shojaei_seo_ai_model');
		if (!$sel.length || !damavandAI.modelPresets) {
			return;
		}
		var rows = damavandAI.modelPresets[provider] || damavandAI.modelPresets.openrouter || [];
		var current = preferred || ($sel.val() || '').toString();
		$sel.empty();
		var matched = false;
		rows.forEach(function (row) {
			var selected = row.id === current;
			if (selected) {
				matched = true;
			}
			$sel.append(
				$('<option>', { value: row.id, text: row.label, selected: selected })
			);
		});
		$sel.append(
			$('<option>', { value: '__custom__', text: 'مدل سفارشی…', selected: !matched && current !== '' })
		);
		var $custom = $('#shojaei_seo_ai_model_custom');
		if (!matched && current && current !== '__custom__') {
			$custom.val(current).show();
		} else if ('__custom__' === current) {
			$custom.show();
		} else {
			$custom.val('').hide();
		}
	}

	$(function () {
		$(document).on('click', '#dm-ai-draft-apply', function () {
			if (pendingDraft) {
				applyPackToFields(pendingDraft);
				$('#dm-ai-draft-panel').prop('hidden', true);
				setStatus(damavandAI.i18n.done);
				pendingDraft = null;
			}
		});
		$(document).on('click', '#dm-ai-draft-discard', function () {
			pendingDraft = null;
			$('#dm-ai-draft-panel').prop('hidden', true);
			setStatus('');
		});

		$(document).on('click', '#dm-auto-seo-run', function () {
			runAutoSeo();
		});

		$(document).on('click', '#dm-ollama-panel [data-ai]', function () {
			generate($(this).data('ai'));
		});

		$(document).on('click', '#shojaei-ai-key-toggle', function () {
			var $inp = $('#shojaei_seo_ai_api_key');
			var t = $inp.attr('type') === 'password' ? 'text' : 'password';
			$inp.attr('type', t);
			$(this).text(t === 'password' ? 'نمایش' : 'مخفی');
		});

		$(document).on('change', '#shojaei_seo_ai_provider', function () {
			var provider = ($(this).val() || 'openrouter').toString();
			rebuildModelSelect(provider);
			syncAiProviderUi();
		});

		$(document).on('change', '#shojaei_seo_ai_model', function () {
			var isCustom = '__custom__' === ($(this).val() || '').toString();
			$('#shojaei_seo_ai_model_custom').toggle(isCustom);
		});

		if ($('#shojaei_seo_ai_provider').length) {
			syncAiProviderUi();
		}

		$('#shojaei-ai-health').on('click', function () {
			var $out = $('#shojaei-ai-health-result').show().text(damavandAI.i18n.testing);
			var $box = $('#shojaei-ai-status-box');
			var payload = $.extend(
				{
					action: 'shojaei_ai_test_connection',
					nonce: damavandAI.nonce
				},
				settingsPayload()
			);
			$.post(damavandAI.ajaxUrl, payload)
				.done(function (res) {
					if (res && res.success) {
						var ms = res.data && res.data.latency ? res.data.latency + ' ms' : '';
						var msg = (res.data && res.data.message) || damavandAI.i18n.done;
						$out.text(msg + (ms ? ' — ' + ms : ''));
						$box.removeClass('is-disconnected').addClass('is-connected');
						$('#shojaei-ai-status-label').text(msg + (ms ? ' (' + ms + ')' : ''));
					} else {
						var errMsg = (res && res.data && res.data.message) || damavandAI.i18n.error;
						$out.text(errMsg);
						$box.removeClass('is-connected').addClass('is-disconnected');
						$('#shojaei-ai-status-label').text('خطا در اتصال');
					}
				})
				.fail(function (xhr) {
					$out.text(ajaxErrorMessage(xhr, damavandAI.i18n.error));
					$box.removeClass('is-connected').addClass('is-disconnected');
				});
		});

		$('#shojaei-ai-llms').on('click', function () {
			generate('llms_txt');
		});

		$('#shojaei-ai-llms-write').on('click', function () {
			$.post(damavandAI.ajaxUrl, {
				action: 'shojaei_ai_write_llms',
				nonce: damavandAI.nonce
			}).done(function (res) {
				if (res && res.success) {
					alert((res.data && res.data.path) || damavandAI.i18n.done);
				} else {
					alert((res && res.data && res.data.message) || damavandAI.i18n.error);
				}
			});
		});

		$('#shojaei-ai-bulk-alt').on('click', function () {
			var $st = $('#shojaei-ai-bulk-alt-status').text(damavandAI.i18n.working);
			$.post(damavandAI.ajaxUrl, {
				action: 'shojaei_ai_bulk_alt_start',
				nonce: damavandAI.nonce
			}).done(function (res) {
				if (!res || !res.success) {
					$st.text((res && res.data && res.data.message) || damavandAI.i18n.error);
					return;
				}
				if (!res.data.job_id) {
					$st.text(res.data.message || damavandAI.i18n.done);
					return;
				}
				var jobId = res.data.job_id;
				var workerNonce = res.data.worker_nonce || '';
				if (workerNonce) {
					$.post(damavandAI.ajaxUrl, {
						action: 'shojaei_ai_bulk_alt_run',
						job_key: jobId,
						nonce: workerNonce
					});
				}
				var ticks = 0;
				var timer = setInterval(function () {
					ticks += 1;
					$.post(damavandAI.ajaxUrl, {
						action: 'shojaei_ai_bulk_alt_status',
						nonce: damavandAI.nonce,
						job_id: jobId
					}).done(function (st) {
						if (!st || !st.success) {
							return;
						}
						var d = st.data || {};
						$st.text(
							(d.processed || 0) + ' / ' + (d.total || 0) + ' — ' + (d.status || '')
						);
						if (d.status === 'done' || d.status === 'failed' || d.status === 'cancelled' || ticks > 90) {
							clearInterval(timer);
						}
					});
				}, 2500);
			});
		});
	});
})(jQuery);
