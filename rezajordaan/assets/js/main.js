(() => {
	"use strict";

	const header = document.querySelector("[data-header]");
	const menuToggle = document.querySelector(".menu-toggle");
	const mobileMenu = document.querySelector(".mobile-menu");
	const dropdown = document.querySelector(".desktop-nav__dropdown");
	const dropdownTrigger = dropdown?.querySelector(".desktop-nav__trigger");
	const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	const configuredMarqueeSpeed = Number.parseFloat(window.rezajordaanConfig?.marqueeSpeed);
	const marqueeSpeed = Number.isFinite(configuredMarqueeSpeed) ? configuredMarqueeSpeed : 42;

	const setHeaderState = () => {
		header?.classList.toggle("is-scrolled", window.scrollY > 24);
	};

	const closeMobileMenu = () => {
		if (!menuToggle || !mobileMenu) return;
		menuToggle.setAttribute("aria-expanded", "false");
		menuToggle.setAttribute("aria-label", "باز کردن منو");
		mobileMenu.setAttribute("aria-hidden", "true");
		mobileMenu.classList.remove("is-open");
		document.body.classList.remove("menu-open");
	};

	const closeDropdown = () => {
		if (!dropdown || !dropdownTrigger) return;
		dropdown.classList.remove("is-open");
		dropdownTrigger.setAttribute("aria-expanded", "false");
	};

	window.addEventListener("scroll", setHeaderState, { passive: true });
	setHeaderState();

	menuToggle?.addEventListener("click", () => {
		const isOpen = menuToggle.getAttribute("aria-expanded") === "true";
		menuToggle.setAttribute("aria-expanded", String(!isOpen));
		menuToggle.setAttribute("aria-label", isOpen ? "باز کردن منو" : "بستن منو");
		mobileMenu?.setAttribute("aria-hidden", String(isOpen));
		mobileMenu?.classList.toggle("is-open", !isOpen);
		document.body.classList.toggle("menu-open", !isOpen);
	});

	mobileMenu?.querySelectorAll("a").forEach((link) => {
		link.addEventListener("click", closeMobileMenu);
	});

	dropdownTrigger?.addEventListener("click", () => {
		const isOpen = dropdownTrigger.getAttribute("aria-expanded") === "true";
		dropdown?.classList.toggle("is-open", !isOpen);
		dropdownTrigger.setAttribute("aria-expanded", String(!isOpen));
	});

	document.addEventListener("click", (event) => {
		if (dropdown && !dropdown.contains(event.target)) closeDropdown();
	});

	document.addEventListener("keydown", (event) => {
		if (event.key !== "Escape") return;
		closeDropdown();
		closeMobileMenu();
	});

	document.querySelectorAll('a[href="#"]').forEach((link) => {
		link.addEventListener("click", (event) => event.preventDefault());
	});

	const paymentLogos = [...document.querySelectorAll("[data-payment-logo]")];
	if (paymentLogos.length) {
		let activePaymentLogo = 0;
		const showPaymentLogo = (index) => {
			paymentLogos.forEach((logo, logoIndex) => {
				const isActive = logoIndex === index;
				logo.classList.toggle("is-active", isActive);
				logo.setAttribute("aria-hidden", String(!isActive));
			});
		};

		showPaymentLogo(activePaymentLogo);
		if (!prefersReducedMotion) {
			window.setInterval(() => {
				activePaymentLogo = (activePaymentLogo + 1) % paymentLogos.length;
				showPaymentLogo(activePaymentLogo);
			}, 2600);
		}
	}

	document.querySelectorAll("[data-price-range]").forEach((priceFilter) => {
		const minRange = priceFilter.querySelector("[data-price-min-range]");
		const maxRange = priceFilter.querySelector("[data-price-max-range]");
		const minInput = priceFilter.querySelector("[data-price-min-input]");
		const maxInput = priceFilter.querySelector("[data-price-max-input]");
		const slider = priceFilter.querySelector(".archive-filters__slider");

		if (!minRange || !maxRange || !minInput || !maxInput || !slider) return;

		const floor = Number(minRange.min);
		const ceiling = Number(maxRange.max);
		const span = Math.max(1, ceiling - floor);
		const clamp = (value) => Math.min(ceiling, Math.max(floor, Number(value)));

		const render = () => {
			let minValue = clamp(minRange.value);
			let maxValue = clamp(maxRange.value);

			if (minValue > maxValue) {
				[minValue, maxValue] = [maxValue, minValue];
			}

			minRange.value = String(minValue);
			maxRange.value = String(maxValue);
			minInput.value = String(minValue);
			maxInput.value = String(maxValue);
			slider.style.setProperty("--price-start", `${((minValue - floor) / span) * 100}%`);
			slider.style.setProperty("--price-end", `${((maxValue - floor) / span) * 100}%`);
		};

		minRange.addEventListener("input", render);
		maxRange.addEventListener("input", render);
		minInput.addEventListener("change", () => {
			minRange.value = minInput.value;
			render();
		});
		maxInput.addEventListener("change", () => {
			maxRange.value = maxInput.value;
			render();
		});
		render();
	});

	const initProductMarquee = () => {
		document.querySelectorAll("[data-product-marquee]").forEach((marquee) => {
			const track = marquee.querySelector(".product-marquee__track");
			if (!track || prefersReducedMotion) {
				return;
			}

			const speedValue = Number.parseFloat(marquee.dataset.speed);
			const speed = Number.isFinite(speedValue) && speedValue > 0 ? speedValue : marqueeSpeed;
			let resizeTimer;

			const applyDuration = () => {
				const distance = track.scrollWidth / 2;
				if (distance <= 0) {
					return;
				}

				const duration = Math.max(8, distance / speed);
				track.style.setProperty("--rj-marquee-duration", `${duration}s`);
				marquee.classList.add("is-animated");
			};

			applyDuration();
			marquee.addEventListener("mouseenter", () => {
				track.style.animationPlayState = "paused";
			});
			marquee.addEventListener("mouseleave", () => {
				track.style.animationPlayState = "running";
			});
			marquee.addEventListener("focusin", () => {
				track.style.animationPlayState = "paused";
			});
			marquee.addEventListener("focusout", () => {
				track.style.animationPlayState = "running";
			});
			window.addEventListener(
				"resize",
				() => {
					window.clearTimeout(resizeTimer);
					resizeTimer = window.setTimeout(applyDuration, 180);
				},
				{ passive: true }
			);
		});
	};

	initProductMarquee();

	const loadMoreWrap = document.querySelector("[data-load-more]");
	const loadMoreButton = loadMoreWrap?.querySelector("[data-load-more-button]");
	const productGrid = document.querySelector(".rezajordaan-archive-cards ul.products");
	if (loadMoreWrap && loadMoreButton && productGrid) {
		let config = {};
		try {
			config = JSON.parse(loadMoreButton.getAttribute("data-load-more-config") || "{}");
		} catch (_error) {
			config = {};
		}

		const ajaxUrl = window.rezajordaanConfig?.ajaxUrl;
		const nonce = window.rezajordaanConfig?.loadMoreNonce;
		const busyLabel = window.rezajordaanConfig?.loadMoreBusy || "در حال بارگذاری…";
		const doneLabel = window.rezajordaanConfig?.loadMoreDone || "همه محصولات نمایش داده شد";
		const idleLabel = loadMoreButton.querySelector("span")?.textContent || "بارگذاری بیشتر";

		loadMoreButton.addEventListener("click", async () => {
			if (loadMoreButton.dataset.busy === "1" || !ajaxUrl || !nonce) return;

			const nextPage = Number(config.page || 1) + 1;
			loadMoreButton.dataset.busy = "1";
			loadMoreButton.disabled = true;
			const label = loadMoreButton.querySelector("span");
			if (label) label.textContent = busyLabel;

			const body = new URLSearchParams();
			body.set("action", "rezajordaan_load_more_products");
			body.set("nonce", nonce);
			body.set("page", String(nextPage));
			body.set("max", String(config.max || 1));
			body.set("taxonomy", config.taxonomy || "");
			body.set("term", String(config.term || 0));
			body.set("orderby", config.orderby || "");
			body.set("min_price", config.min_price || "");
			body.set("max_price", config.max_price || "");
			body.set("search", config.search || "");
			(Array.isArray(config.sizes) ? config.sizes : []).forEach((sizeId) => {
				body.append("sizes[]", String(sizeId));
			});

			try {
				const response = await fetch(ajaxUrl, {
					method: "POST",
					credentials: "same-origin",
					headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
					body,
				});
				const payload = await response.json();
				const html = payload?.data?.html || "";
				const hasMore = Boolean(payload?.success && payload?.data?.hasMore && html);

				if (html) {
					productGrid.insertAdjacentHTML("beforeend", html);
					config.page = nextPage;
					loadMoreButton.setAttribute("data-load-more-config", JSON.stringify(config));
				}

				if (hasMore) {
					if (label) label.textContent = idleLabel;
					loadMoreButton.disabled = false;
					loadMoreButton.dataset.busy = "0";
					return;
				}

				if (label) label.textContent = doneLabel;
				loadMoreWrap.classList.add("is-complete");
			} catch (_error) {
				if (label) label.textContent = idleLabel;
				loadMoreButton.disabled = false;
			} finally {
				loadMoreButton.dataset.busy = "0";
			}
		});
	}

	document.querySelectorAll(".woocommerce-product-gallery").forEach((gallery) => {
		const track = gallery.querySelector(".woocommerce-product-gallery__wrapper, .flex-viewport .slides");
		const thumbs = gallery.querySelector(".flex-control-thumbs");

		if (track) {
			track.setAttribute("tabindex", "0");
			track.addEventListener(
				"wheel",
				(event) => {
					if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;
					event.preventDefault();
					track.scrollLeft += event.deltaY;
				},
				{ passive: false }
			);
		}

		thumbs?.querySelectorAll("li").forEach((thumb) => {
			thumb.addEventListener("click", () => {
				thumb.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
			});
		});
	});

	if (prefersReducedMotion || typeof window.gsap === "undefined" || typeof window.ScrollTrigger === "undefined") {
		return;
	}

	const { gsap } = window;
	if (typeof gsap.registerPlugin === "function") {
		gsap.registerPlugin(window.ScrollTrigger);
	}

	const hero = document.querySelector(".hero");
	if (hero) {
		const heroTimeline = gsap.timeline({ defaults: { ease: "power3.out" } });
		heroTimeline
			.from(".hero__media img", { autoAlpha: 0, scale: 1.06, duration: 1.35 })
			.from(".hero__copy", { autoAlpha: 0, y: 30, scale: 0.97, duration: 0.7 }, "-=0.72")
			.from(".hero__payment-logos", { autoAlpha: 0, y: 18, duration: 0.52 }, "-=0.42")
			.from(".hero__title", { autoAlpha: 0, y: 24, duration: 0.62 }, "-=0.36")
			.from(".hero__tagline", { autoAlpha: 0, y: 18, duration: 0.5 }, "-=0.35")
			.from(".hero .rj-button", { autoAlpha: 0, y: 14, scale: 0.94, duration: 0.48 }, "-=0.28");

		if (document.querySelector(".product-search__form")) {
			heroTimeline.from(".product-search__form", { autoAlpha: 0, y: 20, duration: 0.55 }, "-=0.2");
		}
	}

	gsap.utils.toArray(".section-heading").forEach((heading) => {
		gsap.from(heading.children, {
			autoAlpha: 0,
			y: 28,
			duration: 0.7,
			stagger: 0.1,
			ease: "power3.out",
			scrollTrigger: {
				trigger: heading,
				start: "top 84%",
				once: true,
			},
		});
	});

	if (document.querySelector(".category-grid")) {
		gsap.from(".category-card", {
			autoAlpha: 0,
			y: 58,
			rotate: 2,
			duration: 0.78,
			stagger: 0.1,
			ease: "power3.out",
			scrollTrigger: {
				trigger: ".category-grid",
				start: "top 82%",
				once: true,
			},
		});
	}

	if (document.querySelector(".benefit-grid")) {
		gsap.from(".benefit-card", {
			autoAlpha: 0,
			y: 50,
			scale: 0.96,
			duration: 0.7,
			stagger: 0.1,
			ease: "back.out(1.3)",
			scrollTrigger: {
				trigger: ".benefit-grid",
				start: "top 82%",
				once: true,
			},
		});
	}
})();
