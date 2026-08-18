(function ($) {
	"use strict";

	if (typeof $ === "undefined") {
		return;
	}

	var refreshTimer = null;
	var addressSelector =
		"#billing_state, #shipping_state, #billing_city, #shipping_city, #billing_pws_city, #shipping_pws_city, select[name='billing_city'], select[name='shipping_city'], select[name='billing_state'], select[name='shipping_state'], #billing_pws_district, #shipping_pws_district";

	function refreshCheckoutShipping() {
		if (!$("form.checkout").length) {
			return;
		}

		window.clearTimeout(refreshTimer);
		refreshTimer = window.setTimeout(function () {
			$(document.body).trigger("update_checkout");
		}, 350);
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
			opacity: "1",
			visibility: "visible",
		});
	}

	$(document.body).on("change input", addressSelector, refreshCheckoutShipping);

	// Select2 + mobile taps often miss the native change event.
	$(document.body).on("select2:select select2:clear touchend", addressSelector, refreshCheckoutShipping);

	$(document.body).on("updated_checkout", ensureShippingVisible);

	document.addEventListener("visibilitychange", function () {
		if (document.visibilityState === "visible") {
			refreshCheckoutShipping();
			ensureShippingVisible();
		}
	});

	$(function () {
		refreshCheckoutShipping();
		ensureShippingVisible();
	});
})(window.jQuery);
