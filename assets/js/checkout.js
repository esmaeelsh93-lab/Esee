(function ($) {
	"use strict";

	if (typeof $ === "undefined") {
		return;
	}

	var refreshTimer = null;

	function refreshCheckoutShipping() {
		if (!$("form.checkout").length) {
			return;
		}

		window.clearTimeout(refreshTimer);
		refreshTimer = window.setTimeout(function () {
			$(document.body).trigger("update_checkout");
		}, 350);
	}

	$(document.body).on(
		"change input",
		"#billing_state, #shipping_state, #billing_city, #shipping_city, #billing_pws_city, #shipping_pws_city, select[name='billing_city'], select[name='shipping_city'], select[name='billing_state'], select[name='shipping_state']",
		refreshCheckoutShipping
	);

	// Persian WooCommerce / PWS city lists often load asynchronously on mobile.
	$(document.body).on("updated_checkout", function () {
		$("#shipping_method, .woocommerce-shipping-methods").css({
			overflow: "visible",
			maxHeight: "none",
		});
	});

	$(function () {
		refreshCheckoutShipping();
	});
})(window.jQuery);
