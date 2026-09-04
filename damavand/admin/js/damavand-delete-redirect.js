/**
 * Intercept trash/delete — suggest smart redirect targets.
 */
(function ($) {
	'use strict';

	if (typeof damavandDeleteRedirect === 'undefined') {
		return;
	}

	var pendingAction = null;
	var selectedTargetId = 0;
	var selectedTargetType = '';
	var currentPostId = 0;
	var searchTimer = null;

	function modal() {
		return $('#damavand-delete-redirect-modal');
	}

	function openModal(postId, actionEl) {
		currentPostId = parseInt(postId, 10) || 0;
		pendingAction = actionEl || null;
		selectedTargetId = 0;
		selectedTargetType = '';
		$('#damavand-delete-dest').val('');
		$('#damavand-delete-status').text('');
		$('#damavand-delete-search-results').empty();
		$('#damavand-delete-suggest-list').html('<li>…</li>');
		modal().prop('hidden', false).attr('aria-hidden', 'false');

		$.post(damavandDeleteRedirect.ajaxUrl, {
			action: 'damavand_delete_redirect_suggest',
			nonce: damavandDeleteRedirect.nonce,
			post_id: currentPostId
		}).done(function (res) {
			if (!res || !res.success || !res.data) {
				return;
			}
			$('#damavand-delete-source').text(res.data.source_url || '');
			renderSuggestions(res.data.suggestions || []);
			if (res.data.suggestions && res.data.suggestions.length) {
				var first = res.data.suggestions[0];
				if (first && first.url) {
					$('#damavand-delete-dest').val(first.url);
					selectedTargetId = parseInt(first.id, 10) || 0;
					selectedTargetType = first.type || '';
				}
			} else {
				$('#damavand-delete-status').text(damavandDeleteRedirect.i18n.noSuggest);
			}
		});
	}

	function closeModal() {
		modal().prop('hidden', true).attr('aria-hidden', 'true');
		pendingAction = null;
		currentPostId = 0;
	}

	function renderSuggestions(list) {
		var $ul = $('#damavand-delete-suggest-list').empty();
		if (!list.length) {
			$ul.append($('<li class="description"/>').text(damavandDeleteRedirect.i18n.noSuggest));
			return;
		}
		list.forEach(function (item) {
			var $btn = $('<button type="button" class="button-link damavand-delete-pick"/>')
				.text(item.title || '')
				.data('id', item.id || 0)
				.data('type', item.type || '')
				.data('url', item.url || '');
			var $li = $('<li class="damavand-delete-suggest-item"/>');
			$li.append($btn);
			if (item.reason) {
				$li.append($('<em/>').text(' — ' + item.reason));
			}
			$ul.append($li);
		});
	}

	function proceedDelete(skipRedirect) {
		if (!pendingAction) {
			closeModal();
			return;
		}
		var $status = $('#damavand-delete-status');
		$status.text('...');
		var type = $('input[name="damavand_delete_type"]:checked').val() || '301';
		$.post(damavandDeleteRedirect.ajaxUrl, {
			action: 'damavand_delete_redirect_apply',
			nonce: damavandDeleteRedirect.nonce,
			post_id: currentPostId,
			skip_redirect: skipRedirect ? 1 : 0,
			redirect_type: type,
			target_id: selectedTargetId,
			target_type: selectedTargetType,
			destination: $('#damavand-delete-dest').val() || ''
		}).done(function (res) {
			if (!skipRedirect && (!res || !res.success)) {
				$status.text((res && res.data && res.data.message) || damavandDeleteRedirect.i18n.error);
				return;
			}
			closeModal();
			if (pendingAction.tagName === 'A') {
				window.location.href = pendingAction.href;
			} else if (pendingAction.form) {
				pendingAction.form.submit();
			}
		}).fail(function () {
			$status.text(damavandDeleteRedirect.i18n.error);
		});
	}

	function extractPostIdFromAction(el) {
		var $el = $(el);
		var href = $el.attr('href') || '';
		var m = href.match(/[?&]post=(\d+)/);
		if (m) {
			return parseInt(m[1], 10);
		}
		if (damavandDeleteRedirect.postId) {
			return parseInt(damavandDeleteRedirect.postId, 10);
		}
		return 0;
	}

	$(document).on('click', '#delete-action .submitdelete, .row-actions .submitdelete, a.submitdelete[href*="action=trash"], a.submitdelete[href*="action=delete"]', function (e) {
		var postId = extractPostIdFromAction(this);
		if (postId < 1) {
			return;
		}
		e.preventDefault();
		openModal(postId, this);
	});

	$(document).on('click', '.damavand-delete-pick', function () {
		selectedTargetId = parseInt($(this).data('id'), 10) || 0;
		selectedTargetType = String($(this).data('type') || '');
		$('#damavand-delete-dest').val($(this).data('url') || '');
		$('input[name="damavand_delete_type"][value="301"]').prop('checked', true);
	});

	$(document).on('input', '#damavand-delete-search', function () {
		var q = String($(this).val() || '').trim();
		if (searchTimer) {
			clearTimeout(searchTimer);
		}
		if (q.length < 2) {
			$('#damavand-delete-search-results').empty();
			return;
		}
		searchTimer = setTimeout(function () {
			$.post(damavandDeleteRedirect.ajaxUrl, {
				action: 'damavand_delete_redirect_search',
				nonce: damavandDeleteRedirect.nonce,
				q: q,
				exclude_id: currentPostId,
				post_type: damavandDeleteRedirect.postType || 'product'
			}).done(function (res) {
				var $ul = $('#damavand-delete-search-results').empty();
				var rows = res && res.success && res.data && res.data.results ? res.data.results : [];
				rows.forEach(function (row) {
					var $li = $('<li/>');
					var $btn = $('<button type="button" class="button-link"/>')
						.text(row.title || '')
						.data('id', row.id)
						.data('url', row.url);
					$btn.on('click', function () {
						selectedTargetId = parseInt(row.id, 10) || 0;
						selectedTargetType = String(row.type || '');
						$('#damavand-delete-dest').val(row.url || '');
					});
					$li.append($btn);
					$ul.append($li);
				});
			});
		}, 350);
	});

	$(document).on('change', 'input[name="damavand_delete_type"]', function () {
		if ('410' === $(this).val()) {
			$('#damavand-delete-dest').prop('disabled', true);
			selectedTargetId = 0;
			selectedTargetType = '';
		} else {
			$('#damavand-delete-dest').prop('disabled', false);
		}
	});

	$('#damavand-delete-confirm').on('click', function () {
		proceedDelete(false);
	});
	$('#damavand-delete-skip').on('click', function () {
		proceedDelete(true);
	});
	$('#damavand-delete-cancel, .damavand-delete-modal__backdrop').on('click', function () {
		closeModal();
	});
})(jQuery);
