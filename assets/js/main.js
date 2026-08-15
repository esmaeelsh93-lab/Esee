(() => {
	"use strict";

	const header = document.querySelector("[data-header]");
	const menuToggle = document.querySelector(".menu-toggle");
	const mobileMenu = document.querySelector(".mobile-menu");
	const dropdown = document.querySelector(".desktop-nav__dropdown");
	const dropdownTrigger = dropdown?.querySelector(".desktop-nav__trigger");
	const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

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

	if (prefersReducedMotion || typeof window.gsap === "undefined") return;

	const { gsap } = window;
	gsap.registerPlugin(window.ScrollTrigger);

	const heroTimeline = gsap.timeline({ defaults: { ease: "power3.out" } });
	heroTimeline
		.from(".hero__kicker", { autoAlpha: 0, x: 34, duration: 0.65 })
		.from(".hero__title-line", { autoAlpha: 0, y: 55, rotateX: -18, duration: 0.9 }, "-=0.35")
		.from(".hero__title-script", { autoAlpha: 0, scale: 0.72, rotate: -18, duration: 0.85 }, "-=0.5")
		.from(".hero__tagline", { autoAlpha: 0, y: 22, duration: 0.55 }, "-=0.42")
		.from(".hero__description", { autoAlpha: 0, y: 18, duration: 0.5 }, "-=0.36")
		.from(".hero .pc-button", { autoAlpha: 0, y: 16, duration: 0.48 }, "-=0.28")
		.from(".hero__halo", { autoAlpha: 0, scale: 0.72, duration: 1.1 }, 0.15)
		.from(".hero__hanger", { autoAlpha: 0, y: -90, rotate: -7, duration: 1.25, ease: "elastic.out(1, 0.6)" }, 0.25)
		.from(".hero__ribbon", { autoAlpha: 0, y: 30, rotate: 8, duration: 0.7 }, 0.8)
		.from(".hero__sparkles span, .hero__heart", { autoAlpha: 0, scale: 0, stagger: 0.09, duration: 0.45 }, 0.75);

	gsap.to(".hero__spotlight", {
		xPercent: 30,
		opacity: 0.35,
		duration: 4.5,
		ease: "sine.inOut",
		repeat: -1,
		yoyo: true,
	});

	gsap.to(".hero__hanger", {
		y: 8,
		rotate: -0.7,
		duration: 3.4,
		ease: "sine.inOut",
		repeat: -1,
		yoyo: true,
	});

	gsap.to(".hero__heart--one", {
		y: -13,
		rotate: 7,
		duration: 2.3,
		ease: "sine.inOut",
		repeat: -1,
		yoyo: true,
	});

	gsap.to(".hero__heart--two", {
		y: 10,
		rotate: -8,
		duration: 2.8,
		ease: "sine.inOut",
		repeat: -1,
		yoyo: true,
	});

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
			duration: distance / 42,
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
