(function () {
  if (typeof gsap === 'undefined') {
    return;
  }

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.documentElement.classList.add('js-motion');

  if (gsap.registerPlugin && typeof ScrollTrigger !== 'undefined') {
    gsap.registerPlugin(ScrollTrigger);
  }

  function revealNow(nodes) {
    gsap.set(nodes, { clearProps: 'all', autoAlpha: 1, y: 0, x: 0, scale: 1 });
  }

  function initHero() {
    var hero = document.querySelector('[data-bahar-hero].hero--photo');
    if (!hero || reduce) {
      return;
    }

    var brand = hero.querySelector('.hero__brand');
    var tag = hero.querySelector('.hero__tagline');
    var cta = hero.querySelector('.hero__cta');

    if (brand) {
      gsap.from(brand, { y: 18, autoAlpha: 0, duration: 0.55, ease: 'power2.out' });
    }
    if (tag) {
      gsap.from(tag, { y: 14, autoAlpha: 0, duration: 0.5, delay: 0.08, ease: 'power2.out' });
    }
    if (cta) {
      gsap.from(cta, { y: 10, autoAlpha: 0, duration: 0.45, delay: 0.16, ease: 'power2.out' });
    }
  }

  function initMobileCatHint() {
    if (reduce || window.matchMedia('(min-width: 901px)').matches) {
      return;
    }

    var track = document.querySelector('[data-bahar-cats]');
    var grid = track ? track.querySelector('.categories-grid') : null;
    if (!track || !grid || track.dataset.hintDone === '1') {
      return;
    }

    if (grid.scrollWidth <= grid.clientWidth + 8) {
      return;
    }

    track.dataset.hintDone = '1';
    track.classList.add('is-scroll-hint');

    window.setTimeout(function () {
      track.classList.remove('is-scroll-hint');
    }, 3200);
  }

  function initReveals() {
    if (reduce) {
      revealNow('.categories-grid .cat-card, .bahar-product-card');
      return;
    }

    var cats = document.querySelectorAll('.categories-grid .cat-card');

    if (cats.length && typeof ScrollTrigger !== 'undefined') {
      ScrollTrigger.batch(cats, {
        start: 'top 90%',
        once: true,
        onEnter: function (batch) {
          gsap.from(batch, {
            y: 18,
            duration: 0.55,
            stagger: 0.06,
            ease: 'power2.out',
            clearProps: 'transform',
            overwrite: true,
          });
        },
      });
    } else {
      revealNow(cats);
    }

    var cards = document.querySelectorAll('.bahar-product-card');
    if (cards.length && typeof ScrollTrigger !== 'undefined') {
      ScrollTrigger.batch(cards, {
        start: 'top 92%',
        once: true,
        onEnter: function (batch) {
          gsap.from(batch, {
            y: 22,
            duration: 0.6,
            stagger: 0.07,
            ease: 'power2.out',
            clearProps: 'transform',
            overwrite: true,
          });
        },
      });
    } else {
      revealNow(cards);
    }
  }

  function failSafe() {
    window.setTimeout(function () {
      var hidden = document.querySelectorAll(
        '.bahar-product-card, .categories-grid .cat-card, .hero--photo .hero__brand, .hero--photo .hero__tagline, .hero--photo .hero__cta'
      );
      hidden.forEach(function (el) {
        var style = window.getComputedStyle(el);
        if (style.opacity === '0' || style.visibility === 'hidden') {
          el.style.opacity = '1';
          el.style.visibility = 'visible';
          el.style.transform = 'none';
        }
      });
    }, 2800);
  }

  function start() {
    try {
      initHero();
      initMobileCatHint();
      initReveals();
    } catch (e) {
      revealNow('.categories-grid .cat-card, .bahar-product-card, .hero--photo .hero__brand, .hero--photo .hero__tagline');
    }
    failSafe();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
