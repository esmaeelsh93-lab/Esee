(() => {
	"use strict";

	const config = window.rezajordaanWishlist || {};
	const cookieName = "rj_wishlist";

	const readCookieIds = () => {
		const match = document.cookie.match(/(?:^|; )rj_wishlist=([^;]*)/);
		if (!match) return [];
		return decodeURIComponent(match[1])
			.split(/[\s,]+/)
			.map((id) => Number.parseInt(id, 10))
			.filter((id) => Number.isFinite(id) && id > 0);
	};

	const writeCookieIds = (ids) => {
		const unique = [...new Set(ids.filter((id) => id > 0))];
		const maxAge = 60 * 60 * 24 * 365;
		document.cookie = `${cookieName}=${encodeURIComponent(unique.join(","))}; path=/; max-age=${maxAge}; SameSite=Lax`;
		return unique;
	};

	let ids = Array.isArray(config.ids) && config.ids.length
		? config.ids.map((id) => Number(id)).filter((id) => id > 0)
		: readCookieIds();

	const syncCount = () => {
		document.querySelectorAll("[data-wishlist-count]").forEach((el) => {
			el.textContent = String(ids.length);
		});
		document.querySelectorAll(".rj-header-wishlist").forEach((link) => {
			link.classList.toggle("is-active", ids.length > 0);
		});
	};

	const syncButtons = () => {
		document.querySelectorAll("[data-wishlist-toggle]").forEach((button) => {
			const productId = Number.parseInt(button.getAttribute("data-product-id") || "", 10);
			const active = ids.includes(productId);
			button.classList.toggle("is-active", active);
			button.setAttribute("aria-pressed", active ? "true" : "false");
			const label = active ? (config.i18n?.remove || "حذف از علاقه‌مندی") : (config.i18n?.add || "افزودن به علاقه‌مندی");
			button.setAttribute("aria-label", label);
			const labelEl = button.querySelector("[data-wishlist-label]");
			if (labelEl) labelEl.textContent = label;
		});
	};

	const applyLocalToggle = (productId) => {
		const index = ids.indexOf(productId);
		let inWishlist = false;
		if (index >= 0) {
			ids.splice(index, 1);
			inWishlist = false;
		} else {
			ids.unshift(productId);
			inWishlist = true;
		}
		ids = writeCookieIds(ids);
		syncCount();
		syncButtons();
		return inWishlist;
	};

	const toggleViaAjax = async (productId) => {
		if (!config.ajaxUrl || !config.nonce) {
			return applyLocalToggle(productId);
		}

		const body = new URLSearchParams();
		body.set("action", "rezajordaan_wishlist_toggle");
		body.set("nonce", config.nonce);
		body.set("product_id", String(productId));

		const response = await fetch(config.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
			body,
		});

		const payload = await response.json();
		if (!payload?.success) {
			throw new Error(payload?.data?.message || "wishlist error");
		}

		ids = writeCookieIds(Array.isArray(payload.data.ids) ? payload.data.ids : (
			payload.data.in_wishlist
				? [productId, ...ids.filter((id) => id !== productId)]
				: ids.filter((id) => id !== productId)
		));

		if (typeof payload.data.count === "number") {
			// Prefer server count when available.
			while (ids.length > payload.data.count) ids.pop();
		}

		syncCount();
		syncButtons();

		const page = document.querySelector("[data-wishlist-page]");
		if (page && !payload.data.in_wishlist) {
			const card = page.querySelector(`[data-product-id="${productId}"]`)?.closest("li.product");
			card?.remove();
			if (!page.querySelector("li.product")) {
				page.innerHTML = `<div class="rj-wishlist-empty"><p>${config.i18n?.empty || "لیست علاقه‌مندی خالی است."}</p></div>`;
			}
		}

		return Boolean(payload.data.in_wishlist);
	};

	document.addEventListener("click", async (event) => {
		const button = event.target.closest?.("[data-wishlist-toggle]");
		if (!button) return;

		event.preventDefault();
		event.stopPropagation();

		const productId = Number.parseInt(button.getAttribute("data-product-id") || "", 10);
		if (!productId) return;

		if (button.dataset.busy === "1") return;
		button.dataset.busy = "1";
		button.classList.add("is-busy");

		try {
			await toggleViaAjax(productId);
		} catch (_error) {
			applyLocalToggle(productId);
		} finally {
			button.dataset.busy = "0";
			button.classList.remove("is-busy");
		}
	});

	syncCount();
	syncButtons();
})();
