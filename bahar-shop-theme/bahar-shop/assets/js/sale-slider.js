(function () {
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var cfg = window.baharSaleSlider || { speed: 35 };
  var speed = Math.max(10, Math.min(120, parseInt(cfg.speed, 10) || 35));

  function init() {
    var root = document.querySelector('[data-bahar-sale-slider]');
    if (!root || reduce) {
      return;
    }

    var track = root.querySelector('.sale-slider__grid');
    if (!track || track.dataset.ready === '1') {
      return;
    }

    var items = Array.prototype.slice.call(track.children);
    if (items.length < 2) {
      return;
    }

    items.forEach(function (node) {
      var clone = node.cloneNode(true);
      clone.setAttribute('aria-hidden', 'true');
      clone.setAttribute('tabindex', '-1');
      track.appendChild(clone);
    });

    track.dataset.ready = '1';

    var gap = parseFloat(window.getComputedStyle(track).gap) || 0;
    var distance = 0;
    items.forEach(function (el) {
      distance += el.offsetWidth + gap;
    });

    if (distance < 40) {
      return;
    }

    var rtl = window.getComputedStyle(track).direction === 'rtl';
    var pos = 0;
    var paused = false;
    var last = performance.now();

    function tick(now) {
      if (!paused) {
        var delta = (now - last) / 1000;
        pos += delta * (distance / speed);
        if (pos >= distance) {
          pos -= distance;
        }
        track.style.transform = 'translateX(' + (rtl ? pos : -pos) + 'px)';
      }
      last = now;
      requestAnimationFrame(tick);
    }

    root.addEventListener('mouseenter', function () {
      paused = true;
    });
    root.addEventListener('mouseleave', function () {
      paused = false;
      last = performance.now();
    });
    root.addEventListener('touchstart', function () {
      paused = true;
    }, { passive: true });
    root.addEventListener('touchend', function () {
      paused = false;
      last = performance.now();
    }, { passive: true });

    requestAnimationFrame(tick);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
