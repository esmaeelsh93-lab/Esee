(function ($) {
    'use strict';

    function shojaeiEscapeHtml(str) {
        return $('<div/>').text(str == null ? '' : String(str)).html();
    }

    function shojaeiSafeMessageHtml(msg, tone) {
        tone = tone || 'safe';
        return '<span class="shojaei-tone-' + shojaeiEscapeHtml(tone) + '">' + shojaeiEscapeHtml(msg) + '</span>';
    }

    function getSelectedProductIds() {
        var ids = [];
        $('.shojaei-product-check:checked').each(function () {
            ids.push($(this).val());
        });
        return ids;
    }

    function updateBulkCount() {
        var count = $('.shojaei-product-check:checked').length;
        $('.shojaei-bulk-count').text(count ? count + ' مورد انتخاب شده' : '');
    }

    /* Accordion — فقط داخل همان گروه */
    $(document).on('click', '.shojaei-accordion-header', function () {
        var $item = $(this).closest('.shojaei-accordion-item');
        var $root = $item.closest('.shojaei-accordion');
        var $body = $item.children('.shojaei-accordion-body');
        var wasOpen = $item.hasClass('is-open');

        $root.find('> .shojaei-accordion-item').removeClass('is-open');
        $root.find('> .shojaei-accordion-item > .shojaei-accordion-body').slideUp(180);
        $root.find('> .shojaei-accordion-item > .shojaei-accordion-header').attr('aria-expanded', 'false');

        if (!wasOpen) {
            $item.addClass('is-open');
            $body.slideDown(180);
            $(this).attr('aria-expanded', 'true');
        }
    });

    /**
     * Settings deep-links (#shojaei-performance, #shojaei-content-server, …):
     * open the matching <details> and scroll — do NOT leave content-server stealing focus.
     */
    function shojaeiOpenSettingsHash() {
        var hash = window.location.hash || '';
        if (!hash || hash.indexOf('#shojaei-') !== 0) {
            return false;
        }
        var $target = $(hash);
        if (!$target.length) {
            return false;
        }

        var $details = $target.is('details') ? $target : $target.find('> details.shojaei-details').first();
        if (!$details.length) {
            $details = $target.closest('details.shojaei-details');
        }

        // Close other settings panels so the right one is visible.
        $('.shojaei-settings-panel > details.shojaei-details').each(function () {
            if ($details.length && this === $details[0]) {
                return;
            }
            this.open = false;
        });

        if ($details.length) {
            $details.prop('open', true);
            if ($details[0]) {
                $details[0].open = true;
            }
        }

        // Accordion jump targets (e.g. #shojaei-acc-content-server).
        if ($target.hasClass('shojaei-accordion-item')) {
            var $root = $target.closest('.shojaei-accordion');
            $root.find('> .shojaei-accordion-item').removeClass('is-open');
            $root.find('> .shojaei-accordion-item > .shojaei-accordion-body').hide();
            $root.find('> .shojaei-accordion-item > .shojaei-accordion-header').attr('aria-expanded', 'false');
            $target.addClass('is-open');
            $target.children('.shojaei-accordion-body').show();
            $target.children('.shojaei-accordion-header').attr('aria-expanded', 'true');
        }

        window.setTimeout(function () {
            if ($target[0] && $target[0].scrollIntoView) {
                $target[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 50);
        return true;
    }

    $(function () {
        shojaeiOpenSettingsHash();
    });
    $(window).on('hashchange', function () {
        shojaeiOpenSettingsHash();
    });

    function shojaeiJobsQueueOp(op, extra) {
        var $st = $('#shojaei-jobs-actions-status');
        var payload = $.extend({
            action: 'shojaei_seo_jobs_queue',
            nonce: shojaeiSeoAdmin.nonce,
            jobs_op: op
        }, extra || {});
        $st.text('...');
        return $.post(shojaeiSeoAdmin.ajaxUrl, payload).done(function (res) {
            if (res && res.success) {
                $st.text((res.data && res.data.message) || (shojaeiSeoAdmin.i18n.success || 'OK'));
                if (op === 'ack_errors') {
                    $('#shojaei-jobs-failed-banner, #shojaei-jobs-failed-table').slideUp(150);
                    $('#shojaei-jobs-ack-errors').prop('disabled', true);
                }
            } else {
                $st.text((res && res.data && res.data.message) || (shojaeiSeoAdmin.i18n.error || 'Error'));
            }
        }).fail(function () {
            $st.text(shojaeiSeoAdmin.i18n.error || 'Error');
        });
    }

    $(document).on('click', '#shojaei-jobs-run-tick', function () {
        var $btn = $(this).prop('disabled', true);
        shojaeiJobsQueueOp('run_tick').always(function () {
            $btn.prop('disabled', false);
        });
    });
    $(document).on('click', '#shojaei-jobs-cancel-stale', function () {
        var $btn = $(this).prop('disabled', true);
        shojaeiJobsQueueOp('cancel_stale').always(function () {
            $btn.prop('disabled', false);
        });
    });
    $(document).on('click', '#shojaei-jobs-ack-errors', function () {
        if (!window.confirm('هشدار داشبورد برای جاب‌های ناموفق پاک شود؟ (صف فعال دست نمی‌خورد)')) {
            return;
        }
        var $btn = $(this).prop('disabled', true);
        shojaeiJobsQueueOp('ack_errors', { delete_failed: 0 }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    /* Select all */
    $('#shojaei-select-all').on('change', function () {
        $('.shojaei-product-check').prop('checked', $(this).is(':checked'));
        updateBulkCount();
    });

    $(document).on('change', '.shojaei-product-check', updateBulkCount);

    function shojaeiParseAdminJson(xhr) {
        var data = xhr && xhr.responseJSON;
        if (data && typeof data === 'object') {
            return data;
        }
        var text = (xhr && xhr.responseText) || '';
        if (!text) {
            return null;
        }
        try {
            var start = text.indexOf('{');
            var end = text.lastIndexOf('}');
            if (start >= 0 && end > start) {
                return JSON.parse(text.slice(start, end + 1));
            }
        } catch (e) {
            return null;
        }
        return null;
    }

    function postRedirectAction(payload, $btn, $row) {
        $btn.prop('disabled', true);
        $.ajax({
            url: shojaeiSeoAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: payload
        }).done(function (response) {
            if (response && response.success) {
                $row.fadeOut(400, function () { $(this).remove(); });
                return;
            }

            if (response && response.data && response.data.requires_manual) {
                if (confirm((response.data.message || shojaeiSeoAdmin.i18n.high_value_confirm))) {
                    payload.force_confirm = 1;
                    postRedirectAction(payload, $btn, $row);
                    return;
                }
            }

            alert((response && response.data && response.data.message) || shojaeiSeoAdmin.i18n.error);
            $btn.prop('disabled', false);
        }).fail(function (xhr) {
            var parsed = shojaeiParseAdminJson(xhr);
            if (parsed && parsed.success) {
                $row.fadeOut(400, function () { $(this).remove(); });
                return;
            }

            if (parsed && parsed.data && parsed.data.requires_manual) {
                if (confirm((parsed.data.message || shojaeiSeoAdmin.i18n.high_value_confirm))) {
                    payload.force_confirm = 1;
                    postRedirectAction(payload, $btn, $row);
                    return;
                }
            }

            alert((parsed && parsed.data && parsed.data.message) || shojaeiSeoAdmin.i18n.error);
            $btn.prop('disabled', false);
        });
    }

    /* Redirect Actions */
    $(document).on('click', '.shojaei-btn-redirect', function () {
        if (!confirm(shojaeiSeoAdmin.i18n.confirm_redirect)) {
            return;
        }

        var $btn = $(this);
        var $row = $btn.closest('tr');
        var productId = $btn.data('id');
        var action = $btn.data('action');
        var targetUrl = $row.find('.shojaei-target-url').first().val();

        if (!targetUrl) {
            alert('لطفاً آدرس مقصد را وارد کنید.');
            return;
        }

        postRedirectAction({
            action: 'shojaei_seo_redirect_action',
            nonce: shojaeiSeoAdmin.nonce,
            redirect_action: action,
            product_id: productId,
            target_url: targetUrl
        }, $btn, $row);
    });

    /* Keep Page */
    $(document).on('click', '.shojaei-btn-keep', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var productId = $btn.data('id');

        postRedirectAction({
            action: 'shojaei_seo_redirect_action',
            nonce: shojaeiSeoAdmin.nonce,
            redirect_action: 'keep',
            product_id: productId
        }, $btn, $row);
    });

    /* 410 Gone */
    $(document).on('click', '.shojaei-btn-410', function () {
        if (!confirm(shojaeiSeoAdmin.i18n.confirm_410)) {
            return;
        }

        var $btn = $(this);
        var $row = $btn.closest('tr');
        var productId = $btn.data('id');

        postRedirectAction({
            action: 'shojaei_seo_redirect_action',
            nonce: shojaeiSeoAdmin.nonce,
            redirect_action: 'redirect_410',
            product_id: productId
        }, $btn, $row);
    });

    $(document).on('click', '#shojaei-oos-days-scan', function () {
        if (!confirm(shojaeiSeoAdmin.i18n.oos_days_scan || shojaeiSeoAdmin.i18n.confirm_rescan)) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_oos_days_scan',
            nonce: shojaeiSeoAdmin.nonce
        }, function (res) {
            if (!res || !res.success) {
                alert((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
                $btn.prop('disabled', false);
                return;
            }
            if (!res.data.queued) {
                alert(res.data.message || shojaeiSeoAdmin.i18n.success);
                $btn.prop('disabled', false);
                return;
            }
            pollBatchJob(res.data.job_id, function () {
                location.reload();
            });
        }).fail(function () {
            alert(shojaeiSeoAdmin.i18n.error);
            $btn.prop('disabled', false);
        });
    });

    /* Bulk Actions */
    function pollBatchJob(jobId, onDone) {
        var $box = $('#shojaei-oos-days-progress');
        if (!$box.length) {
            $box = $('#shojaei-batch-progress');
        }
        if (!$box.length) {
            $box = $('<div id="shojaei-batch-progress" class="shojaei-test-result"></div>').prependTo('.shojaei-wrap');
        }
        $box.show().html('<p>' + (shojaeiSeoAdmin.i18n.batch_running || '...') + '</p>');

        var tries = 0;
        var finished = false;

        function renderJob(j) {
            if (!j) {
                return;
            }
            var pct = j.total ? Math.min(100, Math.round((j.processed / j.total) * 100)) : 0;
            var st = j.status || '';
            $box.html(
                '<p><strong>' + st + '</strong> — ' +
                (j.processed || 0) + ' / ' + (j.total || 0) +
                ' (' + pct + '%)' +
                (j.attempts != null ? ' — retry ' + j.attempts + '/' + (j.max_attempts || 3) : '') +
                '</p>' +
                (j.message ? '<p>' + shojaeiEscapeHtml(j.message) + '</p>' : '')
            );
            if (st === 'done' || st === 'failed' || st === 'cancelled') {
                finished = true;
                if (typeof onDone === 'function') {
                    onDone(j);
                }
            }
        }

        var timer = setInterval(function () {
            if (finished) {
                clearInterval(timer);
                return;
            }
            tries++;
            // Drain one chunk while admin is present (no full WP-Cron dependency).
            $.post(shojaeiSeoAdmin.ajaxUrl, {
                action: 'shojaei_seo_job_tick',
                nonce: shojaeiSeoAdmin.nonce,
                job_id: jobId
            }, function (tickRes) {
                if (tickRes && tickRes.success && tickRes.data && tickRes.data.id) {
                    renderJob(tickRes.data);
                    if (finished) {
                        clearInterval(timer);
                    }
                    return;
                }
                $.post(shojaeiSeoAdmin.ajaxUrl, {
                    action: 'shojaei_seo_batch_status',
                    nonce: shojaeiSeoAdmin.nonce,
                    job_id: jobId
                }, function (res) {
                    if (!res.success || !res.data) {
                        return;
                    }
                    renderJob(res.data);
                    if (finished) {
                        clearInterval(timer);
                    }
                });
            });
            if (tries > 180) {
                clearInterval(timer);
            }
        }, 1500);
    }

    $(document).on('click', '.shojaei-bulk-action', function () {
        var ids = getSelectedProductIds();
        if (!ids.length) {
            alert(shojaeiSeoAdmin.i18n.select_products);
            return;
        }

        var $btn = $(this);
        var action = $btn.data('action');

        if (action === 'redirect_410') {
            if (!confirm(shojaeiSeoAdmin.i18n.confirm_410)) {
                return;
            }
        } else if (!confirm(shojaeiSeoAdmin.i18n.confirm_bulk)) {
            return;
        }

        var targetUrl = $('#shojaei-bulk-target-url').val();
        var force = (action.indexOf('redirect') === 0 && confirm(shojaeiSeoAdmin.i18n.high_value_confirm)) ? 1 : 0;

        $btn.prop('disabled', true);

        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_bulk_redirect',
            nonce: shojaeiSeoAdmin.nonce,
            bulk_action: action,
            product_ids: ids,
            target_url: targetUrl,
            force_confirm: force
        }, function (response) {
            if (response.success && response.data && response.data.queued && response.data.job_id) {
                alert(response.data.message || shojaeiSeoAdmin.i18n.batch_queued);
                pollBatchJob(response.data.job_id, function () {
                    location.reload();
                });
                return;
            }
            if (response.success) {
                location.reload();
            } else {
                alert(response.data && response.data.message ? response.data.message : shojaeiSeoAdmin.i18n.error);
                $btn.prop('disabled', false);
            }
        });
    });

    /* Undo Redirect */
    $(document).on('click', '.shojaei-btn-undo', function () {
        if (!confirm(shojaeiSeoAdmin.i18n.confirm_undo)) {
            return;
        }

        var $btn = $(this);
        var $row = $btn.closest('tr');

        $btn.prop('disabled', true);

        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_undo_redirect',
            nonce: shojaeiSeoAdmin.nonce,
            product_id: $btn.data('id'),
            log_id: $btn.data('log-id') || 0
        }, function (response) {
            if (response.success) {
                $row.fadeOut(400, function () { $(this).remove(); });
            } else {
                alert(response.data && response.data.message ? response.data.message : shojaeiSeoAdmin.i18n.error);
                $btn.prop('disabled', false);
            }
        });
    });

    /* Link Preview */
    $('#shojaei-run-preview').on('click', function () {
        var $btn = $(this);
        var postId = $('#shojaei-preview-post').val();
        var content = $('#shojaei-preview-content').val();

        if (!postId && !content.trim()) {
            alert('یک نوشته/محصول انتخاب کنید یا متن وارد کنید.');
            return;
        }

        $btn.prop('disabled', true).text(shojaeiSeoAdmin.i18n.preview_loading);

        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_preview',
            nonce: shojaeiSeoAdmin.nonce,
            post_id: postId,
            content: content
        }, function (response) {
            $btn.prop('disabled', false).text('پیش‌نمایش');

            if (!response.success) {
                alert(response.data && response.data.message ? response.data.message : shojaeiSeoAdmin.i18n.error);
                return;
            }

            var data = response.data;
            var added = parseInt(data.links_added || 0, 10);
            var existing = parseInt(data.existing_links || 0, 10);

            $('#shojaei-preview-count').text(added);
            $('#shojaei-preview-existing').text(existing);
            if (typeof data.max_allowed !== 'undefined') {
                $('#shojaei-preview-cap').text(
                    '(سقف مجاز موتور: ' + data.max_allowed +
                    (data.word_count ? ' — کلمات: ' + data.word_count : '') + ')'
                );
            } else {
                $('#shojaei-preview-cap').text('');
            }

            var $explain = $('#shojaei-preview-explain');
            if (added === 0 && existing > 0) {
                $explain.text('موتور لینک جدیدی نساخت. لینک‌هایی که در پیش‌نمایش می‌بینید از قبل داخل محتوای محصول/نوشته بوده‌اند — نه خروجی موتور لینک‌ساز.').show();
            } else if (added === 0 && existing === 0) {
                $explain.text('نه لینک قبلی در محتوا بود و نه موتور لینک جدیدی درج کرد (سقف، قوانین، یا نبود کلمه کلیدی).').show();
            } else if (added > 0) {
                $explain.text('لینک‌های با پس‌زمینه سبز را موتور همین الان اضافه کرده است. بقیه لینک‌ها از قبل در محتوا بوده‌اند.').show();
            } else {
                $explain.hide().text('');
            }

            $('#shojaei-preview-output').html(data.content);

            var $details = $('#shojaei-preview-details').empty();
            if (data.details && data.details.length) {
                $details.append('<li><strong>درج‌شده توسط موتور:</strong></li>');
                data.details.forEach(function (item) {
                    $details.append(
                        $('<li></li>').html(
                            '<strong>' + shojaeiEscapeHtml(item.keyword) + '</strong>' +
                            (item.matched && item.matched !== item.keyword
                                ? ' <em>(' + shojaeiEscapeHtml(item.matched) + ')</em>'
                                : '') +
                            ' → <a href="' + shojaeiEscapeHtml(item.target_url || '') + '" target="_blank" rel="noopener noreferrer">' + shojaeiEscapeHtml(item.target_url || '') + '</a>' +
                            (item.priority ? ' <small>[p:' + item.priority + ']</small>' : '')
                        )
                    );
                });
            } else {
                $details.append('<li>موتور لینک جدیدی اضافه نکرد.</li>');
            }

            var $existingList = $('#shojaei-preview-existing-list').empty();
            if (data.existing_list && data.existing_list.length) {
                $existingList.append('<li><strong>لینک‌های از قبل در محتوا:</strong></li>');
                data.existing_list.forEach(function (item) {
                    var label = item.anchor || '(بدون متن)';
                    var url = item.url || '';
                    $existingList.append(
                        $('<li></li>').html(
                            shojaeiEscapeHtml(label) +
                            (url ? ' → <a href="' + shojaeiEscapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + shojaeiEscapeHtml(url) + '</a>' : '')
                        )
                    );
                });
            }

            var $skipped = $('#shojaei-preview-skipped').empty();
            if (data.skipped && data.skipped.length) {
                $skipped.append('<li><strong>رد شده (نمونه):</strong></li>');
                data.skipped.forEach(function (item) {
                    $skipped.append(
                        $('<li></li>').text(
                            (item.keyword || '') + ' — ' + (item.reason || '')
                        )
                    );
                });
            }

            $('#shojaei-preview-result').slideDown(200);
        });
    });

    /* Add Link */
    $('#shojaei-add-link-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var keyword = $form.find('[name="keyword"]').val();
        var targetUrl = $form.find('[name="target_url"]').val();

        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_action',
            nonce: shojaeiSeoAdmin.nonce,
            link_action: 'add',
            keyword: keyword,
            target_url: targetUrl
        }, function (response) {
            if (response.success) {
                location.reload();
            } else {
                alert(shojaeiSeoAdmin.i18n.error);
            }
        });
    });

    /* Delete Link */
    $(document).on('click', '.shojaei-link-delete', function () {
        if (!confirm('آیا از حذف این کلمه کلیدی اطمینان دارید؟')) {
            return;
        }

        var linkId = $(this).data('id');
        var $row = $(this).closest('tr');

        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_action',
            nonce: shojaeiSeoAdmin.nonce,
            link_action: 'delete',
            link_id: linkId
        }, function (response) {
            if (response.success) {
                $row.fadeOut(400, function () { $(this).remove(); });
            }
        });
    });

    /* Toggle Link */
    $(document).on('change', '.shojaei-link-toggle', function () {
        var linkId = $(this).data('id');
        var isActive = $(this).is(':checked') ? 1 : 0;

        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_action',
            nonce: shojaeiSeoAdmin.nonce,
            link_action: 'toggle',
            link_id: linkId,
            is_active: isActive
        });
    });

    /* Notifications */
    function notificationRequest(action, id, $el) {
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_notification_action',
            nonce: shojaeiSeoAdmin.nonce,
            notification_action: action,
            notification_id: id || ''
        }, function (response) {
            if (!response.success) {
                return;
            }
            if ($el) {
                if (action === 'dismiss') {
                    $el.fadeOut(300, function () { $(this).remove(); });
                } else {
                    $el.removeClass('is-unread').addClass('is-read');
                    $el.find('.shojaei-notif-read').remove();
                }
            }
            if (response.data && response.data.unread === 0) {
                $('.shojaei-notif-badge').remove();
                $('#shojaei-mark-all-read').remove();
            }
        });
    }

    $(document).on('click', '.shojaei-notif-read', function () {
        var $item = $(this).closest('.shojaei-notification-item');
        notificationRequest('read', $item.data('id'), $item);
    });

    $(document).on('click', '.shojaei-notif-dismiss', function () {
        var $item = $(this).closest('.shojaei-notification-item');
        notificationRequest('dismiss', $item.data('id'), $item);
    });

    $('#shojaei-mark-all-read').on('click', function () {
        notificationRequest('read_all', '', null);
        $('.shojaei-notification-item').removeClass('is-unread').addClass('is-read');
        $('.shojaei-notif-read').remove();
        $('.shojaei-notif-badge').remove();
        $(this).remove();
    });

    /* Force inventory rescan + progress polling */
    function updateScanProgressUI(p) {
        if (!p) return;
        $('#shojaei-scan-progress-label').text(p.label || '');
        $('#shojaei-scan-progress-pct').text((p.percent || 0) + '%');
        $('#shojaei-scan-progress-bar').css('width', (p.percent || 0) + '%');
        $('.shojaei-scan-progress-track').attr('aria-valuenow', p.percent || 0);
        $('#shojaei-scan-progress-detail').text(
            'پردازش‌شده: ' + (p.processed || 0) + ' · کل: ' + (p.total || 0) + ' · باقی‌مانده: ' + (p.pending || 0)
        );
        $('#shojaei-scan-progress').attr('data-running', p.running ? '1' : '0');
        if (p.done) {
            $('#shojaei-force-rescan').prop('disabled', false);
        }
    }

    var scanPollTimer = null;
    function pollScanProgress() {
        if (scanPollTimer) return;
        scanPollTimer = setInterval(function () {
            $.post(shojaeiSeoAdmin.ajaxUrl, {
                action: 'shojaei_seo_scan_progress',
                nonce: shojaeiSeoAdmin.nonce
            }, function (response) {
                if (!response.success || !response.data) return;
                updateScanProgressUI(response.data);
                if (response.data.done || (!response.data.running && (response.data.pending || 0) === 0 && (response.data.percent || 0) >= 100)) {
                    clearInterval(scanPollTimer);
                    scanPollTimer = null;
                }
            });
        }, 2000);
    }

    if ($('#shojaei-scan-progress').length && $('#shojaei-scan-progress').attr('data-running') === '1') {
        pollScanProgress();
    }

    $('#shojaei-force-rescan').on('click', function () {
        if (!confirm(shojaeiSeoAdmin.i18n.confirm_rescan)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).addClass('is-busy');
        updateScanProgressUI({ label: 'در حال صف‌بندی اسکن…', percent: 1, running: true, processed: 0, total: 0, pending: 0 });

        $.ajax({
            url: shojaeiSeoAdmin.ajaxUrl,
            method: 'POST',
            timeout: 20000,
            data: {
                action: 'shojaei_seo_force_rescan',
                nonce: shojaeiSeoAdmin.nonce
            }
        }).done(function (response) {
            if (response && response.success) {
                if (response.data && response.data.progress) {
                    updateScanProgressUI(response.data.progress);
                } else {
                    updateScanProgressUI({ label: 'اسکن در صف است…', percent: 1, running: true, processed: 0, total: 0, pending: 0 });
                }
                pollScanProgress();
            } else {
                alert((response && response.data && response.data.message) ? response.data.message : shojaeiSeoAdmin.i18n.rescan_busy);
            }
        }).fail(function (xhr, status) {
            if (status === 'timeout') {
                // Request may still have queued the job — keep polling instead of 🚫 forever.
                updateScanProgressUI({ label: 'اسکن در صف است…', percent: 1, running: true, processed: 0, total: 0, pending: 0 });
                pollScanProgress();
            } else {
                alert(shojaeiSeoAdmin.i18n.error);
            }
        }).always(function () {
            $btn.prop('disabled', false).removeClass('is-busy');
        });
    });

    /* Auto-run product test when ?product_id= is present */
    (function () {
        var params = new URLSearchParams(window.location.search);
        var pid = parseInt(params.get('product_id') || '0', 10);
        if (!pid || !$('#shojaei-run-product-test').length) return;
        $('#shojaei-test-product-id').val(pid);
        if ($('#shojaei-test-product option[value="' + pid + '"]').length) {
            $('#shojaei-test-product').val(String(pid));
        } else {
            $('#shojaei-test-product').append($('<option></option>').val(String(pid)).text('#' + pid).prop('selected', true));
        }
        setTimeout(function () { $('#shojaei-run-product-test').trigger('click'); }, 300);
    })();

    /* Product diagnose / test */
    function getTestProductId() {
        var fromSelect = $('#shojaei-test-product').val();
        var fromInput = $('#shojaei-test-product-id').val();
        return parseInt(fromInput || fromSelect || 0, 10) || 0;
    }

    function renderProductTest(data) {
        var plan = data.plan || null;
        var rows = [
            ['عنوان', data.title || '—'],
            ['نوع', data.type || '—'],
            ['موجودی', data.in_stock ? 'موجود' : 'ناموجود'],
            ['جزئیات موجودی', data.stock_detail || '—'],
            ['ردیابی شده', data.tracked ? 'بله' : 'خیر'],
            ['وضعیت OOS', data.oos_status || '—'],
            ['چرخه', (data.lifecycle_label || data.lifecycle || '—') + (data.oos_date ? ' | از ' + data.oos_date : '')],
            ['روز ناموجود', String(data.days_oos != null ? data.days_oos : '—')],
            ['فاز', String(data.phase != null ? data.phase : '—')],
            ['ریدایرکت فعلی', (data.redirect_type || 'none') + (data.target_url ? ' → ' + data.target_url : '')],
            ['Robots', data.robots || '—'],
            ['اسکیما', data.schema_mode || '—'],
            ['Dry-Run', data.dry_run ? 'فعال (اعمال نمی‌شود)' : 'خاموش (اعمال واقعی)']
        ];

        if (data.page_value) {
            rows.push(['Page Value', String(data.page_value.score) + (data.page_value.requires_manual ? ' — نیاز به تایید دستی' : '')]);
        }

        if (data.rule_engine) {
            rows.push(['تصمیم Rule Engine', data.rule_engine.primary_label || data.rule_engine.primary_action || '—']);
            rows.push(['حالت اعمال', data.rule_engine.apply_mode || '—']);
        }

        var html = '<div class="shojaei-test-grid">';
        rows.forEach(function (row) {
            html += '<div class="shojaei-test-row"><span class="shojaei-test-label">' + row[0] + '</span><span class="shojaei-test-value">' + shojaeiEscapeHtml(row[1]) + '</span></div>';
        });
        html += '</div>';

        if (data.rule_engine && data.rule_engine.traces && data.rule_engine.traces.length) {
            html += '<div class="shojaei-test-plan"><h4>ردپای قوانین</h4><ul>';
            data.rule_engine.traces.forEach(function (t) {
                html += '<li>' + shojaeiEscapeHtml(t) + '</li>';
            });
            html += '</ul></div>';
        }

        if (data.permalink) {
            html += '<p class="shojaei-preview-meta"><a href="' + data.permalink + '" target="_blank" rel="noopener">' + shojaeiSeoAdmin.i18n.view_product + '</a></p>';
        }

        if (plan) {
            html += '<div class="shojaei-test-plan">';
            html += '<h4>' + shojaeiSeoAdmin.i18n.suggested_plan + '</h4>';
            html += '<ul>';
            html += '<li><strong>' + shojaeiSeoAdmin.i18n.redirect_type + ':</strong> ' + (plan.redirect_type || '—') + '</li>';
            html += '<li><strong>' + shojaeiSeoAdmin.i18n.target_url + ':</strong> ' + shojaeiEscapeHtml(plan.target_url || '—') + '</li>';
            html += '<li><strong>' + shojaeiSeoAdmin.i18n.reason + ':</strong> ' + shojaeiEscapeHtml(plan.reason || '—') + '</li>';
            if (plan.match_id) {
                html += '<li><strong>' + shojaeiSeoAdmin.i18n.match_score + ':</strong> #' + plan.match_id + ' (' + (plan.match_score || 0) + '%)</li>';
            }
            if (plan.score_parts) {
                var p = plan.score_parts;
                html += '<li><strong>جزئیات شباهت:</strong> عنوان ' + (p.title || 0) + '%، برچسب ' + (p.tags || 0) + '%، ویژگی ' + (p.attributes || 0) + '%، قیمت ' + (p.price || 0) + '%</li>';
            }
            if (plan.reason) {
                html += '<li><strong>دلیل انتخاب:</strong> ' + shojaeiEscapeHtml(plan.reason) + '</li>';
            }
            html += '</ul></div>';
        } else if (!data.in_stock) {
            html += '<p class="shojaei-desc">' + shojaeiSeoAdmin.i18n.no_plan + '</p>';
        }

        $('#shojaei-test-result').html(html).show();
    }

    $('#shojaei-run-product-test').on('click', function () {
        var productId = getTestProductId();
        if (!productId) {
            alert(shojaeiSeoAdmin.i18n.select_test_product);
            return;
        }

        var $btn = $(this);
        var $box = $('#shojaei-test-result');
        $btn.prop('disabled', true);
        $box.html('<p class="shojaei-test-loading">' + shojaeiSeoAdmin.i18n.test_loading + '</p>').show();

        $.ajax({
            url: shojaeiSeoAdmin.ajaxUrl,
            method: 'POST',
            timeout: 25000,
            data: {
                action: 'shojaei_seo_product_test',
                nonce: shojaeiSeoAdmin.nonce,
                product_id: productId
            }
        }).done(function (response) {
            if (response && response.success) {
                renderProductTest(response.data || {});
            } else {
                $box.html('<p class="shojaei-test-error">' + shojaeiEscapeHtml((response && response.data && response.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
            }
        }).fail(function (xhr, status) {
            var msg = status === 'timeout'
                ? (shojaeiSeoAdmin.i18n.test_timeout || 'زمان پاسخ تمام شد. دوباره تلاش کنید.')
                : shojaeiSeoAdmin.i18n.error;
            $box.html('<p class="shojaei-test-error">' + shojaeiEscapeHtml(msg) + '</p>');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#shojaei-test-product').on('change', function () {
        if ($(this).val()) {
            $('#shojaei-test-product-id').val($(this).val());
        }
    });

    var lastDryRunBatch = '';
    var lastDryRunData = null;

    function dryRunExportUrl(batchId) {
        var base = shojaeiSeoAdmin.exportBase || '';
        var join = base.indexOf('?') >= 0 ? '&' : '?';
        return base + join + 'shojaei_export=dry_run&batch_id=' + encodeURIComponent(batchId || '');
    }

    function riskBadge(level, label) {
        var cls = 'shojaei-risk-low';
        if (level === 'high') {
            cls = 'shojaei-risk-high';
        } else if (level === 'medium') {
            cls = 'shojaei-risk-medium';
        }
        return '<span class="shojaei-risk-badge ' + cls + '">' + shojaeiEscapeHtml(label || level || '') + '</span>';
    }

    function renderDryRunResult(data) {
        var $box = $('#shojaei-dryrun-result');
        if (!$box.length) {
            return;
        }
        data = data || {};
        lastDryRunData = data;
        lastDryRunBatch = data.batch_id || '';
        $box.attr('data-batch', lastDryRunBatch);

        var counts = data.counts || {};
        var affected = counts.affected != null ? counts.affected : ((data.changes && data.changes.length) || 0);
        var blocked = counts.blocked != null ? counts.blocked : ((data.blocked && data.blocked.length) || 0);
        var byRisk = counts.by_risk || {};
        var byType = counts.by_type || [];

        var html = '';
        html += '<div class="shojaei-dryrun-trust-note">' +
            shojaeiEscapeHtml(data.trust_note || data.message || '') +
            '</div>';

        html += '<div class="shojaei-dryrun-stats">';
        html += '<div class="shojaei-dryrun-stat"><strong>' + affected + '</strong><span>آیتم متاثر</span></div>';
        html += '<div class="shojaei-dryrun-stat"><strong>' + blocked + '</strong><span>مسدود / هشدار</span></div>';
        html += '<div class="shojaei-dryrun-stat"><strong>' + (byRisk.high || 0) + '</strong><span>ریسک بالا</span></div>';
        html += '<div class="shojaei-dryrun-stat"><strong>' + (byRisk.medium || 0) + '</strong><span>ریسک متوسط</span></div>';
        html += '</div>';

        if (data.batch_id) {
            html += '<p class="shojaei-dryrun-batch"><code dir="ltr">batch: ' + shojaeiEscapeHtml(data.batch_id) + '</code></p>';
        }

        if (data.warnings && data.warnings.length) {
            html += '<div class="shojaei-dryrun-warnings"><h4>هشدارها</h4><ul>';
            data.warnings.forEach(function (w) {
                html += '<li>' + shojaeiEscapeHtml(w) + '</li>';
            });
            html += '</ul></div>';
        }

        if (byType.length) {
            html += '<p class="shojaei-dryrun-types">';
            byType.forEach(function (t) {
                html += '<span class="shojaei-badge shojaei-badge-type">' +
                    shojaeiEscapeHtml((t.label || t.action) + ': ' + t.count) +
                    '</span> ';
            });
            html += '</p>';
        }

        html += '<div class="shojaei-dryrun-actions">';
        if (data.can_export !== false && (affected + blocked) > 0) {
            html += '<a class="button" id="shojaei-dryrun-export" href="' + dryRunExportUrl(lastDryRunBatch) + '">' +
                (shojaeiSeoAdmin.i18n.dryrun_export || 'CSV') + '</a> ';
        }
        if (data.can_apply && affected > 0) {
            html += '<button type="button" class="button button-primary" id="shojaei-dryrun-apply">' +
                (shojaeiSeoAdmin.i18n.dryrun_apply || 'Apply') + '</button>';
        }
        html += '</div>';

        if (data.changes && data.changes.length) {
            html += '<table class="shojaei-table shojaei-dryrun-table"><thead><tr>' +
                '<th>محصول</th><th>نوع تغییر</th><th>عملیات</th><th>ریسک</th><th>قبل → بعد</th><th>هشدار</th>' +
                '</tr></thead><tbody>';
            data.changes.forEach(function (c) {
                var beforeStatus = (c.before && c.before.status) ? c.before.status : (c.before && c.before.has_cache ? 'cache' : '—');
                var afterStatus = (c.after && c.after.status)
                    ? c.after.status
                    : (c.after && c.after.links_added != null ? ('links:' + c.after.links_added) : '—');
                if (c.after && c.after.redirect_type && c.after.redirect_type !== 'none') {
                    afterStatus = c.after.redirect_type + (c.after.target_url ? (' → ' + c.after.target_url) : '');
                }
                var warns = (c.warnings && c.warnings.length) ? c.warnings.join(' · ') : '—';
                html += '<tr>' +
                    '<td>' + shojaeiEscapeHtml(c.title || ('#' + (c.product_id || ''))) + '</td>' +
                    '<td>' + shojaeiEscapeHtml(c.change_type || '') + '</td>' +
                    '<td>' + shojaeiEscapeHtml(c.action_label || c.action || '') + '</td>' +
                    '<td>' + riskBadge(c.risk, c.risk_label) + '</td>' +
                    '<td><code>' + shojaeiEscapeHtml(beforeStatus) + '</code> → <code>' +
                    shojaeiEscapeHtml(afterStatus) + '</code></td>' +
                    '<td class="shojaei-dryrun-warn-cell">' + shojaeiEscapeHtml(warns) + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';
        }

        if (data.blocked && data.blocked.length) {
            html += '<h4>مسدود شده</h4><table class="shojaei-table"><thead><tr><th>محصول</th><th>دلیل</th><th>ریسک</th></tr></thead><tbody>';
            data.blocked.forEach(function (b) {
                html += '<tr><td>' + shojaeiEscapeHtml(b.title || '') +
                    '</td><td>' + shojaeiEscapeHtml(b.reason || '') +
                    '</td><td>' + riskBadge(b.risk || 'high', b.risk_label || 'مسدود') + '</td></tr>';
            });
            html += '</tbody></table>';
        }

        $box.html(html).show();
    }

    $(document).on('click', '#shojaei-dryrun-apply', function () {
        var batch = lastDryRunBatch || $('#shojaei-dryrun-result').data('batch') || '';
        if (!batch) {
            alert(shojaeiSeoAdmin.i18n.dryrun_no_batch);
            return;
        }
        if (!confirm(shojaeiSeoAdmin.i18n.dryrun_apply_confirm)) {
            return;
        }
        var force = 0;
        var highRisk = lastDryRunData && lastDryRunData.counts && lastDryRunData.counts.by_risk
            ? (lastDryRunData.counts.by_risk.high || 0)
            : 0;
        if (highRisk > 0) {
            force = confirm(shojaeiSeoAdmin.i18n.high_value_confirm) ? 1 : 0;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_dry_run_apply',
            nonce: shojaeiSeoAdmin.nonce,
            batch_id: batch,
            force_confirm: force
        }, function (response) {
            if (response.success) {
                alert((response.data && response.data.message) || shojaeiSeoAdmin.i18n.success);
                location.reload();
            } else {
                alert((response.data && response.data.message) || shojaeiSeoAdmin.i18n.error);
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            alert(shojaeiSeoAdmin.i18n.error);
            $btn.prop('disabled', false);
        });
    });

    if (shojaeiSeoAdmin.lastDryRun) {
        $(function () {
            renderDryRunResult(shojaeiSeoAdmin.lastDryRun);
        });
    }

    $('#shojaei-dryrun-redirect').on('click', function () {
        var ids = $('#shojaei-dryrun-products').val() || [];
        if (!ids.length) {
            alert(shojaeiSeoAdmin.i18n.select_products);
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_dry_run',
            nonce: shojaeiSeoAdmin.nonce,
            dry_run_type: 'redirect',
            bulk_action: $('#shojaei-dryrun-action').val(),
            product_ids: ids,
            target_url: $('#shojaei-dryrun-target').val()
        }, function (response) {
            if (response.success && response.data && response.data.queued && response.data.job_id) {
                alert(response.data.message || shojaeiSeoAdmin.i18n.batch_queued);
                pollBatchJob(response.data.job_id, function () {
                    location.reload();
                });
                return;
            }
            if (response.success) {
                renderDryRunResult(response.data || {});
            } else {
                alert((response.data && response.data.message) || shojaeiSeoAdmin.i18n.error);
            }
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#shojaei-dryrun-links').on('click', function () {
        var postId = $('#shojaei-dryrun-post').val();
        if (!postId) {
            alert(shojaeiSeoAdmin.i18n.select_test_product);
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_dry_run',
            nonce: shojaeiSeoAdmin.nonce,
            dry_run_type: 'links',
            post_id: postId
        }, function (response) {
            if (response.success) {
                renderDryRunResult(response.data || {});
            } else {
                alert((response.data && response.data.message) || shojaeiSeoAdmin.i18n.error);
            }
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    var pendingUndo = null;

    function renderUndoPreview(data) {
        var $box = $('#shojaei-undo-preview');
        var $body = $('#shojaei-undo-preview-body');
        if (!$box.length) {
            return;
        }

        var html = '<p><strong>' + shojaeiEscapeHtml(data.message || shojaeiSeoAdmin.i18n.undo_preview_title) + '</strong></p>';
        var items = data.items ? data.items : (data.effects ? [data] : []);

        if (!items.length) {
            html += '<p>' + shojaeiEscapeHtml(shojaeiSeoAdmin.i18n.error) + '</p>';
        } else {
            html += '<table class="shojaei-table"><thead><tr><th>عملیات</th><th>موجودیت</th><th>قبل ← بعد از Undo</th></tr></thead><tbody>';
            items.forEach(function (item) {
                var effects = item.effects || [];
                var lines = effects.map(function (e) {
                    return (e.field || '') + ': ' + (e.from || '—') + ' → ' + (e.to || '—');
                }).join('<br>');
                html += '<tr><td>' + shojaeiEscapeHtml(item.action_label || item.action || '') +
                    '</td><td>' + shojaeiEscapeHtml(item.title || ('#' + (item.entity_id || ''))) +
                    '</td><td>' + lines + '</td></tr>';
            });
            html += '</tbody></table>';
            if (data.batch_id) {
                html += '<p><code dir="ltr">batch: ' + shojaeiEscapeHtml(data.batch_id) + '</code></p>';
            }
        }

        $body.html(html);
        $box.show();
        $('html, body').animate({ scrollTop: $box.offset().top - 80 }, 200);
    }

    $(document).on('click', '.shojaei-btn-undo-preview', function () {
        var $btn = $(this);
        var scope = $btn.data('scope') || 'one';
        var payload = {
            action: 'shojaei_seo_undo_preview',
            nonce: shojaeiSeoAdmin.nonce,
            scope: scope
        };
        if (scope === 'batch') {
            payload.batch_id = $btn.data('batch');
            pendingUndo = { scope: 'batch', batch_id: $btn.data('batch') };
        } else {
            payload.log_id = $btn.data('id');
            pendingUndo = { scope: 'one', log_id: $btn.data('id') };
        }

        $btn.prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, payload, function (response) {
            if (response.success) {
                renderUndoPreview(response.data || {});
            } else {
                alert((response.data && response.data.message) || shojaeiSeoAdmin.i18n.error);
                pendingUndo = null;
            }
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#shojaei-undo-cancel').on('click', function () {
        pendingUndo = null;
        $('#shojaei-undo-preview').hide();
    });

    $('#shojaei-undo-confirm').on('click', function () {
        if (!pendingUndo) {
            return;
        }
        var confirmMsg = pendingUndo.scope === 'batch'
            ? shojaeiSeoAdmin.i18n.confirm_rollback_batch
            : shojaeiSeoAdmin.i18n.confirm_rollback;
        if (!confirm(confirmMsg)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);
        var postData = {
            action: 'shojaei_seo_rollback',
            nonce: shojaeiSeoAdmin.nonce,
            scope: pendingUndo.scope
        };
        if (pendingUndo.scope === 'batch') {
            postData.batch_id = pendingUndo.batch_id;
        } else {
            postData.log_id = pendingUndo.log_id;
        }

        $.post(shojaeiSeoAdmin.ajaxUrl, postData, function (response) {
            if (response.success) {
                location.reload();
            } else {
                alert((response.data && response.data.message) || shojaeiSeoAdmin.i18n.error);
                $btn.prop('disabled', false);
            }
        });
    });


    $('#shojaei-schema-scan-btn').on('click', function () {
        var $btn = $(this);
        var $box = $('#shojaei-schema-scan-result');
        var url = $('#shojaei-schema-scan-url').val();
        $btn.prop('disabled', true);
        $box.html('<p>در حال اسکن...</p>').show();

        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_schema_scan',
            nonce: shojaeiSeoAdmin.nonce,
            url: url
        }, function (response) {
            if (!response.success) {
                $box.html('<p class="shojaei-test-error">' + shojaeiEscapeHtml((response.data && response.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
                return;
            }
            var d = response.data || {};
            var html = '<p><strong>' + (d.block_count || 0) + ' بلوک JSON-LD</strong></p>';
            if (d.types) {
                html += '<p>انواع: ';
                Object.keys(d.types).forEach(function (t) {
                    html += '<span class="shojaei-badge shojaei-badge-type">' + t + ' ×' + d.types[t] + '</span> ';
                });
                html += '</p>';
            }
            if (d.has_conflict && d.conflicts) {
                html += '<ul>';
                d.conflicts.forEach(function (c) {
                    html += '<li class="shojaei-test-error">' + shojaeiEscapeHtml(c.message || '') + '</li>';
                });
                html += '</ul>';
            } else {
                html += '<p>تداخل موازی جدی یافت نشد.</p>';
            }
            if (d.suggestions && d.suggestions.length) {
                html += '<h4>پیشنهاد</h4><ul>';
                d.suggestions.forEach(function (s) {
                    html += '<li>' + shojaeiEscapeHtml(s) + '</li>';
                });
                html += '</ul>';
            }
            $box.html(html);
        }).fail(function () {
            $box.html('<p class="shojaei-test-error">' + shojaeiSeoAdmin.i18n.error + '</p>');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    function applyGscStatus(status) {
        var $box = $('#shojaei-gsc-status-box');
        if (!$box.length || !status) {
            return;
        }
        var ok = !!status.connected;
        $box.toggleClass('is-connected', ok).toggleClass('is-disconnected', !ok);
        $('#shojaei-gsc-status-label').text(ok ? 'اتصال قابل استفاده' : 'اتصال ناقص / قطع');
        $('#shojaei-gsc-status-msg').text(status.message || '');

        var layers = status.layers || {};
        var order = ['json_key', 'auth', 'property', 'sites_list', 'indexing'];
        var $list = $('#shojaei-gsc-layers');
        if ($list.length) {
            $list.empty();
            order.forEach(function (key) {
                var layer = layers[key];
                if (!layer) {
                    return;
                }
                var state = layer.state || '';
                if (!state) {
                    if (layer.ok === true) {
                        state = 'success';
                    } else if (layer.ok === false) {
                        state = 'fail';
                    } else {
                        state = 'pending';
                    }
                }
                var mark = '○';
                var cls = 'is-pending';
                if (state === 'success') {
                    mark = '✓';
                    cls = 'is-ok';
                } else if (state === 'warning') {
                    mark = '!';
                    cls = 'is-warn';
                } else if (state === 'fail') {
                    mark = '✗';
                    cls = 'is-fail';
                }
                var $li = $('<li/>').addClass(cls);
                $li.append($('<strong/>').text(mark + ' ' + (layer.label || key)));
                $li.append($('<span/>').text(layer.detail || ''));
                $list.append($li);
            });
        }
        if (status.site_url) {
            $('#shojaei-gsc-site-url').val(status.site_url);
        }
    }

    $('#shojaei-gsc-upload-btn').on('click', function () {
        var fileInput = document.getElementById('shojaei-gsc-key-file');
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            alert('لطفاً فایل JSON کلید را انتخاب کنید.');
            return;
        }
        var siteUrl = ($('#shojaei-gsc-site-url').val() || '').trim();
        var $btn = $(this);
        var $box = $('#shojaei-gsc-result');
        var fd = new FormData();
        fd.append('action', 'shojaei_seo_gsc_upload');
        fd.append('nonce', shojaeiSeoAdmin.nonce);
        fd.append('gsc_key', fileInput.files[0]);
        fd.append('site_url', siteUrl);
        fd.append('shojaei_seo_gsc_property_prefer', $('#shojaei-gsc-property-prefer').val() || 'domain');

        $btn.prop('disabled', true);
        $box.html('<p>در حال آپلود و تشخیص لایه‌ای...</p>').show();

        $.ajax({
            url: shojaeiSeoAdmin.ajaxUrl,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function (response) {
            if (!response.success) {
                $box.empty().append($('<p class="shojaei-test-error"/>').text((response.data && response.data.message) || shojaeiSeoAdmin.i18n.error));
                return;
            }
            applyGscStatus(response.data && response.data.status);
            $box.empty().append($('<p/>').text((response.data && response.data.message) || shojaeiSeoAdmin.i18n.success));
            if (response.data && response.data.status && response.data.status.message) {
                $box.append($('<p/>').text(response.data.status.message));
            }
        }).fail(function () {
            $box.empty().append($('<p class="shojaei-test-error"/>').text(shojaeiSeoAdmin.i18n.error));
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#shojaei-gsc-verify-btn').on('click', function () {
        var $btn = $(this);
        var $box = $('#shojaei-gsc-result');
        var siteUrl = ($('#shojaei-gsc-site-url').val() || '').trim();
        $btn.prop('disabled', true);
        $box.html('<p>در حال بررسی لایه‌های JSON / Auth / Property...</p>').show();
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_gsc_verify',
            nonce: shojaeiSeoAdmin.nonce,
            site_url: siteUrl,
            shojaei_seo_gsc_property_prefer: $('#shojaei-gsc-property-prefer').val() || 'domain',
            probe_indexing: 0
        }, function (response) {
            if (!response.success) {
                $box.empty().append($('<p class="shojaei-test-error"/>').text((response.data && response.data.message) || shojaeiSeoAdmin.i18n.error));
                return;
            }
            applyGscStatus(response.data && response.data.status);
            $box.empty().append($('<p/>').text((response.data.status && response.data.status.message) || ''));
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#shojaei-gsc-test-btn').on('click', function () {
        var $btn = $(this);
        var $box = $('#shojaei-gsc-result');
        var siteUrl = ($('#shojaei-gsc-site-url').val() || '').trim();
        var layerFa = {
            payload: 'بررسی آدرس',
            auth: 'ورود (توکن)',
            property: 'خاصیت سایت'
        };
        $btn.prop('disabled', true);
        $box.attr('dir', 'rtl').html('<p>در حال تست ایندکس گوگل…</p>').show();
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_gsc_test_url',
            nonce: shojaeiSeoAdmin.nonce,
            url: '',
            site_url: siteUrl,
            shojaei_seo_gsc_property_prefer: $('#shojaei-gsc-property-prefer').val() || 'domain',
            probe_indexing: 1
        }, function (response) {
            var payload = response.data || {};
            var ok = !!response.success;
            var msg = payload.message || (ok ? shojaeiSeoAdmin.i18n.success : shojaeiSeoAdmin.i18n.error);

            $box.empty().append($('<p/>').toggleClass('shojaei-test-error', !ok).text(msg));

            if (payload.preflight && payload.preflight.layers) {
                var $ul = $('<ul class="shojaei-gsc-test-layers" dir="rtl"/>');
                Object.keys(payload.preflight.layers).forEach(function (key) {
                    var row = payload.preflight.layers[key] || {};
                    var mark = row.ok ? '✓ ' : '✗ ';
                    var title = layerFa[key] || key;
                    $ul.append($('<li/>').text(mark + title + ' — ' + (row.detail || '')));
                });
                $box.append($ul);
            }

            var tech = payload.technical || {};
            var logs = payload.recent_logs || [];
            if (Object.keys(tech).length || logs.length) {
                var dump = {
                    technical: tech,
                    recent_logs: logs
                };
                var $toggle = $('<button type="button" class="button button-small" style="margin-top:8px;"/>')
                    .text('نمایش جزئیات فنی');
                var $pre = $('<pre class="shojaei-gsc-debug-log" dir="ltr" style="display:none;max-height:220px;overflow:auto;white-space:pre-wrap;text-align:left;"/>')
                    .text(JSON.stringify(dump, null, 2));
                $toggle.on('click', function () {
                    $pre.toggle();
                });
                $box.append($toggle).append($pre);
            }
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#shojaei-gsc-disconnect-btn').on('click', function () {
        if (!confirm('اتصال سرچ کنسول قطع و فایل کلید حذف شود؟')) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_gsc_disconnect',
            nonce: shojaeiSeoAdmin.nonce
        }, function (response) {
            if (response.success) {
                location.reload();
            } else {
                alert((response.data && response.data.message) || shojaeiSeoAdmin.i18n.error);
                $btn.prop('disabled', false);
            }
        });
    });

    /* OOS lifecycle: suggest temp/auto days from message day (editable). */
    function shojaeiSuggestTimeline(messageDay) {
        var msg = Math.max(1, Math.min(365, parseInt(messageDay, 10) || 15));
        var temp = Math.min(365, Math.max(msg + 1, msg * 2));
        var auto = Math.min(365, Math.max(temp + 1, msg * 3));
        return { message: msg, temp: temp, auto: auto };
    }

    function shojaeiUpdateTimelineHint($scope) {
        var $msg = $scope.find('.shojaei-oos-message-day').first();
        if (!$msg.length) {
            return;
        }
        var s = shojaeiSuggestTimeline($msg.val());
        var $hint = $scope.find('.shojaei-timeline-suggest-hint').first();
        if ($hint.length) {
            $hint.text(
                'پیشنهاد بهینه برای ' + s.message + ' روز پیام: دائم ' + s.temp +
                ' · کاندید ' + s.auto + ' (قابل تغییر دستی)'
            );
        }
        $scope.find('.shojaei-timeline-preset').removeClass('is-active');
        $scope.find('.shojaei-timeline-preset[data-message-day="' + s.message + '"]').addClass('is-active');
    }

    function shojaeiApplyTimelineSuggest($scope, force) {
        var $msg = $scope.find('.shojaei-oos-message-day').first();
        var $temp = $scope.find('.shojaei-oos-temp-days').first();
        var $auto = $scope.find('.shojaei-oos-auto-day').first();
        if (!$msg.length || !$temp.length || !$auto.length) {
            return;
        }

        var s = shojaeiSuggestTimeline($msg.val());
        $msg.val(s.message);

        if (force || !$temp.data('manual')) {
            $temp.val(s.temp).removeData('manual').removeAttr('data-manual');
        }
        if (force || !$auto.data('manual')) {
            $auto.val(s.auto).removeData('manual').removeAttr('data-manual');
        }

        shojaeiUpdateTimelineHint($scope);
    }

    $(document).on('input change', '.shojaei-oos-message-day', function () {
        var $scope = $(this).closest('.shojaei-oos-lifecycle-card');
        if (!$scope.length) {
            $scope = $(this).closest('form');
        }
        // Changing the anchor day refreshes dependent suggestions (unless user locked them).
        shojaeiApplyTimelineSuggest($scope, false);
    });

    $(document).on('input change', '.shojaei-oos-temp-days, .shojaei-oos-auto-day', function (e) {
        if (e.originalEvent) {
            $(this).data('manual', 1).attr('data-manual', '1');
        }
    });

    $(document).on('click', '.shojaei-timeline-preset', function (e) {
        e.preventDefault();
        var day = $(this).data('message-day');
        var $scope = $(this).closest('.shojaei-oos-lifecycle-card');
        if (!$scope.length) {
            $scope = $(this).closest('form');
        }
        $scope.find('.shojaei-oos-message-day').val(day);
        shojaeiApplyTimelineSuggest($scope, true);
    });

    $(document).on('click', '.shojaei-timeline-apply-suggest', function (e) {
        e.preventDefault();
        var $scope = $(this).closest('.shojaei-oos-lifecycle-card');
        if (!$scope.length) {
            $scope = $(this).closest('form');
        }
        shojaeiApplyTimelineSuggest($scope, true);
    });

    // On load: only show hint — never overwrite saved values.
    $('.shojaei-oos-lifecycle-card').each(function () {
        shojaeiUpdateTimelineHint($(this));
    });

    /* Slug redirects + health */
    function shojaeiSlugResultHtml(data) {
        if (!data) {
            return '';
        }
        var html = '<p class="shojaei-slug-result-summary"><strong>' + shojaeiEscapeHtml(data.message || '') + '</strong>';
        html += ' <button type="button" class="button-link shojaei-slug-log-toggle" aria-expanded="false">جزئیات</button></p>';
        html += '<div class="shojaei-slug-result-details" hidden>';
        if (data.redirect_notice) {
            html += '<p>' + shojaeiEscapeHtml(data.redirect_notice) + '</p>';
        }
        if (data.old_slug || data.new_slug) {
            html += '<p dir="ltr"><code>' + shojaeiEscapeHtml(data.old_slug || '') +
                '</code> → <code>' + shojaeiEscapeHtml(data.new_slug || '') + '</code></p>';
        }
        if (data.old_url || data.new_url) {
            html += '<p class="description" dir="ltr">' + shojaeiEscapeHtml(data.old_url || '') +
                '<br>→ ' + shojaeiEscapeHtml(data.new_url || '') + '</p>';
        }
        if (data.indexnow === true) {
            html += '<p class="description">IndexNow: OK</p>';
        } else if (data.indexnow_queued === true) {
            html += '<p class="description">IndexNow: در صف تأیید (قدیم/جدید جدا)</p>';
        }
        html += '</div>';
        return html;
    }

    function shojaeiSlugSelectedIds() {
        var ids = [];
        $('#shojaei-slug-health-table tr.shojaei-slug-health-row:not(.is-filter-hidden) .shojaei-slug-row-check:checked').each(function () {
            ids.push(parseInt($(this).val(), 10));
        });
        return ids.filter(function (n) { return n > 0; }).slice(0, 20);
    }

    function shojaeiSlugUpdateSelectedCount() {
        var n = shojaeiSlugSelectedIds().length;
        $('#shojaei-slug-selected-count').text(n + ' / 20');
    }

    function shojaeiSlugBatchResultHtml(data) {
        if (!data) {
            return '';
        }
        var items = data.items || [];
        var html = '<p class="shojaei-slug-result-summary"><strong>' + shojaeiEscapeHtml(data.message || '') + '</strong>';
        if (items.length) {
            html += ' <button type="button" class="button-link shojaei-slug-log-toggle" aria-expanded="' + (data.dry_run ? 'true' : 'false') + '">جزئیات</button>';
        }
        html += '</p>';
        if (items.length) {
            html += '<div class="shojaei-slug-result-details"' + (data.dry_run ? '' : ' hidden') + '>';
            html += '<ul class="shojaei-slug-batch-list">';
            items.forEach(function (item) {
                var line = (item.title || ('#' + (item.product_id || ''))) + ': ' + (item.message || '');
                if (item.old_slug && item.new_slug) {
                    line += ' [' + item.old_slug + ' → ' + item.new_slug + ']';
                }
                var cls = item.ok ? 'is-ok' : (item.skipped_410 ? 'is-skip' : 'is-fail');
                html += '<li class="' + cls + '">' + shojaeiEscapeHtml(line) + '</li>';
            });
            html += '</ul></div>';
        }
        if (!data.dry_run && data.applied > 0) {
            html += '<p><a href="' + (window.location.href.split('&section=')[0] + '&section=redirects') + '">ریدایرکت‌های ۳۰۱ / Undo</a></p>';
        }
        return html;
    }

    function shojaeiSlugClearChecks() {
        $('#shojaei-slug-check-all').prop('checked', false);
        $('#shojaei-slug-health-table .shojaei-slug-row-check').prop('checked', false);
        shojaeiSlugUpdateSelectedCount();
    }

    function shojaeiSlugRemoveHealthRows(ids) {
        (ids || []).forEach(function (id) {
            if (!id) {
                return;
            }
            $('#shojaei-slug-health-table tr[data-product-id="' + id + '"]').remove();
        });
        shojaeiSlugApplyFilter();
    }

    $(document).on('click', '.shojaei-slug-log-toggle', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $details = $btn.closest('#shojaei-slug-apply-result').find('.shojaei-slug-result-details');
        if (!$details.length) {
            return;
        }
        var open = $details.prop('hidden');
        $details.prop('hidden', !open);
        $btn.attr('aria-expanded', open ? 'true' : 'false');
        $btn.text(open ? 'بستن جزئیات' : 'جزئیات');
    });

    $(document).on('change', '.shojaei-slug-redirect-toggle', function () {
        var id = $(this).data('id');
        var active = $(this).is(':checked') ? 1 : 0;
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_slug_action',
            nonce: shojaeiSeoAdmin.nonce,
            slug_action: 'toggle',
            redirect_id: id,
            is_active: active
        });
    });

    $(document).on('click', '.shojaei-slug-redirect-delete', function () {
        if (!confirm(shojaeiSeoAdmin.i18n.slug_delete_confirm || 'Delete?')) {
            return;
        }
        var id = $(this).data('id');
        var $row = $(this).closest('tr');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_slug_action',
            nonce: shojaeiSeoAdmin.nonce,
            slug_action: 'delete',
            redirect_id: id
        }, function (res) {
            if (res && res.success) {
                $row.fadeOut(300, function () { $(this).remove(); });
            } else {
                alert((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
            }
        });
    });

    $(document).on('click', '.shojaei-slug-undo', function () {
        if (!confirm(shojaeiSeoAdmin.i18n.slug_undo_confirm || 'Undo slug change?')) {
            return;
        }
        var id = $(this).data('id');
        var $row = $(this).closest('tr');
        var $box = $('#shojaei-slug-redirect-result').show().html('<p>...</p>');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_slug_action',
            nonce: shojaeiSeoAdmin.nonce,
            slug_action: 'undo',
            redirect_id: id
        }, function (res) {
            if (res && res.success) {
                $box.html('<p class="shojaei-tone-safe">' + shojaeiEscapeHtml((res.data && res.data.message) || '') + '</p>');
                $row.fadeOut(300, function () { $(this).remove(); });
            } else {
                $box.html('<p class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
            }
        });
    });

    $(document).on('click', '.shojaei-slug-score-help-toggle', function (e) {
        e.preventDefault();
        var $body = $('#shojaei-slug-score-help-body');
        if (!$body.length) {
            return;
        }
        var open = $body.prop('hidden');
        $body.prop('hidden', !open);
        $('.shojaei-slug-score-help-toggle').attr('aria-expanded', open ? 'true' : 'false');
        if (open && $body[0] && $body[0].scrollIntoView) {
            $body[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    function shojaeiSlugApplyFilter(filter) {
        filter = filter || ($('.shojaei-slug-filter.is-active').attr('data-filter') || 'all');
        var q = String($('#shojaei-slug-product-search').val() || '').trim().toLowerCase();
        var visible = 0;
        var total = 0;
        $('#shojaei-slug-health-table tr.shojaei-slug-health-row').each(function () {
            var $row = $(this);
            total++;
            // Use attr() — jQuery .data() can coerce/cache and break filters.
            var hasPersian = String($row.attr('data-has-persian') || '0') === '1';
            var hasLong = String($row.attr('data-has-long') || '0') === '1';
            var score = parseInt($row.attr('data-score'), 10);
            if (isNaN(score)) {
                score = 0;
            }
            var show = true;
            if (filter === 'persian') {
                show = hasPersian;
            } else if (filter === 'long') {
                show = hasLong;
            } else if (filter === 'low') {
                show = score < 50;
            }
            if (show && q) {
                var hay = (
                    $row.find('.shojaei-slug-col-title').text() + ' ' +
                    $row.find('.shojaei-slug-col-slug').text() + ' ' +
                    $row.find('.shojaei-slug-col-suggest').text() + ' ' +
                    String($row.attr('data-product-id') || '')
                ).toLowerCase();
                show = hay.indexOf(q) !== -1;
            }
            $row.toggleClass('is-filter-hidden', !show);
            if (!show) {
                $row.find('.shojaei-slug-row-check').prop('checked', false);
            } else {
                visible++;
            }
        });
        var $count = $('#shojaei-slug-filter-count');
        if ($count.length) {
            $count.text(visible + ' / ' + total);
        }
        shojaeiSlugUpdateSelectedCount();
    }

    function shojaeiSlugReasonLabel(code) {
        var map = {
            persian: 'نامک فارسی',
            long: 'خیلی طولانی',
            low_score: 'امتیاز پایین',
            finglish_better: 'پیشنهاد فینگلیش بهتر',
            dup_suggest: 'پیشنهاد تکراری',
            search: 'نتیجه جستجو'
        };
        return map[code] || code;
    }

    function shojaeiSlugBuildHealthRow(item) {
        var tone = item.score >= 75 ? 'safe' : (item.score >= 45 ? 'warning' : 'error');
        var slugRaw = String(item.slug || '');
        var slugDisplay = slugRaw;
        try { slugDisplay = decodeURIComponent(slugRaw); } catch (e) {}
        var reasons = item.reasons || [];
        var reasonsAttr = reasons.join(',');
        var hasPersian = item.has_persian ? '1' : (reasons.indexOf('persian') !== -1 ? '1' : '0');
        var hasLong = item.has_long ? '1' : (reasons.indexOf('long') !== -1 ? '1' : '0');
        var reasonText = reasons.map(shojaeiSlugReasonLabel).join(' · ');
        var title = shojaeiEscapeHtml(item.title || ('#' + item.product_id));
        var editUrl = item.edit_url || '#';
        var $tr = $(
            '<tr class="shojaei-slug-health-row shojaei-slug-search-hit"></tr>'
        );
        $tr.attr({
            'data-product-id': item.product_id,
            'data-old-slug': slugRaw,
            'data-new-slug': item.suggest || '',
            'data-score': item.score,
            'data-reasons': reasonsAttr,
            'data-has-persian': hasPersian,
            'data-has-long': hasLong
        });
        $tr.html(
            '<th class="check-column"><input type="checkbox" class="shojaei-slug-row-check" value="' + item.product_id + '" /></th>' +
            '<td class="shojaei-slug-col-title"><a href="' + editUrl + '">' + title + '</a></td>' +
            '<td class="shojaei-slug-col-slug" dir="auto"><code class="shojaei-slug-code"></code></td>' +
            '<td class="shojaei-slug-col-suggest" dir="ltr"><code class="shojaei-slug-code"></code></td>' +
            '<td><span class="shojaei-slug-score shojaei-tone-' + tone + '">' + item.score + '</span></td>' +
            '<td class="shojaei-slug-col-reason"></td>' +
            '<td class="shojaei-slug-col-actions"><button type="button" class="button button-small button-primary shojaei-slug-apply" data-id="' + item.product_id + '">اعمال + ۳۰۱</button></td>'
        );
        $tr.find('.shojaei-slug-col-slug code').text(slugDisplay);
        $tr.find('.shojaei-slug-col-suggest code').text(item.suggest || '');
        $tr.find('.shojaei-slug-col-reason').text(reasonText);
        return $tr;
    }

    var shojaeiSlugSearchTimer = null;
    function shojaeiSlugRunProductSearch(forceAjax) {
        var q = String($('#shojaei-slug-product-search').val() || '').trim();
        $('#shojaei-slug-product-search-clear').prop('hidden', q.length === 0);
        shojaeiSlugApplyFilter();
        if (!forceAjax || q.length < 1) {
            if (!q) {
                $('#shojaei-slug-search-status').text('');
                $('#shojaei-slug-health-table tr.shojaei-slug-search-hit').remove();
            }
            return;
        }
        var $status = $('#shojaei-slug-search-status').text(shojaeiSeoAdmin.i18n.slug_search_loading || '...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_slug_action',
            nonce: shojaeiSeoAdmin.nonce,
            slug_action: 'search_products',
            q: q
        }).done(function (res) {
            $('#shojaei-slug-health-table tr.shojaei-slug-search-hit').remove();
            if (!res || !res.success || !res.data || !res.data.rows || !res.data.rows.length) {
                $status.text(shojaeiSeoAdmin.i18n.slug_search_empty || 'No results');
                return;
            }
            var rows = res.data.rows;
            var $tbody = $('#shojaei-slug-health-table tbody');
            if (!$tbody.length) {
                // Table missing (empty state) — rebuild minimal table.
                var $card = $('#shojaei-slug-search-status').closest('.shojaei-card');
                $card.find('.shojaei-empty-state').remove();
                if (!$('#shojaei-slug-health-table').length) {
                    $card.append(
                        '<table class="widefat striped shojaei-table shojaei-slug-health-table" id="shojaei-slug-health-table">' +
                        '<thead><tr>' +
                        '<th class="check-column"></th><th>محصول</th><th>نامک فعلی</th><th>پیشنهاد فینگلیش</th><th>امتیاز</th><th>دلیل</th><th>عملیات</th>' +
                        '</tr></thead><tbody></tbody></table>'
                    );
                    $tbody = $('#shojaei-slug-health-table tbody');
                }
            }
            rows.reverse().forEach(function (item) {
                var pid = String(item.product_id);
                var $existing = $('#shojaei-slug-health-table tr[data-product-id="' + pid + '"]');
                if ($existing.length) {
                    $existing.removeClass('is-filter-hidden').prependTo($tbody);
                } else {
                    shojaeiSlugBuildHealthRow(item).prependTo($tbody);
                }
            });
            var tmpl = shojaeiSeoAdmin.i18n.slug_search_found || '%d';
            $status.text(tmpl.replace('%d', String(rows.length)));
            shojaeiSlugApplyFilter();
        }).fail(function () {
            $status.text(shojaeiSeoAdmin.i18n.slug_search_empty || 'No results');
        });
    }

    $(document).on('input', '#shojaei-slug-product-search', function () {
        if (shojaeiSlugSearchTimer) {
            clearTimeout(shojaeiSlugSearchTimer);
        }
        shojaeiSlugSearchTimer = setTimeout(function () {
            shojaeiSlugApplyFilter();
        }, 200);
    });
    $(document).on('keydown', '#shojaei-slug-product-search', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            shojaeiSlugRunProductSearch(true);
        }
    });
    $(document).on('click', '#shojaei-slug-product-search-btn', function (e) {
        e.preventDefault();
        shojaeiSlugRunProductSearch(true);
    });
    $(document).on('click', '#shojaei-slug-product-search-clear', function (e) {
        e.preventDefault();
        $('#shojaei-slug-product-search').val('');
        $('#shojaei-slug-health-table tr.shojaei-slug-search-hit').remove();
        $('#shojaei-slug-search-status').text('');
        $(this).prop('hidden', true);
        shojaeiSlugApplyFilter();
    });

    $(document).on('input', '#shojaei-slug-redirect-search', function () {
        var q = String($(this).val() || '').trim().toLowerCase();
        $('#shojaei-slug-redirects-table tbody tr').each(function () {
            var $row = $(this);
            if (!q) {
                $row.show();
                return;
            }
            var hay = $row.text().toLowerCase();
            $row.toggle(hay.indexOf(q) !== -1);
        });
    });

    $(document).on('click', '#shojaei-slug-train-preview', function () {
        var $st = $('#shojaei-slug-train-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_slug_action',
            nonce: shojaeiSeoAdmin.nonce,
            slug_action: 'dict_preview',
            fa: $('#shojaei-slug-train-fa').val() || '',
            en: $('#shojaei-slug-train-en').val() || '',
            title: 'کتونی نیوبالانس ۵۳۰ مردانه مشکی سفید'
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $('#shojaei-slug-train-sample').text(res.data.slug || '');
            $st.html('<span class="shojaei-tone-safe">پیش‌نمایش: ' + shojaeiEscapeHtml(res.data.slug || '') + '</span>');
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '#shojaei-slug-train-save', function () {
        var $st = $('#shojaei-slug-train-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_slug_action',
            nonce: shojaeiSeoAdmin.nonce,
            slug_action: 'dict_add',
            fa: $('#shojaei-slug-train-fa').val() || '',
            en: $('#shojaei-slug-train-en').val() || ''
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
            window.setTimeout(function () { window.location.reload(); }, 600);
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '.shojaei-slug-train-del', function () {
        var fa = $(this).data('fa');
        if (!fa) { return; }
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_slug_action',
            nonce: shojaeiSeoAdmin.nonce,
            slug_action: 'dict_delete',
            fa: fa
        }).done(function () {
            window.location.reload();
        });
    });

    $(document).on('click', '.shojaei-slug-filter', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var filter = String($(this).attr('data-filter') || 'all');
        $('.shojaei-slug-filter').removeClass('is-active');
        $(this).addClass('is-active');
        shojaeiSlugApplyFilter(filter);
    });

    $(document).on('change', '#shojaei-slug-check-all', function () {
        var on = $(this).is(':checked');
        var $boxes = $('#shojaei-slug-health-table tr.shojaei-slug-health-row:not(.is-filter-hidden) .shojaei-slug-row-check');
        if (!on) {
            $boxes.prop('checked', false);
        } else {
            $boxes.prop('checked', false);
            $boxes.slice(0, 20).prop('checked', true);
        }
        shojaeiSlugUpdateSelectedCount();
    });

    $(document).on('change', '.shojaei-slug-row-check', function () {
        if ($(this).is(':checked')) {
            var n = $('#shojaei-slug-health-table .shojaei-slug-row-check:checked').length;
            if (n > 20) {
                $(this).prop('checked', false);
                alert(shojaeiSeoAdmin.i18n.slug_batch_max || 'Max 20');
            }
        }
        shojaeiSlugUpdateSelectedCount();
    });

    function shojaeiSlugRunBatch(dry) {
        var ids = shojaeiSlugSelectedIds();
        if (!ids.length) {
            alert(shojaeiSeoAdmin.i18n.select_products || 'Select products');
            return;
        }
        if (!dry && !confirm(shojaeiSeoAdmin.i18n.slug_batch_confirm || 'Apply selected slug changes + 301?')) {
            return;
        }
        var $box = $('#shojaei-slug-apply-result').show().html('<p>' + (shojaeiSeoAdmin.i18n.preview_loading || '...') + '</p>');
        if ($box[0] && $box[0].scrollIntoView) {
            $box[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_slug_action',
            nonce: shojaeiSeoAdmin.nonce,
            slug_action: dry ? 'batch_preview' : 'batch_apply',
            product_ids: JSON.stringify(ids)
        }, function (res) {
            if (res && res.success) {
                $box.html(shojaeiSlugBatchResultHtml(res.data));
                if (!dry) {
                    var gone = [];
                    (res.data.items || []).forEach(function (item) {
                        if (item.product_id && (item.ok || item.skipped_410 || item.already_done)) {
                            gone.push(item.product_id);
                        }
                    });
                    shojaeiSlugRemoveHealthRows(gone);
                    shojaeiSlugClearChecks();
                }
            } else {
                $box.html('<p class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
            }
        }).fail(function () {
            $box.html('<p class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</p>');
        });
    }

    $(document).on('click', '#shojaei-slug-batch-dry', function () {
        shojaeiSlugRunBatch(true);
    });
    $(document).on('click', '#shojaei-slug-batch-apply', function () {
        shojaeiSlugRunBatch(false);
    });

    $(document).on('click', '.shojaei-slug-apply', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var id = $btn.data('id');
        var oldSlug = String($row.data('old-slug') || '');
        var newSlug = String($row.data('new-slug') || '');
        var msg = shojaeiSeoAdmin.i18n.slug_apply_confirm || 'Apply?';
        if (oldSlug && newSlug) {
            msg = 'نامک از:\n' + oldSlug + '\n\nبه:\n' + newSlug + '\n\nعوض شود و ریدایرکت ۳۰۱ ساخته شود؟';
        }
        if (!window.confirm(msg)) {
            return;
        }
        var $box = $('#shojaei-slug-apply-result').show().html('<p>...</p>');
        if ($box.length && $box[0].scrollIntoView) {
            $box[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        $btn.prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_slug_action',
            nonce: shojaeiSeoAdmin.nonce,
            slug_action: 'apply',
            product_id: id
        }, function (res) {
            $btn.prop('disabled', false);
            if (res && res.success) {
                $box.html(shojaeiSlugResultHtml(res.data));
                $row.remove();
                shojaeiSlugClearChecks();
                shojaeiSlugApplyFilter();
            } else {
                $box.html('<p class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
                if (res && res.data && res.data.skipped_410) {
                    $row.remove();
                    shojaeiSlugClearChecks();
                    shojaeiSlugApplyFilter();
                }
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $box.html('<p class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</p>');
        });
    });

    shojaeiSlugUpdateSelectedCount();
    if ($('#shojaei-slug-health-table').length) {
        shojaeiSlugApplyFilter('all');
        shojaeiSlugClearChecks();
    }

    $(document).on('click', '#shojaei-slug-full-scan', function () {
        var $btn = $(this);
        if ($btn.prop('disabled')) {
            return;
        }
        if (!window.confirm('اسکن کامل همه محصولات منتشرشده شروع شود؟')) {
            return;
        }
        $btn.prop('disabled', true).text('…');
        var $prog = $('#shojaei-slug-scan-progress').show().html('<p>' + (shojaeiSeoAdmin.i18n.batch_queued || '...') + '</p>');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_slug_action',
            nonce: shojaeiSeoAdmin.nonce,
            slug_action: 'start_full_scan'
        }, function (res) {
            if (!res || !res.success) {
                $btn.prop('disabled', false).text('اسکن کامل همه محصولات');
                $prog.html('<p class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
                return;
            }
            var jobId = res.data.job_id || '';
            $('#shojaei-slug-full-scan-status').text(res.data.message || '');
            if (jobId && typeof pollBatchJob === 'function') {
                pollBatchJob(jobId, function () {
                    window.location.reload();
                });
            } else {
                window.setTimeout(function () { window.location.reload(); }, 2000);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('اسکن کامل همه محصولات');
            $prog.html('<p class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</p>');
        });
    });

    /* Broken redirect audit */
    $(document).on('click', '#shojaei-broken-scan', function () {
        var $btn = $(this);
        var $st = $('#shojaei-broken-scan-status');
        $btn.prop('disabled', true);
        $st.show().html('<p>' + (shojaeiSeoAdmin.i18n.batch_running || '...') + '</p>');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_redirect_audit',
            nonce: shojaeiSeoAdmin.nonce,
            audit_action: 'scan'
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<p class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
                $btn.prop('disabled', false);
                return;
            }
            $st.html('<p class="shojaei-tone-ok">' + shojaeiEscapeHtml(res.data.message || shojaeiSeoAdmin.i18n.broken_scan_ok) + '</p>');
            window.setTimeout(function () { window.location.reload(); }, 600);
        }).fail(function () {
            $st.html('<p class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</p>');
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.shojaei-broken-disable', function () {
        var msg = (shojaeiSeoAdmin.i18n && shojaeiSeoAdmin.i18n.broken_disable_confirm) || '';
        if (msg && !window.confirm(msg)) {
            return;
        }
        var $btn = $(this);
        var $row = $btn.closest('tr');
        $btn.prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_redirect_audit',
            nonce: shojaeiSeoAdmin.nonce,
            audit_action: 'disable',
            kind: $row.data('kind') || '',
            id: $row.data('id') || 0,
            product_id: $row.data('product-id') || 0
        }).done(function (res) {
            if (!res || !res.success) {
                window.alert((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
                $btn.prop('disabled', false);
                return;
            }
            $row.fadeOut(300, function () { $(this).remove(); });
        }).fail(function () {
            window.alert(shojaeiSeoAdmin.i18n.error);
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '#shojaei-chain-scan', function () {
        var $btn = $(this);
        var $st = $('#shojaei-chain-scan-status');
        $btn.prop('disabled', true);
        $st.show().html('<p>' + (shojaeiSeoAdmin.i18n.batch_running || '...') + '</p>');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_redirect_audit',
            nonce: shojaeiSeoAdmin.nonce,
            audit_action: 'scan_chains'
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<p class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
                $btn.prop('disabled', false);
                return;
            }
            $st.html('<p class="shojaei-tone-ok">' + shojaeiEscapeHtml(res.data.message || shojaeiSeoAdmin.i18n.broken_scan_ok) + '</p>');
            window.setTimeout(function () { window.location.reload(); }, 600);
        }).fail(function () {
            $st.html('<p class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</p>');
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.shojaei-chain-flatten', function () {
        var msg = (shojaeiSeoAdmin.i18n && shojaeiSeoAdmin.i18n.chain_flatten_confirm) || '';
        if (msg && !window.confirm(msg)) {
            return;
        }
        var $btn = $(this);
        var $row = $btn.closest('tr');
        $btn.prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_redirect_audit',
            nonce: shojaeiSeoAdmin.nonce,
            audit_action: 'flatten',
            kind: $row.data('kind') || '',
            id: $row.data('id') || 0,
            product_id: $row.data('product-id') || 0
        }).done(function (res) {
            if (!res || !res.success) {
                window.alert((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
                $btn.prop('disabled', false);
                return;
            }
            $row.fadeOut(300, function () { $(this).remove(); });
        }).fail(function () {
            window.alert(shojaeiSeoAdmin.i18n.error);
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '#shojaei-loop-scan', function () {
        var $btn = $(this);
        var $st = $('#shojaei-loop-scan-status');
        $btn.prop('disabled', true);
        $st.show().html('<p>' + (shojaeiSeoAdmin.i18n.batch_running || '...') + '</p>');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_redirect_audit',
            nonce: shojaeiSeoAdmin.nonce,
            audit_action: 'scan_loops'
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<p class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
                $btn.prop('disabled', false);
                return;
            }
            $st.html('<p class="shojaei-tone-ok">' + shojaeiEscapeHtml(res.data.message || shojaeiSeoAdmin.i18n.broken_scan_ok) + '</p>');
            window.setTimeout(function () { window.location.reload(); }, 600);
        }).fail(function () {
            $st.html('<p class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</p>');
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.shojaei-loop-break', function () {
        var msg = (shojaeiSeoAdmin.i18n && shojaeiSeoAdmin.i18n.loop_break_confirm) || '';
        if (msg && !window.confirm(msg)) {
            return;
        }
        var $btn = $(this);
        var $row = $btn.closest('tr');
        $btn.prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_redirect_audit',
            nonce: shojaeiSeoAdmin.nonce,
            audit_action: 'break_loop',
            kind: $row.data('kind') || '',
            id: $row.data('id') || 0,
            product_id: $row.data('product-id') || 0
        }).done(function (res) {
            if (!res || !res.success) {
                window.alert((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
                $btn.prop('disabled', false);
                return;
            }
            $row.fadeOut(300, function () { $(this).remove(); });
        }).fail(function () {
            window.alert(shojaeiSeoAdmin.i18n.error);
            $btn.prop('disabled', false);
        });
    });

    /* Uninstall wipe: confirm before saving dangerous policy */
    $(document).on('submit', '.shojaei-settings-form', function (e) {
        var wipe = $(this).find('input[name="shojaei_seo_remove_data_on_uninstall"]:checked').val();
        if (wipe !== 'yes') {
            return;
        }
        var msg = (window.shojaeiSeoAdmin && shojaeiSeoAdmin.i18n && shojaeiSeoAdmin.i18n.uninstall_wipe_confirm)
            ? shojaeiSeoAdmin.i18n.uninstall_wipe_confirm
            : '';
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    /* Manual redirects (Rank Math–style) */
    function shojaeiMrSyncRemoveButtons() {
        var $rows = $('#shojaei-mr-sources .shojaei-mr-source-row');
        $rows.find('.shojaei-mr-remove-source').prop('hidden', $rows.length < 2);
    }

    $(document).on('click', '#shojaei-mr-add-source', function (e) {
        e.preventDefault();
        var $row = $('<div class="shojaei-mr-source-row"/>');
        $row.append('<input type="text" class="shojaei-mr-source regular-text" name="sources[]" placeholder="/old-path" dir="ltr" />');
        $row.append('<button type="button" class="button-link shojaei-mr-remove-source">Remove</button>');
        $('#shojaei-mr-sources').append($row);
        shojaeiMrSyncRemoveButtons();
        shojaeiMrPreviewArchive();
    });

    $(document).on('click', '.shojaei-mr-remove-source', function (e) {
        e.preventDefault();
        $(this).closest('.shojaei-mr-source-row').remove();
        shojaeiMrSyncRemoveButtons();
        shojaeiMrPreviewArchive();
    });

    var mrArchiveTimer = null;
    function shojaeiMrPreviewArchive() {
        if (!$('#shojaei-manual-redirect-form').length) {
            return;
        }
        var source = '';
        $('#shojaei-mr-sources .shojaei-mr-source').each(function () {
            var v = String($(this).val() || '').trim();
            if (v) {
                source = v;
                return false;
            }
        });
        var $warn = $('#shojaei-mr-archive-warn');
        if (!source) {
            $warn.prop('hidden', true).empty();
            return;
        }
        if (mrArchiveTimer) {
            clearTimeout(mrArchiveTimer);
        }
        mrArchiveTimer = setTimeout(function () {
            $.post(shojaeiSeoAdmin.ajaxUrl, {
                action: 'shojaei_seo_manual_redirect',
                nonce: shojaeiSeoAdmin.nonce,
                mr_action: 'archive_preview',
                source: source
            }).done(function (res) {
                if (!res || !res.success || !res.data || !res.data.is_archive) {
                    $warn.prop('hidden', true).empty();
                    return;
                }
                $warn.prop('hidden', false).html('<p><strong>' + shojaeiEscapeHtml(res.data.message || '') + '</strong></p>');
                if ($('#shojaei-mr-covers-pagination').is(':checked') && res.data.needs_paging) {
                    $('#shojaei-mr-match').val('archive');
                }
            });
        }, 400);
    }

    $(document).on('input change', '#shojaei-mr-sources .shojaei-mr-source, #shojaei-mr-covers-pagination', function () {
        shojaeiMrPreviewArchive();
    });

    $(document).on('change', '.shojaei-mr-type-group input[type="radio"]', function () {
        var name = $(this).attr('name');
        $(this).closest('.shojaei-mr-type-group').find('label.shojaei-mr-chip').removeClass('is-active');
        $(this).closest('label.shojaei-mr-chip').addClass('is-active');
        if (name === 'redirect_type') {
            var t = $(this).val();
            var needDest = (t === '301' || t === '302' || t === '307');
            $('#shojaei-mr-destination').prop('required', needDest);
        }
    });

    $(document).on('submit', '#shojaei-manual-redirect-form', function (e) {
        e.preventDefault();
        var sources = [];
        $('#shojaei-mr-sources .shojaei-mr-source').each(function () {
            var v = String($(this).val() || '').trim();
            if (v) { sources.push(v); }
        });
        var $box = $('#shojaei-mr-form-result').show().html('<p>...</p>');
        var $btn = $('#shojaei-mr-submit').prop('disabled', true);
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_manual_redirect',
            nonce: shojaeiSeoAdmin.nonce,
            mr_action: 'add',
            sources: JSON.stringify(sources),
            destination: $('#shojaei-mr-destination').val() || '',
            redirect_type: $('input[name="redirect_type"]:checked').val() || '301',
            match_type: $('#shojaei-mr-match').val() || 'exact',
            ignore_case: $('input[name="ignore_case"]').is(':checked') ? 1 : 0,
            covers_pagination: $('#shojaei-mr-covers-pagination').is(':checked') ? 1 : 0,
            is_active: $('input[name="is_active"]:checked').val() || '1'
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $box.html('<p class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
                return;
            }
            $box.html('<p class="shojaei-tone-safe">' + shojaeiEscapeHtml((res.data && res.data.message) || shojaeiSeoAdmin.i18n.mr_saved || 'OK') + '</p>');
            window.setTimeout(function () {
                window.location.href = 'admin.php?page=shojaei-seo&tab=manual-redirects';
            }, 500);
        }).fail(function () {
            $btn.prop('disabled', false);
            $box.html('<p class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</p>');
        });
    });

    $(document).on('change', '.shojaei-mr-toggle', function () {
        var id = $(this).data('id');
        var active = $(this).is(':checked') ? 1 : 0;
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_manual_redirect',
            nonce: shojaeiSeoAdmin.nonce,
            mr_action: 'toggle',
            redirect_id: id,
            is_active: active
        });
    });

    $(document).on('click', '.shojaei-mr-delete', function () {
        var msg = (shojaeiSeoAdmin.i18n && shojaeiSeoAdmin.i18n.mr_delete_confirm) || 'Delete?';
        if (!window.confirm(msg)) { return; }
        var id = $(this).data('id');
        var $row = $(this).closest('tr');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_manual_redirect',
            nonce: shojaeiSeoAdmin.nonce,
            mr_action: 'delete',
            redirect_id: id
        }).done(function (res) {
            if (res && res.success) {
                $row.fadeOut(200, function () { $(this).remove(); });
            } else {
                window.alert((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
            }
        });
    });

    $(document).on('input', '#shojaei-mr-search', function () {
        var q = String($(this).val() || '').trim().toLowerCase();
        $('#shojaei-mr-table tbody tr').each(function () {
            var $row = $(this);
            if (!q) { $row.show(); return; }
            $row.toggle($row.text().toLowerCase().indexOf(q) !== -1);
        });
    });

    if ($('#shojaei-mr-sources').length) {
        shojaeiMrSyncRemoveButtons();
    }

    /* Link Genius */
    function shojaeiLgResetMapForm() {
        $('#shojaei-lg-map-id').val('0');
        $('#shojaei-lg-map-name').val('');
        $('#shojaei-lg-map-url').val('');
        $('#shojaei-lg-map-keywords').val('');
        $('#shojaei-lg-map-max').val('3');
        $('#shojaei-lg-map-case').prop('checked', false);
        $('#shojaei-lg-map-active').prop('checked', true);
        $('#shojaei-lg-map-save').text('ذخیره نقشه');
    }

    $(document).on('click', '#shojaei-lg-map-reset', function (e) {
        e.preventDefault();
        shojaeiLgResetMapForm();
    });

    $(document).on('submit', '#shojaei-lg-map-form', function (e) {
        e.preventDefault();
        var $box = $('#shojaei-lg-map-result').show().html('<p>...</p>');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_genius',
            nonce: shojaeiSeoAdmin.nonce,
            lg_action: 'save_map',
            map_id: $('#shojaei-lg-map-id').val() || 0,
            name: $('#shojaei-lg-map-name').val() || '',
            target_url: $('#shojaei-lg-map-url').val() || '',
            keywords: $('#shojaei-lg-map-keywords').val() || '',
            max_per_post: $('#shojaei-lg-map-max').val() || 3,
            case_sensitive: $('#shojaei-lg-map-case').is(':checked') ? 1 : 0,
            is_active: $('#shojaei-lg-map-active').is(':checked') ? 1 : 0
        }).done(function (res) {
            if (!res || !res.success) {
                $box.html('<p class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</p>');
                return;
            }
            $box.html('<p class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</p>');
            window.setTimeout(function () { window.location.reload(); }, 500);
        }).fail(function () {
            $box.html('<p class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</p>');
        });
    });

    $(document).on('click', '.shojaei-lg-map-edit', function () {
        var $tr = $(this).closest('tr');
        $('#shojaei-lg-map-id').val($tr.data('id'));
        $('#shojaei-lg-map-name').val($tr.attr('data-name') || '');
        $('#shojaei-lg-map-url').val($tr.attr('data-url') || '');
        $('#shojaei-lg-map-keywords').val($tr.attr('data-keywords') || '');
        $('#shojaei-lg-map-max').val($tr.attr('data-max') || 3);
        $('#shojaei-lg-map-case').prop('checked', String($tr.attr('data-case')) === '1');
        $('#shojaei-lg-map-active').prop('checked', String($tr.attr('data-active')) !== '0');
        $('#shojaei-lg-map-save').text('به‌روزرسانی نقشه');
        $('html, body').animate({ scrollTop: $('#shojaei-lg-map-form').offset().top - 80 }, 300);
    });

    $(document).on('change', '.shojaei-lg-map-toggle', function () {
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_genius',
            nonce: shojaeiSeoAdmin.nonce,
            lg_action: 'toggle_map',
            map_id: $(this).data('id'),
            is_active: $(this).is(':checked') ? 1 : 0
        });
    });

    $(document).on('click', '.shojaei-lg-map-delete', function () {
        var msg = (shojaeiSeoAdmin.i18n && shojaeiSeoAdmin.i18n.lg_map_delete) || 'Delete?';
        if (!window.confirm(msg)) { return; }
        var id = $(this).data('id');
        var $row = $(this).closest('tr');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_genius',
            nonce: shojaeiSeoAdmin.nonce,
            lg_action: 'delete_map',
            map_id: id
        }).done(function (res) {
            if (res && res.success) {
                $row.fadeOut(200, function () { $(this).remove(); });
            }
        });
    });

    $(document).on('click', '.shojaei-lg-fix-remove', function () {
        var $btn = $(this).prop('disabled', true);
        var $tr = $btn.closest('tr');
        if (!window.confirm('لینک از متن محصول حذف شود؟')) {
            $btn.prop('disabled', false);
            return;
        }
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_genius',
            nonce: shojaeiSeoAdmin.nonce,
            lg_action: 'fix_remove_link',
            source_post_id: $btn.data('source-id') || 0,
            dest_url: $btn.data('dest-url') || '',
            alert_id: $btn.data('alert-id') || ''
        }).done(function (res) {
            if (res && res.success) {
                $tr.fadeOut(200, function () { $(this).remove(); });
                $('#shojaei-lg-inv-status').text(res.data.message || 'OK');
            } else {
                $btn.prop('disabled', false);
                $('#shojaei-lg-inv-status').text((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $('#shojaei-lg-inv-status').text(shojaeiSeoAdmin.i18n.error);
        });
    });

    $(document).on('click', '.shojaei-lg-fix-update', function () {
        var $btn = $(this).prop('disabled', true);
        var $tr = $btn.closest('tr');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_genius',
            nonce: shojaeiSeoAdmin.nonce,
            lg_action: 'fix_update_link',
            source_post_id: $btn.data('source-id') || 0,
            dest_url: $btn.data('dest-url') || '',
            new_url: $btn.data('new-url') || ''
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (res && res.success) {
                $('#shojaei-lg-inv-status').text(res.data.message || 'OK');
                window.setTimeout(function () { window.location.reload(); }, 700);
            } else {
                $('#shojaei-lg-inv-status').text((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $('#shojaei-lg-inv-status').text(shojaeiSeoAdmin.i18n.error);
        });
    });

    $(document).on('click', '#shojaei-lg-crawl', function () {
        var msg = (shojaeiSeoAdmin.i18n && shojaeiSeoAdmin.i18n.lg_crawl_confirm) || 'Start crawl?';
        if (!window.confirm(msg)) { return; }
        var $btn = $(this).prop('disabled', true);
        var $st = $('#shojaei-lg-inv-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_genius',
            nonce: shojaeiSeoAdmin.nonce,
            lg_action: 'start_crawl'
        }).done(function (res) {
            if (!res || !res.success) {
                $st.text((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
                $btn.prop('disabled', false);
                return;
            }
            $st.text(res.data.message || 'OK');
            window.setTimeout(function () { window.location.reload(); }, 1200);
        }).fail(function () {
            $st.text(shojaeiSeoAdmin.i18n.error);
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '#shojaei-lg-http-check', function () {
        var $btn = $(this).prop('disabled', true);
        var $st = $('#shojaei-lg-inv-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_genius',
            nonce: shojaeiSeoAdmin.nonce,
            lg_action: 'http_check'
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $st.text((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
                return;
            }
            $st.text(res.data.message || 'OK');
            window.setTimeout(function () { window.location.reload(); }, 800);
        }).fail(function () {
            $btn.prop('disabled', false);
            $st.text(shojaeiSeoAdmin.i18n.error);
        });
    });

    // SEO Pulse — نبض سئو (background job via WP-Cron / Jobs)
    $(document).on('click', '#shojaei-pulse-scan', function () {
        var $btn = $(this).prop('disabled', true);
        var $st = $('#shojaei-pulse-status').text('صف‌بندی اسکن در پس‌زمینه…');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_pulse',
            nonce: shojaeiSeoAdmin.nonce,
            pulse_action: 'start_scan'
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                $btn.prop('disabled', false).text('شروع اسکن پس‌زمینه');
                return;
            }
            $btn.text('در حال اسکن پس‌زمینه…');
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
            window.setTimeout(function () { window.location.reload(); }, 1500);
        }).fail(function () {
            $btn.prop('disabled', false).text('شروع اسکن پس‌زمینه');
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '.shojaei-pulse-reanalyze', function () {
        var $btn = $(this).prop('disabled', true);
        var pid = $btn.data('post-id');
        var $st = $('#shojaei-pulse-status').text('تحلیل تک‌صفحه…');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_pulse',
            nonce: shojaeiSeoAdmin.nonce,
            pulse_action: 'analyze_one',
            post_id: pid
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
            window.setTimeout(function () { window.location.reload(); }, 700);
        }).fail(function () {
            $btn.prop('disabled', false);
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    // Orphan fix — پیشنهاد مبدأ + تأیید نقشه کلمات
    var orphanTargetId = 0;

    function shojaeiOrphanClose() {
        $('#shojaei-orphan-modal').attr('hidden', true);
        orphanTargetId = 0;
    }

    function shojaeiOrphanOpen() {
        $('#shojaei-orphan-modal').removeAttr('hidden');
    }

    $(document).on('click', '[data-orphan-close]', function () {
        shojaeiOrphanClose();
    });

    $(document).on('click', '.shojaei-orphan-fix', function () {
        var $btn = $(this).prop('disabled', true);
        orphanTargetId = parseInt($btn.data('post-id'), 10) || 0;
        var $modal = $('#shojaei-orphan-modal');
        if (!$modal.length || !orphanTargetId) {
            $btn.prop('disabled', false);
            return;
        }
        $('#shojaei-orphan-modal-status').text('در حال یافتن نوشته‌های مرتبط…');
        $('#shojaei-orphan-suggestions').empty();
        $('#shojaei-orphan-keywords').val('');
        $('#shojaei-orphan-modal-target').text('');
        shojaeiOrphanOpen();
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_genius',
            nonce: shojaeiSeoAdmin.nonce,
            lg_action: 'suggest_orphan',
            post_id: orphanTargetId
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $('#shojaei-orphan-modal-status').html(
                    '<span class="shojaei-tone-error">' +
                    shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) +
                    '</span>'
                );
                return;
            }
            var d = res.data || {};
            var t = d.target || {};
            $('#shojaei-orphan-modal-target').html(
                'مقصد: <strong>' + shojaeiEscapeHtml(t.title || ('#' + orphanTargetId)) + '</strong>'
            );
            $('#shojaei-orphan-keywords').val((d.keywords || []).join('\n'));
            var list = d.suggestions || [];
            if (!list.length) {
                $('#shojaei-orphan-suggestions').html(
                    '<p class="description">' + shojaeiEscapeHtml(d.message || 'منبعی پیدا نشد.') + '</p>'
                );
                $('#shojaei-orphan-modal-status').text('');
                return;
            }
            var html = '<table class="widefat striped" style="margin-top:8px;"><thead><tr>' +
                '<th style="width:36px;"></th><th>مبدأ پیشنهادی</th><th>دلیل</th><th>امتیاز</th></tr></thead><tbody>';
            list.forEach(function (row) {
                var reasons = (row.reasons || []).join(' · ');
                html += '<tr>' +
                    '<td><input type="checkbox" class="shojaei-orphan-src" value="' + String(row.post_id) + '"' +
                    (row.checked ? ' checked' : '') + ' /></td>' +
                    '<td><strong>' + shojaeiEscapeHtml(row.title || '') + '</strong><br /><span class="description">' +
                    shojaeiEscapeHtml(row.post_type || '') + '</span></td>' +
                    '<td class="description">' + shojaeiEscapeHtml(reasons) + '</td>' +
                    '<td>' + String(row.score || 0) + '</td></tr>';
            });
            html += '</tbody></table>';
            $('#shojaei-orphan-suggestions').html(html);
            $('#shojaei-orphan-modal-status').text('حداکثر ۳ مبدأ را انتخاب و تأیید کنید.');
        }).fail(function () {
            $btn.prop('disabled', false);
            $('#shojaei-orphan-modal-status').html(
                '<span class="shojaei-tone-error">' + shojaeiEscapeHtml(shojaeiSeoAdmin.i18n.error) + '</span>'
            );
        });
    });

    $(document).on('click', '#shojaei-orphan-apply', function () {
        var $btn = $(this).prop('disabled', true);
        var ids = [];
        $('.shojaei-orphan-src:checked').each(function () {
            ids.push($(this).val());
        });
        if (!orphanTargetId || !ids.length) {
            $btn.prop('disabled', false);
            $('#shojaei-orphan-modal-status').html(
                '<span class="shojaei-tone-error">حداقل یک مبدأ را انتخاب کنید.</span>'
            );
            return;
        }
        $('#shojaei-orphan-modal-status').text('در حال ساخت نقشه…');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_link_genius',
            nonce: shojaeiSeoAdmin.nonce,
            lg_action: 'apply_orphan_fix',
            post_id: orphanTargetId,
            source_ids: ids,
            keywords: $('#shojaei-orphan-keywords').val() || ''
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $('#shojaei-orphan-modal-status').html(
                    '<span class="shojaei-tone-error">' +
                    shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) +
                    '</span>'
                );
                return;
            }
            var msg = (res.data && res.data.message) || 'OK';
            var mapUrl = res.data && res.data.map_url ? res.data.map_url : '';
            $('#shojaei-orphan-modal-status').html(
                '<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(msg) + '</span>' +
                (mapUrl ? ' <a href="' + mapUrl + '">مشاهده نقشه‌ها</a>' : '')
            );
            window.setTimeout(function () { window.location.reload(); }, 1200);
        }).fail(function () {
            $btn.prop('disabled', false);
            $('#shojaei-orphan-modal-status').html(
                '<span class="shojaei-tone-error">' + shojaeiEscapeHtml(shojaeiSeoAdmin.i18n.error) + '</span>'
            );
        });
    });

    // SEO Core — هسته سئو
    $(document).on('change', '.shojaei-seo-core-toggle', function () {
        var $el = $(this);
        var $st = $('#shojaei-seo-core-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_core',
            nonce: shojaeiSeoAdmin.nonce,
            core_action: 'toggle_module',
            module: $el.data('module'),
            enabled: $el.is(':checked') ? 1 : 0
        }).done(function (res) {
            $st.text((res && res.data && res.data.message) || (res && res.success ? 'OK' : shojaeiSeoAdmin.i18n.error));
        }).fail(function () {
            $st.text(shojaeiSeoAdmin.i18n.error);
        });
    });

    $(document).on('change', '.shojaei-seo-core-override', function () {
        var $el = $(this);
        var $st = $('#shojaei-seo-core-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_core',
            nonce: shojaeiSeoAdmin.nonce,
            core_action: 'toggle_override',
            module: $el.data('module'),
            enabled: $el.is(':checked') ? 1 : 0
        }).done(function (res) {
            $st.text((res && res.data && res.data.message) || (res && res.success ? 'OK' : shojaeiSeoAdmin.i18n.error));
        }).fail(function () {
            $st.text(shojaeiSeoAdmin.i18n.error);
        });
    });

    $(document).on('click', '#shojaei-seo-core-heal', function () {
        var $btn = $(this).prop('disabled', true);
        var $st = $('#shojaei-seo-core-heal-status').text('در حال خودترمیمی…');
        var $rep = $('#shojaei-seo-core-heal-report').empty();
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_core',
            nonce: shojaeiSeoAdmin.nonce,
            core_action: 'heal'
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            var d = res.data || {};
            var ok = !!d.ok;
            $st.html('<span class="' + (ok ? 'shojaei-tone-safe' : 'shojaei-tone-warning') + '">' + (d.message || 'OK') + '</span>');
            var html = '';
            function listBlock(title, items, cls) {
                if (!items || !items.length) { return ''; }
                var out = '<p><strong>' + title + '</strong></p><ul style="list-style:disc;margin:0 1.2em 10px;">';
                items.forEach(function (line) {
                    out += '<li class="' + (cls || '') + '">' + shojaeiEscapeHtml(line) + '</li>';
                });
                return out + '</ul>';
            }
            html += listBlock('ترمیم‌شده', d.repaired || [], 'shojaei-tone-safe');
            html += listBlock('سالم', d.healthy || [], '');
            html += listBlock('خطا / هشدار', d.errors || [], 'shojaei-tone-error');
            $rep.html(html);
            window.setTimeout(function () { window.location.reload(); }, 1200);
        }).fail(function () {
            $btn.prop('disabled', false);
            $st.text(shojaeiSeoAdmin.i18n.error);
        });
    });

    $(document).on('click', '#shojaei-seo-core-selftest', function () {
        var $btn = $(this).prop('disabled', true);
        var $st = $('#shojaei-seo-core-heal-status').text('در حال خودآزمون…');
        var $rep = $('#shojaei-seo-core-selftest-report').empty();
        $('#shojaei-seo-core-heal-report').empty();
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_core',
            nonce: shojaeiSeoAdmin.nonce,
            core_action: 'self_test'
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            var d = res.data || {};
            var ok = !!d.ok;
            $st.html('<span class="' + (ok ? 'shojaei-tone-safe' : 'shojaei-tone-error') + '">' + (d.message || 'OK') + '</span>');
            var html = '<ul style="list-style:none;margin:0;padding:0;">';
            (d.results || []).forEach(function (row) {
                var tone = row.status === 'pass' ? 'shojaei-tone-safe' : (row.status === 'skip' ? 'shojaei-tone-warning' : 'shojaei-tone-error');
                var tag = row.status === 'pass' ? 'موفق' : (row.status === 'skip' ? 'ردشده' : 'شکست');
                html += '<li style="margin:0 0 8px;"><span class="shojaei-slug-score ' + tone + '">' + tag + '</span> ';
                html += '<strong>' + shojaeiEscapeHtml(row.label || row.id || '') + '</strong> — ';
                html += shojaeiEscapeHtml(row.message || '') + '</li>';
            });
            html += '</ul>';
            $rep.html(html);
        }).fail(function () {
            $btn.prop('disabled', false);
            $st.text(shojaeiSeoAdmin.i18n.error);
        });
    });

    $(document).on('click', '#shojaei-sitemap-copy', function () {
        var $input = $('#shojaei-sitemap-url');
        var val = $input.val() || '';
        var $st = $('#shojaei-sitemap-status');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(val).then(function () {
                $st.text('آدرس کپی شد.');
            }).catch(function () {
                $input.trigger('select');
                document.execCommand('copy');
                $st.text('آدرس کپی شد.');
            });
        } else {
            $input.trigger('select');
            document.execCommand('copy');
            $st.text('آدرس کپی شد.');
        }
    });

    $(document).on('click', '#shojaei-sitemap-save-settings', function () {
        var $st = $('#shojaei-sitemap-status').text('...');
        var payload = {
            action: 'shojaei_seo_core_sitemap',
            nonce: shojaeiSeoAdmin.nonce,
            sitemap_action: 'save_settings'
        };
        $('#shojaei-sitemap-settings input[type="checkbox"]').each(function () {
            var name = $(this).attr('name');
            if (name) {
                payload[name] = $(this).is(':checked') ? 1 : 0;
            }
        });
        $.post(shojaeiSeoAdmin.ajaxUrl, payload).done(function (res) {
            $st.text((res && res.data && res.data.message) || (res && res.success ? 'OK' : shojaeiSeoAdmin.i18n.error));
            if (res && res.success) {
                window.setTimeout(function () { window.location.reload(); }, 700);
            }
        }).fail(function () {
            $st.text(shojaeiSeoAdmin.i18n.error);
        });
    });

    $(document).on('click', '#shojaei-sitemap-flush', function () {
        var $st = $('#shojaei-sitemap-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_core_sitemap',
            nonce: shojaeiSeoAdmin.nonce,
            sitemap_action: 'flush_cache'
        }).done(function (res) {
            $st.text((res && res.data && res.data.message) || (res && res.success ? 'OK' : shojaeiSeoAdmin.i18n.error));
        }).fail(function () {
            $st.text(shojaeiSeoAdmin.i18n.error);
        });
    });

    $(document).on('click', '#shojaei-sitemap-rebuild', function () {
        var $st = $('#shojaei-sitemap-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_core_sitemap',
            nonce: shojaeiSeoAdmin.nonce,
            sitemap_action: 'rebuild_index'
        }).done(function (res) {
            $st.text((res && res.data && res.data.message) || (res && res.success ? 'OK' : shojaeiSeoAdmin.i18n.error));
            if (res && res.success) {
                window.setTimeout(function () { window.location.reload(); }, 600);
            }
        }).fail(function () {
            $st.text(shojaeiSeoAdmin.i18n.error);
        });
    });

    $(document).on('click', '#shojaei-sitemap-health-run', function () {
        var $btn = $(this).prop('disabled', true);
        var $st = $('#shojaei-sitemap-health-status').text('در حال اجرای تست زنده (HTTP + XML)…');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_core_sitemap',
            nonce: shojaeiSeoAdmin.nonce,
            sitemap_action: 'health_run'
        }).done(function (res) {
            $st.text((res && res.data && res.data.message) || (res && res.success ? 'OK' : shojaeiSeoAdmin.i18n.error));
            if (res && res.success) {
                window.setTimeout(function () { window.location.reload(); }, 500);
            } else {
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            $st.text(shojaeiSeoAdmin.i18n.error);
            $btn.prop('disabled', false);
        });
    });

    // IndexNow manual (نمایه‌سازی فوری)
    $(document).on('click', '#shojaei-indexnow-submit', function () {
        var $btn = $(this).prop('disabled', true);
        var $st = $('#shojaei-indexnow-status').text('در حال ارسال…');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_indexnow_manual',
            nonce: shojaeiSeoAdmin.nonce,
            in_action: 'submit',
            urls: $('#shojaei-indexnow-urls').val() || ''
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
        }).fail(function () {
            $btn.prop('disabled', false);
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '#shojaei-indexnow-save', function () {
        var $st = $('#shojaei-indexnow-settings-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_indexnow_manual',
            nonce: shojaeiSeoAdmin.nonce,
            in_action: 'save_key',
            key: $('#shojaei-indexnow-key').val() || '',
            enabled: $('#shojaei-indexnow-enabled').is(':checked') ? 1 : 0
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '#shojaei-indexnow-clear-hist', function () {
        var $st = $('#shojaei-indexnow-hist-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_indexnow_manual',
            nonce: shojaeiSeoAdmin.nonce,
            in_action: 'clear_history'
        }).done(function (res) {
            if (!res || !res.success) {
                $st.text((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
                return;
            }
            window.location.reload();
        }).fail(function () {
            $st.text(shojaeiSeoAdmin.i18n.error);
        });
    });

    function shojaeiIndexNowSelectedIds() {
        var ids = [];
        $('.shojaei-indexnow-pick:checked').each(function () {
            var v = $(this).val();
            if (v) { ids.push(v); }
        });
        return ids;
    }

    $(document).on('change', '#shojaei-indexnow-check-all', function () {
        $('.shojaei-indexnow-pick').prop('checked', $(this).is(':checked'));
    });

    $(document).on('click', '#shojaei-indexnow-scan-suggest', function () {
        var $btn = $(this).prop('disabled', true);
        var $st = $('#shojaei-indexnow-suggest-status').text('در حال ساخت پیشنهاد…');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_indexnow_manual',
            nonce: shojaeiSeoAdmin.nonce,
            in_action: 'scan_suggest'
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
            window.setTimeout(function () { window.location.reload(); }, 700);
        }).fail(function () {
            $btn.prop('disabled', false);
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '#shojaei-indexnow-confirm', function () {
        var ids = shojaeiIndexNowSelectedIds();
        var $st = $('#shojaei-indexnow-suggest-status');
        if (!ids.length) {
            $st.html('<span class="shojaei-tone-error">حداقل یک مورد را انتخاب کنید.</span>');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $st.text('در حال ارسال به IndexNow…');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_indexnow_manual',
            nonce: shojaeiSeoAdmin.nonce,
            in_action: 'confirm_pending',
            ids: ids
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
            window.setTimeout(function () { window.location.reload(); }, 900);
        }).fail(function () {
            $btn.prop('disabled', false);
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '#shojaei-indexnow-dismiss', function () {
        var ids = shojaeiIndexNowSelectedIds();
        var $st = $('#shojaei-indexnow-suggest-status');
        if (!ids.length) {
            $st.html('<span class="shojaei-tone-error">حداقل یک مورد را انتخاب کنید.</span>');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $st.text('…');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_indexnow_manual',
            nonce: shojaeiSeoAdmin.nonce,
            in_action: 'dismiss_pending',
            ids: ids
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            window.location.reload();
        }).fail(function () {
            $btn.prop('disabled', false);
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    /* 404 Monitor */
    function shojaei404Post(payload, $st) {
        $st = $st || $('#shojaei-404-status');
        $st.text('...');
        return $.post(shojaeiSeoAdmin.ajaxUrl, $.extend({
            action: 'shojaei_seo_core_404',
            nonce: shojaeiSeoAdmin.nonce
        }, payload)).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
            if (payload.monitor_action !== 'save_settings') {
                window.setTimeout(function () { window.location.reload(); }, 600);
            }
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    }

    $(document).on('click', '#shojaei-404-save-settings', function () {
        shojaei404Post({
            monitor_action: 'save_settings',
            retention_days: $('#shojaei-404-retention').val() || 30,
            ignore_bots: $('#shojaei-404-ignore-bots').is(':checked') ? 1 : 0
        });
    });

    $(document).on('click', '#shojaei-404-purge', function () {
        shojaei404Post({ monitor_action: 'purge_now' });
    });

    $(document).on('click', '#shojaei-404-clear-open', function () {
        if (!window.confirm('همه مسیرهای باز پاک شوند؟')) {
            return;
        }
        shojaei404Post({ monitor_action: 'clear_open' });
    });

    $(document).on('click', '.shojaei-404-ignore', function () {
        var id = $(this).closest('tr').data('id');
        shojaei404Post({ monitor_action: 'ignore', id: id });
    });

    $(document).on('click', '.shojaei-404-reopen', function () {
        var id = $(this).closest('tr').data('id');
        shojaei404Post({ monitor_action: 'reopen', id: id });
    });

    $(document).on('click', '.shojaei-404-delete', function () {
        var id = $(this).closest('tr').data('id');
        if (!window.confirm('این ردیف حذف شود؟')) {
            return;
        }
        shojaei404Post({ monitor_action: 'delete', id: id });
    });

    $(document).on('click', '.shojaei-404-redirect', function () {
        var $tr = $(this).closest('tr');
        var id = $tr.data('id');
        var dest = window.prompt('آدرس مقصد ریدایرکت ۳۰۱ (مسیر یا URL کامل):', '/');
        if (dest === null || !String(dest).trim()) {
            return;
        }
        shojaei404Post({ monitor_action: 'create_redirect', id: id, destination: String(dest).trim() });
    });

    /* Robots.txt module */
    $(document).on('click', '#shojaei-robots-save', function () {
        var $st = $('#shojaei-robots-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_core_robots',
            nonce: shojaeiSeoAdmin.nonce,
            robots_action: 'save',
            mode: $('#shojaei-robots-mode').val() || 'append',
            extra: $('#shojaei-robots-extra').val() || '',
            add_sitemap: $('#shojaei-robots-sitemap').is(':checked') ? 1 : 0
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
            if (res.data.preview) {
                $('#shojaei-robots-preview').text(res.data.preview);
            }
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    /* Canonical module */
    $(document).on('click', '#shojaei-canonical-save', function () {
        var $st = $('#shojaei-canonical-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_core_canonical',
            nonce: shojaeiSeoAdmin.nonce,
            canonical_action: 'save',
            variation: $('#shojaei-canonical-variation').is(':checked') ? 1 : 0,
            force_https: $('#shojaei-canonical-https').is(':checked') ? 1 : 0,
            strip_args: $('#shojaei-canonical-strip').is(':checked') ? 1 : 0
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    /* Schema module */
    $(document).on('click', '#shojaei-schema-save', function () {
        var $st = $('#shojaei-schema-status').text('...');
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'shojaei_seo_core_schema',
            nonce: shojaeiSeoAdmin.nonce,
            schema_action: 'save',
            respect: $('#shojaei-schema-respect').is(':checked') ? 1 : 0,
            product: $('#shojaei-schema-product').is(':checked') ? 1 : 0,
            breadcrumb: $('#shojaei-schema-breadcrumb').is(':checked') ? 1 : 0,
            article: $('#shojaei-schema-article').is(':checked') ? 1 : 0,
            site: $('#shojaei-schema-site').is(':checked') ? 1 : 0,
            collection: $('#shojaei-schema-collection').is(':checked') ? 1 : 0,
            faq: $('#shojaei-schema-faq').is(':checked') ? 1 : 0,
            detect: $('#shojaei-schema-detect').is(':checked') ? 1 : 0,
            disable_wc: $('#shojaei-schema-disable-wc').is(':checked') ? 1 : 0
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            var msg = res.data.message || 'OK';
            if (res.data.mode) {
                msg += ' — ' + res.data.mode;
            }
            $st.html('<span class="shojaei-tone-safe">' + msg + '</span>');
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    /* Damavand SEO Migrator */
    function damavandMigratePost(payload) {
        return $.post(shojaeiSeoAdmin.ajaxUrl, $.extend({
            action: 'damavand_seo_migrate',
            nonce: shojaeiSeoAdmin.nonce
        }, payload));
    }

    function damavandMigrateSetProgress(pct, text) {
        $('#damavand-migrate-progress').prop('hidden', false);
        $('#damavand-migrate-bar').css('width', Math.max(0, Math.min(100, pct)) + '%');
        $('#damavand-migrate-status').text(text || '');
    }

    function damavandMigrateShowResult(d) {
        var html = '<p class="damavand-migrate-glass__title">' + shojaeiEscapeHtml(d.message || '') + '</p>';
        html += '<ul class="damavand-migrate-glass__stats">';
        html += '<li><strong>' + (d.posts_migrated || 0) + '</strong> پست همگام‌سازی‌شده</li>';
        html += '<li><strong>' + (d.posts_scanned || 0) + '</strong> پست اسکن‌شده</li>';
        html += '<li><strong>' + (d.redirects_imported || 0) + '</strong> ریدایرکت واردشده</li>';
        html += '<li><strong>' + (d.redirects_skipped || 0) + '</strong> ریدایرکت ردشده/تکراری</li>';
        html += '</ul>';
        $('#damavand-migrate-result').html(html).prop('hidden', false);
        if (d.readiness) {
            damavandRenderReadiness(d.readiness);
        } else {
            damavandRefreshReadiness();
        }
    }

    function damavandRenderReadiness(r) {
        if (!r || !r.items) { return; }
        var $ul = $('#damavand-ready-list').empty();
        r.items.forEach(function (item) {
            var $li = $('<li/>').addClass(item.ok ? 'is-ok' : 'is-bad').attr('data-id', item.id || '');
            $li.append($('<strong/>').text((item.ok ? '✓ ' : '✗ ') + (item.label || '')));
            $li.append($('<em/>').text(item.detail || ''));
            if (!item.ok && item.fix) {
                $li.append($('<a/>').addClass('button button-small').attr('href', item.fix).text('رفع'));
            }
            $ul.append($li);
        });
        if (r.ready) {
            $('#damavand-ready-cta').prop('hidden', false);
            $('#damavand-ready-wait').prop('hidden', true);
        } else {
            $('#damavand-ready-cta').prop('hidden', true);
            $('#damavand-ready-wait').prop('hidden', false);
        }
    }

    function damavandRefreshReadiness() {
        damavandMigratePost({ migrate_action: 'readiness' }).done(function (res) {
            if (res && res.success) { damavandRenderReadiness(res.data); }
        });
    }

    function damavandMigrateMetaLoop(offset, overwrite, thenFn) {
        damavandMigrateSetProgress(
            offset > 0 ? Math.min(95, 10 + offset / 50) : 5,
            'مهاجرت متا… از آفست ' + offset
        );
        return damavandMigratePost({
            migrate_action: 'meta_batch',
            offset: offset,
            overwrite: overwrite ? 1 : 0
        }).then(function (res) {
            if (!res || !res.success) {
                return $.Deferred().reject((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
            }
            var d = res.data || {};
            var total = Math.max(1, parseInt(d.total, 10) || 1);
            var cur = parseInt(d.offset, 10) || 0;
            var pct = Math.min(90, Math.round((cur / total) * 90));
            damavandMigrateSetProgress(pct, 'متا: ' + cur + ' / ' + total + ' (این دسته: ' + (d.batch_updated || 0) + ')');
            if (d.done) {
                return thenFn ? thenFn() : d;
            }
            return damavandMigrateMetaLoop(cur, overwrite, thenFn);
        });
    }

    function damavandMigrateRun(opts) {
        opts = opts || {};
        var doMeta = opts.meta !== false;
        var doRedir = opts.redirects !== false;
        var overwrite = $('#damavand-migrate-overwrite').is(':checked');
        var $btns = $('#damavand-migrate-start, #damavand-migrate-meta-only, #damavand-migrate-redirects-only').prop('disabled', true);
        $('#damavand-migrate-result').prop('hidden', true).empty();
        damavandMigrateSetProgress(2, 'آماده‌سازی…');

        var chain = damavandMigratePost({ migrate_action: 'reset' });

        if (doMeta) {
            chain = chain.then(function () {
                return damavandMigrateMetaLoop(0, overwrite, function () {
                    return true;
                });
            });
        }
        if (doRedir) {
            chain = chain.then(function () {
                damavandMigrateSetProgress(92, 'ایمپورت ریدایرکت‌ها…');
                return damavandMigratePost({ migrate_action: 'redirects' });
            });
        }
        chain.then(function () {
            damavandMigrateSetProgress(98, 'جمع‌بندی…');
            return damavandMigratePost({ migrate_action: 'finish' });
        }).done(function (res) {
            $btns.prop('disabled', false);
            if (!res || !res.success) {
                damavandMigrateSetProgress(100, (res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
                return;
            }
            damavandMigrateSetProgress(100, 'تمام');
            damavandMigrateShowResult(res.data || {});
        }).fail(function (err) {
            $btns.prop('disabled', false);
            damavandMigrateSetProgress(100, (typeof err === 'string' ? err : shojaeiSeoAdmin.i18n.error));
        });
    }

    function damavandMigrateShowDryRun(d) {
        if (!d) { return; }
        var html = '<p class="damavand-migrate-glass__title">' + shojaeiEscapeHtml('پیش‌نمایش مهاجرت (بدون نوشتن در دیتابیس)') + '</p>';
        html += '<ul class="damavand-migrate-glass__stats">';
        html += '<li>پست/محصول واجد شرایط: <strong>' + (d.eligible_posts || 0) + '</strong></li>';
        if (d.redirect_counts) {
            html += '<li>ریدایرکت Rank Math: <strong>' + (d.redirect_counts.rank_math || 0) + '</strong></li>';
            html += '<li>ریدایرکت Yoast: <strong>' + (d.redirect_counts.yoast || 0) + '</strong></li>';
            html += '<li>ریدایرکت AIOSEO: <strong>' + (d.redirect_counts.aioseo || 0) + '</strong></li>';
            html += '<li>ریدایرکت SEOPress: <strong>' + (d.redirect_counts.seopress || 0) + '</strong></li>';
            html += '<li>ریدایرکت Redirection: <strong>' + (d.redirect_counts.redirection || 0) + '</strong></li>';
        }
        html += '</ul>';
        if (d.samples && d.samples.length) {
            html += '<p><strong>نمونه قبل/بعد:</strong></p>';
            d.samples.forEach(function (s) {
                html += '<details style="margin:8px 0;"><summary>' + shojaeiEscapeHtml(s.post_title || ('#' + s.post_id)) + '</summary>';
                html += '<pre dir="ltr" style="font-size:11px;overflow:auto;">' + shojaeiEscapeHtml(JSON.stringify({ before: s.before, after: s.after }, null, 2)) + '</pre></details>';
            });
        }
        $('#damavand-migrate-result').html(html).prop('hidden', false);
    }

    $(document).on('click', '#damavand-migrate-dry-run', function () {
        var $btn = $(this).prop('disabled', true);
        damavandMigratePost({ migrate_action: 'dry_run' }).done(function (res) {
            $btn.prop('disabled', false);
            if (res && res.success) {
                damavandMigrateShowDryRun(res.data);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '#damavand-migrate-start', function () {
        damavandMigrateRun({ meta: true, redirects: true });
    });
    $(document).on('click', '#damavand-migrate-meta-only', function () {
        damavandMigrateRun({ meta: true, redirects: false });
    });
    $(document).on('click', '#damavand-migrate-redirects-only', function () {
        damavandMigrateRun({ meta: false, redirects: true });
    });

    $(document).on('click', '#damavand-wizard-enable-emit', function () {
        var $btn = $(this).prop('disabled', true);
        var $st = $('#damavand-wizard-enable-status').text('...');
        damavandMigratePost({ migrate_action: 'enable_emit' }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml((res.data && res.data.message) || 'OK') + '</span>');
            if (res.data && res.data.readiness) {
                damavandRenderReadiness(res.data.readiness);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '#shojaei-wizard-sitemap-copy', function () {
        var $input = $('#shojaei-wizard-sitemap-url');
        var val = $input.val() || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(val);
        } else {
            $input.trigger('select');
            document.execCommand('copy');
        }
        $(this).text('کپی شد');
    });

    $(document).on('click', '#damavand-ready-refresh', function () {
        var $st = $('#damavand-ready-refresh-status').text('...');
        damavandMigratePost({ migrate_action: 'readiness' }).done(function (res) {
            if (!res || !res.success) {
                $st.text((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error);
                return;
            }
            damavandRenderReadiness(res.data);
            $st.text(res.data.ready ? 'آماده است.' : 'هنوز ناقص است.');
        }).fail(function () {
            $st.text(shojaeiSeoAdmin.i18n.error);
        });
    });

    if ($('#damavand-ready-list').length && $('.shojaei-wizard').length) {
        damavandRefreshReadiness();
    }

    /* Advanced Analytics & Google Hub */
    function damavandAaPost(payload) {
        return $.post(shojaeiSeoAdmin.ajaxUrl, $.extend({
            action: 'shojaei_seo_advanced_analytics',
            nonce: shojaeiSeoAdmin.nonce
        }, payload));
    }

    $(document).on('click', '#damavand-ga4-save', function () {
        var $st = $('#damavand-ga4-status').text('...');
        damavandAaPost({
            aa_action: 'save_ga4',
            measurement_id: $('#damavand-ga4-id').val() || '',
            enabled: $('#damavand-ga4-enabled').is(':checked') ? 1 : 0,
            force: $('#damavand-ga4-force').is(':checked') ? 1 : 0
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            if (res.data.measurement_id) {
                $('#damavand-ga4-id').val(res.data.measurement_id);
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '#damavand-aa-save-sitemap', function () {
        var $st = $('#damavand-aa-sitemap-status').text('...');
        damavandAaPost({
            aa_action: 'save_sitemap_auto',
            auto_sitemap: $('#damavand-aa-auto-sitemap').is(':checked') ? 1 : 0
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '#damavand-aa-submit-sitemap', function () {
        var $st = $('#damavand-aa-sitemap-status').text('...');
        damavandAaPost({ aa_action: 'submit_sitemap' }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '#damavand-aa-heal', function () {
        var $st = $('#damavand-aa-heal-status').text('...');
        damavandAaPost({ aa_action: 'heal' }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            $st.html('<span class="shojaei-tone-safe">' + shojaeiEscapeHtml(res.data.message || 'OK') + '</span>');
            window.setTimeout(function () { window.location.reload(); }, 700);
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('click', '#damavand-kw-suggest', function () {
        var $st = $('#damavand-kw-status').text('...');
        var $list = $('#damavand-kw-results').prop('hidden', true).empty();
        $.post(shojaeiSeoAdmin.ajaxUrl, {
            action: 'damavand_keyword_suggest',
            nonce: shojaeiSeoAdmin.nonce,
            keyword: $('#damavand-kw-input').val() || ''
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            var items = (res.data && res.data.suggestions) || [];
            if (!items.length) {
                $st.html('<span class="shojaei-tone-warning">پیشنهادی یافت نشد</span>');
                return;
            }
            items.forEach(function (s) {
                $list.append($('<li/>').text(s));
            });
            $list.prop('hidden', false);
            $st.html('<span class="shojaei-tone-safe">' + items.length + ' پیشنهاد</span>');
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    });

    $(document).on('keydown', '#damavand-kw-input', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#damavand-kw-suggest').trigger('click');
        }
    });

    function damavandSaRender(data) {
        var rows = (data && data.rows) || [];
        var totals = (data && data.totals) || {};
        var meta = (data && data.meta) || {};
        var $table = $('#damavand-sa-table');
        var $tb = $table.find('tbody').empty();
        var $tot = $('#damavand-sa-totals');

        if (!rows.length) {
            $table.prop('hidden', true);
            $tot.prop('hidden', true).empty();
            return;
        }

        rows.forEach(function (r) {
            var label = r.label || '—';
            var $tr = $('<tr/>');
            $tr.append($('<td/>').attr('dir', /^(https?:|\/)/.test(label) ? 'ltr' : 'auto').text(label));
            $tr.append($('<td/>').text(r.clicks != null ? r.clicks : '—'));
            $tr.append($('<td/>').text(r.impressions != null ? r.impressions : '—'));
            $tr.append($('<td/>').text(r.ctr != null ? r.ctr : '—'));
            $tr.append($('<td/>').text(r.position != null ? r.position : '—'));
            $tb.append($tr);
        });
        $table.prop('hidden', false);

        var range = (meta.start_date || '') + ' → ' + (meta.end_date || '');
        var cacheNote = meta.cached ? ' (کش)' : '';
        $tot.html(
            '<span><strong>' + (totals.clicks || 0) + '</strong> کلیک</span>' +
            '<span><strong>' + (totals.impressions || 0) + '</strong> نمایش</span>' +
            '<span><strong>' + (totals.ctr || 0) + '٪</strong> CTR</span>' +
            '<span><strong>' + (totals.position || 0) + '</strong> رتبه</span>' +
            '<span class="description">' + range + cacheNote + '</span>'
        ).prop('hidden', false);
    }

    function damavandSaFetch(force) {
        var $st = $('#damavand-sa-status').text('...');
        damavandAaPost({
            aa_action: 'search_analytics',
            dimension: $('#damavand-sa-dimension').val() || 'query',
            days: $('#damavand-sa-days').val() || 28,
            row_limit: $('#damavand-sa-limit').val() || 25,
            force: force ? 1 : 0
        }).done(function (res) {
            if (!res || !res.success) {
                $st.html('<span class="shojaei-tone-error">' + shojaeiEscapeHtml((res && res.data && res.data.message) || shojaeiSeoAdmin.i18n.error) + '</span>');
                return;
            }
            damavandSaRender(res.data || {});
            var n = ((res.data && res.data.rows) || []).length;
            $st.html('<span class="shojaei-tone-safe">' + n + ' ردیف</span>');
        }).fail(function () {
            $st.html('<span class="shojaei-tone-error">' + shojaeiSeoAdmin.i18n.error + '</span>');
        });
    }

    $(document).on('click', '#damavand-sa-fetch', function () {
        damavandSaFetch(false);
    });
    $(document).on('click', '#damavand-sa-refresh', function () {
        damavandSaFetch(true);
    });

})(jQuery);
