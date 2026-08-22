(function ($) {
	"use strict";

	if (typeof $ === "undefined") {
		return;
	}

	var refreshTimer = null;
	var retryTimer = null;
	var addressSelector =
		"#billing_state, #shipping_state, #billing_city, #shipping_city, #billing_pws_city, #shipping_pws_city, #billing_pws_district, #shipping_pws_district, select[name='billing_city'], select[name='shipping_city'], select[name='billing_state'], select[name='shipping_state'], select[name='billing_pws_city'], select[name='shipping_pws_city']";

	function refreshCheckoutShipping() {
		if (!$("form.checkout").length) {
			return;
		}

		window.clearTimeout(refreshTimer);
		refreshTimer = window.setTimeout(function () {
			$(document.body).trigger("update_checkout");
		}, 300);
	}

	function countShippingMethods() {
		return $("#shipping_method li:visible, .woocommerce-shipping-methods li:visible").length;
	}

	function ensureShippingVisible() {
		$("#shipping_method, .woocommerce-shipping-methods").css({
			display: "block",
			maxHeight: "none",
			overflow: "visible",
			visibility: "visible",
		});

		$("#shipping_method li, .woocommerce-shipping-methods li").css({
			display: "block",
			height: "auto",
			maxHeight: "none",
			opacity: "1",
			overflow: "visible",
			visibility: "visible",
		});
	}

	function retryShippingRefresh() {
		if (!$("form.checkout").length) {
			return;
		}

		window.clearInterval(retryTimer);
		var attempts = 0;

		retryTimer = window.setInterval(function () {
			attempts += 1;
			ensureShippingVisible();

			if (countShippingMethods() > 1 || attempts >= 6) {
				window.clearInterval(retryTimer);
				return;
			}

			$(document.body).trigger("update_checkout");
		}, 900);
	}

	$(document.body).on("change input", addressSelector, function () {
		refreshCheckoutShipping();
		retryShippingRefresh();
	});

	$(document.body).on("select2:select select2:clear", addressSelector, function () {
		refreshCheckoutShipping();
		retryShippingRefresh();
	});

	$(document.body).on("updated_checkout", function () {
		ensureShippingVisible();
	});

	document.addEventListener("visibilitychange", function () {
		if (document.visibilityState === "visible") {
			refreshCheckoutShipping();
			ensureShippingVisible();
		}
	});

	$(function () {
		refreshCheckoutShipping();
		ensureShippingVisible();
		retryShippingRefresh();
	});
})(window.jQuery);
