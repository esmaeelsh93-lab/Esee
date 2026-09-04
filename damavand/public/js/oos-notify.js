(function () {
	'use strict';

	var form = document.getElementById('shojaei-oos-notify-form');
	if (!form || typeof shojaeiSeoOos === 'undefined') {
		return;
	}

	var statusEl = document.getElementById('shojaei-oos-notify-status');
	var emailEl = document.getElementById('shojaei-oos-notify-email');
	var cookieKey = 'shojaei_oos_notify_' + (form.querySelector('[name="product_id"]') || {}).value;

	try {
		if (cookieKey && document.cookie.indexOf(cookieKey + '=1') !== -1) {
			if (statusEl) {
				statusEl.textContent = 'قبلاً برای این محصول ثبت‌نام کرده‌اید.';
			}
			form.querySelector('button[type="submit"]').disabled = true;
		}
	} catch (e) { /* ignore */ }

	form.addEventListener('submit', function (ev) {
		ev.preventDefault();
		var email = emailEl ? emailEl.value.trim() : '';
		var productId = form.querySelector('[name="product_id"]');
		var btn = form.querySelector('button[type="submit"]');
		if (!email || !productId) {
			return;
		}
		if (btn) {
			btn.disabled = true;
		}
		if (statusEl) {
			statusEl.textContent = 'در حال ثبت…';
		}

		var body = new FormData();
		body.append('action', 'shojaei_seo_oos_notify');
		body.append('nonce', shojaeiSeoOos.nonce);
		body.append('email', email);
		body.append('product_id', productId.value);

		fetch(shojaeiSeoOos.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (r) { return r.json(); })
			.then(function (res) {
				var msg = (res && res.data && res.data.message) ? res.data.message : 'خطا';
				if (statusEl) {
					statusEl.textContent = msg;
				}
				if (res && res.success) {
					try {
						document.cookie = cookieKey + '=1;path=/;max-age=' + (60 * 60 * 24 * 90);
					} catch (e2) { /* ignore */ }
				} else if (btn) {
					btn.disabled = false;
				}
			})
			.catch(function () {
				if (statusEl) {
					statusEl.textContent = 'ارتباط برقرار نشد.';
				}
				if (btn) {
					btn.disabled = false;
				}
			});
	});
})();
