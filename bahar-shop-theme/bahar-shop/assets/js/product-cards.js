(function () {
  function initCardGallery(root) {
    if (!root || root.dataset.baharReady === '1') {
      return;
    }

    var slides = root.querySelectorAll('.bahar-product-card__slide');
    if (slides.length < 2) {
      return;
    }

    root.dataset.baharReady = '1';

    var index = 0;
    var dots = root.querySelectorAll('.bahar-product-card__dot');
    var prev = root.querySelector('.bahar-product-card__nav--prev');
    var next = root.querySelector('.bahar-product-card__nav--next');
    var touchStartX = 0;

    function show(i) {
      index = (i + slides.length) % slides.length;
      slides.forEach(function (slide, n) {
        slide.classList.toggle('is-active', n === index);
      });
      dots.forEach(function (dot, n) {
        dot.classList.toggle('is-active', n === index);
      });
    }

    if (prev) {
      prev.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        show(index - 1);
      });
    }

    if (next) {
      next.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        show(index + 1);
      });
    }

    root.addEventListener(
      'touchstart',
      function (e) {
        if (!e.changedTouches || !e.changedTouches.length) {
          return;
        }
        touchStartX = e.changedTouches[0].clientX;
      },
      { passive: true }
    );

    root.addEventListener(
      'touchend',
      function (e) {
        if (!e.changedTouches || !e.changedTouches.length) {
          return;
        }
        var dx = e.changedTouches[0].clientX - touchStartX;
        if (Math.abs(dx) < 40) {
          return;
        }
        if (dx > 0) {
          show(index - 1);
        } else {
          show(index + 1);
        }
      },
      { passive: true }
    );
  }

  function boot() {
    document.querySelectorAll('[data-bahar-card-gallery]').forEach(initCardGallery);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
