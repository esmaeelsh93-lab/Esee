(() => {
  "use strict";

  const root = document.documentElement;

  function initializeTheme() {
    const toggle = document.querySelector(".theme-toggle");
    const colorMeta = document.querySelector('meta[name="theme-color"]');
    const mediaQuery = window.matchMedia?.("(prefers-color-scheme: dark)");
    const storageKey = "sabalan-theme";

    const readStoredTheme = () => {
      try {
        const value = window.localStorage.getItem(storageKey);
        return value === "light" || value === "dark" ? value : null;
      } catch {
        return null;
      }
    };

    let userTheme = readStoredTheme();

    const applyTheme = (theme) => {
      const isDark = theme === "dark";
      root.dataset.theme = theme;
      root.style.colorScheme = theme;

      if (toggle) {
        toggle.setAttribute("aria-pressed", String(isDark));
        toggle.setAttribute(
          "aria-label",
          isDark ? "فعال‌سازی حالت روز" : "فعال‌سازی حالت شب",
        );
        toggle.title = isDark ? "حالت روز" : "حالت شب";
      }

      if (colorMeta) {
        colorMeta.content = isDark ? "#161311" : "#fffaf4";
      }
    };

    applyTheme(userTheme || (mediaQuery?.matches ? "dark" : "light"));

    toggle?.addEventListener("click", () => {
      const nextTheme = root.dataset.theme === "dark" ? "light" : "dark";
      userTheme = nextTheme;

      try {
        window.localStorage.setItem(storageKey, nextTheme);
      } catch {
        // The theme still works when storage is unavailable or blocked.
      }

      applyTheme(nextTheme);
    });

    const handleSystemThemeChange = (event) => {
      if (!userTheme) {
        applyTheme(event.matches ? "dark" : "light");
      }
    };

    if (mediaQuery?.addEventListener) {
      mediaQuery.addEventListener("change", handleSystemThemeChange);
    } else {
      mediaQuery?.addListener?.(handleSystemThemeChange);
    }
  }

  function initializeWarehouse() {
    const card = document.querySelector("#warehouseCard");
    const button = document.querySelector("#organizeButton");
    if (!card || !button) return;

    const status = card.querySelector(".warehouse-status");
    const label = button.querySelector(".organize-label");
    const hint = card.querySelector(".interaction-hint");

    const render = (organized) => {
      card.classList.toggle("organized", organized);
      button.setAttribute("aria-pressed", String(organized));
      button.setAttribute(
        "aria-label",
        organized ? "بازگرداندن انبار به حالت نامرتب" : "مرتب‌سازی هوشمند انبار",
      );

      if (status) {
        status.textContent = organized ? "مرتب و به‌روز" : "نیازمند نظم‌دهی";
      }
      if (label) {
        label.textContent = organized ? "بازگرداندن انبار" : "مرتب‌سازی هوشمند انبار";
      }
      if (hint) {
        hint.textContent = organized
          ? "همه کالاها در جای درست قرار گرفتند"
          : "برای دیدن قدرت سبلان کلیک کنید";
      }
    };

    render(card.classList.contains("organized"));
    button.addEventListener("click", () => {
      render(!card.classList.contains("organized"));
    });
  }

  function initializeShowcaseTabs() {
    const tablist = document.querySelector(".showcase-tabs");
    if (!tablist) return;

    const tabs = Array.from(tablist.querySelectorAll('[role="tab"][data-tab]'));
    const panels = Array.from(document.querySelectorAll(".showcase-panel[role='tabpanel']"));
    if (!tabs.length || !panels.length) return;

    const panelFor = (tab) =>
      panels.find((panel) => panel.id === `panel-${tab.dataset.tab}`);

    tabs.forEach((tab, index) => {
      const panel = panelFor(tab);
      if (!panel) {
        tab.disabled = true;
        return;
      }

      if (!tab.id) tab.id = `showcase-tab-${tab.dataset.tab || index + 1}`;
      tab.setAttribute("aria-controls", panel.id);
      panel.setAttribute("aria-labelledby", tab.id);
    });

    const activateTab = (selectedTab, moveFocus = false) => {
      if (selectedTab.disabled || !panelFor(selectedTab)) return;

      tabs.forEach((tab) => {
        const selected = tab === selectedTab;
        tab.classList.toggle("active", selected);
        tab.setAttribute("aria-selected", String(selected));
        tab.tabIndex = selected ? 0 : -1;
      });

      panels.forEach((panel) => {
        const selected = panel === panelFor(selectedTab);
        panel.classList.toggle("active", selected);
        panel.hidden = !selected;
      });

      if (moveFocus) selectedTab.focus();
    };

    const initialTab =
      tabs.find(
        (tab) => tab.getAttribute("aria-selected") === "true" && !tab.disabled,
      ) || tabs.find((tab) => !tab.disabled);

    if (initialTab) activateTab(initialTab);

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => activateTab(tab));
      tab.addEventListener("keydown", (event) => {
        const enabledTabs = tabs.filter((item) => !item.disabled && panelFor(item));
        const currentIndex = enabledTabs.indexOf(tab);
        if (currentIndex < 0) return;

        let nextIndex;
        if (event.key === "ArrowLeft" || event.key === "ArrowDown") {
          nextIndex = (currentIndex + 1) % enabledTabs.length;
        } else if (event.key === "ArrowRight" || event.key === "ArrowUp") {
          nextIndex = (currentIndex - 1 + enabledTabs.length) % enabledTabs.length;
        } else if (event.key === "Home") {
          nextIndex = 0;
        } else if (event.key === "End") {
          nextIndex = enabledTabs.length - 1;
        } else {
          return;
        }

        event.preventDefault();
        activateTab(enabledTabs[nextIndex], true);
      });
    });
  }

  function initializeLightbox() {
    const dialog = document.querySelector("#lightbox");
    const image = dialog?.querySelector(".lightbox-content img");
    const closeButton = dialog?.querySelector(".lightbox-close");
    if (!dialog || !image || !closeButton) return;

    let opener = null;

    const safeImageUrl = (source) => {
      if (!source) return null;

      try {
        const url = new URL(source, document.baseURI);
        const allowedProtocol =
          url.protocol === "https:" ||
          url.protocol === "http:" ||
          (url.protocol === "file:" && window.location.protocol === "file:");
        return allowedProtocol && url.origin === window.location.origin ? url.href : null;
      } catch {
        return null;
      }
    };

    const close = () => {
      if (dialog.open) dialog.close();
    };

    document.querySelectorAll(".image-zoom[data-image]").forEach((button) => {
      button.addEventListener("click", () => {
        const source = safeImageUrl(button.dataset.image);
        if (!source) return;

        opener = button;
        image.src = source;
        image.alt = button.dataset.alt?.trim() || "";

        if (typeof dialog.showModal === "function") {
          if (!dialog.open) dialog.showModal();
        } else {
          dialog.setAttribute("open", "");
        }
        closeButton.focus();
      });
    });

    closeButton.addEventListener("click", close);
    dialog.addEventListener("click", (event) => {
      if (event.target === dialog) close();
    });
    dialog.addEventListener("cancel", (event) => {
      event.preventDefault();
      close();
    });
    dialog.addEventListener("close", () => {
      image.removeAttribute("src");
      image.alt = "";
      opener?.focus();
      opener = null;
    });
  }

  function initializeAccordion() {
    const accordions = document.querySelectorAll(".accordion");

    accordions.forEach((accordion, accordionIndex) => {
      const items = Array.from(accordion.querySelectorAll(".accordion-item"));

      items.forEach((item, itemIndex) => {
        const button = item.querySelector(":scope > button");
        const content = item.querySelector(":scope > .accordion-content");
        if (!button || !content) return;

        const idBase = `accordion-${accordionIndex + 1}-${itemIndex + 1}`;
        if (!button.id) button.id = `${idBase}-button`;
        if (!content.id) content.id = `${idBase}-content`;
        button.setAttribute("aria-controls", content.id);
        content.setAttribute("role", "region");
        content.setAttribute("aria-labelledby", button.id);

        const expanded =
          item.classList.contains("open") ||
          button.getAttribute("aria-expanded") === "true";
        item.classList.toggle("open", expanded);
        button.setAttribute("aria-expanded", String(expanded));
        content.hidden = !expanded;

        button.addEventListener("click", () => {
          const shouldOpen = button.getAttribute("aria-expanded") !== "true";

          items.forEach((otherItem) => {
            const otherButton = otherItem.querySelector(":scope > button");
            const otherContent = otherItem.querySelector(
              ":scope > .accordion-content",
            );
            if (!otherButton || !otherContent) return;

            const open = otherItem === item && shouldOpen;
            otherItem.classList.toggle("open", open);
            otherButton.setAttribute("aria-expanded", String(open));
            otherContent.hidden = !open;
          });
        });
      });
    });
  }

  function initializeReveal() {
    const elements = Array.from(document.querySelectorAll(".reveal"));
    if (!elements.length) return;

    const reveal = (element) => {
      element.classList.add("visible");
    };

    const reduceMotion = window.matchMedia?.(
      "(prefers-reduced-motion: reduce)",
    ).matches;

    if (reduceMotion || !("IntersectionObserver" in window)) {
      elements.forEach(reveal);
      return;
    }

    elements.forEach((element) => {
      const delay = Number.parseInt(element.dataset.delay || "0", 10);
      if (Number.isFinite(delay) && delay > 0) {
        element.style.transitionDelay = `${Math.min(delay, 2000)}ms`;
      }
    });

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          reveal(entry.target);
          observer.unobserve(entry.target);
        });
      },
      {
        threshold: 0.12,
        rootMargin: "0px 0px -40px",
      },
    );

    elements.forEach((element) => observer.observe(element));
  }

  function initializeScrollState() {
    const header = document.querySelector(".site-header");
    const backToTop = document.querySelector(".back-to-top");
    if (!header && !backToTop) return;

    let updatePending = false;

    const update = () => {
      const scrollTop = window.scrollY || root.scrollTop || 0;
      header?.classList.toggle("scrolled", scrollTop > 24);
      backToTop?.classList.toggle("visible", scrollTop > 600);
      updatePending = false;
    };

    const requestUpdate = () => {
      if (updatePending) return;
      updatePending = true;
      window.requestAnimationFrame(update);
    };

    window.addEventListener("scroll", requestUpdate, { passive: true });
    update();

    backToTop?.addEventListener("click", () => {
      window.scrollTo({
        top: 0,
        behavior: window.matchMedia?.("(prefers-reduced-motion: reduce)").matches
          ? "auto"
          : "smooth",
      });
    });
  }

  initializeTheme();
  initializeWarehouse();
  initializeShowcaseTabs();
  initializeLightbox();
  initializeAccordion();
  initializeReveal();
  initializeScrollState();
})();
