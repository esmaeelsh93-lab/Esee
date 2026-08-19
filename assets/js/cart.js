(function ($) {
	"use strict";

	if (typeof $ === "undefined") {
		return;
	}

	function headerCartCount() {
		var countNode = document.querySelector("[data-cart-count]");
		if (!countNode) {
			return 0;
		}

		return Number.parseInt(countNode.textContent, 10) || 0;
	}

	function cartPageLooksEmpty() {
		return Boolean(document.querySelector(".cart-empty"));
	}

	function cartCookieHasItems() {
		var cookies = "";

		try {
			cookies = document.cookie || "";
		} catch (error) {
			return false;
		}

		var match = cookies.match(/(?:^|;\s*)woocommerce_items_in_cart=([^;]*)/);

		if (!match) {
			return false;
		}

		var value = match[1];

		try {
			value = decodeURIComponent(value);
		} catch (error) {
			/* use the raw cookie value */
		}

		return (Number.parseInt(value, 10) || 0) > 0;
	}

	function clearPendingCartFlag() {
		try {
			sessionStorage.removeItem("rj_pending_cart");
		} catch (error) {
			/* ignore */
		}
	}

	function recoverStaleMobileCartCache() {
		if (!document.body.classList.contains("rezajordaan-cart")) {
			return;
		}

		var pendingCart = false;

		try {
			pendingCart = Boolean(sessionStorage.getItem("rj_pending_cart"));
		} catch (error) {
			pendingCart = false;
		}

		var count = headerCartCount();
		var looksEmpty = cartPageLooksEmpty();
		var hasCartCookie = cartCookieHasItems();

		if ((pendingCart || count > 0 || hasCartCookie) && looksEmpty) {
			var url = new URL(window.location.href);

			if (!url.searchParams.has("rj_cart_sync")) {
				url.searchParams.set("rj_cart_sync", String(Date.now()));
				window.location.replace(url.toString());
				return;
			}

			clearPendingCartFlag();
		}

		if (!looksEmpty) {
			clearPendingCartFlag();
		}

		if (typeof wc_cart_fragments_params !== "undefined") {
			$(document.body).trigger("wc_fragment_refresh");
		}
	}

	$(function () {
		recoverStaleMobileCartCache();
	});

	window.addEventListener("pageshow", function (event) {
		if (event.persisted) {
			recoverStaleMobileCartCache();
		}
	});
})(window.jQuery);
