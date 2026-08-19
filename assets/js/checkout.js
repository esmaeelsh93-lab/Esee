(function ($) {
	"use strict";

	if (typeof $ === "undefined") {
		return;
	}

	var refreshTimer = null;
	var addressSelector =
		"#billing_state, #shipping_state, #billing_city, #shipping_city, #billing_district, #shipping_district, #billing_pws_city, #shipping_pws_city, #billing_pws_district, #shipping_pws_district, select[name='billing_state'], select[name='shipping_state'], select[name='billing_city'], select[name='shipping_city'], select[name='billing_district'], select[name='shipping_district'], select[name='billing_pws_city'], select[name='shipping_pws_city'], select[name='billing_pws_district'], select[name='shipping_pws_district']";

	function scheduleCheckoutRefresh(delay) {
		if (!$("form.checkout").length) {
			return;
		}

		window.clearTimeout(refreshTimer);
		refreshTimer = window.setTimeout(function () {
			$(document.body).trigger("update_checkout");
		}, delay || 650);
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

	$(document.body).on("change select2:select select2:clear", addressSelector, function () {
		/*
		 * Persian shipping plugins populate city/district selects asynchronously.
		 * A single delayed refresh lets those requests finish and avoids a race
		 * where an older, partial address leaves only the default Post method.
		 */
		scheduleCheckoutRefresh(700);
	});

	$(document.body).on("updated_checkout", function () {
		ensureShippingVisible();
	});

	document.addEventListener("visibilitychange", function () {
		if (document.visibilityState === "visible") {
			ensureShippingVisible();
			scheduleCheckoutRefresh(250);
		}
	});

	$(function () {
		ensureShippingVisible();
	});
})(window.jQuery);
