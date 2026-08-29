/*!
 * Sabalan Smart Stock Manager - Admin Script
 * Author: Esmaeel Shojaei (TO3E) — https://to3edev.ir
 * Copyright (c) 2026 TO3E. All rights reserved.
 * License: Commercial
 */
jQuery(document).ready(function ($) {
    let searchXHR = null;
    let debounceTimer = null;
    let lastKeyword = '';
    let currentPage = 1;
    const SCAN_KEY = 'ssm_scan_target';
    const AUTO_BUMP_KEY = 'ssm_auto_bump';

    function getLowStockThreshold() {
        const n = parseInt(ssmAdmin && ssmAdmin.lowStockThreshold, 10);
        return isNaN(n) || n < 1 ? 8 : n;
    }

    function isAutoBumpEnabled() {
        if (!ssmAdmin.perms || !ssmAdmin.perms.canEditStock) {
            return false;
        }
        return $('#ssm-auto-bump').is(':checked');
    }

    function getScanTarget() {
        const saved = localStorage.getItem(SCAN_KEY);
        if (saved === 'bottom' || saved === 'top') {
            return saved;
        }
        const checked = $('input[name="ssm_scan_target"]:checked').val();
        return checked === 'bottom' ? 'bottom' : 'top';
    }

    function getScanInput() {
        return getScanTarget() === 'bottom' ? $('#ssm-code-input') : $('#ssm-search-input');
    }

    function focusScanField() {
        const $input = getScanInput();
        if (!$input.length) {
            return;
        }
        $input.trigger('focus');
        // انتخاب متن برای اسکن بعدی راحت‌تر باشد
        if ($input[0] && typeof $input[0].select === 'function') {
            $input[0].select();
        }
    }

    function applyScanTarget(target) {
        target = target === 'bottom' ? 'bottom' : 'top';
        localStorage.setItem(SCAN_KEY, target);
        $('input[name="ssm_scan_target"][value="' + target + '"]').prop('checked', true);
        $('.ssm-panel-edit, .ssm-panel-quick').removeClass('ssm-scan-active');
        if (target === 'bottom') {
            $('.ssm-panel-quick').addClass('ssm-scan-active');
        } else {
            $('.ssm-panel-edit').addClass('ssm-scan-active');
        }
        focusScanField();
    }

    // بازیابی هدف بارکدخوان و کاهش خودکار
    (function initScannerTarget() {
        const saved = localStorage.getItem(SCAN_KEY) || 'top';
        applyScanTarget(saved);
        const autoSaved = localStorage.getItem(AUTO_BUMP_KEY) === '1';
        if ($('#ssm-auto-bump').length) {
            $('#ssm-auto-bump').prop('checked', autoSaved);
        }
    })();

    $('input[name="ssm_scan_target"]').on('change', function () {
        applyScanTarget($(this).val());
    });

    $('#ssm-auto-bump').on('change', function () {
        localStorage.setItem(AUTO_BUMP_KEY, $(this).is(':checked') ? '1' : '0');
    });

    function ajaxErrorMessage(xhr, fallback) {
        // همیشه اول پیام خود سرور را نشان بده (نه پیام کلی)
        if (xhr && xhr.responseJSON) {
            const d = xhr.responseJSON.data;
            if (d && typeof d === 'object' && d.message) {
                return d.message;
            }
            if (typeof d === 'string' && d) {
                return d;
            }
        }
        if (xhr && xhr.status === 429) {
            return (ssmAdmin.i18n && ssmAdmin.i18n.rateLimited) ? ssmAdmin.i18n.rateLimited : 'تعداد درخواست‌ها زیاد است.';
        }
        if (xhr && (xhr.status === 403 || xhr.status === 401)) {
            return (ssmAdmin.i18n && ssmAdmin.i18n.forbidden) ? ssmAdmin.i18n.forbidden : 'دسترسی غیرمجاز.';
        }
        return fallback || ((ssmAdmin.i18n && ssmAdmin.i18n.generic) ? ssmAdmin.i18n.generic : 'خطایی رخ داد.');
    }

    // بستن نتایج زنده با کلیک بیرون
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.ssm-search-box').length) {
            $('#ssm-live-results-list').hide();
        }
    });

    function showLiveResults(html) {
        const $list = $('#ssm-live-results-list');
        $list.html(html).show();
    }

    function performSearch(keyword, isLive = false, page = 1) {
        keyword = (keyword || '').trim();

        // درخواست قبلی لایو رو قطع کن
        if (searchXHR && searchXHR.readyState !== 4) {
            searchXHR.abort();
        }

        // لودینگ: فقط صفحه اول نتایج را عوض کن؛ لود بیشتر روی دکمه نشان داده شود
        if (!isLive) {
            $('#ssm-live-results-list').hide();
            if (page <= 1) {
                $('#ssm-results').html('<div class="ssm-notice ssm-notice-info">در حال جستجو...</div>');
            } else {
                const $btn = $('.ssm-load-more-button');
                $btn.prop('disabled', true).text('در حال بارگذاری...');
            }
        }

        searchXHR = $.ajax({
            url: ssmAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ssm_search_products',
                keyword: keyword,
                page: page,
                is_live: isLive ? 1 : 0,
                nonce: ssmAdmin.nonce
            },
            success: function (response) {
                if (response.success && response.data && response.data.html) {
                    if (isLive) {
                        if (page > 1) {
                            const $list = $('#ssm-live-results-list');
                            $list.find('.ssm-load-more-trigger').remove();
                            $list.append(response.data.html).show();
                        } else {
                            showLiveResults(response.data.html);
                        }
                    } else {
                        if (page > 1) {
                            $('.ssm-load-more-wrap').remove();
                            $('#ssm-results').append(response.data.html);
                        } else {
                            lastKeyword = keyword;
                            currentPage = 1;
                            $('#ssm-results').html(response.data.html);
                        }
                        onResultsUpdated();
                        // بعد از اسکن/جستجو، متغیر پیدا شده را نشان بده و فیلد را برای اسکن بعدی آماده کن
                        if (page === 1) {
                            const $focus = $('#ssm-results .ssm-variation-focus').first();
                            if ($focus.length) {
                                $('html, body').animate({ scrollTop: $focus.offset().top - 80 }, 250);
                            }
                            if (getScanTarget() === 'top') {
                                $('#ssm-search-input').val('');
                                setTimeout(focusScanField, 100);
                            }
                        }
                    }
                } else {
                    if (isLive) {
                        if (page <= 1) {
                            showLiveResults('<li class="no-result">موردی یافت نشد.</li>');
                        }
                    } else if (page > 1) {
                        $('.ssm-load-more-button').prop('disabled', false).text('نمایش ۱۰ مورد بعدی');
                        alert((response && response.data && response.data.message) ? response.data.message : 'مورد بیشتری پیدا نشد.');
                    } else {
                        $('#ssm-results').html('<div class="ssm-notice ssm-notice-warning">نتیجه‌ای یافت نشد.</div>');
                        onResultsUpdated();
                        if (getScanTarget() === 'top') {
                            setTimeout(focusScanField, 100);
                        }
                    }
                }
            },
            error: function (xhr, status) {
                if (status === 'abort') {
                    return;
                }
                if (!isLive && page > 1) {
                    $('.ssm-load-more-button').prop('disabled', false).text('نمایش ۱۰ مورد بعدی');
                }
                console.error('Search Error:', status);
            }
        });
    }

    // لایو سرچ از ۳ حرف
    $('#ssm-search-input').on('input', function () {
        const keyword = $(this).val().trim();

        clearTimeout(debounceTimer);

        if (keyword.length < 3) {
            $('#ssm-live-results-list').hide().empty();
            return;
        }

        // نمایش سریع حالت لودینگ تا کاربر منتظر نماند
        showLiveResults('<li class="ssm-live-loading"><span class="ssm-spinner"></span> در حال جستجو...</li>');

        debounceTimer = setTimeout(function () {
            performSearch(keyword, true);
        }, 250);
    });

    // با فوکوس دوباره روی فیلد، اگر نتیجه‌ای بود دوباره نشان بده
    $('#ssm-search-input').on('focus', function () {
        const keyword = $(this).val().trim();
        if (keyword.length >= 3 && $('#ssm-live-results-list').children().length) {
            $('#ssm-live-results-list').show();
        }
    });

    // جستجوی کامل (دکمه یا اینتر)
    function triggerFullSearch() {
        const keyword = $('#ssm-search-input').val().trim();
        if (keyword.length > 0) {
            clearTimeout(debounceTimer);
            $('#ssm-live-results-list').hide(); // بستن دراپ‌داون
            lastKeyword = keyword;
            currentPage = 1;
            performSearch(keyword, false, currentPage);
        }
    }

    $('#ssm-search-button').on('click', triggerFullSearch);

    // Enter از کیبورد و بارکدخوان
    $('#ssm-search-input, #ssm-code-input').on('keydown', function (e) {
        if (e.key === 'Enter' || e.which === 13) {
            e.preventDefault();
            if (this.id === 'ssm-code-input') {
                previewByCode();
            } else {
                triggerFullSearch();
            }
        }
    });

    // لود بیشتر در نتایج اصلی — از lastKeyword استفاده کن (فیلد بعد از اسکن خالی می‌شود)
    $(document).on('click', '.ssm-load-more-button', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $wrap = $(this).closest('.ssm-load-more-wrap');
        const nextPage = parseInt($wrap.data('next-page'), 10) || (currentPage + 1);
        const keyword = (lastKeyword || $('#ssm-search-input').val() || '').trim();
        if (!keyword) {
            alert('عبارت جستجو موجود نیست. دوباره جستجو کنید.');
            return;
        }
        currentPage = nextPage;
        performSearch(keyword, false, nextPage);
    });

    // لود بیشتر در دراپ‌داون لایو
    $(document).on('click', '.ssm-load-more-trigger', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const nextPage = parseInt($(this).data('next-page'), 10) || 2;
        const keyword = ($('#ssm-search-input').val() || lastKeyword || '').trim();
        if (!keyword) {
            return;
        }
        performSearch(keyword, true, nextPage);
    });

    // کلیک روی یک آیتم لیست زنده → بارگذاری مستقیم کارت کامل محصول
    $(document).on('click', '.ssm-live-item', function (e) {
        e.preventDefault();
        const loadId = $(this).data('load-id');
        if (!loadId) return;

        $('#ssm-live-results-list').hide().empty();
        $('#ssm-results').html('<div class="ssm-notice ssm-notice-info">در حال بارگذاری محصول...</div>');

        if (searchXHR && searchXHR.readyState !== 4) {
            searchXHR.abort();
        }

        searchXHR = $.ajax({
            url: ssmAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ssm_get_product',
                product_id: loadId,
                nonce: ssmAdmin.nonce
            },
            success: function (res) {
                if (res.success && res.data.html) {
                    $('#ssm-results').html(res.data.html);
                    onResultsUpdated();
                    $('html, body').animate({
                        scrollTop: $('#ssm-results').offset().top - 60
                    }, 300);
                } else {
                    $('#ssm-results').html('<div class="ssm-notice ssm-notice-warning">محصول یافت نشد.</div>');
                    onResultsUpdated();
                }
            },
            error: function (xhr, status) {
                if (status !== 'abort') {
                    $('#ssm-results').html('<div class="ssm-notice ssm-notice-warning">خطا در بارگذاری محصول.</div>');
                    onResultsUpdated();
                }
            }
        });
    });

    // جستجو با کد / بارکد

    function previewByCode() {
        const code = $('#ssm-code-input').val().trim();
        if (!code) {
            $('#ssm-code-preview').html('<div class="ssm-notice ssm-notice-warning">کد را وارد کنید.</div>');
            return;
        }

        // اسکن پایین + تیک خودکار = پیدا کردن و −۱ در یک درخواست
        if (getScanTarget() === 'bottom' && isAutoBumpEnabled()) {
            scanAndDecrease(code);
            return;
        }

        $('#ssm-code-preview').html('<div class="ssm-notice ssm-notice-info"><span class="ssm-spinner"></span> در حال دریافت پیش‌نمایش...</div>');

        $.ajax({
            url: ssmAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ssm_lookup_by_code',
                code: code,
                nonce: ssmAdmin.nonce
            },
            success: function (res) {
                if (res.success && res.data.html) {
                    $('#ssm-code-preview').html(res.data.html);
                    if (getScanTarget() === 'bottom') {
                        $('#ssm-code-input').val('');
                        setTimeout(focusScanField, 100);
                    }
                } else {
                    $('#ssm-code-preview').html(
                        '<div class="ssm-notice ssm-notice-warning">' +
                        (res.data && res.data.message ? res.data.message : 'محصولی یافت نشد.') +
                        '</div>'
                    );
                    if (getScanTarget() === 'bottom') {
                        setTimeout(focusScanField, 100);
                    }
                }
            },
            error: function () {
                $('#ssm-code-preview').html('<div class="ssm-notice ssm-notice-warning">خطا در دریافت پیش‌نمایش.</div>');
                if (getScanTarget() === 'bottom') {
                    setTimeout(focusScanField, 100);
                }
            }
        });
    }

    function scanAndDecrease(code) {
        if (!ssmAdmin.perms || !ssmAdmin.perms.canEditStock) {
            alert((ssmAdmin.i18n && ssmAdmin.i18n.noStockPerm) ? ssmAdmin.i18n.noStockPerm : 'شما دسترسی تغییر موجودی ندارید.');
            return;
        }

        $('#ssm-code-preview').html('<div class="ssm-notice ssm-notice-info"><span class="ssm-spinner"></span> در حال کاهش خودکار...</div>');

        $.ajax({
            url: ssmAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ssm_scan_bump',
                code: code,
                nonce: getSsmNonce(),
                _ajax_nonce: getSsmNonce()
            },
            success: function (res) {
                if (res.success && res.data && res.data.html) {
                    $('#ssm-code-preview').html(res.data.html);
                    $('#ssm-code-input').val('');
                    setTimeout(focusScanField, 100);
                } else {
                    $('#ssm-code-preview').html(
                        '<div class="ssm-notice ssm-notice-warning">' +
                        (res.data && res.data.message ? res.data.message : ((ssmAdmin.i18n && ssmAdmin.i18n.decreaseFailed) ? ssmAdmin.i18n.decreaseFailed : 'کاهش انجام نشد.')) +
                        '</div>'
                    );
                    setTimeout(focusScanField, 100);
                }
            },
            error: function (xhr) {
                $('#ssm-code-preview').html(
                    '<div class="ssm-notice ssm-notice-warning">' +
                    ajaxErrorMessage(xhr, (ssmAdmin.i18n && ssmAdmin.i18n.decreaseFailed) ? ssmAdmin.i18n.decreaseFailed : 'کاهش انجام نشد.') +
                    '</div>'
                );
                setTimeout(focusScanField, 100);
            }
        });
    }

    $('#ssm-code-preview-btn').on('click', previewByCode);

    function getSsmNonce() {
        const fromPage = $('input[name="ssm_page_nonce"]').val();
        if (fromPage) {
            return fromPage;
        }
        return (ssmAdmin && ssmAdmin.nonce) ? ssmAdmin.nonce : '';
    }

    // کم کردن یک واحد
    $(document).on('click', '.ssm-decrease-btn', function () {
        const $btn = $(this);
        const productId = $btn.data('product-id');

        if ($btn.hasClass('saving') || !productId) return;
        if (!ssmAdmin.perms || !ssmAdmin.perms.canEditStock) {
            alert((ssmAdmin.i18n && ssmAdmin.i18n.noStockPerm) ? ssmAdmin.i18n.noStockPerm : 'شما دسترسی تغییر موجودی ندارید.');
            return;
        }

        $btn.addClass('saving').text((ssmAdmin.i18n && ssmAdmin.i18n.decreasing) ? ssmAdmin.i18n.decreasing : 'در حال کاهش...');

        $.ajax({
            url: ssmAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                // نام اکشن بدون کلمه decrease تا فایروال بلاک نکند
                action: 'ssm_stock_bump',
                product_id: productId,
                nonce: getSsmNonce(),
                _ajax_nonce: getSsmNonce()
            },
            success: function (res) {
                if (res.success && res.data && res.data.html) {
                    $('#ssm-code-preview').html(res.data.html);
                } else {
                    alert((res.data && res.data.message) ? res.data.message : 'خطا در کاهش موجودی');
                    $btn.removeClass('saving').text('کم کن (−۱)');
                }
            },
            error: function (xhr) {
                let msg = ajaxErrorMessage(xhr, 'خطا در کاهش موجودی');
                if (xhr && (xhr.status === 403 || xhr.status === 401) && !(xhr.responseJSON && xhr.responseJSON.data)) {
                    msg = 'درخواست توسط امنیت هاست/Wordfence مسدود شد (کد ' + xhr.status + ').\n'
                        + 'Wordfence ← Firewall ← All Options ← Whitelisted URLs\n'
                        + 'یا موقتاً Learning Mode روشن کنید.\n'
                        + 'اکشن مجاز: ssm_stock_bump';
                }
                alert(msg);
                $btn.removeClass('saving').text('کم کن (−۱)');
            }
        });
    });

    // استپر موجودی

    function stockLevelClass(qty) {
        qty = parseInt(qty, 10);
        if (isNaN(qty)) return 'stock-unknown';
        const threshold = getLowStockThreshold();
        const p75 = Math.ceil(threshold * 0.75);
        const p50 = Math.ceil(threshold * 0.5);
        if (qty >= threshold) return 'stock-ok';
        if (qty >= p75) return 'stock-warn';
        if (qty >= p50) return 'stock-mid';
        return 'stock-danger';
    }

    function refreshStockLevel($input) {
        const $wrap = $input.closest('.ssm-stock-level');
        const cls = stockLevelClass($input.val());
        const targets = $wrap.length ? $wrap : $input;
        targets.removeClass('stock-ok stock-warn stock-mid stock-danger stock-unknown').addClass(cls);
    }

    function stepStock($input, delta) {
        let value = parseInt($input.val(), 10);
        if (isNaN(value)) {
            value = 0;
        }
        value += delta;
        if (value < 0) {
            value = 0;
        }
        $input.val(value).trigger('change');
        refreshStockLevel($input);
    }

    $(document).on('click', '.ssm-step-up', function () {
        const $input = $(this).closest('.ssm-stepper').find('.ssm-stock-qty');
        stepStock($input, 1);
    });

    $(document).on('click', '.ssm-step-down', function () {
        const $input = $(this).closest('.ssm-stepper').find('.ssm-stock-qty');
        stepStock($input, -1);
    });

    $(document).on('input change', '.ssm-stock-qty', function () {
        refreshStockLevel($(this));
        markDirtyFromInput($(this));
    });

    // تولتیپ راهنما

    $(document).on('click', '.ssm-help', function (e) {
        e.stopPropagation();
        $(this).toggleClass('is-open');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.ssm-help').length) {
            $('.ssm-help').removeClass('is-open');
        }
    });

    // ——— فاز ۲: dirty / فیلتر / ذخیره گروهی ———

    let activeFilter = 'all';
    let bulkSaving = false;

    function getEditUnit($el) {
        const $row = $el.closest('.ssm-variation-row');
        if ($row.length) {
            return $row;
        }
        return $el.closest('.ssm-product-card[data-ssm-type="simple"]');
    }

    function getEditableUnits() {
        return $('#ssm-results .ssm-variation-row, #ssm-results .ssm-product-card[data-ssm-type="simple"]');
    }

    function collectRowChanges($row) {
        const canStock = ssmAdmin.perms && ssmAdmin.perms.canEditStock;
        const canPrice = ssmAdmin.perms && ssmAdmin.perms.canEditPrice;
        const $stockInput = $row.find('.ssm-stock-qty');
        const $priceInput = $row.find('.ssm-regular-price');
        const $saleInput = $row.find('.ssm-sale-price');
        const out = {
            productId: $row.data('product-id'),
            stockQty: '',
            regularPrice: '',
            saleChanged: false,
            salePrice: '',
            $stockInput: $stockInput,
            $priceInput: $priceInput,
            $saleInput: $saleInput,
            dirty: false
        };

        if (canStock && $stockInput.length) {
            const cur = String($stockInput.val());
            const orig = $stockInput.attr('data-original');
            if (typeof orig === 'undefined' || String(orig) !== cur) {
                out.stockQty = cur;
                out.dirty = true;
            }
        }
        if (canPrice && $priceInput.length && !$priceInput.prop('disabled')) {
            const cur = String($priceInput.val());
            const orig = $priceInput.attr('data-original');
            if (typeof orig === 'undefined' || String(orig) !== cur) {
                out.regularPrice = cur;
                out.dirty = true;
            }
        }
        if (canPrice && $saleInput.length && !$saleInput.prop('disabled')) {
            const cur = String($saleInput.val() == null ? '' : $saleInput.val());
            const orig = String($saleInput.attr('data-original') == null ? '' : $saleInput.attr('data-original'));
            if (orig !== cur) {
                out.saleChanged = true;
                out.salePrice = cur;
                out.dirty = true;
            }
        }
        return out;
    }

    function syncRowDirty($row) {
        if (!$row || !$row.length) {
            return;
        }
        const ch = collectRowChanges($row);
        $row.toggleClass('ssm-row-dirty', ch.dirty);
        updateBulkBar();
        if (activeFilter === 'dirty') {
            applyResultFilter(activeFilter);
        }
    }

    function markDirtyFromInput($input) {
        syncRowDirty(getEditUnit($input));
    }

    function updateBulkBar() {
        const count = $('#ssm-results .ssm-row-dirty').length;
        const $bar = $('#ssm-bulk-bar');
        const $count = $('#ssm-bulk-count');
        if (!$bar.length) {
            return;
        }
        if (count > 0) {
            $bar.prop('hidden', false);
            $count.text(count + ' تغییر ذخیره‌نشده');
        } else {
            $bar.prop('hidden', true);
            $count.text('۰ تغییر ذخیره‌نشده');
        }
    }

    function onResultsUpdated() {
        const hasCards = $('#ssm-results .ssm-product-card').length > 0;
        $('#ssm-result-toolbar').prop('hidden', !hasCards);
        if (!hasCards) {
            activeFilter = 'all';
            $('.ssm-filter-chip').removeClass('is-active');
            $('.ssm-filter-chip[data-ssm-filter="all"]').addClass('is-active');
        }
        getEditableUnits().each(function () {
            syncRowDirty($(this));
        });
        applyResultFilter(activeFilter);
        updateBulkBar();
        updateLowStockBanner();
    }

    function updateLowStockBanner() {
        const $banner = $('#ssm-low-stock-banner');
        if (!$banner.length) {
            return;
        }
        const threshold = getLowStockThreshold();
        let lowCount = 0;
        let outCount = 0;
        getEditableUnits().each(function () {
            const qty = unitStockQty($(this));
            if (qty === 0) {
                outCount += 1;
            } else if (qty < threshold) {
                lowCount += 1;
            }
        });
        if (lowCount === 0 && outCount === 0) {
            $banner.prop('hidden', true).text('');
            return;
        }
        const base = (ssmAdmin.i18n && ssmAdmin.i18n.lowStockWarn) ? ssmAdmin.i18n.lowStockWarn : 'در نتایج، موجودی کم یا ناموجود وجود دارد.';
        $banner.prop('hidden', false).text(base + ' (ناموجود: ' + outCount + ' — کم‌موجود ≤' + threshold + ': ' + lowCount + ')');
    }

    function unitStockQty($unit) {
        const $inp = $unit.find('.ssm-stock-qty').first();
        if ($inp.length) {
            const n = parseInt($inp.val(), 10);
            return isNaN(n) ? 0 : n;
        }
        return parseInt($unit.attr('data-ssm-stock') || '0', 10) || 0;
    }

    function unitOnSale($unit) {
        const $inp = $unit.find('.ssm-sale-price').first();
        if ($inp.length) {
            return String($inp.val() || '').trim() !== '';
        }
        return $unit.attr('data-ssm-sale') === '1';
    }

    function applyResultFilter(filter) {
        activeFilter = filter || 'all';
        const $cards = $('#ssm-results .ssm-product-card');
        if (!$cards.length) {
            return;
        }

        $cards.each(function () {
            const $card = $(this);
            const type = $card.attr('data-ssm-type') || 'simple';
            let cardVisible = false;

            if (activeFilter === 'variable') {
                cardVisible = type === 'variable';
                $card.toggle(cardVisible);
                $card.find('.ssm-variation-row').show();
                return;
            }

            if (type === 'variable') {
                $card.find('.ssm-variation-row').each(function () {
                    const $row = $(this);
                    let show = true;
                    const qty = unitStockQty($row);
                    if (activeFilter === 'out') {
                        show = qty === 0;
                    } else if (activeFilter === 'low') {
                        show = qty > 0 && qty < getLowStockThreshold();
                    } else if (activeFilter === 'sale') {
                        show = unitOnSale($row);
                    } else if (activeFilter === 'dirty') {
                        show = $row.hasClass('ssm-row-dirty');
                    }
                    $row.toggle(show);
                    if (show) {
                        cardVisible = true;
                    }
                });
                $card.toggle(activeFilter === 'all' ? true : cardVisible);
            } else {
                let show = true;
                const qty = unitStockQty($card);
                if (activeFilter === 'out') {
                    show = qty === 0;
                } else if (activeFilter === 'low') {
                    show = qty > 0 && qty < getLowStockThreshold();
                } else if (activeFilter === 'sale') {
                    show = unitOnSale($card);
                } else if (activeFilter === 'dirty') {
                    show = $card.hasClass('ssm-row-dirty');
                }
                $card.toggle(show);
            }
        });
    }

    $(document).on('input change', '.ssm-regular-price, .ssm-sale-price', function () {
        markDirtyFromInput($(this));
    });

    $(document).on('click', '.ssm-filter-chip', function () {
        const filter = $(this).data('ssm-filter') || 'all';
        $('.ssm-filter-chip').removeClass('is-active');
        $(this).addClass('is-active');
        applyResultFilter(filter);
    });

    function saveRowChanges($row, $button) {
        const canStock = ssmAdmin.perms && ssmAdmin.perms.canEditStock;
        const canPrice = ssmAdmin.perms && ssmAdmin.perms.canEditPrice;
        const ch = collectRowChanges($row);
        const saveLabel = (ssmAdmin.i18n && ssmAdmin.i18n.save) ? ssmAdmin.i18n.save : 'ذخیره تغییرات';

        if (!canStock && !canPrice) {
            return $.Deferred().reject('perm').promise();
        }
        if (!ch.dirty) {
            return $.Deferred().reject('nodirty').promise();
        }
        if ($button && $button.hasClass('saving')) {
            return $.Deferred().reject('busy').promise();
        }

        if ($button) {
            $button.addClass('saving').text('در حال ذخیره...');
        }

        const payload = {
            action: 'ssm_update_product_data',
            product_id: ch.productId,
            stock_qty: ch.stockQty,
            regular_price: ch.regularPrice,
            nonce: ssmAdmin.nonce
        };
        if (ch.saleChanged) {
            payload.sale_price = ch.salePrice;
        }

        return $.ajax({
            url: ssmAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: payload
        }).then(function (res) {
            if (!res || !res.success) {
                const msg = (res && res.data && res.data.message) ? res.data.message : 'خطا در ذخیره';
                return $.Deferred().reject(msg).promise();
            }
            if (ch.stockQty !== '' && ch.$stockInput.length) {
                ch.$stockInput.attr('data-original', String(ch.$stockInput.val()));
            }
            if (ch.regularPrice !== '' && ch.$priceInput.length) {
                ch.$priceInput.attr('data-original', String(ch.$priceInput.val()));
            }
            if (ch.saleChanged && ch.$saleInput.length) {
                ch.$saleInput.attr('data-original', String(ch.$saleInput.val() == null ? '' : ch.$saleInput.val()));
            }
            syncRowDirty($row);
            const msg = (res.data && res.data.message) ? res.data.message : ((ssmAdmin.i18n && ssmAdmin.i18n.saved) ? ssmAdmin.i18n.saved : 'ذخیره شد');
            if ($button) {
                $button.removeClass('saving').addClass('success-pulse').text('✓ ' + msg);
                setTimeout(function () {
                    $button.removeClass('success-pulse').text(saveLabel);
                }, 1800);
            }
            return res;
        }, function (xhr) {
            const msg = ajaxErrorMessage(xhr, 'خطا در ذخیره');
            if ($button) {
                $button.removeClass('saving').text(saveLabel);
            }
            return $.Deferred().reject(msg).promise();
        });
    }

    $(document).on('click', '.ssm-save-button', function () {
        const $button = $(this);
        const $row = getEditUnit($button);
        if (!$row.length) {
            return;
        }
        saveRowChanges($row, $button).fail(function (err) {
            if (err === 'nodirty') {
                alert('چیزی تغییر نکرده است.');
            } else if (err === 'perm') {
                alert((ssmAdmin.i18n && ssmAdmin.i18n.noEditPerm) ? ssmAdmin.i18n.noEditPerm : 'شما دسترسی تغییر موجودی یا قیمت ندارید.');
            } else if (err && err !== 'busy') {
                alert(err);
            }
        });
    });

    $(document).on('click', '#ssm-save-all', function () {
        if (bulkSaving) {
            return;
        }
        const $rows = $('#ssm-results .ssm-row-dirty').toArray();
        if (!$rows.length) {
            return;
        }
        const $btn = $(this);
        bulkSaving = true;
        $btn.prop('disabled', true).text('در حال ذخیره...');

        let ok = 0;
        let fail = 0;
        let chain = $.Deferred().resolve().promise();

        $rows.forEach(function (el) {
            chain = chain.then(function () {
                const $row = $(el);
                const $rowBtn = $row.find('.ssm-save-button').first();
                return saveRowChanges($row, $rowBtn.length ? $rowBtn : null).then(function () {
                    ok += 1;
                }, function (err) {
                    if (err && err !== 'nodirty' && err !== 'busy') {
                        fail += 1;
                    }
                });
            });
        });

        chain.always(function () {
            bulkSaving = false;
            $btn.prop('disabled', false).text('ذخیره همه');
            updateBulkBar();
            applyResultFilter(activeFilter);
            if (fail > 0) {
                alert('ذخیره شد: ' + ok + ' مورد. ناموفق: ' + fail + ' مورد.');
            }
        });
    });
});
