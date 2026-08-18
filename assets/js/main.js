(() => {
	"use strict";

	const header = document.querySelector("[data-header]");
	const menuToggle = document.querySelector(".menu-toggle");
	const mobileMenu = document.querySelector(".mobile-menu");
	const dropdown = document.querySelector(".desktop-nav__dropdown");
	const dropdownTrigger = dropdown?.querySelector(".desktop-nav__trigger");
	const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	const configuredMarqueeSpeed = Number.parseFloat(window.parisacropConfig?.marqueeSpeed);
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

	if (prefersReducedMotion || typeof window.gsap === "undefined") return;

	const { gsap } = window;
	gsap.registerPlugin(window.ScrollTrigger);

	const hero = document.querySelector(".hero");
	if (hero) {
		const heroTimeline = gsap.timeline({ defaults: { ease: "power3.out" } });
		heroTimeline
			.from(".hero__media img", { autoAlpha: 0, scale: 1.06, duration: 1.35 })
			.from(".hero__copy", { autoAlpha: 0, y: 30, scale: 0.97, duration: 0.7 }, "-=0.72")
			.from(".hero__payment-logos", { autoAlpha: 0, y: 18, duration: 0.52 }, "-=0.42")
			.from(".hero__title", { autoAlpha: 0, y: 24, duration: 0.62 }, "-=0.36")
			.from(".hero__tagline", { autoAlpha: 0, y: 18, duration: 0.5 }, "-=0.35")
			.from(".hero .pc-button", { autoAlpha: 0, y: 14, scale: 0.94, duration: 0.48 }, "-=0.28");

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

	const marquee = document.querySelector("[data-product-marquee]");
	const marqueeTrack = marquee?.querySelector(".product-marquee__track");
	let marqueeTween;
	let resizeTimer;

	const createMarquee = () => {
		if (!marquee || !marqueeTrack) return;
		marqueeTween?.kill();
		gsap.set(marqueeTrack, { x: 0 });
		const cards = [...marqueeTrack.children];
		const duplicateStart = cards[cards.length / 2];
		const distance = cards[0] && duplicateStart
			? Math.abs(duplicateStart.offsetLeft - cards[0].offsetLeft)
			: marqueeTrack.scrollWidth / 2;
		if (distance <= 0) return;

		marqueeTween = gsap.to(marqueeTrack, {
			x: -distance,
			duration: distance / marqueeSpeed,
			ease: "none",
			repeat: -1,
		});
	};

	if (marquee) {
		createMarquee();
		marquee.addEventListener("mouseenter", () => marqueeTween?.pause());
		marquee.addEventListener("mouseleave", () => marqueeTween?.resume());
		marquee.addEventListener("focusin", () => marqueeTween?.pause());
		marquee.addEventListener("focusout", () => marqueeTween?.resume());

		window.addEventListener(
			"resize",
			() => {
				window.clearTimeout(resizeTimer);
				resizeTimer = window.setTimeout(createMarquee, 180);
			},
			{ passive: true }
		);
	}
})();
