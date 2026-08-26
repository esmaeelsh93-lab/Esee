(function () {
  var defaultSlides = [];

  function collectSlides(gallery) {
    var wrapper = gallery.querySelector('.woocommerce-product-gallery__wrapper');
    if (!wrapper) {
      return [];
    }
    return Array.prototype.slice.call(
      wrapper.querySelectorAll('.woocommerce-product-gallery__image img')
    );
  }

  function setMainImage(main, img) {
    if (!main || !img) {
      return;
    }
    main.src = img.src;
    main.srcset = img.srcset || '';
    main.alt = img.alt || '';
    main.sizes = img.sizes || '';
  }

  function initGallery(gallery) {
    if (!gallery || gallery.dataset.baharReady === '1') {
      return;
    }

    var wrapper = gallery.querySelector('.woocommerce-product-gallery__wrapper');
    if (!wrapper) {
      return;
    }

    var slides = wrapper.querySelectorAll('.woocommerce-product-gallery__image');
    if (!slides.length) {
      return;
    }

    gallery.dataset.baharReady = '1';
    defaultSlides = collectSlides(gallery);

    var currentIndex = 0;
    var slideImgs = [];

    var main = document.createElement('div');
    main.className = 'bahar-gallery-main';
    var mainImg = slides[0].querySelector('img');
    if (mainImg) {
      var hero = mainImg.cloneNode(true);
      hero.className = 'bahar-gallery-main__img';
      hero.removeAttribute('loading');
      main.appendChild(hero);
    }

    var thumbs = document.createElement('div');
    thumbs.className = 'bahar-gallery-thumbs';

    function goTo(index) {
      if (!slideImgs.length) {
        return;
      }
      currentIndex = (index + slideImgs.length) % slideImgs.length;
      var active = main.querySelector('.bahar-gallery-main__img');
      setMainImage(active, slideImgs[currentIndex]);
      thumbs.querySelectorAll('.bahar-gallery-thumb').forEach(function (el, n) {
        el.classList.toggle('is-active', n === currentIndex);
      });
    }

    slides.forEach(function (slide, index) {
      var img = slide.querySelector('img');
      if (!img) {
        return;
      }
      slideImgs.push(img);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'bahar-gallery-thumb' + (index === 0 ? ' is-active' : '');
      btn.setAttribute('aria-label', 'تصویر ' + (index + 1));
      var thumbImg = img.cloneNode(true);
      btn.appendChild(thumbImg);
      btn.addEventListener('click', function () {
        goTo(index);
      });
      thumbs.appendChild(btn);
    });

    if (slideImgs.length > 1) {
      var prev = document.createElement('button');
      prev.type = 'button';
      prev.className = 'bahar-gallery-nav bahar-gallery-nav--prev';
      prev.setAttribute('aria-label', 'عکس قبلی');
      prev.textContent = '‹';
      prev.addEventListener('click', function (e) {
        e.preventDefault();
        goTo(currentIndex - 1);
      });

      var next = document.createElement('button');
      next.type = 'button';
      next.className = 'bahar-gallery-nav bahar-gallery-nav--next';
      next.setAttribute('aria-label', 'عکس بعدی');
      next.textContent = '›';
      next.addEventListener('click', function (e) {
        e.preventDefault();
        goTo(currentIndex + 1);
      });

      main.appendChild(prev);
      main.appendChild(next);

      var touchStartX = 0;
      main.addEventListener(
        'touchstart',
        function (e) {
          if (!e.changedTouches || !e.changedTouches.length) {
            return;
          }
          touchStartX = e.changedTouches[0].clientX;
        },
        { passive: true }
      );
      main.addEventListener(
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
            goTo(currentIndex - 1);
          } else {
            goTo(currentIndex + 1);
          }
        },
        { passive: true }
      );
    }

    wrapper.innerHTML = '';
    wrapper.appendChild(main);
    wrapper.appendChild(thumbs);
    gallery.classList.add('bahar-gallery-ready');
    gallery.baharMainImg = main.querySelector('.bahar-gallery-main__img');
    gallery.baharThumbs = thumbs;
    gallery.baharGoTo = goTo;
    gallery.baharSlideImgs = slideImgs;
  }

  function updateFromVariation(variation) {
    var gallery = document.querySelector('.woocommerce-product-gallery.bahar-gallery-ready');
    if (!gallery || !gallery.baharMainImg || !variation || !variation.image || !variation.image.src) {
      return;
    }

    gallery.baharMainImg.src = variation.image.src;
    gallery.baharMainImg.srcset = variation.image.srcset || '';
    gallery.baharMainImg.alt = variation.image.alt || '';
    gallery.baharMainImg.sizes = variation.image.sizes || '';

    if (gallery.baharThumbs) {
      gallery.baharThumbs.querySelectorAll('.bahar-gallery-thumb').forEach(function (el) {
        el.classList.remove('is-active');
      });
    }
  }

  function resetGallery() {
    var gallery = document.querySelector('.woocommerce-product-gallery.bahar-gallery-ready');
    if (!gallery || !gallery.baharMainImg || !defaultSlides.length) {
      return;
    }

    setMainImage(gallery.baharMainImg, defaultSlides[0]);
    if (gallery.baharThumbs) {
      gallery.baharThumbs.querySelectorAll('.bahar-gallery-thumb').forEach(function (el, index) {
        el.classList.toggle('is-active', index === 0);
      });
    }
  }

  document.querySelectorAll('.woocommerce-product-gallery').forEach(initGallery);

  if (typeof jQuery !== 'undefined') {
    jQuery(function ($) {
      $('form.variations_form').on('found_variation', function (event, variation) {
        updateFromVariation(variation);
      });

      $('form.variations_form').on('reset_data hide_variation', function () {
        resetGallery();
      });

      $(document.body).on('wc_variation_form', 'form.variations_form', function () {
        var gallery = document.querySelector('.woocommerce-product-gallery');
        if (gallery && gallery.dataset.baharReady !== '1') {
          initGallery(gallery);
        }
      });
    });
  }
})();
