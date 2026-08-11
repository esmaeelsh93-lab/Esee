/*!
 * Sabalan Smart Stock Manager - Bulk Price
 * Author: Esmaeel Shojaei (TO3E) — https://to3edev.ir
 * Copyright (c) 2026 TO3E. All rights reserved.
 * License: Commercial
 */
jQuery(document).ready(function ($) {
    'use strict';

    let jobId = '';
    let jobStatus = '';
    let confirmToken = '';
    let busy = false;
    let cancelRequested = false;

    const $formControls = $('#ssm-bp-field, #ssm-bp-direction, #ssm-bp-mode, #ssm-bp-amount, #ssm-bp-unit, #ssm-bp-scope, #ssm-bp-category, #ssm-bp-empty-sale');

    function i18n(key, fallback) {
        return (ssmBulkPrice.i18n && ssmBulkPrice.i18n[key]) ? ssmBulkPrice.i18n[key] : fallback;
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function errorMessage(xhr, fallback) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            return xhr.responseJSON.data.message;
        }
        return fallback || i18n('generic', 'خطایی رخ داد. دوباره تلاش کنید.');
    }

    function setStatus(html, type) {
        $('#ssm-bp-status')
            .removeClass('is-info is-ok is-warn')
            .addClass(type ? 'is-' + type : '')
            .html(html || '');
    }

    function syncModeUI() {
        $('#ssm-bp-unit-wrap').toggle($('#ssm-bp-mode').val() === 'fixed');
    }

    function syncFieldUI() {
        $('#ssm-bp-sale-options').prop('hidden', $('#ssm-bp-field').val() !== 'sale');
    }

    function setFormLocked(locked) {
        $formControls.not('#ssm-bp-field').prop('disabled', !!locked);
        $('.ssm-inner-tab').prop('disabled', !!locked);
    }

    function collectPayload() {
        return {
            nonce: ssmBulkPrice.nonce,
            price_field: $('#ssm-bp-field').val(),
            direction: $('#ssm-bp-direction').val(),
            mode: $('#ssm-bp-mode').val(),
            amount: $('#ssm-bp-amount').val(),
            unit: $('#ssm-bp-unit').val(),
            scope: $('#ssm-bp-scope').val(),
            category: $('#ssm-bp-category').val(),
            empty_sale: $('#ssm-bp-empty-sale').val()
        };
    }

    function restoreParams(params) {
        if (!params) {
            return;
        }
        if (params.mode === 'legacy_recovery') {
            return;
        }
        $('#ssm-bp-field').val(params.field || 'regular');
        $('#ssm-bp-direction').val(params.direction || 'increase');
        $('#ssm-bp-mode').val(params.mode || 'percent');
        $('#ssm-bp-amount').val(params.amount);
        $('#ssm-bp-unit').val(params.unit || 'toman');
        $('#ssm-bp-scope').val(params.scope || 'all');
        $('#ssm-bp-category').val(String(params.category || 0));
        $('#ssm-bp-empty-sale').val(params.empty_sale || 'skip');
        $('.ssm-inner-tab').removeClass('is-active').attr('aria-selected', 'false');
        $('.ssm-inner-tab[data-ssm-price-field="' + (params.field || 'regular') + '"]')
            .addClass('is-active')
            .attr('aria-selected', 'true');
        syncModeUI();
        syncFieldUI();
    }

    function formatMoney(value) {
        if (value === '' || value === null || typeof value === 'undefined') {
            return '—';
        }
        const number = Number(value);
        return isNaN(number) ? escapeHtml(value) : number.toLocaleString('fa-IR');
    }

    function renderPreview(data) {
        let html = '<div class="ssm-bp-summary">';
        html += '<span>کل هدف: <strong>' + Number(data.matched || 0).toLocaleString('fa-IR') + '</strong></span>';
        html += '<span>قابل تغییر: <strong>' + Number(data.would_change || 0).toLocaleString('fa-IR') + '</strong></span>';
        html += '<span>رد/بدون تغییر: <strong>' + Number(data.skipped || 0).toLocaleString('fa-IR') + '</strong></span>';
        html += '</div>';
        if (data.unit_note) {
            html += '<p class="ssm-bp-unit-note">' + escapeHtml(data.unit_note) + '</p>';
        }
        if (data.samples && data.samples.length) {
            html += '<div class="ssm-table-wrap"><table class="ssm-table widefat striped"><thead><tr>';
            html += '<th>محصول</th><th>SKU</th><th>قبل</th><th>بعد</th></tr></thead><tbody>';
            data.samples.forEach(function (row) {
                html += '<tr><td>' + escapeHtml(row.name || ('#' + row.id));
                html += ' <span class="ssm-muted">#' + Number(row.id) + '</span></td>';
                html += '<td>' + escapeHtml(row.sku || '—') + '</td>';
                html += '<td>' + formatMoney(row.old) + '</td>';
                html += '<td><strong>' + formatMoney(row.new) + '</strong></td></tr>';
            });
            html += '</tbody></table></div>';
            if (Number(data.would_change) > data.samples.length) {
                html += '<p class="ssm-muted">فقط ۱۰ نمونه اول نمایش داده شده است.</p>';
            }
        } else if (!data.preparing) {
            html += '<div class="ssm-notice ssm-notice-warning">' +
                i18n('noChange', 'با این تنظیمات هیچ تغییری اعمال نمی‌شود.') + '</div>';
        }
        $('#ssm-bp-preview-box').html(html).prop('hidden', false);
    }

    function updateControls(data) {
        jobId = data.job_id || jobId;
        jobStatus = data.status || '';
        confirmToken = data.token || confirmToken;

        const active = ['preparing', 'ready', 'running', 'rolling_back'].indexOf(jobStatus) !== -1;
        setFormLocked(active);
        $('#ssm-bp-cancel').prop('hidden', !data.can_cancel).prop('disabled', false);
        $('#ssm-bp-rollback').prop('hidden', !data.can_rollback).prop('disabled', busy);
        $('#ssm-bp-preview').prop('disabled', busy || active);
        $('#ssm-bp-apply')
            .prop('disabled', busy || !data.can_apply || !confirmToken)
            .text(jobStatus === 'running' ? 'ادامه عملیات' : 'اعمال روی محصولات');
    }

    function progressText(data, prefix) {
        const processed = Number(data.processed || 0).toLocaleString('fa-IR');
        const total = Number(data.total || 0).toLocaleString('fa-IR');
        return prefix + ' ' + processed + ' / ' + total +
            ' — بروزرسانی: ' + Number(data.updated || 0).toLocaleString('fa-IR') +
            ' | تداخل: ' + Number(data.conflicts || 0).toLocaleString('fa-IR') +
            ' | ناموفق: ' + Number(data.failed || 0).toLocaleString('fa-IR');
    }

    function post(action, extra) {
        return $.ajax({
            url: ssmBulkPrice.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: $.extend({
                action: action,
                nonce: ssmBulkPrice.nonce
            }, extra || {})
        });
    }

    function runPreview(existingJobId) {
        busy = true;
        $('#ssm-bp-preview, #ssm-bp-apply').prop('disabled', true);
        const payload = existingJobId ? { job_id: existingJobId } : collectPayload();
        post('ssm_bulk_price_preview', payload).done(function (res) {
            if (!res.success || !res.data) {
                busy = false;
                setFormLocked(false);
                $('#ssm-bp-preview').prop('disabled', false);
                setStatus((res.data && res.data.message) ? res.data.message : i18n('generic', 'خطا'), 'warn');
                return;
            }
            const data = res.data;
            jobId = data.job_id || jobId;
            confirmToken = data.token || confirmToken;
            restoreParams(data.params);
            renderPreview(data);
            if (data.preparing) {
                updateControls(data);
                setStatus(
                    i18n('previewing', 'در حال ساخت پیش‌نمایش امن...') + ' ' +
                    Number(data.prepared || 0).toLocaleString('fa-IR') + ' / ' +
                    Number(data.matched || 0).toLocaleString('fa-IR'),
                    'info'
                );
                if (cancelRequested) {
                    busy = false;
                    performCancel();
                    return;
                }
                window.setTimeout(function () { runPreview(jobId); }, 40);
                return;
            }
            busy = false;
            updateControls(data);
            if (data.can_apply) {
                setStatus(
                    (data.resumed ? i18n('resuming', 'عملیات ذخیره‌شده آماده ادامه است. ') : '') +
                    'پیش‌نمایش امن آماده است؛ قیمت مقصد هر محصول ثابت شد.',
                    'ok'
                );
            } else {
                setStatus(i18n('noChange', 'با این تنظیمات هیچ تغییری اعمال نمی‌شود.'), 'warn');
            }
        }).fail(function (xhr) {
            busy = false;
            if (cancelRequested) {
                performCancel();
                return;
            }
            $('#ssm-bp-preview').prop('disabled', false);
            setStatus(errorMessage(xhr, 'ساخت پیش‌نمایش متوقف شد؛ وضعیت ذخیره شده و با تلاش دوباره ادامه پیدا می‌کند.'), 'warn');
        });
    }

    function runApply() {
        busy = true;
        updateControls({
            job_id: jobId,
            status: jobStatus || 'running',
            token: confirmToken,
            can_apply: true,
            can_cancel: true,
            can_rollback: false
        });
        post('ssm_bulk_price_apply', {
            job_id: jobId,
            confirm_token: confirmToken
        }).done(function (res) {
            if (!res.success || !res.data) {
                busy = false;
                $('#ssm-bp-apply, #ssm-bp-cancel').prop('disabled', false);
                setStatus((res.data && res.data.message) ? res.data.message : i18n('generic', 'خطا'), 'warn');
                return;
            }
            const data = res.data;
            jobStatus = data.status;
            confirmToken = data.token || confirmToken;
            renderPreview(data);
            if (cancelRequested && data.status !== 'completed') {
                busy = false;
                performCancel();
                return;
            }
            if (data.status === 'completed') {
                busy = false;
                updateControls(data);
                setStatus(progressText(data, i18n('done', 'اتمام عملیات')), 'ok');
                return;
            }
            setStatus(progressText(data, i18n('running', 'در حال اعمال...')), 'info');
            window.setTimeout(runApply, 40);
        }).fail(function (xhr) {
            busy = false;
            if (cancelRequested) {
                performCancel();
                return;
            }
            $('#ssm-bp-apply, #ssm-bp-cancel').prop('disabled', false);
            $('#ssm-bp-apply').text('ادامه عملیات');
            setStatus(
                errorMessage(xhr, 'ارتباط قطع شد؛ هیچ قیمت تکراری اعمال نمی‌شود. روی «ادامه عملیات» بزنید.'),
                'warn'
            );
        });
    }

    function performCancel() {
        if (!jobId || !confirmToken) {
            cancelRequested = false;
            return;
        }
        busy = true;
        $('#ssm-bp-cancel').prop('disabled', true);
        post('ssm_bulk_price_cancel', {
            job_id: jobId,
            confirm_token: confirmToken
        }).done(function (res) {
            busy = false;
            cancelRequested = false;
            if (res.success && res.data) {
                jobStatus = res.data.status;
                confirmToken = res.data.token || confirmToken;
                updateControls(res.data);
                setFormLocked(false);
                $('#ssm-bp-preview').prop('disabled', false);
                setStatus('عملیات لغو شد. تغییرات انجام‌شده قابل بازگردانی است.', 'warn');
            } else {
                $('#ssm-bp-cancel').prop('disabled', false);
                setStatus((res.data && res.data.message) ? res.data.message : i18n('generic', 'خطا'), 'warn');
            }
        }).fail(function (xhr) {
            busy = false;
            cancelRequested = false;
            $('#ssm-bp-cancel').prop('disabled', false);
            setStatus(errorMessage(xhr), 'warn');
        });
    }

    function runRollback() {
        busy = true;
        $('#ssm-bp-preview, #ssm-bp-apply, #ssm-bp-cancel, #ssm-bp-rollback').prop('disabled', true);
        post('ssm_bulk_price_rollback', {
            job_id: jobId,
            confirm_token: confirmToken
        }).done(function (res) {
            if (!res.success || !res.data) {
                busy = false;
                $('#ssm-bp-rollback').prop('disabled', false);
                setStatus((res.data && res.data.message) ? res.data.message : i18n('generic', 'خطا'), 'warn');
                return;
            }
            const data = res.data;
            jobStatus = data.status;
            confirmToken = data.token || confirmToken;
            if (data.status === 'rolled_back') {
                busy = false;
                updateControls(data);
                setStatus(
                    'بازگردانی تمام شد — بازگردانده‌شده: ' +
                    Number(data.rolled_back || 0).toLocaleString('fa-IR') +
                    ' | تداخل/رد شده: ' + Number(data.conflicts || 0).toLocaleString('fa-IR'),
                    'ok'
                );
                return;
            }
            setStatus(
                'در حال بازگردانی... ' + Number(data.rolled_back || 0).toLocaleString('fa-IR') +
                ' / ' + Number(data.total || 0).toLocaleString('fa-IR'),
                'info'
            );
            window.setTimeout(runRollback, 40);
        }).fail(function (xhr) {
            busy = false;
            $('#ssm-bp-rollback').prop('disabled', false);
            setStatus(errorMessage(xhr, 'بازگردانی متوقف شد؛ برای ادامه دوباره روی دکمه بازگردانی بزنید.'), 'warn');
        });
    }

    $('.ssm-inner-tab').on('click', function () {
        if ($(this).prop('disabled')) {
            return;
        }
        const field = $(this).data('ssm-price-field') || 'regular';
        $('.ssm-inner-tab').removeClass('is-active').attr('aria-selected', 'false');
        $(this).addClass('is-active').attr('aria-selected', 'true');
        $('#ssm-bp-field').val(field);
        syncFieldUI();
        confirmToken = '';
        $('#ssm-bp-apply').prop('disabled', true);
    });

    $('#ssm-bp-mode').on('change', syncModeUI);
    $formControls.on('change input', function () {
        if (!busy && ['preparing', 'ready', 'running'].indexOf(jobStatus) === -1) {
            confirmToken = '';
            $('#ssm-bp-apply').prop('disabled', true);
        }
    });

    $('#ssm-bp-preview').on('click', function () {
        if (busy) {
            return;
        }
        const amount = parseFloat(String($('#ssm-bp-amount').val()).replace(/[^\d.]/g, ''));
        if (isNaN(amount) || amount <= 0) {
            setStatus('مقدار را درست وارد کنید.', 'warn');
            return;
        }
        if (['completed', 'cancelled', 'rolled_back'].indexOf(jobStatus) !== -1) {
            jobId = '';
            jobStatus = '';
            confirmToken = '';
        }
        setStatus(i18n('previewing', 'در حال ساخت پیش‌نمایش امن...'), 'info');
        runPreview('');
    });

    $('#ssm-bp-apply').on('click', function () {
        if (busy || !jobId || !confirmToken) {
            setStatus(i18n('needPreview', 'اول پیش‌نمایش بگیرید.'), 'warn');
            return;
        }
        if (jobStatus !== 'running' && !window.confirm(i18n('confirm', 'آیا مطمئن هستید؟'))) {
            return;
        }
        setStatus(i18n('running', 'در حال اعمال...'), 'info');
        runApply();
    });

    $('#ssm-bp-cancel').on('click', function () {
        if (!jobId || cancelRequested || !window.confirm(i18n('cancelConfirm', 'عملیات لغو شود؟'))) {
            return;
        }
        cancelRequested = true;
        setStatus('درخواست لغو ثبت شد؛ batch جاری تمام می‌شود و سپس عملیات متوقف خواهد شد.', 'warn');
        if (!busy) {
            performCancel();
        }
    });

    $('#ssm-bp-rollback').on('click', function () {
        if (busy || !jobId || !window.confirm(i18n('rollbackConfirm', 'قیمت‌ها بازگردانده شوند؟'))) {
            return;
        }
        runRollback();
    });

    $('#ssm-recovery-scan').on('click', function () {
        if (busy) {
            return;
        }
        if (!window.confirm('از دیتابیس بکاپ گرفته‌اید و می‌خواهید لاگ‌های این بازه برای بازیابی بررسی شوند؟')) {
            return;
        }
        busy = true;
        const $button = $(this);
        $button.prop('disabled', true);
        $('#ssm-recovery-status')
            .removeClass('is-ok is-warn')
            .addClass('is-info')
            .text('در حال خواندن لاگ‌های افزونه و ووکامرس...');
        post('ssm_legacy_bulk_recovery', {
            recovery_date: $('#ssm-recovery-date').val(),
            recovery_from: $('#ssm-recovery-from').val(),
            recovery_to: $('#ssm-recovery-to').val(),
            recovery_field: $('#ssm-recovery-field').val()
        }).done(function (res) {
            busy = false;
            $button.prop('disabled', false);
            if (!res.success || !res.data) {
                $('#ssm-recovery-status')
                    .removeClass('is-info is-ok')
                    .addClass('is-warn')
                    .text((res.data && res.data.message) ? res.data.message : i18n('generic', 'خطا'));
                return;
            }
            const data = res.data;
            jobId = data.job_id;
            jobStatus = data.status;
            confirmToken = data.token;
            renderPreview(data);
            updateControls(data);
            $('#ssm-recovery-status')
                .removeClass('is-info is-warn')
                .addClass('is-ok')
                .text(
                    Number(data.events_found || 0).toLocaleString('fa-IR') +
                    ' رکورد لاگ برای ' +
                    Number(data.would_change || 0).toLocaleString('fa-IR') +
                    ' محصول پیدا شد. نمونه‌ها را بررسی کنید، سپس «بازگردانی آخرین عملیات» را بزنید.'
                );
            $('html, body').animate({ scrollTop: $('#ssm-bp-preview-box').offset().top - 80 }, 250);
        }).fail(function (xhr) {
            busy = false;
            $button.prop('disabled', false);
            $('#ssm-recovery-status')
                .removeClass('is-info is-ok')
                .addClass('is-warn')
                .text(errorMessage(xhr, 'خواندن لاگ‌ها ناموفق بود.'));
        });
    });

    syncModeUI();
    syncFieldUI();

    post('ssm_bulk_price_status').done(function (res) {
        if (!res.success || !res.data || !res.data.has_job) {
            return;
        }
        const data = res.data;
        restoreParams(data.params);
        renderPreview(data);
        updateControls(data);
        if (data.status === 'preparing') {
            setStatus(i18n('resuming', 'عملیات نیمه‌کاره پیدا شد؛ در حال ادامه پیش‌نمایش...'), 'info');
            runPreview(data.job_id);
        } else if (data.status === 'running') {
            setStatus(progressText(data, 'عملیات نیمه‌کاره آماده ادامه است.'), 'warn');
        } else if (data.status === 'ready') {
            setStatus('پیش‌نمایش ذخیره‌شده آماده اجرا است.', 'ok');
        } else if (data.status === 'rolling_back') {
            setStatus('بازگردانی نیمه‌کاره پیدا شد؛ در حال ادامه...', 'info');
            runRollback();
        } else if (data.status === 'completed') {
            if (data.params && data.params.mode === 'legacy_recovery') {
                setStatus('پیش‌نمایش بازیابی اضطراری آماده است؛ برای اجرا دکمه بازگردانی را بزنید.', 'warn');
            } else {
                setStatus(progressText(data, 'آخرین عملیات کامل شده است.'), 'ok');
            }
        } else if (data.status === 'cancelled') {
            setStatus('آخرین عملیات لغو شده است؛ تغییرات انجام‌شده را می‌توانید بازگردانید.', 'warn');
        }
    });
});
